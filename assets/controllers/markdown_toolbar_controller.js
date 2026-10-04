import { Controller } from '@hotwired/stimulus';

/*
 * Werkzeugleiste für Markdown-Text: fett/kursiv umschließen, Zeilen mit Präfix versehen,
 * Link einfügen, Textbaustein einfügen. Pfeiltasten wandern durch die Leiste (ein Tab-Stopp),
 * Strg+B / Strg+I im Textfeld.
 */
export default class extends Controller {
    static targets = ['input', 'snippet'];

    wrap({ params: { before, after } }) {
        const { selectionStart: start, selectionEnd: end, value } = this.inputTarget;
        const selected = value.slice(start, end);
        this.replace(start, end, before + selected + after, start + before.length, start + before.length + selected.length);
    }

    prefix({ params: { prefix } }) {
        const { selectionStart: start, selectionEnd: end, value } = this.inputTarget;
        const lineStart = value.lastIndexOf('\n', start - 1) + 1;
        const lines = value.slice(lineStart, end).split('\n');
        const numbered = prefix === '1. ';
        const text = lines.map((line, index) => (numbered ? `${index + 1}. ` : prefix) + line).join('\n');
        this.replace(lineStart, end, text, lineStart + text.length, lineStart + text.length);
    }

    link() {
        const { selectionStart: start, selectionEnd: end, value } = this.inputTarget;
        const selected = value.slice(start, end);
        const isUrl = /^https?:\/\/\S+$/.test(selected);
        const text = isUrl ? `[](${selected})` : `[${selected}](https://)`;
        // Cursor dorthin, wo noch etwas fehlt
        const caret = isUrl ? start + 1 : start + selected.length + 3;
        this.replace(start, end, text, caret, isUrl ? caret : caret + 8);
    }

    insertSnippet() {
        if (!this.hasSnippetTarget) {
            return;
        }
        if (this.snippetTarget.value === '') {
            this.snippetTarget.focus();
            return;
        }
        const { selectionStart: start, selectionEnd: end } = this.inputTarget;
        const text = this.snippetTarget.value;
        this.replace(start, end, text, start + text.length, start + text.length);
        this.snippetTarget.value = '';
    }

    shortcut(event) {
        if (!(event.ctrlKey || event.metaKey) || event.altKey || event.shiftKey) {
            return;
        }
        const key = event.key.toLowerCase();
        if (key === 'b') {
            this.wrap({ params: { before: '**', after: '**' } });
        } else if (key === 'i') {
            this.wrap({ params: { before: '_', after: '_' } });
        } else {
            return;
        }
        event.preventDefault();
    }

    navigate(event) {
        const buttons = [...event.currentTarget.querySelectorAll('button')];
        const index = buttons.indexOf(document.activeElement);
        if (index < 0) {
            return;
        }
        let next;
        switch (event.key) {
            case 'ArrowRight':
            case 'ArrowDown':
                next = (index + 1) % buttons.length;
                break;
            case 'ArrowLeft':
            case 'ArrowUp':
                next = (index - 1 + buttons.length) % buttons.length;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = buttons.length - 1;
                break;
            default:
                return;
        }
        event.preventDefault();
        buttons.forEach((button, i) => {
            button.tabIndex = i === next ? 0 : -1;
        });
        buttons[next].focus();
    }

    replace(start, end, text, selectionStart, selectionEnd) {
        const input = this.inputTarget;
        input.focus();
        input.setSelectionRange(start, end);
        // execCommand erhält das Rückgängigmachen des Browsers; sonst direkt ersetzen
        if (!document.execCommand?.('insertText', false, text)) {
            input.setRangeText(text, start, end, 'end');
            input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        input.setSelectionRange(selectionStart, selectionEnd);
    }
}
