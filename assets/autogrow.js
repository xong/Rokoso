/*
 * Textfelder wachsen beim Schreiben mit: nie kleiner als ihre rows-Höhe, höchstens 70 % der Fensterhöhe, danach
 * scrollen sie. Gilt für alle <textarea>; abschalten mit data-autogrow="false".
 */
const MAX_SHARE = 0.7;

function scrollParent(element) {
    for (let parent = element.parentElement; parent; parent = parent.parentElement) {
        const { overflowY } = getComputedStyle(parent);
        if ((overflowY === 'auto' || overflowY === 'scroll') && parent.scrollHeight > parent.clientHeight) {
            return parent;
        }
    }

    return document.scrollingElement;
}

export function grow(textarea) {
    // ausgeblendete Felder (zugeklappt, anderer Reiter) haben keine Maße – beim Fokussieren nachholen
    if (textarea.dataset.autogrow === 'false' || textarea.getClientRects().length === 0) {
        return;
    }
    // die kurzzeitige Höhe "auto" darf die Seite nicht springen lassen
    const scroller = scrollParent(textarea);
    const top = scroller?.scrollTop;
    textarea.style.height = 'auto';
    const style = getComputedStyle(textarea);
    const border = parseFloat(style.borderTopWidth) + parseFloat(style.borderBottomWidth);
    const content = textarea.scrollHeight + border;
    const max = Math.max(window.innerHeight * MAX_SHARE, textarea.offsetHeight);
    textarea.style.height = `${Math.min(content, max)}px`;
    textarea.style.overflowY = content > max ? 'auto' : 'hidden';
    if (scroller && top !== undefined) {
        scroller.scrollTop = top;
    }
}

const growAll = () => document.querySelectorAll('textarea').forEach(grow);

document.addEventListener('input', (event) => {
    if (event.target instanceof HTMLTextAreaElement) {
        grow(event.target);
    }
});
document.addEventListener('focusin', (event) => {
    if (event.target instanceof HTMLTextAreaElement) {
        grow(event.target);
    }
});
for (const name of ['turbo:load', 'turbo:render', 'turbo:frame-load']) {
    document.addEventListener(name, growAll);
}
