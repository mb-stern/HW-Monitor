# HW Monitor
Beschreibung des Moduls.

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

* Abfrage des Libre Hardware Monitors mit der id-Nummer des gewünschten Wertes

### 2. Voraussetzungen

- IP-Symcon ab Version 7.0
- Installierter Libre Hardware Monitor https://github.com/LibreHardwareMonitor/LibreHardwareMonitor

### 3. Software-Installation

* Über den Module Store kann das 'HW Monitor'-Modul noch nicht installiert werden.
* Download des Moduls via Module Store https://github.com/mb-stern/HW-Monitor

### 4. Einrichten der Instanzen in IP-Symcon

 Unter 'Instanz hinzufügen' kann das 'Hardware Monitor'-Modul mithilfe des Schnellfilters gefunden werden.  
	- Weitere Informationen zum Hinzufügen von Instanzen in der [Dokumentation der Instanzen](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

__Konfigurationsseite__:

Name     | Beschreibung
-------- | ------------------
IP-Adresse |     IP-Adresse des Rechners auf dem der Libre Hardware Monitor läuft
Port       |  Port des Rechners (Standard ist 8085). Der Port muss in der Firewall geöffnet sein
Intervall  |  Intervall für das Update der Werte
Überwachte ID's|  Hier die gewünschten ID's der Werte. Diese Wert sind ersichtlich im JSON im Browser unter diesem (Beispiel)-Pfad http://192.168.178.76:8085/data.json

### 5. Statusvariablen und Profile

Die Statusvariablen/Kategorien werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

#### Statusvariablen

Name   | Typ     | Beschreibung
------ | ------- | ------------
Id     |   Float      |	ID des Wertes
Text   |     String   |	Name des Wertes
Min    |   Float      |	Minimum des Wertes
Value  |     Float    |	Aktueller Wert
Max    |   Float      |	Maximum des Wertes


#### Profile
aktuell werden keine Profile erstellt

Name   | Typ
------ | -------
       |
       |

### 6. WebFront

Die Funktionalität, die das Modul im WebFront bietet.

### 7. PHP-Befehlsreferenz

`boolean HW_Update(integer $InstanzID);`
Aktualisierung der Daten.

Beispiel:
`HW_Update(12345);`
