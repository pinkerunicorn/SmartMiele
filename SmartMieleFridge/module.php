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
        $this->RegisterVariableString('StatusText', 'Status', '', 15);
        
        $this->RegisterVariableFloat('Temp1', 'Ist-Temperatur (Zone 1)', '', 20);
        $this->RegisterVariableFloat('TargetTemp1', 'Ziel-Temperatur (Zone 1)', '', 25);
        $this->EnableAction('TargetTemp1');
        
        $this->RegisterVariableBoolean('DoorOpen', 'Tür geöffnet', '~Alert', 30);

        $this->RegisterVariableBoolean('SuperCooling', 'Schnellkühlen', '~Switch', 35);
        $this->EnableAction('SuperCooling');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
            'ICON' => 'Information'
        ]);
        
        $tempPresentation = [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
        ];
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Temp1'), $tempPresentation);
        
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('TargetTemp1'), [
            'SUFFIX' => ' °C',
            'ICON' => 'Temperature'
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
            if (isset($state['status']['value_raw'])) {
                $statusRaw = $state['status']['value_raw'];
                $isSuperCooling = ($statusRaw == 14 || $statusRaw == 146);
                $this->SetValue('SuperCooling', $isSuperCooling);
            }

            if (isset($state['temperature'][0]['value_raw'])) {
                $valTemp = $state['temperature'][0]['value_raw'];
                $this->SendDebug('Temp Update', 'Raw: ' . $valTemp . ' Type: ' . gettype($valTemp), 0);
                $this->SetValue('Temp1', (float)$valTemp);
            }
            if (isset($state['targetTemperature'][0]['value_raw'])) {
                $valTarget = $state['targetTemperature'][0]['value_raw'];
                $this->SendDebug('TargetTemp Update', 'Raw: ' . $valTarget . ' Type: ' . gettype($valTarget), 0);
                
                $varID = @$this->GetIDForIdent('TargetTemp1');
                if ($varID) {
                    $varObj = @IPS_GetVariable($varID);
                    if ($varObj) {
                        $this->SendDebug('TargetTemp Update', 'VarID: ' . $varID . ' SymconType: ' . $varObj['VariableType'], 0);
                    }
                }

                try {
                    $this->SetValue('TargetTemp1', (float)$valTarget);
                } catch (\Throwable $e) {
                    $this->SendDebug('TargetTemp Error', $e->getMessage(), 0);
                    IPS_LogMessage('SmartMieleFridge', 'Error setting TargetTemp1: ' . $e->getMessage());
                }
            }
            
            if (isset($state['signalDoor'])) {
                $this->SetValue('DoorOpen', (bool)$state['signalDoor']);
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
            case 'TargetTemp1':
                $actionData['targetTemperature'] = [
                    [
                        'zone' => 1,
                        'value' => $Value
                    ]
                ];
                break;
            case 'SuperCooling':
                $actionData['processAction'] = $Value ? 6 : 7;
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
