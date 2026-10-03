import { Controller } from '@hotwired/stimulus';

/* Mehrfachauswahl in Listen: zeigt die Sammelaktionen, sobald etwas ausgewählt ist. */
export default class extends Controller {
    static targets = ['bar', 'item', 'all', 'count'];

    connect() {
        this.update();
    }

    toggleAll() {
        this.itemTargets.forEach((item) => { item.checked = this.allTarget.checked; });
        this.update();
    }

    update() {
        const selected = this.itemTargets.filter((item) => item.checked).length;
        const wasHidden = this.barTarget.hidden;
        this.barTarget.hidden = selected === 0;
        if (this.hasAllTarget) {
            this.allTarget.checked = selected > 0 && selected === this.itemTargets.length;
            this.allTarget.indeterminate = selected > 0 && selected < this.itemTargets.length;
        }
        if (this.hasCountTarget) {
            this.countTarget.textContent = selected === 0 ? '' : `${selected} ${this.element.dataset.bulkSelectedLabel ?? ''}`.trim();
        }
        // Erstes Auswählen: Leiste wird sichtbar, Fokus bleibt am Kontrollkästchen
        if (wasHidden && selected > 0) {
            this.barTarget.scrollIntoView({ block: 'nearest' });
        }
    }
}
