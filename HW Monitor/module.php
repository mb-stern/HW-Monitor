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

        // Alle vorhandenen Variablen speichern
        $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
        $existingVariableIDs = [];
        foreach ($existingVariables as $existingVariableID) 
        {
            $existingVariableIDs[] = IPS_GetObject($existingVariableID)['ObjectIdent'];
        }


        $existingObjects = IPS_GetChildrenIDs($this->InstanceID);
        $newObjectIDs = [];




        // Schleife für die ID-Liste
        foreach ($idListe as $idItem) 
        {
            $gesuchteId = $idItem['id'];



            /// Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

            // Kategorie für diese ID erstellen, falls noch nicht vorhanden
            $categoryName = $foundValues['Text'][0];
            $categoryID = @IPS_GetObjectIDByName($categoryName, $this->InstanceID);
            $this->SendDebug("Kategorie geprüft", "Kategorie mit ID: ".$categoryID." und Name: ".$categoryName."", 0);
            if ($categoryID === false) 
            {
                // Kategorie erstellen, wenn sie nicht existiert oder kein Kategorieobjekt ist
                $categoryID = IPS_CreateCategory();
                IPS_SetName($categoryID, $categoryName);
                IPS_SetParent($categoryID, $this->InstanceID);
                $this->SendDebug("Kategorie erstellt", "Die Kategorie wurde erstellt: ".$categoryID."", 0);
            }


            // Variablen anlegen und einstellen für die gefundenen Werte
            $counter = 0;

            // Prüfe auf das Vorhandensein der Schlüssel 'Text', 'id', 'Min', 'Max', 'Value', 'Type'
            $requiredKeys = ['Text', 'id', 'Min', 'Max', 'Value', 'Type'];
            
            
            foreach ($requiredKeys as $searchKey) 
            {
                if (!array_key_exists($searchKey, $foundValues)) 
                {
                    continue; // Schlüssel nicht vorhanden, überspringen
                }

                foreach ($foundValues[$searchKey] as $gefundenerWert) 
                {
                    $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                    $variablePosition = $gesuchteId * 10 + $counter;

                    $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $categoryID);
                    if ($variableID === false) 
                    {
                        // Variable erstellen
                        if (in_array($searchKey, ['Min', 'Max', 'Value'])) 
                        {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), ($this->getVariableProfileByType($foundValues['Type'][0])), $variablePosition);

                            // Ersetzungen für Float-Variablen anwenden
                            $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                        } 
                        elseif ($searchKey === 'id') 
                        {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        } 
                        elseif ($searchKey === 'Text' || $searchKey === 'Type') 
                        {
                            $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        }

                        // Setze das Elternobjekt
                        IPS_SetParent($variableID, $categoryID);
                    } 
                    else 
                    {
                        // Variable bereits vorhanden, Wert aktualisieren
                        $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;
                        SetValue($variableID, $convertedValue);
                        //Debug senden
                        $this->SendDebug("Variable aktualisiert", "Variabel-ID: ".$variableID.", Position: ".$variablePosition.", Name: ".$searchKey.", Wert: ".$convertedValue."", 0);
                    }

                    $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;

                    SetValue($variableID, $convertedValue);

                    //Debug senden
                    $this->SendDebug("Variable aktualisiert", "Variabel-ID: ".$variableID.", Position: ".$variablePosition.", Name: ".$searchKey.", Wert: ".$convertedValue."", 0);

                    $counter++;

                }
            }
        }

       // Lösche nicht mehr benötigte Variablen und Kategorien
$existingIds = array_column($idListe, 'id');
foreach ($idListe as $idItem) {
    $gesuchteId = $idItem['id'];

    // Suche nach Werten für die gefundenen IDs
    $foundValues = [];
    $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

    // Kategorie für diese ID erstellen, falls noch nicht vorhanden
    $categoryName = $foundValues['Text'][0];
    $categoryID = @IPS_GetObjectIDByName($categoryName, $this->InstanceID);
    $this->SendDebug("Löschfunktion", "Prüfung der Kategorie mit id: ".$gesuchteId." mit Kategorie-ID: ".$categoryID." und Name: ".$categoryName."", 0);
    if (!in_array($gesuchteId, $existingIds)) {
        // Wenn die ID nicht mehr vorhanden ist, lösche die Kategorie und alle Variablen darin
        if ($categoryID !== false) {
            // Alle Variablen innerhalb der Kategorie löschen
            $categoryChildren = IPS_GetChildrenIDs($categoryID);
            foreach ($categoryChildren as $childID) {
                IPS_DeleteVariable($childID);
                //Debug senden
                $this->SendDebug("Variable gelöscht", "ID: $childID", 0);
            }
            // Kategorie selbst löschen
            IPS_DeleteCategory($categoryID);
            //Debug senden
            $this->SendDebug("Kategorie gelöscht", $categoryName, 0);
        }
    }
}

    }
}
