<?php
declare(strict_types=1);

class HWMonitor extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Eigenschaften
        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyInteger('UpdateInterval', 30);
        $this->RegisterPropertyString('SelectedSensors', '[]');

        $this->RegisterTimer('UpdateTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "DoUpdate", 0);');

        $this->createVariableProfiles();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SendDebug('ApplyChanges', 'Start', 0);

        $intervalMs = max(0, $this->ReadPropertyInteger('UpdateInterval')) * 1000;
        $this->SetTimerInterval('UpdateTimer', $intervalMs);
        $this->SendDebug('ApplyChanges', 'Timer gesetzt: ' . $intervalMs . ' ms', 0);

        $ip = $this->ReadPropertyString('IPAddress');
        if ($ip === '' || $ip === '0.0.0.0') {
            $this->SendDebug('ApplyChanges', 'Abbruch: IP nicht gesetzt', 0);
            return;
        }

        $ok = $this->Update();
        $this->SendDebug('ApplyChanges', 'Update() -> ' . ($ok ? 'OK' : 'FEHLER'), 0);
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'DoUpdate':       // Timer
            case 'ManualUpdate':   // Button
                $this->SendDebug('RequestAction', $Ident . ' -> Update()', 0);
                $this->Update();
                return;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    // ------------------------ Formular ------------------------
    public function GetConfigurationForm()
    {
        $error = '';
        $options = [];

        $ip   = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');

        // Überschrift/Label oben: URL oder Hinweis
        $urlCaption = ($ip !== '' && $ip !== '0.0.0.0')
            ? "Quelle: http://{$ip}:{$port}"
            : "Quelle: (Bitte IP-Adresse konfigurieren)";

        try {
            if ($ip !== '' && $ip !== '0.0.0.0') {
                $data    = $this->getData();              // live abrufen
                $options = $this->buildOptions($data);    // nur echte Sensor-Blätter
            } else {
                $error = 'Bitte IP-Adresse konfigurieren.';
            }
        } catch (Exception $e) {
            $error = 'Scan: ' . $e->getMessage();
        }

        // Bisherige Auswahl mergen
        $saved = json_decode($this->ReadPropertyString('SelectedSensors'), true) ?: [];
        $byUID = [];
        foreach ($saved as $r) {
            if (!empty($r['uid'])) {
                $byUID[$r['uid']] = $r;
            }
        }

        $values = [];
        $posSuggest = 1;
        foreach ($options as $opt) {
            $prev = $byUID[$opt['uid']] ?? null;
            $values[] = [
                'active'  => (bool)($prev['active'] ?? false),
                'pos'     => isset($prev['pos']) && (int)$prev['pos'] > 0 ? (int)$prev['pos'] : $posSuggest++,
                'caption' => $opt['caption'],   // „Pfad“ in der Tabelle
                'type'    => $opt['type'],
                'uid'     => $opt['uid'],
                'icon'    => $opt['icon'] ?? ''
            ];
        }

        $form = [
            'elements' => [
                ['type' => 'Label', 'caption' => $urlCaption],

                ['type' => 'ValidationTextBox', 'name' => 'IPAddress', 'caption' => 'IP-Adresse'],
                ['type' => 'NumberSpinner',     'name' => 'Port',      'caption' => 'Port', 'minimum' => 1, 'maximum' => 65535],
                ['type' => 'NumberSpinner',     'name' => 'UpdateInterval', 'caption' => 'Updateintervall (Sek.)', 'minimum' => 0, 'suffix' => 's'],

                [
                    'type'     => 'List',
                    'name'     => 'SelectedSensors',   // muss exakt der Property entsprechen
                    'caption'  => 'Sensoren',
                    'rowCount' => 16,
                    'add'      => false,
                    'delete'   => false,
                    'sort'     => ['column' => 'pos', 'direction' => 'ascending'],
                    'columns'  => [
                        [
                            'caption' => 'Aktiv',
                            'name'    => 'active',
                            'width'   => '70px',
                            'align'   => 'center',
                            'edit'    => ['type' => 'CheckBox']
                        ],
                        [
                            'caption' => 'Pos.',
                            'name'    => 'pos',
                            'width'   => '70px',
                            'align'   => 'center',
                            'edit'    => ['type' => 'NumberSpinner', 'minimum' => 1, 'maximum' => 9999]
                        ],
                        // Spaltenüberschrift „Pfad“ (anstatt „Name“)
                        [
                            'caption' => 'Pfad',
                            'name'    => 'caption',
                            'width'   => 'auto',
                            'save'    => true,
                            'edit'    => ['type' => 'ValidationTextBox', 'enabled' => false]
                        ],
                        [
                            'caption' => 'Type',
                            'name'    => 'type',
                            'width'   => '120px',
                            'save'    => true,
                            'edit'    => ['type' => 'ValidationTextBox', 'enabled' => false]
                        ],
                        [
                            'caption' => 'UID',
                            'name'    => 'uid',
                            'width'   => '420px',
                            'save'    => true,
                            'edit'    => ['type' => 'ValidationTextBox', 'enabled' => false]
                        ],
                    ],
                    'values'   => $values
                ],

                ['type' => 'Label', 'caption' => ($error ?: 'Bereit.')]
            ],
            'actions' => [
                [
                    'type'    => 'Button',
                    'caption' => 'Jetzt aktualisieren (ausgewählte Daten)',
                    'onClick' => 'IPS_RequestAction($id, "ManualUpdate", 0);'
                ]
            ],
            'status'  => []
        ];

        return json_encode($form);
    }

    // ------------------------ Update ------------------------
    public function Update(): bool
    {
        // --- Auswahl laden & debuggen
        $raw = $this->ReadPropertyString('SelectedSensors');
        $this->SendDebug('SelectedSensors.raw', $raw === '' ? '(empty)' : $raw, 0);

        $rows = json_decode($raw, true);
        if (!is_array($rows)) { $rows = []; }

        // aktive Zeilen herausfiltern
        $activeRows = [];
        foreach ($rows as $r) {
            $uid = (string)($r['uid'] ?? '');
            $pos = (int)($r['pos'] ?? 0);
            $activeFlag = $r['active'] ?? false;
            $active = ($activeFlag === true) || ($activeFlag === 1) || ($activeFlag === '1');

            if ($active && $uid !== '') {
                if ($pos <= 0) { $pos = 0; } // wird unten automatisch vergeben
                $activeRows[] = [
                    'uid'     => $uid,
                    'pos'     => $pos,
                    'caption' => (string)($r['caption'] ?? ''),
                    'type'    => (string)($r['type'] ?? '')
                ];
            }
        }
        $this->SendDebug('Update.ActiveRows', 'count=' . count($activeRows), 0);

        // existierende Idents sammeln (brauchen wir gleich fürs Cleanup)
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') { $existingIdents[$ident] = true; }
        }

        if (empty($activeRows)) {
            // KEINE Häkchen -> ALLE unsere Variablen entfernen
            $removed = 0;
            foreach (array_keys($existingIdents) as $ident) {
                if ($this->isOurIdent($ident)) {
                    $this->UnregisterVariable($ident);
                    $removed++;
                }
            }
            $this->SendDebug('Update', 'Keine aktiven Zeilen -> Cleanup, entfernt: '.$removed, 0);
            return true;
        }

        // Auto-Positionen für pos==0
        $nextPos = 1;
        $usedPos = [];
        foreach ($activeRows as &$r) {
            $p = (int)$r['pos'];
            if ($p <= 0) {
                while (isset($usedPos[$nextPos])) { $nextPos++; }
                $r['pos'] = $nextPos;
                $this->SendDebug('AutoPos', $r['uid'] . ' -> pos=' . $nextPos, 0);
                $usedPos[$nextPos] = true;
                $nextPos++;
            } else {
                $usedPos[$p] = true;
            }
        }
        unset($r);

        // Daten holen
        try {
            $data = $this->getData();
        } catch (Exception $e) {
            $this->SendDebug('Update.Error', $e->getMessage(), 0);
            $this->LogMessage($e->getMessage(), KL_ERROR);
            return false;
        }

        // Sensor-Payloads sammeln (sowohl kodiert als auch normalisiert indexieren)
        $points = [];
        $this->collectSensors($data, [], $points);
        $this->SendDebug('Update.Sensors', 'im JSON: ' . count($points), 0);

        // existierende Idents der Instanz
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') { $existingIdents[$ident] = true; }
        }

        $seen = [];

        // Für jede aktive Zeile die Vierergruppe anlegen/aktualisieren
        foreach ($activeRows as $r) {
            $uidSel  = $r['uid'];
            $pos     = (int)$r['pos'];
            $caption = (string)$r['caption'];
            $typeSel = (string)$r['type'];

            // Payload finden (direkt oder normalisiert)
            $payload = $points[$uidSel] ?? $points[$this->normalizeUid($uidSel)] ?? null;

            if ($payload === null) {
                // Platzhalter-Payload, falls Quelle nicht (mehr) existiert
                $payload = [
                    'Text'  => $caption,
                    'Type'  => $typeSel,
                    'Min'   => null,
                    'Value' => null,
                    'Max'   => null
                ];
                $this->SendDebug('Update.Warn', 'UID nicht im JSON gefunden: ' . $uidSel, 0);
            }

            // Profil, Position, Basisnamen
            $type    = (string)($payload['Type'] ?? $typeSel);
            $profile = $this->getVariableProfileByType($type);
            $basePos = $pos * 10;

            // Voller Pfad (aus Caption) für den Variablen-WERT der String-Variable
            $pathVal   = $caption !== '' ? $caption : (string)($payload['Text'] ?? '');
            $pathClean = trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $pathVal)); // [Typ]-Anhang entfernen

            // Leaf ermitteln (nur letzter Teil) für die ANZEIGENAMEN beim Anlegen
            $leaf = $pathClean;
            if (strpos($leaf, '›') !== false) {
                $parts = array_map('trim', explode('›', $leaf));
                $leaf  = end($parts) ?: $leaf;
            }
            // Typ ohne Klammern ggf. anhängen (Duplikate vermeiden)
            $prettyPrefix = $leaf;
            if ($type !== '' && stripos(' ' . $leaf . ' ', ' ' . $type . ' ') === false) {
                $prettyPrefix = trim($leaf . ' ' . $type);
            }

            // ---------- 1) String: Pfad (Ident bleibt _Text) ----------
            $idText = $this->identFor($pos, 'Text');
            $vText  = @IPS_GetObjectIDByIdent($idText, $this->InstanceID);
            if ($vText === false) {
                // sichtbarer Name NUR beim Anlegen
                $vText = $this->RegisterVariableString($idText, "{$prettyPrefix} - Pfad", '', $basePos + 0);
            } else {
                // keine Umbenennung; nur Position aktualisieren ist unkritisch
                IPS_SetPosition($vText, $basePos + 0);
            }
            // Wert: kompletter Pfad (ohne [Typ])
            if ((string)GetValue($vText) !== $pathClean) {
                SetValue($vText, $pathClean);
            }
            $seen[$idText] = true;

            // ---------- 2–4) Float: Min / Value / Max ----------
            foreach ([['Min',1], ['Value',2], ['Max',3]] as [$field, $offset]) {
                $ident = $this->identFor($pos, $field);
                $vid   = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

                if ($vid === false) {
                    // sichtbarer Name NUR beim Anlegen
                    $vid = $this->RegisterVariableFloat($ident, "{$prettyPrefix} - {$field}", $profile, $basePos + $offset);
                } else {
                    // keine Umbenennung/Profil-Überschreibung; Position ggf. setzen
                    IPS_SetPosition($vid, $basePos + $offset);
                }

                // Wert setzen (Messwert darf überschrieben werden)
                $u   = null;
                $num = $this->parseNumberWithUnit($payload[$field] ?? null, $u);
                if ($num !== null && (float)GetValue($vid) !== (float)$num) {
                    SetValue($vid, $num);
                }

                $seen[$ident] = true;
            }
        }

        // Cleanup: alle „unsere“ Variablen entfernen, die diesmal nicht gesehen wurden
        foreach (array_keys($existingIdents) as $ident) {
            if (!isset($seen[$ident]) && $this->isOurIdent($ident)) {
                $this->UnregisterVariable($ident);
            }
        }

        $this->SendDebug('Update.Done', 'ok', 0);
        return true;
    }

    // ------------------------ Datenerfassung ------------------------
    private function getData(): array
    {
        $ip   = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');
        if ($ip === '' || $ip === '0.0.0.0') {
            throw new Exception('IP-Adresse ist nicht gesetzt.');
        }
        $url = "http://{$ip}:{$port}/data.json";

        $ctx = stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
        $content = @file_get_contents($url, false, $ctx);
        if ($content === false) {
            throw new Exception("HTTP-Fehler beim Abruf von {$url}");
        }
        $data = json_decode($content, true);
        if ($data === null) {
            throw new Exception("Ungültiges JSON von {$url}");
        }
        return $data;
    }

    // nur echte Sensor-Blätter sammeln: uid => payload
    private function collectSensors(array $node, array $ancestors, array &$map): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node)) {
            // UID in Roh- und Normalform
            $uidRaw = $this->buildUID($node, $ancestors);
            $uidNor = $this->normalizeUid($uidRaw);

            $payload = [
                'Text'     => $node['Text']     ?? '',
                'Type'     => $node['Type']     ?? '',
                'Min'      => $node['Min']      ?? null,
                'Value'    => $node['Value']    ?? null,
                'Max'      => $node['Max']      ?? null,
                'SensorId' => $node['SensorId'] ?? ''
            ];
            if (!isset($map[$uidRaw])) $map[$uidRaw] = $payload;
            if (!isset($map[$uidNor])) $map[$uidNor] = $payload;
        }

        if (!empty($node['Children']) && is_array($node['Children'])) {
            foreach ($node['Children'] as $child) {
                if (is_array($child)) {
                    $this->collectSensors($child, $currentAnc, $map);
                }
            }
        }
    }

    private function isSensorLeaf(array $node): bool
    {
        if (!empty($node['SensorId'])) {
            return true;
        }
        $hasChildren = !empty($node['Children']) && is_array($node['Children']);
        if ($hasChildren) {
            return false;
        }
        if (!empty($node['Text'])) {
            return true;
        }
        foreach (['Min','Value','Max'] as $k) {
            $u = null;
            if ($this->parseNumberWithUnit($node[$k] ?? null, $u) !== null) {
                return true;
            }
        }
        return false;
    }

    private function buildUID(array $node, array $ancestors): string
    {
        if (!empty($node['SensorId'])) {
            // Roh behalten (kann %7B ... %7D usw. enthalten)
            return 'sensor:' . (string)$node['SensorId'];
        }
        $parts = [];
        foreach ($ancestors as $a) {
            if (!empty($a['Text'])) { $parts[] = (string)$a['Text']; }
        }
        if (!empty($node['Text'])) { $parts[] = (string)$node['Text']; }
        $type = (string)($node['Type'] ?? '');
        return 'path:' . implode('/', $parts) . ($type !== '' ? '|' . $type : '');
    }

    private function normalizeUid(string $uid): string
    {
        // sowohl 'sensor:' als auch 'path:' belassen, nur den sensor-Teil rawurldecoden
        if (strpos($uid, 'sensor:') === 0) {
            $sid = substr($uid, 7); // hinter 'sensor:'
            // doppelt kodierte Anteile ebenfalls entschärfen
            $dec1 = rawurldecode($sid);
            $dec2 = rawurldecode($dec1);
            return 'sensor:' . $dec2;
        }
        return $uid;
    }

    // ------------------------ Helfer ------------------------
    private function loadSelectedRows(): array
    {
        $raw = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($raw)) { return []; }
        $rows = [];
        foreach ($raw as $r) {
            $rows[] = [
                'active'  => (bool)($r['active'] ?? false),
                'pos'     => (int)($r['pos'] ?? 0),
                'uid'     => (string)($r['uid'] ?? ''),
                'caption' => (string)($r['caption'] ?? ''),
                'type'    => (string)($r['type'] ?? ''),
                'icon'    => (string)($r['icon'] ?? '')
            ];
        }
        return $rows;
    }

    private function identFor(int $pos, string $field): string
    {
        return 'Variable_' . $pos . '_' . $field; // Pos*10 wird über Positionsparameter gesetzt
    }

    private function isOurIdent(string $ident): bool
    {
        return (bool)preg_match('/^Variable_\d+_(Text|Min|Value|Max)$/', $ident);
    }

    private function parseNumberWithUnit($raw, ?string &$unitOut = null): ?float
    {
        if ($raw === null) { return null; }
        if (is_numeric($raw)) { $unitOut = ''; return (float)$raw; }
        if (!is_string($raw)) { return null; }
        if (preg_match('/([-+]?\d+(?:\.\d+)?)\s*([%°A-Za-z\/\.]+)?/u', $raw, $m)) {
            $unitOut = isset($m[2]) ? trim($m[2]) : '';
            return (float)$m[1];
        }
        return null;
    }

    // ------------------------ Profile ------------------------
    private function createVariableProfiles(): void
    {
        $profiles = [
            'HW.Clock' => [2, 0, 6000, 1, 0, ' MHz'],
            'HW.Data'  => [2, 0, 4096, 1, 1, ' GB'],
            'HW.Temp'  => [2, -40, 125, 1, 0, ' °C'],
            'HW.Fan'   => [2, 0, 6000, 1, 0, ' RPM'],
            'HW.Rate'  => [2, 0, 100000, 1, 0, ' KB/s'],
        ];
        foreach ($profiles as $name => [$type, $min, $max, $step, $digits, $suffix]) {
            if (!IPS_VariableProfileExists($name)) {
                IPS_CreateVariableProfile($name, $type);
            }
            IPS_SetVariableProfileValues($name, $min, $max, $step);
            IPS_SetVariableProfileDigits($name, $digits);
            IPS_SetVariableProfileText($name, '', $suffix);
        }
    }

    protected function getVariableProfileByType($type): string
    {
        $type = is_string($type) ? ucfirst(strtolower($type)) : '';
        $map = [
            'Clock'       => 'HW.Clock',
            'Load'        => '~Progress',
            'Temperature' => 'HW.Temp',
            'Fan'         => 'HW.Fan',
            'Voltage'     => '~Volt',
            'Power'       => '~Watt',
            'Data'        => 'HW.Data',
            'Level'       => '~Progress',
            'Throughput'  => 'HW.Rate',
            'Frequency'   => 'HW.Clock',
            'Usage'       => '~Progress',
            'Memory'      => 'HW.Data',
            'Bandwidth'   => 'HW.Rate',
            ''            => '~Intensity.100'
        ];
        return $map[$type] ?? '~Intensity.100';
    }

    // ------------------------ Options (Form) ------------------------
    private function buildOptions(array $data): array
    {
        $out = [];
        $this->walkForOptions($data, [], $out);
        usort($out, fn($a,$b) => strcmp($a['caption'], $b['caption']));
        return $out;
    }

    private function walkForOptions(array $node, array $ancestors, array &$out): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node)) {
            $uid = $this->buildUID($node, $ancestors);

            $parts = [];
            foreach ($ancestors as $a) {
                if (!empty($a['Text'])) { $parts[] = (string)$a['Text']; }
            }
            if (!empty($node['Text'])) { $parts[] = (string)$node['Text']; }
            $type = (string)($node['Type'] ?? '');
            $caption = implode(' › ', $parts) . ($type ? '  [' . $type . ']' : '');

            $out[] = [
                'uid'     => $uid,
                'caption' => $caption,
                'type'    => $type,
                'icon'    => (string)($node['ImageURL'] ?? '')
            ];
        }

        if (!empty($node['Children']) && is_array($node['Children'])) {
            foreach ($node['Children'] as $child) {
                if (is_array($child)) {
                    $this->walkForOptions($child, $currentAnc, $out);
                }
            }
        }
    }
}
