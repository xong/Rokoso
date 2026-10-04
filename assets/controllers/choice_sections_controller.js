import { Controller } from '@hotwired/stimulus';

/* Zeigt nur die Abschnitte, die zur gewählten Option der Radiogruppe passen (data-kinds="choice schedule"); verborgene Felder werden deaktiviert. */
export default class extends Controller {
    static targets = ['section'];

    connect() {
        this.toggle();
    }

    toggle() {
        const kind = this.element.querySelector('input[type="radio"]:checked')?.value;
        this.sectionTargets.forEach((section) => {
            const visible = section.dataset.kinds.split(' ').includes(kind);
            section.hidden = !visible;
            section.querySelectorAll('input, textarea, select').forEach((field) => { field.disabled = !visible; });
        });
    }
}
