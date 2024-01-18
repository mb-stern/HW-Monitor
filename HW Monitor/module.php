<?php
class HWMonitor extends IPSModule
{
    // ... (vorheriger Code)

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Schleife für die ID-Liste
        $counter = 1;
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

                        // Hier die Methode RegisterVariableFloat oder RegisterVariableString verwenden
                        if ($variableType == VARIABLETYPE_FLOAT) {
                            $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $gesuchteId * 10 + $counter);
                        } else {
                            $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $gesuchteId * 10 + $counter);
                        }

                        // Konvertiere den Wert, wenn der Typ nicht übereinstimmt
                        $convertedValue = ($variableType == VARIABLETYPE_STRING) ? (string)$gefundenerWert : (float)$gefundenerWert;

                        SetValue($this->GetIDForIdent($variableIdentValue), $convertedValue);
                        $counter++;
                    }
                }
            }
        }

        // Lösche nicht mehr benötigte Variablen
        $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($existingVariables as $existingVariable) {
            $variableInfo = IPS_GetVariable($existingVariable);

            // Änderung: Prüfe, ob "VariableIdent" vorhanden ist, bevor es verwendet wird
            if (isset($variableInfo['VariableIdent'])) {
                $variableIdent = $variableInfo['VariableIdent'];

                // Prüfe, ob die Variable in der IDListe vorhanden ist
                $found = false;
                foreach ($idListe as $idItem) {
                    $gesuchteId = $idItem['id'];
                    if (strpos($variableIdent, "Variable_" . $gesuchteId) !== false) {
                        $found = true;
                        break;
                    }
                }

                // Lösche die Variable, wenn sie nicht in der IDListe gefunden wurde
                if (!$found) {
                    IPS_DeleteVariable($existingVariable);
                }
            }
        }
    }
}
