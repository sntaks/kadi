{{--
    /account (plan Phase 6): Profile · Security · Notifications · Connected accounts, the tabs of the old
    profile page in the Night table look. The same component (App\Livewire\Profile\Show) and rules:
    nameRules(), phoneRules() and KadiAccountSync run exactly as before.
--}}
<x-account.frame :avatar="$resolvedAvatarUrl" active="account">
    <div x-data="{ tabs: ['profile', 'security', 'notifications', 'connected'], tab: 'profile' }"
         {{-- #info is the old profile page's name for the first tab; links and redirects may still carry it. --}}
         x-init="const h = location.hash.slice(1) === 'info' ? 'profile' : location.hash.slice(1); tab = tabs.includes(h) ? h : 'profile'"
         x-on:hashchange.window="const h = location.hash.slice(1) === 'info' ? 'profile' : location.hash.slice(1); if (tabs.includes(h)) tab = h"
         @if ($needsLoad) wire:init="loadCustomer" @endif>

        @foreach (['google-linked' => 'Your Google account is linked.'] as $status => $message)
            @if (session('status') === $status)<p class="nt-ok mb-4" role="status">{{ $message }}</p>@endif
        @endforeach
        @if ($errors->has('google'))<p class="nt-error mb-4" role="alert">{{ $errors->first('google') }}</p>@endif
        @if (session('profile_success'))<p class="nt-ok mb-4" role="status">{{ session('profile_success') }}</p>@endif
        @if (session('password_success'))<p class="nt-ok mb-4" role="status">{{ session('password_success') }}</p>@endif

        <div class="nt-chips mb-4" role="tablist" aria-label="Account settings">
            @foreach (['profile' => 'Profile', 'security' => 'Security', 'notifications' => 'Notifications', 'connected' => 'Connected accounts'] as $key => $label)
                <button type="button" role="tab" class="nt-chip" id="tab-{{ $key }}" aria-controls="panel-{{ $key }}"
                        x-bind:aria-selected="tab === '{{ $key }}'" x-bind:tabindex="tab === '{{ $key }}' ? 0 : -1"
                        x-on:click="tab = '{{ $key }}'; history.replaceState(null, '', '#{{ $key }}')"
                        data-test="account-tab-{{ $key }}">{{ $label }}</button>
            @endforeach
        </div>

        <div class="nt-area">
            <div class="nt-stack">
                {{-- Profile --}}
                <section id="panel-profile" role="tabpanel" aria-labelledby="tab-profile" x-show="tab === 'profile'" class="nt-panel nt-card-pad">
                    <h2>Profile</h2>
                    <p>Your player name is what opponents see at the table.</p>

                    <form wire:ignore action="{{ route('profile.picture') }}" method="POST" enctype="multipart/form-data"
                          class="mb-6 flex flex-wrap items-center gap-4 border-b border-line pb-6" x-data="profilePicture()">
                        @csrf
                        <img src="{{ $resolvedAvatarUrl }}" x-bind:src="preview || $wire.resolvedAvatarUrl" alt="Your profile picture"
                             width="72" height="72" class="size-[72px] rounded-full border border-line-2 object-cover">
                        <div class="grid gap-2">
                            <span class="text-sm font-semibold text-ivory">Profile picture</span>
                            <span class="nt-hint">JPG, PNG or WebP, up to 2 MB.</span>
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="nt-btn nt-btn-ghost nt-btn-sm cursor-pointer">
                                    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only" x-on:change="choose($event)">
                                    Choose a photo
                                </label>
                                <x-ui.button size="sm" type="submit" x-show="preview" x-cloak x-bind:disabled="uploading" x-on:click="uploading = true">
                                    <span x-text="uploading ? 'Uploading…' : 'Upload'">Upload</span>
                                </x-ui.button>
                                <x-ui.button variant="quiet" size="sm" x-show="preview" x-cloak x-on:click="preview = null">Cancel</x-ui.button>
                            </div>
                            @error('photo')<p class="nt-error">{{ $message }}</p>@enderror
                            <p class="nt-error" x-show="error" x-text="error" x-cloak></p>
                        </div>
                    </form>

                    <form wire:submit="updateProfile" class="grid gap-5">
                        @php($nextNameChange = $this->nextNameChange())
                        <div>
                            <x-player-name-field wire:model="name" :value="$name" :label="__('Player name')"
                                :locked="$nextNameChange !== null"
                                :locked-message="__('You can change your name once a year. You can change it again on :date.', ['date' => $nextNameChange])" />
                            @error('name')<p class="nt-error mt-1">{{ $message }}</p>@enderror
                        </div>

                        <label class="nt-field">
                            <span>E-mail</span>
                            <input type="email" value="{{ $email }}" disabled>
                        </label>

                        @if (empty(auth()->user()->phone))
                            <label class="nt-field">
                                <span>M-Pesa number</span>
                                <input type="tel" wire:model="phoneNo" placeholder="07XX XXX XXX" autocomplete="tel" required>
                                <span class="nt-hint">Deposits and withdrawals go to this number.</span>
                                @error('phoneNo')<span class="nt-error">{{ $message }}</span>@enderror
                            </label>
                        @else
                            <div class="nt-field">
                                <span>M-Pesa number</span>
                                <div class="flex items-center justify-between gap-3 rounded-control border border-line bg-felt-2 px-[14px] py-3">
                                    <span class="nt-num text-ivory">{{ $phoneNo }}</span>
                                    @if (auth()->user()->hasVerifiedPhone())
                                        <x-ui.pill tone="ok">Confirmed</x-ui.pill>
                                    @else
                                        <x-ui.button size="sm" x-on:click="$dispatch('open-phone-required', { purpose: 'verify' })" data-test="profile-verify-phone">Confirm</x-ui.button>
                                    @endif
                                </div>
                            </div>
                        @endif

                        <div>
                            <x-ui.button type="submit" data-test="profile-save" :disabled="$needsLoad" wire:loading.attr="disabled" wire:target="updateProfile">
                                <span wire:loading.remove wire:target="updateProfile">Save changes</span>
                                <span wire:loading wire:target="updateProfile">Saving…</span>
                            </x-ui.button>
                        </div>
                    </form>
                </section>

                {{-- Security --}}
                <section id="panel-security" role="tabpanel" aria-labelledby="tab-security" x-show="tab === 'security'" x-cloak class="nt-panel nt-card-pad">
                    <h2>Security</h2>
                    <p>Your password, two-factor sign-in and passkeys.</p>

                    @if ($isGoogleUser)
                        <div class="nt-callout mb-5"><span><b>You signed in with Google.</b> Set a password to turn on two-factor sign-in, passkeys and password sign-in.</span></div>
                        <form wire:submit="setPassword" class="grid gap-4">
                            <label class="nt-field"><span>New password</span><input type="password" wire:model="newSetPassword" autocomplete="new-password"></label>
                            @error('newSetPassword')<p class="nt-error">{{ $message }}</p>@enderror
                            <label class="nt-field"><span>Confirm new password</span><input type="password" wire:model="newSetPasswordConfirmation" autocomplete="new-password"></label>
                            <div><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="setPassword">Set password</x-ui.button></div>
                        </form>
                    @else
                        <form wire:submit="updatePassword" class="grid gap-4">
                            <label class="nt-field"><span>Current password</span><input type="password" wire:model="currentPassword" autocomplete="current-password"></label>
                            @error('currentPassword')<p class="nt-error">{{ $message }}</p>@enderror
                            <label class="nt-field"><span>New password</span><input type="password" wire:model="newPassword" autocomplete="new-password"></label>
                            @error('newPassword')<p class="nt-error">{{ $message }}</p>@enderror
                            <label class="nt-field"><span>Confirm new password</span><input type="password" wire:model="newPasswordConfirmation" autocomplete="new-password"></label>
                            <div><x-ui.button type="submit" wire:loading.attr="disabled" wire:target="updatePassword">Update password</x-ui.button></div>
                        </form>
                    @endif

                    <section class="mt-8 border-t border-line pt-8" aria-label="{{ __('Two-factor sign-in') }}">
                        <livewire:profile.security.two-factor-panel />
                    </section>
                    <section class="mt-8 border-t border-line pt-8" aria-label="{{ __('Passkeys') }}">
                        <livewire:profile.security.passkey-manager />
                    </section>
                    <div class="mt-8 border-t border-line pt-8">
                        <livewire:account.close-account />
                    </div>
                </section>

                {{-- Notifications --}}
                <section id="panel-notifications" role="tabpanel" aria-labelledby="tab-notifications" x-show="tab === 'notifications'" x-cloak class="nt-panel nt-card-pad">
                    <h2>Notifications</h2>
                    <p>Choose whether this device can send you alerts from Kadi.</p>
                    <x-pwa.notification-toggle />
                </section>

                {{-- Connected accounts --}}
                <section id="panel-connected" role="tabpanel" aria-labelledby="tab-connected" x-show="tab === 'connected'" x-cloak class="nt-panel nt-card-pad">
                    <h2>Connected accounts</h2>
                    <p>Link your Google account to sign in with one tap.</p>
                    <livewire:settings.link-google-account />
                </section>
            </div>

            <aside class="nt-stack" aria-label="Your account at a glance">
                <div class="nt-panel nt-card-pad">
                    <h2 class="nt-card-title">Your account</h2>
                    <dl class="nt-breakdown mt-3">
                        <div><dt>Player no.</dt><dd class="nt-num" data-test="profile-player-no">
                            @if ($needsLoad)<x-skeleton inline class="h-4 w-20 rounded" />@else{{ \App\Support\PlayerNumber::format($kadiCustomer['player_no'] ?? null) ?? '—' }}@endif
                        </dd></div>
                        <div><dt>E-mail</dt><dd class="truncate">{{ auth()->user()->email }}</dd></div>
                        <div><dt>Kadi wallet</dt><dd>{{ auth()->user()->isLinked() ? 'Linked' : 'Being set up' }}</dd></div>
                    </dl>
                </div>
                <div class="nt-panel nt-card-pad">
                    <h2 class="nt-card-title">Your totals</h2>
                    <div class="mt-3 grid grid-cols-2 gap-3">
                        @foreach ([
                            ['Deposits', 'KES '.number_format($kadiCustomer['deposits'] ?? 0, 2)],
                            ['Withdrawals', 'KES '.number_format($kadiCustomer['withdraws'] ?? 0, 2)],
                            ['Single games', number_format($kadiCustomer['single_played'] ?? 0)],
                            ['Tournaments', number_format($kadiCustomer['competition_played'] ?? 0)],
                        ] as [$label, $value])
                            <div class="nt-stat">
                                <span>{{ $label }}</span>
                                @if ($needsLoad)<x-skeleton class="h-5 w-16 rounded" data-test="profile-stat-skeleton" />@else<b class="nt-num text-[18px]">{{ $value }}</b>@endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </aside>
        </div>
    </div>

    <script>
        function profilePicture() {
            return {
                preview: null,
                uploading: false,
                error: null,
                choose(event) {
                    const file = event.target.files[0];
                    if (!file) return;
                    if (!['image/jpeg', 'image/jpg', 'image/png', 'image/webp'].includes(file.type)) {
                        this.error = 'The photo must be a JPG, PNG or WebP file.';
                        this.preview = null;
                        event.target.value = '';
                        return;
                    }
                    if (file.size > 2 * 1024 * 1024) {
                        this.error = 'The photo must not be larger than 2 MB.';
                        this.preview = null;
                        event.target.value = '';
                        return;
                    }
                    this.error = null;
                    const reader = new FileReader();
                    reader.onload = (e) => { this.preview = e.target.result; };
                    reader.readAsDataURL(file);
                },
            };
        }
    </script>
</x-account.frame>
