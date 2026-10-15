import { Controller } from '@hotwired/stimulus';

/*
 * Ende folgt dem Beginn: ohne Ende eine Stunde nach dem Beginn, sonst wird die bisherige Dauer beibehalten
 * (Beginn verschieben verschiebt das Ende mit).
 */
const HOUR = 60 * 60 * 1000;

const parse = (value) => {
    const date = value ? new Date(value) : null;

    return date && !Number.isNaN(date.getTime()) ? date : null;
};

const pad = (n) => String(n).padStart(2, '0');
const format = (date) => `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

export default class extends Controller {
    static targets = ['start', 'end'];

    connect() {
        this.previous = this.startTarget.value;
    }

    follow() {
        const start = parse(this.startTarget.value);
        const previous = parse(this.previous);
        this.previous = this.startTarget.value;
        if (!start) {
            return;
        }
        const end = parse(this.endTarget.value);
        let duration = HOUR;
        if (end && previous && end >= previous) {
            duration = end - previous;
        } else if (end && !previous && end > start) {
            return;
        }
        this.endTarget.value = format(new Date(start.getTime() + duration));
        this.endTarget.dispatchEvent(new Event('input', { bubbles: true }));
    }
}
