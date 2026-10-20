<?php

declare(strict_types=1);

namespace Tests\Unit\Endpoint\Api\V1;

use App\Http\Controllers\Api\V1\CalendarEventController;
use App\Models\CalendarImport;
use App\Models\Member;
use App\Models\Organization;
use App\Models\OutlookConnection;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Service\OutlookCalendarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\Passport;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Import meetings from the Outlook calendar (Graph and the token endpoint faked with Http::fake).
 */
#[UsesClass(CalendarEventController::class)]
#[UsesClass(OutlookCalendarService::class)]
class CalendarEventEndpointTest extends ApiEndpointTestAbstract
{
    private const string CALENDAR_VIEW = 'graph.microsoft.com/v1.0/me/calendarView*';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-10-19T15:00:00Z'));
        config([
            'services.entra_calendar.enabled' => true,
            'services.entra_calendar.tenant_id' => 'tenant-1',
            'services.entra_calendar.client_id' => 'client-1',
            'services.entra_calendar.client_secret' => 'secret-1',
            'services.entra_calendar.redirect_uri' => 'https://timetracker.test/outlook/callback',
        ]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return object{user: User, organization: Organization, member: Member, owner: User, ownerMember: Member}
     */
    private function connectedUser(array $permissions = ['time-entries:create:own'], bool $expiredToken = false): object
    {
        $data = $this->createUserWithPermission($permissions);
        $data->user->timezone = 'America/Sao_Paulo';
        $data->user->save();
        $connection = new OutlookConnection;
        $connection->user_id = $data->user->getKey();
        $connection->refresh_token = 'refresh-old';
        $connection->access_token = 'access-old';
        $connection->access_token_expires_at = $expiredToken ? Carbon::now()->subMinute() : Carbon::now()->addHour();
        $connection->connected_at = Carbon::now()->subDays(30);
        $connection->save();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function event(string $uid, string $subject, string $start, string $end, array $overrides = []): array
    {
        return array_merge([
            'iCalUId' => $uid,
            'subject' => $subject,
            'start' => ['dateTime' => $start.'.0000000', 'timeZone' => 'America/Sao_Paulo'],
            'end' => ['dateTime' => $end.'.0000000', 'timeZone' => 'America/Sao_Paulo'],
            'isAllDay' => false,
            'isCancelled' => false,
            'showAs' => 'busy',
            'isOnlineMeeting' => true,
            'responseStatus' => ['response' => 'accepted'],
            'organizer' => ['emailAddress' => ['name' => 'Ana Lima', 'address' => 'ana@example.test']],
        ], $overrides);
    }

    private function fakeCalendar(): void
    {
        Http::fake([
            'graph.microsoft.com/v1.0/me/calendarView?*skiptoken*' => Http::response(['value' => [
                $this->event('uid-page2', 'Comitê', '2026-10-16T14:00:00', '2026-10-16T15:00:00'),
            ]]),
            self::CALENDAR_VIEW => Http::response([
                'value' => [
                    $this->event('uid-daily', 'Daily Projeto X', '2026-10-15T09:00:00', '2026-10-15T09:30:00'),
                    $this->event('uid-cancel', 'Cancelada', '2026-10-15T10:00:00', '2026-10-15T11:00:00', ['isCancelled' => true]),
                    $this->event('uid-allday', 'Feriado', '2026-10-15T00:00:00', '2026-10-16T00:00:00', ['isAllDay' => true]),
                    $this->event('uid-declined', 'Recusada', '2026-10-15T11:00:00', '2026-10-15T12:00:00', ['responseStatus' => ['response' => 'declined']]),
                    $this->event('uid-free', 'Bloqueio livre', '2026-10-15T13:00:00', '2026-10-15T14:00:00', ['showAs' => 'free']),
                    $this->event('uid-review', 'Review', '2026-10-14T16:00:00', '2026-10-14T17:00:00', ['isOnlineMeeting' => false]),
                ],
                '@odata.nextLink' => 'https://graph.microsoft.com/v1.0/me/calendarView?$skiptoken=page2',
            ]),
        ]);
    }

    public function test_index_is_not_found_when_disabled(): void
    {
        config(['services.entra_calendar.enabled' => false]);
        $data = $this->connectedUser();
        Passport::actingAs($data->user);

        $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]))->assertNotFound();
        $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), ['items' => []])->assertNotFound();
        $this->postJson(route('api.v1.calendar-events.ignore', [$data->organization->getKey()]), ['items' => []])->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_index_fails_without_permission_to_create_own_time_entries(): void
    {
        $data = $this->connectedUser([]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_index_fails_when_outlook_is_not_connected(): void
    {
        $data = $this->createUserWithPermission(['time-entries:create:own']);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $response->assertStatus(400);
        $response->assertJsonPath('key', 'outlook_not_connected');
    }

    public function test_index_lists_meetings_without_cancelled_all_day_declined_and_free(): void
    {
        $data = $this->connectedUser();
        $this->fakeCalendar();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [
            $data->organization->getKey(),
            'start' => '2026-10-12',
            'end' => '2026-10-19',
        ]));

        $this->assertResponseCode($response, 200);
        $response->assertJsonCount(3, 'data');
        $response->assertJsonPath('data.0.subject', 'Review');
        $response->assertJsonPath('data.0.is_online_meeting', false);
        $response->assertJsonPath('data.1.event_key', 'uid-daily|2026-10-15T12:00:00Z');
        $response->assertJsonPath('data.1.start', '2026-10-15T12:00:00Z');
        $response->assertJsonPath('data.1.end', '2026-10-15T12:30:00Z');
        $response->assertJsonPath('data.1.duration', 1800);
        $response->assertJsonPath('data.1.organizer', 'Ana Lima');
        $response->assertJsonPath('data.1.already_imported', false);
        $response->assertJsonPath('data.2.subject', 'Comitê');
        $response->assertJsonPath('meta.start', '2026-10-12');
        $response->assertJsonPath('meta.end', '2026-10-19');
        $response->assertJsonPath('meta.new_count', 3);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/me/calendarView')
            && $request->hasHeader('Authorization', 'Bearer access-old')
            && $request->hasHeader('Prefer', 'outlook.timezone="America/Sao_Paulo"')
            && str_contains(urldecode($request->url()), 'startDateTime=2026-10-12T03:00:00Z')
            && str_contains(urldecode($request->url()), 'endDateTime=2026-10-20T03:00:00Z'));
    }

    public function test_index_by_default_starts_at_the_day_of_the_last_import(): void
    {
        $data = $this->connectedUser();
        OutlookConnection::query()->where('user_id', $data->user->getKey())
            ->update(['last_import_at' => Carbon::parse('2026-10-16T02:00:00Z')]); // 15/10 23:00 in São Paulo
        Http::fake([self::CALENDAR_VIEW => Http::response(['value' => []])]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('meta.start', '2026-10-15');
        $response->assertJsonPath('meta.end', '2026-10-19');
    }

    public function test_index_by_default_takes_the_last_7_days_when_never_imported(): void
    {
        $data = $this->connectedUser();
        Http::fake([self::CALENDAR_VIEW => Http::response(['value' => []])]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('meta.start', '2026-10-12');
        $response->assertJsonPath('meta.end', '2026-10-19');
    }

    public function test_index_rejects_a_period_longer_than_the_limit(): void
    {
        $data = $this->connectedUser();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [
            $data->organization->getKey(),
            'start' => '2026-01-01',
            'end' => '2026-10-19',
        ]));

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['end']);
    }

    public function test_index_flags_imported_and_ignored_meetings_and_suggests_the_most_used_project(): void
    {
        $data = $this->connectedUser();
        $projectA = Project::factory()->forOrganization($data->organization)->create();
        $projectB = Project::factory()->forOrganization($data->organization)->create();
        $tag = Tag::factory()->forOrganization($data->organization)->create();
        $task = Task::factory()->forOrganization($data->organization)->forProject($projectA)->create();
        TimeEntry::factory()->forMember($data->member)->forProject($projectA)->start(Carbon::parse('2026-10-01T12:00:00Z'))
            ->create(['description' => 'Daily Projeto X', 'tags' => [], 'task_id' => null]);
        TimeEntry::factory()->forMember($data->member)->forProject($projectA)->start(Carbon::parse('2026-10-02T12:00:00Z'))
            ->create(['description' => 'daily projeto x', 'tags' => [$tag->getKey()], 'task_id' => $task->getKey()]);
        TimeEntry::factory()->forMember($data->member)->forProject($projectB)->start(Carbon::parse('2026-10-03T12:00:00Z'))
            ->create(['description' => 'Daily Projeto X - extra']);
        TimeEntry::factory()->forMember($data->member)->forProject($projectB)->start(Carbon::parse('2026-10-04T12:00:00Z'))
            ->create(['description' => 'Pauta do Comitê de riscos']);
        // someone else's time entry does not count for the suggestion
        $other = Member::factory()->forOrganization($data->organization)->create();
        TimeEntry::factory()->forMember($other)->forProject($projectB)->count(3)->create(['description' => 'Review']);

        $imported = TimeEntry::factory()->forMember($data->member)->create();
        $this->handled($data, 'uid-daily|2026-10-15T12:00:00Z', CalendarImport::STATUS_IMPORTED, $imported->getKey());
        $this->handled($data, 'uid-page2|2026-10-16T17:00:00Z', CalendarImport::STATUS_IGNORED);
        $this->fakeCalendar();
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [
            $data->organization->getKey(),
            'start' => '2026-10-12',
            'end' => '2026-10-19',
        ]));

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('data.0.subject', 'Review');
        $response->assertJsonPath('data.0.suggested_project_id', null);
        $response->assertJsonPath('data.0.status', null);
        $response->assertJsonPath('data.1.already_imported', true);
        $response->assertJsonPath('data.1.status', 'imported');
        $response->assertJsonPath('data.1.time_entry_id', $imported->getKey());
        $response->assertJsonPath('data.1.suggested_project_id', $projectA->getKey());
        $response->assertJsonPath('data.1.suggested_task_id', $task->getKey());
        $response->assertJsonPath('data.1.suggested_tags', [$tag->getKey()]);
        $response->assertJsonPath('data.2.already_imported', false);
        $response->assertJsonPath('data.2.status', 'ignored');
        $response->assertJsonPath('data.2.suggested_project_id', $projectB->getKey());
        $response->assertJsonPath('meta.new_count', 1);
    }

    public function test_index_renews_an_expired_access_token_with_the_refresh_token(): void
    {
        $data = $this->connectedUser(expiredToken: true);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-new',
                'refresh_token' => 'refresh-new',
                'expires_in' => 3599,
            ]),
            self::CALENDAR_VIEW => Http::response(['value' => []]),
        ]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $this->assertResponseCode($response, 200);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/tenant-1/oauth2/v2.0/token'
            && $request['grant_type'] === 'refresh_token'
            && $request['refresh_token'] === 'refresh-old'
            && $request['scope'] === 'offline_access Calendars.Read User.Read');
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/me/calendarView')
            && $request->hasHeader('Authorization', 'Bearer access-new'));
        $connection = OutlookConnection::query()->where('user_id', $data->user->getKey())->firstOrFail();
        $this->assertSame('access-new', $connection->access_token);
        $this->assertSame('refresh-new', $connection->refresh_token);
        $this->assertTrue($connection->access_token_expires_at->isAfter(Carbon::now()->addMinutes(50)));
    }

    public function test_index_asks_to_reconnect_when_the_refresh_token_is_revoked(): void
    {
        $data = $this->connectedUser(expiredToken: true);
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_grant'], 400),
        ]);
        Passport::actingAs($data->user);

        $response = $this->getJson(route('api.v1.calendar-events.index', [$data->organization->getKey()]));

        $response->assertStatus(400);
        $response->assertJsonPath('key', 'outlook_reconnect_required');
        $this->assertDatabaseMissing('outlook_connections', ['user_id' => $data->user->getKey()]);
    }

    public function test_import_creates_time_entries_and_does_not_duplicate(): void
    {
        $data = $this->connectedUser();
        $project = Project::factory()->forOrganization($data->organization)->isPublic()->create();
        $task = Task::factory()->forOrganization($data->organization)->forProject($project)->create();
        $tag = Tag::factory()->forOrganization($data->organization)->create();
        Passport::actingAs($data->user);
        $payload = ['items' => [
            [
                'event_key' => 'uid-daily|2026-10-15T12:00:00Z',
                'description' => 'Daily Projeto X',
                'start' => '2026-10-15T12:00:00Z',
                'end' => '2026-10-15T12:30:00Z',
                'project_id' => $project->getKey(),
                'task_id' => $task->getKey(),
                'tags' => [$tag->getKey()],
            ],
            [
                'event_key' => 'uid-review|2026-10-14T19:00:00Z',
                'description' => 'Review',
                'start' => '2026-10-14T19:00:00Z',
                'end' => '2026-10-14T20:00:00Z',
                'project_id' => null,
            ],
        ]];

        $response = $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), $payload);

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('data.imported', 2);
        $entry = TimeEntry::query()->where('description', 'Daily Projeto X')->firstOrFail();
        $this->assertSame($data->member->getKey(), $entry->member_id);
        $this->assertSame($data->user->getKey(), $entry->user_id);
        $this->assertSame($project->getKey(), $entry->project_id);
        $this->assertSame($task->getKey(), $entry->task_id);
        $this->assertSame([$tag->getKey()], $entry->tags);
        $this->assertFalse($entry->billable);
        $this->assertSame('work', $entry->type->value);
        $this->assertSame('2026-10-15T12:30:00Z', $entry->end?->format('Y-m-d\TH:i:s\Z'));
        $this->assertDatabaseHas('calendar_imports', [
            'member_id' => $data->member->getKey(),
            'event_key' => 'uid-daily|2026-10-15T12:00:00Z',
            'status' => 'imported',
            'time_entry_id' => $entry->getKey(),
        ]);
        $this->assertNotNull(OutlookConnection::query()->where('user_id', $data->user->getKey())->value('last_import_at'));

        // Act again: same meetings are not imported twice
        $again = $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), $payload);

        $this->assertResponseCode($again, 200);
        $again->assertJsonPath('data.imported', 0);
        $again->assertJsonPath('data.skipped.0.reason', 'already_imported');
        $this->assertSame(2, TimeEntry::query()->where('member_id', $data->member->getKey())->count());
        $this->assertSame(2, CalendarImport::query()->count());
    }

    public function test_import_skips_overlapping_meetings_when_the_organization_prevents_overlap(): void
    {
        $data = $this->connectedUser();
        $data->organization->prevent_overlapping_time_entries = true;
        $data->organization->save();
        TimeEntry::factory()->forMember($data->member)->create([
            'start' => Carbon::parse('2026-10-15T11:00:00Z'),
            'end' => Carbon::parse('2026-10-15T13:00:00Z'),
        ]);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), ['items' => [[
            'event_key' => 'uid-daily|2026-10-15T12:00:00Z',
            'start' => '2026-10-15T12:00:00Z',
            'end' => '2026-10-15T12:30:00Z',
        ]]]);

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('data.imported', 0);
        $response->assertJsonPath('data.skipped.0.reason', 'overlapping_time_entry');
        $this->assertDatabaseMissing('calendar_imports', ['event_key' => 'uid-daily|2026-10-15T12:00:00Z']);
    }

    public function test_import_rejects_project_of_another_organization_and_task_of_another_project(): void
    {
        $data = $this->connectedUser();
        $foreign = Project::factory()->create();
        $project = Project::factory()->forOrganization($data->organization)->isPublic()->create();
        $otherProject = Project::factory()->forOrganization($data->organization)->create();
        $task = Task::factory()->forOrganization($data->organization)->forProject($otherProject)->create();
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), ['items' => [
            ['event_key' => 'a|1', 'start' => '2026-10-15T12:00:00Z', 'end' => '2026-10-15T12:30:00Z', 'project_id' => $foreign->getKey()],
            ['event_key' => 'b|1', 'start' => '2026-10-15T13:00:00Z', 'end' => '2026-10-15T13:30:00Z', 'project_id' => $project->getKey(), 'task_id' => $task->getKey()],
        ]]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['items.0.project_id', 'items.1.task_id']);
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_import_fails_without_permission_to_create_own_time_entries(): void
    {
        $data = $this->connectedUser(['time-entries:view:own']);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.calendar-events.import', [$data->organization->getKey()]), ['items' => [[
            'event_key' => 'uid-daily|2026-10-15T12:00:00Z',
            'start' => '2026-10-15T12:00:00Z',
            'end' => '2026-10-15T12:30:00Z',
        ]]]);

        $response->assertForbidden();
        $this->assertSame(0, TimeEntry::query()->count());
    }

    public function test_ignore_marks_meetings_and_keeps_imported_ones(): void
    {
        $data = $this->connectedUser();
        $imported = TimeEntry::factory()->forMember($data->member)->create();
        $this->handled($data, 'uid-daily|2026-10-15T12:00:00Z', CalendarImport::STATUS_IMPORTED, $imported->getKey());
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.calendar-events.ignore', [$data->organization->getKey()]), ['items' => [
            ['event_key' => 'uid-daily|2026-10-15T12:00:00Z'],
            ['event_key' => 'uid-free|2026-10-15T16:00:00Z'],
            ['event_key' => 'uid-free|2026-10-15T16:00:00Z'],
        ]]);

        $this->assertResponseCode($response, 200);
        $response->assertJsonPath('data.ignored', 1);
        $this->assertDatabaseHas('calendar_imports', ['event_key' => 'uid-daily|2026-10-15T12:00:00Z', 'status' => 'imported']);
        $this->assertDatabaseHas('calendar_imports', ['event_key' => 'uid-free|2026-10-15T16:00:00Z', 'status' => 'ignored', 'member_id' => $data->member->getKey()]);
    }

    public function test_ignore_fails_without_permission(): void
    {
        $data = $this->connectedUser([]);
        Passport::actingAs($data->user);

        $response = $this->postJson(route('api.v1.calendar-events.ignore', [$data->organization->getKey()]), ['items' => [
            ['event_key' => 'uid-daily|2026-10-15T12:00:00Z'],
        ]]);

        $response->assertForbidden();
        $this->assertSame(0, CalendarImport::query()->count());
    }

    /**
     * @param  object{organization: Organization, member: Member}  $data
     */
    private function handled(object $data, string $eventKey, string $status, ?string $timeEntryId = null): void
    {
        $import = new CalendarImport;
        $import->organization_id = $data->organization->getKey();
        $import->member_id = $data->member->getKey();
        $import->event_key = $eventKey;
        $import->status = $status;
        $import->time_entry_id = $timeEntryId;
        $import->save();
    }
}
