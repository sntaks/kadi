{{--
    The sign-in sheet (plan §2 "Sign-in inside the site", Phase 6b): Log in, Join and Forgot password over
    the page the player is on, in the shared <x-ui.sheet> (a right drawer on desktop, a bottom sheet on
    phones). The forms post to Fortify's own routes (throttling and CSRF unchanged) with `return_to`, so the
    player lands back here.

    Opened by $dispatch('open-signin', { tab: 'login' | 'join', returnTo: '/wallet' }), by the top bar's Log in
    and Join, and by any [data-needs-account] link (resources/js/site/signin.js). After a failed submit the
    page comes back with the errors, and the sheet reopens on the form that was sent.
--}}
@php
    use App\Http\Middleware\RememberReturnPath;

    $sent = session('signin_form');
    $reopen = $sent !== null && ($errors->any() || session('status'));
    // This page, query included, so the player returns to exactly what they were looking at.
    $uri = request()->getRequestUri();
    $here = RememberReturnPath::isSafe($uri) ? $uri : '/';
    $returnTo = old('return_to', $here);
@endphp
<div x-data="{
        tab: @js($sent ?? 'login'),
        returnTo: @js($returnTo),
        titles: { login: 'Log in', join: 'Join Kadi', forgot: 'Reset your password' },
        show(detail) {
            this.tab = ['login', 'join', 'forgot'].includes(detail?.tab) ? detail.tab : 'login';
            this.returnTo = typeof detail?.returnTo === 'string' && detail.returnTo.startsWith('/') && ! detail.returnTo.startsWith('//') ? detail.returnTo : @js($here);
            $dispatch('open-sheet', 'signin');
        },
     }"
     x-on:open-signin.window="show($event.detail)"
     x-effect="$root.querySelectorAll('input[name=return_to]').forEach((input) => input.value = returnTo);
               $root.querySelectorAll('a[data-google]').forEach((a) => { const u = new URL(a.href); u.searchParams.set('return_to', returnTo); a.href = u.toString(); })">
    <x-ui.sheet name="signin" :open="$reopen">
        <x-slot:title><span x-text="titles[tab]">{{ ['login' => 'Log in', 'join' => 'Join Kadi', 'forgot' => 'Reset your password'][$sent ?? 'login'] }}</span></x-slot:title>

        <div class="mt-4 grid gap-5">
            <x-ui.tabs label="Log in or join" x-show="tab !== 'forgot'">
                <x-ui.tab :active="($sent ?? 'login') === 'login'" id="signin-tab-login" aria-controls="signin-panel-login"
                          x-bind:aria-selected="tab === 'login'" x-on:click="tab = 'login'">Log in</x-ui.tab>
                <x-ui.tab :active="$sent === 'join'" id="signin-tab-join" aria-controls="signin-panel-join"
                          x-bind:aria-selected="tab === 'join'" x-on:click="tab = 'join'">Join</x-ui.tab>
            </x-ui.tabs>

            <div id="signin-panel-login" role="tabpanel" aria-labelledby="signin-tab-login" x-show="tab === 'login'">
                <x-auth.login-form :return-to="$returnTo" sheet />
            </div>
            <div id="signin-panel-join" role="tabpanel" aria-labelledby="signin-tab-join" x-show="tab === 'join'" x-cloak>
                <x-auth.join-form :return-to="$returnTo" sheet />
            </div>
            <div x-show="tab === 'forgot'" x-cloak><x-auth.forgot-form sheet /></div>

            <p class="nt-hint m-0 text-center">18+ only. Play responsibly.</p>
        </div>
    </x-ui.sheet>
</div>
