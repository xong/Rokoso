# Projektplan – Coop

> **Lebendes Dokument.** Wird nach jedem Arbeitsschritt aktualisiert.
> Anforderungen: [`App.md`](../App.md) · Entscheidungen: [`ENTSCHEIDUNGEN.md`](ENTSCHEIDUNGEN.md) · Offene Fragen: [`OFFENE-FRAGEN.md`](OFFENE-FRAGEN.md)

## Wo stehen wir? (zum Wiedereinstieg)

| | |
|---|---|
| **Aktuelle Phase** | Ausbau 2 nach Systemvergleich (Phasen 20–34) |
| **Nächster Schritt** | Phase 28 (Öffentliche Mitwirkung) |
| **Letzte Sitzung** | 2026-10-04: Phase 27 (Kontakte mit Institution/Funktion, Verteiler pro Organisation mit Filter und Empfängervorschlag, Rundschreiben einzeln adressiert mit Fehlerprotokoll, Kontakt-Verlauf aus Nachrichten und Sitzungen, Notizen mit @-Erwähnungen, vCard-Import/-Export; Entscheidung 55); davor Phase 26 (Entwürfe mit automatischem Speichern, Kollisionshinweis, Adressvorschläge, Signaturen pro Konto, Textbausteine pro Organisation, Markdown-Werkzeugleiste mit Versand HTML + Text, Senden rückgängig über Warteschlange, Kopie in den IMAP-Gesendet-Ordner; Cron `app:mail:outbox`; Entscheidung 54); davor Phase 25 (Mitgliedschaft mit Funktion und Amtszeit, ehemalige Mitglieder ohne Zugriff, Gastzugang pro Projekt, Übergabe beim Amtswechsel, Projektleitung, Projekte archivieren, Handbuch mit Seitenbaum und Versionen; Entscheidung 53); davor Phase 24 (Abstimmungen offen/geheim, Entscheidung/Auswahl/Terminfindung, Umlaufbeschluss → Beschlussliste, gewählter Termin → Sitzung bzw. Kalender, Abstimmungen in Forenthemen und Sitzungen; Entscheidung 52); davor Phase 23 (Sitzungen mit Tagesordnung, TOP-Vorschlägen, Einladung per E-Mail mit .ics, Anwesenheit/Beschlussfähigkeit, Protokoll mit Freigabe und Druckansicht, Beschlussliste, Aufgaben aus TOPs, Stimmrecht pro Mitgliedschaft; Entscheidung 51); davor Phase 22 (Aufgaben ohne Pflichtfrist, Status offen/in Arbeit/erledigt, Liste Meine/Alle/Projekt, Board, Aufgabe aus Nachricht/Forenthema, Projektseite mit Terminen und Aufgaben; Entscheidung 50); davor Phase 21 (Benachrichtigungen mit Glocke, @-Erwähnungen, Beobachten, E-Mail sofort/täglich/aus, Web-Push; Cron `app:notify`; Entscheidung 49); davor Phase 20 (Status-Modell, Rückgängig, Wiedervorlage, Verlauf, Mehrfachauswahl, Threads, Regeln; Entscheidung 48); davor 2026-10-03: Dunkelmodus (Hell/Dunkel/System im Profil, Entscheidung 38); davor 2026-10-02: App.md 38–48 umgesetzt (Phasen 13–18: Drag&Drop, Hover-Leiste, Kalender-Layout, Projekt-Kommentare, Dateien, persönliche Ablage, Forum), je Phase ein Commit; 62 Tests grün; Sichtprüfung im Browser steht noch aus (Browser-Erweiterung war nicht verbunden) |

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
- [x] 14.1 Mittlere Spalte: Mini-Monat mit KW und Punkten, darunter „Demnächst“
- [x] 14.2 Detail: Woche (Standard) / Tag mit gleich hohen Stunden-Slots, Einträge positioniert, ganztägige oben

## Phase 15 – Projekt-Kommentare (43)
- [x] 15.1 `Comment` verallgemeinert (Nachricht, Projekt, Datei); Kommentarspalte beim Projekt

## Phase 16 – Dateien (41, 42)
- [x] 16.1 `Folder` (Hierarchie, Organisation/Projekt) und `File` (Speicher wie Anhänge)
- [x] 16.2 Menüpunkt „Dateien“: Ordner anlegen/umbenennen/löschen, hochladen (auch Drag&Drop), herunterladen
- [x] 16.3 Datei-Detail mit Projektzuordnung und Kommentarspalte

## Phase 17 – Persönliche Ablage (44)
- [x] 17.1 Dateien und E-Mail-Anhänge in die eigene Ablage legen (Verweis, keine Kopie)
- [x] 17.2 Beim Schreiben einer E-Mail Dateien aus der Ablage anhängen

