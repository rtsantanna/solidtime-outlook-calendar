<?php

declare(strict_types=1);

return [
    'gotenberg' => [
        'url' => env('GOTENBERG_URL'),
        'basic_auth_username' => env('GOTENBERG_BASIC_AUTH_USERNAME'),
        'basic_auth_password' => env('GOTENBERG_BASIC_AUTH_PASSWORD'),
    ],

    // Outlook calendar import: delegated Microsoft Graph flow (authorization code + PKCE); each user connects their own
    // calendar (Calendars.Read, User.Read, offline_access). Tenant: a tenant id, or "organizations" for any work account.
    'entra_calendar' => [
        'enabled' => (bool) env('ENTRA_CALENDAR_ENABLED', false),
        'tenant_id' => env('ENTRA_CALENDAR_TENANT_ID'),
        'client_id' => env('ENTRA_CALENDAR_CLIENT_ID'),
        'client_secret' => env('ENTRA_CALENDAR_CLIENT_SECRET'),
        'redirect_uri' => env('ENTRA_CALENDAR_REDIRECT_URI', rtrim((string) env('APP_URL'), '/').'/outlook/callback'),
        'scopes' => 'offline_access Calendars.Read User.Read',
        'http_timeout' => (int) env('ENTRA_CALENDAR_HTTP_TIMEOUT', 15),
        // default range when the user never imported, and the maximum range per request (days)
        'default_days' => (int) env('ENTRA_CALENDAR_DEFAULT_DAYS', 7),
        'max_days' => (int) env('ENTRA_CALENDAR_MAX_DAYS', 62),
    ],
];
