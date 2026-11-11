<?php

namespace App\Events;

use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** A sign-in with valid credentials refused because the player is signed in on another device (App\Auth\SingleSession). */
class SignInRefused
{
    use Dispatchable;

    public function __construct(public User $user) {}
}
