# Outlook calendar import

Each user connects **their own** Outlook calendar and turns past meetings into time entries in a few clicks. The
calendar is only read, never written.

## How it works for the user

1. On the **Time** page, **Import from calendar** → **Connect Outlook**. Microsoft asks the user to sign in and consent;
   the app comes back to the Time page with the import dialog open.
2. The dialog lists the meetings of the period. By default the period runs from the day of the last import (or the
   last 7 days) until today, in the user's time zone. Left out:
   - cancelled and all-day events;
   - declined invitations;
   - events marked as "free".
3. New meetings come checked, each with a **suggested project, task and tags**: the project the user picked most often
   for entries with the same (or a containing) description in the last year. The user can change the suggestion, create
   a project on the spot, then pick **Import selected** or **Ignore selected**.
4. Each imported meeting becomes a normal, non-billable time entry of that user. Checks on import:
   - a meeting is never imported twice;
   - deleting the entry frees the meeting again;
   - when the organization prevents overlapping time entries, overlapping meetings are skipped.

**Disconnect Outlook** removes the stored tokens.

## Setup (administrator)

### 1. Register an app in Microsoft Entra ID

**Entra admin center → App registrations → New registration**:

- **Supported account types:** your organization only (single tenant), or any organization (multi-tenant).
- **Redirect URI:** platform **Web**, `https://<your-solidtime-host>/outlook/callback`.
- **API permissions → Microsoft Graph → Delegated:** `Calendars.Read`, `User.Read`, `offline_access`.
  - Grant admin consent if your tenant does not let users consent to apps.
  - No application permissions are needed.
- **Certificates & secrets:** create a client secret.

### 2. Configure solidtime

```env
ENTRA_CALENDAR_ENABLED=true
ENTRA_CALENDAR_TENANT_ID=<tenant id, or "organizations" for a multi-tenant app>
ENTRA_CALENDAR_CLIENT_ID=<application (client) id>
ENTRA_CALENDAR_CLIENT_SECRET=<client secret>
# optional
ENTRA_CALENDAR_REDIRECT_URI=        # default: APP_URL + /outlook/callback
ENTRA_CALENDAR_DEFAULT_DAYS=7       # range when the user never imported
ENTRA_CALENDAR_MAX_DAYS=62          # longest range per request
ENTRA_CALENDAR_HTTP_TIMEOUT=15      # seconds
```

Run the migrations, which create `outlook_connections` and `calendar_imports`. With `ENTRA_CALENDAR_ENABLED=false`, or
any of the first four values missing, the button does not show and the routes answer 404.

## Security

- **Least privilege:** the scopes are delegated and read-only, so each user can only read their own calendar.
- **OAuth flow:**
  - authorization code with **PKCE** (S256);
  - the `state` parameter is checked with `hash_equals`;
  - the login hint is the user's e-mail.
- **Token storage:** refresh and access tokens are stored with Laravel's `encrypted` cast (`APP_KEY`), hidden from
  serialization, and never written to logs, audits or API responses.
- **Error logging:** on errors only the HTTP status and the Entra ID error code are logged, never the response body.
- **Revoked access:** if Entra ID rejects the refresh token (revoked, expired or password changed), the connection is
  removed and the user is asked to connect again.
- **Outbound traffic:** goes only to `login.microsoftonline.com` and `graph.microsoft.com`. Graph pagination links are
  followed only when they point back to Graph.
- **Permissions in solidtime:** importing needs the same permission as creating an own time entry
  (`time-entries:create:own`).

## API

`GET /api/v1/organizations/{organization}/calendar-events` (optional `start`/`end`, `Y-m-d`),
`POST .../calendar-events/import` and `POST .../calendar-events/ignore`. They are included in the generated API client
(`getCalendarEvents`, `importCalendarEvents`, `ignoreCalendarEvents`).

## Files

- `app/Service/OutlookCalendarService.php`: OAuth and Graph calls.
- `app/Http/Controllers/Web/OutlookController.php`: connect, callback and disconnect.
- `app/Http/Controllers/Api/V1/CalendarEventController.php`: list, import and ignore.
- `app/Models/{OutlookConnection,CalendarImport}.php` and two migrations.
- `resources/js/Components/Outlook/OutlookCalendarImportModal.vue` and the button in `resources/js/Pages/Time.vue`.
- Tests:
  - `tests/Feature/OutlookConnectionTest.php`;
  - `tests/Unit/Endpoint/Api/V1/CalendarEventEndpointTest.php` (Graph and Entra ID faked with `Http::fake`).
