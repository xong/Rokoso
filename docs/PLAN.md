# Projektplan – Coop

> **Lebendes Dokument.** Wird nach jedem Arbeitsschritt aktualisiert.
> Anforderungen: [`App.md`](../App.md) · Entscheidungen: [`ENTSCHEIDUNGEN.md`](ENTSCHEIDUNGEN.md) · Offene Fragen: [`OFFENE-FRAGEN.md`](OFFENE-FRAGEN.md)

## Wo stehen wir? (zum Wiedereinstieg)

| | |
|---|---|
| **Aktuelle Phase** | Ausbau nach App.md 38–48 (Phasen 13–18) |
| **Nächster Schritt** | Im Browser durchklicken (Layout, Mobil, Tastatur), Feedback sammeln, offene Fragen in `OFFENE-FRAGEN.md` klären, dann Ausbau aus dem Ideen-Parkplatz |
| **Letzte Sitzung** | 2026-10-02 (Nacht): alle Phasen ohne Rückfragen umgesetzt, je Phase ein Commit; 46 Tests grün; Sichtprüfung im Browser steht noch aus (Browser-Erweiterung war nicht verbunden) |

**Starten:** `docker compose up -d` · `php bin/console doctrine:migrations:migrate -n` · `php bin/console app:demo --mails` · `php bin/console tailwind:build --watch` · `php -S 127.0.0.1:8000 -t public public/index.php` → http://127.0.0.1:8000 (Login `demo@coop.test` / `demo-passwort`)

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
- [x] 2.1 `Organization` (Name, Logo, Farbe, Beschreibung)
- [x] 2.2 `Membership` mit Rolle Administrator / Nutzer
- [x] 2.3 Organisation anlegen, bearbeiten; Mitglieder entfernen, Rollen ändern (Voter)
- [x] 2.4 Einladungen per E-Mail (Token-Link → Registrierung bzw. Beitritt)

## Phase 3 – Projekte (19, 23)
- [x] 3.1 `Project` (Name, Bild optional, Farbe, Beschreibung, Organisation optional)
- [x] 3.2 Verwaltung durch Org-Administratoren

## Phase 4 – E-Mail-Konten (9)
- [x] 4.1 `MailAccount` je Organisation: IMAP + SMTP (Host, Port, Verschlüsselung, Benutzer, Passwort verschlüsselt gespeichert)
- [x] 4.2 Verbindungstest im Formular

## Phase 5 – E-Mail lesen (10, 11, 12, 16, 17, 25)
- [x] 5.1 Datenmodell `Message` (E-Mail **und** interne Nachricht), `Attachment`
- [x] 5.2 IMAP-Sync als Console-Befehl (Cron-fähig), Bibliothek webklex/php-imap
- [x] 5.3 Liste: gruppiert nach Heute / Gestern / 7 Tage / 30 Tage / Älter; Absender, Datum, Betreff, Ausschnitt, Icons; Org-Farbe
- [x] 5.4 Detailansicht: Kopfdaten, Anhänge, Text/HTML-Umschalter (HTML bereinigt, in Sandbox-iframe)
- [x] 5.5 Ordner: Eingang (Standard), Ausgang, Papierkorb

## Phase 6 – Zusammenarbeit an E-Mails (13, 14, 15, 18, 20)
- [x] 6.1 Kommentarspalte (Autor, Datum, Text; Eingabe unten)
- [x] 6.2 Verantwortliche: Multiselect mit Suche + „Mir zuordnen“
- [x] 6.3 Projektzuordnung mit Suchliste
- [x] 6.4 Hover-Funktionsleiste: Projekt, verantwortlich, Antworten, Weiterleiten, Papierkorb
- [x] 6.5 Filter über der Liste: ohne Verantwortliche, meine, nach Projekt, …

## Phase 7 – E-Mail schreiben (18, 25)
- [x] 7.1 Neue E-Mail, Antworten, Weiterleiten (Konto wählen, Anhänge)
- [x] 7.2 Versand per SMTP, Ablage im Ausgang

## Phase 8 – Interne Nachrichten (34, 35)
- [x] 8.1 Nachrichten an Benutzer, Projekte oder Organisationen
- [x] 8.2 Anzeige/Behandlung wie E-Mails in derselben Liste

