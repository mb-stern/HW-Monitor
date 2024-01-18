<?php
class HWMonitor extends IPSModule
{
    // ... (andere Methoden und Eigenschaften)

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
        $contentArray = json_decode($content, true);

        $idListeString = $this->ReadPropertyString('IDListe');
        $idListe = json_decode($idListeString, true);

        // Variablen anlegen und einstellen für die Contentausgabe
        $JSON = "JSON_Content";
        $JSONIdent = "JSON_Content_Ident";
        $this->RegisterVariableString($JSONIdent, $JSON);
        SetValue($this->GetIDForIdent($JSONIdent), $content);

        // Variablen anlegen und einstellen für die ID-Ausgabe
        $IDs = "Registrierte_IDs";
        $IDsIdent = "Registrierte_IDs_Ident";
        $this->RegisterVariableString($IDsIdent, $IDs);
        SetValue($this->GetIDForIdent($IDsIdent), $idListeString);

        // Suche nach "id" in der ID-Liste
        $foundIds = [];

        foreach ($idListe as $idItem) {
            $gesuchteId = $idItem['id'];
            $foundIds[] = $gesuchteId;
        }

        // Variablen anlegen und einstellen für die gefundenen IDs
        foreach ($foundIds as $gesuchteId) {
            $variableIdent = "Variable_" . $gesuchteId;
            $variableIdExists = $this->GetIDForIdent($variableIdent);

            if ($variableIdExists === false) {
                // Die Variable für die ID existiert noch nicht, daher erstellen
                $this->RegisterVariableFloat($variableIdent, "Variable für ID $gesuchteId");
                SetValue($this->GetIDForIdent($variableIdent), $gesuchteId);
            }

            // Suche nach "Value" für die gefundenen IDs
            $foundValue = [];
            $this->searchValueForId($contentArray, $gesuchteId, $foundValue);

            // Variablen anlegen und einstellen für die gefundenen Werte
            foreach ($foundValue as $gefundenerWert) {
                $variableIdentValue = "Variable_" . $gesuchteId . "_Value";
                $variableIdValueExists = $this->GetIDForIdent($variableIdentValue);

                if ($variableIdValueExists === false) {
                    // Die Variable für den "Value" existiert noch nicht, daher erstellen
                    $this->RegisterVariableString($variableIdentValue, "Variable für Wert zur ID $gesuchteId");
                }

                SetValue($this->GetIDForIdent($variableIdentValue), $gefundenerWert);
            }
        }
    }
}
