<?php

namespace App\Http\Controllers\Dev;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Contracts\View\View;

/**
 * Renders the mail templates in the browser for design work. Answers only when
 * `app.mail_previews` is on (local by default) and always uses a made-up player,
 * never a real account.
 */
class MailPreviewController extends Controller
{
    public function __invoke(string $type): View
    {
        abort_unless(config('app.mail_previews'), 404);

        $user = (new User)->forceFill([
            'name' => 'Preview Player',
            'email' => 'player@example.com',
            'account_no' => 'KK-PREVIEW',
        ]);

        return match ($type) {
            'verify' => view('mail.verify-email', [
                'user' => $user,
                'verificationUrl' => '#',
                'appName' => config('app.name'),
            ]),
            'welcome' => view('mail.welcome', [
                'user' => $user,
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ]),
            'security' => view('mail.security-alert', [
                'user' => $user,
                'change' => 'Email address changed',
                'when' => now()->format('j M Y, H:i T'),
                'appName' => config('app.name'),
                'appUrl' => config('app.url'),
            ]),
            default => abort(404),
        };
    }
}