## Phase 18 – Forum (45)
- [x] 18.1 Bereiche (Hierarchie, Organisation/Projekt), Themen, Beiträge
- [x] 18.2 Markdown mit Bildern (Drag&Drop) und Dateianhängen
- [x] 18.3 Bearbeiten/Löschen, ungelesene Themen, Projektzuordnung

## Phase 19 – Dunkelmodus
- [x] 19.1 Farbschema im Profil wählbar (Hell, Dunkel, System)
- [x] 19.2 Dunkle Farbpalette über CSS-Variablen, Flächen `bg-surface`

---

## Ausbau 2 – Ergebnis des Systemvergleichs (2026-10-03)
Grundlage: Vergleich mit Front/Help Scout/Zammad (gemeinsames Postfach), Nextcloud (Groupware), Discourse (Forum), OpenSlides/Loomio (Gremienarbeit), SchoolFox/Sdui (Elternkommunikation). Entscheidungen des Projektinhabers: #39–#47 in `ENTSCHEIDUNGEN.md`. Reihenfolge: Gremienarbeit früh, weil sie den Großteil der Arbeitszeit ausmacht.

## Phase 20 – Postfach: Status-Modell
- [x] 20.1 Status **offen/erledigt** pro Nachricht (wer, wann); Eingang = offen, unabhängig vom Projekt (ersetzt Entscheidung 34; Projektzuordnung bleibt); Ordner „Erledigt“; neue Antwort in der Unterhaltung öffnet wieder
- [x] 20.2 **Rückgängig-Meldung** (Toast) für erledigt, Papierkorb, Projekt
- [x] 20.3 **Wiedervorlage** („Snooze“ bis Datum) mit Ordner „Wiedervorlage“; fällige tauchen wieder im Eingang auf
- [x] 20.4 **Verlauf** pro Nachricht (zugewiesen, Projekt, erledigt, beantwortet, weitergeleitet)
- [x] 20.5 **Mehrfachauswahl** in der Liste mit Sammelaktionen (erledigt, Papierkorb, Projekt, zuweisen, gelesen)
- [x] 20.6 **Unterhaltungen (Threads)** über `In-Reply-To`/`References`, im Detail als Verlauf
- [x] 20.7 **Regeln** pro Konto (Absender/Betreff enthält … → Projekt, Verantwortliche, erledigt)

## Phase 21 – Benachrichtigungen
- [x] 21.1 `Notification` + **Glocke** in der Navigation (zugewiesen, erwähnt, Kommentar, neues Thema/Beitrag in beobachtetem Bereich, Termin/Aufgabe fällig, Abstimmung offen, Kontaktanfrage)
- [x] 21.2 **@-Erwähnungen** in Kommentaren und Forum (Autovervollständigung)
- [x] 21.3 **Beobachten** von Forenbereichen/Themen und Projekten
- [x] 21.4 **E-Mail-Benachrichtigung** sofort/täglich/aus pro Person (Cron `app:notify`)
- [x] 21.5 **Web-Push** für die PWA (VAPID, minishlink/web-push)

## Phase 22 – Aufgaben
- [x] 22.1 Aufgaben ohne Pflichtdatum (Frist optional), **Aufgabenliste** „Meine“/„Alle“/pro Projekt
- [x] 22.2 **Board** (offen / in Arbeit / erledigt)
- [x] 22.3 **Aufgabe aus Nachricht oder Forenthema** erstellen (mit Verweis)
- [x] 22.4 Projektseite zeigt Termine und Aufgaben

## Phase 23 – Gremienarbeit: Sitzungen und Beschlüsse
- [x] 23.1 **Sitzung** (Gremium = Organisation, Termin, Ort/Videolink, Status geplant/eingeladen/durchgeführt/Protokoll freigegeben)
- [x] 23.2 **Tagesordnung**: TOPs sortierbar, Verantwortliche, Dauer, Anlagen; TOP-Vorschläge der Mitglieder
- [x] 23.3 **Einladung** per E-Mail (Mitglieder + externe Gäste) mit Tagesordnung und .ics; Warnung bei unterschrittener Einladungsfrist (pro Organisation einstellbar)
- [x] 23.4 **Anwesenheit** und Beschlussfähigkeit (Quorum pro Organisation, zählt Stimmberechtigte)
- [x] 23.5 **Protokoll** je TOP, Freigabe, Druckansicht (Browser „Als PDF speichern“)
- [x] 23.6 **Beschlüsse** mit Abstimmungsergebnis; **Beschlussliste** (durchsuchbar, filterbar nach Jahr/Projekt)
- [x] 23.7 Aufgaben aus dem Protokoll (Verantwortliche, Frist) → Aufgabenliste

