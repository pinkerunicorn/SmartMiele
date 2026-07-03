<?php

declare(strict_types=1);

class SmartMieleWasher extends IPSModule
{
    public function Create()
    {
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyBoolean('EnableTwinDos', true);

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableString('StatusText', 'Status', '', 10);
        $this->RegisterVariableBoolean('SignalInfo', 'Hinweis vorhanden', '', 11);
        $this->RegisterVariableBoolean('SignalFailure', 'Fehler erkannt', '~Alert', 12);
        
        $this->RegisterVariableString('ProgramName', 'Programmbezeichnung', '', 21);
        $this->RegisterVariableString('ProgramPhaseText', 'Programm-Phase', '', 22);
        
        $this->RegisterVariableInteger('StartTime', 'Start um', '~UnixTimestampTime', 25);
        $this->RegisterVariableInteger('FinishTime', 'Ende um', '~UnixTimestampTime', 26);
        $this->RegisterVariableInteger('ElapsedTime', 'verstrichene Zeit', '', 27);
        $this->RegisterVariableInteger('RemainingTime', 'verbleibende Zeit', '', 28);
        $this->RegisterVariableInteger('ProgressPct', 'Arbeitsfortschritt', '', 29);
        
        $this->RegisterVariableInteger('Temperature', 'Temperatur', '', 31);
        $this->RegisterVariableInteger('SpinSpeed', 'Drehzahl', '', 32);
        $this->RegisterVariableBoolean('Door', 'Tür', '~Window', 33);
        
        $this->RegisterVariableInteger('TwinDos1', 'TwinDos 1 Füllstand', '', 40);
        $this->RegisterVariableInteger('TwinDos2', 'TwinDos 2 Füllstand', '', 45);
        
        $this->RegisterVariableFloat('CurrentWaterConsumption', 'aktueller Wasserverbrauch', '', 50);
        $this->RegisterVariableFloat('CurrentEnergyConsumption', 'aktueller Energieverbrauch', '', 55);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
            'ICON' => 'Information'
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
            'ICON' => 'Graph'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Temperature'), [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('SpinSpeed'), [
            'SUFFIX' => ' U/min',
            'ICON' => 'Motion'
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

    public function ReceiveData($JSONString)
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
            if (isset($fillingLevels['twinDosContainer1FillingLevel']['value_raw'])) {
                $this->SetValue('TwinDos1', (int)$fillingLevels['twinDosContainer1FillingLevel']['value_raw']);
            }
            if (isset($fillingLevels['twinDosContainer2FillingLevel']['value_raw'])) {
                $this->SetValue('TwinDos2', (int)$fillingLevels['twinDosContainer2FillingLevel']['value_raw']);
            }
        }
    }

    private function ProcessDeviceData(array $deviceData)
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_localized'])) {
                $this->SetValue('StatusText', (string)$state['status']['value_localized']);
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
                if ($t > -100) $this->SetValue('Temperature', (int)$t);
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

            $totalMinutes = 0;
            if (isset($state['remainingTime']) && is_array($state['remainingTime'])) {
                $hours = $state['remainingTime'][0] ?? 0;
                $minutes = $state['remainingTime'][1] ?? 0;
                $totalMinutes = ($hours * 60) + $minutes;
                $this->SetValue('RemainingTime', (int)$totalMinutes);
                
                if ($totalMinutes > 0) {
                    $this->SetValue('FinishTime', time() + ($totalMinutes * 60));
                } else {
                    $this->SetValue('FinishTime', 0);
                }
            }
            
            $elapsedMinutes = 0;
            if (isset($state['elapsedTime']) && is_array($state['elapsedTime'])) {
                $hours = $state['elapsedTime'][0] ?? 0;
                $minutes = $state['elapsedTime'][1] ?? 0;
                $elapsedMinutes = ($hours * 60) + $minutes;
                $this->SetValue('ElapsedTime', (int)$elapsedMinutes);
            }
            
            $statusRaw = $state['status']['value_raw'] ?? 0;
            
            if ($statusRaw == 4) { // Waiting to start (Startzeitvorwahl)
                if (isset($state['startTime']) && is_array($state['startTime'])) {
                    $hours = $state['startTime'][0] ?? 0;
                    $minutes = $state['startTime'][1] ?? 0;
                    if ($hours > 0 || $minutes > 0) {
                        $ts = mktime((int)$hours, (int)$minutes, 0);
                        if ($ts < time() - (12 * 3600)) {
                            $ts += 86400; // Next day
                        }
                        $this->SetValue('StartTime', $ts);
                    } else {
                        $this->SetValue('StartTime', 0);
                    }
                }
            } else if ($statusRaw == 5 || $statusRaw == 7) { // In use or Finished
                if ($elapsedMinutes > 0) {
                    $this->SetValue('StartTime', time() - ($elapsedMinutes * 60));
                } else {
                    $this->SetValue('StartTime', 0);
                }
            } else {
                $this->SetValue('StartTime', 0);
            }
            
            if ($totalMinutes > 0 || $elapsedMinutes > 0) {
                $total = $totalMinutes + $elapsedMinutes;
                if ($total > 0) {
                    $progress = (int)round(($elapsedMinutes / $total) * 100);
                    $this->SetValue('ProgressPct', $progress);
                }
            } else {
                $this->SetValue('ProgressPct', 0);
            }
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
