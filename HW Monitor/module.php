<?php
public function Update()
{
    // Libre Hardware Monitor abfragen
    $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
    $contentArray = json_decode($content, true);

    // Debug senden
    $this->SendDebug("Verbindungseinstellung", "".$this->ReadPropertyString('IPAddress')." : ".$this->ReadPropertyInteger('Port')."", 0);

    // Gewählte ID's abfragen
    $idListeString = $this->ReadPropertyString('IDListe');
    $idListe = json_decode($idListeString, true);

    // Alle vorhandenen Variablen und Kategorien speichern
    $existingObjects = IPS_GetChildrenIDs($this->InstanceID);
    $newObjectIDs = [];

    // Schleife für die ID-Liste
    foreach ($idListe as $idItem) 
    {
        $gesuchteId = $idItem['id'];

        /// Suche nach Werten für die gefundenen IDs
        $foundValues = [];
        $this->searchValueForId($contentArray, $gesuchteId, $foundValues);

        // Kategorie für diese ID erstellen, falls noch nicht vorhanden
        $categoryName = $foundValues['Text'][0];
        $categoryID = @IPS_GetObjectIDByName($categoryName, $this->InstanceID);
        if ($categoryID === false) 
        {
            // Kategorie erstellen, wenn sie nicht existiert oder kein Kategorieobjekt ist
            $categoryID = IPS_CreateCategory();
            IPS_SetName($categoryID, $categoryName);
            IPS_SetParent($categoryID, $this->InstanceID);
        }

        // Variablen anlegen und einstellen für die gefundenen Werte
        $counter = 0;

        // Prüfe auf das Vorhandensein der Schlüssel 'Text', 'id', 'Min', 'Max', 'Value', 'Type'
        $requiredKeys = ['Text', 'id', 'Min', 'Max', 'Value', 'Type'];

        foreach ($requiredKeys as $searchKey) 
        {
            if (!array_key_exists($searchKey, $foundValues)) 
            {
                continue; // Schlüssel nicht vorhanden, überspringen
            }
        
            foreach ($foundValues[$searchKey] as $gefundenerWert) 
            {
                $variableIdentValue = "Variable_" . ($gesuchteId * 10 + $counter) . "_$searchKey";
                $variablePosition = $gesuchteId * 10 + $counter;
        
                $variableID = @IPS_GetObjectIDByIdent($variableIdentValue, $categoryID);
                if ($variableID === false) 
                {
                    // Variable erstellen
                    if (in_array($searchKey, ['Min', 'Max', 'Value'])) 
                    {
                        $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), ($this->getVariableProfileByType($foundValues['Type'][0])), $variablePosition);

                        // Ersetzungen für Float-Variablen anwenden
                        $gefundenerWert = (float)str_replace([',', '%', '°C'], ['.', '', ''], $gefundenerWert);
                    } 
                    elseif ($searchKey === 'id') 
                    {
                        $variableID = $this->RegisterVariableFloat($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                    } 
                    elseif ($searchKey === 'Text' || $searchKey === 'Type') 
                    {
                        $variableID = $this->RegisterVariableString($variableIdentValue, ucfirst($searchKey), "", $variablePosition);
                    }
        
                    // Setze das Elternobjekt
                    IPS_SetParent($variableID, $categoryID);
                } 
                else 
                {
                    // Variable bereits vorhanden, Wert aktualisieren
                    $convertedValue = ($searchKey === 'Text' || $searchKey === 'Type') ? (string)$gefundenerWert : (float)$gefundenerWert;
                    SetValue($variableID, $convertedValue);
                }
        
                $counter++;
            }
        }

        // Speichern der erstellten Objekte für die spätere Löschung
        $newObjectIDs[] = $categoryID;
        foreach (IPS_GetChildrenIDs($categoryID) as $childID) {
            $newObjectIDs[] = $childID;
        }
    }

    // Nicht mehr benötigte Objekte löschen
foreach ($existingObjects as $existingObjectID) 
{
    // Objektinformationen abrufen
    $existingObject = IPS_GetObject($existingObjectID);
    $objectName = $existingObject['ObjectName'];
    $objectType = $existingObject['ObjectType'];

    // Überprüfen, ob das vorhandene Objekt noch benötigt wird
    $keepObject = false;
    foreach ($newObjectIDs as $newObjectID) 
    {
        if ($existingObjectID == $newObjectID) 
        {
            $keepObject = true;
            break;
        }
    }

    // Wenn nicht mehr benötigt, löschen Sie das Objekt
    if (!$keepObject) 
    {
        if ($objectType == 0) 
        {
            // Variable löschen
            $this->UnregisterVariable($existingObjectID);
        } 
        elseif ($objectType == 1) 
        {
            // Kategorie löschen
            IPS_DeleteCategory($existingObjectID);
        }
    }
}

   