## Phase 24 – Abstimmungen und Terminfindung
- [x] 24.1 **Stimmrecht** als Kennzeichen pro Mitgliedschaft (Standard: ja)
- [x] 24.2 **Abstimmung** (Ja/Nein/Enthaltung oder Auswahl, Einfach-/Mehrfachwahl), Frist, Ergebnis; **offen (namentlich) oder geheim** (gespeichert wird nur, dass jemand abgestimmt hat)
- [x] 24.3 **Umlaufbeschluss** (Abstimmung mit Frist außerhalb einer Sitzung → Beschlussliste)
- [x] 24.4 **Terminfindung** (Vorschläge ja/vielleicht/nein; gewählter Termin → Kalender bzw. Sitzung)
- [x] 24.5 Abstimmungen in Forenthemen und Sitzungen

## Phase 25 – Mitgliedschaften: Amtszeiten, Gäste, Übergabe, Wissen
- [x] 25.1 Mitgliedschaft mit **Funktion** (Vorsitz, Kasse, Schriftführung …) und **Amtszeit** bis; abgelaufene werden „ehemalig“ (kein Zugriff mehr)
- [x] 25.2 **Gastzugang pro Projekt**: Rolle „Gast“ sieht nur freigegebene Projekte (Forum, Dateien, Termine, Aufgaben), keine E-Mails, Kontakte oder Mitgliederdaten
- [x] 25.3 **Übergabe**: Zuweisungen, Aufgaben, Projekte einer Person an eine andere übertragen
- [x] 25.4 **Wissen/Handbuch** pro Organisation: Seiten (Markdown, Hierarchie, Versionen)
- [x] 25.5 **Projekte archivieren** (abgeschlossen, aus Auswahllisten ausgeblendet)

## Phase 26 – Postfach: Schreiben
- [x] 26.1 **Entwürfe** (automatisch gespeichert, Ordner „Entwürfe“)
- [x] 26.2 **Kollisionshinweis** „X schreibt gerade eine Antwort“ (offener Entwurf zur selben Nachricht)
- [x] 26.3 **Empfänger-Autovervollständigung** aus Kontakten und Kontaktgruppen
- [x] 26.4 **Signaturen** (pro Person und Konto) und **Textbausteine** (pro Organisation)
- [x] 26.5 **Senden rückgängig** (kurze Verzögerung mit Abbrechen)
- [x] 26.6 **Rich-Text** per Markdown-Editor mit Werkzeugleiste; Versand als HTML + Text
- [x] 26.7 Gesendete Mails zusätzlich per IMAP in den **Gesendet-Ordner** des Servers kopieren (pro Konto abschaltbar)

## Phase 27 – Verteiler und Kontakte
- [x] 27.1 Kontakt: **Institution** (z. B. Schule) und **Funktion** (Schulleitung, Elternbeirat, Schulamt …)
- [x] 27.2 **Kontaktgruppen** (Verteiler)
- [x] 27.3 **Rundschreiben** an Gruppen über ein Org-Konto (einzeln adressiert, keine offenen Empfängerlisten), im Ausgang nachvollziehbar
- [x] 27.4 **Kontakt-Verlauf**: Nachrichten, Termine, Notizen zum Kontakt
- [x] 27.5 **vCard** importieren/exportieren

## Phase 28 – Öffentliche Mitwirkung (ohne Konto, je Funktion in der Organisationsverwaltung an-/abschaltbar)
- [ ] 28.1 Öffentliche Seite pro Organisation (eigene URL, einbettbar), Spamschutz **konfigurierbar** durch Org-Admins: unsichtbar (Lockfeld, Mindestzeit, Drosselung) und optional E-Mail-Bestätigung
- [ ] 28.2 **Kontaktformular** → landet im Eingang (Konto wählbar): Schule/Einrichtung, Dateianhänge (begrenzt), **Themenauswahl nur wenn Themen aktiviert** (Thema → Projekt/Verantwortliche), **Eingangsbestätigung nur wenn vom Absender gewünscht**, Datenschutzhinweis
- [ ] 28.3 **Öffentliche Umfragen** (Elternbefragung per Link; Fragetypen Auswahl/Mehrfach/Skala/Freitext; anonym oder mit Kontakt; Auswertung + CSV)
- [ ] 28.4 **Veranstaltungs-Anmeldung** (Termin öffentlich, Anmeldung mit Platzbegrenzung, Teilnehmerliste)
- [ ] 28.5 **Verteiler-Selbstanmeldung** (Bestätigungslink, jederzeit abmeldbar → Kontaktgruppe)
- [ ] 28.6 **Öffentliche Infoseite** (Neuigkeiten, ausgewählte Beschlüsse, öffentliche Termine)

