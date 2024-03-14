<?php
class HWMonitor extends IPSModule
{
    private $updateTimer;

    protected function searchValueForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                $this->searchValuesForId($jsonArray, $searchId, $foundValues);
                break;
            } elseif (is_array($value)) {
                $this->searchValueForId($value, $searchId, $foundValues);
            }
        }
    }

    protected function searchValuesForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if (is_array($value)) {
                $this->searchValuesForId($value, $searchId, $foundValues);
            } else {
                $foundValues[$key][] = $value;
            }
        }
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');

        // Benötigte Variablen erstellen
        if (!IPS_VariableProfileExists("HW.Clock")) {
			IPS_CreateVariableProfile("HW.Clock", 2); //2 für Float
			IPS_SetVariableProfileValues("HW.Clock", 0, 5000, 1); //Min, Max, Schritt
            IPS_SetVariableProfileDigits("HW.Clock", 0); //Nachkommastellen
			IPS_SetVariableProfileText("HW.Clock", "", " Mhz"); //Präfix, Suffix
		}
        if (!IPS_VariableProfileExists("HW.Data")) {
			IPS_CreateVariableProfile("HW.Data", 2);
			IPS_SetVariableProfileValues("HW.Data", 0, 100, 1);
            IPS_SetVariableProfileDigits("HW.Data", 1);
			IPS_SetVariableProfileText("HW.Data", "", " GB");
		}
        if (!IPS_VariableProfileExists("HW.Temp")) {
			IPS_CreateVariableProfile("HW.Temp", 2);
			IPS_SetVariableProfileValues("HW.Temp", 0, 100, 1);
            IPS_SetVariableProfileDigits("HW.Temp", 0);
			IPS_SetVariableProfileText("HW.Temp", "", " °C");
		}
        if (!IPS_VariableProfileExists("HW.Fan")) {
			IPS_CreateVariableProfile("HW.Fan", 2);
			IPS_SetVariableProfileValues("HW.Fan", 0, 1000, 1);
            IPS_SetVariableProfileDigits("HW.Fan", 0);
			IPS_SetVariableProfileText("HW.Fan", "", " RPM");
		}
        if (!IPS_VariableProfileExists("HW.Rate")) {
			IPS_CreateVariableProfile("HW.Rate", 2);
			IPS_SetVariableProfileValues("HW.Rate", 0, 1000, 1);
            IPS_SetVariableProfileDigits("HW.Rate", 0);
			IPS_SetVariableProfileText("HW.Rate", "", " KB/s");
		}
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        // Überprüfen, ob die erforderlichen Konfigurationsparameter gesetzt sind
        $ipAddress = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');
        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);

        // Überprüfe, ob die IP-Adresse nicht die Muster-IP ist
        if ($ipAddress == '0.0.0.0') 
        {
            $this->SendDebug("Konfiguration", "IP-Adresse ist nicht konfiguriert", 0);   
            $this->LogMessage("IP-Adresse ist nicht konfiguriert", KL_ERROR);
        } 
        else 
        {
            // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
            $this->Update();
        }
    }

    protected function getVariableProfileByType($type)
    {
        switch ($type) {
            case 'Clock':
                return 'HW.Clock';
            case 'Load':
                return '~Progress';
            case 'Temperature':
                return 'HW.Temp';
            case 'Fan':
                return 'HW.Fan';
            case 'Voltage':
                return '~Volt';
            case 'Power':
                return '~Watt';
            case 'Data':
                return 'HW.Data';
            case 'Level':
                return '~Progress';
            case 'Throughput':
                return 'HW.Rate';
            // Weitere Zuordnungen für andere 'Type'-Werte hier ergänzen
            default:
                return '';
        }
    }

    // Nicht mehr benötigte Objekte löschen
foreach ($existingObjects as $existingObjectID) 
{
    // Objektinformationen abrufen
    $existingObject = IPS_GetObject($existingObjectID);
    $objectName = $existingObject['ObjectName'];
    $objectType = $existingObject['ObjectType'];

    // Überprüfen, ob das vorhandene Objekt noch benötigt wird
    $keepObject = false;
    foreach ($newObjectIDs as $newObjectID) 
    {
        if ($existingObjectID == $newObjectID) 
        {
            $keepObject = true;
            break;
        }
    }

    // Wenn nicht mehr benötigt, löschen Sie das Objekt
    if (!$keepObject) 
    {
        if ($objectType == 0) 
        {
            // Variable löschen
            $this->UnregisterVariable($existingObjectID);
        } 
        elseif ($objectType == 1) 
        {
            // Kategorie löschen
            IPS_DeleteCategory($existingObjectID);
        }
    }
}

}
}
