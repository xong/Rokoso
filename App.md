# Kollaborationssoftware

## Projektanforderungen
- Basis ist Symfony.
- Das Projekt soll OpenSource sein und auch nur freie Bibliotheken verwenden.
- Die Weboberfläche soll mit Tailwind gestaltet werden.
- Die Weboberfläche soll responsive sein und auch als Mobile-App funktionieren.
- Die Oberfläche soll modern und kompakt sein.

## Projektbeschreibung:
1. Benutzer sollen sich anmelden können inkl. dazugehöriger Funktionen: Passwort vergessen, Abmelden, Profilverwaltung.
2. Im Profil soll man sein Profilbild, seinen Name, seine E-Mail-Adresse und sein Passwort ändern können.
3. Die Standardansicht ist ein dreispaltiger (20%, 25%, 55%)E-Mail-Client: Navigation, E-Mails, Detailansicht.
4. In der Navigationsspalte werden wir im Laufe des Projekts weitere Punkte hinzufügen. Der erste Punkt ist "E-Mails".
5. Die Navigation kann nach links komprimiert werden. Dann bleiben nur die Icons sichtbar. Bei Hover werden die Namen der Navigationselemente und eventuelle Untermenüs sichtbar.
6. Der E-Mail-Client listet E-Mails einer Organisation auf. Die Verwaltung von Organisationen erfolgt über einen Menüpunkt "Organisationen".
7. Eine Organisation hat einen Namen, ein Bild bzw. Logo, eine Farbe, eine Beschreibung und eine Liste von Mitgliedern.
8. Man kann Organisationen erstellen, Mitglieder hinzufügen und entfernen und Mitgliederrollen verwalten.
9. Über eine Organisation können E-Mail-Konten verwaltet werden. Dazu gehören alle Angaben, die zum Empfang und Versand von E-Mails über diese Konten erforderlich sind.
10. Die E-Mails der E-Mail-Konten werden in der E-Mail-Ansicht angezeigt und ggf. mit den Farben der Organisation markiert.
11. E-Mails können angezeigt werden. Dabei sollen Betreff, Datum, Versender, Empfänger, Anhänge und der Inhalt angezeigt werden.
12. Man kann zwischen der Text- und der HTML-Version der E-Mails wechseln.
13. Neben dem Inhalt der E-Mail soll es eine Kommentarspalte geben, in der man Kommentare zu den E-Mails schreiben kann, die mit Autor, Datum und Text angezeigt werden. Das Eingabefeld soll unterhalb bestehender Kommentare angezeigt werden.
14. E-Mails können einer oder mehreren verantwortlichen Nutzern zugeordnet werden. Es gibt dabei eine Selectbox mit Multiselect und einen Button, mit der man sich selbst die E-Mail zuordnen kann.
15. Es gibt oberhalb der mittleren Spalte eine Filtermöglichkeit, mit der man die E-Mails nach verschiedenen Kriterien filtern kann, z. B. nach E-Mails, die noch keinen verantwortlichen Nutzer haben oder E-Mails, die in meiner Verantwortlichkeit liegen oder E-Mails eines bestimmten Projekts.
16. Die E-Mail-Liste in der mittleren Spalte gruppiert die E-Mails nach Datum: Heute, Gestern, Letzte 7 Tage, Letzte 30 Tage, Älter.
17. In der Liste sieht man Versender, Datum, Betreff und einen kurzen Ausschnitt des Inhalts. Außerdem wird über Icons angezeigt, ob eine E-Mail noch keinen verantwortlichen Nutzer hat, ob sie in meiner Verantwortlichkeit liegt oder ob sie einen Anhang hat. Diese Ansicht soll kompakt und leicht zu bedienen sein.
18. Beim Hovern über eine E-Mail erscheint bei jeder E-Mail eine Funktionsleiste: E-Mail zu Projekt zuordnen, E-Mail als verantwortlich markieren, E-Mail beantworten, E-Mail weiterleiten, E-Mail in den Papierkorb verschieben.
19. Projekte sind ein eigener Menüpunkt und haben einen Namen, ein optionales Bild, eine Farbe, eine Beschreibung und können einer Organisation zugeordnet werden.
20. In der "E-Mail zu Projekt zuordnen"-Funktion kann man über eine Liste ein Projekt auswählen, um diesem die E-Mail zuzuordnen. Die Liste ist über ein Suchfeld gefiltert und kann nach Projektnamen gesucht werden. Das Gleiche gilt für die Verantwortlichen.
21. Mitglieder einer Organisation können folgende Rollen haben: Administrator und Nutzer.
22. Administratoren können neue Benutzer per E-Mail-Adresse in Organisationen einladen.
23. Administratoren können die Projekte einer Organisation verwalten.
24. Administratoren können die Rollen von Benutzern in Organisationen verwalten.
25. Der "E-Mails"-Menüpunkt hat als Unterpunkte: Eingang (das ist die Standardauswahl), Ausgang (alle gesendeten E-Mails), Papierkorb (alle gelöschten E-Mails), Neue E-Mail (eine neue E-Mail senden).
26. Ein weiterer Menüpunkt ist "Kalender": hier werden Termine und Aufgaben angezeigt. In der mittleren Spalte wird ein Kalender mit dem aktuellen Monat angezeigt. Bei Klick auf einen Tag wird in der Detailansicht eine Tagesübersicht angezeigt. Bei Klick auf die Kalenderwoche wird eine Wochenübersicht angezeigt. Man kann mittels eine kleinen Navigationsleiste zum vorherigen und nächsten Monat wechseln. Dazwischen gibt es einen klickbaren Monat mit Jahreszahl, über den man effektiver zu anderen Monaten/Jahren wechseln kann.
27. Tages- und Wochenübersicht sind eine Liste, die von oben nach unten geht und die Uhrzeiten des Tages/der Tage am Anfang anzeigt. Ganz- oder mehrtägige Termine oder Aufgaben werden oberhalb dieser Liste angezeigt.
28. Termine und Aufgaben können einem Projekt zugeordnet werden.
29. Über der Detailansicht gibt es Buttons zum Anlegen von Terminen und Aufgaben.
30. Termine und Aufgaben können als verantwortlich markiert oder anderen zugewiesen werden.
31. Termine und Aufgaben können als erledigt markiert werden.
32. Termine und Aufgaben können gelöscht werden.
33. Termine und Aufgaben können einmalig oder wiederkehrend angelegt werden. Sie haben: Titel, Projekt, Ort, Dauer (Von-Bis bzw. "ganztägig"), Beschreibung, Verantwortlich, Teilnehmer, URL, "erledigt".
34. Es gibt eine interne Nachrichtenfunktion, die Benutzern ermöglicht, Nachrichten zu senden und zu empfangen. Nachrichtenempfänger können Benutzer, Projekte oder Organisationen sein.
35. Nachrichten werden in der E-Mail-Ansicht angezeigt und genau wie E-Mails behandelt.
36. Es gibt einen Menüpunkt "Kontakte". Kontakte sollen alle denkbaren Felder haben, die aber übersichtlich und leicht zu bedienen sind.
37. Wenn eine E-Mail von einem Kontakt kommt, soll das Bild des Kontakts oder ein Icon mit den Initialen des Kontakts angezeigt werden.