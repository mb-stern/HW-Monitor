<?php
class HWMonitor extends IPSModule
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
        $this->RegisterPropertyInteger('UpdateInterval', 5);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 5, 'UpdateInterval(' . $this->InstanceID . ');');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('HW_Update') * 1000);
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
            foreach ($foundValues as $searchKey => $values) {
                if (in_array($searchKey, ['Text', 'id', 'Min', 'Max', 'Value'])) {
                    foreach ($values as $gefundenerWert) {
                        $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                        $variablePosition = $gesuchteId * 10 + $counter;

                        $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                        if ($variableID === false) {
                            if ($searchKey === 'Text') {
                                $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            } else {
                                $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            }
                        } else {
                            $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                            if ($keyIndex !== false) {
                                unset($existingVariableIDs[$keyIndex]);
                            }
                        }

                        $convertedValue = ($searchKey === 'Text') ? (string)$gefundenerWert : (float)$gefundenerWert;

                        SetValue($variableID, $convertedValue);
                        $counter++;
                    }
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
