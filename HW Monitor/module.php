<?php
declare(strict_types=1);

class HWMonitor extends IPSModuleStrict
{
    public function Create(): void
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

    public function ApplyChanges(): void
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

    public function RequestAction(string $Ident, mixed $Value): void
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
    public function GetConfigurationForm(): string
    {
        // Form (symcon)
        $form = [
            'elements' => [
                [
                    'type'    => 'ValidationTextBox',
                    'name'    => 'IPAddress',
                    'caption' => 'IP-Adresse',
                    'width'   => '200px'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'Port',
                    'caption' => 'Port',
                    'minimum' => 1,
                    'maximum' => 65535,
                    'width'   => '120px'
                ],
                [
                    'type'    => 'NumberSpinner',
                    'name'    => 'UpdateInterval',
                    'caption' => 'Update-Intervall (Sek.)',
                    'minimum' => 0,
                    'maximum' => 3600,
                    'width'   => '120px'
                ],
                [
                    'type'    => 'Label',
                    'caption' => ' '
                ],
                [
                    'type'    => 'Label',
                    'caption' => 'Sensor-Auswahl:'
                ],
                [
                    'type'  => 'List',
                    'name'  => 'SelectedSensors',
                    'caption' => '',
                    'rowCount' => 12,
                    'add'   => false,
                    'delete'=> false,
                    'columns' => [
                        [
                            'caption' => 'Aktiv',
                            'name'    => 'enabled',
                            'width'   => '60px',
                            'add'     => false,
                            'edit'    => true,
                            'delete'  => false
                        ],
                        [
                            'caption' => 'Name',
                            'name'    => 'name',
                            'width'   => '240px',
                            'add'     => false,
                            'edit'    => false,
                            'delete'  => false
                        ],
                        [
                            'caption' => 'Unit',
                            'name'    => 'unit',
                            'width'   => '90px',
                            'add'     => false,
                            'edit'    => false,
                            'delete'  => false
                        ],
                        [
                            'caption' => 'Ident',
                            'name'    => 'ident',
                            'width'   => '220px',
                            'add'     => false,
                            'edit'    => false,
                            'delete'  => false
                        ]
                    ]
                ]
            ],
            'actions' => [
                [
                    'type'    => 'Button',
                    'caption' => 'Jetzt aktualisieren',
                    'onClick' => 'IPS_RequestAction(' . $this->InstanceID . ', "ManualUpdate", 0);'
                ]
            ]
        ];

        // Versuche Live-Sensorliste (optional)
        $ip = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');

        if ($ip !== '' && $ip !== '0.0.0.0' && $port > 0) {
            $url = 'http://' . $ip . ':' . $port . '/data.json';

            $raw = @file_get_contents($url);
            if ($raw !== false) {
                $rows = json_decode($raw, true);
                if (is_array($rows)) {
                    $selected = json_decode($this->ReadPropertyString('SelectedSensors'), true);
                    if (!is_array($selected)) {
                        $selected = [];
                    }

                    // Map: ident -> enabled
                    $selMap = [];
                    foreach ($selected as $s) {
                        if (isset($s['ident'])) {
                            $selMap[(string)$s['ident']] = (bool)($s['enabled'] ?? false);
                        }
                    }

                    // Liste bauen
                    $list = [];
                    foreach ($rows as $idx => $payload) {
                        $name = (string)($payload['Text'] ?? '');
                        $unit = (string)($payload['Unit'] ?? '');
                        $ident = $this->identFor((int)$idx, 'Value'); // default ident für Value
                        $list[] = [
                            'enabled' => $selMap[$ident] ?? false,
                            'name'    => $name,
                            'unit'    => $unit,
                            'ident'   => $ident
                        ];
                    }

                    // In Form einfügen
                    foreach ($form['elements'] as &$el) {
                        if (($el['type'] ?? '') === 'List' && ($el['name'] ?? '') === 'SelectedSensors') {
                            $el['values'] = $list;
                        }
                    }
                    unset($el);
                }
            }
        }

        return json_encode($form);
    }

    // ------------------------ Update ------------------------
    public function Update(): bool
    {
        // --- Auswahl laden (nur aktivierte Rows) ---
        $selected = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($selected)) {
            $selected = [];
        }

        $activeRows = [];
        foreach ($selected as $r) {
            if (!empty($r['enabled'])) {
                $uid = (string)($r['uid'] ?? '');
                $pos = (int)($r['pos'] ?? 0);
                $caption = (string)($r['caption'] ?? '');
                $type = (string)($r['type'] ?? '');
                if ($uid !== '') {
                    $activeRows[] = [
                        'uid'     => $uid,
                        'pos'     => $pos,
                        'caption' => $caption,
                        'type'    => $type
                    ];
                }
            }
        }

