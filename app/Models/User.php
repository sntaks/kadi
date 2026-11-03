<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use NotificationChannels\WebPush\HasPushSubscriptions;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password', 'account_no', 'phone', 'linked_id', 'google_id', 'avatar'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasPushSubscriptions, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'age_confirmed_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'name_changed_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'verified_reported_at' => 'datetime',
            'promo_code_applied' => 'boolean',
            'promo_notice_dismissed_at' => 'datetime',
            'promo_jackpot_wallet_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Consent columns for the current terms version. Not mass-assignable;
     * apply with forceFill() so consent can only be set by our own code.
     *
     * @return array<string, mixed>
     */
    public static function consentAttributes(): array
    {
        return [
            'age_confirmed_at' => now(),
            'terms_accepted_at' => now(),
            'terms_version' => config('kadi.terms_version'),
        ];
    }

    public function hasCurrentConsent(): bool
    {
        return $this->age_confirmed_at !== null
            && $this->terms_accepted_at !== null
            && $this->terms_version === config('kadi.terms_version');
    }

    public function recordConsent(): void
    {
        $this->forceFill(static::consentAttributes())->save();
    }

    public function isLinked(): bool
    {
        return ! is_null($this->linked_id);
    }

    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['super-admin', 'admin']);
    }

    /**
     * Store the phone as 254XXXXXXXXX (no +, spaces or leading 0); blank becomes null.
     */
    public function setPhoneAttribute($value): void
    {
        $phone = PhoneNumber::normalize($value === null ? null : (string) $value);

        // A changed number has not been proven yet. (Only allowed while unverified: see PhoneRequired.)
        if (array_key_exists('phone', $this->attributes) && $this->attributes['phone'] !== $phone) {
            $this->attributes['phone_verified_at'] = null;
        }

        $this->attributes['phone'] = $phone;
    }

    /**
     * The phone was confirmed with an SMS code. Required before deposits, withdrawals and referral
     * payouts (the money goes to this number).
     */
    public function hasVerifiedPhone(): bool
    {
        return $this->phone !== null && $this->phone_verified_at !== null;
    }

    /**
     * Signed up with a promo code that may still earn the signup bonus: not refused at POST
     * customers and verification not reported yet. Drives the "verify now" nudges; KadiApi decides.
     */
    public function awaitsSignupBonus(): bool
    {
        return config('kadi.promotions.enabled')
            && $this->signup_promo_code !== null
            && $this->promo_code_applied !== false
            && $this->verified_reported_at === null;
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))
            ->implode('');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function gameDisputes(): HasMany
    {
        return $this->hasMany(GameDispute::class);
    }
}
