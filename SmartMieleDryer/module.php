<?php

declare(strict_types=1);

class SmartMieleDryer extends IPSModule
{
    public function Create()
    {
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableInteger('Status', 'Status', '', 10);
        $this->RegisterVariableString('StatusText', 'Status (Text)', '', 15);
        
        $this->RegisterVariableInteger('ProgramPhase', 'Programmphase', '', 20);
        $this->RegisterVariableString('ProgramPhaseText', 'Programmphase (Text)', '', 25);
        
        $this->RegisterVariableInteger('RemainingTime', 'Verbleibende Zeit', '', 30);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Status'), json_encode([
            'Icon' => 'Information'
        ]));
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('RemainingTime'), json_encode([
            'Suffix' => ' min',
            'Icon' => 'Clock'
        ]));
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
}
