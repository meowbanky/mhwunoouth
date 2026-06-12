<?php
ob_start();

if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['UserID']) || trim($_SESSION['UserID']) == '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    if (!file_exists(__DIR__ . '/../classes/OOUTHSalaryAPIClient.php')) {
        throw new Exception('OOUTHSalaryAPIClient.php not found at: ' . __DIR__ . '/../classes/');
    }
    require_once(__DIR__ . '/../classes/OOUTHSalaryAPIClient.php');

    $action    = $_GET['action'] ?? '';
    $apiClient = new OOUTHSalaryAPIClient();

    switch ($action) {

        case 'get_periods':
            // Authenticate first so we can give a clear error if credentials are wrong
            if (!$apiClient->authenticate()) {
                $authErr = $apiClient->getLastAuthError();
                throw new Exception('API auth failed — key_len:' . strlen(OOUTH_API_KEY) . ' secret_len:' . strlen(OOUTH_API_SECRET) . ' response:' . json_encode($authErr));
            }
            $result = $apiClient->getPeriods(1, 1000);
            if ($result && isset($result['success']) && $result['success']) {
                echo json_encode(['success' => true, 'data' => $result['data']]);
            } else {
                throw new Exception($result['error']['message'] ?? $result['message'] ?? 'Failed to fetch periods from API');
            }
            break;

        case 'get_active_period':
            $result = $apiClient->getActivePeriod();
            if ($result && isset($result['success']) && $result['success']) {
                echo json_encode(['success' => true, 'data' => $result['data']['period']]);
            } else {
                throw new Exception($result['error']['message'] ?? 'Failed to fetch active period');
            }
            break;

        case 'get_data':
            $periodId = $_GET['period'] ?? null;
            if (!$periodId) throw new Exception('Period ID is required');
            $result = $apiClient->getResourceData($periodId);
            if ($result && isset($result['success']) && $result['success']) {
                echo json_encode([
                    'success'  => true,
                    'data'     => $result['data'],
                    'metadata' => $result['metadata'],
                ]);
            } else {
                throw new Exception($result['error']['message'] ?? 'Failed to fetch data from API');
            }
            break;

        case 'get_local_periods':
            if (!file_exists(__DIR__ . '/../Connections/hms.php')) {
                throw new Exception('hms.php not found at: ' . __DIR__ . '/../Connections/');
            }
            require_once(__DIR__ . '/../Connections/hms.php');
            $query  = "SELECT Periodid, PayrollPeriod, PhysicalYear, PhysicalMonth
                       FROM tbpayrollperiods ORDER BY Periodid DESC";
            $result = mysqli_query($hms, $query);
            if ($result) {
                $periods = [];
                while ($row = mysqli_fetch_assoc($result)) $periods[] = $row;
                echo json_encode(['success' => true, 'data' => $periods]);
            } else {
                throw new Exception('Failed to fetch local periods: ' . mysqli_error($hms));
            }
            break;

        case 'test_connection':
            if ($apiClient->authenticate()) {
                echo json_encode([
                    'success' => true,
                    'message' => 'API connection successful',
                    'data'    => [
                        'resource_type' => OOUTH_RESOURCE_TYPE,
                        'resource_id'   => OOUTH_RESOURCE_ID,
                        'resource_name' => OOUTH_RESOURCE_NAME,
                    ]
                ]);
            } else {
                throw new Exception('Failed to authenticate with API');
            }
            break;

        case 'test_sig_variants':
            // Try all signature variants and report which one the API accepts
            $ts      = time();
            $key     = OOUTH_API_KEY;
            $secret  = OOUTH_API_SECRET;
            $sigStr  = $key . $ts;
            $decoded = hex2bin($secret);

            $variants = [
                'hex_raw_secret'    => hash_hmac('sha256', $sigStr, $secret),
                'hex_decoded_secret'=> hash_hmac('sha256', $sigStr, $decoded),
                'b64_raw_secret'    => base64_encode(hash_hmac('sha256', $sigStr, $secret,  true)),
                'b64_decoded_secret'=> base64_encode(hash_hmac('sha256', $sigStr, $decoded, true)),
                'ts_only_hex'       => hash_hmac('sha256', (string)$ts, $secret),
                'ts_only_hex_dec'   => hash_hmac('sha256', (string)$ts, $decoded),
            ];

            $results = [];
            foreach ($variants as $name => $sig) {
                $ch = curl_init(OOUTH_API_BASE_URL . '/auth/token');
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST  => 'POST',
                    CURLOPT_TIMEOUT        => 10,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'X-API-Key: '    . $key,
                        'X-Timestamp: '  . $ts,
                        'X-Signature: '  . $sig,
                    ],
                ]);
                $raw  = curl_exec($ch);
                $err  = curl_error($ch);
                curl_close($ch);
                $resp = $err ? ['curl_error' => $err] : json_decode($raw, true);
                $results[$name] = [
                    'sig'     => substr($sig, 0, 20) . '...',
                    'success' => $resp['success'] ?? false,
                    'code'    => $resp['error']['code'] ?? ($resp['success'] ? 'OK' : '?'),
                ];
            }
            echo json_encode(['success' => true, 'timestamp' => $ts, 'variants' => $results]);
            break;

        default:
            throw new Exception('Invalid action specified');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
