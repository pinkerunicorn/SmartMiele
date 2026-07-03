<?php

declare(strict_types=1);

class SmartMieleSplitter extends IPSModule
{
    public function Create()
    {
        parent::Create();
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

    public function ApplyChanges()
    {
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

    public function TestConnection()
    {
        $token = $this->GetToken();
        if ($token) {
            echo "Authentication successful!\n";
            $this->FetchData();
        } else {
            echo "Authentication failed. Please check credentials.\n";
        }
    }

    public function FetchData()
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('FetchData', 'HTTP Code: ' . $httpCode . ' Length: ' . strlen($result), 0);

        if ($httpCode == 200 && $result) {
            $data = json_decode($result, true);
            if (is_array($data)) {
                // Send to children
                $payload = [
                    'DataID' => '{D90209DA-6A59-4DD8-96BC-6878CE50ACCC}',
                    'Devices' => $data
                ];
                $this->SendDataToChildren(json_encode($payload));
                $this->SetStatus(102);
            }
        } else {
            $this->SendDebug('FetchData', 'Error fetching data: ' . $result, 0);
            $this->SetStatus(201); // API Error
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ApiGet', 'Endpoint: ' . $endpoint . ' HTTP Code: ' . $httpCode, 0);

        if ($httpCode == 200 && $result) {
            return json_decode($result, true);
        }
        return false;
    }

    public function ExecuteAction(string $deviceId, array $actionData)
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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->SendDebug('ExecuteAction', 'Device: ' . $deviceId . ' Payload: ' . json_encode($actionData) . ' HTTP Code: ' . $httpCode . ' Result: ' . $result, 0);

        if ($httpCode == 200 || $httpCode == 204) {
            // Give API a moment to apply change, then fetch new data
            IPS_Sleep(1000);
            $this->FetchData();
            return true;
        }
        
        return false;
    }

    public function ForwardData($JSONString)
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
}
