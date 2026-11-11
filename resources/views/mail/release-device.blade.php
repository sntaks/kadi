<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Sign out your other device') }} — {{ $appName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#0a0a0a;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#0a0a0a;">
        <tr>
            <td align="center" style="padding:40px 16px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-bottom:24px;">
                            <span style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(245,197,66,0.6);">
                                {{ $appName }} · Security
                            </span>
                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;background-color:#1a1a1a;border:1px solid rgba(245,197,66,0.3);border-radius:12px;">
                    <tr>
                        <td style="padding:32px;">

                            <h1 style="margin:0 0 16px;font-size:22px;font-weight:700;color:#f5f5f0;">
                                {{ __('Sign out your other device') }}
                            </h1>

                            <p style="margin:0 0 12px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                {{ __('Hi :name,', ['name' => $user->name]) }}
                            </p>

                            <p style="margin:0 0 20px;font-size:15px;line-height:1.6;color:#e5e5e5;">
                                {{ __('Someone who knows your sign-in details asked to sign out the other device that is signed in to your account. If that was you, use the button below. It works once, for :minutes minutes.', ['minutes' => $minutes]) }}
                            </p>

                            <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:0 0 24px;">
                                <tr>
                                    <td style="border-radius:8px;background-color:#f5c542;">
                                        <a href="{{ $url }}" style="display:inline-block;padding:12px 24px;font-size:15px;font-weight:700;color:#0a0a0a;text-decoration:none;">{{ __('Sign out my other device') }}</a>
                                    </td>
                                </tr>
                            </table>

                            <p style="margin:0;font-size:14px;line-height:1.6;color:#6b6b6b;">
                                {{ __("If this wasn't you, don't use the link: someone knows your password. Change it now from your account's Security tab.") }}
                            </p>

                        </td>
                    </tr>
                </table>

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:520px;">
                    <tr>
                        <td align="center" style="padding-top:20px;">
                            <p style="margin:0;font-size:11px;color:#3a3a3a;line-height:1.6;">
                                {{ __('You are receiving this email because someone asked to sign out a device on your account.') }}<br>
                                &copy; {{ date('Y') }} {{ $appName }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

</body>
</html>
