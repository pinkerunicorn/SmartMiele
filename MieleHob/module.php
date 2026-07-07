<?php

declare(strict_types=1);

class MieleHob extends IPSModule
{
    public function Create(): void
    {
        parent::Create();
        
        $this->RegisterPropertyString('DeviceID', '');
        $this->RegisterPropertyInteger('PlateCount', 4);

        // Connect to Splitter
        $this->ConnectParent('{16E6F7DB-7B41-47D4-A2AD-DA0D029DDCB5}');
        
        // Variables
        $this->RegisterVariableString('StatusText', 'ℹ️ Status', '', 10);
        
        // Dynamisch je nach Modell Kochzonen anlegen (meistens 4-6)
        // Wir legen prophylaktisch 4 an
        for ($i=1; $i<=4; $i++) {
            $this->RegisterVariableInteger('Plate' . $i, '♨️ Kochzone ' . $i, 'Miele.HobPlate', 20 + $i);
        }
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
            'PRESENTATION' => 0, // VARIABLE_PRESENTATION_LABEL
            'ICON' => 'Information'
        ]);

        $plates = $this->ReadPropertyInteger('PlateCount');
        
        $associations = [
            [0, 'Aus', '', 0xFFFFFF]
        ];
        for ($s=1; $s<=9; $s++) {
            $associations[] = [$s, 'Stufe '.$s, '', -1];
        }

        if (!IPS_VariableProfileExists('Miele.HobPlate')) {
            IPS_CreateVariableProfile('Miele.HobPlate', 1);
            IPS_SetVariableProfileIcon('Miele.HobPlate', 'Flame');
            IPS_SetVariableProfileText('Miele.HobPlate', '', ' Stufe');
            foreach ($associations as $ass) {
                IPS_SetVariableProfileAssociation('Miele.HobPlate', $ass[0], $ass[1], $ass[2], $ass[3]);
            }
        }

        for ($i = 1; $i <= $plates; $i++) {
            $this->RegisterVariableInteger('Plate' . $i, 'Kochzone ' . $i, 'Miele.HobPlate', 20 + $i);
        }
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

            if (isset($state['plateStep']) && is_array($state['plateStep'])) {
                $plates = $this->ReadPropertyInteger('PlateCount');
                for ($i = 0; $i < $plates; $i++) {
                    if (isset($state['plateStep'][$i]['value_raw'])) {
                        $this->SetValue('Plate' . ($i + 1), (int)$state['plateStep'][$i]['value_raw']);
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
