import { Controller } from '@hotwired/stimulus';

/*
 * Passwort im Klartext zeigen: Umschalt-Knopf (aria-pressed) neben dem Feld.
 * Beim Absenden wird das Feld wieder verdeckt, damit der Browser es als Passwort behandelt.
 */
export default class extends Controller {
    static targets = ['input', 'button'];

    connect() {
        this.hide = () => this.set(false);
        this.inputTarget.form?.addEventListener('submit', this.hide);
    }

    disconnect() {
        this.inputTarget.form?.removeEventListener('submit', this.hide);
    }

    toggle() {
        this.set(this.inputTarget.type === 'password');
        this.inputTarget.focus();
    }

    set(visible) {
        this.inputTarget.type = visible ? 'text' : 'password';
        this.buttonTarget.setAttribute('aria-pressed', visible ? 'true' : 'false');
    }
}
