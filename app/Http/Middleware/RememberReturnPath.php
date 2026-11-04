<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The sign-in sheet opens over any page and posts to Fortify's own routes. It sends `return_to`, the
 * page it opened on, so a successful log in or sign-up lands back there (Fortify redirects to the
 * intended URL). It also flashes which form was sent (`signin_form`).
 *
 * Only a same-site path is accepted, never a full URL, `//host`, a sign-in page or an internal endpoint,
 * so this cannot become an open redirect or a loop. The return path lives only as long as the attempt:
 * when the player is still a guest afterwards (wrong password, invalid form) the intended URL is put back
 * as it was, so a later, unrelated login is not sent to a page from an abandoned attempt.
 */
class RememberReturnPath
{
    public const FORMS = ['login', 'join', 'forgot'];

    /** Pages a player should never be sent back to after signing in: the sign-in pages themselves. */
    private const NEVER_PAGES = ['/login', '/register', '/forgot-password', '/reset-password', '/two-factor-challenge', '/logout'];

    /** Internal endpoints (Livewire's is /livewire-{hash}/…): any path that starts with one of these. */
    private const NEVER_PREFIXES = ['/livewire', '/auth/', '/user/', '/email/', '/broadcasting', '/push/', '/api/', '/sanctum/'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || $request->user() !== null) {
            return $next($request);
        }

        // Which sign-in form was sent, for the next page: the sheet reopens on it and only that form shows
        // the errors. Fortify's success redirects carry no old input.
        if (in_array($request->input('_sheet'), self::FORMS, true)) {
            $request->session()->flash('signin_form', $request->input('_sheet'));
        }

        $before = $request->session()->get('url.intended');
        $remembered = self::remember($request, $request->input('return_to'));

        $response = $next($request);

        // Still a guest and not half-way through two-factor: the attempt failed, so forget its return path.
        if ($remembered && auth()->guest() && ! $request->session()->has('login.id')) {
            $before === null ? $request->session()->forget('url.intended') : $request->session()->put('url.intended', $before);
        }

        return $response;
    }

    /** Store a guest's return path as the intended URL. Shared with the Google redirect. */
    public static function remember(Request $request, mixed $path): bool
    {
        if ($request->user() !== null || ! is_string($path) || ! self::isSafe($path)) {
            return false;
        }

        $request->session()->put('url.intended', url($path));
        $request->session()->put('signin.return_to', $path);

        return true;
    }

    /** Drop a return path this sign-in flow stored (a cancelled Google sign-up), leaving any other intended URL alone. */
    public static function forget(Request $request): void
    {
        $path = $request->session()->pull('signin.return_to');

        if (is_string($path) && $request->session()->get('url.intended') === url($path)) {
            $request->session()->forget('url.intended');
        }
    }

    public static function isSafe(string $path): bool
    {
        if (! str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\') || strlen($path) > 512
            || preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return false;
        }

        $bare = (string) parse_url($path, PHP_URL_PATH);

        foreach (self::NEVER_PAGES as $page) {
            if ($bare === $page || str_starts_with($bare, $page.'/')) {
                return false;
            }
        }

        foreach (self::NEVER_PREFIXES as $prefix) {
            if (str_starts_with($bare, $prefix) || $bare === rtrim($prefix, '/')) {
                return false;
            }
        }

        return true;
    }
}
