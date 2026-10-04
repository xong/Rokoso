import { Controller } from '@hotwired/stimulus';

/*
 * Speichert das Formular nach kurzer Pause als Entwurf (ohne neue Dateien).
 * Vor dem Absenden wird ein laufendes Speichern abgewartet, damit kein doppelter Entwurf entsteht.
 */
export default class extends Controller {
    static targets = ['status', 'draft'];
    static values = { saved: String, failed: String, delay: { type: Number, default: 2000 } };

    connect() {
        this.timer = null;
        this.pending = null;
        this.dirty = false;
        this.submitting = false;
    }

    disconnect() {
        clearTimeout(this.timer);
    }

    changed(event) {
        if (event.target.type === 'file' || this.submitting) {
            return;
        }
        this.dirty = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), this.delayValue);
    }

    async save() {
        clearTimeout(this.timer);
        if (this.pending) {
            await this.pending;
        }
        if (!this.dirty || this.submitting) {
            return;
        }
        this.dirty = false;
        this.pending = this.send().finally(() => {
            this.pending = null;
        });
        await this.pending;
    }

    async send() {
        const data = new FormData(this.element);
        for (const [name, value] of [...data.entries()]) {
            if (value instanceof File) {
                data.delete(name);
            }
        }
        try {
            const response = await fetch(this.element.action || window.location.href, {
                method: 'POST',
                body: data,
                headers: { 'X-Autosave': '1', Accept: 'application/json' },
            });
            if (!response.ok) {
                throw new Error(String(response.status));
            }
            const { id } = await response.json();
            this.draftTarget.value = id;
            const url = new URL(window.location.href);
            url.search = `?draft=${id}`;
            window.history.replaceState(window.history.state, '', url);
            const time = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            this.statusTarget.textContent = this.savedValue.replace('%time%', time);
        } catch {
            this.statusTarget.textContent = this.failedValue;
        }
    }

    async submit(event) {
        if (this.submitting) {
            return;
        }
        clearTimeout(this.timer);
        if (!this.pending) {
            this.submitting = true;
            return;
        }
        // erst den laufenden Entwurf abwarten (er vergibt die Entwurfs-ID), dann erneut absenden
        event.preventDefault();
        event.stopImmediatePropagation();
        const submitter = event.submitter;
        await this.pending;
        this.submitting = true;
        this.element.requestSubmit(submitter);
    }
}
