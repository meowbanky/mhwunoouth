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

        // 2. Fetch Deductions (Active/Current Status)
        elseif ($action === 'fetch_deductions') {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 20;
            $offset = ($page - 1) * $limit;

            // Count Query
            $countQuery = "SELECT COUNT(*) FROM tbl_contributions 
                           INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid";
            $countStmt = $conn->query($countQuery);
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // Grand Total Query (Sum of EVERYTHING in the table, effectively "Current Standing")
            $sumQuery = "SELECT SUM(loan) as total_loan, SUM(contribution) as total_contri FROM tbl_contributions";
            $sumStmt = $conn->query($sumQuery);
            $sums = $sumStmt->fetch(PDO::FETCH_ASSOC);
            $grandTotal = ($sums['total_loan'] ?? 0) + ($sums['total_contri'] ?? 0);

            // Data Query
            $query = "SELECT 
                        tbl_personalinfo.patientid, 
                        CONCAT(tbl_personalinfo.Lname, ' , ', tbl_personalinfo.Fname, ' ', IFNULL(tbl_personalinfo.Mname, '')) AS fullname, 
                        tbl_contributions.contribution, 
                        tbl_contributions.loan, 
                        (tbl_contributions.contribution + tbl_contributions.loan) as total 
                      FROM tbl_contributions 
                      INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tbl_contributions.membersid 
                      ORDER BY tbl_personalinfo.Lname ASC 
                      LIMIT $limit OFFSET $offset";
            
            $stmt = $conn->query($query);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Format Numbers
            foreach ($rows as &$row) {
                $row['contribution_fmt'] = number_format($row['contribution'], 2);
                $row['loan_fmt'] = number_format($row['loan'], 2);
                $row['total_fmt'] = number_format($row['total'], 2);
            }

            $response['status'] = 'success';
            $response['data'] = [
                'list' => $rows,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit
                ],
                'grand_total' => number_format($grandTotal, 2)
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
