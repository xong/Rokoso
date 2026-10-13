import { Controller } from '@hotwired/stimulus';

/*
 * Web-Push im Profil ein- und ausschalten. Das Abonnement des Browsers wird an den Server geschickt,
 * der Service Worker (sw.js) zeigt die Benachrichtigungen an.
 */
export default class extends Controller {
    static targets = ['enable', 'disable', 'status', 'test'];
    static values = {
        key: String, url: String, token: String,
        enabled: String, disabled: String, denied: String, unsupported: String, error: String,
        devices: Boolean,
    };

    async connect() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window) || !window.isSecureContext) {
            this.enableTarget.disabled = true;
            this.say(this.unsupportedValue);
            return;
        }
        try {
            const registration = await navigator.serviceWorker.ready;
            const subscription = await registration.pushManager.getSubscription();
            this.toggle(null !== subscription);
            if (subscription && 'granted' === Notification.permission) {
                await this.refresh(registration, subscription);
            }
        } catch (e) {
            this.toggle(false);
        }
    }

    /*
     * Gleicht das Abo dieses Browsers mit dem Server ab: Gehört es zu einem anderen Schlüssel (neue Schlüssel,
     * andere Instanz), wird es erneuert; außerdem kennt der Server es danach sicher (z. B. nach Ablauf gelöscht).
     */
    async refresh(registration, subscription) {
        const key = this.decode(this.keyValue);
        const current = subscription.options?.applicationServerKey;
        if (current && !this.same(new Uint8Array(current), key)) {
            await this.drop(subscription);
            subscription =await registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
        }
        await this.send('POST', subscription);
        this.devicesValue = true;
        this.toggle(true);
    }

    // Altes Abo beim Server und im Browser entfernen; scheitert der Server, stört das nicht weiter.
    async drop(subscription) {
        await this.send('DELETE', subscription).catch(() => {});
        await subscription.unsubscribe();
    }

    same(a, b) {
        return a.length === b.length && a.every((value, i) => value === b[i]);
    }

    async enable() {
        try {
            if ('granted' !== await Notification.requestPermission()) {
                this.say(this.deniedValue);
                return;
            }
            const registration = await navigator.serviceWorker.ready;
            // a leftover subscription with another key would make subscribe() fail
            const old = await registration.pushManager.getSubscription();
            if (old) {
                await this.drop(old);
            }
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
        if (this.hasTestTarget) {
            // other devices of the person can be tested from here as well
            this.testTarget.hidden = !subscribed && !this.devicesValue;
        }
    }

    say(text) {
        this.statusTarget.textContent = text;
    }

    decode(base64) {
        const padded = (base64 + '='.repeat((4 - (base64.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        return Uint8Array.from(atob(padded), (c) => c.charCodeAt(0));
    }
}
