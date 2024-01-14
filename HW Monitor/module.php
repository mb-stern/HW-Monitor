<?php

class YourClassName extends IPSModule
{
    public function Create()
    {
        // Initialisierung beim Erstellen der Instanz
    }

    public function ApplyChanges()
    {
        // Konfiguration nach Änderungen übernehmen
    }

    public function GetDataFromUrl()
    {
        // JSON von der URL abrufen und entpacken
        $content = file_get_contents("http://192.168.178.76:8085/data.json");
        $value = json_decode($content, true);

        // JSON-Array aus der Property 'IDListe' holen
        $idListeString = $this->ReadPropertyString('IDListe');
        $idListeArray = json_decode($idListeString, true);

        // Variablen anlegen und einstellen für die Contentausgabe
        $JSON = "JSON-Content"; // Geben Sie einen geeigneten Namen ein
        $JSONIdent = "JSONIdent"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);

        // Variablen anlegen und einstellen für die ID-Ausgabe zur Überprüfung
        $IDs = "Registrierte IDs"; // Geben Sie einen geeigneten Namen ein
        $IDsIdent = "IDsIdent"; // Geben Sie eine geeignete Identifikation ein
        $this->RegisterVariableString($IDsIdent, $IDs);
        $formattedString = $this->formatArrayToString($idListeArray);
        SetValue($this->GetIDForIdent($IDsIdent), $formattedString);

        // Gewünschte "id" festlegen
        $desiredId = '0'; // Änderung: Die ID ist normalerweise eine Zahl, kein JSON-String

        // Suche nach der gewünschten "id" im JSON-Baum
        $result = $this->searchById($value, $desiredId);

        // Ausgabe der gefundenen Daten
        if ($result) {
            echo "Gefundene Daten für id $desiredId:\n";
            print_r($result);

            // Variablennamen erstellen
            $variableName = 'Temperature_' . str_replace(' ', '_', $result['Text']);

            // Überprüfen, ob die Variable bereits existiert, andernfalls erstellen
            if (!IPS_VariableExists($variableName)) {
                $variableID = IPS_CreateVariable(2); // Float-Variablentyp
                IPS_SetName($variableID, $variableName);
                IPS_SetParent($variableID, 0 /* ID des Objektbaums, in dem die Variable erstellt werden soll */);
            } else {
                $variableID = IPS_GetVariableIDByName($variableName, 0 /* ID des Objektbaums */);
            }

            // Wert der Variable setzen
            $value = floatval(str_replace(' °C', '', $result['Value'])); // Annahme: Wert ist in der Form "xx.x °C"
            SetValue($variableID, $value);

            echo "Float-Variable $variableName mit Wert $value erstellt/aktualisiert.\n";
        } else {
            echo "Die id $desiredId wurde nicht gefunden.\n";
        }
    }

    // Funktion zum Formatieren des JSON-Arrays in einen String zur Ausgabe in eine Variable
    private function formatArrayToString($array)
    {
        $formattedString = '';

        if (is_array($array)) {
            foreach ($array as $item) {
                if (is_array($item)) {
                    foreach ($item as $key => $ids) {
                        $formattedString .= "$ids,";
                    }
                }
            }
        }

        // Entfernen Sie das letzte Komma und Leerzeichen
        $formattedString = rtrim($formattedString, ', ');

        return $formattedString;
    }

    // Funktion zum Durchsuchen des Baums nach der gewünschten "id"
    private function searchById($array, $id)
    {
        foreach ($array as $item) {
            if ($item['id'] == $id) {
                return $item;
            }
            if (!empty($item['Children'])) {
                $result = $this->searchById($item['Children'], $id);
                if ($result) {
                    return $result;
                }
            }
        }
        return null;
    }
}