## Phase 29 – Kalender-Ausbau
- [ ] 29.1 **Einzeltermine einer Serie** ändern oder absagen
- [ ] 29.2 **iCal-Abo** (persönlicher geheimer Link) für Handy-Kalender
- [ ] 29.3 **Erinnerungen** vor Terminen/Fristen (über Benachrichtigungen)
- [ ] 29.4 **Einladungen an Externe** als .ics per E-Mail

## Phase 30 – Dateien-Ausbau und Ablage
- [ ] 30.1 **Vorschau** (PDF/Bilder) im Datei-Detail
- [ ] 30.2 **Versionen** beim erneuten Hochladen
- [ ] 30.3 **Freigabelinks** für Externe mit Ablaufdatum
- [ ] 30.4 **Ablage als „Merken“**: Stern an Dateien, Anhängen, Nachrichten, Forenthemen; beim Schreiben aus allen Dateien anhängen
- [ ] 30.5 **PWA-Teilen-Ziel** (Dateien aus anderen Apps an Coop teilen)

## Phase 31 – Start, Suche, Tastatur
- [ ] 31.1 **Startseite „Heute“**: meine offenen Nachrichten, fällige Aufgaben, nächste Termine/Sitzungen, offene Abstimmungen, neue Forenthemen, Erwähnungen
- [ ] 31.2 **Globale Suche** über Nachrichten, Dateien, Forum, Kontakte, Termine, Beschlüsse, Wissen
- [ ] 31.3 **Befehlspalette** (Strg+K) und **Tastenkürzel** mit Übersicht (`?`)
- [ ] 31.4 **Ungelesen-Zähler** an Eingang/Ordnern; Hinweis auf neue Nachrichten ohne Neuladen

## Phase 32 – Navigation und Usability
- [ ] 32.1 **Interne Nachrichten nur noch an Personen**; Gruppen-Nachrichten werden zu Forenthemen (Migration bestehender Daten)
- [ ] 32.2 **„Verfassen“-Knopf** (E-Mail, Direktnachricht, Forenthema, Termin, Aufgabe) statt Menüpunkten
- [ ] 32.3 Bereich **„Verwaltung“** (Organisationen, Mitglieder, E-Mail-Konten, Regeln, Textbausteine, öffentliche Mitwirkung); Navigation neu gruppiert
- [ ] 32.4 **Mobile Navigationsleiste** unten; Wischgesten in der Nachrichtenliste
- [ ] 32.5 **Turbo Frames/Streams** für Aktionen ohne Neuladen der Seite
- [ ] 32.6 **Leere Zustände** mit Handlungsaufforderung, **Einrichtungsassistent**, Hilfetexte

## Phase 33 – Sicherheit und Datenschutz
- [ ] 33.1 **Zwei-Faktor-Anmeldung** (TOTP, scheb/2fa-bundle)
- [ ] 33.2 **Überall abmelden** (Sitzungen ungültig machen)
- [ ] 33.3 **Konto löschen** und **Datenexport** (DSGVO)
- [ ] 33.4 **Löschfristen**: Papierkorb nach X Tagen, Nachrichten und öffentliche Einsendungen nach X Jahren (pro Organisation)
- [ ] 33.5 **Sicherheitsprotokoll** (Anmeldungen, Rollenänderungen, Löschungen)
- [ ] 33.6 **Plattform-Admin** (Benutzer sperren/löschen, Organisationen einsehen)
- [ ] 33.7 Muster für das Verzeichnis der Verarbeitungstätigkeiten in `docs/BETRIEB.md`

## Phase 34 – Betrieb und Qualität
- [ ] 34.1 **Abrufstatus** pro Konto (letzter Abruf, Fehler) für Admins sichtbar, Hinweis bei Fehlern
- [ ] 34.2 Abruf beim Öffnen der App (gedrosselt) zusätzlich zum Cron
- [ ] 34.3 **Browser-Tests** mit symfony/panther für Kernabläufe
- [ ] 34.4 **Docker-Image** für den Betrieb
- [ ] 34.5 Barrierefreiheit: automatische Prüfung (axe) + Screenreader-Stichprobe (NVDA, manuell durch den Projektinhaber)

**Bewusst nicht geplant:** eigener Chat/Video (Videolink bei Termin/Sitzung genügt), gemeinsames Bearbeiten von Office-Dokumenten, Newsletter mit Öffnungs-Tracking, vollwertiges CRM, automatische Übersetzung von Eltern-Mails (Datenschutz).

---

## Ideen-Parkplatz
Neue Ideen, die noch keiner Phase zugeordnet sind:

- Weitere UI-Sprachen (Texte liegen bereits in Übersetzungsdateien)
- Weitere Mitwirkungsmöglichkeiten für Eltern und Pädagog*innen (Vorschläge willkommen)
