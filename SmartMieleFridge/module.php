<?php

declare(strict_types=1);

class SmartMieleFridge extends IPSModule
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
        
        $this->RegisterVariableFloat('Temperature1', 'Ist-Temperatur (Zone 1)', '', 20);
        $this->RegisterVariableFloat('TargetTemperature1', 'Ziel-Temperatur (Zone 1)', '', 25);
        $this->EnableAction('TargetTemperature1');
        
        $this->RegisterVariableBoolean('DoorOpen', 'Tür geöffnet', '~Alert', 30);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Status'), [
            'ICON' => 'Information'
        ]);
        
        $tempPresentation = [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
        ];
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Temperature1'), $tempPresentation);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TargetTemperature1'), [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature',
            'PRESENTATION' => 1, // Slider
            'MIN' => 1,
            'MAX' => 9,
            'STEP' => 1
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

            if (isset($state['temperature'][0]['value_raw'])) {
                $this->SetValue('Temperature1', $state['temperature'][0]['value_raw']);
            }
            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $this->SetValue('TargetTemperature1', $state['targetTemperature'][0]['value_raw']);
            }
            
            if (isset($state['signalDoor'])) {
                $this->SetValue('DoorOpen', $state['signalDoor']);
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
            echo "Gerät erfolgreich aktualisiert!\n";
        } else {
            if (isset($state['message'])) {
                echo "Fehler beim Update: " . $state['message'] . "\n";
            } else {
                echo "Fehler beim Update: Konnte keine Daten abrufen. Bitte API-Verbindung und Device ID prüfen.\n";
            }
        }
    }

    public function RequestAction($Ident, $Value)
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'TargetTemperature1':
                $actionData['targetTemperature'] = [
                    [
                        'zone' => 1,
                        'value' => $Value
                    ]
                ];
                break;

            default:
                throw new Exception('Invalid Action');
        }

        if (!empty($actionData)) {
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
            }
        }
    }
}
