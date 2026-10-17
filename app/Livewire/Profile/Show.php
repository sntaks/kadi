<?php

namespace App\Livewire\Profile;

use App\Actions\Security\RevokeOtherSessions;
use App\Concerns\ProfileValidationRules;
use App\Events\PasswordChanged;
use App\Facades\KadiApi;
use App\Services\KadiAccountSync;
use App\Services\WalletBalanceFetcher;
use App\Support\PlayerName;
use Illuminate\Contracts\View\Factory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profile | Kadi')]
class Show extends Component
{
    use ProfileValidationRules;

    public string $name = '';

    public string $email = '';

    public string $idNo = '';

    public string $phoneNo = '';

    public array $kadiCustomer = [];

    public string $activeTab = 'info';

    public string $currentPassword = '';

    public string $newPassword = '';

    public string $newPasswordConfirmation = '';

    public string $newSetPassword = '';

    public string $newSetPasswordConfirmation = '';

    public bool $isGoogleUser = false;

    public string $profilePicUrl = '';

    public string $resolvedAvatarUrl = '';

    /** The KadiApi profile cache is cold: loadCustomer() fetches it after first paint. */
    public bool $needsLoad = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->isGoogleUser = $user->google_id !== null;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->applyCustomer(Cache::get("kadi.customer.{$user->id}", []));
        $this->needsLoad = $this->kadiCustomer === [] && $user->linked_id;
    }

    /**
     * wire:init: fetch the KadiApi profile a cold cache could not show. Goes through the shared
     * fetcher (same lock and cache keys as the header balance), so loading both costs one call.
     */
    public function loadCustomer(): void
    {
        $this->needsLoad = false;
        $user = auth()->user();

        if (app(WalletBalanceFetcher::class)->fetch($user)['fresh']) {
            $this->dispatch('wallet-refreshed');
        }

        $this->applyCustomer(Cache::get("kadi.customer.{$user->id}", []));
    }

    private function applyCustomer(array $customer): void
    {
        $this->kadiCustomer = $customer;
        $this->idNo = (string) ($customer['id_no'] ?? '');
        $this->phoneNo = (string) ($customer['phone_no'] ?? auth()->user()->phone ?? '');
        $this->profilePicUrl = $this->buildProfilePicUrl();
        $this->resolvedAvatarUrl = $this->resolveAvatarUrl();
    }

    public function updateProfile(): void
    {
        // Save is disabled until the profile has loaded; never save over fields that were not loaded.
        if ($this->needsLoad) {
            $this->loadCustomer();
        }

        $user = auth()->user();

        // A phone is set once (it is the M-Pesa payout number); after that the field is ignored.
        $settingPhone = empty($user->phone) && $this->phoneNo !== '';

        $this->validate([
            'name' => $this->nameRules(),
            'email' => ['required', 'email', 'unique:users,email,'.auth()->id()],
            'idNo' => ['nullable', 'string'],
            'phoneNo' => $settingPhone ? $this->phoneRules($user->id) : ['nullable', 'string'],
        ]);

        $nameChanged = $user->name !== $this->name;
        $previousEmail = $user->email;

        $userUpdate = ['name' => $this->name, 'email' => $this->email];
        // Only allow setting phone when it isn't already on record
        if ($settingPhone) {
            $userUpdate['phone'] = $this->phoneNo;
        }
        $user->fill($userUpdate);
        PlayerName::stampChange($user);
        $user->save();
        PlayerName::clearIfResolved($user);

        // The game reads the name from kadi.accounts, so keep it in step.
        if ($nameChanged) {
            app(KadiAccountSync::class)->syncName($user, $previousEmail);
        }

        $customerId = $user->linked_id;

        if ($customerId) {
            try {
                // Blanks are dropped as well as nulls: an empty id_no (profile not loaded, or never
                // set) must not overwrite the one KadiApi holds.
                $response = KadiApi::updateCustomer($customerId, array_filter([
                    'name' => $this->name,
                    'id_no' => $this->idNo,
                    // Only when it was just set, and as stored (254XXXXXXXXX, no `+`).
                    'phone_no' => $settingPhone ? $user->phone : null,
                ], fn ($v) => $v !== null && $v !== ''));

                if (is_array($response['data'] ?? null)) {
                    // Merged, not overwritten: fields the update response leaves out (player_no,
                    // balance) must not disappear from the profile and wallet until the next fetch.
                    $this->kadiCustomer = array_merge($this->kadiCustomer, $response['data']);
                    Cache::put('kadi.customer.'.auth()->id(), $this->kadiCustomer, now()->addHour());
                }
            } catch (\Throwable $e) {
                Log::error('KadiApi profile update failed: '.$e->getMessage());
            }
        }

        // Sync phone to kadi database when setting it for the first time
        if ($settingPhone) {
            $this->phoneNo = (string) $user->phone;
            app(KadiAccountSync::class)->syncPhone($user);
        }

        session()->flash('profile_success', 'Profile updated successfully.');
    }

    public function updatePassword(): void
    {
        $this->validate([
            'currentPassword' => ['required'],
            'newPassword' => ['required', Password::defaults()],
            'newPasswordConfirmation' => ['required', 'same:newPassword'],
        ]);

        if (! Hash::check($this->currentPassword, auth()->user()->password)) {
            $this->addError('currentPassword', 'Current password is incorrect.');

            return;
        }

        $user = auth()->user();

        $user->update([
            'password' => Hash::make($this->newPassword),
        ]);

        PasswordChanged::dispatch($user);

        app(RevokeOtherSessions::class)($user);

        $this->reset('currentPassword', 'newPassword', 'newPasswordConfirmation');
        session()->flash('password_success', 'Password updated successfully.');
    }

    public function setPassword(): void
    {
        $this->validate([
            'newSetPassword' => ['required', Password::defaults()],
            'newSetPasswordConfirmation' => ['required', 'same:newSetPassword'],
        ]);

        $user = auth()->user();

        $user->update([
            'password' => Hash::make($this->newSetPassword),
        ]);

        PasswordChanged::dispatch($user);

        app(RevokeOtherSessions::class)($user);

        $this->reset('newSetPassword', 'newSetPasswordConfirmation');
        session()->flash('password_success', 'Password set successfully. You can now use 2FA and passkeys.');
    }

    private function resolveAvatarUrl(): string
    {
        // 1. Kadi-uploaded profile picture (highest priority)
        $uploaded = $this->buildProfilePicUrl();
        if ($uploaded !== '') {
            return $uploaded;
        }

        $user = auth()->user();

        // 2. Google avatar (linked account)
        if ($user->avatar) {
            return $user->avatar;
        }

        // 3. Gravatar with robohash fallback
        $hash = md5(strtolower(trim($user->email)));

        return "https://www.gravatar.com/avatar/{$hash}?d=robohash&r=pg&s=200";
    }

    private function buildProfilePicUrl(): string
    {
        $pic = $this->kadiCustomer['pic'] ?? null;
        $accountId = $this->kadiCustomer['id'] ?? auth()->user()->linked_id;

        if (! $pic || ! $accountId) {
            return '';
        }

        $base = rtrim(config('services.kadi_api.image_url'), '/');

        return $pic === 'profilepic.png' ? asset('images/avatar.png') : "{$base}{$pic}";
    }

    public function nextNameChange(): ?string
    {
        return PlayerName::nextChangeAt(auth()->user())?->format('j F Y');
    }

    public function render(): Factory|\Illuminate\Contracts\View\View|View
    {
        return view('livewire.profile.show')
            ->layout('layouts.app');
    }
}
