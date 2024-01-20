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

            // Sortiere die gefundenen Werte nach gewünschter Reihenfolge
            $desiredOrder = ['Text', 'id', 'Value', 'Min', 'Max'];
            $sortedValues = [];
            foreach ($desiredOrder as $orderKey) {
                if (isset($foundValues[$orderKey])) {
                    $sortedValues[$orderKey] = $foundValues[$orderKey];
                    unset($foundValues[$orderKey]);
                }
            }
            $sortedValues = array_merge($sortedValues, $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            foreach ($sortedValues as $searchKey => $values) {
                if (in_array($searchKey, ['id', 'Text', 'Value', 'Min', 'Max'])) {
                    foreach ($values as $gefundenerWert) {
                        $variableIdentValue = "Variable_" . $gesuchteId . "_$searchKey";
                        $variableType = $searchKey === 'Value' || $searchKey === 'Text' ? VARIABLETYPE_STRING : VARIABLETYPE_FLOAT;

                        $variableID = $this->GetIDForIdent($variableIdentValue);

                        // Überprüfen, ob die Variable bereits existiert
                        if (!$variableID) {
                            // Hier die Methode RegisterVariableFloat oder RegisterVariableString verwenden
                            if ($variableType == VARIABLETYPE_FLOAT) {
                                $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", 0);
                            } else {
                                $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", 0);
                            }
                        }

                        // Konvertiere den Wert, wenn der Typ nicht übereinstimmt
                        $convertedValue = ($variableType == VARIABLETYPE_STRING) ? (string)$gefundenerWert : (float)$gefundenerWert;

                        SetValue($variableID, $convertedValue);
                    }
                }
            }
        }
    }
}
?>
