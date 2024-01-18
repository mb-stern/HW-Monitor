<?php

class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    protected function searchValueForId($jsonArray, $searchId, &$foundValue)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                // Die gesuchte ID wurde gefunden, jetzt den zugehörigen "Value" suchen
                $this->searchJsonValue($jsonArray, 'Value', $foundValue);
                break; // Wir haben die ID gefunden, daher können wir die Suche beenden
            } elseif (is_array($value)) {
                // Rekursiv in den verschachtelten Arrays suchen
                $this->searchValueForId($value, $searchId, $foundValue);
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

        // Variablen anlegen und einstellen für die Contentausgabe
        $JSON = "JSON_Content";
        $JSONIdent = "JSON_Content_Ident";
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);

        // Schleife für die ID-Liste
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            // Variablen anlegen und einstellen für die ID
            $variableIdent = "Variable_" . $gesuchteId;
            $variableIdExists = @IPS_GetObjectIDByIdent($variableIdent, $this->InstanceID);

            if ($variableIdExists === false) {
                $this->RegisterVariableFloat($variableIdent, "ID");
                SetValue($this->GetIDForIdent($variableIdent), $gesuchteId);
            }

            // Suche nach "Value" für die gefundenen IDs
            $foundValue = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValue);

            // Variablen anlegen und einstellen für die gefundenen Werte
            foreach ($foundValue as $gefundenerWert) {
                $variableIdentValue = "Variable_" . $gesuchteId . "_Value";
                $variableIdValueExists = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);

                if ($variableIdValueExists === false) {
                    $this->RegisterVariableString($variableIdentValue, "Value");
                }

                SetValue($this->GetIDForIdent($variableIdentValue), $gefundenerWert);
            }
        }
    }
}

?>