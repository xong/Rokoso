import { Controller } from '@hotwired/stimulus';

/*
 * Adressvorschläge (Combobox): Der Text nach dem letzten Komma/Semikolon wird mit Kontakten,
 * Mitgliedern und Kontaktgruppen verglichen. Optionen sind Text oder {label, value} (Gruppe → alle Adressen). ↑/↓ wählen, Enter oder Tab übernimmt, Esc schließt.
 */
export default class extends Controller {
    static targets = ['input', 'list', 'status'];
    static values = { options: Array, count: String };

    connect() {
        this.matches = [];
        this.active = -1;
        this.listTarget.id = `recipients-${Math.random().toString(36).slice(2)}`;
        this.inputTarget.setAttribute('aria-autocomplete', 'list');
        this.inputTarget.setAttribute('aria-controls', this.listTarget.id);
        this.inputTarget.setAttribute('aria-expanded', 'false');
        this.inputTarget.setAttribute('autocomplete', 'off');
    }

    token() {
        const value = this.inputTarget.value;
        const start = Math.max(value.lastIndexOf(','), value.lastIndexOf(';')) + 1;
        return { start, text: value.slice(start).trim() };
    }

    update() {
        const { text } = this.token();
        if (text.length < 2) {
            this.close();
            return;
        }
        const query = text.toLocaleLowerCase();
        const chosen = this.inputTarget.value.toLocaleLowerCase();
        this.matches = this.optionsValue
            .map((option) => (typeof option === 'string' ? { label: option, value: option } : option))
            .filter((option) => {
                const lower = option.label.toLocaleLowerCase();
                return lower.includes(query) && !chosen.includes(`${this.address(option.value.toLocaleLowerCase())}>,`);
            })
            .slice(0, 8);
        if (this.matches.length === 0) {
            this.close();
            return;
        }
        this.active = 0;
        this.render();
    }

    address(option) {
        const match = option.match(/<([^>]+)>$/);
        return match ? match[1] : option;
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

    choose(option) {
        if (!option) {
            return;
        }
        const input = this.inputTarget;
        const { start } = this.token();
        const head = input.value.slice(0, start).trimEnd();
        input.value = `${head}${head ? ' ' : ''}${option.value}, `;
        input.setSelectionRange(input.value.length, input.value.length);
        input.focus();
        this.close();
        input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    render() {
        const options = this.matches.map((match, index) => {
            const option = document.createElement('li');
            option.id = `${this.listTarget.id}-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', index === this.active ? 'true' : 'false');
            option.className = `cursor-pointer truncate px-3 py-1.5 ${index === this.active ? 'bg-brand-50 text-brand-700' : 'hover:bg-slate-100'}`;
            option.textContent = match.label;
            // mousedown statt click: die Eingabe verliert so nicht den Fokus
            option.addEventListener('mousedown', (event) => {
                event.preventDefault();
                this.choose(match);
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
