import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

/*
 * Befehlspalette (Strg+K / Cmd+K) als Combobox mit Listbox in einem <dialog>,
 * dazu globale Tastenkürzel: „/“ Suche, „?“ Übersicht, „c“ Verfassen, „g x“ Bereich wechseln.
 * Kürzel ohne Modifier greifen nicht in Eingabefeldern.
 */
export default class extends Controller {
    static targets = ['dialog', 'help', 'input', 'list', 'option', 'searchLabel', 'status'];

    connect() {
        this.prefix = null;
        this.onKeydown = (event) => this.keydown(event);
        window.addEventListener('keydown', this.onKeydown);
    }

    disconnect() {
        window.removeEventListener('keydown', this.onKeydown);
    }

    open(event) {
        event?.preventDefault();
        if (this.dialogTarget.open) {
            return;
        }
        this.helpTarget.close();
        this.inputTarget.value = '';
        this.filter();
        this.dialogTarget.showModal();
        this.inputTarget.focus();
    }

    openHelp(event) {
        event?.preventDefault();
        this.dialogTarget.close();
        this.helpTarget.showModal();
    }

    closed() {
        this.inputTarget.removeAttribute('aria-activedescendant');
    }

    backdrop(event) {
        // Klick auf den Hintergrund (das <dialog> selbst) schließt
        if (event.target === event.currentTarget) {
            event.currentTarget.close();
        }
    }

    filter() {
        const query = this.inputTarget.value.trim();
        const needle = query.toLocaleLowerCase('de');
        let visible = 0;
        this.optionTargets.forEach((option) => {
            if (option.dataset.search) {
                option.hidden = query === '';
                const label = this.searchLabelTarget;
                label.textContent = query === '' ? label.textContent : label.dataset.template.replace('__Q__', query);
            } else {
                option.hidden = needle !== '' && !option.textContent.toLocaleLowerCase('de').includes(needle);
            }
            if (!option.hidden) {
                visible++;
            }
        });
        this.statusTarget.textContent = visible > 0 ? '' : '–';
        this.select(this.visibleOptions[0]);
    }

    navigate(event) {
        const options = this.visibleOptions;
        const index = options.indexOf(this.current);
        switch (event.key) {
            case 'ArrowDown':
                this.select(options[(index + 1) % options.length]);
                break;
            case 'ArrowUp':
                this.select(options[(index - 1 + options.length) % options.length]);
                break;
            case 'Home':
                if (!event.shiftKey) this.select(options[0]);
                break;
            case 'End':
                if (!event.shiftKey) this.select(options[options.length - 1]);
                break;
            case 'Enter':
                if (this.current) this.go(this.current);
                break;
            default:
                return;
        }
        event.preventDefault();
    }

    hover(event) {
        this.select(event.currentTarget);
    }

    choose(event) {
        this.go(event.currentTarget);
    }

    get visibleOptions() {
        return this.optionTargets.filter((option) => !option.hidden);
    }

    get current() {
        return this.optionTargets.find((option) => option.getAttribute('aria-selected') === 'true');
    }

    select(option) {
        this.optionTargets.forEach((o) => o.setAttribute('aria-selected', o === option ? 'true' : 'false'));
        if (option) {
            this.inputTarget.setAttribute('aria-activedescendant', option.id);
            option.scrollIntoView({ block: 'nearest' });
        } else {
            this.inputTarget.removeAttribute('aria-activedescendant');
        }
    }

    go(option) {
        let url = option.dataset.url;
        if (option.dataset.search) {
            url += '?q=' + encodeURIComponent(this.inputTarget.value.trim());
        }
        this.dialogTarget.close();
        Turbo.visit(url);
    }

    keydown(event) {
        if ((event.ctrlKey || event.metaKey) && !event.altKey && event.key.toLowerCase() === 'k') {
            this.dialogTarget.open ? this.dialogTarget.close() : this.open(event);
            return;
        }
        if (event.ctrlKey || event.metaKey || event.altKey || event.defaultPrevented || this.isTyping(event.target)) {
            return;
        }
        if (this.dialogTarget.open || this.helpTarget.open) {
            return;
        }

        const key = event.key;
        if (this.prefix === 'g') {
            this.prefix = null;
            const option = this.optionTargets.find((o) => o.dataset.keys === 'g ' + key);
            if (option) {
                event.preventDefault();
                Turbo.visit(option.dataset.url);
            }
            return;
        }

        switch (key) {
            case 'g':
                this.prefix = 'g';
                clearTimeout(this.prefixTimer);
                this.prefixTimer = setTimeout(() => (this.prefix = null), 1500);
                break;
            case '?':
                this.openHelp(event);
                break;
            case '/':
                event.preventDefault();
                this.focusSearch();
                break;
            default: {
                const option = this.optionTargets.find((o) => o.dataset.keys === key);
                if (option) {
                    event.preventDefault();
                    Turbo.visit(option.dataset.url);
                }
            }
        }
    }

    focusSearch() {
        const field = document.querySelector('main input[type="search"]');
        if (field) {
            field.focus();
            field.select();
        } else {
            this.open();
        }
    }

    isTyping(element) {
        return element instanceof HTMLElement
            && (element.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(element.tagName));
    }
}
