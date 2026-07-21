<?php

declare(strict_types=1);

class MieleSplitter extends IPSModuleStrict
{
    public function Create(): void{
        parent::Create();
        
        // Self-healing for corrupted CustomPresentations
        foreach (@IPS_GetChildrenIDs($this->InstanceID) as $childID) {
            if (@IPS_VariableExists($childID)) {
                @IPS_SetVariableCustomPresentation($childID, []);
            }
        }
$this->RegisterPropertyString('ClientID', '');
        $this->RegisterPropertyString('ClientSecret', '');
        $this->RegisterPropertyString('Username', '');
        $this->RegisterPropertyString('Password', '');
        $this->RegisterPropertyString('Country', 'de-DE');
        $this->RegisterPropertyInteger('UpdateInterval', 60);

        $this->RegisterAttributeString('AccessToken', '');
        $this->RegisterAttributeString('RefreshToken', '');
        $this->RegisterAttributeInteger('TokenExpires', 0);

        $this->RegisterTimer('SM_UpdateData', 0, 'SM_FetchData($_IPS[\'TARGET\']);');
    }

    public function ApplyChanges(): void{
        parent::ApplyChanges();


        $interval = $this->ReadPropertyInteger('UpdateInterval');
        if ($interval < 30) {
            $interval = 30;
        }

        if ($this->ReadPropertyString('ClientID') != '' && $this->ReadPropertyString('Username') != '') {
            $this->SetTimerInterval('SM_UpdateData', $interval * 1000);
            $this->SetStatus(102); // Active
        } else {
            $this->SetTimerInterval('SM_UpdateData', 0);
            $this->SetStatus(104); // Inactive
        }
    }

    private function GetToken()
    {
        $token = $this->ReadAttributeString('AccessToken');
        $expires = $this->ReadAttributeInteger('TokenExpires');

        if ($token != '' && $expires > time() + 60) {
            return $token;
        }

        // We need a new token
        $clientId = $this->ReadPropertyString('ClientID');
        $clientSecret = $this->ReadPropertyString('ClientSecret');
        $username = $this->ReadPropertyString('Username');
        $password = $this->ReadPropertyString('Password');
        $country = $this->ReadPropertyString('Country');

        if (empty($clientId) || empty($username)) {
            $this->SendDebug('Auth', 'Credentials missing', 0);
            return false;
        }

        $url = 'https://api.mcs3.miele.com/thirdparty/token';
        $postData = http_build_query([
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'grant_type' => 'password',
            'username' => $username,
            'password' => $password,
            'state' => 'token',
            'redirect_uri' => '/v1/devices',
            'vg' => $country
        ]);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json; charset=utf-8',
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('Auth', 'HTTP Code: ' . $httpCode . ' Result: ' . $result, 0);

        if ($httpCode == 200 && $result) {
            $data = json_decode($result, true);
            if (isset($data['access_token'])) {
                $this->WriteAttributeString('AccessToken', $data['access_token']);
                if (isset($data['refresh_token'])) {
                    $this->WriteAttributeString('RefreshToken', $data['refresh_token']);
                }
                $this->WriteAttributeInteger('TokenExpires', time() + $data['expires_in']);
                $this->SetStatus(102);
                return $data['access_token'];
            }
        }
        $this->SetStatus(200); // Auth failed
        return false;
    }

    public function TestConnection(): void
    {
        $token = $this->GetToken();
        if ($token) {
            echo "Authentication successful!\n";
            $this->FetchData();
        } else {
            echo "Authentication failed. Please check credentials.\n";
        }
    }

    public function FetchData(): void
    {
        $token = $this->GetToken();
        if (!$token) {
            return;
        }

        $url = 'https://api.mcs3.miele.com/v1/devices';
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
            'Accept-Language: de'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('FetchData', 'HTTP Code: ' . $httpCode . ' Length: ' . strlen($result), 0);

        if ($httpCode == 200 && $result) {
            $data = json_decode($result, true);
            if (is_array($data)) {
                // Send to children
                $payload = [
                    'DataID'  => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                    'Devices' => $data
                ];
                $this->SendDataToChildren(json_encode($payload));
                $this->SetStatus(102);
            }
        } else {
            $this->SLog('ERROR', 'Fehler beim Abrufen der Gerätedaten (Miele API)', 'HTTP Code: ' . $httpCode . ' | Result: ' . $result);
            $this->SendDebug('FetchData', 'Error fetching data: ' . $result, 0);
            $this->SetStatus(201);
        }
    }

    public function ApiGet(string $endpoint)
    {
        $token = $this->GetToken();
        if (!$token) {
            return false;
        }

        $url = 'https://api.mcs3.miele.com' . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json; charset=utf-8',
            'Authorization: Bearer ' . $token,
            'Accept-Language: de'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ApiGet', 'Endpoint: ' . $endpoint . ' HTTP Code: ' . $httpCode, 0);

        if ($httpCode == 200 && $result) {
            return json_decode($result, true);
        }
        return false;
    }

