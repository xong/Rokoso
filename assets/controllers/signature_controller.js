import { Controller } from '@hotwired/stimulus';

/*
 * Tauscht beim Wechsel des Absenders die Signatur im Text aus (Block nach "-- ").
 */
export default class extends Controller {
    static targets = ['account', 'input'];
    static values = { signatures: Object };

    connect() {
        this.current = this.accountTarget.value;
    }

    swap() {
        const previous = this.signaturesValue[this.current] ?? '';
        const next = this.signaturesValue[this.accountTarget.value] ?? '';
        this.current = this.accountTarget.value;
        if (previous === next) {
            return;
        }
        const input = this.inputTarget;
        const oldBlock = `-- \n${previous}`;
        if (previous !== '' && input.value.includes(oldBlock)) {
            input.value = next === ''
                ? input.value.replace(`\n\n${oldBlock}`, '').replace(oldBlock, '')
                : input.value.replace(oldBlock, `-- \n${next}`);
        } else if (next !== '') {
            const rest = input.value.replace(/^\n+/, '');
            input.value = `\n\n-- \n${next}${rest === '' ? '\n' : `\n\n${rest}`}`;
        }
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
