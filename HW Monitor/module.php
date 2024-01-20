<?php
class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    protected function searchValueForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if ($key === 'id' && $value === $searchId) {
                $this->searchValuesForId($jsonArray, $searchId, $foundValues);
                break;
            } elseif (is_array($value)) {
                $this->searchValueForId($value, $searchId, $foundValues);
            }
        }
    }

    protected function searchValuesForId($jsonArray, $searchId, &$foundValues)
    {
        foreach ($jsonArray as $key => $value) {
            if (is_array($value)) {
                $this->searchValuesForId($value, $searchId, $foundValues);
            } else {
                $foundValues[$key][] = $value;
            }
        }
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');
        $this->RegisterPropertyInteger("Intervall", 60);

        // Timer mit Standard-Intervall erstellen
        $this->RegisterTimer("MeinTimer", $this->ReadPropertyInteger("Intervall") * 1000, 'HWMonitor_MeinTimerEvent($_IPS["TARGET"]);');

        // Erstmalige Initialisierung
        $this->ApplyChanges();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer-Intervall setzen
        $this->SetTimerInterval("MeinTimer", $this->ReadPropertyInteger("Intervall") * 1000);

        // Timer-Event manuell auslösen
        $this->MeinTimerEvent();
    }

    public function MeinTimerEvent()
    {
        $this->Log("MeinTimerEvent started");

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        $this->Log("Content: " . print_r($contentArray, true));

        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Rest des Codes bleibt unverändert
        // ...

        $this->Log("MeinTimerEvent finished");
    }
}
