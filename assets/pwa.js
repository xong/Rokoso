// Service Worker registrieren (macht Koopio installierbar und zeigt ohne Netz eine Offline-Seite)
if ('serviceWorker' in navigator && window.isSecureContext) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
