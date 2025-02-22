# Libre Hardware Monitor Modul für IP-Symcon
Dieses Modul greift die JSON Daten des Libre Harware Monitor ab und liefert die gewünscheten Werte als Variablen in IP-Symcon.
Die gewünschten Werte können im Browser unter diesem (Beispiel)-Pfad http://192.168.178.76:8085/data.json lokalisiert und dann im Modul mit der id-Nummer eingetragen werden.
Es muss darauf geachtet werden, dass keine ID's von ganzen Gruppen hinzugefügt werden. Diese führt zu unkontollierter Erstellung von Variablen. Es werden vier Variablen pro gewählter ID erstellt

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionen](#8-versionen)

### 1. Funktionsumfang

* Abfrage des Libre Hardware Monitors mit der id-Nummer und Ausgabe der gewünschten Werte in Variablen.
* Die IDs der Werte werden im Objektbaum Faktor 10 als Objektnummer angezeigt, um eine Sortierung zu erreichen. Ebenfalls ist über den Präfix ID XX eine übersichtliche Strukturierung vorhanden

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- Installierter Libre Hardware Monitor https://github.com/LibreHardwareMonitor/LibreHardwareMonitor.

### 3. Software-Installation

* Über den Module Store kann das 'Hardware Monitor'-Modul installiert werden.
* Download des Moduls auch über den Module Control https://github.com/mb-stern/HW-Monitor

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Hardware Monitor'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
IP-Adresse 		|  IP-Adresse des Rechners auf dem der Libre Hardware Monitor läuft
Port       		|  Port des Rechners (Standard ist 8085). Der Port muss in der Firewall geöffnet sein
Intervall  		|  Intervall für das Update der Werte
Überwachte ID's	|  Hier die gewünschten ID's der Werte. Diese Wert sind ersichtlich im JSON im Browser unter diesem (Beispiel)-Pfad http://192.168.178.76:8085/data.json

![2024-03-18 18_46_24-IP-Symcon Verwaltungskonsole](https://github.com/mb-stern/HW-Monitor/assets/95777848/c8472a43-d642-40ff-8edf-531ed633da82)

![2024-03-18 18_38_21-Entwicklung — IP-Symcon Verwaltungskonsole](https://github.com/mb-stern/HW-Monitor/assets/95777848/90f3d0f4-7684-4b4c-ac52-76152d864dbf)

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
ID XX - Name    |   String   |	Name des Wertes
ID XX - Minimum |   Float    |	Minimum des Wertes
ID XX - Maximum |   Float    |	Aktueller Wert
ID XX - Istwert |   Float    |	Maximum des Wertes


#### Profile

Name   | Typ
------ | -------
HW.Fan    | Float
HW.Clock  | Float
HW.Temp   | Float
HW.Data   | Float
HW.Rate   | Float

### 6. WebFront

Anzeige der gewünschten Variabeln oder Grafiken in der Visualisierung.

### 7. PHP-Befehlsreferenz

`boolean HW_Update(integer $InstanzID);`
Aktualisierung der Daten.

Beispiel:
`HW_Update(12345);`

### 8. Versionen

Version 1.8 (22.12.2024)
* Anpassung Modulname
* Anpassung Readme mit geänderter URL

Version 1.7 (19.12.2024)
* Der Name des Wertes wird wieder angezeigt.

Version 1.6 (07.07.2024)
* Spenden-Button hinzugefügt.
* Dokumentationslink hinzugefügt.

Version 1.5 (05.05.2024)
* Der Fehler wurde behoben, dass alle Variablen gelöscht wurden, wenn die Verbindung zum Hardwaremonitor unterbrochen wurde. So bleiben nun auch die aufgezeichneten Variablen erhalten.

Version 1.4 (18.03.2024)
* Die Variablen werden nun mit dem Präfix (ID XX) übersichtlicher dargestellt. Die ID muss dazu entfernt und wieder hinzugefügt werden, um durchgehend die neue Struktur zu erhalten
* Es werden nur noch vier Variabeln pro ID angelegt (vorher sechs)

Version 1.3 (17.02.2024)
* Anpassung des Codes um die Store Kompatibilät zu erlangen
* Anpassung von Debug und Fehlermeldung

Version 1.2 (05.02.2024)
* Debug hinzugefügt
* Muster IP-Adresse wird nicht mehr standardmässig geladen bei Installation des Moduls. Dies führte bei der Installation zu Fehlermeldungen.
* Fenster für ID's im Konfigurationsformular vergrössert.

Version 1.1 (23.01.2024)
* Variabelprofile werden erstellt und zugeordnet

Version 1.0 (21.1.2024)
* Initiale Version
