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
        // Überprüfen, ob $idItem ein Array ist und ob 'id' als Schlüssel vorhanden ist
        if (!is_array($idItem) || !array_key_exists('id', $idItem)) {
            $this->Log('Ungültiges ID-Item oder "id" nicht gefunden');
            continue;
        }
    
        $gesuchteId = $idItem['id'];
    
        // Direkt nach der ID im ContentArray suchen
        foreach ($idListe as $idItem) {
            // Überprüfen, ob $idItem ein Array ist und ob 'id' als Schlüssel vorhanden ist
            if (!is_array($idItem) || !array_key_exists('id', $idItem)) {
                $this->Log('Ungültiges ID-Item oder "id" nicht gefunden');
                continue;
            }
        
            $gesuchteId = $idItem['id'];
        
            // Direkt nach der ID im ContentArray suchen
            foreach ($idListe as $idItem) {
                // Überprüfen, ob $idItem ein Array ist und ob 'id' als Schlüssel vorhanden ist
                if (!is_array($idItem) || !array_key_exists('id', $idItem)) {
                    $this->Log('Ungültiges ID-Item oder "id" nicht gefunden');
                    continue;
                }
            
                $gesuchteId = $idItem['id'];
            
                // Direkt nach der ID im ContentArray suchen
                foreach ($contentArray as $item) {
                    // Überprüfen, ob $item ein Array ist
                    if (!is_array($item)) {
                        $this->Log('Ungültiges Content-Item: Nicht-Array gefunden');
                        continue;
                    }
            
                    // Überprüfen, ob 'id' als Schlüssel im Array $item vorhanden ist
                    if (!array_key_exists('id', $item)) {
                        $this->Log('Ungültiges Content-Item: "id" nicht gefunden');
                        continue;
                    }
            
                    if ($item['id'] == $gesuchteId) {
                        // Hier werden die gewünschten Werte für jede gefundene ID aus dem JSON-Content extrahiert
                        $minValue = isset($item['Min']) ? $item['Min'] : '';
                        $maxValue = isset($item['Max']) ? $item['Max'] : '';
                        $valueValue = isset($item['Value']) ? $item['Value'] : '';
                        $textValue = isset($item['Text']) ? $item['Text'] : '';
            
                        // Hier kannst du die Variablen erstellen oder die Werte anderweitig verwenden
                        // Zum Beispiel:
                        $minIdent = "Min_" . $gesuchteId;
                        $maxIdent = "Max_" . $gesuchteId;
                        $valueIdent = "Value_" . $gesuchteId;
                        $textIdent = "Text_" . $gesuchteId;
            
                        $this->RegisterVariableString($minIdent, "Min für ID $gesuchteId");
                        $this->RegisterVariableString($maxIdent, "Max für ID $gesuchteId");
                        $this->RegisterVariableString($valueIdent, "Value für ID $gesuchteId");
                        $this->RegisterVariableString($textIdent, "Text für ID $gesuchteId");
            
                        SetValue($this->GetIDForIdent($minIdent), $minValue);
                        SetValue($this->GetIDForIdent($maxIdent), $maxValue);
                        SetValue($this->GetIDForIdent($valueIdent), $valueValue);
                        SetValue($this->GetIDForIdent($textIdent), $textValue);
            
                        // Weitere Aktionen je nach Bedarf...
                    }
                }
            }
        }         
    }       
    
}
}