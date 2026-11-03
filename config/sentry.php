<?php

use App\Support\GitRevision;
use App\Support\SentryScrubber;

/**
 * Sentry Laravel SDK configuration file.
 *
 * @see https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/
 */
return [

    // @see https://docs.sentry.io/concepts/key-terms/dsn-explainer/
    'dsn' => env('SENTRY_LARAVEL_DSN', env('SENTRY_DSN')),

    // @see https://spotlightjs.com/
    // 'spotlight' => env('SENTRY_SPOTLIGHT', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#logger
    // 'logger' => Sentry\Logger\DebugFileLogger::class, // By default this will log to `storage_path('logs/sentry.log')`

    // The release version of your application. Defaults to the deployed commit, read from .git
    // without starting a process; the deploy runs `artisan optimize` after `git pull`, so the cached
    // config carries the new SHA.
    'release' => env('SENTRY_RELEASE') ?: GitRevision::head(),

    // When left empty or `null` the Laravel environment will be used (usually discovered from `APP_ENV` in your `.env`)
    'environment' => env('SENTRY_ENVIRONMENT'),

    // Override the organization ID used for trace continuation checks.
    'org_id' => env('SENTRY_ORG_ID') === null ? null : (int) env('SENTRY_ORG_ID'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#sample_rate
    'sample_rate' => env('SENTRY_SAMPLE_RATE') === null ? 1.0 : (float) env('SENTRY_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#traces_sample_rate
    // Low by default: every open wallet tab polls Livewire every 30s (every 5s during a deposit).
    'traces_sample_rate' => in_array(env('SENTRY_TRACES_SAMPLE_RATE'), [null, ''], true) ? 0.05 : (float) env('SENTRY_TRACES_SAMPLE_RATE'),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#profiles_sample_rate
    'profiles_sample_rate' => env('SENTRY_PROFILES_SAMPLE_RATE') === null ? null : (float) env('SENTRY_PROFILES_SAMPLE_RATE'),

    // Only continue incoming traces when the organization IDs are compatible with this SDK instance.
    'strict_trace_continuation' => env('SENTRY_STRICT_TRACE_CONTINUATION', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable_logs
    'enable_logs' => env('SENTRY_ENABLE_LOGS', false),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#enable_metrics
    'enable_metrics' => env('SENTRY_ENABLE_METRICS', true),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#log_flush_threshold
    'log_flush_threshold' => env('SENTRY_LOG_FLUSH_THRESHOLD') === null ? null : (int) env('SENTRY_LOG_FLUSH_THRESHOLD'),

    // The minimum log level that will be sent to Sentry as logs using the `sentry_logs` logging channel
    'logs_channel_level' => env('SENTRY_LOG_LEVEL', env('SENTRY_LOGS_LEVEL', env('LOG_LEVEL', 'debug'))),

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#send_default_pii
    'send_default_pii' => env('SENTRY_SEND_DEFAULT_PII', false),

    // Configures which data is collected automatically. Set explicitly (not `null`) so the query
    // string is filtered: `?ref=` / `?promo=` codes, e-mails and phone numbers must never reach
    // Sentry (see AGENTS.md). Built-in sensitive terms (token, password, signature...) still apply.
    'data_collection' => [
        'user_info' => false,
        // Request bodies (Livewire payloads, forms) hold phone numbers, OTP codes and passwords.
        'cookies' => ['mode' => 'off'],
        'http_bodies' => [],
        // Shared with the browser SDK through resources/js/sentry-private.json.
        'url_query_params' => ['mode' => 'denyList', 'terms' => SentryScrubber::queryParams()],
        'database_query_data' => false,
    ],

    // Masks private query values and path tokens (password reset, e-mail verification) in URLs,
    // transaction names, spans and breadcrumbs. Array callables so `config:cache` keeps working.
    'before_send' => [SentryScrubber::class, 'beforeSend'],
    'before_send_transaction' => [SentryScrubber::class, 'beforeSendTransaction'],
    'before_breadcrumb' => [SentryScrubber::class, 'beforeBreadcrumb'],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_exceptions
    // 'ignore_exceptions' => [],

    // @see: https://docs.sentry.io/platforms/php/guides/laravel/configuration/options/#ignore_transactions
    'ignore_transactions' => [
        // Ignore Laravel's default health URL
        '/up',
    ],

    // Breadcrumb specific configuration
    'breadcrumbs' => [
        // Capture Laravel logs as breadcrumbs
        'logs' => env('SENTRY_BREADCRUMBS_LOGS_ENABLED', true),

        // Off: cache keys hold phone numbers and IPs (PhoneOtp rate limits, see AGENTS.md).
        'cache' => env('SENTRY_BREADCRUMBS_CACHE_ENABLED', false),

        // Off: these breadcrumbs carry every public component property and call argument, which
        // includes phone numbers, verification codes, e-mails, promo codes and passwords.
        'livewire' => env('SENTRY_BREADCRUMBS_LIVEWIRE_ENABLED', false),

        // Capture SQL queries as breadcrumbs
        'sql_queries' => env('SENTRY_BREADCRUMBS_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query breadcrumbs, ignored when `data_collection` is set
        // This option will be migrated to `data_collection.database_query_data` in the next major version
        'sql_bindings' => env('SENTRY_BREADCRUMBS_SQL_BINDINGS_ENABLED', false),

        // Capture queue job information as breadcrumbs
        'queue_info' => env('SENTRY_BREADCRUMBS_QUEUE_INFO_ENABLED', true),

        // Capture command information as breadcrumbs
        'command_info' => env('SENTRY_BREADCRUMBS_COMMAND_JOBS_ENABLED', true),

        // Capture HTTP client request information as breadcrumbs
        'http_client_requests' => env('SENTRY_BREADCRUMBS_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture send notifications as breadcrumbs
        'notifications' => env('SENTRY_BREADCRUMBS_NOTIFICATIONS_ENABLED', true),
    ],

    // Performance monitoring specific configuration
    'tracing' => [
        // Trace queue jobs as their own transactions (this enables tracing for queue jobs)
        'queue_job_transactions' => env('SENTRY_TRACE_QUEUE_ENABLED', true),

        // Capture queue jobs as spans when executed on the sync driver
        'queue_jobs' => env('SENTRY_TRACE_QUEUE_JOBS_ENABLED', true),

        // Capture SQL queries as spans
        'sql_queries' => env('SENTRY_TRACE_SQL_QUERIES_ENABLED', true),

        // Capture SQL query bindings (parameters) in SQL query spans, ignored when `data_collection` is set
        // This option will be migrated to `data_collection.database_query_data` in the next major version
        'sql_bindings' => env('SENTRY_TRACE_SQL_BINDINGS_ENABLED', false),

        // Capture where the SQL query originated from on the SQL query spans
        'sql_origin' => env('SENTRY_TRACE_SQL_ORIGIN_ENABLED', true),

        // Define a threshold in milliseconds for SQL queries to resolve their origin
        'sql_origin_threshold_ms' => env('SENTRY_TRACE_SQL_ORIGIN_THRESHOLD_MS', 100),

        // Capture views rendered as spans
        'views' => env('SENTRY_TRACE_VIEWS_ENABLED', true),

        // Capture Livewire components as spans
        'livewire' => env('SENTRY_TRACE_LIVEWIRE_ENABLED', true),

        // Capture HTTP client requests as spans
        'http_client_requests' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ENABLED', true),

        // Capture where the HTTP client request originated from on the HTTP client request spans
        'http_client_requests_origin' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ORIGIN_ENABLED', true),

        // Define a threshold in milliseconds for HTTP client requests to resolve their origin
        'http_client_requests_origin_threshold_ms' => env('SENTRY_TRACE_HTTP_CLIENT_REQUESTS_ORIGIN_THRESHOLD_MS', 250),

        // Off: span descriptions are cache keys, which hold phone numbers and IPs.
        'cache' => env('SENTRY_TRACE_CACHE_ENABLED', false),

        // Capture Redis operations as spans (this enables Redis events in Laravel)
        'redis_commands' => env('SENTRY_TRACE_REDIS_COMMANDS', false),

        // Capture where the Redis command originated from on the Redis command spans
        'redis_origin' => env('SENTRY_TRACE_REDIS_ORIGIN_ENABLED', true),

        // Capture send notifications as spans
        'notifications' => env('SENTRY_TRACE_NOTIFICATIONS_ENABLED', true),

        // Enable tracing for requests without a matching route (404's)
        'missing_routes' => env('SENTRY_TRACE_MISSING_ROUTES_ENABLED', false),

        // Configures if the performance trace should continue after the response has been sent to the user until the application terminates
        // This is required to capture any spans that are created after the response has been sent like queue jobs dispatched using `dispatch(...)->afterResponse()` for example
        'continue_after_response' => env('SENTRY_TRACE_CONTINUE_AFTER_RESPONSE', true),

        // Capture AI agent interactions as spans (requires laravel/ai)
        'gen_ai' => env('SENTRY_TRACE_GEN_AI_ENABLED', true),

        // Capture AI invoke_agent spans
        'gen_ai_invoke_agent' => env('SENTRY_TRACE_GEN_AI_INVOKE_AGENT_ENABLED', true),

        // Capture AI chat spans
        'gen_ai_chat' => env('SENTRY_TRACE_GEN_AI_CHAT_ENABLED', true),

        // Capture AI execute_tool spans
        'gen_ai_execute_tool' => env('SENTRY_TRACE_GEN_AI_EXECUTE_TOOL_ENABLED', true),

        // Capture AI embeddings spans
        'gen_ai_embeddings' => env('SENTRY_TRACE_GEN_AI_EMBEDDINGS_ENABLED', true),

        // Capture AI evaluate spans for classifications (requires laravel/ai 1.0+)
        'gen_ai_evaluate' => env('SENTRY_TRACE_GEN_AI_EVALUATE_ENABLED', true),

        // Enable the tracing integrations supplied by Sentry (recommended)
        'default_integrations' => env('SENTRY_TRACE_DEFAULT_INTEGRATIONS_ENABLED', true),
    ],

];
