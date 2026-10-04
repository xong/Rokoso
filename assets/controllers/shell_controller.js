import { Controller } from '@hotwired/stimulus';

/**
 * App-Rahmen: einklappbare Navigation (Desktop) und Off-Canvas-Navigation (mobil).
 * Der Einklapp-Zustand wird in einem Cookie gespeichert, damit der Server ihn ohne Flackern rendert.
 */
export default class extends Controller {
    static targets = ['nav', 'main', 'openButton', 'collapseToggle'];
    static values = { collapsed: Boolean, collapseLabel: String, expandLabel: String };

    toggleCollapsed() {
        this.collapsedValue = !this.collapsedValue;
        document.cookie = `nav_collapsed=${this.collapsedValue ? 1 : 0}; path=/; max-age=31536000; SameSite=Lax`;
    }

    collapsedValueChanged() {
        this.element.dataset.collapsed = String(this.collapsedValue);
        if (this.hasCollapseToggleTarget) {
            const label = this.collapsedValue ? this.expandLabelValue : this.collapseLabelValue;
            this.collapseToggleTarget.setAttribute('aria-label', label);
            this.collapseToggleTarget.title = label;
        }
    }

    open(event) {
        // several triggers (header, mobile bottom bar): focus returns to the one that was used
        this.trigger = event?.currentTarget ?? this.openButtonTarget;
        this.element.dataset.navOpen = 'true';
        this.openButtonTargets.forEach((button) => button.setAttribute('aria-expanded', 'true'));
        this.mainTarget.inert = true;
        this.navTarget.querySelector('a[href]')?.focus();
    }

    close() {
        if (this.element.dataset.navOpen !== 'true') {
            return;
        }
        delete this.element.dataset.navOpen;
        this.openButtonTargets.forEach((button) => button.setAttribute('aria-expanded', 'false'));
        this.mainTarget.inert = false;
        (this.trigger ?? this.openButtonTarget).focus();
    }
}
