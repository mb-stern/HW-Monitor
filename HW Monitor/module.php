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

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');
        $this->RegisterPropertyInteger("Intervall", 60);

        // Timer mit Standard-Intervall erstellen
        $this->RegisterTimer("MeinTimer", 0, 'HWMonitor_MeinTimerEvent($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Hier lesen wir den Intervall-Wert aus den Eigenschaften
        $intervall = $this->ReadPropertyInteger("Intervall");

        // Hier setzen wir den Timer mit dem gelesenen Intervall-Wert
        $this->SetTimerInterval("MeinTimer", $intervall * 1000); // Umrechnung in Millisekunden

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

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
            $counter = 0; // Zähler für jede 'id' zurücksetzen
            foreach ($foundValues as $searchKey => $values) {
                if (in_array($searchKey, ['Text', 'id', 'Min', 'Max', 'Value'])) {
                    foreach ($values as $gefundenerWert) {
                        $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                        $variablePosition = $gesuchteId * 10 + $counter;

                        // Überprüfen, ob die Variable bereits existiert
                        $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                        if ($variableID === false) {
                            // Variable existiert noch nicht, also erstellen
                            if ($searchKey === 'Text') {
                                $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            } else {
                                $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            }

                            // Konfiguration nur bei Neuerstellung
                            // Hier könnten zusätzliche Konfigurationen erfolgen
                        } else {
                            // Variable existiert bereits, entferne sie aus der Liste der vorhandenen Variablen
                            $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                            if ($keyIndex !== false) {
                                unset($existingVariableIDs[$keyIndex]);
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

        // Lösche nicht mehr benötigte Variablen
        foreach ($existingVariableIDs as $variableToRemove) {
            $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
            if ($variableIDToRemove !== false) {
                IPS_DeleteVariable($variableIDToRemove);
            }
        }
    }

    public function MeinTimerEvent()
    {
        // Hier kannst du den Code platzieren, der bei jedem Timer-Ereignis ausgeführt werden soll
        // Zum Beispiel: Daten aktualisieren, Werte abrufen, etc.
        // ...
    }
}
