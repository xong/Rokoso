import { Controller } from '@hotwired/stimulus';

/* Sendet das Formular ab, sobald sich ein Feld ändert (z. B. Rollen-Auswahl, Filter). */
export default class extends Controller {
    submit() {
        this.element.requestSubmit();
    }
}
