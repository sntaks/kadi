{{--
    Push notification switch for this device, driven by the `pwaPush` Alpine store
    (resources/js/pwa/push.js). It never asks for permission by itself: only clicking the
    switch does, which is what browsers (and iOS in particular) require.

    States: checking | unsupported | ios-needs-install | default | granted-unsubscribed |
            granted-subscribed | denied

    `wire:ignore` keeps Livewire re-renders of the surrounding page from resetting the
    display styles Alpine manages here.
--}}
<div wire:ignore data-test="pwa-notifications" class="space-y-4">

    {{-- Working out what this device can do --}}
    <p x-show="$store.pwaPush.state === 'checking'" class="text-sm text-ash" role="status">
        Checking this device&hellip;
    </p>

    {{-- Not possible here --}}
    <div x-show="$store.pwaPush.state === 'unsupported'" x-cloak style="display: none;" data-test="pwa-notifications-unsupported"
         class="rounded-lg border border-line bg-gold/5 p-4 text-sm text-ivory/80">
        <span x-text="$store.pwaPush.unsupportedMessage"></span>
    </div>

    {{-- iPhone / iPad in Safari: push only exists once installed to the Home Screen --}}
    <div x-show="$store.pwaPush.state === 'ios-needs-install'" x-cloak style="display: none;" data-test="pwa-notifications-ios"
         class="space-y-3 rounded-lg border border-line bg-gold/5 p-4">
        <p class="text-sm text-ivory/80">
            To get notifications on iPhone or iPad, first add Kadi to your Home Screen, then open it from there.
        </p>
        <button type="button" @click="$store.pwaInstall.activate()" aria-haspopup="dialog"
                class="nt-btn nt-btn-ghost nt-btn-sm">
            Show me how
        </button>
    </div>

    {{-- The switch --}}
    <div x-show="['default', 'granted-unsubscribed', 'granted-subscribed', 'denied'].includes($store.pwaPush.state)" x-cloak style="display: none;"
         class="flex items-center justify-between gap-4">
        <div class="min-w-0">
            <p id="pwa-notifications-label" class="text-sm font-semibold text-ivory">Notifications on this device</p>
            <p id="pwa-notifications-desc" class="mt-0.5 text-xs text-ash">
                <span x-show="$store.pwaPush.state === 'granted-subscribed'">On. Kadi can send you alerts here, even when the app is closed.</span>
                <span x-show="$store.pwaPush.state === 'denied'">Blocked in your browser settings.</span>
                <span x-show="$store.pwaPush.state === 'default' || $store.pwaPush.state === 'granted-unsubscribed'">Off. Turn on to get alerts from Kadi on this device.</span>
            </p>
        </div>

        <button
            type="button"
            role="switch"
            data-test="pwa-notifications-switch"
            aria-labelledby="pwa-notifications-label"
            aria-describedby="pwa-notifications-desc"
            :aria-checked="$store.pwaPush.isOn ? 'true' : 'false'"
            :aria-busy="$store.pwaPush.busy ? 'true' : 'false'"
            :disabled="!$store.pwaPush.canToggle"
            @click="$store.pwaPush.toggle()"
            :class="$store.pwaPush.isOn ? 'bg-gold' : 'bg-felt-4'"
            class="relative inline-flex h-7 w-12 shrink-0 items-center rounded-full transition-colors focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-gold disabled:cursor-not-allowed disabled:opacity-50"
        >
            <span
                :class="$store.pwaPush.isOn ? 'translate-x-6' : 'translate-x-1'"
                class="inline-block h-5 w-5 rounded-full bg-white shadow transition-transform"
                aria-hidden="true"
            ></span>
        </button>
    </div>

    {{-- Send a real push to this account's devices. Local/staging or admins only; not rendered for anyone else. --}}
    @if (auth()->check() && app(\App\Services\PushTestSender::class)->allowedFor(auth()->user()))
        <div x-show="$store.pwaPush.state === 'granted-subscribed'" x-cloak style="display: none;" data-test="pwa-notifications-test"
             class="space-y-2 border-t border-line pt-4">
            <div class="flex flex-wrap items-center gap-3">
                <button type="button" data-test="pwa-notifications-test-button"
                        @click="$store.pwaPush.sendTest()"
                        :disabled="$store.pwaPush.testing"
                        :aria-busy="$store.pwaPush.testing ? 'true' : 'false'"
                        class="nt-btn nt-btn-ghost nt-btn-sm disabled:cursor-not-allowed disabled:opacity-50">
                    <span x-show="!$store.pwaPush.testing">Send a test notification</span>
                    <span x-show="$store.pwaPush.testing">Sending&hellip;</span>
                </button>
                <span class="text-xs text-ash">Shown in local, staging and to admins only.</span>
            </div>
            <p role="status" aria-live="polite" data-test="pwa-notifications-test-result"
               x-show="$store.pwaPush.testResult" x-cloak style="display: none;"
               :class="$store.pwaPush.testResult?.ok ? 'text-win' : 'text-red-300'"
               class="text-sm" x-text="$store.pwaPush.testResult?.message"></p>
        </div>
    @endif

    {{-- Blocked: how to undo it, per platform (browsers never re-prompt after a block) --}}
    <div x-show="$store.pwaPush.state === 'denied'" x-cloak style="display: none;" data-test="pwa-notifications-denied"
         class="rounded-lg border border-red-900/40 bg-red-900/10 p-4 text-sm text-ivory/80">
        <p class="font-semibold text-red-300">Notifications are blocked for Kadi.</p>
        <p class="mt-1" x-text="$store.pwaPush.deniedHelp"></p>
        <p class="mt-1 text-xs text-ash">Come back to this page afterwards and switch notifications on.</p>
    </div>

    {{-- Something went wrong (announced to screen readers) --}}
    <div x-show="$store.pwaPush.error" x-cloak style="display: none;" role="alert" data-test="pwa-notifications-error"
         class="rounded-lg border border-red-700 bg-red-900/30 p-3 text-sm text-red-300">
        {{-- Bindings inside a hidden block are still evaluated, so every read of `error` is null-safe. --}}
        <span x-text="$store.pwaPush.error?.message"></span>
        <a x-show="$store.pwaPush.error?.href" :href="$store.pwaPush.error?.href" class="ml-1 underline"
           x-text="$store.pwaPush.error?.linkText"></a>
    </div>
</div>
