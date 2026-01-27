<?php
namespace class\services;

class NotificationService {
    private $db;
    private $oneSignalConfig;
    private $smsConfig;

    public function __construct($db) {
        $this->db = $db;
        $this->oneSignalConfig = [
            'appId' => $_ENV['ONESIGNAL_APP_ID'] ?? '',
            'apiKey' => $_ENV['ONESIGNAL_API_KEY'] ?? ''
        ];
        $this->smsConfig = [
            'sender' => $_ENV['TERMII_SENDER'] ?? ($_ENV['SMS_SENDER'] ?? ''),
            'apiKey' => $_ENV['TERMII_API_KEY'] ?? ($_ENV['SMS_API_KEY'] ?? ''),
            'endpoint' => $_ENV['TERMII_ENDPOINT'] ?? ($_ENV['SMS_ENDPOINT'] ?? '')
        ];
    }

    private function getTransactionDetails($memberId, $periodId) {
        $query = "SELECT tlb_mastertransaction.memberid,tbpayrollperiods.Periodid,
                CONCAT(tbl_personalinfo.Lname, ' , ', tbl_personalinfo.Fname, ' ', IFNULL(tbl_personalinfo.Mname, '')) AS namess,
                tbl_personalinfo.MobilePhone,
								concat(LEFT(tbpayrollperiods.PhysicalMonth, 3),' -',tbpayrollperiods.PhysicalYear) as PayrollPeriod,
                SUM(tlb_mastertransaction.Contribution) as Contribution,
                SUM(tlb_mastertransaction.loanAmount) as loanAmount,
                SUM(tlb_mastertransaction.loanRepayment) as loanRepayment,
                (
                    SELECT 
                        (SUM(m2.loanAmount) + SUM(m2.interest))- SUM(m2.loanRepayment)
                    FROM tlb_mastertransaction m2
                    WHERE m2.memberid = tlb_mastertransaction.memberid
                    AND m2.periodid <= tlb_mastertransaction.periodid
                ) as loanBalance,
                (
                    SELECT 
                        SUM(m2.Contribution)
                    FROM tlb_mastertransaction m2
                    WHERE m2.memberid = tlb_mastertransaction.memberid
                    AND m2.periodid <= tlb_mastertransaction.periodid
                ) as welfareContribution,
                SUM(tlb_mastertransaction.Contribution + 
                    tlb_mastertransaction.loanRepayment ) as total
            FROM tlb_mastertransaction 
            INNER JOIN tbl_personalinfo ON tlb_mastertransaction.memberid = tbl_personalinfo.patientId
            LEFT JOIN tbpayrollperiods ON tlb_mastertransaction.periodid = tbpayrollperiods.Periodid              
            WHERE tbl_personalinfo.patientId = :memberId 
            AND tlb_mastertransaction.periodid = :periodId
            GROUP BY tbpayrollperiods.Periodid 
            ORDER BY tbpayrollperiods.Periodid DESC 
            LIMIT 1";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':memberId', $memberId, \PDO::PARAM_STR);
        $stmt->bindValue(':periodId', $periodId, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function logNotification($memberId, $message,$title = 'Transaction Alert') {
        $query = "INSERT INTO notifications 
                  (memberid, message, created_at, status, title) 
                  VALUES 
                  (:memberId, :message, NOW(), 'unread', :title)";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':memberId', $memberId, \PDO::PARAM_STR);
        $stmt->bindValue(':message', $message, \PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, \PDO::PARAM_STR);

        return $stmt->execute();
    }

    public function sendTransactionNotification($memberId, $periodId) {
        try {
            // Get transaction details
            $transactionData = $this->getTransactionDetails($memberId, $periodId);
            if (!$transactionData) {
                throw new \Exception("No transaction data found");
            }

            // Format message
            $message = $this->formatTransactionMessage($transactionData);

            // Send notifications
            $smsResult = $this->sendSMS($transactionData['MobilePhone'], $message);

            // if (!empty($transactionData['onesignal_id'])) {
            //     $this->sendPushNotification(
            //         $transactionData['onesignal_id'],
            //         "Transaction Update",
            //         $message
            //     );
            // }

            // Log notification
            $this->logNotification($memberId, $message);

            return true;
        } catch (\Exception $e) {
            error_log("Notification Error: " . $e->getMessage());
            return false;
        }
    }

    private function formatTransactionMessage($data) {
        return sprintf(
            "MHWUNWELL ACCT. BAL., MONTHLY CONTR: %s\n" .
            "WELFARE SAVINGS: %s\n" .
            "WELFARE BALANCE: %s\n" .
            "LOAN : %s\n" .
            "LOAN BALANCE: %s\n" .
            "AS AT: %s ENDING\n",
            number_format(floatval($data['total']), 2, '.', ','),
            number_format(floatval($data['Contribution']), 2, '.', ','),
            number_format(floatval($data['welfareContribution']), 2, '.', ','),
            number_format(floatval($data['loanAmount']), 2, '.', ','),
            number_format(floatval($data['loanBalance']), 2, '.', ','),
            $data['PayrollPeriod']
        );
    }

    public function sendSMS($phone, $message, $channel = 'generic') {
        if (empty($phone)) {
            throw new \Exception("Phone number is required");
        }

        $phone = $this->formatPhoneNumber($phone);

        $data = [
            "api_key" => $this->smsConfig['apiKey'],
            "to" => $phone,
            "from" => $this->smsConfig['sender'],
            "sms" => $message,
            "type" => "plain",
            "channel" => $channel 
        ];

        // HARDCODED BASE URL to ensure correctness regardless of .env weirdness
        // The user wants https://v3.api.termii.com/api/sms/send
        $endpoint = "https://v3.api.termii.com/api/sms/send";

        return $this->executeCurlRequest($endpoint, $data);
    }

    public function sendBulkSMS(array $phoneNumbers, $message, $channel = 'generic') {
        if (empty($phoneNumbers)) {
            throw new \Exception("Phone numbers are required");
        }

        // Re-index array to be safe JSON
        $formattedNumbers = array_values(array_map([$this, 'formatPhoneNumber'], $phoneNumbers));

        $data = [
            "api_key" => $this->smsConfig['apiKey'],
            "to" => $formattedNumbers,
            "from" => $this->smsConfig['sender'],
            "sms" => $message,
            "type" => "plain",
            "channel" => $channel
        ];

        // HARDCODED BULK URL to ensure correctness
        // The user wants https://v3.api.termii.com/api/sms/send/bulk
        $url = "https://v3.api.termii.com/api/sms/send/bulk";

        return $this->executeCurlRequest($url, $data);
    }

    private function executeCurlRequest($url, $data) {
        $ch = curl_init();
        
        // Debug Log Payload
        error_log("Termii Request Payload: " . json_encode($data));

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30, // Increased timeout for network latency
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ]
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \Exception("Curl error: $error");
        }

        curl_close($ch);

        $responseData = json_decode($response, true);

        // Termii can return various codes, strictly check for success indicators
        if ($httpCode !== 200 && $httpCode !== 201) {
             // Debug log
             error_log("Termii API Error: URL: $url - Code: $httpCode - Response: $response");
             $errorMessage = isset($responseData['message']) ? $responseData['message'] : $response;
             // Include URL in error message for better debugging
             throw new \Exception("SMS API Error ($httpCode): $errorMessage (URL: $url)");
        }

        // Return formatted numbers for debugging
        $responseData['debug_numbers'] = $data['to'] ?? []; 

        return $responseData;
    }

