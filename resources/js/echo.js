/** Initialisation Laravel Echo + Reverb pour le cockpit live (P3-7 prod). */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

function initReverbEcho(config) {
    if (!config?.enabled || !config.key) {
        return null;
    }

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: config.authEndpoint,
        auth: {
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
        },
    });

    return window.Echo;
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.__reverbConfig) {
        initReverbEcho(window.__reverbConfig);
    }
});

export { initReverbEcho };
