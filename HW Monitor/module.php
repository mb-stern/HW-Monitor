<?php
class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        // Never delete this line!
        IPS_LogMessage(__CLASS__, $Message);
    }

    public function Create()
    {
        // Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();

        // JSON von der URL abrufen und entpacken
        $content = @file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        if ($content === false) {
            $this->Log('Fehler beim Abrufen der JSON-Daten.');
            return;
        }

        // JSON-Array aus der Property 'IDListe' holen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // JSON-Array erstellen
        $contentArray = json_decode($content, true);

        // Überprüfen, ob die JSON-Dekodierung erfolgreich war
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->Log('Fehler beim Dekodieren des JSON-Inhalts: ' . json_last_error_msg());
            return;
        }

        // Durch die ID-Liste iterieren und passende IDs im Inhalt finden
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];
        
            // Direkt nach der ID im ContentArray suchen
            foreach ($contentArray as $item) {
                // JSON-String des aktuellen Elements erhalten
                $jsonString = json_encode($item);
        
                // Präfix "id" mit Anführungszeichen hinzufügen
                $gesuchtesPräfix = '"id":' . $gesuchteId;
        
                // Überprüfen, ob das Präfix im JSON-String gefunden wird
                if (strpos($jsonString, $gesuchtesPräfix) !== false) {
                    // Die gefundenen Werte ausgeben
                    $gefundeneId = (float)$gesuchteId;
                    $textValue = $item['Text'];
                    $minValue = (float)$item['Min'];
                    $maxValue = (float)$item['Max'];
                    $valueValue = (float)$item['Value'];
        
                    // Variablen erstellen und Werte setzen
                    $idVariableIdent = "Variable_ID_" . $gefundeneId;
                    $textVariableIdent = "Variable_Text_" . $gefundeneId;
                    $minVariableIdent = "Variable_Min_" . $gefundeneId;
                    $maxVariableIdent = "Variable_Max_" . $gefundeneId;
                    $valueVariableIdent = "Variable_Value_" . $gefundeneId;
        
                    $this->RegisterVariableFloat($idVariableIdent, "ID für $gefundeneId");
                    $this->RegisterVariableString($textVariableIdent, "Text für $gefundeneId");
                    $this->RegisterVariableFloat($minVariableIdent, "Min für $gefundeneId");
                    $this->RegisterVariableFloat($maxVariableIdent, "Max für $gefundeneId");
                    $this->RegisterVariableFloat($valueVariableIdent, "Value für $gefundeneId");
        
                    SetValue($this->GetIDForIdent($idVariableIdent), $gefundeneId);
                    SetValue($this->GetIDForIdent($textVariableIdent), $textValue);
                    SetValue($this->GetIDForIdent($minVariableIdent), $minValue);
                    SetValue($this->GetIDForIdent($maxVariableIdent), $maxValue);
                    SetValue($this->GetIDForIdent($valueVariableIdent), $valueValue);
                }
            }
        }
        

    }
}
?>
