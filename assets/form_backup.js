import { formText } from './form_texts.js';

/*
 * Sicherung im Browser für Formulare mit Textfeld, die nicht selbst im Hintergrund speichern (Neuanlegen, Kommentare,
 * Antworten, Direktnachrichten, Einstellungen): geänderte Felder landen im localStorage (je Konto, Seite und
 * Formular) und kommen beim nächsten Öffnen zurück – je Feld nur, wenn es noch im selben Ausgangszustand ist, damit
 * neuere Inhalte vom Server nicht überschrieben werden. Erfolgreiches Absenden löscht die Sicherung, nach 7 Tagen
 * verfällt sie. Aus: data-backup="off" am Formular oder einem umgebenden Element (öffentliche Seiten, Vertrauliches).
 */
const PREFIX = 'form-backup:';
const MAX_AGE = 7 * 24 * 3600 * 1000;
const TEXT_TYPES = new Set(['text', 'email', 'url', 'tel', 'number', 'date', 'time', 'datetime-local']);

const initial = new WeakMap();
const keys = new WeakMap();
const sent = new WeakSet();
const timers = new WeakMap();

const storage = () => {
    try {
        return window.localStorage;
    } catch {
        return null;
    }
};

function eligible(form) {
    const controllers = (form.dataset.controller ?? '').split(/\s+/);

    return form instanceof HTMLFormElement
        && form.method === 'post'
        && formText('user') !== ''
        && form.querySelector('textarea') !== null
        && form.closest('[data-backup="off"]') === null
        && !controllers.includes('autosave')
        && !controllers.includes('autosave-form');
}

/** Felder mit Inhalt, je mit stabilem Schlüssel (Name + Vorkommen bzw. Name = Wert bei Kästchen) */
function fields(form) {
    const result = new Map();
    const seen = {};
    for (const element of form.elements) {
        if (!element.name || element.disabled || element.name.endsWith('_token') || element.name === '_token') {
            continue;
        }
        if (element instanceof HTMLInputElement && (element.type === 'checkbox' || element.type === 'radio')) {
            result.set(`${element.name}=${element.value}`, element);
        } else if (element instanceof HTMLTextAreaElement || element instanceof HTMLSelectElement
            || (element instanceof HTMLInputElement && TEXT_TYPES.has(element.type))) {
            seen[element.name] = (seen[element.name] ?? -1) + 1;
            result.set(`${element.name}#${seen[element.name]}`, element);
        }
    }

    return result;
}

function read(element) {
    if (element instanceof HTMLInputElement && (element.type === 'checkbox' || element.type === 'radio')) {
        return element.checked;
    }
    if (element instanceof HTMLSelectElement && element.multiple) {
        return [...element.selectedOptions].map((option) => option.value).join('\n');
    }

    return element.value;
}

function write(element, value) {
    if (typeof value === 'boolean') {
        element.checked = value;
    } else if (element instanceof HTMLSelectElement && element.multiple) {
        const values = value.split('\n');
        for (const option of element.options) {
            option.selected = values.includes(option.value);
        }
    } else {
        element.value = value;
    }
    element.dispatchEvent(new Event('input', { bubbles: true }));
}

function snapshot(form) {
    return Object.fromEntries([...fields(form)].map(([key, element]) => [key, read(element)]));
}

// beim Öffnen festgelegt: während eines Seitenwechsels zeigt location schon auf die nächste Seite
const key = (form) => keys.get(form);

function keyFor(form) {
    const action = form.getAttribute('action') ?? '';
    const same = [...document.forms].filter((other) => (other.getAttribute('action') ?? '') === action && eligible(other));

    return `${PREFIX}${formText('user')}:${window.location.pathname}:${action}:${same.indexOf(form)}`;
}

