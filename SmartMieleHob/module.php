<?php

declare(strict_types=1);

class SmartMieleHob extends IPSModule
{
    public function Create()
    {
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('PlateCount', 4);

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableInteger('Status', 'Status', '', 10);
        $this->RegisterVariableString('StatusText', 'Status (Text)', '', 15);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('Status'), [
            'ICON' => 'Information'
        ]);

        $plates = $this->ReadPropertyInteger('PlateCount');
        for ($i = 1; $i <= $plates; $i++) {
            $this->RegisterVariableInteger('Plate' . $i, 'Kochzone ' . $i, '', 20 + $i);
            
            IPS_SetVariableCustomPresentation($this->GetIDForIdent('Plate' . $i), [
                'ICON' => 'Flame',
                'SUFFIX' => ' Stufe'
            ]);
        }
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

            if (isset($state['plateStep']) && is_array($state['plateStep'])) {
                $plates = $this->ReadPropertyInteger('PlateCount');
                for ($i = 0; $i < $plates; $i++) {
                    if (isset($state['plateStep'][$i]['value_raw'])) {
                        $this->SetValue('Plate' . ($i + 1), $state['plateStep'][$i]['value_raw']);
                    }
                }
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
}
