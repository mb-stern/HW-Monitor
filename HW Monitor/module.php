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

        // Schleife für die ID-Liste
        foreach ($idListe as $idItem) 
        {
            $gesuchteId = $idItem['id'];



            // Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

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

                    $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                    if ($variableID === false) 
                    {
                        if ($searchKey === 'Text') 
                        {
                            $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        }
                        
                        elseif ($searchKey === 'id') 
                        {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            IPS_SetParent($variableID, ucfirst($searchKey);
                        } 
                        
                        elseif ($searchKey === 'Type') 
                        {
                            $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        }
                        elseif (in_array($searchKey, ['Min', 'Max', 'Value'])) 
                        {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), ($this->getVariableProfileByType($foundValues['Type'][0])), $variablePosition);

                            // Ersetzungen für Float-Variablen anwenden
                            $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                        } 
                    } 
                    else 
                    {
                        $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                        if ($keyIndex !== false) 
                        {
                            unset($existingVariableIDs[$keyIndex]);
                        }
                    }

                    $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;

                    SetValue($variableID, $convertedValue);

                    //Debug senden
                    $this->SendDebug("Variable aktualisiert", "Variabel-ID: ".$variableID.", Position: ".$variablePosition.", Name: ".$searchKey.", Wert: ".$convertedValue."", 0);

                    $counter++;

                }
            }
        }

        // Lösche nicht mehr benötigte Variablen
        foreach ($existingVariableIDs as $variableToRemove) 
        {
            $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
            if ($variableIDToRemove !== false)
            {
                $this->UnregisterVariable($variableToRemove);
                //Debug senden
                $this->SendDebug("Variable gelöscht", "".$variableToRemove."", 0);
            }
        }
    }
}
