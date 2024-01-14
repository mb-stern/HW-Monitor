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
    $gesuchteId = $idItem['id'];

    // Direkt nach der ID im ContentArray suchen
    foreach ($contentArray as $item) {
        if (is_array($item) && isset($item['id'])) {
            $jsonString = json_encode($item);
            $gesuchtesPräfix = '"id":' . $gesuchteId;

            if (strpos($jsonString, $gesuchtesPräfix) !== false) {
                // Die gefundene ID ausgeben (als float)
                $gefundeneId = (float) $gesuchteId;
                echo "Gefundene ID: $gefundeneId\n";

                // Hier die Variable für 'Text' als String erstellen
                $textValue = $item['Text'];
                $textVariableIdent = "Text_Variable_" . $gefundeneId;
                $this->RegisterVariableString($textVariableIdent, "Text Variable für ID $gefundeneId");
                SetValue($this->GetIDForIdent($textVariableIdent), $textValue);

                // Hier die Variable für 'Min' als String erstellen
                $minValue = $item['Min'];
                $minVariableIdent = "Min_Variable_" . $gefundeneId;
                $this->RegisterVariableString($minVariableIdent, "Min Variable für ID $gefundeneId");
                SetValue($this->GetIDForIdent($minVariableIdent), $minValue);

                // Hier die Variable für 'Max' als String erstellen
                $maxValue = $item['Max'];
                $maxVariableIdent = "Max_Variable_" . $gefundeneId;
                $this->RegisterVariableString($maxVariableIdent, "Max Variable für ID $gefundeneId");
                SetValue($this->GetIDForIdent($maxVariableIdent), $maxValue);

                // Hier die Variable für 'Value' als String erstellen
                $valueValue = $item['Value'];
                $valueVariableIdent = "Value_Variable_" . $gefundeneId;
                $this->RegisterVariableString($valueVariableIdent, "Value Variable für ID $gefundeneId");
                SetValue($this->GetIDForIdent($valueVariableIdent), $valueValue);
            }
        }
    }
}
    }
}
