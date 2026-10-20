<?php

declare(strict_types=1);

namespace App\Http\Requests\V1\CalendarEvent;

use App\Http\Requests\V1\BaseFormRequest;
use App\Models\Organization;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Service\OutlookCalendarService;
use App\Service\PermissionStore;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Korridor\LaravelModelValidationRules\Rules\ExistsEloquent;

/**
 * Import Outlook meetings as time entries of the logged-in member.
 * Same project/task/tag rules as creating a time entry.
 *
 * @property Organization $organization Organization from model binding
 */
class CalendarEventImportRequest extends BaseFormRequest
{
    // disabled (ENTRA_CALENDAR_ENABLED=false) answers 404 before validating
    protected function prepareForValidation(): void
    {
        abort_unless(app(OutlookCalendarService::class)->isEnabled(), 404);
    }

    /**
     * @return array<string, array<string|\Closure|ValidationRule|Rule>>
     */
    public function rules(): array
    {
        return [
            'items' => [
                'required',
                'array',
                'min:1',
                'max:200',
            ],
            // Key of the meeting as returned by the list endpoint (iCalUId + start)
            'items.*.event_key' => [
                'required',
                'string',
                'max:512',
            ],
            // Description of the time entry (default: subject of the meeting)
            'items.*.description' => [
                'nullable',
                'string',
                'max:5000',
            ],
            // Start (Format: "Y-m-d\TH:i:s\Z", UTC)
            'items.*.start' => [
                'required',
                'date_format:Y-m-d\TH:i:s\Z',
            ],
            // End (Format: "Y-m-d\TH:i:s\Z", UTC), after start
            'items.*.end' => [
                'required',
                'date_format:Y-m-d\TH:i:s\Z',
                'after:items.*.start',
            ],
            // Project of the time entry (same visibility rules as creating a time entry)
            'items.*.project_id' => [
                'nullable',
                'string',
                'required_with:items.*.task_id',
                ExistsEloquent::make(Project::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Project> $builder */
                    $builder = $builder->whereBelongsTo($this->organization, 'organization');

                    $permissionStore = app(PermissionStore::class);
                    if (! $permissionStore->has($this->organization, 'time-entries:create:all')
                        && ! $permissionStore->has($this->organization, 'projects:view:all')) {
                        $builder = $builder->visibleByEmployee(Auth::user());
                    }

                    return $builder;
                })->uuid(),
            ],
            // Task of the time entry; must belong to the project
            'items.*.task_id' => [
                'nullable',
                'string',
                ExistsEloquent::make(Task::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Task> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value)) {
                        return;
                    }
                    $index = explode('.', $attribute)[1] ?? '';
                    $projectId = $this->input('items.'.$index.'.project_id');
                    if (! is_string($projectId) || ! Task::query()->whereKey($value)->where('project_id', $projectId)->exists()) {
                        $fail(__('validation.task_belongs_to_project'));
                    }
                },
            ],
            'items.*.tags' => [
                'nullable',
                'array',
            ],
            'items.*.tags.*' => [
                'string',
                ExistsEloquent::make(Tag::class, null, function (Builder $builder): Builder {
                    /** @var Builder<Tag> $builder */
                    return $builder->whereBelongsTo($this->organization, 'organization');
                })->uuid(),
            ],
        ];
    }

    /**
     * @return list<array{event_key: string, description: string|null, start: string, end: string, project_id: string|null, task_id: string|null, tags: list<string>}>
     */
    public function getItems(): array
    {
        /** @var array<int, array<string, mixed>> $items */
        $items = $this->validated('items');
        $result = [];
        foreach ($items as $item) {
            $tags = is_array($item['tags'] ?? null) ? $item['tags'] : [];
            $result[] = [
                'event_key' => (string) $item['event_key'],
                'description' => is_string($item['description'] ?? null) ? $item['description'] : null,
                'start' => (string) $item['start'],
                'end' => (string) $item['end'],
                'project_id' => is_string($item['project_id'] ?? null) ? $item['project_id'] : null,
                'task_id' => is_string($item['task_id'] ?? null) ? $item['task_id'] : null,
                'tags' => array_values(array_unique(array_map(fn (mixed $tag): string => (string) $tag, $tags))),
            ];
        }

        return $result;
    }
}
