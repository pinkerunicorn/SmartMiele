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
        $this->RegisterVariableString('StatusText', 'ℹ️ Status', '', 10);
        $this->RegisterVariableBoolean('Light', '💡 Licht', '~Switch', 20);
        $this->EnableAction('Light');
        
        $this->RegisterVariableInteger('VentilationStep', '💨 Lüfterstufe', '', 30);
        $this->EnableAction('VentilationStep');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Symcon 8 Custom Presentations
        IPS_SetVariableCustomPresentation($this->GetIDForIdent('StatusText'), [
            'ICON' => 'Information'
        ]);

        IPS_SetVariableCustomPresentation($this->GetIDForIdent('VentilationStep'), [
            'PRESENTATION' => VARIABLE_PRESENTATION_SLIDER, // Slider
            'MIN' => 0.0,
            'MAX' => 4.0,
            'STEP' => 1.0,
            'SUFFIX' => ' Stufe',
            'ICON' => 'Ventilator'
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
                $deviceData = $data['Devices'][$deviceId];
                $this->ProcessDeviceData($deviceData);
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

            // Light (Miele API: 1=On, 2=Off)
            if (isset($state['light'])) {
                $isLightOn = ($state['light'] == 1);
                $this->SetValue('Light', (bool)$isLightOn);
            }

            // VentilationStep
            if (isset($state['ventilationStep']['value_raw'])) {
                $this->SetValue('VentilationStep', (int)$state['ventilationStep']['value_raw']);
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

    protected function Log(string $text): void
    {
        IPS_LogMessage('SmartVillaKunterbunt', 'SmartMieleHood: ' . $text);
    }

    public function RequestAction($Ident, $Value)
    {
        $deviceId = $this->ReadPropertyString('DeviceID');
        if (empty($deviceId)) {
            $this->Log("Device ID not configured.");
            echo "Device ID not configured.\n";
            return;
        }

        $actionData = [];

        switch ($Ident) {
            case 'Light':
                // Miele API: 1=On, 2=Off
                $actionData['light'] = $Value ? 1 : 2;
                $this->Log("Schalte Licht: " . ($Value ? 'An' : 'Aus'));
                break;
            
            case 'VentilationStep':
                $actionData['ventilationStep'] = $Value;
                $this->Log("Setze Lüfterstufe: " . $Value);
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
                $this->Log("Fehler beim Ausführen der Aktion.");
                echo "Fehler beim Ausführen der Aktion.\n";
            }
        }
    }
}
