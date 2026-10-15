import { Controller } from '@hotwired/stimulus';

const FOCUS_KEY = 'live-search-focus';

/**
 * Submits a GET filter form while typing (debounced). The form should carry data-turbo-action="replace",
 * then Turbo morphs the page; the focused field is left alone so the typed text and the caret stay.
 */
export default class extends Controller {
    static targets = ['input'];
    static values = { delay: { type: Number, default: 350 } };

    connect() {
        this.submitted = this.inputTarget.value.trim();
        this.keepFocused = (event) => {
            if (event.target === this.inputTarget && document.activeElement === this.inputTarget) {
                event.preventDefault();
            }
        };
        document.addEventListener('turbo:before-morph-element', this.keepFocused);
        // a morph keeps the focus anyway
        this.forgetFocus = () => this.clearFocusFlag();
        document.addEventListener('turbo:morph', this.forgetFocus);
        // after a full render (e.g. coming from a message detail) the field gets the focus back
        try {
            if (sessionStorage.getItem(FOCUS_KEY) === this.inputTarget.id) {
                sessionStorage.removeItem(FOCUS_KEY);
                const end = this.inputTarget.value.length;
                this.inputTarget.focus();
                this.inputTarget.setSelectionRange(end, end);
            }
        } catch {
            // storage blocked: the focus simply stays where the browser puts it
        }
    }

    disconnect() {
        clearTimeout(this.timer);
        document.removeEventListener('turbo:before-morph-element', this.keepFocused);
        document.removeEventListener('turbo:morph', this.forgetFocus);
    }

    clearFocusFlag() {
        try {
            sessionStorage.removeItem(FOCUS_KEY);
        } catch {
            // ignore
        }
    }

    type(event) {
        if (event.isComposing) {
            return;
        }
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.submitIfChanged(), this.delayValue);
    }

    // "search" fires when the field is cleared with the × button or Escape
    cleared() {
        if (this.inputTarget.value === '') {
            clearTimeout(this.timer);
            this.submitIfChanged();
        }
    }

    // Enter or a select change submits the form itself
    remember() {
        clearTimeout(this.timer);
        this.submitted = this.inputTarget.value.trim();
        this.rememberFocus();
    }

    submitIfChanged() {
        if (this.inputTarget.value.trim() === this.submitted) {
            return;
        }
        this.element.requestSubmit();
    }

    rememberFocus() {
        try {
            if (document.activeElement === this.inputTarget) {
                sessionStorage.setItem(FOCUS_KEY, this.inputTarget.id);
            } else {
                sessionStorage.removeItem(FOCUS_KEY);
            }
        } catch {
            // ignore
        }
    }
}