    public function ExecuteAction(string $deviceId, array $actionData): bool
    {
        $token = $this->GetToken();
        if (!$token) {
            return false;
        }

        $url = 'https://api.mcs3.miele.com/v1/devices/' . urlencode($deviceId) . '/actions';
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($actionData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: */*',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
            'Accept-Language: de'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $result = curl_exec($ch);
        if ($result === false) {
            $this->SLog('ERROR', 'API-Anfrage fehlgeschlagen', curl_error($ch));
            curl_close($ch);
            return false;
        }
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ExecuteAction', 'Device: ' . $deviceId . ' Payload: ' . json_encode($actionData) . ' HTTP Code: ' . $httpCode . ' Result: ' . $result, 0);

        if ($httpCode == 200 || $httpCode == 204) {
            return true;
        }
        
        $resultData = @json_decode($result, true);
        if ($httpCode == 400 && is_array($resultData) && isset($resultData['message']) && strpos($resultData['message'], 'is not available for device') !== false) {
            $this->SLog('WARNING', "Aktion aktuell nicht verfügbar (Miele API)", $resultData['message']);
            return true;
        }

        $this->SLog('ERROR', "Fehler beim Ausführen der Aktion (Miele API)", "HTTP Code: " . $httpCode . " | Result: " . $result);
        return false;
    }

    public function ForwardData(string $JSONString): string
    {
        $data = json_decode($JSONString, true);
        if ($data['DataID'] == '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}') {
            if (isset($data['Command'])) {
                switch ($data['Command']) {
                    case 'ExecuteAction':
                        return json_encode($this->ExecuteAction($data['DeviceID'], $data['ActionData']));
                    case 'ApiGet':
                        return json_encode($this->ApiGet($data['Endpoint']));
                }
            }
        }
        return '{}';
    }

    private function SLog(string $level, string $message, string $details = ''): void
    {
        $source = static::class;
        $slogInstances = @IPS_GetInstanceListByModuleID('{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}');
        if (is_array($slogInstances) && count($slogInstances) > 0) {
            @SLOG_Log($slogInstances[0], $level, $source, $message, $details);
        } else {
            IPS_LogMessage('SmartVillaKunterbunt', $source . ': ' . $message);
        }
    }

    protected function LogMessage(string $Message, int $Type): bool
    {
        $this->SLog('INFO', $Message);
        IPS_LogMessage('SmartVillaKunterbunt', 'MieleSplitter: ' . $Message);
        return true;
    }

    public function GetConfigurationForm(): string
    {
        return <<<'EOT'
{
    "elements": [
        {
            "type": "Label",
            "caption": "Hey! Hier verbinden wir uns mit der Miele Cloud. Trag einfach deine Zugangsdaten und die API-Schlüssel ein."
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "ClientID",
                    "caption": "Client ID"
                },
                {
                    "type": "ValidationTextBox",
                    "name": "ClientSecret",
                    "caption": "Client Secret"
                }
            ]
        },
        {
            "type": "RowLayout",
            "items": [
                {
                    "type": "ValidationTextBox",
                    "name": "Username",
                    "caption": "Miele Username (Email)"
                }
            ]
        },
        {
            "type": "PasswordTextBox",
            "name": "Password",
            "caption": "Miele Password"
        },
        {
            "type": "Select",
            "name": "Country",
            "caption": "Country",
            "options": [
                {
                    "caption": "Deutschland",
                    "value": "de-DE"
                },
                {
                    "caption": "Österreich",
                    "value": "de-AT"
                },
                {
                    "caption": "Schweiz",
                    "value": "de-CH"
                }
            ]
        },
        {
            "type": "Label",
            "caption": "Wie oft soll ich die Daten für dich frisch aus dem Netz holen?"
        },
        {
            "type": "NumberSpinner",
            "name": "UpdateInterval",
            "caption": "Update Interval (seconds)",
            "minimum": 30,
            "maximum": 3600
        }
    ],
    "actions": [
        {
            "type": "Button",
            "caption": "Test Connection",
            "onClick": "SM_TestConnection($id);",
            "icon": "Play"
        },
        {
            "type": "Button",
            "caption": "Force Update",
            "onClick": "SM_FetchData($id);"
        }
    ],
    "status": [
        {
            "code": 102,
            "icon": "active",
            "caption": "Active"
        },
        {
            "code": 200,
            "icon": "error",
            "caption": "Authentication failed"
        },
        {
            "code": 201,
            "icon": "error",
            "caption": "API Error"
        }
    ]
}
EOT;
    }
}


