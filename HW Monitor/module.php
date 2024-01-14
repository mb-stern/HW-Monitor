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
        $this->RegisterPropertyString("IDListe", '[]');
        //$this->RegisterPropertyString("IDListe", '');
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

        // JSON-Array aus der Property 'IDListe' holen
        $idListeString = $this->ReadPropertyString('IDListe');
        $formattedString = $this->formatArrayToString(json_decode($idListeString, true));

        //Variablen anlegen und einstellen für die Contentausgabe
        $JSON = "JSON-Content"; // Geben Sie einen geeigneten Namen ein
        $JSONIdent = "JSONIdent"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);
        
        //Variablen anlegen und einstellen für die ID-Ausgabe zur Überprüfung
        $IDs = "Registrierte IDs"; // Geben Sie einen geeigneten Namen ein
        $IDsIdent = "IDsIdent"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($IDsIdent, $IDs);
        SetValue($this->GetIDForIdent($IDsIdent), $formattedString);

        // Werte aus dem JSON basierend auf den ausgewählten IDs abrufen
        $selectedValues = $this->getValuesOnSameLevel($value, $formattedString);

        // Hier können Sie die abgerufenen Werte weiterverarbeiten oder loggen
        $this->Log('Abgerufene Werte: ' . print_r($selectedValues, true));

        // Variablen für die abgerufenen Werte erstellen oder aktualisieren
        $this->createOrUpdateVariables($selectedValues);
    }

    // Funktion zum Formatieren des JSON-Arrays in einen String
    private function formatArrayToString($array)
    {
        $formattedString = '';

        if (is_array($array)) {
            foreach ($array as $item) {
                if (is_array($item)) {
                    foreach ($item as $key => $value) {
                        $formattedString .= "$value, ";
                    }
                }
            }
        }

        // Entfernen Sie das letzte Komma und Leerzeichen
        $formattedString = rtrim($formattedString, ', ');

        return $formattedString;
    }

    // Funktion zum Abrufen aller Werte auf derselben Ebene basierend auf den IDs im $formattedString
    private function getValuesOnSameLevel($jsonArray, $formattedString)
    {
        $selectedIDs = json_decode($formattedString, true);

        $valuesOnSameLevel = [];

        foreach ($selectedIDs as $selectedID) {
            // Überprüfen Sie, ob die ausgewählte ID im JSON vorhanden ist
            if (isset($jsonArray[$selectedID])) {
                // Hinzufügen aller Werte auf derselben Ebene zur Liste
                foreach ($jsonArray[$selectedID] as $key => $value) {
                    $valuesOnSameLevel[] = [
                        'ID' => $key,
                        'Text' => $key, // Anpassen, wenn ein bestimmtes Textattribut vorhanden ist
                        'Value' => $value,
                        'Min' => null, // Anpassen, wenn Min/Max-Werte verfügbar sind
                        'Max' => null,
                    ];
                }
            }
        }

        return $valuesOnSameLevel;
    }

    // Funktion zum Erstellen oder Aktualisieren von Float-Variablen
    private function createOrUpdateVariables($selectedValues)
    {
        foreach ($selectedValues as $value) {
            $variableID = $this->RegisterVariableFloat($value['Text'], $value['Text']);

            // Profil erstellen oder abrufen
            $profileName = 'HWM_Profile_' . $value['ID'];
            if (!IPS_VariableProfileExists($profileName)) {
                IPS_CreateVariableProfile($profileName, 2 /* Float */);
            }

            // Profileigenschaften setzen (z.B., Min und Max)
            IPS_SetVariableProfileText($profileName, '', ' ' . $value['Text']);
            IPS_SetVariableProfileValues($profileName, $value['Min'], $value['Max'], 0.1); // Passen Sie Schritt und Dez
            // Passen Sie Schritt und Dezimalstellen nach Bedarf an

            // Verknüpfung des Variablenprofils mit der erstellten Float-Variable
            IPS_SetVariableCustomProfile($variableID, $profileName);

            // Wert in die Variable setzen
            SetValueFloat($variableID, $value['Value']);
        }
    }
}
?>
