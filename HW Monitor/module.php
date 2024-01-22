<?php

class HWMonitor extends IPSModule
{
    private $updateTimer;

    protected function Log($message)
    {
        IPS_LogMessage(__CLASS__, $message);
    }

    protected function searchValueById($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                $this->searchValuesById($jsonArray, $searchId, $foundValues);
                break;
            } elseif (is_array($value)) {
                $this->searchValueById($value, $searchId, $foundValues);
            }
        }
    }

    protected function searchValuesById($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if (is_array($value)) {
                $this->searchValuesById($value, $searchId, $foundValues);
            } else {
                $foundValues[$key][] = $value;
            }
        }
    }
    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '192.168.178.76');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');

        // Vordefinierte Zuordnungsliste für 'Type' zu Variablenprofilen
        $typeProfileMapping = [
            "Clock" => "HW.Clock",
            "Load"  => "HW.Load",
            // Füge weitere Zuordnungen hinzu, wenn nötig
        ];

         // Durchlaufe die IDListe und erstelle Variablen basierend auf dem 'Type'-Feld
$idListeString = $this->ReadPropertyString('IDListe');
$idListe = json_decode($idListeString, true);

foreach ($idListe as $idItem) {
    $gesuchteId = $idItem['id'];

    // Suche nach Werten für die gefundenen IDs
    $foundValues = [];
    $this->searchValueById($contentArray, $gesuchteId, $foundValues);

    // Prüfe, ob 'Type' vorhanden ist
    if (array_key_exists('Type', $foundValues)) {
        $type = $foundValues['Type'][0]; // Nehme den ersten gefundenen Wert für 'Type'

        // Überprüfe, ob 'Type' in der Zuordnungsliste vorhanden ist
        if (array_key_exists($type, $typeProfileMapping)) {
            $variableIdentValue = "Variable_" . ($gesuchteId * 10);
            $variablePosition = $gesuchteId * 10;

            $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
            if ($variableID === false) {
                $profileName = $typeProfileMapping[$type];

                // Erstelle die Variable nur, wenn ein gültiges Profil in der Zuordnungsliste vorhanden ist
                if (IPS_VariableProfileExists($profileName)) {
                    $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($type), $profileName, $variablePosition);
                } else {
                    $this->Log("Ungültiges Profil in der Zuordnungsliste - Profil: $profileName"); // Debug-Ausgabe
                }
            }
        } else {
            $this->Log("Profil für 'Type' nicht gefunden - Type: $type"); // Debug-Ausgabe
        }
    } else {
        $this->Log("Kein 'Type' gefunden - ID: $gesuchteId"); // Debug-Ausgabe
    }
}
}


    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
        $this->Update();
    }

    public function Update()
    {
        // Libre Hardware Monitor abfragen
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        // Gewählte ID's abfragen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Alle vorhandenen Variablen speichern
        $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
        $existingVariableIDs = [];
        foreach ($existingVariables as $existingVariableID) {
            $existingVariableIDs[] = IPS_GetObject($existingVariableID)['ObjectIdent'];
        }

        // Schleife für die ID-Liste
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            // Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueById($contentArray, $gesuchteId, $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            $counter = 0;

            // Prüfe auf das Vorhandensein der Schlüssel 'Text', 'id', 'Min', 'Max', 'Value', 'Type'
            $requiredKeys = ['Text', 'id', 'Min', 'Max', 'Value', 'Type'];
            foreach ($requiredKeys as $searchKey) {
                if (!array_key_exists($searchKey, $foundValues)) {
                    continue; // Schlüssel nicht vorhanden, überspringen
                }

                foreach ($foundValues[$searchKey] as $gefundenerWert) {
                    $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                    $variablePosition = $gesuchteId * 10 + $counter;

                    $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                    if ($variableID === false) {
                        if (in_array($searchKey, ['Min', 'Max', 'Value'])) {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);

                            // Ersetzungen für Float-Variablen anwenden
                            $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                        } elseif ($searchKey === 'id') {
                            $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        } elseif ($searchKey === 'Text' || $searchKey === 'Type') {
                            $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                        }

                        // Variablenprofil zuordnen basierend auf 'Type'
                        if ($searchKey === 'Type' && array_key_exists('Type', $foundValues)) {
                            $type = $foundValues['Type'][0];
                            $profileName = "HW.$type";
                            
                            IPS_LogMessage("HWMonitor", "Variable: $variableIdentValue, Profile: $profileName");

                            if (IPS_VariableProfileExists($profileName)) {
                                IPS_SetVariableCustomProfile($variableID, $profileName);
                            }
                        }
                    } else {
                        $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                        if ($keyIndex !== false) {
                            unset($existingVariableIDs[$keyIndex]);
                        }
                    }

                    $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;

                    SetValue($variableID, $convertedValue);
                    $counter++;
                }
            }
        }

        // Lösche nicht mehr benötigte Variablen
        foreach ($existingVariableIDs as $variableToRemove) {
            $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
            if ($variableIDToRemove !== false) {
                IPS_DeleteVariable($variableIDToRemove);
            }
        }
    }

    private function createVariableProfile($profileName, $minValue, $maxValue, $suffix)
    {
        if (!IPS_VariableProfileExists($profileName)) {
            IPS_CreateVariableProfile($profileName, 2);
            IPS_SetVariableProfileValues($profileName, $minValue, $maxValue, 1);
            IPS_SetVariableProfileAssociation($profileName, 0, $suffix, "", -1);
        }
    }
}

