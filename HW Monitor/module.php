<?php
declare(strict_types=1);

class HWMonitor extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // ===== Properties =====
        $this->RegisterPropertyString('IPAddress', '0.0.0.0');
        $this->RegisterPropertyInteger('Port', 8085);
        $this->RegisterPropertyInteger('UpdateInterval', 30); // Sekunden
        $this->RegisterPropertyBoolean('TrackAll', true);     // Alle Sensoren automatisch
        $this->RegisterPropertyString('SelectedSensors', '[]'); // Array von UIDs (JSON)
        $this->RegisterPropertyBoolean('AutoCleanup', true);    // Nicht mehr gesehene Variablen löschen

        // ===== Timer =====
        // per RequestAction -> DoUpdate
        $this->RegisterTimer(
            'UpdateTimer',
            0,
            'IPS_RequestAction(' . $this->InstanceID . ', "DoUpdate", 0);'
        );

        // ===== Variable Profiles =====
        $this->createVariableProfiles();
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $intervalMs = max(0, $this->ReadPropertyInteger('UpdateInterval')) * 1000;
        $this->SetTimerInterval('UpdateTimer', $intervalMs);

        $ip = $this->ReadPropertyString('IPAddress');
        if ($ip === '0.0.0.0' || $ip === '') {
            $this->SendDebug('Konfiguration', 'IP-Adresse ist nicht konfiguriert', 0);
            return;
        }

        // Initial: einmal Update versuchen (ohne fatal)
        $this->Update();
    }

    // ------------------------------------------------------------
    // RequestAction: Buttons und Timer
    // ------------------------------------------------------------
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'DoUpdate':
                $this->Update();
                break;

            case 'Refresh':
                // keine Speicherung nötig – das Formular liest live
                // (hier nur force Logik/Debug)
                $this->SendDebug('Refresh', 'Sensorliste aktualisiert (Form wird neu aufgebaut)', 0);
                // Force UI Refresh durch Status-Änderung (kleiner Trick)
                $this->UpdateFormField('DummyInfo', 'caption', 'Letzte Aktualisierung: ' . date('H:i:s'));
                break;

            default:
                throw new Exception('Invalid Ident in RequestAction: ' . $Ident);
        }
    }

    // ------------------------------------------------------------
    // Dynamisches Formular (kein form.json nötig)
    // ------------------------------------------------------------
    public function GetConfigurationForm()
    {
        $ip        = $this->ReadPropertyString('IPAddress');
        $port      = $this->ReadPropertyInteger('Port');
        $trackAll  = $this->ReadPropertyBoolean('TrackAll');

        // Versuch, Daten zu laden und Options zu bauen
        $options = [];
        $error   = '';
        try {
            if ($ip !== '0.0.0.0' && $ip !== '') {
                $data = $this->getData();  // wirft Exception bei Fehlern
                $options = $this->buildOptionsFromData($data);
                if (empty($options)) {
                    $error = 'Keine Sensoren gefunden.';
                }
            } else {
                $error = 'Bitte IP-Adresse konfigurieren.';
            }
        } catch (Exception $e) {
            $error = 'Form-Live-Scan: ' . $e->getMessage();
        }

        // Aktuell gewählte UIDs
        $selected = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($selected)) {
            $selected = [];
        }

        // CheckBoxList-Werte inkl. “checked”
        $values = [];
        foreach ($options as $opt) {
            $values[] = [
                'caption' => $opt['caption'],
                'value'   => $opt['uid'],
                'checked' => in_array($opt['uid'], $selected, true),
                'icon'    => $opt['icon'] ?? ''
            ];
        }

        $form = [
            'elements' => [
                ['type' => 'Label', 'caption' => 'Verbindung'],
                ['type' => 'ValidationTextBox', 'name' => 'IPAddress', 'caption' => 'IP-Adresse'],
                ['type' => 'NumberSpinner', 'name' => 'Port', 'caption' => 'Port', 'minimum' => 1, 'maximum' => 65535],
                ['type' => 'NumberSpinner', 'name' => 'UpdateInterval', 'caption' => 'Updateintervall (Sek.)', 'minimum' => 0, 'suffix' => 's'],

                ['type' => 'Label', 'caption' => 'Erfassung'],
                ['type' => 'CheckBox', 'name' => 'TrackAll', 'caption' => 'Alle Sensoren automatisch übernehmen'],
                [
                    'type'    => 'CheckBoxList',
                    'name'    => 'SelectedSensors',
                    'caption' => 'Manuelle Sensor-Auswahl (UID-basiert)',
                    'values'  => $values,
                    'visible' => !$trackAll
                ],

                ['type' => 'Label', 'caption' => 'Bereinigung'],
                ['type' => 'CheckBox', 'name' => 'AutoCleanup', 'caption' => 'Nicht mehr vorhandene Variablen automatisch löschen'],

                ['type' => 'Label', 'caption' => 'Werkzeuge'],
                [
                    'type' => 'Button',
                    'caption' => 'Sensorliste neu einlesen',
                    'onClick' => 'IPS_RequestAction($id, "Refresh", 0);'
                ],
                [
                    'type' => 'Button',
                    'caption' => 'Jetzt aktualisieren',
                    'onClick' => 'IPS_RequestAction($id, "DoUpdate", 0);'
                ],
                ['type' => 'Label', 'name' => 'DummyInfo', 'caption' => ($error ?: 'Bereit.')]
            ],
            'actions' => [],
            'status'  => []
        ];

        return json_encode($form);
    }

    // ------------------------------------------------------------
    // Hauptlogik
    // ------------------------------------------------------------
    public function Update(): bool
    {
        try {
            $data = $this->getData();
        } catch (Exception $e) {
            $this->SendDebug('Fehler', $e->getMessage(), 0);
            $this->LogMessage($e->getMessage(), KL_ERROR);
            return false;
        }

        // Bestehende Variablen-Idents sammeln (nicht leere)
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') {
                $existingIdents[$ident] = true;
            }
        }

        $points = [];
        $this->traverseAndCollect($data, [], $points);

        // Filter: All vs Selected
        $trackAll = $this->ReadPropertyBoolean('TrackAll');
        $selected = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($selected)) {
            $selected = [];
        }

        $seen = [];
        $count = 0;

        foreach ($points as $uid => $p) {
            if (!$trackAll && !in_array($uid, $selected, true)) {
                continue;
            }

            $type    = $p['Type'] ?? '';
            $profile = $this->getVariableProfileByType($type);
            $nameSuffix = $p['Text'] ?? '';
            $namePrefix = $type ? '[' . $type . '] ' : '';

            // Text (Name)
            $identText = $this->identFromUID($uid, 'Text');
            $varTextID = @IPS_GetObjectIDByIdent($identText, $this->InstanceID);
            if ($varTextID === false) {
                $varTextID = $this->RegisterVariableString($identText, $namePrefix . 'Name', '', 0);
            }
            SetValue($varTextID, (string)$nameSuffix);
            $seen[$identText] = true;

            // Min/Value/Max (Float, nur numerisch setzen)
            foreach (['Min','Value','Max'] as $field) {
                $ident = $this->identFromUID($uid, $field);
                $varID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if ($varID === false) {
                    $varID = $this->RegisterVariableFloat($ident, $namePrefix . $field, $profile, 0);
                }
                $unit = null;
                $num  = $this->parseNumberWithUnit($p[$field] ?? null, $unit);
                if ($num !== null) {
                    SetValue($varID, $num);
                } else {
                    // Kein numerischer Wert – nicht setzen
                    $this->SendDebug('Typwarnung', "Kein numerischer Wert für {$field} bei {$uid}", 0);
                }
                $seen[$ident] = true;
            }

            $count++;
        }

        // Cleanup
        if ($this->ReadPropertyBoolean('AutoCleanup')) {
            foreach (array_keys($existingIdents) as $ident) {
                if (!isset($seen[$ident])) {
                    $this->UnregisterVariable($ident);
                    $this->SendDebug('Cleanup', 'Variable entfernt: ' . $ident, 0);
                }
            }
        }

        $this->SendDebug('Update', "Aktualisiert: {$count} Sensoren", 0);
        return true;
    }

    // ------------------------------------------------------------
    // Live-Daten holen + Utilities
    // ------------------------------------------------------------
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
        // Optional: Keys normalisieren, wenn nötig
        // $data = $this->normalizeKeysRecursive($data);

        return $data;
    }

    private function normalizeKeysRecursive(array $arr): array
    {
        $out = [];
        foreach ($arr as $k => $v) {
            $nk = is_string($k) ? ucfirst(strtolower($k)) : $k; // z.B. type -> Type
            $out[$nk] = is_array($v) ? $this->normalizeKeysRecursive($v) : $v;
        }
        return $out;
    }

    private function traverseAndCollect(array $node, array $ancestors, array &$points): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        // Ist das ein interessanter Punkt (hat einen der Felder)?
        $hasFields = false;
        foreach (['Text','Min','Value','Max','Type','SensorId'] as $k) {
            if (isset($node[$k]) && $node[$k] !== '' && $node[$k] !== null) {
                $hasFields = true;
                break;
            }
        }

        if ($hasFields) {
            $uid = $this->buildNodeUID($node, $ancestors);
            if (!isset($points[$uid])) {
                $points[$uid] = [
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
                    $this->traverseAndCollect($child, $currentAnc, $points);
                }
            }
        }
    }

    private function buildNodeUID(array $node, array $ancestors): string
    {
        // 1) stabil: SensorId
        if (!empty($node['SensorId'])) {
            return 'sensor:' . (string)$node['SensorId'];
        }

        // 2) Fallback: deterministischer Pfad aus Texten + Type
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
        $uid  = 'path:' . implode('/', $parts) . ($type ? '|' . $type : '');
        return $uid;
    }

    private function identFromUID(string $uid, string $field): string
    {
        // handliche, stabile Idents
        $hash = substr(sha1($uid), 0, 16);
        return 'S_' . $hash . '_' . $field; // z.B. S_ab12...cdef_Value
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

    // ------------------------------------------------------------
    // Profiles
    // ------------------------------------------------------------
    private function createVariableProfiles(): void
    {
        $profiles = [
            // name         => [vartype, min, max, step, digits, suffix]
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

            // zusätzliche Zuordnungen (robuster)
            'Frequency'   => 'HW.Clock',
            'Usage'       => '~Progress',
            'Memory'      => 'HW.Data',
            'Bandwidth'   => 'HW.Rate',
            ''            => '~Intensity.100' // Fallback
        ];

        return $profiles[$type] ?? '~Intensity.100';
    }

    // ------------------------------------------------------------
    // Formular-Helfer: Options bauen (Pfadkette + Icon)
    // ------------------------------------------------------------
    private function buildOptionsFromData(array $data): array
    {
        // Wir bauen die gleiche Punkteliste wie in Update(),
        // erweitern aber um "caption" = schöne Pfadkette
        $points = [];
        $this->traverseAndCollectForOptions($data, [], $points);
        // Sortieren (alphabetisch nach caption)
        usort($points, function ($a, $b) {
            return strcmp($a['caption'], $b['caption']);
        });
        return $points;
    }

    private function traverseAndCollectForOptions(array $node, array $ancestors, array &$out): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        $hasFields = false;
        foreach (['Text','Min','Value','Max','Type','SensorId'] as $k) {
            if (isset($node[$k]) && $node[$k] !== '' && $node[$k] !== null) {
                $hasFields = true;
                break;
            }
        }

        if ($hasFields) {
            $uid = $this->buildNodeUID($node, $ancestors);

            // Pfadkette als Caption (logische Gruppierung)
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

            $icon = $node['ImageURL'] ?? '';
            $out[] = [
                'uid'     => $uid,
                'caption' => $caption,
                'icon'    => $icon
            ];
        }

        if (!empty($node['Children']) && is_array($node['Children'])) {
            foreach ($node['Children'] as $child) {
                if (is_array($child)) {
                    $this->traverseAndCollectForOptions($child, $currentAnc, $out);
                }
            }
        }
    }
}
