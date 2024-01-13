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
    }

    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();
      
        // JSON von der URL abrufen
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
    
        $value = json_decode($content, true);
    }

}