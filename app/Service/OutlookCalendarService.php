<?php

declare(strict_types=1);

namespace App\Service;

use App\Exceptions\Api\OutlookReconnectRequiredApiException;
use App\Exceptions\Api\OutlookUnavailableApiException;
use App\Models\OutlookConnection;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Outlook calendar through Microsoft Graph with the user's own DELEGATED token (authorization code + PKCE).
 * Talks only to login.microsoftonline.com and graph.microsoft.com. Tokens never go to logs or error messages.
 */
class OutlookCalendarService
{
    private const string GRAPH = 'https://graph.microsoft.com/v1.0';

    private const string LOGIN = 'https://login.microsoftonline.com';

    /** Maximum number of @odata.nextLink pages (100 events per page). */
    private const int MAX_PAGES = 20;

    /** Windows time zones that Graph may return and PHP does not know. */
    private const array WINDOWS_TIMEZONES = [
        'E. South America Standard Time' => 'America/Sao_Paulo',
        'SA Western Standard Time' => 'America/Manaus',
        'SA Eastern Standard Time' => 'America/Fortaleza',
        'Central Brazilian Standard Time' => 'America/Cuiaba',
        'Bahia Standard Time' => 'America/Bahia',
        'Tocantins Standard Time' => 'America/Araguaina',
        'UTC' => 'UTC',
        'tzone://Microsoft/Utc' => 'UTC',
    ];

    /** IANA zones Graph accepts in the Prefer header ("Additional time zones" of dateTimeTimeZone); anything else uses UTC. */
    private const array GRAPH_IANA_TIMEZONES = [
        'America/Sao_Paulo', 'America/Cuiaba', 'America/Bahia', 'America/Manaus', 'America/Fortaleza',
        'America/Argentina/Buenos_Aires', 'America/Montevideo', 'America/Santiago', 'America/Bogota',
        'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Europe/London', 'Europe/Paris',
        'Europe/Berlin', 'Etc/GMT', 'UTC',
    ];

    public function isEnabled(): bool
    {
        return (bool) config('services.entra_calendar.enabled') && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return filled(config('services.entra_calendar.tenant_id'))
            && filled(config('services.entra_calendar.client_id'))
            && filled(config('services.entra_calendar.client_secret'))
            && filled(config('services.entra_calendar.redirect_uri'));
    }

    public function isConnected(User $user): bool
    {
        return OutlookConnection::query()->where('user_id', $user->getKey())->exists();
    }

    /**
     * @return array{verifier: string, challenge: string}
     */
    public function makePkce(): array
    {
        $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        return ['verifier' => $verifier, 'challenge' => $challenge];
    }

