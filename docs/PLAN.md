# Projektplan – Coop

> **Lebendes Dokument.** Wird nach jedem Arbeitsschritt aktualisiert.
> Anforderungen: [`App.md`](../App.md) · Entscheidungen: [`ENTSCHEIDUNGEN.md`](ENTSCHEIDUNGEN.md) · Offene Fragen: [`OFFENE-FRAGEN.md`](OFFENE-FRAGEN.md)

## Wo stehen wir? (zum Wiedereinstieg)

| | |
|---|---|
| **Aktuelle Phase** | Phase 1 – Benutzer & Anmeldung |
| **Nächster Schritt** | 1.1 `User`-Entity + Migration; Layout-Abnahme im Browser (0.4) steht noch aus |
| **Letzte Sitzung** | 2026-10-02: Plan erstellt, Symfony 8 + Tailwind + Layout-Gerüst, Docker-DB läuft, Name „Coop“, offene Registrierung beschlossen |

**Starten:** `docker compose up -d` · `php bin/console tailwind:build --watch` · `php -S 127.0.0.1:8000 -t public public/index.php` → http://127.0.0.1:8000

Legende: `[ ]` offen · `[~]` in Arbeit · `[x]` erledigt · Nummern in Klammern = Punkte aus `App.md`

---

## Phase 0 – Fundament
Ziel: lauffähige, leere Anwendung mit Layout-Gerüst.

- [x] 0.1 PHP 8.5 lokal (Mindestversion 8.4), Symfony 8.0, Git, MIT-Lizenz, README
- [x] 0.2 Pakete: Doctrine, Twig, Security, Mailer, Messenger, Forms, Validator, AssetMapper, Turbo, Stimulus, Twig Components, UX Icons (Lucide), Tailwind 4.3 (Standalone-Binary)
- [x] 0.3 Datenbank: `compose.yaml` (Projekt `coop`) mit MariaDB 11.8 (Port 3307, DB/Benutzer `coop`) + Mailpit; Verbindung getestet
- [~] 0.4 Basis-Layout: 3 Spalten, einklappbare Navigation (Cookie `nav_collapsed`), Flyouts im eingeklappten Zustand, mobil Off-Canvas-Navigation + Liste/Detail einzeln – **Sichtprüfung im Browser steht aus**
- [x] 0.5 Qualität: PHPUnit (Smoke-Tests Navigation), PHP-CS-Fixer, PHPStan Level 8, `composer check`; UI-Texte in `translations/messages.de.yaml`

## Phase 1 – Benutzer & Anmeldung (1, 2)
- [x] 1.1 `User`-Entity (E-Mail, Name, Passwort-Hash, Profilbild, verifiziert) + Migration
- [x] 1.2 Registrierung mit E-Mail-Bestätigung (symfonycasts/verify-email-bundle); Login erst nach Bestätigung
- [x] 1.3 Login / Logout / „Angemeldet bleiben“; alle App-Seiten nur angemeldet; Login-Drosselung
- [x] 1.4 Passwort vergessen (symfonycasts/reset-password-bundle)
- [x] 1.5 Profil: Bild, Name, Passwort ändern; E-Mail ändern mit erneuter Bestätigung
- [x] 1.6 Benutzermenü in der Navigation (Avatar/Initialen, Profil, Abmelden)
- [x] 1.7 Systemmails gestaltet (Twig-Inline-CSS), lokal über Mailpit

## Phase 2 – Organisationen & Mitglieder (6, 7, 8, 21, 22, 24)
- [ ] 2.1 `Organization` (Name, Logo, Farbe, Beschreibung)
- [ ] 2.2 `Membership` mit Rolle Administrator / Nutzer
- [ ] 2.3 Organisation anlegen, bearbeiten; Mitglieder entfernen, Rollen ändern (Voter)
- [ ] 2.4 Einladungen per E-Mail (Token-Link → Registrierung bzw. Beitritt)

## Phase 3 – Projekte (19, 23)
- [ ] 3.1 `Project` (Name, Bild optional, Farbe, Beschreibung, Organisation optional)
- [ ] 3.2 Verwaltung durch Org-Administratoren

## Phase 4 – E-Mail-Konten (9)
- [ ] 4.1 `MailAccount` je Organisation: IMAP + SMTP (Host, Port, Verschlüsselung, Benutzer, Passwort verschlüsselt gespeichert)
- [ ] 4.2 Verbindungstest im Formular

## Phase 5 – E-Mail lesen (10, 11, 12, 16, 17, 25)
- [ ] 5.1 Datenmodell `Message` (E-Mail **und** interne Nachricht), `Attachment`
- [ ] 5.2 IMAP-Sync als Console-Befehl (Cron-fähig), Bibliothek webklex/php-imap
- [ ] 5.3 Liste: gruppiert nach Heute / Gestern / 7 Tage / 30 Tage / Älter; Absender, Datum, Betreff, Ausschnitt, Icons; Org-Farbe
- [ ] 5.4 Detailansicht: Kopfdaten, Anhänge, Text/HTML-Umschalter (HTML bereinigt, in Sandbox-iframe)
- [ ] 5.5 Ordner: Eingang (Standard), Ausgang, Papierkorb

## Phase 6 – Zusammenarbeit an E-Mails (13, 14, 15, 18, 20)
- [ ] 6.1 Kommentarspalte (Autor, Datum, Text; Eingabe unten)
- [ ] 6.2 Verantwortliche: Multiselect mit Suche + „Mir zuordnen“
- [ ] 6.3 Projektzuordnung mit Suchliste
- [ ] 6.4 Hover-Funktionsleiste: Projekt, verantwortlich, Antworten, Weiterleiten, Papierkorb
- [ ] 6.5 Filter über der Liste: ohne Verantwortliche, meine, nach Projekt, …

## Phase 7 – E-Mail schreiben (18, 25)
- [ ] 7.1 Neue E-Mail, Antworten, Weiterleiten (Konto wählen, Anhänge)
- [ ] 7.2 Versand per SMTP, Ablage im Ausgang

## Phase 8 – Interne Nachrichten (34, 35)
- [ ] 8.1 Nachrichten an Benutzer, Projekte oder Organisationen
- [ ] 8.2 Anzeige/Behandlung wie E-Mails in derselben Liste

## Phase 9 – Kontakte (36, 37)
- [ ] 9.1 `Contact` mit umfangreichen, aber gruppierten Feldern
- [ ] 9.2 Avatar bzw. Initialen in E-Mail-Liste und -Detail

## Phase 10 – Kalender (26–33)
- [ ] 10.1 `CalendarItem` (Termin/Aufgabe): Titel, Projekt, Ort, Von-Bis/ganztägig, Beschreibung, Verantwortliche, Teilnehmer, URL, erledigt
- [ ] 10.2 Monatsansicht mit Navigation und Monats-/Jahrauswahl
- [ ] 10.3 Tages- und Wochenansicht (Zeitleiste, ganztägige oben)
- [ ] 10.4 Anlegen, zuweisen, erledigen, löschen
- [ ] 10.5 Wiederholungen (RRULE, rlanvin/php-rrule)

## Phase 11 – PWA & Mobile
- [ ] 11.1 Web-App-Manifest, Icons, Service Worker
- [ ] 11.2 Mobile Feinschliff, ggf. Web-Push-Benachrichtigungen

## Phase 12 – Betrieb
- [ ] 12.1 Deployment-Anleitung (Cron für Sync, Messenger)
- [ ] 12.2 Backup, Updates, Admin-Doku

---

## Ideen-Parkplatz
Neue Ideen, die noch keiner Phase zugeordnet sind:

- *(leer)*
