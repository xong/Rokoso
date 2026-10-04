import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Fragt die Ungelesen-Zähler regelmäßig ab (nur bei sichtbarem Tab), aktualisiert die
 * Badges [data-live-count] und den Seitentitel und zeigt bei neuen Nachrichten einen Hinweis.
 */
export default class extends Controller {
    static targets = ['notice'];
    static values = { url: String, interval: { type: Number, default: 60000 } };

    connect() {
        this.inbox = this.read('inbox');
        this.baseTitle = document.title.replace(/^\(\d+\+?\) /, '');
        this.updateTitle(this.inbox);
        this.timer = setInterval(() => this.poll(), this.intervalValue);
        this.onVisible = () => document.visibilityState === 'visible' && this.poll();
        document.addEventListener('visibilitychange', this.onVisible);
    }

    disconnect() {
        clearInterval(this.timer);
        document.removeEventListener('visibilitychange', this.onVisible);
    }

    async poll() {
        if (document.visibilityState !== 'visible') {
            return;
        }
        let counts;
        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!response.ok) {
                return;
            }
            counts = await response.json();
        } catch {
            return; // offline – beim nächsten Mal wieder
        }
        Object.entries(counts).forEach(([key, value]) => this.write(key, value));
        if (counts.inbox > this.inbox) {
            this.noticeTarget.hidden = false;
        }
        this.inbox = counts.inbox;
        this.updateTitle(counts.inbox);
    }

    reload() {
        this.noticeTarget.hidden = true;
        Turbo.visit(window.location.href, { action: 'replace' });
    }

    dismiss() {
        this.noticeTarget.hidden = true;
    }

    read(key) {
        const badge = document.querySelector(`[data-live-count="${key}"]`);
        if (!badge || badge.hidden) {
            return 0;
        }
        return parseInt(badge.querySelector('[data-live-number]')?.textContent ?? '0', 10) || 0;
    }

    write(key, value) {
        document.querySelectorAll(`[data-live-count="${key}"]`).forEach((badge) => {
            badge.hidden = value <= 0;
            const number = badge.querySelector('[data-live-number]');
            if (number) {
                number.textContent = value > 99 ? '99+' : String(value);
            }
        });
    }

    updateTitle(count) {
        document.title = (count > 0 ? `(${count > 99 ? '99+' : count}) ` : '') + this.baseTitle;
    }
}
