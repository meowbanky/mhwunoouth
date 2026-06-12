<?php
/**
 * OOUTH Salary API Client
 */

require_once(__DIR__ . '/../config/api_config.php');

class OOUTHSalaryAPIClient {

    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $jwtToken    = null;
    private $tokenExpiry = null;
    private $timeout;
    private $debug;
    private $lastAuthError = null;

    public function __construct() {
        $this->baseUrl   = OOUTH_API_BASE_URL;
        $this->apiKey    = OOUTH_API_KEY;
        $this->apiSecret = OOUTH_API_SECRET;
        $this->timeout   = OOUTH_API_TIMEOUT;
        $this->debug     = OOUTH_API_DEBUG;
    }

    public function authenticate() {
        try {
            $timestamp       = time();
            $signatureString = $this->apiKey . $timestamp;
            $signature       = hash_hmac('sha256', $signatureString, $this->apiSecret);

            $response = $this->request('POST', '/auth/token', null, [
                'X-Timestamp' => $timestamp,
                'X-Signature' => $signature
            ]);

            if ($response && isset($response['success']) && $response['success']) {
                $this->jwtToken    = $response['data']['access_token'];
                $this->tokenExpiry = time() + ($response['data']['expires_in'] ?? 900);
                return true;
            }
            $this->lastAuthError = $response;
            return false;
        } catch (Exception $e) {
            $this->lastAuthError = ['exception' => $e->getMessage()];
            return false;
        }
    }

    private function ensureAuthenticated() {
        if ($this->jwtToken && $this->tokenExpiry && time() < ($this->tokenExpiry - 60)) {
            return true;
        }
        return $this->authenticate();
    }

    public function getPeriods($page = 1, $limit = 100) {
        if (!$this->ensureAuthenticated()) return null;
        return $this->request('GET', "/payroll/periods?page={$page}&limit={$limit}");
    }

    public function getActivePeriod() {
        if (!$this->ensureAuthenticated()) return null;
        return $this->request('GET', '/payroll/periods/active');
    }

    public function getPeriod($periodId) {
        if (!$this->ensureAuthenticated()) return null;
        return $this->request('GET', "/payroll/periods/{$periodId}");
    }

    public function getDeductions($deductionId, $periodId = null) {
        if (!$this->ensureAuthenticated()) return null;
        $url = "/payroll/deductions/{$deductionId}";
        if ($periodId !== null) $url .= "?period={$periodId}";
        return $this->request('GET', $url);
    }

    public function getAllowances($allowanceId, $periodId = null) {
        if (!$this->ensureAuthenticated()) return null;
        $url = "/payroll/allowances/{$allowanceId}";
        if ($periodId !== null) $url .= "?period={$periodId}";
        return $this->request('GET', $url);
    }

    public function getLastAuthError() {
        return $this->lastAuthError;
    }

    public function getResourceData($periodId = null) {
        if (OOUTH_RESOURCE_TYPE === 'deduction') {
            return $this->getDeductions(OOUTH_RESOURCE_ID, $periodId);
        }
        return $this->getAllowances(OOUTH_RESOURCE_ID, $periodId);
    }

    private function request($method, $endpoint, $body = null, $extraHeaders = []) {
        $url     = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'X-API-Key: ' . $this->apiKey,
        ];

        if ($this->jwtToken) {
            $headers[] = 'Authorization: Bearer ' . $this->jwtToken;
        }
        foreach ($extraHeaders as $k => $v) {
            $headers[] = "{$k}: {$v}";
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST,  $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER,     $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT,        $this->timeout);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // TODO: re-enable once server CA certs confirmed
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if ($body) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $response = curl_exec($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            if ($this->debug) error_log('OOUTH API cURL error: ' . $error);
            return null;
        }

        return json_decode($response, true);
    }
}
