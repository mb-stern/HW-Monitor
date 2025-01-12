<?php
class HWMonitor extends IPSModule
{
    private $updateTimer;

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

        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyString('IDListe', '[]');
        $this->RegisterPropertyInteger('UpdateInterval', 0);

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer('UpdateTimer', 0, 'HW_Update(' . $this->InstanceID . ');');

        // VariableProfile-Erstellung ausgelagert
        $this->createVariableProfiles();
    }

    // Neue Methode für die Erstellung der VariableProfiles
    private function createVariableProfiles()
    {
        $profiles = [
            "HW.Clock" => [2, 0, 5000, 1, 0, " Mhz"],
            "HW.Data" => [2, 0, 100, 1, 1, " GB"],
            "HW.Temp" => [2, 0, 100, 1, 0, " °C"],
            "HW.Fan" => [2, 0, 1000, 1, 0, " RPM"],
            "HW.Rate" => [2, 0, 1000, 1, 0, " KB/s"],
        ];

        foreach ($profiles as $name => [$type, $min, $max, $step, $digits, $suffix]) {
            if (!IPS_VariableProfileExists($name)) {
                IPS_CreateVariableProfile($name, $type);
                IPS_SetVariableProfileValues($name, $min, $max, $step);
                IPS_SetVariableProfileDigits($name, $digits);
                IPS_SetVariableProfileText($name, "", $suffix);
            }
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('UpdateTimer', $this->ReadPropertyInteger('UpdateInterval') * 1000);

        $ipAddress = $this->ReadPropertyString('IPAddress');
        if ($ipAddress == '0.0.0.0') {
            $this->SendDebug("Konfiguration", "IP-Adresse ist nicht konfiguriert", 0);
            $this->LogMessage("IP-Adresse ist nicht konfiguriert", KL_ERROR);
        } else {
            $this->Update();
        }
    }

    protected function getVariableProfileByType($type)
    {
        $profiles = [
            'Clock' => 'HW.Clock',
            'Load' => '~Progress',
            'Temperature' => 'HW.Temp',
            'Fan' => 'HW.Fan',
            'Voltage' => '~Volt',
            'Power' => '~Watt',
            'Data' => 'HW.Data',
            'Level' => '~Progress',
            'Throughput' => 'HW.Rate'
        ];

        return $profiles[$type] ?? '';
    }

    public function Update()
    {
        try {
            // Fehlerbehandlung für JSON-Abfrage
            $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
            $contentArray = json_decode($content, true);
            if ($contentArray === null) {
                throw new Exception('Invalid JSON data');
            }
        } catch (Exception $e) {
            $this->SendDebug("Fehler", $e->getMessage(), 0);
            $this->LogMessage($e->getMessage(), KL_ERROR);
            return;
        }

        $this->SendDebug("Verbindungseinstellung", "{$this->ReadPropertyString('IPAddress')} : {$this->ReadPropertyInteger('Port')}", 0);

        $idListe = json_decode($this->ReadPropertyString('IDListe'), true);
        $existingVariables = IPS_GetChildrenIDs($this->InstanceID);
        $existingVariableIDs = array_map(function($id) {
            return IPS_GetObject($id)['ObjectIdent'];
        }, $existingVariables);

        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

            $prefix = $foundValues['id'][0] ?? '';
            $this->SendDebug("Präfix", "Präfix für ID: $prefix erstellt", 0);

            // Neue Methode zur Erstellung und Aktualisierung der Variablen
            $this->createAndUpdateVariables($gesuchteId, $prefix, $foundValues, $existingVariableIDs);
        }

        // Löschen nicht mehr benötigter Variablen
        $this->deleteUnusedVariables($existingVariableIDs);
    }

    // Neue Methode zur Erstellung und Aktualisierung der Variablen
    private function createAndUpdateVariables($gesuchteId, $prefix, $foundValues, &$existingVariableIDs)
    {
        $variableNameReplacements = [
            'Text' => 'Name',
            'Min' => 'Minimum',
            'Max' => 'Maximum',
            'Value' => 'Istwert',
        ];

        $counter = 0;
        foreach (['Text', 'Min', 'Max', 'Value'] as $searchKey) {
            if (!isset($foundValues[$searchKey])) {
                continue;
            }

            $variableName = $variableNameReplacements[$searchKey] ?? ucfirst($searchKey);

            foreach ($foundValues[$searchKey] as $gefundenerWert) {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                $variablePosition = $gesuchteId * 10 + $counter;

                $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                if ($variableID === false) {
                    $variableID = $this->createVariable($variableIdentValue, $variableName, $foundValues['Type'][0], $variablePosition, $searchKey, $prefix);
                } else {
                    $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                    if ($keyIndex !== false) {
                        unset($existingVariableIDs[$keyIndex]);
                    }
                }
                $this->SendDebug("gefundener Wert", "Wert: $gefundenerWert", 0);

                // Prüfen, ob der Wert numerisch ist
                if (is_numeric($gefundenerWert)) {
                    $gefundenerWert = (float)$gefundenerWert; // Nur bei numerischen Werten umwandeln
                }
                
                // Wert speichern
                SetValue($variableID, $gefundenerWert);
                $this->SendDebug("Variable aktualisiert", "Variabel-ID: $variableID, Position: $variablePosition, Name: $variableName, Wert: $gefundenerWert", 0);

                $counter++;
            }
        }
    }

    // Neue Methode zur Variablenerstellung
    private function createVariable($ident, $name, $type, $position, $key, $prefix)
    {
        if (in_array($key, ['Min', 'Max', 'Value'])) {
            return $this->RegisterVariableFloat($ident, "ID $prefix - $name", $this->getVariableProfileByType($type), $position);
        } elseif ($key === 'Text') {
            return $this->RegisterVariableString($ident, "ID $prefix - $name", "", $position);
        }
        return false;
    }

    // Neue Methode zum Löschen nicht mehr benötigter Variablen
    private function deleteUnusedVariables($existingVariableIDs)
    {
        foreach ($existingVariableIDs as $variableToRemove) {
            $variableIDToRemove = @IPS_GetObjectIDByIdent($variableToRemove, $this->InstanceID);
            if ($variableIDToRemove !== false) {
                $this->UnregisterVariable($variableToRemove);
                $this->SendDebug("Variable gelöscht", $variableToRemove, 0);
            }
        }
    }
}
