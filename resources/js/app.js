import './sentry.js';
import './echo.js';
import './alpine/bottom-nav.js';
import './alpine/legal-doc.js';
import './games/play-frame.js';
import './ui/reveal.js';
import './site/signin.js';
import { initInstall } from './pwa/install.js';
import { initPush } from './pwa/push.js';
import { registerServiceWorker } from './pwa/register-sw.js';

registerServiceWorker();
initInstall();
initPush();
