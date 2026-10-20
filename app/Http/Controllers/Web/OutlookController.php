<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Models\OutlookConnection;
use App\Models\User;
use App\Service\OutlookCalendarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Connect/disconnect the logged-in user's Outlook calendar (authorization code + PKCE with Entra ID).
 * No token in logs or messages; errors become a friendly notice on the Time page.
 */
class OutlookController extends Controller
{
    private const string SESSION_KEY = 'outlook_oauth';

    public function connect(Request $request, OutlookCalendarService $outlook): RedirectResponse
    {
        abort_unless($outlook->isEnabled(), 404);

        $pkce = $outlook->makePkce();
        $state = Str::random(40);
        $request->session()->put(self::SESSION_KEY, [
            'state' => $state,
            'verifier' => $pkce['verifier'],
        ]);

        return redirect()->away($outlook->authorizationUrl($state, $pkce['challenge'], $this->currentUser()->email));
    }

    public function callback(Request $request, OutlookCalendarService $outlook): RedirectResponse
    {
        abort_unless($outlook->isEnabled(), 404);

        /** @var array{state?: string, verifier?: string}|null $pending */
        $pending = $request->session()->pull(self::SESSION_KEY);
        $state = $request->query('state');
        $code = $request->query('code');

        if ($request->query('error') !== null) {
            return $this->back(__('The Outlook connection was cancelled or not authorized.'), 'warning');
        }
        if (! is_array($pending) || ! isset($pending['state'], $pending['verifier'])
            || ! is_string($state) || ! hash_equals($pending['state'], $state)
            || ! is_string($code) || $code === '') {
            return $this->back(__('The Outlook connection could not be completed. Please try again.'), 'danger');
        }

        try {
            $outlook->connect($this->currentUser(), $code, $pending['verifier']);
        } catch (Throwable $exception) {
            report($exception);

            return $this->back(__('The Outlook connection could not be completed. Please try again.'), 'danger');
        }

        return $this->back(__('Outlook connected. You can now import your meetings.'), 'success', true);
    }

    public function disconnect(OutlookCalendarService $outlook): RedirectResponse
    {
        abort_unless($outlook->isEnabled(), 404);

        OutlookConnection::query()->where('user_id', $this->currentUser()->getKey())->delete();

        return redirect()->back(fallback: route('time'))
            ->with('bannerText', __('Outlook disconnected.'))
            ->with('bannerStyle', 'success');
    }

    private function back(string $message, string $style, bool $openImport = false): RedirectResponse
    {
        return redirect()->route('time', $openImport ? ['outlook' => 'connected'] : [])
            ->with('bannerText', $message)
            ->with('bannerStyle', $style);
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
