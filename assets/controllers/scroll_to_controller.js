import { Controller } from '@hotwired/stimulus';

/* Scrollt einen Container beim Laden an eine Position (z. B. Zeitleiste auf 7 Uhr). */
export default class extends Controller {
    static values = { offset: Number };

    connect() {
        this.element.scrollTop = this.offsetValue;
    }
}
