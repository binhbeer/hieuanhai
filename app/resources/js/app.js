import Echo from 'laravel-echo';
import { Lightbox } from 'lightbox3';
import Pusher from 'pusher-js';

window.Pusher = Pusher;
window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

Lightbox.init();

window.accountForm = () => ({
    submitting: false,
    errors: {},
    status: '',

    async submit(event) {
        this.submitting = true;
        this.errors = {};
        this.status = '';

        try {
            const response = await fetch(event.currentTarget.action, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: new FormData(event.currentTarget),
            });
            const content = response.status === 204 ? '' : await response.text();
            const data = content ? JSON.parse(content) : {};

            if (response.status === 422 || response.status === 429) {
                this.errors = data.errors ?? { form: [data.message] };
                this.$nextTick(() => event.currentTarget.querySelector(`[name="${Object.keys(this.errors)[0]}"]`)?.focus());
                return;
            }

            if (! response.ok) {
                throw new Error(data.message ?? response.statusText);
            }

            if (data.two_factor) {
                window.dispatchEvent(new CustomEvent('open-account-modal', { detail: { component: 'auth.two-factor-challenge' } }));
                return;
            }

            if (data.message) {
                this.status = data.message;
                return;
            }

            window.location.assign('/');
        } catch (error) {
            this.errors = { form: [error.message] };
        } finally {
            this.submitting = false;
        }
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js', { scope: '/' }).then((registration) => registration.update()).catch(() => {});
    });
}
