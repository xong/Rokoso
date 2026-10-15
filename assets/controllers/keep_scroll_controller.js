import { Controller } from '@hotwired/stimulus';

/*
 * Hält die Scrollposition der Liste über Turbo-Seitenwechsel (z. B. Klick auf eine E-Mail), solange es dieselbe
 * Liste ist (key), und holt den aktiven Eintrag ins Bild. Turbo ersetzt sonst den Body und die Liste springt nach oben.
 * Mobil ist die Liste in der Detailansicht ausgeblendet: dann bleibt die gemerkte Position für den Rückweg stehen.
 */
let saved = null;

const visible = (element) => element.clientHeight > 0;

addEventListener('turbo:before-render', () => {
    const list = document.querySelector('[data-controller~="keep-scroll"]');
    if (!list) {
        saved = null;
    } else if (visible(list)) {
        saved = { key: list.dataset.keepScrollKeyValue, top: list.scrollTop };
    }
});

export default class extends Controller {
    static values = { key: String };

    connect() {
        if (!visible(this.element)) {
            return;
        }
        if (saved?.key === this.keyValue) {
            this.element.scrollTop = saved.top;
        }
        saved = null;

        const active = this.element.querySelector('[aria-current="page"]');
        if (active) {
            const box = this.element.getBoundingClientRect();
            const item = active.getBoundingClientRect();
            if (item.top < box.top || item.bottom > box.bottom) {
                active.scrollIntoView({ block: 'nearest' });
            }
        }
    }
}
