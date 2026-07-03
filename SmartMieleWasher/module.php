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
        $this->RegisterVariableInteger('Status', 'Status', '', 10);
        $this->RegisterVariableString('StatusText', 'Status (Text)', '', 15);
        
        $this->RegisterVariableInteger('ProgramPhase', 'Programmphase', '', 20);
        $this->RegisterVariableString('ProgramPhaseText', 'Programmphase (Text)', '', 25);
        
        $this->RegisterVariableInteger('RemainingTime', 'Verbleibende Zeit', '', 30);
        
        $this->RegisterVariableInteger('TwinDos1', 'TwinDos 1 Füllstand', '', 40);
        $this->RegisterVariableInteger('TwinDos2', 'TwinDos 2 Füllstand', '', 45);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Status'), [
            'ICON' => 'Information'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('RemainingTime'), [
            'SUFFIX' => ' min',
            'ICON' => 'Clock'
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TwinDos1'), [
            'SUFFIX' => ' %',
            'ICON' => 'Drop',
            'PRESENTATION' => 1,
            'MIN' => 0,
            'MAX' => 100
        ]);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TwinDos2'), [
            'SUFFIX' => ' %',
            'ICON' => 'Drop',
            'PRESENTATION' => 1,
            'MIN' => 0,
            'MAX' => 100
        ]);
    }

    public function ReceiveData($JSONString)
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == '{11A893D6-2EE7-48DF-AA82-2CFF2BA74B64}') {
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
                $this->SetValue('TwinDos1', $fillingLevels['twinDosContainer1FillingLevel']['value_raw']);
            }
            if (isset($fillingLevels['twinDosContainer2FillingLevel']['value_raw'])) {
                $this->SetValue('TwinDos2', $fillingLevels['twinDosContainer2FillingLevel']['value_raw']);
            }
        }
    }

    private function ProcessDeviceData(array $deviceData)
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            if (isset($state['status']['value_raw'])) {
                $this->SetValue('Status', $state['status']['value_raw']);
                $this->SetValue('StatusText', $state['status']['value_localized'] ?? '');
            }

            if (isset($state['programPhase']['value_raw'])) {
                $this->SetValue('ProgramPhase', $state['programPhase']['value_raw']);
                $this->SetValue('ProgramPhaseText', $state['programPhase']['value_localized'] ?? '');
            }

            if (isset($state['remainingTime']) && is_array($state['remainingTime'])) {
                $hours = $state['remainingTime'][0] ?? 0;
                $minutes = $state['remainingTime'][1] ?? 0;
                $totalMinutes = ($hours * 60) + $minutes;
                $this->SetValue('RemainingTime', $totalMinutes);
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
