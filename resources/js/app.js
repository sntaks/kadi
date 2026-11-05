import './sentry.js';
import './echo.js';
import './games/play-frame.js';
import './ui/reveal.js';
import './site/analytics.js';
import './site/signin.js';
import './sports/betslip.js';
import './sports/match-panel.js';
import './sports/countdown.js';
import { initInstall } from './pwa/install.js';
import { initPush } from './pwa/push.js';
import { registerServiceWorker } from './pwa/register-sw.js';

registerServiceWorker();
initInstall();
initPush();
