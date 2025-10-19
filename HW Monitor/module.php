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
        $this->RegisterPropertyInteger('UpdateInterval', 30); // Sekunden
        // Auswahl-Liste: [{active:bool, pos:int, uid:string, caption:string, type:string, icon:string}]
        $this->RegisterPropertyString('SelectedSensors', '[]');

        // Timer
        $this->RegisterTimer('UpdateTimer', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "DoUpdate", 0);');

        // Profile
        $this->createVariableProfiles();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->SetTimerInterval('UpdateTimer', max(0, $this->ReadPropertyInteger('UpdateInterval')) * 1000);

        // Nach jedem Übernehmen sofort aktualisieren (Variablen anlegen/aktualisieren & Cleanup)
        if ($this->ReadPropertyString('IPAddress') !== '' && $this->ReadPropertyString('IPAddress') !== '0.0.0.0') {
            $this->Update();
        }
    }

    public function RequestAction($Ident, $Value)
    {
        if ($Ident === 'DoUpdate') {
            $this->Update();
            return;
        }
        throw new Exception('Invalid Ident: ' . $Ident);
    }

    // ------------------------ Formular ------------------------
    public function GetConfigurationForm()
    {
        $error = '';
        $options = [];

        try {
            $ip = $this->ReadPropertyString('IPAddress');
            if ($ip !== '' && $ip !== '0.0.0.0') {
                $data = $this->getData();                   // live abrufen
                $options = $this->buildOptions($data);      // nur echte Sensor-Blätter
            } else {
                $error = 'Bitte IP-Adresse konfigurieren.';
            }
        } catch (Exception $e) {
            $error = 'Scan: ' . $e->getMessage();
        }

        // Bisherige Auswahl mergen
        $saved = $this->loadSelectedRows(); // [{active,pos,uid,caption,type,icon}]
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
                'caption' => $opt['caption'],
                'type'    => $opt['type'],
                'uid'     => $opt['uid'],
                'icon'    => $opt['icon'] ?? ''
            ];
        }

        $form = [
            'elements' => [
                ['type' => 'Label', 'caption' => 'Verbindung'],
                ['type' => 'ValidationTextBox', 'name' => 'IPAddress', 'caption' => 'IP-Adresse'],
                ['type' => 'NumberSpinner',     'name' => 'Port',      'caption' => 'Port', 'minimum' => 1, 'maximum' => 65535],
                ['type' => 'NumberSpinner',     'name' => 'UpdateInterval', 'caption' => 'Updateintervall (Sek.)', 'minimum' => 0, 'suffix' => 's'],

                ['type' => 'Label', 'caption' => 'Auswahl & Positionen (Übernehmen speichert die Häkchen!)'],
                [
                    'type'    => 'List',
                    'name'    => 'SelectedSensors',   // muss exakt der Property entsprechen
                    'caption' => 'Sensoren',
                    'rowCount'=> 16,
                    'add'     => false,
                    'delete'  => false,
                    'sort'    => ['column' => 'pos', 'direction' => 'ascending'],
                    'columns' => [
                        ['caption' => 'Aktiv', 'name' => 'active', 'width' => '70px', 'align' => 'center', 'edit' => ['type' => 'CheckBox']],
                        ['caption' => 'Pos.',  'name' => 'pos',    'width' => '70px', 'align' => 'center', 'edit' => ['type' => 'NumberSpinner', 'minimum' => 1, 'maximum' => 9999]],
                        ['caption' => 'Name',  'name' => 'caption', 'width' => 'auto', 'save' => false],
                        ['caption' => 'Type',  'name' => 'type',    'width' => '120px', 'save' => false],
                        ['caption' => 'UID',   'name' => 'uid',     'width' => '420px', 'save' => false],
                    ],
                    'values'  => $values
                ],

                ['type' => 'Label', 'caption' => ($error ?: 'Bereit.')]
            ],
            'actions' => [],
            'status'  => []
        ];

        return json_encode($form);
    }

    // ------------------------ Update ------------------------
    public function Update(): bool
    {
        // Auswahl lesen (nur aktive)
        $rows = $this->loadSelectedRows();
        $activeRows = array_values(array_filter($rows, fn($r) =>
            !empty($r['active']) && !empty($r['uid'])
        ));

        // Auto-Positionsvergabe on-the-fly für pos<=0
        $nextPos = 1;
        $usedPos = [];
        foreach ($activeRows as &$r) {
            $p = (int)($r['pos'] ?? 0);
            if ($p <= 0) {
                while (isset($usedPos[$nextPos])) { $nextPos++; }
                $p = $nextPos++;
                $r['pos'] = $p;
            }
            $usedPos[$p] = true;
        }
        unset($r);

        // Daten holen
        try {
            $data = $this->getData();
        } catch (Exception $e) {
            $this->SendDebug('Fehler', $e->getMessage(), 0);
            $this->LogMessage($e->getMessage(), KL_ERROR);
            return false;
        }

        // aktuelle Sensoren (nur echte Blatt-Knoten)
        $points = [];
        $this->collectSensors($data, [], $points); // uid => ['Text','Type','Min','Value','Max','SensorId']

        // vorhandene Idents erfassen
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') {
                $existingIdents[$ident] = true;
            }
        }

        $seen = [];

        // JEDE aktive Zeile → Vierergruppe anlegen/aktualisieren
        foreach ($activeRows as $r) {
            $uid     = (string)$r['uid'];
            $pos     = (int)$r['pos'];
            $caption = (string)($r['caption'] ?? '');
            $fallbackType = (string)($r['type'] ?? '');

            $payload = $points[$uid] ?? [
                // UID nicht (mehr) im JSON? → trotzdem anlegen, Namen aus caption
                'Text' => $caption,
                'Type' => $fallbackType,
                'Min' => null, 'Value' => null, 'Max' => null, 'SensorId' => ''
            ];

            $type    = (string)($payload['Type'] ?? $fallbackType);
            $profile = $this->getVariableProfileByType($type);

            $basePos = $pos * 10;
            $nameVal = $caption !== '' ? $caption : (string)($payload['Text'] ?? '');

            // Name (immer anlegen/setzen)
            $idText = $this->identFor($pos, 'Text');
            $vText  = @IPS_GetObjectIDByIdent($idText, $this->InstanceID);
            if ($vText === false) {
                $vText = $this->RegisterVariableString($idText, "Pos {$pos} - Name", '', $basePos + 0);
            }
            if ((string)GetValue($vText) !== $nameVal) {
                SetValue($vText, $nameVal);
            }
            $seen[$idText] = true;

            // Min/Value/Max (Variablen IMMER anlegen; Wert nur setzen, wenn numerisch)
            foreach ([['Min',1], ['Value',2], ['Max',3]] as [$field, $offset]) {
                $ident = $this->identFor($pos, $field);
                $vid   = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if ($vid === false) {
                    $vid = $this->RegisterVariableFloat($ident, "Pos {$pos} - {$field}", $profile, $basePos + $offset);
                }
                $unit = null;
                $num  = $this->parseNumberWithUnit($payload[$field] ?? null, $unit);
                if ($num !== null && (float)GetValue($vid) !== (float)$num) {
                    SetValue($vid, $num);
                }
                $seen[$ident] = true; // gesehen, damit Cleanup sie NICHT löscht
            }
        }

        // Cleanup: ALLES löschen, was nicht (mehr) ausgewählt ist (nur unsere Idents)
        foreach (array_keys($existingIdents) as $ident) {
            if (!isset($seen[$ident]) && $this->isOurIdent($ident)) {
                $this->UnregisterVariable($ident);
            }
        }

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
            $uid = $this->buildUID($node, $ancestors);
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
            return 'sensor:' . (string)$node['SensorId'];
        }
        $parts = [];
        foreach ($ancestors as $a) {
            if (!empty($a['Text'])) { $parts[] = (string)$a['Text']; }
        }
        if (!empty($node['Text'])) { $parts[] = (string)$node['Text']; }
        $type = $node['Type'] ?? '';
        return 'path:' . implode('/', $parts) . ($type ? '|' . $type : '');
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
