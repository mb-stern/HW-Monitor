public function ApplyChanges()
{
    // Never delete this line!
    parent::ApplyChanges();

    // JSON von der URL abrufen und entpacken
    $content = file_get_contents("http://{$this->ReadPropertyString('IPAddress')}:{$this->ReadPropertyInteger('Port')}/data.json");
    echo "Raw Content:\n$content\n";

    $contentArray = json_decode($content, true);
    echo "Decoded Content:\n";
    print_r($contentArray);

    // Überprüfen, ob die JSON-Dekodierung erfolgreich war
    if (json_last_error() !== JSON_ERROR_NONE) {
        die('Fehler beim Dekodieren des JSON-Inhalts');
    }

    // ... restlicher Code ...

    // Durch die ID-Liste iterieren und passende IDs im Inhalt finden
    foreach ($idListe as $idItem) {
        $gesuchteId = $idItem['id'];

        // Direkt nach der ID im ContentArray suchen
        foreach ($contentArray as $item) {
            // Überprüfen, ob das Element ein Array ist
            if (is_array($item) && isset($item['id'])) {
                $jsonString = json_encode($item);
                $gesuchtesPräfix = '"id":' . $gesuchteId;

                if (strpos($jsonString, $gesuchtesPräfix) !== false) {
                    // Die gefundene ID ausgeben (als float)
                    $gefundeneId = (float) $gesuchteId;
                    echo "Gefundene ID: $gefundeneId\n";

                    // Hier kannst du die Variable erstellen oder den gefundenen Wert anderweitig verwenden
                    // Zum Beispiel:
                    $variableIdent = "Variable_" . $gefundeneId;
                    $this->RegisterVariableFloat($variableIdent, "Variable für ID $gefundeneId");
                    SetValue($this->GetIDForIdent($variableIdent), $gefundeneId);
                }
            }
        }
    }
}
