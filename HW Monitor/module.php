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
        $this->RegisterPropertyInteger('UpdateInterval', 30); // Sek.
        $this->RegisterPropertyBoolean('TrackAll', false);    // <- Standard: erst auswählen!
        // Liste von Zeilen: [{uid:"...", caption:"...", active:true|false, icon:""}]
        $this->RegisterPropertyString('SelectedSensors', '[]');
        $this->RegisterPropertyBoolean('AutoCleanup', true);

        // ===== Timer =====
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

        // Erst updaten, wenn:
        // - TrackAll = true ODER
        // - es gibt mindestens eine aktive Auswahl
        $trackAll = $this->ReadPropertyBoolean('TrackAll');
        $hasActiveSelection = $this->hasAtLeastOneActiveSelection();

        if ($trackAll || $hasActiveSelection) {
            $this->Update();
        } else {
            $this->SendDebug('Init', 'Keine Variablen angelegt (TrackAll=false & keine aktive Auswahl).', 0);
        }
    }

    // ------------------------------------------------------------
    // RequestAction
    // ------------------------------------------------------------
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'DoUpdate':
                $this->Update();
                break;

            case 'Refresh':
                $this->SendDebug('Refresh', 'Sensorliste aktualisiert (Form neu aufgebaut)', 0);
                $this->UpdateFormField('DummyInfo', 'caption', 'Letzte Aktualisierung: ' . date('H:i:s'));
                break;

            default:
                throw new Exception('Invalid Ident in RequestAction: ' . $Ident);
        }
    }

    // ------------------------------------------------------------
    // Formular (List = SelectedSensors)
    // ------------------------------------------------------------
    public function GetConfigurationForm()
    {
        $ip        = $this->ReadPropertyString('IPAddress');
        $trackAll  = $this->ReadPropertyBoolean('TrackAll');

        $options = [];
        $error   = '';
        try {
            if ($ip !== '0.0.0.0' && $ip !== '') {
                $data    = $this->getData();            // kann Exception werfen
                $options = $this->buildOptionsFromData($data); // [{uid, caption, icon}]
                if (empty($options)) {
                    $error = 'Keine Sensoren gefunden.';
                }
            } else {
                $error = 'Bitte IP-Adresse konfigurieren.';
            }
        } catch (Exception $e) {
            $error = 'Form-Live-Scan: ' . $e->getMessage();
        }

        // Auswahl laden (Zeilenformat oder Altformat)
        $selectedRows = $this->loadSelectedRows();
        $byUID = [];
        foreach ($selectedRows as $r) {
            if (!empty($r['uid'])) {
                $byUID[$r['uid']] = [
                    'active'  => (bool)($r['active'] ?? false),
                    'caption' => (string)($r['caption'] ?? ''),
                    'icon'    => (string)($r['icon'] ?? '')
                ];
            }
        }

        // Werte für die List (Active-Flag vorbelegen)
        $values = [];
        foreach ($options as $opt) {
            $pre = $byUID[$opt['uid']] ?? ['active' => false, 'caption' => '', 'icon' => ''];
            $values[] = [
                'uid'     => $opt['uid'],
                'caption' => $opt['caption'],
                'active'  => (bool)$pre['active'],
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

                // WICHTIG: Name == Property "SelectedSensors", damit IPS die Liste speichert!
                [
                    'type'    => 'List',
                    'name'    => 'SelectedSensors',
                    'caption' => 'Manuelle Sensor-Auswahl (vor dem Anlegen)',
                    'visible' => !$trackAll,
                    'rowCount' => 15,
                    'add'     => false,
                    'delete'  => false,
                    'sort'    => [
                        'column' => 'caption',
                        'direction' => 'ascending'
                    ],
                    'columns' => [
                        [
                            'caption' => 'Aktiv',
                            'name'    => 'active',
                            'width'   => '80px',
                            'align'   => 'center',
                            'edit'    => ['type' => 'CheckBox']
                        ],
                        [
                            'caption' => 'Sensor',
                            'name'    => 'caption',
                            'width'   => 'auto',
                            'save'    => false
                        ],
                        [
                            'caption' => 'UID',
                            'name'    => 'uid',
                            'width'   => '450px',
                            'save'    => false
                        ],
                        [
                            'caption' => 'Icon',
                            'name'    => 'icon',
                            'width'   => '220px',
                            'save'    => false
                        ],
                    ],
                    'values' => $values
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

        $trackAll = $this->ReadPropertyBoolean('TrackAll');
        $selectedRows = $this->loadSelectedRows(); // [{uid,caption,active}]
        $selectedActiveUIDs = [];
        foreach ($selectedRows as $r) {
            if (!empty($r['uid']) && !empty($r['active'])) {
                $selectedActiveUIDs[$r['uid']] = true;
            }
        }

        // Wenn TrackAll=false & keine aktive Auswahl → nix anlegen
        if (!$trackAll && empty($selectedActiveUIDs)) {
            $this->SendDebug('Update', 'Abbruch: keine aktive Auswahl (TrackAll=false).', 0);
            return true;
        }

        // Bestehende Variablen-Idents
        $existingIDs = IPS_GetChildrenIDs($this->InstanceID);
        $existingIdents = [];
        foreach ($existingIDs as $vid) {
            $obj = IPS_GetObject($vid);
            $ident = $obj['ObjectIdent'] ?? '';
            if ($ident !== '') {
                $existingIdents[$ident] = true;
            }
        }

        // ECHTE Sensor-Punkte einsammeln
        $points = [];
        $this->traverseAndCollectSensorsOnly($data, [], $points); // uid => payload

        $seen = [];
        $count = 0;

        foreach ($points as $uid => $p) {
            if (!$trackAll && empty($selectedActiveUIDs[$uid])) {
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
            if ((string)GetValue($varTextID) !== (string)$nameSuffix) {
                SetValue($varTextID, (string)$nameSuffix);
            }
            $seen[$identText] = true;

            // Min/Value/Max (nur numerisch)
            foreach (['Min','Value','Max'] as $field) {
                $ident = $this->identFromUID($uid, $field);
                $varID = @IPS_GetObjectIDByIdent($ident, $this->InstanceID);
                if ($varID === false) {
                    $varID = $this->RegisterVariableFloat($ident, $namePrefix . $field, $profile, 0);
                }
                $unit = null;
                $num  = $this->parseNumberWithUnit($p[$field] ?? null, $unit);
                if ($num !== null) {
                    if ((float)GetValue($varID) !== (float)$num) {
                        SetValue($varID, $num);
                    }
                    $seen[$ident] = true;
                }
                // Wenn nicht numerisch, wird die Variable nicht als "gesehen" markiert → bleibt ggf. übrig und wird beim Cleanup entfernt.
            }

            $count++;
        }

        // Cleanup
        if ($this->ReadPropertyBoolean('AutoCleanup')) {
            foreach (array_keys($existingIdents) as $ident) {
                if (!isset($seen[$ident])) {
                    $this->UnregisterVariable($ident);
                    // Debug bewusst sparsam
                }
            }
        }

        $this->SendDebug('Update', "Aktualisiert: {$count} Sensoren", 0);
        return true;
    }

    // ------------------------------------------------------------
    // Daten & Utils
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
        return $data;
    }

    // Nur Blatt-Sensoren!
    private function traverseAndCollectSensorsOnly(array $node, array $ancestors, array &$points): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node, $ancestors)) {
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
                    $this->traverseAndCollectSensorsOnly($child, $currentAnc, $points);
                }
            }
        }
    }

    // Ein „echter“ Sensor ist:
    // - mit SensorId ODER
    // - ohne Children UND (Min|Value|Max) numerisch parsbar
    private function isSensorLeaf(array $node, array $ancestors): bool
    {
        if (!empty($node['SensorId'])) {
            return true;
        }
        $hasChildren = !empty($node['Children']) && is_array($node['Children']);
        if ($hasChildren) {
            return false;
        }
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

    private function identFromUID(string $uid, string $field): string
    {
        $hash = substr(sha1($uid), 0, 16);
        return 'S_' . $hash . '_' . $field;
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

    private function hasAtLeastOneActiveSelection(): bool
    {
        $rows = $this->loadSelectedRows();
        foreach ($rows as $r) {
            if (!empty($r['uid']) && !empty($r['active'])) {
                return true;
            }
        }
        return false;
    }

    // ------------------------------------------------------------
    // Profiles
    // ------------------------------------------------------------
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

    // ------------------------------------------------------------
    // Formular-Helfer
    // ------------------------------------------------------------
    private function buildOptionsFromData(array $data): array
    {
        // Nur echte Sensor-Blätter in die Auswahl aufnehmen
        $points = [];
        $this->traverseOptionsSensorsOnly($data, [], $points);
        usort($points, function ($a, $b) {
            return strcmp($a['caption'], $b['caption']);
        });
        return $points;
    }

    private function traverseOptionsSensorsOnly(array $node, array $ancestors, array &$out): void
    {
        $currentAnc = array_merge($ancestors, [$node]);

        if ($this->isSensorLeaf($node, $ancestors)) {
            $uid = $this->buildNodeUID($node, $ancestors);

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
                    $this->traverseOptionsSensorsOnly($child, $currentAnc, $out);
                }
            }
        }
    }

    // Auswahl-Lader (Abwärtskompatibilität)
    private function loadSelectedRows(): array
    {
        $raw = json_decode($this->ReadPropertyString('SelectedSensors'), true);
        if (!is_array($raw)) {
            return [];
        }

        // Neues Format (Zeilen)
        if (!empty($raw) && isset($raw[0]) && is_array($raw[0]) && array_key_exists('uid', $raw[0])) {
            $rows = [];
            foreach ($raw as $r) {
                $rows[] = [
                    'uid'     => (string)($r['uid'] ?? ''),
                    'caption' => (string)($r['caption'] ?? ''),
                    'active'  => (bool)($r['active'] ?? false),
                    'icon'    => (string)($r['icon'] ?? '')
                ];
            }
            return $rows;
        }

        // Altformat (Liste von UIDs)
        $rows = [];
        foreach ($raw as $uid) {
            if (!is_string($uid)) continue;
            $rows[] = [
                'uid'     => $uid,
                'caption' => '',
                'active'  => true,
                'icon'    => ''
            ];
        }
        return $rows;
    }
}
