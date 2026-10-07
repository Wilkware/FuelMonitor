# ⛽ Spritmonitor (Fuel Monitor)

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/spritmonitor/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.0.20261007-orange.svg?style=flat-square)](https://github.com/Wilkware/FuelMonitor)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/FuelMonitor/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/FuelMonitor/actions)

Der Spritmonitor berechnet den Spritverbrauch und die Kosten eines Fahrzeugs und hilft bei der Verwaltung von Service- und TÜV-Terminen.

## Inhaltsverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

Der Spritmonitor ist etwas mehr als ein reiner Verbrauchsmonitor:

* Berechnung von Durchschnittsverbrauch (l/100 km) und Kosten (€/100 km) nach der **Volltankmethode**
* Unterstützung von Erstbefüllung, Teil- und Volltankungen – Teilbetankungen werden bis zur nächsten Volltankung aufsummiert
* Nachträgliche Erfassung von Tankvorgängen (Heute, Gestern, Vorgestern bzw. frei wählbares Datum)
* Automatische Plausibilitätsprüfung von Menge, Literpreis und Rechnungsbetrag
* Verwaltung der allgemeinen Fahrzeugdaten (Marke/Modell, Kennzeichen, Erstzulassung, Fahrzeugbild)
* Überwachung von TÜV- und Service-Fälligkeiten (nach Datum und/oder Kilometerstand) mit farbiger Statusanzeige
* Eigene Kachel für die TileVisu (HTML-SDK) mit Eingabemaske, Verbrauchsverlauf und Fälligkeitsanzeige
* Speicherung aller Tankdaten im Archiv für eigene Auswertungen und Statistiken
* Import & Export der Tankdaten als CSV-Datei zur Datensicherung

### 2. Voraussetzungen

* Symcon ab Version 8.1
* Archive Control (Standard-Instanz in Symcon) – die Tankdaten werden ausschließlich im Archiv gespeichert
* TileVisu für die Darstellung als Kachel

### 3. Installation

* Über den Module Store das Modul _Spritmonitor_ installieren.
* Alternativ über das Module Control folgende URL hinzufügen:
`https://github.com/Wilkware/FuelMonitor`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Spritmonitor_-Modul (Alias: _Benzinkostenrechner_, _Benzinverbrauchsrechner_) unter dem Hersteller '(Geräte)' aufgeführt.
* Pro Fahrzeug wird eine eigene Instanz angelegt.
* Ohne Archivierung kann das Modul keine Tankvorgänge speichern. Die Instanz bleibt deshalb __inaktiv__ (Status: _Archivierung ist nicht (korrekt) eingerichtet_), bis die Archivierung aktiviert ist.
* Dazu im Aktionsbereich der Konfiguration im Bereich _🗄️ Archivierung_ auf __Archivierung aktivieren__ klicken. Der Bereich ist automatisch aufgeklappt, solange die Archivierung fehlt. Eingerichtet wird die Archivierung der Statusvariablen _Kilometer_ (Aggregation: Zähler), _Liter_, _Preis_, _Durchschnittsverbrauch_ und _Kosten_ (Aggregation: Standard); danach ist die Instanz aktiv.
* Bei bestehenden Instanzen aus älteren Versionen ist die Instanz nach dem Update ggf. ebenfalls inaktiv, bis die Aggregation über den Button angepasst wurde. Die vorhandenen Archivdaten bleiben dabei erhalten.
* Ist ein Anfangs-Kilometerstand eingetragen, wird nach dem Aktivieren der Archivierung automatisch die Erstbefüllung angelegt.

__Konfigurationsseite__:

Einstellungsbereich:

> 🚗 Fahrzeugdaten ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Marke / Modell                                   | Bezeichnung des Fahrzeugs, wird optional in der Kachel angezeigt
Kennzeichen                                      | Nummernschild im Format `XX-YY 1234` (optional mit `E` oder `H`)
Erstzulassung                                    | Datum der Erstzulassung; daraus wird einmalig der erste TÜV-Termin (+3 Jahre) vorgeschlagen
Kilometerstand                                   | Anfangs-Kilometerstand (z. B. beim Gebrauchtkauf); wird bei leerem Archiv automatisch als Erstbefüllung eingetragen
Fahrzeugbild                                     | Medienobjekt (PNG, JPG, GIF, WEBP), das als Hintergrundbild der Kachel dient

> 🛠️ Service & TÜV ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
TÜV-Überwachung aktivieren                       | Legt die Variable _TÜV-Fälligkeitstermin_ an und zeigt den TÜV-Status in der Kachel
Erinnere X Tage vor Fälligkeit                   | Ab wie vielen Tagen vor dem Termin der Status auf _Warnung_ wechselt
Serviceerinnerung aktivieren                     | Aktiviert die Service-Überwachung
Nach Kilometerleistung verfolgen                 | Legt die Variable _Wartungsintervall_ (fälliger Kilometerstand) an
Erinnere X km vor Fälligkeit                     | Ab wie vielen Restkilometern der Status auf _Warnung_ wechselt; gerechnet wird mit dem zuletzt erfassten Kilometerstand
Nach Datum verfolgen                             | Legt die Variable _Wartungsdatum_ an
Erinnere X Tage vor Fälligkeit                   | Ab wie vielen Tagen vor dem Servicetermin der Status auf _Warnung_ wechselt

Sind Service nach Datum und nach Kilometer aktiv, zeigt die Kachel jeweils die dringendere Fälligkeit an.
Die Fälligkeiten selbst werden über die Statusvariablen oder direkt in der Kachel (Stift-Symbol ✎) gepflegt.

> ✨ Visualisierung ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Marken- und Modellbezeichnung anzeigen?          | Zeigt Marke/Modell vor dem Kennzeichen an (kann entfallen, wenn die Kachel schon so heißt)
Farbe (Normal)                                   | Statusfarbe, wenn die Fälligkeit noch nicht im Erinnerungszeitraum liegt
Farbe (Warnung)                                  | Statusfarbe innerhalb des Erinnerungszeitraums
Farbe (Kritisch)                                 | Statusfarbe, wenn der Termin bzw. Kilometerstand erreicht oder überschritten ist

> ⚙️ Erweiterte Einstellungen ...

Name                                                         | Beschreibung
------------------------------------------------------------ | -----------------------------------
Zeige Fehlermeldung wenn Datum in der Zukunft liegt!         | Einträge mit einem Datum in der Zukunft werden immer verworfen; die Option steuert nur, ob eine Meldung erscheint
Zeige Fehlermeldung wenn Kilometerstand abnimmt!             | Einträge mit kleinerem oder gleichem Kilometerstand werden immer verworfen; die Option steuert nur, ob eine Meldung erscheint
Erlaube Erstbefüllung nur beim ersten Speichern ...          | Aktiv (Standard): eine Erstbefüllung ist nur bei leerem Archiv möglich. Inaktiv: eine erneute Erstbefüllung **löscht alle bisherigen Archivdaten** der Instanz und startet neu

__Aktionsbereich__:

> 🗄️ Archivierung ...

Zeigt an, ob die Archivierung korrekt eingerichtet ist, und bietet den Button __Archivierung aktivieren__ (siehe oben).

> 🔄 Import & Export ...

Import und Export sind nur bei aktiver Archivierung verfügbar, sonst sind die Bedienelemente ausgegraut.

Name           | Beschreibung
-------------- | ---------------------------------
Datei          | CSV-Datei für den Import
Importieren    | Ersetzt __alle__ vorhandenen Tankdaten im Archiv durch den Inhalt der Datei (mit Sicherheitsabfrage)
Exportieren    | Lädt alle Tankdaten als CSV-Datei herunter (`fuelmonitor_<Kennzeichen>_<Datum>.csv`), dafür muss keine Datei ausgewählt werden

Aufbau der CSV-Datei (Trennzeichen `;`, Kopfzeile in der ersten Zeile):

Spalte   | Inhalt
-------- | ---------------------------------
Date     | Zeitpunkt der Betankung, z. B. `2026-02-02 08:30:00`
Type     | `0` = Erstbefüllung, `1` = Teilbetankung, `2` = Volltankung
Mileage  | Kilometerstand
Liters   | getankte Liter
Price    | Preis je Liter
Average  | Verbrauch in l/100 km
Costs    | Kosten in €/100 km

_Hinweise:_

* Die Art der Betankung wird nicht archiviert. Beim Export wird sie aus den Daten abgeleitet (erster Eintrag = Erstbefüllung, Verbrauch > 0 = Volltankung, sonst Teilbetankung).
* Verbrauch und Kosten werden beim Import unverändert übernommen, nicht neu berechnet. Der Bezugspunkt für die nächste Volltankung wird aus den importierten Daten wiederhergestellt.
* Die Datei kann z. B. mit Excel bearbeitet werden. Beim Import werden auch `,` als Trennzeichen sowie Dezimalkomma und deutsches Datumsformat (`02.02.2026 08:30`) erkannt.
* Die TÜV- und Service-Fälligkeiten sind nicht Teil der Datei.

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Ident                 | Name                   | Typ     | Beschreibung
--------------------- | ---------------------- | ------- | ------------------------------
kilometers            | Kilometer              | Integer | Kilometerstand der Betankung
liters                | Liter                  | Float   | getankte Liter
price                 | Preis                  | Float   | Preis je Liter
average               | Durchschnittsverbrauch | Float   | verbrauchte Liter je 100 km (nur bei Volltankung, sonst 0)
costs                 | Kosten                 | Float   | Kosten je 100 km (nur bei Volltankung, sonst 0)
tuev_due_date         | TÜV-Fälligkeitstermin  | Integer | nächster TÜV-Termin (wenn aktiviert)
service_due_date      | Wartungsdatum          | Integer | nächster Servicetermin (wenn aktiviert)
service_due_mileage   | Wartungsintervall      | Integer | Kilometerstand, bei dem der nächste Service fällig ist (wenn aktiviert)

_Hinweis:_ Tankvorgänge vom aktuellen Tag werden ganz normal gesetzt und dabei archiviert.
Nachträglich erfasste Tankvorgänge (z. B. von gestern) werden mit ihrem Datum direkt ins Archiv geschrieben und **nicht** in die Variable übernommen.
Die Statusvariablen _Kilometer_ bis _Kosten_ zeigen daher nicht zwingend den zuletzt erfassten Tankvorgang – maßgeblich ist immer das Archiv.

__Berechnung (Volltankmethode)__:

Betankung      | Wirkung
-------------- | ------------------------------
Erstbefüllung  | Setzt den Bezugspunkt (Kilometerstand), es wird noch kein Verbrauch berechnet
Teilbetankung  | Speichert die Werte, die getankten Liter werden bis zur nächsten Volltankung aufsummiert
Volltankung    | Verbrauch = (Liter seit letzter Volltankung inkl. Teilbetankungen × 100) / gefahrene km; Kosten = Verbrauch × Literpreis

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable               | Darstellung   | Werte
---------------------- | ------------- | ------------------------------
Kilometer              | Wertanzeige   | km
Liter                  | Wertanzeige   | l (2 Nachkommastellen)
Preis                  | Wertanzeige   | € (3 Nachkommastellen)
Durchschnittsverbrauch | Wertanzeige   | l/100km (2 Nachkommastellen)
Kosten                 | Wertanzeige   | €/100km (2 Nachkommastellen)
TÜV-Fälligkeitstermin  | Datum/Uhrzeit | Datum
Wartungsdatum          | Datum/Uhrzeit | Datum
Wartungsintervall      | Werteingabe   | km

### 7. Visualisierung

Das Modul bringt eine eigene Kachel für die TileVisu mit (HTML-SDK). Dazu einfach die Instanz in der Visualisierung verlinken.

Die Kachel bietet:

* Fahrzeugbild als Hintergrund, Kennzeichen und optional Marke/Modell
* TÜV- und Service-Status als farbige Badges (Normal/Warnung/Kritisch) mit Resttagen bzw. Restkilometern; am Fälligkeitstag erscheint „heute fällig“, danach „überfällig“
* Korrektur der Fälligkeiten über das Stift-Symbol (✎)
* Kennzahlen des letzten Eintrags (Kilometerstand, Verbrauch, Kosten) sowie den Verbrauchsverlauf als Diagramm und Tabelle
* Eingabemaske für neue Tankvorgänge: Datum, Art der Betankung, Kilometerstand, Menge, Literpreis und Rechnungsbetrag. Von Menge, Literpreis und Rechnung werden zwei eingegeben, der dritte Wert wird automatisch berechnet.

Alternativ können auch nur die Statusvariablen direkt verlinkt werden – dann bitte den Hinweis unter [Statusvariablen](#user-content-5-statusvariablen) beachten.

### 8. Befehlsreferenz

Das Modul stellt keine eigenen öffentlichen Funktionen bereit. Die Bedienung erfolgt über die Kachel bzw. über `RequestAction` auf die Statusvariablen:

```php
// TÜV-Termin setzen (Unix-Zeitstempel)
RequestAction(IPS_GetObjectIDByIdent('tuev_due_date', $instanceId), strtotime('2027-05-31'));
// Service fällig bei Kilometerstand
RequestAction(IPS_GetObjectIDByIdent('service_due_mileage', $instanceId), 120000);
```

### 9. Versionshistorie

v3.0.20261007

* _NEU_: Import & Export der Tankdaten als CSV-Datei zur Datensicherung (nur bei aktiver Archivierung)
* _NEU_: Archivierung wird über einen Button im Aktionsbereich aktiviert, ohne Archivierung bleibt die Instanz inaktiv
* _NEU_: Kachel vollständig übersetzt (Deutsch/Englisch)
* _NEU_: Kilometerstand ist beim Speichern eines Tankvorgangs Pflicht
* _NEU_: Hinweis im Log, wenn das gewählte Fahrzeugbild nicht (mehr) vorhanden ist
* _FIX_: Fälligkeitsdatum wurde in der Kachel um einen Tag verschoben angezeigt bzw. gespeichert
* _FIX_: Verbrauchsverlauf konnte bei unveränderten Werten Lücken aufweisen, Laden des Verlaufs deutlich beschleunigt
* _FIX_: Teilbetankungen zeigten im Verlauf den Verbrauch der vorherigen Volltankung (Nullwerte wurden beim nächsten Speichern gelöscht)
* _FIX_: Service-Restkilometer berücksichtigen nachträglich erfasste Tankvorgänge
* _FIX_: TÜV/Service am Fälligkeitstag als „heute fällig“ statt „überfällig“
* _FIX_: Aggregation der Liter im Archiv auf „Standard“ korrigiert (einmalig über den Button anpassen)
* _FIX_: Darstellungen der Statusvariablen korrigiert (Wertanzeige statt Werteingabe)
* _FIX_: Sicherheit und Stabilität verbessert (Systemstart, ungültige Eingaben, fehlendes Archiv)

v2.0.20260730

* _NEU_: Eigene Kachel für die TileVisu (HTML-SDK) mit Eingabemaske und Verbrauchsverlauf
* _NEU_: Unterstützung von Teilbetankungen (Volltankmethode)
* _NEU_: Fahrzeugdaten (Marke/Modell, Kennzeichen, Erstzulassung, Fahrzeugbild)
* _NEU_: TÜV- und Service-Überwachung nach Datum und/oder Kilometerstand
* _NEU_: Darstellungen statt Profile

v1.1.20260714

* _FIX_: Umstellung auf `IPSModuleStrict`
* _FIX_: Bibliotheken und Formular überarbeitet

v1.0.20220424

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skripte und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
