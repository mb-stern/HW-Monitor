# Libre Hardware Monitor Modul für IP-Symcon

Folgende Module beinhaltet das HW Monitor Repository:

- __HW Monitor__ ([Dokumentation](HW%20Monitor))  

Dieses Modul greift die JSON-Daten des **Libre Hardware Monitor** ab und stellt ausgewählte Sensorwerte als Variablen in **IP-Symcon** bereit.
Die Sensoren werden automatisch ausgelesen und können komfortabel über Checkboxen im Konfigurationsformular ausgewählt werden.

Jeder aktivierte Sensor erzeugt **vier Variablen** in IP-Symcon:
**Pfad**, **Minimum**, **Istwert**, **Maximum**.