import { execSync } from 'node:child_process';
import {
    defineConfig,
    loadEnv
} from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from "@tailwindcss/vite";
import { sentryVitePlugin } from '@sentry/vite-plugin';

// Same release as the backend (config/sentry.php): the deployed git commit.
function gitCommit() {
    try {
        return execSync('git rev-parse HEAD', { stdio: ['ignore', 'pipe', 'ignore'] }).toString().trim();
    } catch {
        return '';
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const release = env.SENTRY_RELEASE || gitCommit();
    process.env.VITE_SENTRY_RELEASE ??= release;

    // Source maps are only built and uploaded when an auth token is configured, then deleted
    // so they are never served from public/build.
    const uploadSourceMaps = Boolean(env.SENTRY_AUTH_TOKEN);

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.js',
                    'resources/js/passkeys.js',
                ],
                refresh: true,
            }),
            tailwindcss(),
            // Must stay last.
            uploadSourceMaps && sentryVitePlugin({
                org: env.SENTRY_ORG || 'sntaks-solutions',
                project: env.SENTRY_PROJECT || 'kadi',
                url: 'https://de.sentry.io/',
                authToken: env.SENTRY_AUTH_TOKEN,
                release: { name: release || undefined },
                sourcemaps: { filesToDeleteAfterUpload: ['public/build/**/*.map'] },
                telemetry: false,
            }),
        ],
        build: {
            cssCodeSplit: true,
            assetsInlineLimit: 4096,
            sourcemap: uploadSourceMaps ? 'hidden' : false,
        },
        server: {
            cors: true,
            watch: {
                ignored: [
                    '**/.agents/**',
                    '**/.claude/**',
                    '**/.cursor/**',
                    '**/.junie/**',
                    '**/storage/framework/views/**',
                    '**/vendor/**',
                ],
            },
        },
    };
});
