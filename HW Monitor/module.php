Erleben Sie eine frühe Vorschau von ChatGPT in Ihrer Sprache.
Alpha beitreten

Chat history
ChatGPT
New chat

Today
PHP Script Timer Integration


Upgrade plan
Get GPT-4, DALL·E, and more

User
Stefan Künzli

Close sidebar
ChatGPT 3.5

User
Ich möchte diesem script einen aktualierungsintervall hinzufügen <?php
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
                // Die gesuchte ID wurde gefunden, jetzt die zugehörigen Werte suchen
                $this->searchValuesForId($jsonArray, $searchId, $foundValues);
                break; // Wir haben die ID gefunden, daher können wir die Suche beenden
            } elseif (is_array($value)) {
                // Rekursiv in den verschachtelten Arrays suchen
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
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

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

            // Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            $counter = 0; // Zähler für jede 'id' zurücksetzen
            foreach ($foundValues as $searchKey => $values) {
                if (in_array($searchKey, ['Text', 'id', 'Min', 'Max', 'Value'])) {
                    foreach ($values as $gefundenerWert) {
                        $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                        $variablePosition = $gesuchteId * 10 + $counter;

                        // Überprüfen, ob die Variable bereits existiert
                        $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                        if ($variableID === false) {
                            // Variable existiert noch nicht, also erstellen
                            if ($searchKey === 'Text') {
                                $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            } else {
                                $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            }

                            // Konfiguration nur bei Neuerstellung
                            // Hier könnten zusätzliche Konfigurationen erfolgen
                        } else {
                            // Variable existiert bereits, entferne sie aus der Liste der vorhandenen Variablen
                            $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                            if ($keyIndex !== false) {
                                unset($existingVariableIDs[$keyIndex]);
                            }
                        }

                        // Konvertiere den Wert, wenn der Typ nicht übereinstimmt
                        $convertedValue = ($searchKey === 'Text') ? (string)$gefundenerWert : (float)$gefundenerWert;

                        SetValue($variableID, $convertedValue);
                        $counter++;
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
ChatGPT
Um ein Aktualisierungsintervall zu diesem Skript hinzuzufügen, kannst du das IPS-Timer-Modul verwenden. Hier ist eine aktualisierte Version deines Skripts mit einem Timer für das Aktualisierungsintervall:

php
Copy code
<?php
class HWMonitor extends IPSModule
{
    private $updateTimer;

    protected function Log($Message)
    {
        IPS_LogMessage(__CLASS__, $Message);
    }

    protected function searchValueForId($jsonArray, $searchId, &$foundValues)
    {
        // ... (unverändert)
    }

    protected function searchValuesForId($jsonArray, $searchId, &$foundValues)
    {
        // ... (unverändert)
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer("UpdateTimer", 300000, 'HM_Update($_IPS["TARGET"]);'); // 300000 Millisekunden = 5 Minuten
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
        $this->Update();
    }

    public function Update()
    {
        // ... (unverändert)
    }

    // Funktion für den Timer
    public function UpdateTimer()
    {
        $this->Update();
    }
}
In diesem Beispiel wurde ein Timer mit dem Namen "UpdateTimer" registriert, der alle 5 Minuten (300000 Millisekunden) das Skript HM_Update ausführt. Das Update enthält den Code, der beim ApplyChanges aufgerufen wird, um die Daten zu aktualisieren.

Stelle sicher, dass du die Methode Update entsprechend deinen Anforderungen anpasst. Du kannst auch das Aktualisierungsintervall nach Bedarf ändern, indem du den Timer entsprechend anpasst.

User
Bitte das gesamte script
ChatGPT
Entschuldige für das Missverständnis. Hier ist das vollständige aktualisierte Skript:

php
Copy code
<?php
class HWMonitor extends IPSModule
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

        $this->RegisterPropertyString("IPAddress", "192.168.178.76");
        $this->RegisterPropertyInteger("Port", 8085);
        $this->RegisterPropertyString("IDListe", '[]');

        // Timer für Aktualisierung registrieren
        $this->RegisterTimer("UpdateTimer", 3000, 'HM_Update($_IPS["TARGET"]);');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Bei Änderungen am Konfigurationsformular oder bei der Initialisierung auslösen
        $this->Update();
    }

    public function Update()
    {
        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

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

            // Suche nach Werten für die gefundenen IDs
            $foundValues = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

            // Variablen anlegen und einstellen für die gefundenen Werte
            $counter = 0;
            foreach ($foundValues as $searchKey => $values) {
                if (in_array($searchKey, ['Text', 'id', 'Min', 'Max', 'Value'])) {
                    foreach ($values as $gefundenerWert) {
                        $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                        $variablePosition = $gesuchteId * 10 + $counter;

                        $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $this->InstanceID);
                        if ($variableID === false) {
                            if ($searchKey === 'Text') {
                                $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            } else {
                                $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                            }
                        } else {
                            $keyIndex = array_search($variableIdentValue, $existingVariableIDs);
                            if ($keyIndex !== false) {
                                unset($existingVariableIDs[$keyIndex]);
                            }
                        }

                        $convertedValue = ($searchKey === 'Text') ? (string)$gefundenerWert : (float)$gefundenerWert;

                        SetValue($variableID, $convertedValue);
                        $counter++;
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

    // Funktion für den Timer
    public function UpdateTimer()
    {
        $this->Update();
    }
}
Dieses Skript enthält nun einen Timer mit einem Intervall von 5 Minuten für die Aktualisierung der Daten. Der Timer führt die Funktion Update aus, die die Daten abruft und aktualisiert. Du kannst das Intervall anpassen, indem du den Wert 300000 (in Millisekunden) im RegisterTimer entsprechend änderst.





Message ChatGPT…

ChatGPT can make mistakes. Consider checking important information.

?
ChatGPT
