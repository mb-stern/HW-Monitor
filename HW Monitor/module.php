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

        // IntervalBox für das Timer-Intervall
        $this->RegisterPropertyInteger("Intervall", 300); // Standardwert: 300 Sekunden (5 Minuten)

        // Timer mit einer Standard-Intervallzeit erstellen
        $this->RegisterTimer("UpdateDataTimer", 0, 'HWM_UpdateData($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer-Intervall aus der Eigenschaft lesen und setzen
        $intervall = $this->ReadPropertyInteger("Intervall");
        $this->SetTimerInterval("UpdateDataTimer", $intervall * 1000); // In Millisekunden umrechnen

        $this->UpdateData(); // Führe die Initialisierung beim Anlegen des Moduls aus
    }

    public function UpdateData()
    {
        // Diese Methode wird vom Timer aufgerufen und kann für die Aktualisierung der Daten verwendet werden
        $this->Log("Updating data...");

        // Daten abrufen
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

            /// Variablen anlegen und einstellen für die gefundenen Werte
foreach ($foundValues as $searchKey => $values) {
    if (in_array($searchKey, ['Text', 'id', 'Min', 'Max', 'Value'])) {
        $counter = 0; // Zähler für jede 'id' zurücksetzen
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
        
                $this->Log("Data updated successfully.");
            }
        
            public function UpdateDataTimer()
            {
                // Methode, die vom Timer ausgelöst wird
                $this->UpdateData();
            }
        }
        