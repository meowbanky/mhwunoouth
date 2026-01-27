<?php
header('Content-Type: application/json');
require_once('Connections/hms.php');

// Define response structure
$response = ['status' => 'error', 'message' => '', 'data' => []];

try {
    if (!isset($_POST['action'])) {
        throw new Exception("Invalid request");
    }

    $action = $_POST['action'];

    if ($action === 'fetch_comparison') {
        
        // 1. Main Query
        // Logic copied from loanContri_Compare.php
        $query = "SELECT 
                    tbl_personalinfo.patientid, 
                    concat(tbl_personalinfo.Lname,' ',tbl_personalinfo.Fname,' ',ifnull(tbl_personalinfo.Mname,'')) as namee,
                    ((sum(tlb_mastertransaction.loanAmount)+sum(tlb_mastertransaction.interest))-(sum(tlb_mastertransaction.loanRepayment) + ifnull(sum(tlb_mastertransaction.repayment_bank),0))) AS loanBalance,
                    tbl_contributions.loan as standard_repayment
                  FROM tlb_mastertransaction 
                  LEFT JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tlb_mastertransaction.memberid 
                  LEFT JOIN tbl_contributions ON tbl_contributions.membersid = tbl_personalinfo.patientid
                  WHERE tbl_personalinfo.`Status` = 'Active' 
                  GROUP BY memberid 
                  HAVING loanBalance > 0 OR standard_repayment > 0 -- Optimization: valid records only
                  ORDER BY loanBalance DESC";

        $stmt = $conn->query($query);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dataList = [];
        $totalLoanBalance = 0;
        $totalRepayment = 0; // Sum of standard repayments
        $pendingReductions = 0;

        foreach ($results as $row) {
            $bal = floatval($row['loanBalance']);
            $rep = floatval($row['standard_repayment']);

            // Determine Status
            $status = 'Normal';
            if ($bal < $rep && $bal > 0) { 
                $status = 'Reduce Repayment';
                $pendingReductions++;
            } elseif ($bal <= 0) {
                $status = 'Clear'; // Should technically stop deducting
            }

            // Accumulate Totals
            $totalLoanBalance += $bal;
            $totalRepayment += $rep;

            $dataList[] = [
                'staff_no' => $row['patientid'],
                'name' => trim($row['namee']),
                'loan_balance' => $bal,
                'loan_repayment' => $rep,
                'status' => $status
            ];
        }

        $response['status'] = 'success';
        $response['data'] = [
            'list' => $dataList,
            'summary' => [
                'total_loan_balance' => $totalLoanBalance,
                'total_repayment' => $totalRepayment,
                'pending_reductions' => $pendingReductions
            ]
        ];

    } else {
        throw new Exception("Unknown action");
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
