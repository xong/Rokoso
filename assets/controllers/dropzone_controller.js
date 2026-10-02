import { Controller } from '@hotwired/stimulus';

/*
 * Drag&Drop für <input type="file" multiple>: abgelegte und ausgewählte Dateien werden gesammelt,
 * als Liste mit „Entfernen“-Knopf angezeigt und über das native Feld mitgeschickt.
 */
export default class extends Controller {
    static targets = ['input', 'list'];
    static values = { removeLabel: String };

    connect() {
        this.files = [];
    }

    over(event) {
        if (!event.dataTransfer?.types.includes('Files')) return;
        event.preventDefault();
        this.element.dataset.over = '';
    }

    leave(event) {
        if (!this.element.contains(event.relatedTarget)) {
            delete this.element.dataset.over;
        }
    }

    drop(event) {
        if (!event.dataTransfer?.files.length) return;
        event.preventDefault();
        delete this.element.dataset.over;
        this.add(event.dataTransfer.files);
    }

    /* Auswahl über den Dateidialog ersetzt die bisherige Auswahl des Felds – wir ergänzen stattdessen */
    render() {
        this.add(this.inputTarget.files);
    }

    add(fileList) {
        for (const file of fileList) {
            const duplicate = this.files.some((f) => f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
            if (!duplicate) this.files.push(file);
        }
        this.sync();
    }

    remove(event) {
        this.files.splice(Number(event.currentTarget.dataset.index), 1);
        this.sync();
        this.inputTarget.focus();
    }

    sync() {
        const transfer = new DataTransfer();
        this.files.forEach((file) => transfer.items.add(file));
        this.inputTarget.files = transfer.files;

        this.listTarget.replaceChildren(...this.files.map((file, index) => {
            const item = document.createElement('li');
            item.className = 'flex items-center gap-2 rounded-md bg-slate-50 px-2 py-1';
            const name = document.createElement('span');
            name.className = 'min-w-0 flex-1 truncate';
            name.textContent = file.name;
            const size = document.createElement('span');
            size.className = 'text-xs text-slate-500';
            size.textContent = this.formatSize(file.size);
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'rounded px-1.5 text-slate-500 hover:bg-slate-200 hover:text-red-600';
            button.dataset.index = String(index);
            button.dataset.action = 'dropzone#remove';
            button.setAttribute('aria-label', `${this.removeLabelValue}: ${file.name}`);
            button.textContent = '×';
            item.append(name, size, button);
            return item;
        }));
    }

    formatSize(bytes) {
        if (bytes < 1024) return `${bytes} B`;
        if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
        return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    }
}
