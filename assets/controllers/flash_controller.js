import { Controller } from '@hotwired/stimulus';

/* Blendet Kurzmeldungen nach einigen Sekunden aus. */
export default class extends Controller {
    connect() {
        this.timeout = setTimeout(() => this.element.remove(), 6000);
    }

    disconnect() {
        clearTimeout(this.timeout);
    }

    dismiss(event) {
        event.currentTarget.closest('div.pointer-events-auto')?.remove();
        if (!this.element.querySelector('.pointer-events-auto')) {
            this.element.remove();
        }
    }
}
