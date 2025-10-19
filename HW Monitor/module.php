<?php
declare(strict_types=1);

class HWMonitor extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // ========== Properties ==========
        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyInteger('UpdateInterval', 30); // Sekunden
        $this->RegisterPropertyBoolean('AutoCleanup', true);

        // Liste von Zeilen:
        // [{active:bool, pos:int, uid:string, caption:string, type:string, icon:string}]
        $this->RegisterPropertyString('SelectedSensors', '[]');

        // ========== Timer ==========
        $this->RegisterTimer(
            'UpdateTimer',
            0,
            'IPS_RequestAction(' . $this->InstanceID . ', "DoUpdate", 0);'
        );

        // ========== Profiles ==========
        $this->createVariableProfiles();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Timer setzen
        $intervalMs = max(0, $this->ReadPropertyInteger('UpdateInterval')) * 1000;
        $this->SetTimerInterval('UpdateTimer', $intervalMs);

        // Verbindung prüfen
        $ip = $this->ReadPropertyString('IPAddress');
        if ($ip === '' || $ip === '0.0.0.0') {
            $this->SendDebug('Konfiguration', 'IP-Adresse ist nicht konfiguriert', 0);
            return;
        }

        // WICHTIG: Direkt nach Übernehmen Variablen anlegen/aktualisieren
        $this->Update();
    }

    // ===================== RequestAction =====================
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'DoUpdate':
                $this->Update();
                break;

            case 'Refresh':
                $this->UpdateFormField('DummyInfo', 'caption', 'Letztes Einlesen: ' . date('H:i:s'));
                break;

            case 'AutoPositions':
                // UI-Helfer: zeigt nur Status an – Speichern muss der Nutzer (Übernehmen)
                $this->UpdateFormField('DummyInfo', 'caption', 'Positionsvorschlag gesetzt (1..N). Bitte Übernehmen.');
                break;

            case 'Diag':
                $rows = $this->loadSelectedRows();
                $active = array_values(array_filter($rows, fn($r) => !empty($r['active']) && !empty($r['uid'])));
                $this->SendDebug('Diag.Selected.ActiveRows', json_encode($active, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 0);
                try {
                    $data = $this->getData();
                    $points = [];
                    $this->traverseSensors($data, [], $points);
                    $this->SendDebug('Diag.JSON.SensorCount', strval(count($points)), 0);
                    // zeige mal die ersten 5 UIDs
                    $uids = array_slice(array_keys($points), 0, 5);
                    $this->SendDebug('Diag.JSON.SampleUIDs', json_encode($uids, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE), 0);
                } catch (Exception $e) {
                    $this->SendDebug('Diag.Error', $e->getMessage(), 0);
                }
                break;

            default:
                throw new Exception('Invalid Ident: ' . $Ident);
        }
    }

    // ===================== Konfigurationsformular =====================
    public function GetConfigurationForm()
    {
        $ip   = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');

        $options = [];
        $error   = '';
        try {
            if ($ip !== '0.0.0.0' && $ip !== '') {
                $data    = $this->getData(); // kann Exception werfen
                $options = $this->buildOptionsFromData($data); // nur echte Sensor-Blätter
                if (empty($options)) {
                    $error = 'Keine Sensoren gefunden.';
                }
            } else {
                $error = 'Bitte IP-Adresse konfigurieren.';
            }
        } catch (Exception $e) {
            $error = 'Form-Scan: ' . $e->getMessage();
        }

        // Bisherige Auswahl laden
        $rows = $this->loadSelectedRows(); // [{active,pos,uid,caption,type,icon}]
        $byUID = [];
        foreach ($rows as $r) {
            if (!empty($r['uid'])) {
                $byUID[$r['uid']] = $r;
            }
        }

        // Tabellenwerte zusammenführen (Options + gespeicherte Auswahl)
        $values = [];
        $posSuggestion = 1;
        foreach ($options as $opt) {
            $prev = $byUID[$opt['uid']] ?? null;
            $values[] = [
                'active'  => $prev['active'] ?? false,
                'pos'     => isset($prev['pos']) ? (int)$prev['pos'] : $posSuggestion++,
                'caption' => $opt['caption'],
                'type'    => $opt['type'],
                'uid'     => $opt['uid'],
                'icon'    => $opt['icon'] ?? ''
            ];
        }

        // Formular
        $form = [
            'elements' => [
                ['type' => 'Label', 'caption' => 'Verbindung'],
                ['type' => 'ValidationTextBox', 'name' => 'IPAddress', 'caption' => 'IP-Adresse'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port', 'minimum' => 1, 'maximum' => 65535],
                ['type' => 'NumberSpinner', 'name' => 'UpdateInterval', 'caption' => 'Updateintervall (Sek.)', 'minimum' => 0, 'suffix' => 's'],

                [
                    'type' => 'Button',
                    'caption' => 'Diagnose: aktive Auswahl + JSON-Überblick ins Debug loggen',
                    'onClick' => 'IPS_RequestAction($id, "Diag", 0);'
                ],

                ['type' => 'Label', 'caption' => 'Sensor-Auswahl (wie Goodwe: Name + manuelle Positionierung)'],
                [
                    'type'    => 'List',
                    'name'    => 'SelectedSensors',
                    'caption' => 'Auswahl & Positionen',
                    'rowCount'=> 18,
                    'add'     => false,
                    'delete'  => false,
                    'sort'    => [
                        'column' => 'pos',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
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
                        [
                            'caption' => 'Name',
                            'name'    => 'caption',
                            'width'   => 'auto',
                            'save'    => false
                        ],
                        [
                            'caption' => 'Type',
                            'name'    => 'type',
                            'width'   => '120px',
                            'save'    => false
                        ],
                        [
                            'caption' => 'UID',
                            'name'    => 'uid',
                            'width'   => '420px',
                            'save'    => false
                        ]
                    ],
                    'values'  => $values
                ],

                ['type' => 'Label', 'caption' => 'Optionen'],
                ['type' => 'CheckBox', 'name' => 'AutoCleanup', 'caption' => 'Nicht mehr ausgewählte Variablen automatisch löschen'],

                ['type' => 'Label', 'caption' => 'Werkzeuge'],
                [
                    'type' => 'Button',
                    'caption' => 'Sensorliste neu einlesen',
                    'onClick' => 'IPS_RequestAction($id, "Refresh", 0);'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Jetzt aktualisieren (Variablen anlegen/aktualisieren)',
                    'onClick' => 'IPS_RequestAction($id, "DoUpdate", 0);'
                ],
                ['type' => 'Label', 'name' => 'DummyInfo', 'caption' => ($error ?: 'Bereit.')]
            ],
            'actions' => [],
            'status'  => []
        ];

        return json_encode($form);
    }

    // ===================== Update-Logik =====================
    public function Update(): bool
    {
        // 1) Auswahl laden
        $rows = $this->loadSelectedRows();
        // aktive Zeilen (UID vorhanden)
        $activeRows = array_values(array_filter($rows, fn($r) =>
            !empty($r['active']) && !empty($r['uid'])
        ));
        $this->SendDebug('Update.Start', 'Aktive Zeilen: ' . count($activeRows), 0);

        if (empty($activeRows)) {
            $this->SendDebug('Update.Abbruch', 'Keine aktive Auswahl – nichts zu tun.', 0);
            return true;
        }

        // 2) Auto-Positionen on-the-fly für pos<=0
        $nextPos = 1;
        $usedPos = [];
        foreach ($activeRows as &$r) {
            $p = (int)($r['pos'] ?? 0);
            if ($p <= 0) {
                while (isset($usedPos[$nextPos])) { $nextPos++; }
                $p = $nextPos++;
                $r['pos'] = $p;
                $this->SendDebug('AutoPos', "UID {$r['uid']} → pos={$p}", 0);
            }
            $usedPos[$p] = true;
        }
        unset($r);

        // 3) Daten holen
        try {
            $data = $this->getData();
        } catch (Exception $e) {
            $this->SendDebug('Fehler', $e->getMessage(), 0);
            $this->LogMessage($e->getMessage(), KL_ERROR);
            return false;
        }

        // 4) Sensormap (nur echte Sensor-Blätter)
        $points = [];
        $this->traverseSensors($data, [], $points); // uid => payload
        $this->SendDebug('Update.Sensors', 'Gefundene Sensoren im JSON: ' . count($points), 0);

        // 5) Bestehende Idents erfassen
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') {
                $existingIdents[$ident] = true;
            }
        }

        $seen    = [];
        $created = 0;
        $updated = 0;

        // 6) Für jede aktive Zeile Vierergruppe anlegen/aktualisieren
        foreach ($activeRows as $r) {
            $uid     = (string)$r['uid'];
            $pos     = (int)$r['pos'];
            $caption = (string)($r['caption'] ?? '');
            $fallbackType = (string)($r['type'] ?? '');

            if (!isset($points[$uid])) {
                $this->SendDebug('Update.Skip', "UID nicht im JSON gefunden: {$uid}", 0);
                continue;
            }
            $payload = $points[$uid]; // ['Text','Type','Min','Value','Max','SensorId']
            $type    = (string)($payload['Type'] ?? $fallbackType);
            $profile = $this->getVariableProfileByType($type);

            $nameValue = $caption !== '' ? $caption : ($payload['Text'] ?? '');
            $basePos   = $pos * 10;

            // --- Name (immer anlegen & setzen) ---
            $idText = $this->identForGroup($pos, 'Text');
            $varText = @IPS_GetObjectIDByIdent($idText, $this->InstanceID);
            if ($varText === false) {
                $varText = $this->RegisterVariableString($idText, "Pos {$pos} - Name", '', $basePos + 0);
                $created++;
                $this->SendDebug('Create', "Var angelegt: {$idText}", 0);
            }
            if ((string)GetValue($varText) !== (string)$nameValue) {
                SetValue($varText, (string)$nameValue);
                $updated++;
            }
            $seen[$idText] = true;

            // --- Min / Value / Max (nur numerisch setzen, aber Variablen werden trotzdem angelegt) ---
            foreach ([['Min',1], ['Value',2], ['Max',3]] as [$field, $offset]) {
                $ident = $this->identForGroup($pos, $field);
                $var   = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if ($var === false) {
                    $var = $this->RegisterVariableFloat($ident, "Pos {$pos} - {$field}", $profile, $basePos + $offset);
                    $created++;
                    $this->SendDebug('Create', "Var angelegt: {$ident}", 0);
                }
                $unit = null;
                $num  = $this->parseNumberWithUnit($payload[$field] ?? null, $unit);
                if ($num !== null) {
                    if ((float)GetValue($var) !== (float)$num) {
                        SetValue($var, $num);
                        $updated++;
                    }
                }
                // Wichtig: als "gesehen" markieren, damit Cleanup die Gruppe nicht wieder löscht
                $seen[$ident] = true;
            }
        }

        // 7) Cleanup nur unsere Gruppe-Idents
        if ($this->ReadPropertyBoolean('AutoCleanup')) {
            $removed = 0;
            foreach (array_keys($existingIdents) as $ident) {
                if (!isset($seen[$ident]) && $this->isOurGroupIdent($ident)) {
                    $this->UnregisterVariable($ident);
                    $removed++;
                }
            }
            $this->SendDebug('Cleanup', "Entfernt: {$removed}", 0);
        }

        $this->SendDebug('Update.Done', "Angelegt: {$created}, Aktualisiert: {$updated}", 0);
        return true;
    }

    // ===================== Datenzugriff & Utilities =====================
    private function getData(): array
    {
        $ip   = $this->ReadPropertyString('IPAddress');
        $port = $this->ReadPropertyInteger('Port');
        if ($ip === '' || $ip === '0.0.0.0') {
            throw new Exception('IP-Adresse ist nicht gesetzt.');
        }
        $url = "http://{$ip}:{$port}/data.json";

        $ctx = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true
            ]
        ]);

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

    // Nur echte Sensor-Blätter in Map schreiben: uid => payload
    private function traverseSensors(array $node, array $ancestors, array &$map): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node, $ancestors)) {
            $uid = $this->buildNodeUID($node, $ancestors);
            if (!isset($map[$uid])) {
                $map[$uid] = [
                    'Text'     => $node['Text']     ?? '',
                    'Type'     => $node['Type']     ?? '',
                    'Min'      => $node['Min']      ?? null,
                    'Value'    => $node['Value']    ?? null,
                    'Max'      => $node['Max']      ?? null,
                    'SensorId' => $node['SensorId'] ?? ''
                ];
            }
        }

        if (!empty($node['Children']) && is_array($node['Children'])) {
            foreach ($node['Children'] as $child) {
                if (is_array($child)) {
                    $this->traverseSensors($child, $currentAnc, $map);
                }
            }
        }
    }

    private function isSensorLeaf(array $node, array $ancestors = []): bool
    {
        if (!empty($node['SensorId'])) {
            return true;
        }
        $hasChildren = !empty($node['Children']) && is_array($node['Children']);
        if ($hasChildren) {
            return false;
        }
        // Blatt ohne Children: akzeptiere, wenn ein Name vorhanden ist (und optional Type)
        if (!empty($node['Text'])) {
            return true;
        }
        // letzte Reserve: einer der Werte ist numerisch
        foreach (['Min','Value','Max'] as $k) {
            $u = null;
            if ($this->parseNumberWithUnit($node[$k] ?? null, $u) !== null) {
                return true;
            }
        }
        return false;
    }

    private function buildNodeUID(array $node, array $ancestors): string
    {
        if (!empty($node['SensorId'])) {
            return 'sensor:' . (string)$node['SensorId'];
        }
        $parts = [];
        foreach ($ancestors as $a) {
            if (!empty($a['Text'])) {
                $parts[] = (string)$a['Text'];
            }
        }
        if (!empty($node['Text'])) {
            $parts[] = (string)$node['Text'];
        }
        $type = $node['Type'] ?? '';
        return 'path:' . implode('/', $parts) . ($type ? '|' . $type : '');
    }

    private function identForGroup(int $pos, string $field): string
    {
        // stabile Idents im Vierer-Set
        return 'Variable_' . $pos . '_' . $field; // z.B. Variable_12_Value
    }

    private function isOurGroupIdent(string $ident): bool
    {
        return (bool)preg_match('/^Variable_\d+_(Text|Min|Value|Max)$/', $ident);
    }

    private function parseNumberWithUnit($raw, ?string &$unitOut = null): ?float
    {
        if ($raw === null) {
            return null;
        }
        if (is_numeric($raw)) {
            $unitOut = '';
            return (float)$raw;
        }
        if (!is_string($raw)) {
            return null;
        }
        if (preg_match('/([-+]?\d+(?:\.\d+)?)\s*([%°A-Za-z\/\.]+)?/u', $raw, $m)) {
            $unitOut = isset($m[2]) ? trim($m[2]) : '';
            return (float)$m[1];
        }
        return null;
    }

    // ===================== Profiles =====================
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
        $profiles = [
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
            ''            => '~Intensity.100' // Fallback
        ];

        return $profiles[$type] ?? '~Intensity.100';
    }

    // ===================== Formular-Helfer =====================
    private function buildOptionsFromData(array $data): array
    {
        // Nur echte Sensor-Blätter auflisten
        $out = [];
        $this->traverseOptions($data, [], $out);
        // Sortierung: nach Caption
        usort($out, fn($a, $b) => strcmp($a['caption'], $b['caption']));
        return $out;
    }

    private function traverseOptions(array $node, array $ancestors, array &$out): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node)) {
            $uid = $this->buildNodeUID($node, $ancestors);

            // Pfadkette als Name
            $parts = [];
            foreach ($ancestors as $a) {
                if (!empty($a['Text'])) {
                    $parts[] = (string)$a['Text'];
                }
            }
            $leafText = $node['Text'] ?? '';
            if ($leafText !== '') {
                $parts[] = $leafText;
            }
            $type = $node['Type'] ?? '';
            $caption = implode(' › ', $parts) . ($type ? '  [' . $type . ']' : '');

            $out[] = [
                'uid'     => $uid,
                'caption' => $caption,
                'type'    => (string)$type,
                'icon'    => (string)($node['ImageURL'] ?? '')
            ];
        }

        if (!empty($node['Children']) && is_array($node['Children'])) {
            foreach ($node['Children'] as $child) {
                if (is_array($child)) {
                    $this->traverseOptions($child, $currentAnc, $out);
                }
            }
        }
    }

    private function loadSelectedRows(): array
    {
        $raw = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($raw)) {
            return [];
        }

        // Erwartetes Format (Zeilen)
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
}
