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

        // Schleife für die ID-Liste
        $counter = 1;
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            // Überprüfen, ob die Variable bereits existiert
            $variableIdentValue = "Variable_" . $gesuchteId . "_Text";
            $variableID = @$this->GetIDForIdent($variableIdentValue);

            if ($variableID === false) {
                // Wenn die Variable nicht existiert, neu erstellen
                $variableID = $this->RegisterVariableString($variableIdentValue, "Text", "", $gesuchteId * 10 + $counter);
            }

            // Hier weitere Variablen erstellen oder aktualisieren...

            // Konvertiere den Wert, wenn der Typ nicht übereinstimmt
            $convertedValue = (string)$gefundenerWert;
            SetValue($variableID, $convertedValue);
            $counter++;
        }
    }
}
