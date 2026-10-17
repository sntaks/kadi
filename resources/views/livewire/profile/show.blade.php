<div x-data="{ tab: ['info', 'security', 'connected', 'notifications'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'info' }" class="mx-auto max-w-5xl space-y-6" @if ($needsLoad) wire:init="loadCustomer" @endif>
{{-- google-linked session flash --}}
@if (session('status') === 'google-linked')
    <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-green-400">
        Google account linked successfully.
    </div>
@endif
@if ($errors->has('google'))
    <div class="rounded-lg border border-red-700 bg-red-900/30 p-4 text-sm text-red-400">
        {{ $errors->first('google') }}
    </div>
@endif

    {{-- Flash messages --}}
    @if (session('profile_success'))
        <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-green-400">
            {{ session('profile_success') }}
        </div>
    @endif
    @if (session('password_success'))
        <div class="rounded-lg border border-green-700 bg-green-900/30 p-4 text-sm text-green-400">
            {{ session('password_success') }}
        </div>
    @endif

    <h1 class="text-3xl font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">👤 My Profile</h1>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- CARD 1: Edit Profile Form (left, spans 2 cols) --}}
        <div class="lg:col-span-2 space-y-4">

            {{-- Tab switcher --}}
            {{-- Four tabs don't fit one row on phones, so they form a 2x2 grid there and a single row from `sm` up. --}}
            <div class="grid grid-cols-2 gap-2 rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-1.5 sm:flex">
                <button
                    @click="tab = 'info'"
                    :class="tab === 'info' ? 'bg-[#f5c542] text-black' : 'text-[#6b6b6b] hover:text-[#f5f5f0]'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                    style="font-family: 'Cinzel', serif;"
                >
                    Personal Info
                </button>
                <button
                    @click="tab = 'security'"
                    :class="tab === 'security' ? 'bg-[#f5c542] text-black' : 'text-[#6b6b6b] hover:text-[#f5f5f0]'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                    style="font-family: 'Cinzel', serif;"
                >
                    Security
                </button>
                <button
                    @click="tab = 'connected'"
                    :class="tab === 'connected' ? 'bg-[#f5c542] text-black' : 'text-[#6b6b6b] hover:text-[#f5f5f0]'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                    style="font-family: 'Cinzel', serif;"
                >
                    Connected Accounts
                </button>
                <button
                    @click="tab = 'notifications'"
                    :class="tab === 'notifications' ? 'bg-[#f5c542] text-black' : 'text-[#6b6b6b] hover:text-[#f5f5f0]'"
                    class="flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition"
                    style="font-family: 'Cinzel', serif;"
                    data-test="profile-tab-notifications"
                >
                    Notifications
                </button>
            </div>

            {{-- Tab 1: Personal Info --}}
            <div x-show="tab === 'info'" class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-8">
                <h2 class="mb-6 text-lg font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Personal Information</h2>

                {{-- Profile Picture Upload (plain multipart form — avoids Livewire temp-upload issues) --}}
                <form wire:ignore action="{{ route('profile.picture') }}" method="POST" enctype="multipart/form-data"
                      class="mb-8 flex items-center gap-6 pb-6 border-b border-yellow-800/20"
                      x-data="profilePic()">
                    @csrf

                    {{-- Avatar / JS preview --}}
                    <div class="relative flex-shrink-0">
                        {{-- $wire keeps the avatar live inside wire:ignore (it changes once loadCustomer() runs). --}}
                        <img id="pic-preview"
                             src="{{ $resolvedAvatarUrl }}"
                             :src="preview || $wire.resolvedAvatarUrl"
                             alt="Profile picture"
                             class="h-20 w-20 rounded-full object-cover border-2 border-[#f5c542]/40" />
                        <span x-show="preview"
                              class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-[#f5c542] text-[10px] text-black font-bold">✓</span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <p class="mb-1 text-sm font-semibold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Profile Picture</p>
                        <p class="mb-3 text-xs text-[#6b6b6b]">JPG, PNG or WebP · The photo must not exceed 2MB</p>

                        <div class="flex flex-wrap items-center gap-3">
                            <label class="cursor-pointer">
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp"
                                       class="sr-only" @change="onFile($event)" />
                                <span class="inline-flex items-center gap-2 rounded-lg border border-[#f5c542]/40 bg-[#f5c542]/5 px-4 py-2 text-xs font-semibold text-[#f5c542] transition hover:bg-[#f5c542]/10 cursor-pointer">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                    </svg>
                                    Choose Photo
                                </span>
                            </label>

                            <button x-show="preview" type="submit" :disabled="uploading"
                                    @submit="uploading = true"
                                    class="inline-flex items-center gap-2 rounded-lg bg-[#f5c542] px-4 py-2 text-xs font-bold text-black transition hover:bg-[#ffde74] disabled:opacity-50">
                                <span x-show="uploading" class="animate-spin inline-block">⟳</span>
                                <span x-text="uploading ? 'Uploading…' : 'Upload Photo'">Upload Photo</span>
                            </button>

                            <button x-show="preview" type="button" @click="preview = null; uploading = false"
                                    class="text-xs text-[#6b6b6b] transition hover:text-red-400">
                                Cancel
                            </button>
                        </div>

                        @if ($errors->has('photo'))
                            <p class="mt-2 text-xs text-red-400">{{ $errors->first('photo') }}</p>
                        @endif
                        <p x-show="error" x-text="error" class="mt-2 text-xs text-red-400"></p>
                    </div>
                </form>

                <script>
                function profilePic() {
                    return {
                        preview: null,
                        uploading: false,
                        error: null,
                        onFile(e) {
                            const file = e.target.files[0];
                            if (!file) return;

                            // Validate file type
                            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
                            if (!allowedTypes.includes(file.type)) {
                                this.error = 'The photo must be a JPG, JPEG, PNG, or WEBP file.';
                                this.preview = null;
                                e.target.value = '';
                                return;
                            }

                            // Validate file size (2MB = 2 * 1024 * 1024 bytes)
                            const maxSize = 2 * 1024 * 1024;
                            if (file.size > maxSize) {
                                this.error = 'The photo must not exceed 2MB.';
                                this.preview = null;
                                e.target.value = '';
                                return;
                            }

                            this.error = null;
                            const reader = new FileReader();
                            reader.onload = ev => { this.preview = ev.target.result; };
                            reader.readAsDataURL(file);
                        }
                    }
                }
                </script>

                <form wire:submit="updateProfile" class="space-y-5">

                    <div>
                        @php
                            $nextNameChange = $this->nextNameChange();
                        @endphp
                        <x-player-name-field
                            wire:model="name"
                            :value="$name"
                            :label="__('Player Name')"
                            :locked="$nextNameChange !== null"
                            :locked-message="__('You can change your name once a year. You can change it again on :date.', ['date' => $nextNameChange])" />
                        @error('name') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>

                     {{--
                     <div>
                        <flux:input wire:model="email" :label="__('Email Address')" type="email" required />
                        @error('email') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                    </div>
                    --}}

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-[#6b6b6b] mb-1.5" style="font-family: 'Outfit', sans-serif;">
                            Email Address
                        </label>
                        <div class="rounded-lg border border-yellow-800/20 bg-[#111111] px-4 py-3 font-mono text-sm text-[#f5c542]">
                            {{ $email }}
                        </div>
                    </div>

                    @if (empty(auth()->user()->phone))
                    <div>
                        <flux:input wire:model="phoneNo" :label="__('Phone Number')" type="tel" required
                                    placeholder="+254 7XX XXX XXX" />
                        @error('phoneNo') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-[#f5c542]/60" style="font-family: 'Outfit', sans-serif;">
                            Required to play games and make transactions.
                        </p>
                    </div>
                    @else
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-[#6b6b6b] mb-1.5" style="font-family: 'Outfit', sans-serif;">
                            Phone Number
                        </label>
                        <div class="flex items-center justify-between gap-3 rounded-lg border border-yellow-800/20 bg-[#111111] px-4 py-3">
                            <span class="font-mono text-sm text-[#f5c542]">{{ $phoneNo }}</span>
                            @if (auth()->user()->hasVerifiedPhone())
                                <span class="inline-flex items-center gap-1 text-xs text-green-400"><flux:icon.check-badge variant="micro" /> Verified</span>
                            @else
                                <button type="button" x-on:click="$dispatch('open-phone-required', { purpose: 'verify' })"
                                        class="text-xs font-semibold text-[#f5c542] hover:underline" data-test="profile-verify-phone">Verify</button>
                            @endif
                        </div>
                    </div>
                    @endif

                    {{-- Account No: read-only
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-widest text-[#6b6b6b] mb-1.5" style="font-family: 'Outfit', sans-serif;">
                            Account No
                        </label>
                        <div class="rounded-lg border border-yellow-800/20 bg-[#111111] px-4 py-3 font-mono text-sm text-[#f5c542]">
                            {{ $kadiCustomer['account_no'] ?? auth()->user()->account_no ?? '—' }}
                        </div>
                    </div>
                    --}}

                    {{-- Disabled until loadCustomer() has run, so nothing is saved over fields that have not loaded. --}}
                    <button
                        type="submit"
                        data-test="profile-save" @disabled($needsLoad)
                        class="btn-casino-primary inline-flex items-center gap-2 rounded-xl px-8 py-3 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-50 cursor-not-allowed"
                    >
                        <span wire:loading wire:target="updateProfile" class="animate-spin">⟳</span>
                        <span wire:loading.remove wire:target="updateProfile">Save Changes</span>
                        <span wire:loading wire:target="updateProfile">Saving...</span>
                    </button>

                </form>
            </div>

            {{-- Tab 3: Connected Accounts --}}
            <div x-show="tab === 'connected'" x-cloak class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-8">
                <h2 class="mb-2 text-lg font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Connected Accounts</h2>
                <p class="mb-6 text-sm text-[#6b6b6b]" style="font-family: 'Outfit', sans-serif;">Link your Google account for one-click sign-in.</p>
                <livewire:settings.link-google-account />
            </div>

            {{-- Tab 4: Notifications (browser push for this device) --}}
            <div x-show="tab === 'notifications'" x-cloak class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-8">
                <h2 class="mb-2 text-lg font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Notifications</h2>
                <p class="mb-6 text-sm text-[#6b6b6b]" style="font-family: 'Outfit', sans-serif;">Choose whether this device can send you alerts from Kadi.</p>
                <x-pwa.notification-toggle />
            </div>

            {{-- Tab 2: Security (password + 2FA + passkeys) --}}
            <div x-show="tab === 'security'" x-cloak class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-8">
                <h2 class="mb-6 text-lg font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">Security</h2>

                <section aria-label="{{ __('Password') }}">
                    @if ($isGoogleUser)
                        <div class="mb-4 rounded-lg border border-yellow-800/20 bg-[#f5c542]/5 p-4">
                            <p class="text-sm text-[#f5f5f0]">
                                <span class="font-semibold">You signed in with Google.</span>
                                Set a password below to enable 2FA, passkeys, and password-based sign-in.
                            </p>
                        </div>
                        <form wire:submit="setPassword" class="space-y-5">
                            <div>
                                <flux:input wire:model="newSetPassword" :label="__('New Password')" type="password" viewable />
                                @error('newSetPassword') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <flux:input wire:model="newSetPasswordConfirmation" :label="__('Confirm New Password')" type="password" viewable />
                            </div>
                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                            >
                                <span wire:loading wire:target="setPassword" class="animate-spin mr-2">⟳</span>
                                Set Password
                            </flux:button>
                        </form>
                    @else
                        <form wire:submit="updatePassword" class="space-y-5">
                            <div>
                                <flux:input wire:model="currentPassword" :label="__('Current Password')" type="password" viewable />
                                @error('currentPassword') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <flux:input wire:model="newPassword" :label="__('New Password')" type="password" viewable />
                                @error('newPassword') <p class="mt-1 text-xs text-red-400">{{ $message }}</p> @enderror
                            </div>
                            <div>
                                <flux:input wire:model="newPasswordConfirmation" :label="__('Confirm New Password')" type="password" viewable />
                            </div>
                            <flux:button
                                type="submit"
                                variant="primary"
                                wire:loading.attr="disabled"
                                wire:loading.class="opacity-50 cursor-not-allowed"
                            >
                                <span wire:loading wire:target="updatePassword" class="animate-spin mr-2">⟳</span>
                                Update Password
                            </flux:button>
                        </form>
                    @endif
                </section>

                <section class="mt-8 border-t border-yellow-800/20 pt-8" aria-label="{{ __('Two-Factor Authentication') }}">
                    <livewire:profile.security.two-factor-panel />
                </section>

                <section class="mt-8 border-t border-yellow-800/20 pt-8" aria-label="{{ __('Passkeys') }}">
                    <livewire:profile.security.passkey-manager />
                </section>
            </div>
        </div>

        {{-- Right column: Card 2 + Card 3 --}}
        <div class="space-y-6">

            {{-- CARD 2: Account Info --}}
            <div class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-6 text-center">
                {{-- Avatar --}}
                <div class="mx-auto mb-4 h-20 w-20">
                    <img src="{{ $resolvedAvatarUrl }}" alt="Profile picture"
                         class="h-20 w-20 rounded-full object-cover border-2 border-[#f5c542]/40" />
                </div>

                <h3 class="mb-0.5 font-bold text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">{{ auth()->user()->name }}</h3>
                <p class="mb-1 text-sm text-[#6b6b6b]">{{ auth()->user()->email }}</p>
                {{-- <p class="mb-4 font-mono text-xs text-[#f5c542]/70">{{ auth()->user()->account_no ?? '—' }}</p> --}}

                <div class="mb-4 border-t border-yellow-800/20 pt-4 text-left space-y-2">
                    <div class="flex justify-between text-sm">
                        <span class="text-[#6b6b6b]">Player No</span>
                        @if ($needsLoad)
                            <x-skeleton inline class="h-4 w-20 rounded" />
                        @else
                            <span class="font-mono text-[#f5c542]" data-test="profile-player-no">{{ \App\Support\PlayerNumber::format($kadiCustomer['player_no'] ?? null) ?? '—' }}</span>
                        @endif
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-[#6b6b6b]">Member since</span>
                        <span class="text-[#f5f5f0]">{{ auth()->user()->created_at->format('M Y') }}</span>
                    </div>
                </div>

                <div class="flex flex-wrap justify-center gap-2">
                    <span class="rounded-full bg-green-900/50 px-3 py-1 text-xs font-semibold text-green-400 border border-green-700">
                        ✓ Active
                    </span>
                    @if (auth()->user()->isLinked())
                        <span class="rounded-full bg-[#f5c542]/10 px-3 py-1 text-xs font-semibold text-[#f5c542] border border-[#f5c542]/30">
                            🔗 Kadi Linked
                        </span>
                    @else
                        <span class="rounded-full bg-red-900/20 px-3 py-1 text-xs font-semibold text-red-400 border border-red-700/40">
                            ⚠ Not Linked
                        </span>
                    @endif
                </div>
            </div>

            {{-- CARD 3: Stats --}}
            <div class="rounded-xl border border-yellow-800/30 bg-[#1a1a1a] p-6">
                <h3 class="mb-4 text-sm font-bold tracking-widest text-[#f5c542] uppercase" style="font-family: 'Cinzel', serif;">
                    Your Stats
                </h3>
                <div class="grid grid-cols-2 gap-3">
                    @php
                        $stats = [
                            ['label' => 'Total Deposits',     'value' => session('currency.code', 'KES').' '.number_format($kadiCustomer['deposits'] ?? 0, 2)],
                            ['label' => 'Total Withdrawals',  'value' => session('currency.code', 'KES').' '.number_format($kadiCustomer['withdraws'] ?? 0, 2)],
                            ['label' => 'Singles Played',     'value' => $kadiCustomer['single_played'] ?? 0],
                            ['label' => 'Competitions',       'value' => $kadiCustomer['competition_played'] ?? 0],
                        ];
                    @endphp
                    @foreach ($stats as $stat)
                        <div class="rounded-lg border-t-2 border-[#f5c542]/40 bg-[#111111] p-3 text-center">
                            @if ($needsLoad)
                                <x-skeleton.region :label="__('Loading…')" class="flex justify-center py-0.5">
                                    <x-skeleton class="h-5 w-16 rounded" data-test="profile-stat-skeleton" />
                                </x-skeleton.region>
                            @else
                                <div class="text-base font-black text-[#f5f5f0]" style="font-family: 'Cinzel', serif;">
                                    {{ $stat['value'] }}
                                </div>
                            @endif
                            <div class="mt-1 text-xs text-[#6b6b6b]" style="font-family: 'Outfit', sans-serif;">
                                {{ $stat['label'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

        </div>
    </div>
</div>
