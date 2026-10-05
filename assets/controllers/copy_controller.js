import { Controller } from '@hotwired/stimulus';

/*
 * Kopiert einen Text (z. B. einen Einladungslink) in die Zwischenablage und meldet das kurz am Knopf und für Screenreader.
 * Ohne Clipboard-API (kein HTTPS) per verstecktem Textfeld.
 */
export default class extends Controller {
    static targets = ['label', 'status'];
    static values = { text: String, done: String };

    async copy() {
        try {
            await navigator.clipboard.writeText(this.textValue);
        } catch {
            const field = document.createElement('textarea');
            field.value = this.textValue;
            field.setAttribute('readonly', '');
            field.className = 'fixed -left-[9999px]';
            document.body.append(field);
            field.select();
            document.execCommand('copy');
            field.remove();
        }

        if (this.hasLabelTarget) {
            this.original ??= this.labelTarget.textContent;
            this.labelTarget.textContent = this.doneValue;
        }
        if (this.hasStatusTarget) {
            this.statusTarget.textContent = this.doneValue;
        }
        clearTimeout(this.timeout);
        this.timeout = setTimeout(() => {
            if (this.hasLabelTarget) this.labelTarget.textContent = this.original;
            if (this.hasStatusTarget) this.statusTarget.textContent = '';
        }, 2500);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }
}
