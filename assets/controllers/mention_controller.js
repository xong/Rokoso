import { Controller } from '@hotwired/stimulus';

/*
 * @-Erwähnungen: Beim Tippen von "@Na…" erscheinen passende Namen.
 * ↑/↓ wählen, Enter oder Tab übernimmt, Esc schließt; die Eingabe behält immer den Fokus.
 */
export default class extends Controller {
    static targets = ['input', 'list', 'status'];
    static values = { names: Array, count: String };

    connect() {
        this.matches = [];
        this.active = -1;
        this.start = -1;
        this.listTarget.id = `mention-${Math.random().toString(36).slice(2)}`;
        this.inputTarget.setAttribute('aria-autocomplete', 'list');
        this.inputTarget.setAttribute('aria-controls', this.listTarget.id);
        this.inputTarget.setAttribute('aria-expanded', 'false');
    }

    update() {
        const input = this.inputTarget;
        const before = input.value.slice(0, input.selectionStart);
        const match = before.match(/(?:^|[^\p{L}\p{N}.])@([^@\n]{0,30})$/u);
        if (!match || this.namesValue.length === 0) {
            this.close();
            return;
        }
        const query = match[1].toLocaleLowerCase();
        this.start = before.length - match[1].length - 1;
        this.matches = this.namesValue
            .filter((name) => {
                const lower = name.toLocaleLowerCase();
                return lower.startsWith(query) || lower.split(/\s+/).some((part) => part.startsWith(query));
            })
            .slice(0, 8);
        if (this.matches.length === 0) {
            this.close();
            return;
        }
        this.active = 0;
        this.render();
    }

    key(event) {
        if (this.listTarget.hidden) {
            return;
        }
        switch (event.key) {
            case 'ArrowDown':
                this.move(1);
                break;
            case 'ArrowUp':
                this.move(-1);
                break;
            case 'Enter':
            case 'Tab':
                this.choose(this.matches[this.active]);
                break;
            case 'Escape':
                event.stopPropagation();
                this.close();
                break;
            default:
                return;
        }
        event.preventDefault();
    }

    close() {
        if (this.listTarget.hidden) {
            return;
        }
        this.listTarget.hidden = true;
        this.listTarget.replaceChildren();
        this.inputTarget.setAttribute('aria-expanded', 'false');
        this.inputTarget.removeAttribute('aria-activedescendant');
        this.matches = [];
    }

    move(step) {
        this.active = (this.active + step + this.matches.length) % this.matches.length;
        this.render();
    }

    choose(name) {
        if (!name) {
            return;
        }
        const input = this.inputTarget;
        const caret = input.selectionStart;
        const insert = `@${name} `;
        input.value = input.value.slice(0, this.start) + insert + input.value.slice(caret);
        const position = this.start + insert.length;
        input.setSelectionRange(position, position);
        input.focus();
        this.close();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    render() {
        const options = this.matches.map((name, index) => {
            const option = document.createElement('li');
            option.id = `${this.listTarget.id}-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', index === this.active ? 'true' : 'false');
            option.className = `cursor-pointer px-3 py-1.5 ${index === this.active ? 'bg-brand-50 text-brand-700' : 'hover:bg-slate-100'}`;
            option.textContent = name;
            // mousedown statt click: die Eingabe verliert so nicht den Fokus
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.choose(name);
            });
            return option;
        });
        this.listTarget.replaceChildren(...options);
        const wasHidden = this.listTarget.hidden;
        this.listTarget.hidden = false;
        this.inputTarget.setAttribute('aria-expanded', 'true');
        this.inputTarget.setAttribute('aria-activedescendant', options[this.active].id);
        options[this.active].scrollIntoView({ block: 'nearest' });
        if (wasHidden && this.hasStatusTarget) {
            this.statusTarget.textContent = this.countValue.replace('%count%', String(this.matches.length));
        }
    }
}
