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

window.downloadImage = async (url, trigger) => {
    if (trigger.hasAttribute('data-flux-loading')) return;

    const idleIcon = trigger.querySelector('[data-download-idle]');
    const loadingIcon = trigger.querySelector('[data-download-loading]');
    trigger.setAttribute('data-flux-loading', '');
    trigger.setAttribute('aria-disabled', 'true');
    trigger.classList.add('pointer-events-none', 'opacity-75');
    idleIcon?.classList.add('hidden');
    loadingIcon?.classList.remove('hidden');

    try {
        const response = await fetch(url);

        if (! response.ok) throw new Error(response.statusText);

        const disposition = response.headers.get('content-disposition') ?? '';
        const fileName = disposition.match(/filename="?([^";]+)"?/i)?.[1] ?? 'GenAnh.com-image.jpg';
        const objectUrl = URL.createObjectURL(await response.blob());
        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = fileName;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(objectUrl);
    } catch {
        window.Flux?.toast({ text: trigger.dataset.downloadError, variant: 'danger' });
    } finally {
        trigger.removeAttribute('data-flux-loading');
        trigger.removeAttribute('aria-disabled');
        trigger.classList.remove('pointer-events-none', 'opacity-75');
        idleIcon?.classList.remove('hidden');
        loadingIcon?.classList.add('hidden');
    }
};

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
