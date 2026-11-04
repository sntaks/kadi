// The sign-in sheet (resources/views/components/site/signin-sheet.blade.php) opens over the page instead of
// leaving it: for the top bar's Log in / Join ([data-signin]) and for any link a guest can't open yet
// ([data-needs-account]: Wallet, Activity, Play Kadi…). Without JavaScript, or on pages without the sheet,
// those links still go to the real sign-in pages, which redirect back afterwards.
const SIGN_IN_PATHS = ['/login', '/register'];

document.addEventListener('click', (event) => {
    const link = event.target.closest?.('a[data-signin], a[data-needs-account]');

    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!document.querySelector('[data-sheet="signin"]')) return;

    const target = new URL(link.href, window.location.origin);
    if (target.origin !== window.location.origin) return;

    event.preventDefault();

    // A link to the sign-in page itself returns to this page; any other link returns to where it pointed.
    const returnTo = SIGN_IN_PATHS.includes(target.pathname)
        ? window.location.pathname + window.location.search
        : target.pathname + target.search;
    const tab = link.dataset.signin === 'join' || target.pathname === '/register' ? 'join' : 'login';

    window.dispatchEvent(new CustomEvent('open-signin', { detail: { tab, returnTo } }));
});
