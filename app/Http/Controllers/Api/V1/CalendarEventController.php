<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\TimeEntryType;
use App\Exceptions\Api\OutlookNotConnectedApiException;
use App\Exceptions\Api\OutlookReconnectRequiredApiException;
use App\Exceptions\Api\OutlookUnavailableApiException;
use App\Http\Requests\V1\CalendarEvent\CalendarEventIgnoreRequest;
use App\Http\Requests\V1\CalendarEvent\CalendarEventImportRequest;
use App\Http\Requests\V1\CalendarEvent\CalendarEventIndexRequest;
use App\Jobs\RecalculateSpentTimeForProject;
use App\Jobs\RecalculateSpentTimeForTask;
use App\Models\CalendarImport;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OutlookConnection;
use App\Models\Project;
use App\Models\TimeEntry;
use App\Service\OutlookCalendarService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Import from calendar" (Outlook). Always the logged-in user's OWN calendar and time entries;
 * requires the same permission as creating an own time entry (time-entries:create:own).
 */
class CalendarEventController extends Controller
{
    /**
     * Get calendar events
     *
     * Meetings of the logged-in user's Outlook calendar (cancelled, all-day, declined and "free" are left out).
     * Without start/end: from the day of the last import (or 7 days ago) until today, in the user's timezone.
     * Meetings already imported or ignored come flagged.
     *
     * @throws AuthorizationException|OutlookNotConnectedApiException|OutlookReconnectRequiredApiException|OutlookUnavailableApiException
     *
     * @operationId getCalendarEvents
     */
    public function index(Organization $organization, CalendarEventIndexRequest $request, OutlookCalendarService $outlook): JsonResponse
    {
        abort_unless($outlook->isEnabled(), 404);
        $this->checkPermission($organization, 'time-entries:create:own');
        $member = $this->member($organization);
        $user = $this->user();

        $connection = OutlookConnection::query()->where('user_id', $user->getKey())->first();
        if ($connection === null) {
            throw new OutlookNotConnectedApiException;
        }

        $timezone = $this->timezone($user->timezone);
        [$startDate, $endDate] = $this->period($request, $connection, $timezone);
        $rangeStart = Carbon::createFromFormat('Y-m-d', $startDate, $timezone)->startOfDay();
        $rangeEnd = Carbon::createFromFormat('Y-m-d', $endDate, $timezone)->addDay()->startOfDay();

        $events = $outlook->listEvents($connection, $rangeStart, $rangeEnd, $timezone);

        /** @var array<string, CalendarImport> $handled */
        $handled = CalendarImport::query()
            ->where('member_id', $member->getKey())
            ->whereIn('event_key', array_map(fn (array $event): string => $event['event_key'], $events))
            ->get()
            ->keyBy('event_key')
            ->all();

        $suggestions = [];
        $data = [];
        $newCount = 0;
        foreach ($events as $event) {
            $import = $handled[$event['event_key']] ?? null;
            $alreadyImported = $import !== null && $import->isImported();
            $status = $alreadyImported ? CalendarImport::STATUS_IMPORTED
                : ($import !== null && $import->status === CalendarImport::STATUS_IGNORED ? CalendarImport::STATUS_IGNORED : null);
            if ($status === null) {
                $newCount++;
            }
            $subjectKey = mb_strtolower($event['subject']);
            if (! array_key_exists($subjectKey, $suggestions)) {
                $suggestions[$subjectKey] = $this->suggest($organization, $member, $event['subject']);
            }
            $suggestion = $suggestions[$subjectKey];

            $data[] = [
                'event_key' => $event['event_key'],
                'subject' => $event['subject'],
                'start' => $event['start']->format('Y-m-d\TH:i:s\Z'),
                'end' => $event['end']->format('Y-m-d\TH:i:s\Z'),
                'duration' => $event['duration'],
                'organizer' => $event['organizer'],
                'is_online_meeting' => $event['is_online_meeting'],
                'already_imported' => $alreadyImported,
                'status' => $status,
                'time_entry_id' => $alreadyImported ? $import->time_entry_id : null,
                'suggested_project_id' => $suggestion['project_id'],
                'suggested_task_id' => $suggestion['task_id'],
                'suggested_tags' => $suggestion['tags'],
            ];
        }

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'start' => $startDate,
                'end' => $endDate,
                'timezone' => $timezone,
                'new_count' => $newCount,
                'last_import_at' => $connection->last_import_at?->format('Y-m-d\TH:i:s\Z'),
                'account_email' => $connection->account_email,
            ],
        ]);
    }

    /**
     * Import calendar events
     *
     * Creates normal work time entries (not billable) for the logged-in member, one per meeting.
     * Meetings already imported are skipped; with "prevent overlapping" on, overlapping meetings are skipped too.
     *
     * @throws AuthorizationException
     *
     * @operationId importCalendarEvents
     */
    public function import(Organization $organization, CalendarEventImportRequest $request, OutlookCalendarService $outlook): JsonResponse
    {
        abort_unless($outlook->isEnabled(), 404);
        $this->checkPermission($organization, 'time-entries:create:own');
        $member = $this->member($organization);

        $items = [];
        foreach ($request->getItems() as $item) {
            $items[$item['event_key']] = $item; // the same meeting repeated in the request counts once
        }

        $timeEntryIds = [];
        $skipped = [];
        $projects = [];
        $tasks = [];
        DB::transaction(function () use ($organization, $member, $items, &$timeEntryIds, &$skipped, &$projects, &$tasks): void {
            /** @var array<string, CalendarImport> $existing */
            $existing = CalendarImport::query()
                ->where('member_id', $member->getKey())
                ->whereIn('event_key', array_keys($items))
                ->lockForUpdate()
                ->get()
                ->keyBy('event_key')
                ->all();

            foreach ($items as $eventKey => $item) {
                $import = $existing[$eventKey] ?? null;
                if ($import !== null && $import->isImported()) {
                    $skipped[] = ['event_key' => $eventKey, 'reason' => 'already_imported'];

                    continue;
                }
                $start = Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $item['start'], 'UTC');
                $end = Carbon::createFromFormat('Y-m-d\TH:i:s\Z', $item['end'], 'UTC');
                if ($this->overlaps($organization, $member, $start, $end)) {
                    $skipped[] = ['event_key' => $eventKey, 'reason' => 'overlapping_time_entry'];

                    continue;
                }

                $project = $item['project_id'] !== null ? Project::query()->findOrFail($item['project_id']) : null;
                $task = $item['task_id'] !== null && $project !== null ? $project->tasks()->findOrFail($item['task_id']) : null;

                $timeEntry = new TimeEntry;
                $timeEntry->description = $item['description'] ?? '';
                $timeEntry->start = $start;
                $timeEntry->end = $end;
                $timeEntry->billable = false;
                $timeEntry->type = TimeEntryType::Work;
                $timeEntry->tags = $item['tags'];
                $timeEntry->member()->associate($member);
                $timeEntry->user_id = $member->user_id;
                $timeEntry->organization()->associate($organization);
                $timeEntry->project()->associate($project);
                $timeEntry->task()->associate($task);
                $timeEntry->client()->associate($project?->client);
                $timeEntry->setComputedAttributeValue('billable_rate');
                $timeEntry->save();
                $timeEntryIds[] = $timeEntry->getKey();

                $import ??= new CalendarImport;
                $import->organization_id = $organization->getKey();
                $import->member_id = $member->getKey();
                $import->event_key = $eventKey;
                $import->status = CalendarImport::STATUS_IMPORTED;
                $import->time_entry_id = $timeEntry->getKey();
                $import->save();

                if ($project !== null) {
                    $projects[$project->getKey()] = $project;
                }
                if ($task !== null) {
                    $tasks[$task->getKey()] = $task;
                }
            }
        });

        foreach ($projects as $project) {
            RecalculateSpentTimeForProject::dispatch($project);
        }
        foreach ($tasks as $task) {
            RecalculateSpentTimeForTask::dispatch($task);
        }
        if ($timeEntryIds !== []) {
            OutlookConnection::query()->where('user_id', $member->user_id)->update(['last_import_at' => Carbon::now()]);
        }

        return new JsonResponse([
            'data' => [
                'imported' => count($timeEntryIds),
                'time_entry_ids' => $timeEntryIds,
                'skipped' => $skipped,
            ],
        ]);
    }

    /**
     * Ignore calendar events
     *
     * Marks meetings as ignored, so they no longer count as new. Meetings already imported stay imported.
     *
     * @throws AuthorizationException
     *
     * @operationId ignoreCalendarEvents
     */
    public function ignore(Organization $organization, CalendarEventIgnoreRequest $request, OutlookCalendarService $outlook): JsonResponse
    {
        abort_unless($outlook->isEnabled(), 404);
        $this->checkPermission($organization, 'time-entries:create:own');
        $member = $this->member($organization);
        $keys = $request->getEventKeys();

        $ignored = 0;
        DB::transaction(function () use ($organization, $member, $keys, &$ignored): void {
            /** @var array<string, CalendarImport> $existing */
            $existing = CalendarImport::query()
                ->where('member_id', $member->getKey())
                ->whereIn('event_key', $keys)
                ->lockForUpdate()
                ->get()
                ->keyBy('event_key')
                ->all();

            foreach ($keys as $eventKey) {
                $import = $existing[$eventKey] ?? null;
                if ($import !== null && $import->isImported()) {
                    continue;
                }
                $import ??= new CalendarImport;
                $import->organization_id = $organization->getKey();
                $import->member_id = $member->getKey();
                $import->event_key = $eventKey;
                $import->status = CalendarImport::STATUS_IGNORED;
                $import->time_entry_id = null;
                $import->save();
                $ignored++;
            }
        });

        return new JsonResponse(['data' => ['ignored' => $ignored]]);
    }

    /**
     * @return array{0: string, 1: string} Y-m-d days in the user's time zone
     */
    private function period(CalendarEventIndexRequest $request, OutlookConnection $connection, string $timezone): array
    {
        $today = Carbon::now($timezone)->startOfDay();
        $defaultDays = max(1, (int) config('services.entra_calendar.default_days', 7));
        $maxDays = max(1, (int) config('services.entra_calendar.max_days', 62));

        $end = $request->getEndDate() !== null
            ? Carbon::createFromFormat('Y-m-d', $request->getEndDate(), $timezone)->startOfDay()
            : null;
        if ($request->getStartDate() !== null) {
            $start = Carbon::createFromFormat('Y-m-d', $request->getStartDate(), $timezone)->startOfDay();
            $end ??= $today->greaterThan($start) ? $today : $start->copy();
        } else {
            $end ??= $today;
            $start = $connection->last_import_at !== null && $request->getEndDate() === null
                ? $connection->last_import_at->copy()->setTimezone($timezone)->startOfDay()
                : $end->copy()->subDays($defaultDays);
            if ($start->greaterThan($end)) {
                $start = $end->copy();
            }
            if ($start->diffInDays($end) > $maxDays) {
                $start = $end->copy()->subDays($maxDays);
            }
        }

        return [$start->format('Y-m-d'), $end->format('Y-m-d')];
    }

    /**
     * The project the member used most in time entries whose description equals (or contains) the subject,
     * with the task and tags of that project's most recent entry.
     *
     * @return array{project_id: string|null, task_id: string|null, tags: list<string>}
     */
    private function suggest(Organization $organization, Member $member, string $subject): array
    {
        $none = ['project_id' => null, 'task_id' => null, 'tags' => []];
        $subject = trim($subject);
        if (mb_strlen($subject) < 3) {
            return $none;
        }
        $lower = mb_strtolower($subject);
        $like = '%'.addcslashes($lower, '%_\\').'%';

        $matchers = [
            fn (Builder $query): Builder => $query->whereRaw('lower(time_entries.description) = ?', [$lower]),
            fn (Builder $query): Builder => $query->whereRaw('lower(time_entries.description) like ?', [$like]),
        ];
        foreach ($matchers as $matcher) {
            $base = fn (): Builder => $matcher(TimeEntry::query()
                ->where('time_entries.organization_id', $organization->getKey())
                ->where('time_entries.member_id', $member->getKey())
                ->where('time_entries.type', TimeEntryType::Work->value)
                ->whereNotNull('time_entries.project_id')
                ->where('time_entries.start', '>=', Carbon::now()->subYear())
                ->whereHas('project', fn (Builder $project): Builder => $project->whereNull('archived_at')));

            /** @var object{project_id: string}|null $best */
            $best = $base()
                ->select('time_entries.project_id')
                ->selectRaw('count(*) as uses, max(time_entries.start) as last_start')
                ->groupBy('time_entries.project_id')
                ->orderByDesc('uses')
                ->orderByDesc('last_start')
                ->toBase()
                ->first();
            if ($best === null) {
                continue;
            }

            /** @var TimeEntry|null $latest */
            $latest = $base()
                ->where('time_entries.project_id', $best->project_id)
                ->orderByDesc('time_entries.start')
                ->first();

            return [
                'project_id' => (string) $best->project_id,
                'task_id' => $latest?->task_id,
                'tags' => array_values($latest->tags ?? []),
            ];
        }

        return $none;
    }

    /**
     * Same rule as TimeEntryController when the organization prevents overlapping time entries.
     */
    private function overlaps(Organization $organization, Member $member, Carbon $start, Carbon $end): bool
    {
        if (! $organization->prevent_overlapping_time_entries) {
            return false;
        }

        return TimeEntry::query()
            ->where('organization_id', $organization->getKey())
            ->where('member_id', $member->getKey())
            ->where(function (Builder $query) use ($start, $end): void {
                $query->where(fn (Builder $q): Builder => $q->where('end', '>', $start)->where('start', '<', $start))
                    ->orWhere(fn (Builder $q): Builder => $q->where('start', '<', $end)->where('end', '>', $end))
                    ->orWhere(fn (Builder $q): Builder => $q->where('start', '>=', $start)->where('end', '<=', $end));
            })
            ->exists();
    }

    private function timezone(?string $timezone): string
    {
        return is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)
            ? $timezone
            : (string) config('app.timezone', 'UTC');
    }
}
