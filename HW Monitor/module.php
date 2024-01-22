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

    protected function getProfileForType($type)
    {
        $profileMapping = [
            'Clock' => 'HW.Clock',
            'Load'  => 'HW.Load',
            // Fügen Sie hier weitere Zuordnungen hinzu, wenn nötig
        ];

        // Gibt das entsprechende Profil zurück, oder ein Standardprofil, falls keins gefunden wurde
        return isset($profileMapping[$type]) ? $profileMapping[$type] : 'Standardprofil';
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString('IPAddress', '192.168.178.76');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

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

        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);
        $this->Update();
    }

    public function Update()
{
    $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
    $contentArray = json_decode($content, true);

    $idListeString = $this->ReadPropertyString('IDListe');
    $idListe = json_decode($idListeString, true);

    $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
    $existingVariableIDs = [];
    foreach ($existingVariables as $existingVariableID) {
        $existingVariableIDs[] = IPS_GetObject($existingVariableID)['ObjectIdent'];
    }

    foreach ($idListe as $idItem) {
        $gesuchteId = $idItem['id'];

        $foundValues = [];
        $this->searchValuesForId($contentArray, $gesuchteId, $foundValues);

        $counter = 0;

        $requiredKeys = ['Text', 'id', 'Min', 'Max', 'Value', 'Type'];
        foreach ($requiredKeys as $searchKey) {
            if (!array_key_exists($searchKey, $foundValues)) {
                continue;
            }

            foreach ($foundValues[$searchKey] as $gefundenerWert) {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                $variablePosition = $gesuchteId * 10 + $counter;

                $profile = $this->getProfileForType($foundValues['Type'][0]);

                $variableID = false;
                if (in_array($searchKey, ['Min', 'Max', 'Value'])) {
                    $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), $profile, $variablePosition);
                    $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                } elseif ($searchKey === 'id') {
                    $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), $profile, $variablePosition);
                } elseif ($searchKey === 'Text' || $searchKey === 'Type') {
                    $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), '', $variablePosition);
                }

                if ($variableID !== false) {
                    $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                    if ($keyIndex !== false) {
                        unset($existingVariableIDs[$keyIndex]);
                    }

                    $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;
                    SetValue($variableID, $convertedValue);
                    $counter++;
                }
            }
        }
    }

    foreach ($existingVariableIDs as $variableToRemove) {
        $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
        if ($variableIDToRemove !== false) {
            IPS_DeleteVariable($variableIDToRemove);
        }
    }
}
}
