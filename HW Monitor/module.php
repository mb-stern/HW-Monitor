<?php

class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    public function Create()
    {
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
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        $JSON = "JSON_Content";
        $JSONIdent = "JSON_Content_Ident";
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);

        $IDs = "Registrierte_IDs";
        $IDsIdent = "Registrierte_IDs_Ident";
        $this->RegisterVariableString($IDsIdent, $IDs);
        SetValue($this->GetIDForIdent($IDsIdent), $idListeString);

        if (json_last_error() !== JSON_ERROR_NONE) {
            die('Fehler beim Dekodieren des JSON-Inhalts');
        }

        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];

            foreach ($contentArray as $item) {
                if (isset($item['id']) && $item['id'] === $gesuchteId) {
                    $gefundeneId = (float)$item['id'];
                    echo "Gefundene ID: $gefundeneId\n";

                    $variableIdent = "Variable_" . $gefundeneId;
                    $this->RegisterVariableFloat($variableIdent, "Variable für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent), $gefundeneId);

                    // Zusätzliche Werte erstellen
                    foreach ($item as $key => $value) {
                        if ($key !== 'id') {
                            $valueIdent = $variableIdent . "_$key";
                            $this->RegisterVariableString($valueIdent, "Wert für $key (ID $gefundeneId)");
                            SetValue($this->GetIDForIdent($valueIdent), $value);
                        }
                    }
                }
            }
        }
    }
}

?>
