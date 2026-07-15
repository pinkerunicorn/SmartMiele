<?php

declare(strict_types=1);

class MieleDryer extends IPSModuleStrict
{

    public function Create(): void{
        parent::Create();
        
        
        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }
$this->RegisterPropertyString('DeviceID', '');

        // Connect to Splitter

        
        // Variables
        $this->RegisterVariableString('StatusText', 'ℹ Status', '', 10);
        IPS_SetIcon($this->GetIDForIdent('StatusText'), 'Information');
        $this->RegisterVariableBoolean('SignalInfo', '🔔 Hinweis vorhanden', '', 11);
        IPS_SetIcon($this->GetIDForIdent('SignalInfo'), 'Information');
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', '', 12);
        IPS_SetIcon($this->GetIDForIdent('SignalFailure'), 'Warning');
        
        $this->RegisterVariableString('ProgramName', '📝 Programmbezeichnung', '', 21);
        IPS_SetIcon($this->GetIDForIdent('ProgramName'), 'Script');
        $this->RegisterVariableString('ProgramPhaseText', '🔄 Programm-Phase', '', 22);
        IPS_SetIcon($this->GetIDForIdent('ProgramPhaseText'), 'Script');
        
        $this->RegisterVariableInteger('StartTime', '▶ Start um', '', 25);
        IPS_SetIcon($this->GetIDForIdent('StartTime'), 'Clock');
        $this->RegisterVariableInteger('FinishTime', '⏹ Ende um', '', 26);
        IPS_SetIcon($this->GetIDForIdent('FinishTime'), 'Clock');
        $this->RegisterVariableInteger('ElapsedTime', '⏱ verstrichene Zeit', '', 27);
        IPS_SetIcon($this->GetIDForIdent('ElapsedTime'), 'Clock');
        $this->RegisterVariableInteger('RemainingTime', '⏳ verbleibende Zeit', '', 28);
        IPS_SetIcon($this->GetIDForIdent('RemainingTime'), 'Clock');
        $this->RegisterVariableInteger('RemainingTimeSeconds', '⏳ verbleibende Zeit (Sekunden)', '', 28);
        IPS_SetIcon($this->GetIDForIdent('RemainingTimeSeconds'), 'Clock');
        $this->RegisterVariableInteger('ProgressPct', '📈 Arbeitsfortschritt', '', 29);
        IPS_SetIcon($this->GetIDForIdent('ProgressPct'), 'Gauge');
        
        $this->RegisterVariableBoolean('Door', '🚪 Tür', '', 33);
        IPS_SetIcon($this->GetIDForIdent('Door'), 'Window');
        
        $this->RegisterVariableFloat('CurrentEnergyConsumption', '⚡ aktueller Energieverbrauch', '', 55);
        IPS_SetIcon($this->GetIDForIdent('CurrentEnergyConsumption'), 'Energy');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();


        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'=> 'Information'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalFailure'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'=> 'Alert'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StartTime'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'=> 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('FinishTime'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'=> 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('ElapsedTime'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'=> 'min',
            'ICON'=> 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('RemainingTime'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'=> 'min',
            'ICON'=> 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('RemainingTimeSeconds'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'=> 's',
            'ICON'=> 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('ProgressPct'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'=> '%',
            'ICON'=> 'Intensity'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Door'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'ICON'=> 'Window'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('CurrentEnergyConsumption'), [
                'PRESENTATION'=> VARIABLE_PRESENTATION_VALUE_PRESENTATION,
            'SUFFIX'=> 'kWh',
            'ICON'=> 'Electricity'
        ]);
    }

    public function ReceiveData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            $deviceId = $this->ReadPropertyString('DeviceID');
            if (empty($deviceId)) {
                return "";
            }

            if (isset($data['Devices'][$deviceId])) {
                $this->ProcessDeviceData($data['Devices'][$deviceId]);
            }
        }
    