    private function sendPushNotification($playerId, $title, $message) {
        if (empty($playerId)) {
            return false;
        }

        $fields = [
            'app_id' => $this->oneSignalConfig['appId'],
            'include_player_ids' => [$playerId],
            'headings' => ['en' => $title],
            'contents' => ['en' => $message],
            'priority' => 10
        ];

        $ch = curl_init('https://onesignal.com/api/v1/notifications');
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Basic ' . $this->oneSignalConfig['apiKey']
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT => 30
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("OneSignal API Error: $response");
        }

        return json_decode($response, true);
    }

    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters except '+'
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // Remove text-based or empty trash
        if (empty($phone)) return '';

        // If starts with +, remove it
        if (substr($phone, 0, 1) === '+') {
            $phone = substr($phone, 1);
        }

        if (substr($phone, 0, 1) === '0') {
            return '234' . substr($phone, 1);
        }
        
        // If it starts with 234, perfect.
        // If it's a 10 digit number (e.g. 8012345678) without leading 0, assume it needs 234
        if (strlen($phone) === 10 && substr($phone, 0, 1) !== '0') {
            return '234' . $phone;
        }

        return $phone;
    }

    public function getSMSBalance() {
        // Use the configured API key (which falls back to TERMII_... or SMS_...)
        $apiKey = $this->smsConfig['apiKey'];
        
        // Log if key is missing (debugging)
        if (empty($apiKey)) {
            error_log("Termii Balance Error: API Key is empty.");
            return 0;
        }

        $url = "https://v3.api.termii.com/api/get-balance?api_key=" . urlencode(trim($apiKey));
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false, // Fix for local SSL issues
            CURLOPT_SSL_VERIFYHOST => 0,     // Fix for local SSL issues
            CURLOPT_TIMEOUT => 30 // Keep timeout to prevent hanging
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
             error_log("Termii Balance Curl Exec Failed: " . curl_error($ch));
             curl_close($ch);
             return 0;
        }

        curl_close($ch);
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Termii Balance JSON Decode Error: " . json_last_error_msg());
            return 0;
        }

        if ($httpCode !== 200) {
             error_log("Termii Balance Failed ($httpCode): " . $response);
             return 0;
        }
        
        // Safely check for balance or fallback
        // Termii response example: { "balance": 785.57, "currency": "NGN", ... }
        if (isset($data['balance'])) {
             return $data['balance'];
        } else {
             // If key doesn't exist, log structure
             error_log("Termii Balance: 'balance' key missing in response.");
             return 0;
        }
    }

    public function getSMSInbox() {
        // Use the configured API key
        $apiKey = $this->smsConfig['apiKey'];
        
        if (empty($apiKey)) {
            error_log("Termii Inbox Error: API Key is empty.");
            return [];
        }

        // Use api.ng.termii.com as verified for this account
        $url = "https://v3.api.termii.com/api/sms/inbox?api_key=" . urlencode(trim($apiKey));


        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_TIMEOUT => 60 // Longer timeout for history
        ]);

        $response = curl_exec($ch);
    
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
             error_log("Termii Inbox Curl Exec Failed: " . curl_error($ch));
             curl_close($ch);
             return [];
        }

        curl_close($ch);
        
        $data = json_decode($response, true);

        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("Termii Inbox JSON Decode Error: " . json_last_error_msg());
            return [];
        }
        
        if (is_array($data)) {
            return $data; 
        }

        return [];
    }

    public function calculateTransactionCost($message, $recipientCount) {
        // Cost per page = 5 Naira
        $costPerPage = 5.0;
        
        $len = strlen($message);
        $pages = 1;
        
        if ($len > 160) {
            // Standard multi-part calculation
            // 153 chars per segment for concatenated SMS
            $pages = ceil($len / 153);
        }
        
        return $pages * $recipientCount * $costPerPage;
    }
}