    public function authorizationUrl(string $state, string $codeChallenge, ?string $loginHint = null): string
    {
        $query = [
            'client_id' => (string) config('services.entra_calendar.client_id'),
            'response_type' => 'code',
            'redirect_uri' => (string) config('services.entra_calendar.redirect_uri'),
            'response_mode' => 'query',
            'scope' => (string) config('services.entra_calendar.scopes'),
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ];
        if ($loginHint !== null && $loginHint !== '') {
            $query['login_hint'] = $loginHint;
        }

        return $this->authorityUrl().'/authorize?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Exchanges the code for tokens and stores the user's connection (encrypted).
     */
    public function connect(User $user, string $code, string $codeVerifier): OutlookConnection
    {
        $tokens = $this->requestToken([
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => (string) config('services.entra_calendar.redirect_uri'),
            'code_verifier' => $codeVerifier,
        ]);
        if ($tokens === null || ! isset($tokens['refresh_token'])) {
            throw new RuntimeException('Entra ID returned no refresh token (is offline_access missing?).');
        }

        $connection = OutlookConnection::query()->firstOrNew(['user_id' => $user->getKey()]);
        $connection->user_id = $user->getKey();
        $connection->refresh_token = $tokens['refresh_token'];
        $connection->access_token = $tokens['access_token'];
        $connection->access_token_expires_at = $tokens['expires_at'];
        $connection->account_email = $this->fetchAccountEmail($tokens['access_token']);
        $connection->connected_at = Carbon::now();
        $connection->save();

        return $connection;
    }

    /**
     * A valid access token, refreshed with the refresh token when less than a minute is left.
     *
     * @throws OutlookReconnectRequiredApiException refresh token rejected (the connection is removed)
     * @throws OutlookUnavailableApiException
     */
    public function accessToken(OutlookConnection $connection): string
    {
        if ($connection->access_token !== null
            && $connection->access_token_expires_at !== null
            && $connection->access_token_expires_at->isAfter(Carbon::now()->addMinute())) {
            return $connection->access_token;
        }

        $tokens = $this->requestToken([
            'grant_type' => 'refresh_token',
            'refresh_token' => $connection->refresh_token,
        ]);
        if ($tokens === null) {
            $connection->delete();
            throw new OutlookReconnectRequiredApiException;
        }

        $connection->access_token = $tokens['access_token'];
        $connection->access_token_expires_at = $tokens['expires_at'];
        if (isset($tokens['refresh_token'])) {
            $connection->refresh_token = $tokens['refresh_token'];
        }
        $connection->save();

        return $tokens['access_token'];
    }

    /**
     * The user's meetings between start and end (UTC), filtered: no cancelled, all-day, declined or "free" events.
     *
     * @return list<array{event_key: string, subject: string, start: Carbon, end: Carbon, duration: int, organizer: string|null, is_online_meeting: bool}>
     *
     * @throws OutlookReconnectRequiredApiException
     * @throws OutlookUnavailableApiException
     */
    public function listEvents(OutlookConnection $connection, Carbon $start, Carbon $end, string $timezone): array
    {
        $token = $this->accessToken($connection);
        // times come back in the user's zone when Graph knows it; the UTC conversion uses the returned timeZone
        $timezone = in_array($timezone, self::GRAPH_IANA_TIMEZONES, true) ? $timezone : 'UTC';

        $url = self::GRAPH.'/me/calendarView?'.http_build_query([
            'startDateTime' => $start->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            'endDateTime' => $end->copy()->utc()->format('Y-m-d\TH:i:s\Z'),
            '$select' => 'subject,start,end,isAllDay,isCancelled,showAs,iCalUId,organizer,responseStatus,isOnlineMeeting',
            '$orderby' => 'start/dateTime',
            '$top' => 100,
        ], '', '&', PHP_QUERY_RFC3986);

        $events = [];
        for ($page = 0; $url !== null && $page < self::MAX_PAGES; $page++) {
            $response = $this->graphGet($token, $url, $timezone);
            /** @var array<int, mixed> $values */
            $values = (array) $response->json('value', []);
            foreach ($values as $raw) {
                if (! is_array($raw)) {
                    continue;
                }
                $event = $this->normalizeEvent($raw, $timezone);
                if ($event !== null) {
                    $events[] = $event;
                }
            }
            $body = $response->json();
            $next = is_array($body) ? ($body['@odata.nextLink'] ?? null) : null; // the key has a dot: json('...') would read it as a path
            $url = is_string($next) && str_starts_with($next, self::GRAPH.'/') ? $next : null;
        }

        usort($events, fn (array $a, array $b): int => $a['start']->getTimestamp() <=> $b['start']->getTimestamp());

        return $events;
    }

    /**
     * @param  array<mixed>  $raw
     * @return array{event_key: string, subject: string, start: Carbon, end: Carbon, duration: int, organizer: string|null, is_online_meeting: bool}|null
     */
    private function normalizeEvent(array $raw, string $timezone): ?array
    {
        if (($raw['isCancelled'] ?? false) === true || ($raw['isAllDay'] ?? false) === true) {
            return null;
        }
        if (($raw['showAs'] ?? null) === 'free') {
            return null;
        }
        $response = $raw['responseStatus']['response'] ?? null;
        if ($response === 'declined') {
            return null;
        }
        $uid = $raw['iCalUId'] ?? null;
        if (! is_string($uid) || $uid === '') {
            return null;
        }
        $start = $this->parseDateTime($raw['start'] ?? null, $timezone);
        $end = $this->parseDateTime($raw['end'] ?? null, $timezone);
        if ($start === null || $end === null || ! $end->isAfter($start)) {
            return null;
        }

        $organizer = $raw['organizer']['emailAddress'] ?? null;
        $organizerLabel = null;
        if (is_array($organizer)) {
            $name = is_string($organizer['name'] ?? null) ? trim($organizer['name']) : '';
            $address = is_string($organizer['address'] ?? null) ? trim($organizer['address']) : '';
            $organizerLabel = $name !== '' ? $name : ($address !== '' ? $address : null);
        }

        $subject = is_string($raw['subject'] ?? null) ? trim($raw['subject']) : '';

        return [
            'event_key' => self::eventKey($uid, $start),
            'subject' => mb_substr($subject, 0, 500),
            'start' => $start,
            'end' => $end,
            'duration' => $end->getTimestamp() - $start->getTimestamp(),
            'organizer' => $organizerLabel,
            'is_online_meeting' => ($raw['isOnlineMeeting'] ?? false) === true,
        ];
    }

    public static function eventKey(string $iCalUId, Carbon $start): string
    {
        return mb_substr($iCalUId, 0, 480).'|'.$start->copy()->utc()->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Graph returns {"dateTime": "2026-10-19T09:00:00.0000000", "timeZone": "America/Sao_Paulo"}.
     */
    private function parseDateTime(mixed $value, string $timezone): ?Carbon
    {
        if (! is_array($value) || ! is_string($value['dateTime'] ?? null)) {
            return null;
        }
        $dateTime = substr($value['dateTime'], 0, 19);
        $zone = is_string($value['timeZone'] ?? null) ? $value['timeZone'] : $timezone;
        $zone = self::WINDOWS_TIMEZONES[$zone] ?? $zone;
        if (! in_array($zone, timezone_identifiers_list(), true) && $zone !== 'UTC') {
            $zone = $timezone;
        }

        try {
            return Carbon::createFromFormat('Y-m-d\TH:i:s', $dateTime, $zone)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    private function graphGet(string $token, string $url, string $timezone): Response
    {
        try {
            $response = Http::withToken($token)
                ->withHeaders(['Prefer' => 'outlook.timezone="'.$timezone.'"'])
                ->acceptJson()
                ->timeout((int) config('services.entra_calendar.http_timeout'))
                ->withoutRedirecting()
                ->get($url);
        } catch (ConnectionException) {
            throw new OutlookUnavailableApiException;
        }
        if ($response->status() === 401) {
            throw new OutlookReconnectRequiredApiException;
        }
        if (! $response->successful()) {
            Log::warning('Outlook: Microsoft Graph answered '.$response->status().' while reading the calendar.');
            throw new OutlookUnavailableApiException;
        }

        return $response;
    }

    private function fetchAccountEmail(string $accessToken): ?string
    {
        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config('services.entra_calendar.http_timeout'))
                ->get(self::GRAPH.'/me', ['$select' => 'mail,userPrincipalName']);
        } catch (ConnectionException) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $mail = $response->json('mail');
        if (! is_string($mail) || $mail === '') {
            $mail = $response->json('userPrincipalName');
        }

        return is_string($mail) && $mail !== '' ? mb_substr(mb_strtolower($mail), 0, 255) : null;
    }

    /**
     * @param  array<string, string>  $params
     * @return array{access_token: string, refresh_token?: string, expires_at: Carbon}|null null = grant rejected (invalid_grant)
     *
     * @throws OutlookUnavailableApiException
     */
    private function requestToken(array $params): ?array
    {
        try {
            $response = Http::asForm()
                ->acceptJson()
                ->timeout((int) config('services.entra_calendar.http_timeout'))
                ->post($this->authorityUrl().'/token', array_merge($params, [
                    'client_id' => (string) config('services.entra_calendar.client_id'),
                    'client_secret' => (string) config('services.entra_calendar.client_secret'),
                    'scope' => (string) config('services.entra_calendar.scopes'),
                ]));
        } catch (ConnectionException) {
            throw new OutlookUnavailableApiException;
        }

        if ($response->status() === 400 && in_array($response->json('error'), ['invalid_grant', 'interaction_required'], true)) {
            return null;
        }
        $accessToken = $response->json('access_token');
        if (! $response->successful() || ! is_string($accessToken) || $accessToken === '') {
            // only the Entra ID error code, never the body (it may echo parameters)
            $error = $response->json('error');
            Log::warning('Outlook: Entra ID answered '.$response->status().(is_string($error) ? ' ('.$error.')' : '').' on the token request.');
            throw new OutlookUnavailableApiException;
        }

        $result = [
            'access_token' => $accessToken,
            'expires_at' => Carbon::now()->addSeconds(max(60, (int) $response->json('expires_in', 3600))),
        ];
        $refresh = $response->json('refresh_token');
        if (is_string($refresh) && $refresh !== '') {
            $result['refresh_token'] = $refresh;
        }

        return $result;
    }

    private function authorityUrl(): string
    {
        return self::LOGIN.'/'.rawurlencode((string) config('services.entra_calendar.tenant_id')).'/oauth2/v2.0';
    }
}
