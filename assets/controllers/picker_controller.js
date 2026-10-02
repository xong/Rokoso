import { Controller } from '@hotwired/stimulus';

/*
 * Durchsuchbare Auswahl auf Basis von <details>:
 * - Suchfeld filtert die Optionen (data-picker-target="option", Text in data-label)
 * - Esc schließt und gibt den Fokus an den Auslöser (<summary>) zurück
 * - Klick außerhalb schließt – auch in ein iframe (HTML-Mail), dort kommt kein Klick-Ereignis an,
 *   das Fenster verliert aber den Fokus
 * - Fokus verlässt die Auswahl per Tab → schließt
 */
export default class extends Controller {
    static targets = ['search', 'option', 'empty'];

    connect() {
        this.outside = (event) => {
            if (this.element.open && !this.element.contains(event.target)) {
                this.element.open = false;
            }
        };
        this.windowBlur = () => {
            // Fokus wandert in ein iframe der Seite
            setTimeout(() => {
                if (this.element.open && document.activeElement?.tagName === 'IFRAME') {
                    this.element.open = false;
                }
            }, 0);
        };
        this.focusOut = (event) => {
            if (this.element.open && event.relatedTarget && !this.element.contains(event.relatedTarget)) {
                this.element.open = false;
            }
        };
        document.addEventListener('pointerdown', this.outside);
        window.addEventListener('blur', this.windowBlur);
        this.element.addEventListener('focusout', this.focusOut);
        if (this.element.open && this.hasSearchTarget) {
            this.searchTarget.focus();
        }
    }

    disconnect() {
        document.removeEventListener('pointerdown', this.outside);
        window.removeEventListener('blur', this.windowBlur);
        this.element.removeEventListener('focusout', this.focusOut);
    }

    toggled() {
        if (this.element.open && this.hasSearchTarget) {
            this.searchTarget.value = '';
            this.filter();
            this.searchTarget.focus();
        }
    }

    filter() {
        const term = this.searchTarget.value.trim().toLowerCase();
        let visible = 0;
        this.optionTargets.forEach((option) => {
            const match = !term || option.dataset.label.toLowerCase().includes(term);
            option.hidden = !match;
            if (match) visible++;
        });
        if (this.hasEmptyTarget) {
            this.emptyTarget.hidden = visible > 0;
        }
    }

    close(event) {
        if (!this.element.open) return;
        event.preventDefault();
        event.stopPropagation();
        this.element.open = false;
        this.element.querySelector('summary')?.focus();
    }

    /* Enter im Suchfeld soll das Formular nicht absenden */
    preventSubmit(event) {
        event.preventDefault();
    }
}
