<?php
class HWMonitor extends IPSModule //development
{
    private $updateTimer;

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

        $this->RegisterPropertyString('IPAddress', '192.168.178.76');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');

        if (!IPS_VariableProfileExists("HW.Clock")) {
			IPS_CreateVariableProfile("HW.Clock", 2);
			IPS_SetVariableProfileValues("HW.Clock", 0, 5000, 1);
			IPS_SetVariableProfileAssociation("HW.Clock", 0, "MHz", "", -1);
		}
        if (!IPS_VariableProfileExists("HW.Load")) {
			IPS_CreateVariableProfile("HW.Load", 2);
			IPS_SetVariableProfileValues("HW.Load", 0, 100, 1);
			IPS_SetVariableProfileAssociation("HW.Load", 0, "%", "", -1);
		}

    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer für Aktualisierung aktualisieren
        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        //$this->SetTimerInterval('Update', 0);

        // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
        $this->Update();
    }

    public function Update()
{
    // Libre Hardware Monitor abfragen
    $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
    $contentArray = json_decode($content, true);

    // Gewählte ID's abfragen
    $idListeString = $this->ReadPropertyString('IDListe');
    $idListe = json_decode($idListeString, true);

    // Alle vorhandenen Variablen speichern
    $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
    $existingVariableIDs = [];
    foreach ($existingVariables as $existingVariableID) {
        $existingVariableIDs[] = IPS_GetObject($existingVariableID)['ObjectIdent'];
    }

    // Schleife für die ID-Liste
    foreach ($idListe as $idItem) {
        $gesuchteId = $idItem['id'];

        // Variablen anlegen und einstellen für die gefundenen Werte
        $counter = 0;

        // Hinzufügen einer Zuordnungsliste für Type zu Variablenprofilen
        $typeToProfileMapping = [
            'Clock' => 'HW.Clock',
            'Load' => 'HW.Load',
            // Weitere Zuordnungen hier hinzufügen, falls benötigt
        ];

        // Überprüfen, ob 'Type' in $foundValues vorhanden ist
        if (array_key_exists('Type', $foundValues)) {
            foreach ($foundValues['Type'] as $typeValue) {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_Type";
                $variablePosition = $gesuchteId * 10 + $counter;

                $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                if ($variableID === false) {
                    $variableID = $this->RegisterVariableString($variableIdentValue, 'Type', '', $variablePosition);
                } else {
                    $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                    if ($keyIndex !== false) {
                        unset($existingVariableIDs[$keyIndex]);
                    }
                }

                SetValue($variableID, $typeValue);
                $counter++;

                // Hinzufügen der Zuordnung des Variablenprofils basierend auf dem 'Type'
                $variableProfile = isset($typeToProfileMapping[$typeValue]) ? $typeToProfileMapping[$typeValue] : '';
                if (!IPS_VariableProfileExists($variableProfile)) {
                    // Hier könnten Sie eine Standardprofil-Erstellung vornehmen oder eine Warnung ausgeben.
                    $this->Log("Variable profile '{$variableProfile}' does not exist!");
                } else {
                    IPS_SetVariableCustomProfile($variableID, $variableProfile);
                }
            }
        }
    }

        // Lösche nicht mehr benötigte Variablen
        foreach ($existingVariableIDs as $variableToRemove) {
            $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
            if ($variableIDToRemove !== false) {
                IPS_DeleteVariable($variableIDToRemove);
            }
        }
    }
}
