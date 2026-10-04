import { Controller } from '@hotwired/stimulus';

/*
 * Eingeklappte Navigation: Die Flyouts sind position: fixed, damit die Liste scrollen kann
 * (overflow würde absolut positionierte Flyouts abschneiden). Hier werden sie neben den Eintrag gesetzt.
 */
export default class extends Controller {
    place(event) {
        const item = event.target.closest('[data-flyout-item]');
        const flyout = item?.querySelector(':scope > [data-flyout]');
        if (!flyout || !this.element.contains(item)) {
            return;
        }
        const rect = item.getBoundingClientRect();
        flyout.style.left = `${rect.right}px`;
        flyout.style.top = `${rect.top}px`;
        // nicht unter den Fensterrand ragen lassen
        requestAnimationFrame(() => {
            const max = window.innerHeight - flyout.offsetHeight - 8;
            if (rect.top > max) {
                flyout.style.top = `${Math.max(8, max)}px`;
            }
        });
    }
}
