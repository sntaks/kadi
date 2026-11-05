{{-- /login in the new look (Phase 6b): the sign-in sheet's form, in a panel. For e-mail links, password managers and no-JS. --}}
<x-auth.page :title="__('Log in')">
    <div class="grid gap-6">
        <x-auth.head title="Log in" description="Welcome back. Your table is waiting." />
        <x-auth.login-form />
    </div>
</x-auth.page>
