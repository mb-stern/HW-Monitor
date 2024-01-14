<?php

// Beispiel-Inhaltsdaten
$content = '{"items":[{"id":1,"name":"Item 1"},{"id":2,"name":"Item 2"},{"id":3,"name":"Item 3"},{"id":4,"name":"Item 4"},{"id":5,"name":"Item 5"},{"id":13,"name":"Item 13"}]}';

// Beispiel-ID-Liste als String
$idListeString = '[{"id":4},{"id":13}]';

// ID-Liste als assoziatives Array dekodieren
$idListe = json_decode($idListeString, true);

// Inhaltsdaten als assoziatives Array dekodieren
$contentArray = json_decode($content, true);

// Überprüfen, ob die JSON-Dekodierung erfolgreich war
if (json_last_error() !== JSON_ERROR_NONE) {
    die('Fehler beim Dekodieren des JSON-Inhalts');
}

// Überprüfen, ob das erwartete Schlüssel "items" im Array vorhanden ist
if (!isset($contentArray['items'])) {
    die('Der erwartete Schlüssel "items" ist im JSON-Inhalt nicht vorhanden');
}

// Durch die ID-Liste iterieren und passende IDs im Inhalt finden
foreach ($idListe as $idItem) {
    $gesuchteId = $idItem['id'];

    foreach ($contentArray['items'] as $item) {
        if ($item['id'] === $gesuchteId) {
            // Die gefundene ID ausgeben (als float)
            $gefundeneId = (float)$item['id'];
            echo "Gefundene ID: $gefundeneId\n";
            break;
        }
    }
}
?>