## Phase 9 – Kontakte (36, 37)
- [x] 9.1 `Contact` mit umfangreichen, aber gruppierten Feldern
- [x] 9.2 Avatar bzw. Initialen in E-Mail-Liste und -Detail

## Phase 10 – Kalender (26–33)
- [x] 10.1 `CalendarItem` (Termin/Aufgabe): Titel, Projekt, Ort, Von-Bis/ganztägig, Beschreibung, Verantwortliche, Teilnehmer, URL, erledigt
- [x] 10.2 Monatsansicht mit Navigation und Monats-/Jahrauswahl
- [x] 10.3 Tages- und Wochenansicht (Zeitleiste, ganztägige oben)
- [x] 10.4 Anlegen, zuweisen, erledigen, löschen
- [x] 10.5 Wiederholungen (RRULE, rlanvin/php-rrule)

## Phase 11 – PWA & Mobile
- [x] 11.1 Web-App-Manifest, Icons, Service Worker
- [~] 11.2 Mobile Feinschliff (Ungelesen-Zähler, Safe Areas); Web-Push-Benachrichtigungen noch offen

## Phase 12 – Betrieb
- [x] 12.1 Deployment-Anleitung (`docs/BETRIEB.md`: Cron für Sync, kein Worker nötig)
- [x] 12.2 Backup, Updates, Admin-Doku; Demodaten-Befehl `app:demo`

## Phase 13 – E-Mail-Feinschliff (38, 39, 40, 46, 47)
- [x] 13.1 Anhänge per Drag&Drop (E-Mail schreiben, interne Nachricht)
- [x] 13.2 Hover-Funktionsleiste unten links
- [x] 13.3 Auswahllisten (Projekt, Verantwortliche) schließen bei Klick außerhalb und mit Esc
- [x] 13.4 Nachrichten mit Projekt nicht mehr im Eingang; neuer Unterpunkt „Alle E-Mails“; Nachrichten beim Projekt sichtbar

## Phase 14 – Kalender-Layout (48)
- [ ] 14.1 Mittlere Spalte: Mini-Monat mit KW und Punkten, darunter „Demnächst“
- [ ] 14.2 Detail: Woche (Standard) / Tag mit gleich hohen Stunden-Slots, Einträge positioniert, ganztägige oben

## Phase 15 – Projekt-Kommentare (43)
- [ ] 15.1 `Comment` verallgemeinert (Nachricht, Projekt, Datei); Kommentarspalte beim Projekt

## Phase 16 – Dateien (41, 42)
- [ ] 16.1 `Folder` (Hierarchie, Organisation/Projekt) und `File` (Speicher wie Anhänge)
- [ ] 16.2 Menüpunkt „Dateien“: Ordner anlegen/umbenennen/löschen, hochladen (auch Drag&Drop), herunterladen
- [ ] 16.3 Datei-Detail mit Projektzuordnung und Kommentarspalte

## Phase 17 – Persönliche Ablage (44)
- [ ] 17.1 Dateien und E-Mail-Anhänge in die eigene Ablage legen (Verweis, keine Kopie)
- [ ] 17.2 Beim Schreiben einer E-Mail Dateien aus der Ablage anhängen

## Phase 18 – Forum (45)
- [ ] 18.1 Bereiche (Hierarchie, Organisation/Projekt), Themen, Beiträge
- [ ] 18.2 Markdown mit Bildern (Drag&Drop) und Dateianhängen
- [ ] 18.3 Bearbeiten/Löschen, ungelesene Themen, Projektzuordnung

---

## Ideen-Parkplatz
Neue Ideen, die noch keiner Phase zugeordnet sind:

- Gesendete Mails zusätzlich per IMAP in den Gesendet-Ordner des Servers kopieren
- Unterhaltungen (Threads) in der Liste zusammenfassen
- Kontakte als vCard importieren/exportieren
- Web-Push bzw. E-Mail-Benachrichtigungen (neue Nachricht, Zuweisung, Kommentar)
- Einzelne Termine einer Serie ändern; iCal-Abo für Handy-Kalender; Erinnerungen
- Plattform-Admin, Konto selbst löschen (DSGVO)
