import { Controller } from '@hotwired/stimulus';

/* Blendet Kurzmeldungen nach einigen Sekunden aus (nicht, solange Maus oder Fokus darin sind). */
export default class extends Controller {
    static values = { delay: { type: Number, default: 6000 } };

    connect() {
        this.start();
        this.element.addEventListener('mouseenter', this.stop);
        this.element.addEventListener('focusin', this.stop);
        this.element.addEventListener('mouseleave', this.start);
        this.element.addEventListener('focusout', this.start);
    }

    disconnect() {
        this.stop();
    }

    start = () => {
        this.stop();
        this.timeout = setTimeout(() => this.element.remove(), this.delayValue);
    };

    stop = () => {
        clearTimeout(this.timeout);
    };

    dismiss(event) {
        event.currentTarget.closest('div.pointer-events-auto')?.remove();
        if (!this.element.querySelector('.pointer-events-auto')) {
            this.element.remove();
        }
    }
}
