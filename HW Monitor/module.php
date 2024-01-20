<?php
class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    protected function searchValueForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                // Die gesuchte ID wurde gefunden, jetzt die zugehörigen Werte suchen
                $this->searchValuesForId($jsonArray, $searchId, $foundValues);
                break; // Wir haben die ID gefunden, daher können wir die Suche beenden
            } elseif (is_array($value)) {
                // Rekursiv in den verschachtelten Arrays suchen
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

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Alle vorhandenen Variablen löschen
        $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($existingVariables as $existingVariableID) {
            IPS_DeleteVariable($existingVariableID);
        }

// Schleife für die ID-Liste
foreach ($idListe as $idItem) {
    $gesuchteId = $idItem['id'];

    // Suche nach Werten für die gefundenen IDs
    $foundValues = [];
    $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

    $this->Log("Found values for ID $gesuchteId: " . print_r($foundValues, true));

    // Variablen anlegen und einstellen für die gefundenen Werte
    $counter = 0; // Zähler für jede 'id' zurücksetzen
    foreach ($foundValues as $searchKey => $values) {
        if (in_array($searchKey, ['Id', 'Text', 'Min', 'Max', 'Value'])) {
            foreach ($values as $gefundenerWert) {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";

                // Hier die Methode RegisterVariableFloat oder RegisterVariableString verwenden
                if ($searchKey === 'Text') {
                    $variableID = @$this->GetIDForIdent($variableIdentValue);
                    if ($variableID === false) {
                        $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $gesuchteId * 10 + $counter);
                    }
                } else {
                    $variableID = @$this->GetIDForIdent($variableIdentValue);
                    if ($variableID === false) {
                        $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $gesuchteId * 10 + $counter);
                    }
                }

                // Konvertiere den Wert, wenn der Typ nicht übereinstimmt
                $convertedValue = ($searchKey === 'Text') ? (string)$gefundenerWert : (float)$gefundenerWert;

                SetValue($variableID, $convertedValue);
                $counter++;
            }
        }
    }
}

    }
}
