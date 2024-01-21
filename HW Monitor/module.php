<?php
class HWMonitor extends IPSModule //development
{
    private $updateTimer;

    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
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

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '192.168.178.76');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        //$this->SetTimerInterval('Update', 0);

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
    $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

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
}
