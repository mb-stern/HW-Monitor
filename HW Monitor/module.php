<?php
class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
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

        // Variablen anlegen und einstellen für die ID-Ausgabe
        $IDs = "Registrierte_IDs";
        $IDsIdent = "Registrierte_IDs_Ident";
        $this->RegisterVariableString($IDsIdent, $IDs);
        SetValue($this->GetIDForIdent($IDsIdent), $idListeString);

        // Suche nach "id" in der ID-Liste
        $foundIds = [];

        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];
            $foundIds[] = $gesuchteId;
        }

        // Variablen anlegen und einstellen für die gefundenen IDs
        foreach ($foundIds as $gefundeneId) {
            $variableIdent = "Variable_" . $gefundeneId;
            $this->RegisterVariableFloat($variableIdent, "Variable für ID $gefundeneId");
            SetValue($this->GetIDForIdent($variableIdent), $gefundeneId);

            // Suche nach "Value" für die gefundenen IDs
            $foundValues = [];
            $this->searchJsonValue($contentArray, 'Value', $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            foreach ($foundValues as $gefundenerWert) {
                $variableIdentValue = "Variable_" . md5($gefundenerWert);
                $this->RegisterVariableString($variableIdentValue, "Variable für Wert $gefundenerWert");
                SetValue($this->GetIDForIdent($variableIdentValue), $gefundenerWert);
            }
        }
    }
}
