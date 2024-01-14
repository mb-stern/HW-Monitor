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

        // Überprüfen, ob die JSON-Dekodierung erfolgreich war
        if (json_last_error() !== JSON_ERROR_NONE) {
            die('Fehler beim Dekodieren des JSON-Inhalts');
        }

        // Durch die ID-Liste iterieren und passende IDs im Inhalt finden
        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            // Direkt nach der ID im ContentArray suchen
            foreach ($contentArray as $item) {
                // Funktion zum Suchen der ID in den Children
                $foundItem = $this->findIdInChildren($item, $gesuchteId);

                if ($foundItem !== null) {
                    // Die gefundene ID ausgeben (als float)
                    $gefundeneId = (float)$gesuchteId;
                    echo "Gefundene ID: $gefundeneId\n";

                    // Hier können Sie die Variable für 'Text' erstellen oder den gefundenen Wert anderweitig verwenden
                    // Zum Beispiel:
                    $variableIdent = "Variable_" . $gefundeneId;
                    $this->RegisterVariableString($variableIdent, "Variable für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent), $foundItem['Text']);

                    // Hier können Sie auch die Variablen für 'Min', 'Max', 'Value' erstellen
                    // Zum Beispiel:
                    $this->RegisterVariableString($variableIdent . "_Min", "Min für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent . "_Min"), $foundItem['Min']);

                    $this->RegisterVariableString($variableIdent . "_Max", "Max für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent . "_Max"), $foundItem['Max']);

                    $this->RegisterVariableString($variableIdent . "_Value", "Value für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent . "_Value"), $foundItem['Value']);
                }
            }
        }
    }

    // Funktion zum Suchen der ID in den Children
    private function findIdInChildren($item, $gesuchteId)
    {
        if ($item['id'] == $gesuchteId) {
            return $item;
        }

        if (isset($item['Children']) && is_array($item['Children'])) {
            foreach ($item['Children'] as $child) {
                $found = $this->findIdInChildren($child, $gesuchteId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }
}
