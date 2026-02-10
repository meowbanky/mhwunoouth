<?php
session_start();
require_once('Connections/hms.php');

// Prioritize GET/POST period, then Session, then -1
if (isset($_REQUEST['period']) && !empty($_REQUEST['period'])) {
    $col_Batch = $_REQUEST['period'];
} else {
    $col_Batch = isset($_SESSION['period']) ? $_SESSION['period'] : "-1";
}

try {
    $query_Batch = "SELECT CONCAT(tbl_personalinfo.Lname,' , ',tbl_personalinfo.Fname,' ',(ifnull(tbl_personalinfo.Mname,' '))) AS `name`, 
                        (tbl_loan.loanamount + tbl_loan.interest) as loanamount, 
                        tbl_loan.loanid, tbl_loan.periodid, tbl_loan.memberid, 
                        tbl_contributions.loan as loanrepayment 
                        FROM tbl_personalinfo 
                        INNER JOIN tbl_loan ON tbl_loan.memberid = tbl_personalinfo.patientid 
                        LEFT JOIN tbl_contributions ON tbl_contributions.membersid = tbl_personalinfo.patientid 
                        WHERE tbl_loan.periodid = :periodid ORDER BY tbl_loan.loanid DESC";
    $stmtBatch = $conn->prepare($query_Batch);
    $stmtBatch->execute([':periodid' => $col_Batch]);
    $batchLoans = $stmtBatch->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Totals
    $stmtSum = $conn->prepare("SELECT (sum(loanamount)+sum(interest)) as amount FROM tbl_loan WHERE periodId = :periodid");
    $stmtSum->execute([':periodid' => $col_Batch]);
    $row_batchsum = $stmtSum->fetch(PDO::FETCH_ASSOC);
    $totalLoanAmount = $row_batchsum['amount'] ?? 0;

} catch (PDOException $e) {
    die("Error fetching loans: " . $e->getMessage());
}

// Return JSON with HTML components (Table Body and Footer)
$htmlBody = '';
if (count($batchLoans) > 0) {
    foreach ($batchLoans as $row) {
        $htmlBody .= '<tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">';
        $htmlBody .= '<td class="px-6 py-4">';
        $htmlBody .= '<div class="flex flex-col">';
        $htmlBody .= '<span class="text-sm font-semibold text-slate-900 dark:text-white">' . htmlspecialchars($row['name']) . '</span>';
        $htmlBody .= '<span class="text-[11px] text-slate-500">ID: ' . htmlspecialchars($row['memberid']) . '</span>';
        $htmlBody .= '</div>';
        $htmlBody .= '</td>';
        $htmlBody .= '<td class="px-6 py-4 text-sm font-medium">₦' . number_format($row['loanamount'], 2) . '</td>';
        $htmlBody .= '<td class="px-6 py-4 text-sm font-medium text-slate-500">#' . htmlspecialchars($row['loanid']) . '</td>';
        $htmlBody .= '<td class="px-6 py-4 text-right">';
        $htmlBody .= '<button class="text-red-500 hover:text-red-700" onclick="deleteLoan(' . $row['loanid'] . ')"><span class="material-icons-round text-sm">delete</span></button>';
        $htmlBody .= '</td>';
        $htmlBody .= '</tr>';
    }
} else {
    $htmlBody .= '<tr><td colspan="4" class="px-6 py-4 text-center text-slate-500 italic">No loans found for this period</td></tr>';
}

$htmlFooter = '<tr class="bg-slate-50/50 dark:bg-slate-800/10">';
$htmlFooter .= '<td class="px-6 py-4 font-bold text-sm">Totals</td>';
$htmlFooter .= '<td class="px-6 py-4 font-bold text-sm text-primary">₦' . number_format($totalLoanAmount, 2) . '</td>';
$htmlFooter .= '<td colspan="2"></td>';
$htmlFooter .= '</tr>';

echo json_encode(['body' => $htmlBody, 'footer' => $htmlFooter]);
?>
