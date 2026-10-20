<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\Web\OutlookController;
use App\Models\OutlookConnection;
use App\Models\User;
use App\Service\OutlookCalendarService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCaseWithDatabase;

/**
 * Connect/disconnect the Outlook calendar (authorization code + PKCE with Entra ID).
 */
#[UsesClass(OutlookController::class)]
#[UsesClass(OutlookCalendarService::class)]
class OutlookConnectionTest extends TestCaseWithDatabase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.entra_calendar.enabled' => true,
            'services.entra_calendar.tenant_id' => 'tenant-1',
            'services.entra_calendar.client_id' => 'client-1',
            'services.entra_calendar.client_secret' => 'secret-1',
            'services.entra_calendar.redirect_uri' => 'https://timetracker.test/outlook/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_connect_is_not_available_when_disabled(): void
    {
        config(['services.entra_calendar.enabled' => false]);
        $user = User::factory()->create();

        $this->actingAs($user)->get('/outlook/connect')->assertNotFound();
        $this->actingAs($user)->get('/outlook/callback?code=x&state=y')->assertNotFound();
        $this->actingAs($user)->post('/outlook/disconnect')->assertNotFound();
    }

    public function test_connect_requires_login(): void
    {
        $this->get('/outlook/connect')->assertRedirect('/login');
    }

    public function test_connect_redirects_to_entra_with_state_and_pkce(): void
    {
        $user = User::factory()->create(['email' => 'pessoa@example.test']);

        $response = $this->actingAs($user)->get('/outlook/connect');

        $response->assertRedirect();
        $location = (string) $response->headers->get('Location');
        $this->assertStringStartsWith('https://login.microsoftonline.com/tenant-1/oauth2/v2.0/authorize?', $location);
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $pending = session('outlook_oauth');
        $this->assertIsArray($pending);
        $this->assertSame('client-1', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('https://timetracker.test/outlook/callback', $query['redirect_uri']);
        $this->assertSame('offline_access Calendars.Read User.Read', $query['scope']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame('pessoa@example.test', $query['login_hint']);
        $this->assertSame($pending['state'], $query['state']);
        $expectedChallenge = rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '=');
        $this->assertSame($expectedChallenge, $query['code_challenge']);
    }

    public function test_callback_exchanges_code_and_stores_tokens_encrypted(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response([
                'access_token' => 'access-token-1',
                'refresh_token' => 'refresh-token-1',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ]),
            'graph.microsoft.com/v1.0/me*' => Http::response(['mail' => 'Pessoa@EXAMPLE.test', 'userPrincipalName' => 'pessoa@example.test']),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['outlook_oauth' => ['state' => 'state-1', 'verifier' => 'verifier-1']])
            ->get('/outlook/callback?code=code-1&state=state-1');

        $response->assertRedirect(route('time', ['outlook' => 'connected']));
        $response->assertSessionHas('bannerStyle', 'success');
        $connection = OutlookConnection::query()->where('user_id', $user->getKey())->firstOrFail();
        $this->assertSame('refresh-token-1', $connection->refresh_token);
        $this->assertSame('access-token-1', $connection->access_token);
        $this->assertSame('pessoa@example.test', $connection->account_email);
        $this->assertNotNull($connection->access_token_expires_at);
        $raw = DB::table('outlook_connections')->where('user_id', $user->getKey())->first();
        $this->assertNotNull($raw);
        $this->assertStringNotContainsString('refresh-token-1', (string) $raw->refresh_token);
        $this->assertStringNotContainsString('access-token-1', (string) $raw->access_token);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://login.microsoftonline.com/tenant-1/oauth2/v2.0/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'code-1'
            && $request['code_verifier'] === 'verifier-1'
            && $request['client_secret'] === 'secret-1'
            && $request['redirect_uri'] === 'https://timetracker.test/outlook/callback');
        $this->assertNull(session('outlook_oauth'));
    }

    public function test_callback_with_wrong_state_does_not_connect(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['outlook_oauth' => ['state' => 'state-1', 'verifier' => 'verifier-1']])
            ->get('/outlook/callback?code=code-1&state=other');

        $response->assertRedirect(route('time'));
        $response->assertSessionHas('bannerStyle', 'danger');
        $this->assertDatabaseMissing('outlook_connections', ['user_id' => $user->getKey()]);
        Http::assertNothingSent();
    }

    public function test_callback_with_error_from_entra_shows_friendly_message(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->withSession(['outlook_oauth' => ['state' => 'state-1', 'verifier' => 'verifier-1']])
            ->get('/outlook/callback?error=access_denied&error_description=denied&state=state-1');

        $response->assertRedirect(route('time'));
        $response->assertSessionHas('bannerStyle', 'warning');
        $this->assertDatabaseMissing('outlook_connections', ['user_id' => $user->getKey()]);
    }

    public function test_callback_when_token_exchange_fails_shows_friendly_message(): void
    {
        $user = User::factory()->create();
        Http::fake([
            'login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client'], 401),
        ]);

        $response = $this->actingAs($user)
            ->withSession(['outlook_oauth' => ['state' => 'state-1', 'verifier' => 'verifier-1']])
            ->get('/outlook/callback?code=code-1&state=state-1');

        $response->assertRedirect(route('time'));
        $response->assertSessionHas('bannerStyle', 'danger');
        $this->assertDatabaseMissing('outlook_connections', ['user_id' => $user->getKey()]);
    }

    public function test_disconnect_removes_only_the_own_connection(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        foreach ([$user, $other] as $owner) {
            $connection = new OutlookConnection;
            $connection->user_id = $owner->getKey();
            $connection->refresh_token = 'refresh';
            $connection->connected_at = now();
            $connection->save();
        }

        $this->actingAs($user)->post('/outlook/disconnect')->assertRedirect();

        $this->assertDatabaseMissing('outlook_connections', ['user_id' => $user->getKey()]);
        $this->assertDatabaseHas('outlook_connections', ['user_id' => $other->getKey()]);
    }
}