function save(form) {
    clearTimeout(timers.get(form));
    const base = initial.get(form);
    const store = storage();
    if (!base || !store || sent.has(form) || !form.isConnected) {
        return;
    }
    const values = snapshot(form);
    const changed = Object.fromEntries(Object.entries(values).filter(([name, value]) => base[name] !== value));
    try {
        if (Object.keys(changed).length === 0) {
            store.removeItem(key(form));
        } else {
            store.setItem(key(form), JSON.stringify({ time: Date.now(), base, values: changed }));
        }
    } catch {
        // Speicher voll oder gesperrt: dann eben ohne Sicherung
    }
}

function notice(form, restored, base) {
    const box = document.createElement('div');
    box.className = 'mb-3 flex flex-wrap items-center gap-2 rounded-md bg-brand-50 px-3 py-2 text-sm text-brand-800 ring-1 ring-brand-200';
    box.setAttribute('role', 'status');
    const text = document.createElement('span');
    const discard = document.createElement('button');
    discard.type = 'button';
    discard.className = 'ml-auto font-medium underline';
    discard.addEventListener('click', () => {
        for (const [name, element] of restored) {
            write(element, base[name]);
        }
        storage()?.removeItem(key(form));
        box.remove();
        form.querySelector('textarea')?.focus();
    });
    box.append(text, discard);
    form.prepend(box);
    // erst nach dem Einfügen befüllen, damit Screenreader die Meldung ansagen
    requestAnimationFrame(() => {
        text.textContent = formText('restored');
        discard.textContent = formText('discard');
    });
}

function restore(form) {
    if (initial.has(form) || !eligible(form)) {
        return;
    }
    const base = snapshot(form);
    keys.set(form, keyFor(form));
    initial.set(form, base);
    const store = storage();
    let backup = null;
    try {
        backup = JSON.parse(store?.getItem(key(form)) ?? 'null');
    } catch {
        backup = null;
    }
    if (!backup || Date.now() - backup.time > MAX_AGE) {
        return;
    }
    const restored = new Map();
    const elements = fields(form);
    for (const [name, value] of Object.entries(backup.values ?? {})) {
        const element = elements.get(name);
        if (element && base[name] === backup.base?.[name] && base[name] !== value) {
            write(element, value);
            restored.set(name, element);
        }
    }
    if (restored.size > 0) {
        notice(form, restored, base);
    }
}

function cleanup() {
    const store = storage();
    if (!store) {
        return;
    }
    try {
        for (let i = store.length - 1; i >= 0; i--) {
            const name = store.key(i);
            if (name?.startsWith(PREFIX) && !(Date.now() - (JSON.parse(store.getItem(name) ?? '{}').time ?? 0) < MAX_AGE)) {
                store.removeItem(name);
            }
        }
    } catch {
        // kaputte Einträge stören nicht
    }
}

const restoreAll = () => [...document.forms].forEach(restore);
const saveAll = () => [...document.forms].filter((form) => initial.has(form)).forEach(save);

function changed(event) {
    const form = event.target.form;
    if (!form || !initial.has(form) || event.target.type === 'hidden') {
        return;
    }
    sent.delete(form);
    clearTimeout(timers.get(form));
    timers.set(form, setTimeout(() => save(form), 400));
}

document.addEventListener('input', changed);
document.addEventListener('change', changed);
document.addEventListener('turbo:submit-end', (event) => {
    const form = event.detail.formSubmission.formElement;
    if (event.detail.success && initial.has(form)) {
        clearTimeout(timers.get(form));
        sent.add(form);
        storage()?.removeItem(key(form));
    }
});
// Formulare ohne Turbo (data-turbo="false"): beim Absenden löschen
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (initial.has(form) && form.closest('[data-turbo="false"]')) {
        sent.add(form);
        storage()?.removeItem(key(form));
    }
});
document.addEventListener('turbo:before-visit', saveAll);
document.addEventListener('turbo:before-render', saveAll);
window.addEventListener('pagehide', saveAll);
document.addEventListener('turbo:load', () => {
    restoreAll();
    cleanup();
});
document.addEventListener('turbo:frame-load', restoreAll);
