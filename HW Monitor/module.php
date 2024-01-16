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

        // Überprüfen, ob das Abrufen der Daten erfolgreich war
        if ($content === false) {
            $this->Log("Fehler beim Abrufen der Daten von der URL");
            return;
        }

        // JSON-Array aus der Property 'IDListe' holen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Überprüfen, ob die JSON-Dekodierung erfolgreich war
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($idListe)) {
            $this->Log('Fehler beim Dekodieren der IDListe');
            return;
        }

        // JSON-Array aus der URL abrufen und entpacken
        $contentArray = json_decode($content, true);

        // Fortsetzung des Codes...
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            // Direkt nach der ID im ContentArray suchen
            foreach ($contentArray as $item) {
                if ($item['id'] == $gesuchteId) {
                    foreach ($item as $key => $value) {
                        // Überprüfen, ob der Schlüssel nicht 'id' ist (um Doppelungen zu vermeiden)
                        if ($key != 'id') {
                            // Hier kannst du die Variable erstellen oder den gefundenen Wert anderweitig verwenden
                            // Zum Beispiel:
                            $variableIdent = "Variable_" . $gesuchteId . "_" . $key;
                            $this->RegisterVariableString($variableIdent, "Variable für ID $gesuchteId - $key");
                            SetValue($this->GetIDForIdent($variableIdent), $value);
                        }
                    }
                }
            }
        }
    }
}
?>
