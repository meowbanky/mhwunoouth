<?php
header('Content-Type: application/json');
require_once('Connections/hms.php');

$response = ['status' => 'error', 'message' => '', 'data' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // 1. Fetch Periods
        if ($action === 'fetch_periods') {
            $query = "SELECT Periodid, PayrollPeriod FROM tbpayrollperiods ORDER BY Periodid DESC";
            $stmt = $conn->query($query);
            $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            $response['data'] = $periods;
        }
        
        // 1b. Fetch All Active Members (For selection dropdown)
        elseif ($action === 'fetch_all_members') {
            $query = "SELECT patientid, CONCAT(Lname, ' ', Fname, ' ', IFNULL(Mname, '')) as fullname 
                      FROM tbl_personalinfo 
                      WHERE Status = 'Active' 
                      ORDER BY patientid ASC";
            $stmt = $conn->query($query);
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $response['status'] = 'success';
            $response['data'] = $members;
        }

        // 2. Fetch Deductions filtered by period
        elseif ($action === 'fetch_deductions') {
            $page     = isset($_POST['page'])      ? (int)$_POST['page']  : 1;
            $limit    = isset($_POST['limit'])     ? (int)$_POST['limit'] : 20;
            $periodId = isset($_POST['period_id']) ? (int)$_POST['period_id'] : 0;
            $offset   = ($page - 1) * $limit;

            $periodFilter = $periodId > 0 ? "AND tbl_contributions.period_id = $periodId" : '';

            // Count
            $countStmt = $conn->query(
                "SELECT COUNT(*) FROM tbl_contributions
                 INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid
                 WHERE tbl_personalinfo.status = 'Active' $periodFilter"
            );
            $totalRecords = $countStmt->fetchColumn();
            $totalPages   = max(1, ceil($totalRecords / $limit));

            // Grand total for period. "Grand total" stays what the union will
            // actually process — bank loan money is reported separately because
            // it never reaches the loan engine.
            $sumStmt = $conn->query(
                "SELECT SUM(loan) as total_loan, SUM(contribution) as total_contri,
                        SUM(bank_loan) as total_bank, SUM(gross_deduction) as total_gross
                 FROM tbl_contributions
                 INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid
                 WHERE tbl_personalinfo.status = 'Active' $periodFilter"
            );
            $sums      = $sumStmt->fetch(PDO::FETCH_ASSOC);
            $grandTotal = ($sums['total_loan'] ?? 0) + ($sums['total_contri'] ?? 0);

            // Data
            $stmt = $conn->query(
                "SELECT tbl_personalinfo.patientid,
                        CONCAT(tbl_personalinfo.Lname, ' , ', tbl_personalinfo.Fname, ' ', IFNULL(tbl_personalinfo.Mname, '')) AS fullname,
                        tbl_contributions.contribution,
                        tbl_contributions.loan,
                        tbl_contributions.bank_loan,
                        tbl_contributions.gross_deduction,
                        (tbl_contributions.contribution + tbl_contributions.loan) as total
                 FROM tbl_contributions
                 INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid
                 WHERE tbl_personalinfo.status = 'Active' $periodFilter
                 ORDER BY tbl_personalinfo.patientid ASC
                 LIMIT $limit OFFSET $offset"
            );
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($rows as &$row) {
                $row['contribution_fmt'] = number_format($row['contribution'],    2);
                $row['loan_fmt']         = number_format($row['loan'],            2);
                $row['bank_loan_fmt']    = number_format($row['bank_loan'],       2);
                $row['gross_fmt']        = number_format($row['gross_deduction'], 2);
                $row['total_fmt']        = number_format($row['total'],           2);
                $row['has_bank_loan']    = ((float)$row['bank_loan']) > 0;
            }
            unset($row);

            $response['status'] = 'success';
            $response['data']   = [
                'list'       => $rows,
                'pagination' => [
                    'current_page'  => $page,
                    'total_pages'   => $totalPages,
                    'total_records' => $totalRecords,
                    'limit'         => $limit
                ],
                'grand_total'     => number_format($grandTotal, 2),
                'bank_loan_total' => number_format($sums['total_bank']  ?? 0, 2),
                'gross_total'     => number_format($sums['total_gross'] ?? 0, 2),
                'has_bank_loans'  => ((float)($sums['total_bank'] ?? 0)) > 0,
            ];
        } else {
            throw new Exception("Invalid Action");
        }
    } else {
        throw new Exception("Invalid Request Method");
    }
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
