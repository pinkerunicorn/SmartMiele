<?php

declare(strict_types=1);

class MieleWasher extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        
        
        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }
$this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyBoolean('EnableTwinDos', true);

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableString('StatusText', 'ℹ️ Status', '', 10);
        $this->RegisterVariableBoolean('SignalInfo', '🔔 Hinweis vorhanden', '', 11);
        $this->RegisterVariableBoolean('SignalFailure', '⚠️ Fehler erkannt', '', 12);
        
        $this->RegisterVariableString('ProgramName', '📝 Programmbezeichnung', '', 21);
        $this->RegisterVariableString('ProgramPhaseText', '🔄 Programm-Phase', '', 22);
        
        $this->RegisterVariableInteger('StartTime', '▶️ Start um', '', 25);
        $this->RegisterVariableInteger('FinishTime', '⏹️ Ende um', '', 26);
        $this->RegisterVariableInteger('ElapsedTime', '⏱️ verstrichene Zeit', '', 27);
        $this->RegisterVariableInteger('RemainingTime', '⏳ verbleibende Zeit', '', 28);
        $this->RegisterVariableInteger('ProgressPct', '📈 Arbeitsfortschritt', '', 29);
        
        $this->RegisterVariableInteger('Temperature', '🌡️ Temperatur', '', 31);
        $this->RegisterVariableInteger('SpinSpeed', '🌪️ Drehzahl', '', 32);
        $this->RegisterVariableBoolean('Door', '🚪 Tür', '', 33);
        
        $this->RegisterVariableInteger('TwinDos1', '💧 TwinDos 1 Füllstand', '', 40);
        $this->RegisterVariableInteger('TwinDos2', '💧 TwinDos 2 Füllstand', '', 45);
        
        $this->RegisterVariableFloat('CurrentWaterConsumption', '🚰 aktueller Wasserverbrauch', '', 50);
        $this->RegisterVariableFloat('CurrentEnergyConsumption', '⚡ aktueller Energieverbrauch', '', 55);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
            'ICON' => 'Information'
        ]);

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SignalFailure'), [
            'ICON' => 'Alert'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StartTime'), [
            'ICON' => 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('FinishTime'), [
            'ICON' => 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('ElapsedTime'), [
            'SUFFIX' => ' min',
            'ICON' => 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('RemainingTime'), [
            'SUFFIX' => ' min',
            'ICON' => 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('ProgressPct'), [
            'SUFFIX' => ' %',
            'ICON' => 'Intensity'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Temperature'), [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SpinSpeed'), [
            'SUFFIX' => ' U/min',
            'ICON' => 'Motion'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Door'), [
            'ICON' => 'Window'
        ]);

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('CurrentWaterConsumption'), [
            'SUFFIX' => ' l',
            'ICON' => 'Drop'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('CurrentEnergyConsumption'), [
            'SUFFIX' => ' kWh',
            'ICON' => 'Electricity'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TwinDos1'), [
            'SUFFIX' => ' %',
            'ICON' => 'Drop'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TwinDos2'), [
            'SUFFIX' => ' %',
            'ICON' => 'Drop'
        ]);
    }

    public function ReceiveData($JSONString): void
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            $deviceId = $this->ReadPropertyString('DeviceID');
            if (empty($deviceId)) {
                return;
            }

            if (isset($data['Devices'][$deviceId])) {
                $this->ProcessDeviceData($data['Devices'][$deviceId]);
                
                if ($this->ReadPropertyBoolean('EnableTwinDos')) {
                    $this->FetchFillingLevels($deviceId);
                }
            }
        }
    }

    private function FetchFillingLevels($deviceId)
    {
        $payload = [
            'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ApiGet',
            'Endpoint' => '/v1/devices/' . urlencode($deviceId) . '/fillingLevels'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $fillingLevels = json_decode($result, true);
        
        if ($fillingLevels) {
            if (isset($fillingLevels['twinDosContainer1FillingLevel'])) {
                $val = is_array($fillingLevels['twinDosContainer1FillingLevel']) ? $fillingLevels['twinDosContainer1FillingLevel']['value_raw'] : $fillingLevels['twinDosContainer1FillingLevel'];
                $this->SetValue('TwinDos1', (int)$val);
            }
            if (isset($fillingLevels['twinDosContainer2FillingLevel'])) {
                $val = is_array($fillingLevels['twinDosContainer2FillingLevel']) ? $fillingLevels['twinDosContainer2FillingLevel']['value_raw'] : $fillingLevels['twinDosContainer2FillingLevel'];
                $this->SetValue('TwinDos2', (int)$val);
            }
        }
    }

    protected function Log(string $text): void
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleWasher: ' . $text);
    }

    private function ProcessDeviceData(array $deviceData)
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $newStatus = (string)$state['status']['value_localized'];
                if (@$this->GetValue('StatusText') !== $newStatus) {
                    $this->Log("Status geändert: " . $newStatus);
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

            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $t = $state['targetTemperature'][0]['value_raw'];
                if ($t > -100) {
                    if ($t >= 1000) {
                        $this->SetValue('Temperature', (int)($t / 100));
                    } else {
                        $this->SetValue('Temperature', (int)$t);
                    }
                }
            }
            if (isset($state['spinningSpeed']['value_raw'])) {
                $s = $state['spinningSpeed']['value_raw'];
                if ($s > -1) $this->SetValue('SpinSpeed', (int)$s);
            }
            if (isset($state['signalDoor'])) {
                $this->SetValue('Door', (bool)$state['signalDoor']);
            }
            
            if (isset($state['ecoFeedback']['currentWaterConsumption']['value'])) {
                $this->SetValue('CurrentWaterConsumption', (float)$state['ecoFeedback']['currentWaterConsumption']['value']);
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
                    // Miele Waschmaschinen senden oft KEINE verstrichene Zeit.
                    // Wir frieren die Startzeit ein und berechnen die verstrichene Zeit selbst!
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
            'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
            'Command' => 'ApiGet',
            'Endpoint' => '/v1/devices/' . urlencode($deviceId) . '/state'
        ];
        
        $result = $this->SendDataToParent(json_encode($payload));
        $state = json_decode($result, true);

        if ($state && is_array($state) && !isset($state['message'])) {
            $this->ProcessDeviceData(['state' => $state]);
            
            if ($this->ReadPropertyBoolean('EnableTwinDos')) {
                $this->FetchFillingLevels($deviceId);
            }
            
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            if (isset($state['message'])) {
                echo "Fehler beim Update: " . $state['message'] . "\n";
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prüfen.\n";
            }
        }
    }
}
