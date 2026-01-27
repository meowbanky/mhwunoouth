<?php
require_once('Connections/hms.php');
session_start();

header('Content-Type: application/json');

// 1. Authentication Check
if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'create') {
        $rawDate = $_POST['period_date'] ?? ''; 
        
        if (empty($rawDate)) {
            throw new Exception("Please select a date.");
        }

        // Expecting Month Picker (YYYY-MM) or Date Picker (YYYY-MM-DD)
        // Adjust format based on input length or try both
        $format = (strlen($rawDate) === 7) ? 'Y-m' : 'Y-m-d';
        $dateObj = DateTime::createFromFormat($format, $rawDate);
        
        if (!$dateObj) {
            throw new Exception("Invalid Date Format.");
        }

        $payrollPeriod = $dateObj->format('F-Y');
        $physicalYear = $dateObj->format('Y');
        $physicalMonth = $dateObj->format('F');
        $user = $_SESSION['FirstName'] ?? 'System';

        // Check duplicate
        $stmtCheck = $conn->prepare("SELECT Periodid FROM tbpayrollperiods WHERE PayrollPeriod = :period");
        $stmtCheck->execute(['period' => $payrollPeriod]);

        if ($stmtCheck->rowCount() > 0) {
            throw new Exception("Period '{$payrollPeriod}' already exists.");
        }

        // Insert
        $sqlInsert = "INSERT INTO tbpayrollperiods (PayrollPeriod, PhysicalYear, PhysicalMonth, InsertedBy, DateInserted) 
                      VALUES (:period, :year, :month, :by, NOW())";
        $stmtInsert = $conn->prepare($sqlInsert);
        $stmtInsert->execute([
            'period' => $payrollPeriod,
            'year' => $physicalYear,
            'month' => $physicalMonth,
            'by' => $user
        ]);

        echo json_encode(['status' => 'success', 'message' => "Period '{$payrollPeriod}' created successfully."]);

    } elseif ($action === 'delete') {
        $periodId = $_POST['id'] ?? '';

        if (empty($periodId)) {
            throw new Exception("Period ID is required.");
        }

        // Check if transactions exist
        $stmtCheckParams = ['id' => $periodId];
        $stmtCheckTrans = $conn->prepare("SELECT COUNT(*) FROM tlb_mastertransaction WHERE periodid = :id");
        $stmtCheckTrans->execute($stmtCheckParams);
        $count = $stmtCheckTrans->fetchColumn();

        if ($count > 0) {
            throw new Exception("Cannot delete period. Transactions have already been recorded for this period.");
        }

        // Proceed to delete
        $stmtDelete = $conn->prepare("DELETE FROM tbpayrollperiods WHERE Periodid = :id");
        $stmtDelete->execute($stmtCheckParams);

        echo json_encode(['status' => 'success', 'message' => "Period deleted successfully."]);

    } elseif ($action === 'fetch') {
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 12; // Default 12 for grid (3x4 or 4x3)
        $offset = ($page - 1) * $limit;

        // Count Total
        $stmtCount = $conn->query("SELECT COUNT(*) FROM tbpayrollperiods");
        $totalItems = $stmtCount->fetchColumn();
        $totalPages = ceil($totalItems / $limit);

        // Fetch Data
        $stmtFetch = $conn->prepare("SELECT * FROM tbpayrollperiods ORDER BY Periodid DESC LIMIT :limit OFFSET :offset");
        $stmtFetch->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmtFetch->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmtFetch->execute();
        $data = $stmtFetch->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'meta' => [
                'total' => $totalItems,
                'page' => $page,
                'pages' => $totalPages,
                'limit' => $limit
            ]
        ]);

    } else {
        throw new Exception("Invalid Action");
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
