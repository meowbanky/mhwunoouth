<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once('Connections/hms.php');

$action = isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : '');

try {
    if ($action === 'fetch_member') {
        $id = isset($_POST['id']) ? $_POST['id'] : (isset($_GET['id']) ? $_GET['id'] : null);
        
        if (!$id) {
            throw new Exception("Member ID required");
        }

        // 1. Fetch Personal Info
        $stmt = $conn->prepare("SELECT patientid, Fname, Lname, Mname FROM tbl_personalinfo WHERE patientid = :id");
        $stmt->execute([':id' => $id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$member) {
            throw new Exception("Member not found");
        }

        // 2. Fetch Contributions
        $stmt_c = $conn->prepare("SELECT contribution, loan FROM tbl_contributions WHERE membersid = :id");
        $stmt_c->execute([':id' => $id]);
        $contrib = $stmt_c->fetch(PDO::FETCH_ASSOC);

        if (!$contrib) {
            $contrib = ['contribution' => 0, 'loan' => 0];
        }

        // 3. Fetch Loan Balance
        $query_bal = "SELECT ((SUM(loanAmount) + SUM(interest)) - SUM(loanRepayment)) as loanbalance 
                      FROM tlb_mastertransaction 
                      WHERE memberid = :id";
        $stmt_bal = $conn->prepare($query_bal);
        $stmt_bal->execute([':id' => $id]);
        $bal_row = $stmt_bal->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'member' => $member,
                'contribution' => $contrib,
                'loan_balance' => $bal_row['loanbalance'] ?? 0
            ]
        ]);

    } elseif ($action === 'fetch_directory') {
        $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $search = isset($_POST['search']) ? trim($_POST['search']) : '';
        
        // Base query
        $sql = "SELECT patientid, Fname, Lname, Mname, passport FROM tbl_personalinfo WHERE Status = 'Active'";
        $params = [];
        
        // Search filter
        if (!empty($search)) {
            $sql .= " AND (Fname LIKE :s OR Lname LIKE :s OR Mname LIKE :s OR patientid LIKE :s)";
            $params[':s'] = "%$search%";
        }
        
        // Count total for pagination
        $countSql = str_replace("SELECT patientid, Fname, Lname, Mname, passport", "SELECT COUNT(*) as total", $sql);
        $stmtCount = $conn->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages = ceil($totalRecords / $limit);
        
        // Fetch paginated data
        $sql .= " ORDER BY patientid ASC LIMIT $offset, $limit";
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'data' => [
                'members' => $members,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords
                ]
            ]
        ]);

    } elseif ($action === 'update_record') {
        $id = $_POST['member_id'];
        $contrib = str_replace(',', '', $_POST['contribution_amount']);
        $loan = str_replace(',', '', $_POST['loan_repayment']);

        if (!is_numeric($contrib)) $contrib = 0;
        if (!is_numeric($loan)) $loan = 0;

        // Check if record exists first? Or simple UPDATE (which might fail if row doesn't exist). 
        // Original code used UPDATE directly, implying rows exist or it fails silently.
        // Better to try UPDATE, if 0 rows affected, maybe INSERT? 
        // For now, sticking to original logic: plain UPDATE.
        
        $stmt = $conn->prepare("UPDATE tbl_contributions SET contribution = :contrib, loan = :loan WHERE membersid = :id");
        $success = $stmt->execute([
            ':contrib' => $contrib, 
            ':loan' => $loan, 
            ':id' => $id
        ]);
        
        // Use rowCount to check? No, rowCount is 0 if values are same. Just checking execute success is fine.

        if ($success) {
            echo json_encode(['status' => 'success', 'message' => 'Record updated successfully']);
        } else {
             throw new Exception("Update failed");
        }

    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
