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
        //$this->RegisterPropertyInteger("Intervall", 10);
        //$this->RegisterTimer("HWM_UpdateTimer", $this->ReadPropertyInteger("Intervall") * 1000, 'HWM_Update($_IPS[\'TARGET\']);');
    }
        public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
      
        // JSON von der URL abrufen und entpacken
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        // JSON-Array aus der Property 'IDListe' holen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Variablen anlegen und einstellen für die Contentausgabe
        $JSON = "JSON_Content"; // Geben Sie einen geeigneten Namen ein
        $JSONIdent = "JSON_Content_Ident"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);
        
        // Variablen anlegen und einstellen für die ID-Ausgabe
        $IDs = "Registrierte_IDs"; // Geben Sie einen geeigneten Namen ein
        $IDsIdent = "Registrierte_IDs_Ident"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($IDsIdent, $IDs);
        SetValue($this->GetIDForIdent($IDsIdent), $idListeString);

        // Überprüfen, ob die JSON-Dekodierung erfolgreich war
        if (json_last_error() !== JSON_ERROR_NONE) {
            die('Fehler beim Dekodieren des JSON-Inhalts');
        }

     // Durch die ID-Liste iterieren und passende IDs im Inhalt finden
    foreach ($idListe as $idItem) {
        $gesuchteId = $idItem['id'];

        // Direkt nach der ID im ContentArray suchen
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

            // Iterieren Sie über alle Schlüssel-Wert-Paare auf derselben Ebene wie "id"
            foreach ($item as $key => $value) {
                $variableIdent = "Variable_" . $gefundeneId . "_" . $key;
            
                // Feststellen, welchen Typ die Variable haben soll
                if (is_numeric($value)) {
                    $variableType = VARIABLETYPE_FLOAT;
                } else {
                    $variableType = VARIABLETYPE_STRING;
                }
            
                // Variable nur erstellen, wenn sie noch nicht existiert
                if (!$this->GetIDForIdent($variableIdent)) {
                    $this->RegisterVariable($variableIdent, "Variable für ID $gefundeneId - $key", $variableType);
                }
            
                // Wert setzen, abhängig vom Typ
                if ($variableType === VARIABLETYPE_FLOAT) {
                    SetValue($this->GetIDForIdent($variableIdent), (float)$value);
                } else {
                    SetValue($this->GetIDForIdent($variableIdent), (string)$value);
                }
            }
        }
    }
}           
}
}
}