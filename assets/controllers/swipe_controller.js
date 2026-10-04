import { Controller } from '@hotwired/stimulus';

/*
 * Wischgeste auf Touch-Geräten für Listeneinträge: nach rechts löst das Formular
 * `right` aus (z. B. Erledigt), nach links `left` (z. B. Papierkorb).
 * Reine Ergänzung – dieselben Aktionen gibt es als Knöpfe für Maus und Tastatur.
 */
export default class extends Controller {
    static targets = ['left', 'right'];

    start(event) {
        if (event.touches.length !== 1) {
            return;
        }
        this.x = event.touches[0].clientX;
        this.y = event.touches[0].clientY;
        this.dx = 0;
        this.horizontal = null;
    }

    move(event) {
        if (this.x === undefined) {
            return;
        }
        const dx = event.touches[0].clientX - this.x;
        const dy = event.touches[0].clientY - this.y;
        if (this.horizontal === null && (Math.abs(dx) > 10 || Math.abs(dy) > 10)) {
            this.horizontal = Math.abs(dx) > Math.abs(dy);
        }
        if (!this.horizontal || !this.formFor(dx)) {
            return;
        }
        this.dx = dx;
        this.element.style.transform = `translateX(${dx}px)`;
        this.element.style.backgroundColor = dx > 0 ? 'var(--color-emerald-50)' : 'var(--color-red-50)';
    }

    end() {
        const form = this.formFor(this.dx);
        if (this.horizontal && form && Math.abs(this.dx) > this.element.offsetWidth * 0.35) {
            this.element.style.transform = `translateX(${this.dx > 0 ? '100%' : '-100%'})`;
            form.requestSubmit();
        } else {
            this.cancel();
        }
        this.x = undefined;
    }

    cancel() {
        this.element.style.transform = '';
        this.element.style.backgroundColor = '';
        this.x = undefined;
    }

    formFor(dx) {
        if (dx > 0) {
            return this.hasRightTarget ? this.rightTarget : null;
        }
        if (dx < 0) {
            return this.hasLeftTarget ? this.leftTarget : null;
        }

        return null;
    }
}