        $this->SendDebug('Update.ActiveRows', 'count=' . count($activeRows), 0);

        // Wenn nichts ausgewählt ist: nichts tun
        if (count($activeRows) === 0) {
            $this->SendDebug('Update.Info', 'keine aktiven Rows', 0);
            return true;
        }

        $ip = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');
        if ($ip === '' || $ip === '0.0.0.0' || $port <= 0) {
            $this->SendDebug('Update.Error', 'IP/Port ungültig', 0);
            return false;
        }

        // JSON holen
        $url = 'http://' . $ip . ':' . $port . '/data.json';
        $this->SendDebug('Update.URL', $url, 0);

        try {
            $raw = @file_get_contents($url);
            if ($raw === false) {
                throw new Exception('HTTP-Fehler oder leere Antwort');
            }

            $points = json_decode($raw, true);
            if (!is_array($points)) {
                throw new Exception('JSON ungültig');
            }
        } catch (Throwable $e) {
            $this->SendDebug('Update.Error', $e->getMessage(), 0);
            return false;
        }

        $this->SendDebug('Update.Sensors', 'im JSON: ' . count($points), 0);

        // Index nach UID, damit wir die ausgewählten Einträge schnell finden
        $byUid = [];
        foreach ($points as $p) {
            if (!is_array($p)) {
                continue;
            }
            $uid = (string)($p['UID'] ?? '');
            if ($uid !== '') {
                $byUid[$uid] = $p;
            }
        }

        // Track welche Idents gesehen wurden (für Cleanup)
        $seen = [];

        // Bestehende Variablen sammeln (nur direkt unter der Instanz)
        $existingIdents = [];
        $children = IPS_GetChildrenIDs($this->InstanceID);
        foreach ($children as $cid) {
            $obj = IPS_GetObject($cid);
            if ($obj['ObjectType'] === OBJECTTYPE_VARIABLE) {
                $ident = (string)($obj['ObjectIdent'] ?? '');
                if ($ident !== '') {
                    $existingIdents[$ident] = $cid;
                }
            }
        }

        foreach ($activeRows as $row) {
            $uidSel   = (string)$row['uid'];
            $pos      = (int)$row['pos'];
            $caption  = (string)$row['caption'];
            $typeSel  = (string)$row['type'];

            $payload = $byUid[$uidSel] ?? null;

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
                $this->RegisterVariableString($idText, "{$uidName} - Pfad", '', $basePos + 0);
                $vText = $this->GetIDForIdent($idText);
            } else {
                IPS_SetPosition($vText, $basePos + 0);
            }

            $pathVal   = (string)($payload['Text'] ?? $caption);
            $pathClean = trim(preg_replace('/\s*\[[^\]]*\]\s*$/', '', $pathVal));
            if ((string)GetValue($vText) !== $pathClean) {
                $this->SetValue($idText, $pathClean);
            }
            $seen[$idText] = true;

            // ---------- 2–4) Float: Min / Value / Max ----------
            foreach ([['Min',1], ['Value',2], ['Max',3]] as [$field, $offset]) {
                $ident = $this->identFor($pos, $field);
                $vid   = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);

                if ($vid === false) {
                    // Name nur beim Anlegen setzen – danach nicht mehr umbenennen
                    $this->RegisterVariableFloat($ident, "{$uidName} - {$field}", $profile, $basePos + $offset);
                    $vid = $this->GetIDForIdent($ident);
                } else {
                    IPS_SetPosition($vid, $basePos + $offset);
                    // kein IPS_SetName, kein Profil-Überschreiben!
                }

                $u = null;
                $num = $this->parseNumberWithUnit($payload[$field] ?? null, $u);
                if ($num !== null && (float)GetValue($vid) !== (float)$num) {
                    $this->SetValue($ident, $num);
                }
                $seen[$ident] = true;
            }
        }

        // Cleanup: alle „unsere“ Variablen entfernen, die diesmal nicht gesehen wurden
        foreach (array_keys($existingIdents) as $ident) {
            if (!isset($seen[$ident])) {
                $vid = $existingIdents[$ident];
                // nur Variablen löschen, die zu unserem Muster gehören
                // (dein Originalverhalten beibehalten)
                if (preg_match('/^S\d+_(Text|Min|Value|Max)$/', $ident)) {
                    IPS_DeleteVariable($vid);
                }
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
