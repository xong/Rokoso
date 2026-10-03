import { Controller } from '@hotwired/stimulus';

/*
 * Web-Push im Profil ein- und ausschalten. Das Abonnement des Browsers wird an den Server geschickt,
 * der Service Worker (sw.js) zeigt die Benachrichtigungen an.
 */
export default class extends Controller {
    static targets = ['enable', 'disable', 'status'];
    static values = {
        key: String, url: String, token: String,
        enabled: String, disabled: String, denied: String, unsupported: String, error: String,
    };

    async connect() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) {
            this.enableTarget.disabled = true;
            this.say(this.unsupportedValue);
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            this.toggle(null !== await registration.pushManager.getSubscription());
        } catch (e) {
            this.toggle(false);
        }
    }

    async enable() {
        try {
            if ('granted' !== await Notification.requestPermission()) {
                this.say(this.deniedValue);
                return;
            }
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.decode(this.keyValue),
            });
            await this.send('POST', subscription);
            this.toggle(true);
            this.disableTarget.focus();
            this.say(this.enabledValue);
        } catch (e) {
            this.say(this.errorValue);
        }
    }

    async disable() {
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            if (subscription) {
                await this.send('DELETE', subscription);
                await subscription.unsubscribe();
            }
            this.toggle(false);
            this.enableTarget.focus();
            this.say(this.disabledValue);
        } catch (e) {
            this.say(this.errorValue);
        }
    }

    async send(method, subscription) {
        const response = await fetch(this.urlValue, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify(subscription.toJSON()),
        });
        if (!response.ok) {
            throw new Error(String(response.status));
        }
    }

    toggle(subscribed) {
        this.enableTarget.hidden = subscribed;
        this.disableTarget.hidden = !subscribed;
    }

    say(text) {
        this.statusTarget.textContent = text;
    }

    decode(base64) {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    }
}
