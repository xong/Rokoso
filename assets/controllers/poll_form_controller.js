import { Controller } from '@hotwired/stimulus';

/* Zeigt im Abstimmungsformular nur die Felder, die zur gewählten Art passen (data-kinds="choice schedule"). */
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
