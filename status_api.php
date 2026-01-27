<?php
require_once('Connections/hms.php');
session_start();

header('Content-Type: application/json');

// Authentication Check
if (!isset($_SESSION['UserID'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    if ($action === 'search_member') {
        $query = $_GET['query'] ?? '';
        if (empty($query)) {
            echo json_encode([]);
            exit;
        }

        $sql = "SELECT patientid, Fname, Mname, Lname, MobilePhone 
                FROM tbl_personalinfo 
                WHERE patientid LIKE :q 
                   OR Fname LIKE :q 
                   OR Mname LIKE :q 
                   OR Lname LIKE :q 
                   OR MobilePhone LIKE :q 
                LIMIT 10";
        
        $stmt = $conn->prepare($sql);
        $searchTerm = "%$query%";
        $stmt->execute(['q' => $searchTerm]);
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format for frontend
        $formatted = array_map(function($row) {
            $fullName = trim($row['Lname'] . ' ' . $row['Fname'] . ' ' . $row['Mname']);
            return [
                'id' => $row['patientid'],
                'text' => "$fullName ({$row['patientid']})",
                'mobile' => $row['MobilePhone'],
                'name' => $fullName
            ];
        }, $results);

        echo json_encode(['status' => 'success', 'data' => $formatted]);

    } elseif ($action === 'fetch_periods') {
        $stmt = $conn->query("SELECT Periodid, PayrollPeriod FROM tbpayrollperiods ORDER BY Periodid DESC");
        $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $periods]);

    } elseif ($action === 'get_status') {
        $memberId = $_POST['member_id'] ?? '';
        $periodId = $_POST['period_id'] ?? '';

        if (empty($memberId) || empty($periodId)) {
            throw new Exception("Member ID and Period are required.");
        }

        // Logic from legacy getStatus.php
        $sql = "SELECT 
                    tbl_personalinfo.patientid, 
                    CONCAT(tbl_personalinfo.Lname,' , ', tbl_personalinfo.Fname,' ', IFNULL(tbl_personalinfo.Mname,'')) as namess,
                    (SUM(tlb_mastertransaction.Contribution) + SUM(tlb_mastertransaction.withdrawal)) as Contribution,
                    (SUM(tlb_mastertransaction.loanAmount) + SUM(tlb_mastertransaction.interest)) as Loan, 
                    ((SUM(tlb_mastertransaction.loanAmount) + SUM(tlb_mastertransaction.interest)) - (SUM(tlb_mastertransaction.loanRepayment) + IFNULL(SUM(tlb_mastertransaction.repayment_bank),0))) as Loanbalance, 
                    SUM(tlb_mastertransaction.withdrawal) as withdrawal 
                FROM tlb_mastertransaction 
                INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tlb_mastertransaction.memberid 
                WHERE patientid = :memberId AND tlb_mastertransaction.periodid <= :periodId 
                GROUP BY patientid";

        // Safer query with IFNULL checks
        $sql = "SELECT 
                    tbl_personalinfo.patientid, 
                    CONCAT(tbl_personalinfo.Lname,' , ', tbl_personalinfo.Fname,' ', IFNULL(tbl_personalinfo.Mname,'')) as namess,
                    (IFNULL(SUM(tlb_mastertransaction.Contribution),0) + IFNULL(SUM(tlb_mastertransaction.withdrawal),0)) as Contribution,
                    (IFNULL(SUM(tlb_mastertransaction.loanAmount),0) + IFNULL(SUM(tlb_mastertransaction.interest),0)) as Loan, 
                    ((IFNULL(SUM(tlb_mastertransaction.loanAmount),0) + IFNULL(SUM(tlb_mastertransaction.interest),0)) - (IFNULL(SUM(tlb_mastertransaction.loanRepayment),0) + IFNULL(SUM(tlb_mastertransaction.repayment_bank),0))) as Loanbalance, 
                    IFNULL(SUM(tlb_mastertransaction.withdrawal),0) as withdrawal 
                FROM tlb_mastertransaction 
                INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tlb_mastertransaction.memberid 
                WHERE patientid = :memberId AND tlb_mastertransaction.periodid <= :periodId 
                GROUP BY patientid";

        $stmt = $conn->prepare($sql);
        $stmt->execute(['memberId' => $memberId, 'periodId' => $periodId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($data) {
            echo json_encode(['status' => 'success', 'data' => $data]);
        } else {
            // No transactions found is confusing if we error, so return empty structure or null
            // Check if member exists at least
            echo json_encode(['status' => 'success', 'data' => null, 'message' => 'No records found for this period range.']);
        }


    } elseif ($action === 'get_global_stats') {
        // 1. Total Savings
        $totalSavings = 0;
        try {
            // Using tbl_contributions as per dashboard.php
            $stmt = $conn->query("SELECT SUM(contribution) FROM tbl_contributions");
            $totalSavings = $stmt->fetchColumn() ?: 0;
        } catch (PDOException $e) {}

        // 2. Gender Stats
        $genderStats = ['Male' => 0, 'Female' => 0];
        try {
            $stmt = $conn->query("SELECT gender, COUNT(*) as count FROM tbl_personalinfo WHERE Status = 'Active' GROUP BY gender");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $g = ucfirst(strtolower(trim($row['gender'])));
                if (isset($genderStats[$g])) {
                    $genderStats[$g] = $row['count'];
                }
            }
        } catch (PDOException $e) {}

        // 3. Active Members (for context if needed)
        $activeMembers = 0;
        try {
            $stmt = $conn->query("SELECT COUNT(*) FROM tbl_personalinfo WHERE Status = 'Active'");
            $activeMembers = $stmt->fetchColumn();
        } catch (PDOException $e) {}
        
        // 4. Active Loans (for context)
        $activeLoans = 0;
         try {
            $stmt = $conn->query("SELECT COUNT(*) FROM sploan WHERE repayment < loanAmount");
            $activeLoans = $stmt->fetchColumn();
        } catch (PDOException $e) {}


        echo json_encode([
            'status' => 'success', 
            'data' => [
                'total_savings' => $totalSavings,
                'gender' => $genderStats,
                'total_members' => $activeMembers,
                'active_loans' => $activeLoans
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
