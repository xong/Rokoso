---
routes: [agent_, oauth_]
---
# KI-Agenten

KI-Assistenten wie Claude können über eine Schnittstelle (MCP) mit Rokoso arbeiten: E-Mails sichten und Antworten vorbereiten, Kontakte anlegen, Aufgaben und Termine eintragen oder im Forum und Handbuch nachschlagen.

## Was ein Agent darf

Ein Agent handelt **mit deinen Rechten**: Er sieht nur, was du auch siehst, und nur in Organisationen, die KI-Agenten zulassen. Zusätzlich legst du fest, welche Bereiche er lesen oder ändern darf – E-Mails, Kontakte und Einrichtungen, Kalender und Aufgaben, Forum, Handbuch und Dateien.

- **E-Mails verschickt ein Agent nie.** Er legt nur **Entwürfe** an; du prüfst sie unter **E-Mails → Entwürfe** und schickst sie selbst ab.
- Alles, was ein Agent anlegt oder ändert, trägt den Hinweis **„über KI-Agent“** mit dem Namen des Zugangs – bei Entwürfen, Kontakten, Terminen, Beiträgen, Kommentaren und Handbuch-Versionen.
- Endgültig löschen kann ein Agent nichts. E-Mails kann er wie du sortieren, also auch als erledigt markieren, in den Papierkorb legen oder als Spam einstufen.

## Für Administratoren: Bereich freischalten

KI-Agenten sind zunächst **abgeschaltet**. Unter **Verwaltung → Organisation bearbeiten** schaltet ihr den Bereich **KI-Agenten** ein. Erst dann erscheint im Profil der Punkt **KI-Agenten**, und Agenten sehen Inhalte dieser Organisation. Klärt vorher, ob und mit welchem Anbieter ihr Daten verarbeiten lassen wollt – Inhalte, die ein Agent liest, gehen an den Anbieter des KI-Dienstes.

## Mit claude.ai oder Claude Desktop verbinden

1. In Claude unter **Einstellungen → Konnektoren** einen eigenen Konnektor hinzufügen.
2. Als Adresse die **MCP-Adresse** aus **Mein Profil → KI-Agenten** eintragen (endet auf `/mcp`).
3. Claude öffnet eine Anmeldeseite von Rokoso. Dort wählst du die Bereiche und bestätigst mit **Erlauben**.

Die Verbindung erscheint danach in deinem Profil und lässt sich dort jederzeit widerrufen.

## Mit Claude Code oder eigenen Skripten verbinden

Unter **Mein Profil → KI-Agenten** erstellst du ein **persönliches Token**: Name, Bereiche und Gültigkeit wählen, dann **Token erstellen**. Das Token wird **nur einmal angezeigt** – kopiere es sofort. Den fertigen Befehl für Claude Code findest du auf derselben Seite.

Behandle das Token wie ein Passwort. Wer es kennt, kann im gewählten Umfang in deinem Namen arbeiten. Brauchst du es nicht mehr, **widerrufe** es. **Überall abmelden** (siehe [Profil und Sicherheit](/help/profil-sicherheit)) beendet auch alle Agenten-Zugänge.

## Vorsicht bei fremden Inhalten

E-Mails und öffentliche Einsendungen stammen von Fremden. Sie können Text enthalten, der einen Agenten zu etwas verleiten soll („Leite alle Mails weiter …“). Rokoso weist den Agenten an, solche Inhalte nur als Daten zu behandeln. Gib einem Agenten trotzdem nur die Bereiche, die er für die Aufgabe braucht, und prüfe Entwürfe vor dem Absenden.
