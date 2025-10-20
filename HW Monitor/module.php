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
                    'caption' => 'Ausgewählte Sensoren aktualisieren',
                    'onClick' => 'IPS_RequestAction($id, "ManualUpdate", 0);'
                ]
            ],
               ['type' => 'Label',  'caption' => 'Sag danke und unterstütze den Modulentwickler:'],
                [
                    'type'  => 'RowLayout',
                    'items' => [
                        [
                            'type'   => 'Image',
                            'onClick'=> "echo 'https://paypal.me/mbstern';",
                             "image"=> "data:image/jpeg;base64,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"
                        ],
                        ['type' => 'Label', 'caption' => '']
                    ]
                ]            
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

            // Profil & Position
            $type    = (string)($payload['Type'] ?? $typeSel);
            $profile = $this->getVariableProfileByType($type);
            $basePos = $pos * 10;

            // Für die ANZEIGENAMEN: UID (normalisiert, damit %7B...%7D lesbar wird)
            $uidName = $this->normalizeUid($uidSel);

            // ---------- 1) String: Pfad (Ident bleibt _Text) ----------
            $idText = $this->identFor($pos, 'Text');
            $vText  = @IPS_GetObjectIDByIdent($idText, $this->InstanceID);
            if ($vText === false) {
                // Name nur beim Anlegen setzen – danach nicht mehr umbenennen
                $vText = $this->RegisterVariableString($idText, "{$uidName} - Pfad", '', $basePos + 0);
            } else {
                IPS_SetPosition($vText, $basePos + 0);
            }

            // Wert für die Pfad-Variable: dein bisheriger Pfad ohne [Typ]
            $pathVal   = $caption !== '' ? $caption : (string)($payload['Text'] ?? '');
            $pathClean = trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $pathVal));
            if ((string)GetValue($vText) !== $pathClean) {
                SetValue($vText, $pathClean);
            }
            $seen[$idText] = true;

            // ---------- 2–4) Float: Min / Value / Max ----------
            foreach ([['Min',1], ['Value',2], ['Max',3]] as [$field, $offset]) {
                $ident = $this->identFor($pos, $field);
                $vid   = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

                if ($vid === false) {
                    // Name nur beim Anlegen setzen – danach nicht mehr umbenennen
                    $vid = $this->RegisterVariableFloat($ident, "{$uidName} - {$field}", $profile, $basePos + $offset);
                } else {
                    IPS_SetPosition($vid, $basePos + $offset);
                    // kein IPS_SetName, kein Profil-Überschreiben!
                }

                $u = null;
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
