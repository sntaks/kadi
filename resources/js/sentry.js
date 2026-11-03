import * as Sentry from '@sentry/browser';
import privateLists from './sentry-private.json';

// Browser error monitoring + page-load/navigation tracing. Imported first in app.js so it
// catches errors thrown while the rest of the bundle starts up. No DSN = disabled.
const dsn = import.meta.env.VITE_SENTRY_DSN;

// Shared with the backend (app/Support/SentryScrubber.php): one list for both sides.
const PRIVATE_QUERY_PARAMS = privateLists.queryParams;
const SECRET_PATHS = privateLists.secretPaths;
const FILTERED = '[Filtered]';

const escapeRegExp = (value) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
const SECRET_PATH_PATTERNS = SECRET_PATHS.map(
    (prefix) => new RegExp(`(${escapeRegExp(prefix)})[^\\s?#"']+`, 'g'),
);

// Masks private query values and everything after a secret path prefix (password-reset tokens,
// e-mail verification hashes). Works on full URLs, paths and transaction names.
function scrubUrl(url) {
    if (typeof url !== 'string') return url;

    let result = url;
    for (const pattern of SECRET_PATH_PATTERNS) result = result.replace(pattern, `$1${FILTERED}`);

    if (!result.includes('?')) return result;

    try {
        const parsed = new URL(result, window.location.origin);
        for (const key of [...parsed.searchParams.keys()]) {
            if (PRIVATE_QUERY_PARAMS.some((term) => key.toLowerCase().includes(term))) {
                parsed.searchParams.set(key, FILTERED);
            }
        }

        return result.startsWith('/') ? parsed.pathname + parsed.search + parsed.hash : parsed.toString();
    } catch {
        return result;
    }
}

function scrubEvent(event) {
    if (event.request?.url) event.request.url = scrubUrl(event.request.url);
    if (event.transaction) event.transaction = scrubUrl(event.transaction);

    for (const span of event.spans ?? []) {
        if (span.description) span.description = scrubUrl(span.description);
        for (const key of ['url', 'http.url', 'http.query']) {
            if (span.data?.[key]) span.data[key] = scrubUrl(span.data[key]);
        }
    }

    return event;
}

// SDK v11 streams spans by default (traceLifecycle 'stream'), and then beforeSendTransaction never
// runs: every span goes through beforeSendSpan instead. Scrub its name and any string attribute
// (url.full, http.url, page paths, raw or { value } form).
function scrubSpan(span) {
    if (span.name) span.name = scrubUrl(span.name);

    for (const [key, attr] of Object.entries(span.attributes ?? {})) {
        if (typeof attr === 'string') span.attributes[key] = scrubUrl(attr);
        else if (attr && typeof attr.value === 'string') attr.value = scrubUrl(attr.value);
    }

    return span;
}

// An empty env value ("VITE_SENTRY_TRACES_SAMPLE_RATE=") means "use the default", not 0.
function sampleRate(value, fallback) {
    const rate = Number.parseFloat(value);

    return Number.isFinite(rate) ? rate : fallback;
}

if (dsn) {
    Sentry.init({
        dsn,
        environment: import.meta.env.VITE_SENTRY_ENVIRONMENT || import.meta.env.MODE,
        release: import.meta.env.VITE_SENTRY_RELEASE || undefined,

        // Same rules as the backend (config/sentry.php): no user info, cookies or bodies, and
        // `?ref=` / `?promo=` codes, e-mails and phone numbers never leave the browser.
        dataCollection: {
            userInfo: false,
            cookies: false,
            httpBodies: [],
            urlQueryParams: { deny: PRIVATE_QUERY_PARAMS },
        },

        // `dataCollection` filters query strings only; path tokens and navigation breadcrumbs
        // (wire:navigate uses the History API) are scrubbed here.
        beforeBreadcrumb(breadcrumb) {
            if (breadcrumb.data) {
                for (const key of ['from', 'to', 'url']) {
                    if (key in breadcrumb.data) breadcrumb.data[key] = scrubUrl(breadcrumb.data[key]);
                }
            }

            return breadcrumb;
        },
        beforeSend: scrubEvent,
        beforeSendSpan: scrubSpan,

        integrations: [Sentry.browserTracingIntegration()],
        tracesSampleRate: sampleRate(import.meta.env.VITE_SENTRY_TRACES_SAMPLE_RATE, 0.2),

        // Errors from browser extensions are not ours.
        denyUrls: [/^chrome-extension:\/\//, /^moz-extension:\/\//, /^safari-web-extension:\/\//],
    });
}
