<?php

class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    protected function searchValuesForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                // Die gesuchte ID wurde gefunden, jetzt die zugehörigen Werte suchen
                foreach (['Value', 'Min', 'Max', 'Text'] as $searchKey) {
                    $this->searchJsonValue($jsonArray, $searchKey, $foundValues[$searchKey]);
                }
                break; // Wir haben die ID gefunden, daher können wir die Suche beenden
            } elseif (is_array($value)) {
                // Rekursiv in den verschachtelten Arrays suchen
                $this->searchValuesForId($value, $searchId, $foundValues);
            }
        }
    }

    protected function searchJsonValue($jsonArray, $searchKey, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === $searchKey) {
                $foundValues[] = $value;
            } elseif (is_array($value)) {
                $this->searchJsonValue($value, $searchKey, $foundValues);
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

            // Variablen anlegen und einstellen für die ID
            $variableIdent = "Variable_" . $counter;
            $this->RegisterVariableFloat($variableIdent, "ID", "", $counter);
            SetValue($this->GetIDForIdent($variableIdent), $gesuchteId);
            $counter++;

            // Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValuesForId($contentArray, $gesuchteId, $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            foreach ($foundValues as $key => $value) {
                $variableIdentValue = "Variable_" . $counter . "_$key";
                $this->RegisterVariableString($variableIdentValue, ucfirst($key), "", $counter);
                SetValue($this->GetIDForIdent($variableIdentValue), $value);
                $counter++;
            }
        }
    }
}

?>
