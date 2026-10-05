import { Controller } from '@hotwired/stimulus';

/**
 * Verschiebbare Spalte (Liste, Kommentare): Ziehen am Trenner oder Pfeiltasten ändern die Breite in Prozent
 * des Elternelements (zwischen min und max), Doppelklick setzt sie zurück. Gespeichert im Cookie "panel_<name>",
 * damit der Server sie ohne Flackern rendert (PanelWidthExtension).
 */
export default class extends Controller {
    static targets = ['handle'];
    static values = { name: String, min: Number, max: Number, edge: { type: String, default: 'right' } };

    connect() {
        this.updateAria();
    }

    start(event) {
        if (event.button !== 0) {
            return;
        }
        event.preventDefault();
        this.handleTarget.setPointerCapture(event.pointerId);
        document.documentElement.dataset.resizing = 'true';
        this.handleTarget.addEventListener('pointermove', this.move);
        this.handleTarget.addEventListener('pointerup', this.stop);
        this.handleTarget.addEventListener('pointercancel', this.stop);
    }

    move = (event) => {
        const rect = this.element.getBoundingClientRect();
        const width = this.edgeValue === 'right' ? event.clientX - rect.left : rect.right - event.clientX;
        this.apply((width / this.parentWidth()) * 100);
    };

    stop = () => {
        this.handleTarget.removeEventListener('pointermove', this.move);
        this.handleTarget.removeEventListener('pointerup', this.stop);
        this.handleTarget.removeEventListener('pointercancel', this.stop);
        delete document.documentElement.dataset.resizing;
        if (this.percent !== undefined) {
            this.save();
        }
    };

    key(event) {
        const step = event.shiftKey ? 10 : 2;
        const grow = this.edgeValue === 'right' ? 'ArrowRight' : 'ArrowLeft';
        const shrink = this.edgeValue === 'right' ? 'ArrowLeft' : 'ArrowRight';
        const current = this.current();
        const target = {
            [grow]: current + step,
            [shrink]: current - step,
            Home: this.minimum(),
            End: this.maxValue,
        }[event.key];
        if (target === undefined) {
            return;
        }
        event.preventDefault();
        this.apply(target);
        this.save();
    }

    reset() {
        this.percent = undefined;
        this.element.style.removeProperty('--panel-width');
        document.cookie = `panel_${this.nameValue}=; path=/; max-age=0; SameSite=Lax`;
        this.updateAria();
    }

    apply(percent) {
        this.percent = Math.min(this.maxValue, Math.max(this.minimum(), percent));
        this.element.style.setProperty('--panel-width', `${this.percent.toFixed(1)}%`);
        this.updateAria();
    }

    save() {
        document.cookie = `panel_${this.nameValue}=${this.percent.toFixed(1)}; path=/; max-age=31536000; SameSite=Lax`;
    }

    // a CSS min-width in rem can be larger than the minimum percentage on small screens
    minimum() {
        const pixels = parseFloat(getComputedStyle(this.element).minWidth) || 0;
        return Math.max(this.minValue, (pixels / this.parentWidth()) * 100);
    }

    current() {
        return (this.element.getBoundingClientRect().width / this.parentWidth()) * 100;
    }

    parentWidth() {
        return this.element.parentElement.getBoundingClientRect().width || 1;
    }

    updateAria() {
        // hidden on small screens (columns stacked): keep the server value
        if (this.handleTarget.offsetParent === null) {
            return;
        }
        this.handleTarget.setAttribute('aria-valuenow', String(Math.round(this.current())));
    }
}
