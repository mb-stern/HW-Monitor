<?php
class HWMonitor extends IPSModule
{
    private $updateTimer;

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');

        // Benötigte Variablenprofile erstellen
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

    public function Update()
    {
        // Libre Hardware Monitor abfragen
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        //Debug senden
        $this->SendDebug("Verbindungseinstellung", "".$this->ReadPropertyString('IPAddress')." : ".$this->ReadPropertyInteger('Port')."", 0);

        // Gewählte ID's abfragen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Alle vorhandenen Variablen speichern für die Löschfunktion
        $categories = IPS_GetChildrenIDs($this->InstanceID);
        $existingVariables = [];

        foreach ($categories as $categoryID) 
        {
            $variablesInCategory = IPS_GetChildrenIDs($categoryID);
            $existingVariables = array_merge($existingVariables, $variablesInCategory);
        }

        // Konvertiere das Array in einen lesbaren String für die Debug Ausgabe
        $variablesString = implode(", ", $existingVariables);
        $this->SendDebug("Löschfunktion 1", "Speicherung der Variabel-ID: " . $variablesString, 0);


        $existingVariableIDs = [];
        foreach ($existingVariables as $existingVariableID) 
        {
            $existingVariableIDs[] = IPS_GetObject($existingVariableID)['ObjectIdent'];
            // Konvertiere das Array in einen lesbaren String für die Debug Ausgabe
            $variablesString = implode(", ", $existingVariableIDs);
            $this->SendDebug("Löschfunktion 2", "Speicherung des Variablennamens: " . $variablesString, 0);
        }

        // Schleife für die ID-Liste
        $this->SendDebug("Test 1", "Start der Schleife ID-Liste", 0);
        foreach ($idListe as $idItem) 
        {
            $gesuchteId = $idItem['id'];
            $this->SendDebug("Test 2", "Schleife ID-Liste mit gesuchter ID gestartet: ".$gesuchteId."", 0);

            /// Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

            // Kategorie für diese ID erstellen, falls noch nicht vorhanden
            $categoryName = $foundValues['Text'][0];
            $categoryID = @IPS_GetObjectIDByName($categoryName, $this->InstanceID);
            $this->SendDebug("Kategorie geprüft", "Kategorie vor Prüfung auf Vorhandensein mit Kategorie-ID: ".$categoryID." und Name: ".$categoryName."", 0);
            if ($categoryID === false) 
            {
                // Kategorie erstellen, wenn sie nicht existiert oder kein Kategorieobjekt ist
                $categoryID = IPS_CreateCategory();
                IPS_SetName($categoryID, $categoryName);
                IPS_SetParent($categoryID, $this->InstanceID);
                $this->SendDebug("Kategorie erstellt", "Die Kategorie wurde erstellt mit Kategorie-ID: ".$categoryID." und Name: ".$categoryName."", 0);
            }

            $counter = 0;

            // Prüfe auf das Vorhandensein der Schlüssel 'Text', 'id', 'Min', 'Max', 'Value', 'Type'
            $requiredKeys = ['Text', 'id', 'Min', 'Max', 'Value', 'Type'];

            // Durchlaufe die gefundenen Werte für den aktuellen Schlüssel
            foreach ($requiredKeys as $searchKey)
            foreach ($foundValues[$searchKey] as $gefundenerWert) 
            {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                $variablePosition = $gesuchteId * 10 + $counter;
            
                // Variablen erstellen
                $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $categoryID);
                if ($variableID === false) 
                {
                    if (in_array($searchKey, ['Min', 'Max', 'Value', 'id'])) 
                    {
                        $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
            
                        // Ersetzungen für Float-Variablen anwenden
                        $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                    } 
                    elseif ($searchKey === 'Text' || $searchKey === 'Type') 
                    {
                        $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                    }
                        
                    // Setze die Kategorie als Elternobjekt
                    IPS_SetParent($variableID, $categoryID);
                } 
            
                $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;
                SetValue($variableID, $convertedValue);
                //Debug senden
                $this->SendDebug("Variable aktualisiert", "Erstellen oder aktualisieren von Ident: ".$variableIdentValue.", Name: ".ucfirst($searchKey).", Position: ".$variablePosition."", 0);
            
                $counter++;
            }
        }
        // Funktion zum rekursiven Durchlaufen aller Elemente
        function searchVariableInTree($parentId, $variableToRemove)
        {
            $objectIds = IPS_GetChildrenIDs($parentId);
            foreach ($objectIds as $objectId) {
                // Überprüfen, ob das Objekt eine Variable ist
                if (IPS_ObjectGetType($objectId) == 2 /* Variable */) {
                    // Überprüfen, ob die Variable den zu entfernenden Identifikator hat
                    if (IPS_GetObject($objectId)['ObjectIdent'] == $variableToRemove) {
                        return $objectId; // Variable gefunden
                    }
                } elseif (IPS_ObjectGetType($objectId) == 3 /* Kategorie */) {
                    // Wenn es sich um eine Kategorie handelt, rekursiv in dieser Kategorie suchen
                    $foundObjectId = searchVariableInTree($objectId, $variableToRemove);
                    if ($foundObjectId !== false) {
                        return $foundObjectId; // Variable in der Kategorie gefunden
                    }
                }
            }
            return false; // Variable nicht gefunden
        }

        // Lösche nicht mehr benötigte Variablen
        foreach ($existingVariableIDs as $variableToRemove) {
            // Versuche, die Variable mit der Identifikation zu finden
            $variableIDToRemove = searchVariableInTree($this->InstanceID, $variableToRemove);
            
            if ($variableIDToRemove !== false) {
                $this->UnregisterVariable($variableIDToRemove);
                // Debug senden
                $this->SendDebug("Löschfunktion", "Die Variable ".$variableToRemove." wurde gelöscht", 0);
            } else {
                // Debug senden, wenn die Variable nicht gefunden wurde
                $this->SendDebug("Löschfunktion", "Die Variable ".$variableToRemove." konnte nicht gefunden werden.", 0);
            }
        }
    }

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
}   