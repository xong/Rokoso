import { Controller } from '@hotwired/stimulus';

/*
 * Beschriftet die Wiederholungs-Auswahl passend zum Beginn („Monatlich am 2. Donnerstag“) und blendet „am letzten …“ aus, wenn der Beginn nicht in der letzten Woche des Monats liegt.
 * Intervall („Wiederholen alle [2] Wochen“) und Ende erscheinen nur bei einer Wiederholung; die Einheit folgt Auswahl und Zahl.
 */
export default class extends Controller {
    static targets = ['start', 'select', 'options', 'interval', 'unit'];
    static values = { labels: Object, weekdays: Array, units: Object };

    connect() {
        this.selectTarget.querySelectorAll('option').forEach((option) => { option.dataset.label ??= option.textContent; });
        this.update();
    }

    update() {
        const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(this.startTarget.value);
        const date = match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
        const day = date?.getDate();
        const daysInMonth = date ? new Date(date.getFullYear(), date.getMonth() + 1, 0).getDate() : 0;
        const params = date ? { '%day%': day, '%nth%': Math.floor((day - 1) / 7) + 1, '%weekday%': this.weekdaysValue[date.getDay()] } : null;

        this.selectTarget.querySelectorAll('option').forEach((option) => {
            const template = this.labelsValue[option.value];
            option.textContent = params && template ? template.replace(/%\w+%/g, (key) => params[key] ?? key) : option.dataset.label;
            if (option.value === 'MONTHLY_LAST') {
                const unavailable = date !== null && day + 7 <= daysInMonth && !option.selected;
                option.hidden = unavailable;
                option.disabled = unavailable;
            }
        });

        const unit = this.unitsValue[this.selectTarget.value];
        if (this.hasOptionsTarget) {
            this.optionsTarget.hidden = !unit;
        }
        if (unit && this.hasUnitTarget) {
            this.unitTarget.textContent = Number(this.intervalTarget.value) === 1 ? unit[0] : unit[1];
        }
    }
}
