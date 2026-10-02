import { Controller } from '@hotwired/stimulus';

/*
 * Markdown-Textfeld im Forum: Bilder per Drag&Drop, Einfügen (Strg+V) oder Dateiauswahl hochladen
 * und als ![name](url) an der Cursorposition einfügen. Andere Dateien gehen in die Anhänge.
 */
const IMAGE_TYPES = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];

export default class extends Controller {
    static targets = ['input', 'status'];
    static values = { url: String, token: String, uploading: String, done: String, error: String };

    over(event) {
        const items = Array.from(event.dataTransfer?.items ?? []);
        if (items.some((item) => item.kind === 'file' && IMAGE_TYPES.includes(item.type))) event.preventDefault();
    }

    drop(event) {
        const images = this.images(event.dataTransfer?.files);
        if (!images.length) return;
        event.preventDefault();
        event.stopPropagation();
        this.uploadAll(images);
    }

    paste(event) {
        const images = this.images(event.clipboardData?.files);
        if (!images.length) return;
        event.preventDefault();
        this.uploadAll(images);
    }

    pick(event) {
        this.uploadAll(this.images(event.target.files));
        event.target.value = '';
    }

    images(fileList) {
        return Array.from(fileList ?? []).filter((file) => IMAGE_TYPES.includes(file.type));
    }

    async uploadAll(files) {
        for (const file of files) {
            await this.upload(file);
        }
        this.inputTarget.focus();
    }

    async upload(file) {
        this.status(this.uploadingValue);
        const body = new FormData();
        body.append('image', file);
        body.append('_token', this.tokenValue);
        try {
            const response = await fetch(this.urlValue, { method: 'POST', body, headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            const { markdown } = await response.json();
            this.insert(markdown);
            this.status(this.doneValue);
        } catch {
            this.status(`${this.errorValue} (${file.name})`);
        }
    }

    insert(text) {
        const input = this.inputTarget;
        const { selectionStart: start, selectionEnd: end, value } = input;
        const before = value.slice(0, start);
        const prefix = before === '' || before.endsWith('\n') ? '' : '\n';
        const snippet = `${prefix}${text}\n`;
        input.value = before + snippet + value.slice(end);
        input.selectionStart = input.selectionEnd = start + snippet.length;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    status(text) {
        if (this.hasStatusTarget) this.statusTarget.textContent = text;
    }
}
