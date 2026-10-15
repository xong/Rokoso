import { Controller } from '@hotwired/stimulus';
import { generateCsrfHeaders, generateCsrfToken, removeCsrfToken } from './csrf_protection_controller.js';
import { formText } from '../form_texts.js';

/*
 * Speichert ein Bearbeiten-Formular kurz nach der letzten Eingabe im Hintergrund (Header X-Autosave, siehe
 * AutosaveListener): 204 = gespeichert, 422 = Eingaben unvollständig, sonst fehlgeschlagen. Neue Dateien gehen erst
 * mit „Speichern“ mit. Wer die Seite verlässt, wartet auf das letzte Speichern.
 */
export default class extends Controller {
    static values = { delay: { type: Number, default: 1500 } };

    connect() {
        this.timer = null;
        this.pending = null;
        this.dirty = false;
        this.again = false;
        this.submitting = false;

        this.status = document.createElement('p');
        this.status.className = 'self-center text-xs text-slate-500';
        this.status.setAttribute('role', 'status');
        const submit = this.element.querySelector('[type="submit"]');
        (submit?.parentElement ?? this.element).append(this.status);

        this.onChange = (event) => this.changed(event);
        this.onSubmit = () => this.submitted();
        this.onVisit = (event) => this.beforeVisit(event);
        this.onHide = () => this.save(true);
        this.element.addEventListener('input', this.onChange);
        this.element.addEventListener('change', this.onChange);
        this.element.addEventListener('submit', this.onSubmit);
        document.addEventListener('turbo:before-visit', this.onVisit);
        window.addEventListener('pagehide', this.onHide);
    }

    disconnect() {
        this.element.removeEventListener('input', this.onChange);
        this.element.removeEventListener('change', this.onChange);
        this.element.removeEventListener('submit', this.onSubmit);
        document.removeEventListener('turbo:before-visit', this.onVisit);
        window.removeEventListener('pagehide', this.onHide);
        this.status.remove();
        this.save(true);
    }

    changed(event) {
        // Suchfelder der Auswahllisten, Dateiauswahl und versteckte Felder (CSRF-Token) gehören nicht zum Inhalt
        if (this.submitting || !event.target.name || ['file', 'hidden'].includes(event.target.type)) {
            return;
        }
        this.dirty = true;
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.save(), this.delayValue);
    }

    submitted() {
        clearTimeout(this.timer);
        this.submitting = true;
    }

    /** Link geklickt, während noch etwas offen ist: erst speichern, dann weiter */
    async beforeVisit(event) {
        if (!this.dirty && !this.pending) {
            return;
        }
        event.preventDefault();
        this.save();
        while (this.pending) {
            await this.pending;
        }
        this.dirty = false;
        window.Turbo?.visit(event.detail.url);
    }

    save(keepalive = false) {
        clearTimeout(this.timer);
        if (!this.dirty || this.submitting) {
            return;
        }
        if (this.pending && !keepalive) {
            this.again = true;

            return;
        }
        this.dirty = false;
        // doppelt abgesicherter CSRF-Token wie beim normalen Absenden (Feld + Cookie + Header)
        generateCsrfToken(this.element);
        const body = new FormData(this.element);
        for (const [name, value] of [...body.entries()]) {
            if (value instanceof File) {
                body.delete(name);
            }
        }
        this.pending = fetch(this.element.action || window.location.href, {
            method: 'POST',
            body,
            keepalive,
            headers: { 'X-Autosave': '1', ...generateCsrfHeaders(this.element) },
        })
            .then((response) => this.show(response.status))
            .catch(() => {
                this.dirty = true;
                this.show(0);
            })
            .finally(() => {
                removeCsrfToken(this.element);
                this.pending = null;
                if (this.again) {
                    this.again = false;
                    this.save();
                }
            });
    }

    show(status) {
        if (status === 204) {
            const time = new Date().toLocaleTimeString('de-DE', { hour: '2-digit', minute: '2-digit' });
            this.status.textContent = formText('saved').replace('%time%', time);
        } else {
            this.status.textContent = formText(status === 422 ? 'invalid' : 'failed');
        }
    }
}
