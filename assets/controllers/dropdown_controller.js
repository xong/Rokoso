import { Controller } from '@hotwired/stimulus';

/*
 * Aufklappmenü auf Basis von <details>: Esc und Klick außerhalb schließen,
 * Fokus geht zurück zum Auslöser.
 */
export default class extends Controller {
    connect() {
        this.outside = (event) => {
            if (this.element.open && !this.element.contains(event.target)) {
                this.element.open = false;
            }
        };
        document.addEventListener('click', this.outside);
    }

    disconnect() {
        document.removeEventListener('click', this.outside);
    }

    close() {
        if (this.element.open) {
            this.element.open = false;
            this.element.querySelector('summary')?.focus();
        }
    }
}
