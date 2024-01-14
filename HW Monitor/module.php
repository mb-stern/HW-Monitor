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
        $this->RegisterPropertyInteger("Intervall", 10);
        $this->RegisterPropertyString("IDListe", '[]');
        $this->RegisterTimer("HWM_UpdateTimer", $this->ReadPropertyInteger("Intervall") * 1000, 'HWM_Update($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        $this->UnregisterTimer("HWM_UpdateTimer");
        
        // Never delete this line!
        parent::Destroy();
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
    //$gesuchteId = $idItem['id'];

    $gesuchteId = '"id":40';

    // Direkt nach der ID im ContentArray suchen
    foreach ($contentArray as $item) {
        if (isset($item['id']) && $item['id'] === $gesuchteId) {
            // Die gefundene ID ausgeben (als float)
            $gefundeneId = (float)$item['id'];
            echo "Gefundene ID: $gefundeneId\n";
    
            // Hier können Sie die Variable erstellen oder den gefundenen Wert anderweitig verwenden
            // Zum Beispiel:
            $variableIdent = "Variable_" . $gefundeneId;
            $this->RegisterVariableFloat($variableIdent, "Variable für ID $gefundeneId");
            SetValue($this->GetIDForIdent($variableIdent), $gefundeneId);
        }
    }
}

}
}
