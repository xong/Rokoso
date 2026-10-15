/*
 * Übersetzte Texte für automatisches Speichern und die Sicherung im Browser: #form-texts in base.html.twig
 * (nur für angemeldete Personen; data-user trennt die Sicherungen verschiedener Konten im selben Browser).
 */
export const formText = (key) => document.getElementById('form-texts')?.dataset[key] ?? '';
