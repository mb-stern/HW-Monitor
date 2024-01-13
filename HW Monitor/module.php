<?php
class HWMonitor extends IPSModule
{
    /**
     * Log Message
     * @param string $Message
     */
    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    /**
     * Create
     */
    public function Create()
    {
        //Never delete this line!
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
    }

    /**
     * ApplyChanges
     */
    public function ApplyChanges()
    {
        //Never delete this line!
        parent::ApplyChanges();

        // JSON von der URL abrufen
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");

        // JSON decodieren
        $value = json_decode($content, true);

        // Assoziatives Array für die JSON-Struktur und die gewünschten Schlüssel ('Value', 'Text' und 'Profile')
        $jsonStructure = [
            /*'CPU-Temp' => ['Path' => ['Children', 0, 'Children', 1, 'Children', 3, 'Children', 4],
                            'Profile' => '~Temperature'],
            'CPU-Load' => ['Path' => ['Children', 0, 'Children', 1, 'Children', 4, 'Children', 0],
                            'Profile' => '~Progress'],
            'Memory-Load' => ['Path' => ['Children', 0, 'Children', 2, 'Children', 0, 'Children', 0],
                            'Profile' => '~Progress'],
            'LW-C' => ['Path' => ['Children', 0, 'Children', 6, 'Children', 0, 'Children', 0],
                            'Profile' => '~Progress'],
            'LW-D' => ['Path' => ['Children', 0, 'Children', 5, 'Children', 0, 'Children', 0],
                            'Profile' => '~Progress'],
            'LW-E' => ['Path' => ['Children', 0, 'Children', 3, 'Children', 0, 'Children', 0],
                            'Profile' => '~Progress'],*/
            'LW-F' => ['Path' => ['Children', 0, 'Children', 7, 'Children', 0, 'Children', 0],
                            'Profile' => '~Progress'],
            // Füge weitere Schlüssel hinzu, falls notwendig
        ];

        // Funktion zum Extrahieren der Werte
        function extractValues($data, $path) {
            foreach ($path as $key) {
                if (isset($data[$key])) {
                    $data = $data[$key];
                } else {
                    return null;
                }
            }

            return $data;
        }

        // ID des übergeordneten Objekts (Root) abrufen
        $parentID = IPS_GetParent($_IPS['SELF']);

        // Loop durch die JSON-Struktur und extrahiere die Werte
        foreach ($jsonStructure as $key => $config) {
            $path = $config['Path'];
            $profile = $config['Profile'];

            $valueData = extractValues($value, $path);

            if ($valueData !== null && isset($valueData['Value'], $valueData['Min'], $valueData['Max'])) {
                // Prüfe, ob das Dummy-Modul bereits existiert
                $dummyModuleID = @IPS_GetObjectIDByName($key, $parentID);

                if ($dummyModuleID === false) {
                    // Dummy-Modul-Instanz erstellen
                    $this->dummyModuleID = IPS_CreateInstance("{485D0419-BE97-4548-AA9C-C083EB82E61E}");  // Dummy Module

                    // Setze den Namen des Dummy-Moduls
                    IPS_SetName($dummyModuleID, $key);

                    // Setze das übergeordnete Objekt des Dummy-Moduls
                    //IPS_SetParent($dummyModuleID, $parentID);
                }

                // Loop durch die Werte und erstelle oder aktualisiere Float-Variablen innerhalb des Dummy-Moduls
                foreach (['Value', 'Min', 'Max'] as $position => $variableName) {
                    // Finde die Variable im Dummy-Modul basierend auf der Position
                    $variableID = IPS_GetObjectIDByIdent("Position" . ($position + 1), $dummyModuleID);

                    // Wenn die Variable nicht gefunden wird, versuche, sie zu erstellen
                    if ($variableID === false) {
                        $variableID = IPS_CreateVariable(2);  // Float
                        IPS_SetParent($variableID, $dummyModuleID);
                        IPS_SetName($variableID, $variableName);
                        IPS_SetIdent($variableID, "Position" . ($position + 1));  // Identifikation setzen
                        IPS_SetVariableCustomProfile($variableID, $profile);
                        IPS_SetPosition($variableID, $position + 1); // Positionen beginnen bei 1
                    }

                    // Merke die ID der Variable für spätere Aktualisierungen
                    $variableIDMap[$variableName] = $variableID;

                    // Setze den Wert der Float-Variable nach expliziter Konvertierung zu Float
                    $floatValue = (float) str_replace([',', '%', '°C'], ['.', '', ''], $valueData[$variableName]);

                    // Debug-Ausgabe für den Wert
                    //echo "Debug: Wert für Variable '{$variableName}': '{$valueData[$variableName]}' (Float: '{$floatValue}', Typ: '" . gettype($floatValue) . "')\n";

                    if (!is_nan($floatValue)) {
                        // Aktualisiere den Wert der Float-Variable
                        SetValue($variableIDMap[$variableName], $floatValue);
                    } else {
                        //echo "Fehler: Konnte Wert nicht in Float umwandeln für Variable '{$variableName}' (Wert: '{$valueData[$variableName]}').\n";
                    }
                }
            } else {
                echo "Werte konnten nicht extrahiert werden für Schlüssel: $key\n";
            }
        }
    }
}
?>
