<?php

namespace App\Auth;

use Illuminate\Validation\ValidationException;

/**
 * A sign-in refused because the player is signed in on another device. A validation error on `email`, so every
 * sign-in form (and the passkey JSON call) shows it where it already shows sign-in errors.
 */
class SignedInElsewhere extends ValidationException
{
    public const MESSAGE = 'You are signed in on another device. Sign out there first, or we can e-mail you a link that signs it out.';

    public static function make(): self
    {
        return static::withMessages(['email' => self::MESSAGE]);
    }
}
