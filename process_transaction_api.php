<?php
header('Content-Type: application/json');
require_once('Connections/hms.php');
require_once('NotificationService.php');
use class\services\NotificationService;

$response = ['status' => 'error', 'message' => ''];

$notificationService = null;
try {
    $notificationService = new NotificationService($conn);
} catch (Exception $e) {
    // Log error but continue processing - notifications are secondary
    error_log("NotificationService Init Failed: " . $e->getMessage());
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        $periodId = $_POST['period_id'] ?? '';

        if (empty($periodId)) {
            throw new Exception("Period ID is required.");
        }

        // ---------------------------------------------------------------------
        // Action: Fetch Members to Process
        // ---------------------------------------------------------------------
        if ($action === 'fetch_members_to_process') {
            // Get list of active members to process
            // Logic ported from process.php: SELECT ... FROM tbl_contributions INNER JOIN tbl_personalinfo ... WHERE `Status` = 'Active' order by tbl_personalinfo.ordered_id asc
            $query = "SELECT tbl_personalinfo.patientid 
                      FROM tbl_contributions 
                      INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid 
                      WHERE `Status` = 'Active' 
                      ORDER BY tbl_personalinfo.ordered_id ASC";
            
            $stmt = $conn->query($query);
            $members = $stmt->fetchAll(PDO::FETCH_COLUMN); // Returns array of IDs

            $response['status'] = 'success';
            $response['data'] = $members;
            $response['count'] = count($members);
        }

        // ---------------------------------------------------------------------
        // Action: Process Single Member
        // ---------------------------------------------------------------------
        elseif ($action === 'process_member') {
            $memberId = $_POST['member_id'] ?? '';
            if (empty($memberId)) throw new Exception("Member ID is required.");

            // 1. Fetch Standing Order (Contribution & Loan Deduction Amount)
            // Using logic from process.php: $query_deductions
            $deductionSql = "SELECT contribution, loan FROM tbl_contributions WHERE membersid = ?";
            $deductionStmt = $conn->prepare($deductionSql);
            $deductionStmt->execute([$memberId]);
            $deduction = $deductionStmt->fetch(PDO::FETCH_ASSOC);

            if (!$deduction) {
                throw new Exception("No deduction record found for member $memberId");
            }
            
            $monthlyContribution = floatval($deduction['contribution']);
            $monthlyLoanDeduction = floatval($deduction['loan']); // 'loon' / 'contLoan' in legacy

            // 2. Check if already processed for this period
            $checkSql = "SELECT COUNT(*) FROM tlb_mastertransaction 
                         WHERE memberid = ? AND periodid = ? AND completed = 1 AND (Contribution > 0 OR refund > 0)";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$memberId, $periodId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                // Already processed, skip silently or return specific status
                $response['status'] = 'success';
                $response['message'] = 'Already processed';
                $response['code'] = 'SKIPPED';
                echo json_encode($response);
                exit;
            }

            // 3. Calculate Current Balances
            // Logic ported from process.php: $balancesSQL
            // Note: PHP 8 string quoting for SQL

            $balSql = "SELECT 
                        IFNULL((SUM(loanAmount) + SUM(interest)), 0) 
                        - (SUM(loanRepayment) + SUM(repayment_bank)) AS Loanbalance
                       FROM tlb_mastertransaction 
                       WHERE memberid = ?";
            
            $balStmt = $conn->prepare($balSql);
            $balStmt->execute([$memberId]);
            $balanceRow = $balStmt->fetch(PDO::FETCH_ASSOC);
            
            $currentLoanBalance = floatval($balanceRow['Loanbalance']);

            // 4. Processing Logic (The "If-Else" Maze from process.php)
            
            // Case A: No Loan Balance & No Monthly Loan Deduction -> Simple Contribution
            if ($currentLoanBalance <= 0 && $monthlyLoanDeduction <= 0) {
                insertTransaction($conn, $periodId, $memberId, $monthlyContribution, 0, 0);
            }
            
            // Case B: No Loan Balance BUT Monthly Loan Deduction Trying to run -> Refund Overshoot
            // (Legacy: ($row_balances['Loanbalance'] <= 0) && ($row_balances['contLoan'] > 0 ))
            elseif ($currentLoanBalance <= 0 && $monthlyLoanDeduction > 0) {
                // 1. Insert Transaction (Just Contribution)
                insertTransaction($conn, $periodId, $memberId, $monthlyContribution, 0, 0);
                
                // 2. Refund the Loan Deduction Amount (Since they don't owe anything)
                // Note: The logic in process.php actually refunded ($row_balances['contLoan'] - $row_balances['Loanbalance'])
                // But $row_balances['Loanbalance'] is <= 0 (e.g. -500), so -(-500) = +500.
                // Wait, if Loanbalance is negative, it means they overpaid? 
                // Legacy logic: $refund = ($row_balances['contLoan'] - $row_balances['Loanbalance']);
                // Example: Deduction=5000, Balance=-1000. Refund = 5000 - (-1000) = 6000.
                // This implies refreshing the negative balance AND the new deduction? 
                // Let's stick strictly to legacy logic formula.
                
                $refundAmount = $monthlyLoanDeduction - $currentLoanBalance; 
                
                insertRefund($conn, $periodId, $memberId, $monthlyLoanDeduction); // Original code inserted 'contLoan' into tbl_refund...
                insertMasterRefund($conn, $periodId, $memberId, $refundAmount);   // ...but 'refund' calculated value into tlb_mastertransaction
            }
            
            // Case C: Has Loan Balance
            elseif ($currentLoanBalance > 0) {
                
                if ($monthlyLoanDeduction > 0) {
                    
                    // C1: Balance > Deduction (Normal Repayment)
                    if ($currentLoanBalance > $monthlyLoanDeduction) {
                        insertTransaction($conn, $periodId, $memberId, $monthlyContribution, $monthlyLoanDeduction, 0);
                    }
                    
                    // C2: Balance < Deduction (Last Repayment + Refund Excess)
                    elseif ($currentLoanBalance < $monthlyLoanDeduction) {
                         // Pay off exact remaining balance
                        insertTransaction($conn, $periodId, $memberId, $monthlyContribution, $currentLoanBalance, 0);
                        
                        // Refund the difference
                        $refundDiff = $monthlyLoanDeduction - $currentLoanBalance;
                        
                        insertRefund($conn, $periodId, $memberId, $refundDiff);
                        insertMasterRefund($conn, $periodId, $memberId, $refundDiff);
                    }
                    
                    // C3: Balance == Deduction (Exact Finish)
                    else {
                        insertTransaction($conn, $periodId, $memberId, $monthlyContribution, $monthlyLoanDeduction, 0);
                    }
                } 
                else {
                    // Loan exists but deduction is 0?
                    // Legacy: elseif($row_balances['contLoan'] ==  0 )
                    insertTransaction($conn, $periodId, $memberId, $monthlyContribution, 0, 0);
                }
            }
            
                if ($notificationService) {
                    // Check SMS Balance before sending
                    // Standard Transaction Alert is typically 1 page.
                    // Cost = 5.0
                    $estimatedCost = 5.0; 
                    
                    try {
                        $currentBalance = $notificationService->getSMSBalance();
                        if ($currentBalance >= $estimatedCost) {
                            $notificationService->sendTransactionNotification($memberId, $periodId);
                        } else {
                            error_log("Transaction Notification Skipped: Insufficient SMS Balance. Required: $estimatedCost, Available: $currentBalance");
                        }
                    } catch (Exception $e) {
                         error_log("Transaction Notification Balance Check Failed: " . $e->getMessage());
                         // Attempt to send anyway? No, safer to fail open if balance check fails? 
                         // Or fail closed. Let's fail safe and not send if we can't verify balance, 
                         // OR just log the error and try to send (Termii will fail if no balance).
                         // Termii API will return error if no balance, so we can actually just let it try
                         // but explicitly checking saves an API call.
                         // Let's assume on error we skip to be safe.
                    }
                }
                
                $response['status'] = 'success';
                $response['message'] = 'Processed successfully';
            }

        } else {
        throw new Exception("Invalid Request Method");
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);


// --- Helper Functions ---

function insertTransaction($conn, $periodId, $memberId, $contribution, $loanRepayment, $refund) {
    $sql = "INSERT INTO tlb_mastertransaction (periodid, memberid, Contribution, loanRepayment, refund, completed) 
            VALUES (?, ?, ?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$periodId, $memberId, $contribution, $loanRepayment, $refund]);
}

function insertRefund($conn, $periodId, $memberId, $amount) {
    $sql = "INSERT INTO tbl_refund (periodid, membersid, amount) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$periodId, $memberId, $amount]);
}

function insertMasterRefund($conn, $periodId, $memberId, $amount) {
    // Legacy Insert for Refunds in Master Transaction
    // It seems process.php does a separate INSERT for refund rows in mastertransaction
    $sql = "INSERT INTO tlb_mastertransaction (periodid, memberid, refund, completed) VALUES (?, ?, ?, 1)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$periodId, $memberId, $amount]);
}
?>
