import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { cookie } from 'index';
import { TOKEN_KEY } from 'authProvider';

// Laravel Echo 需要 Pusher 在 window 上
(window as any).Pusher = Pusher;

export function createEcho(): Echo {
  return new Echo({
    broadcaster: 'reverb',
    key: process.env.REACT_APP_REVERB_KEY,
    wsHost: process.env.REACT_APP_REVERB_HOST,
    wsPort: Number(process.env.REACT_APP_REVERB_PORT) || 9001,
    wssPort: Number(process.env.REACT_APP_REVERB_PORT) || 443,
    forceTLS: process.env.REACT_APP_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    // Broadcasting 認證端點 — 使用 auth:api (JWT) middleware 保護
    authEndpoint: `${process.env.REACT_APP_HOST}/api/v1/broadcasting/auth`,
    auth: {
      headers: {
        Authorization: `Bearer ${cookie.get(TOKEN_KEY)}`,
      },
    },
  });
}
