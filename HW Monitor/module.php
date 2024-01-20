<?php
class HWMonitor extends IPSModule
{
    // ... (bestehender Code bleibt unverändert)

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');

        // Timer mit einer Standard-Intervallzeit von 5 Minuten erstellen (300 Sekunden)
        $this->RegisterTimer("UpdateDataTimer", 300, 'HWM_UpdateData($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // ... (bestehender Code bleibt unverändert)
    }

    public function UpdateData()
    {
        // Diese Methode wird vom Timer aufgerufen und kann für die Aktualisierung der Daten verwendet werden
        $this->Log("Updating data...");

        // Füge hier den Code für die Aktualisierung der Daten hinzu, ähnlich wie in der ApplyChanges-Methode
        // ...

        $this->Log("Data updated successfully.");
    }
}
