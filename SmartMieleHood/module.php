<?php

declare(strict_types=1);

class SmartMieleHood extends IPSModule
{
    public function Create()
    {
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableInteger('Status', 'Status', '', 10);
        $this->RegisterVariableBoolean('Light', 'Licht', '~Switch', 20);
        $this->EnableAction('Light');
        
        $this->RegisterVariableInteger('VentilationStep', 'Lüfterstufe', '', 30);
        $this->EnableAction('VentilationStep');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Status'), [
            'ICON' => 'Information'
        ]);

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('VentilationStep'), [
            'PRESENTATION' => 1, // Slider
            'MIN' => 0,
            'MAX' => 4,
            'STEP' => 1,
            'SUFFIX' => ' Stufe',
            'ICON' => 'Ventilator'
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
                $deviceData = $data['Devices'][$deviceId];
                $this->ProcessDeviceData($deviceData);
            }
        }
    }

    private function ProcessDeviceData(array $deviceData)
    {
        if (isset($deviceData['state'])) {
            $state = $deviceData['state'];

            // Status
            if (isset($state['status']['value_raw'])) {
                $this->SetValue('Status', $state['status']['value_raw']);
            }

            // Light (Miele API: 1=On, 2=Off)
            if (isset($state['light'])) {
                $isLightOn = ($state['light'] == 1);
                $this->SetValue('Light', $isLightOn);
            }

            // VentilationStep
            if (isset($state['ventilationStep']['value_raw'])) {
                $this->SetValue('VentilationStep', $state['ventilationStep']['value_raw']);
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            echo "Device ID not configured.\n";
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'Light':
                // Miele API: 1=On, 2=Off
                $actionData['light'] = $Value ? 1 : 2;
                break;
            
            case 'VentilationStep':
                $actionData['ventilationStep'] = $Value;
                break;

            default:
                throw new Exception('Invalid Action');
        }

        if (!empty($actionData)) {
            // Forward to Splitter
            $payload = [
                'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                'Command' => 'ExecuteAction',
                'DeviceID' => $deviceId,
                'ActionData' => $actionData
            ];
            
            $result = $this->SendDataToParent(json_encode($payload));
            $success = json_decode($result, true);

            if ($success) {
                $this->SetValue($Ident, $Value);
            } else {
                echo "Fehler beim Ausführen der Aktion.\n";
            }
        }
    }
}