        return "";
    }

    protected function Log(string $text): void
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleDryer: '. $text);
    }

    private function ProcessDeviceData(array $deviceData)
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $newStatus = (string)$state['status']['value_localized'];
                if (@$this->GetValue('StatusText') !== $newStatus) {
                    $this->Log("Status geändert: ". $newStatus);
                }
                $this->SetValue('StatusText', $newStatus);
            }
            if (isset($state['signalInfo'])) {
                $this->SetValue('SignalInfo', (bool)$state['signalInfo']);
            }
            if (isset($state['signalFailure'])) {
                $this->SetValue('SignalFailure', (bool)$state['signalFailure']);
            }
            
            if (isset($state['ProgramID']['value_localized'])) {
                $this->SetValue('ProgramName', (string)$state['ProgramID']['value_localized']);
            }
            if (isset($state['programPhase']['value_localized'])) {
                $this->SetValue('ProgramPhaseText', (string)$state['programPhase']['value_localized']);
            }

            if (isset($state['signalDoor'])) {
                $this->SetValue('Door', (bool)$state['signalDoor']);
            }
            
            if (isset($state['ecoFeedback']['currentEnergyConsumption']['value'])) {
                $this->SetValue('CurrentEnergyConsumption', (float)$state['ecoFeedback']['currentEnergyConsumption']['value']);
            }

            $statusRaw = $state['status']['value_raw'] ?? 0;
            
            // --- Time & Progress Calculation ---
            $remMinutes = @$this->GetValue('RemainingTime');
            if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 2) {
                $remMinutes = ($state['remainingTime'][0] * 60) + $state['remainingTime'][1];
            } else if (isset($state['remainingTime']) && is_array($state['remainingTime']) && count($state['remainingTime']) == 0) {
                if ($statusRaw != 5 && $statusRaw != 7) $remMinutes = 0;
            }

            $elapsedMinutes = @$this->GetValue('ElapsedTime');
            if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                $elapsedMinutes = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
            } else if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 0) {
                if ($statusRaw != 5 && $statusRaw != 7) $elapsedMinutes = 0;
            }

            if ($statusRaw == 7) { // Finished
                $remMinutes = 0;
                $progress = 100;
                $startTime = @$this->GetValue('StartTime');
                $finishTime = @$this->GetValue('FinishTime');
            } else if ($statusRaw == 5) { // In Use
                $now = (int)(floor(time() / 60) * 60); // Strip seconds
                $oldStart = @$this->GetValue('StartTime');
                
                $machineElapsed = 0;
                if (isset($state['elapsedTime']) && is_array($state['elapsedTime']) && count($state['elapsedTime']) == 2) {
                    $machineElapsed = ($state['elapsedTime'][0] * 60) + $state['elapsedTime'][1];
                }
                
                if ($machineElapsed > 0) {
                    $elapsedMinutes = $machineElapsed;
                    $expectedStart = $now - ($elapsedMinutes * 60);
                    // Jitter protection: keep anchored StartTime if it's close
                    if ($oldStart > 0 && abs($expectedStart - $oldStart) < 300) {
                        $startTime = $oldStart;
                    } else {
                        $startTime = $expectedStart;
                    }
                } else {
                    // Falls der Trockner keine ElapsedTime schickt, berechnen wir sie selbst
                    if ($oldStart > 0 && $oldStart <= time()) {
                        $startTime = $oldStart;
                    } else {
                        $startTime = $now;
                    }
                    $elapsedMinutes = (int)round((time() - $startTime) / 60);
                }
                
                $finishTime = $now + ($remMinutes * 60);
                
                $total = $elapsedMinutes + $remMinutes;
                $progress = ($total > 0) ? (int)round(($elapsedMinutes / $total) * 100) : 0;
            } else if ($statusRaw == 4) { // Waiting to start
                $progress = 0;
                $elapsedMinutes = 0;
                if (isset($state['startTime']) && is_array($state['startTime']) && count($state['startTime']) == 2) {
                    $ts = mktime((int)$state['startTime'][0], (int)$state['startTime'][1], 0);
                    if ($ts < time() - (12 * 3600)) $ts += 86400; // Next day
                    $startTime = $ts;
                } else {
                    $startTime = 0;
                }
                $finishTime = ($startTime > 0) ? $startTime + ($remMinutes * 60) : 0;
            } else { // Off, Idle
                $progress = 0;
                $elapsedMinutes = 0;
                $remMinutes = 0;
                $startTime = 0;
                $finishTime = 0;
            }

            $this->SetValue('ElapsedTime', (int)$elapsedMinutes);
            $this->SetValue('RemainingTime', (int)$remMinutes);
            $this->SetValue('RemainingTimeSeconds', (int)($remMinutes * 60));
            $this->SetValue('StartTime', (int)$startTime);
            $this->SetValue('FinishTime', (int)$finishTime);
            $this->SetValue('ProgressPct', (int)$progress);
        }
    }

    public function UpdateDevice()
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            echo "Fehler: Bitte zuerst eine Device ID eintragen.\n";
            return;
        }

        $payload = [
            'DataID'=> '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command'=> 'ApiGet',
            'Endpoint'=> '/v1/devices/'. urlencode($deviceId) . '/state'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $state = json_decode($result, true);

        if ($state && is_array($state) && !isset($state['message'])) {
            $this->ProcessDeviceData(['state'=> $state]);
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            if (isset($state['message'])) {
                echo "Fehler beim Update: ". $state['message'] . "\n";
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prüfen.\n";
            }
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleDryer: '. $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "DeviceID",
                    "caption": "Miele Device ID (fabNumber)"
                }
            ]
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Gerät aktualisieren",
            "onClick": "SM_UpdateDevice($id);"
        }
    ]
}
EOT;
    }
}


