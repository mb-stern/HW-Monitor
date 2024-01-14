<?php
class HWMonitor extends IPSModule
{
    protected function Log($Message)
    {
        //Never delete this line!
        IPS_LogMessage(__CLASS__, $Message);
    }
    
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyInteger("Intervall", 10);
        $this->RegisterPropertyString("IDListe", '[{"ID"}]');
        $this->RegisterTimer("HWM_UpdateTimer", $this->ReadPropertyInteger("Intervall") * 1000, 'HWM_Update($_IPS[\'TARGET\']);');
    }

    public function Destroy()
    {
        $this->UnregisterTimer("HWM_UpdateTimer");
        
        //Never delete this line!
        parent::Destroy();
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
      
        // JSON von der URL abrufen und entpacken
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $value = json_decode($content, true);

        //Variablen anlegen und einstellen
        $variableName = "JSON-Content"; // Geben Sie einen geeigneten Namen ein
        //$variableIdent = "MyVariableIdent"; // Geben Sie eine geeignete Identifikation ein

        //$this->RegisterVariableString($variableIdent, $variableName);
        //SetValue($this->GetIDForIdent($variableIdent), $content);

        
        SetValue($this->RegisterVariableString($variableName), $content);
    }
}