<?php
session_start();
require_once('Connections/hms.php');

$periodId = isset($_REQUEST['period']) && !empty($_REQUEST['period'])
    ? $_REQUEST['period']
    : (isset($_SESSION['period']) ? $_SESSION['period'] : "-1");

try {
    $stmt = $conn->prepare(
        "SELECT t.teller_id, t.memberid, t.teller_upload, t.repayment_bank,
                CONCAT(p.Lname, ' , ', p.Fname, ' ', IFNULL(p.Mname,'')) AS name
         FROM tbl_teller t
         INNER JOIN tbl_personalinfo p ON p.patientid = t.memberid
         WHERE t.periodid = :periodid
         ORDER BY t.teller_id DESC"
    );
    $stmt->execute([':periodid' => $periodId]);
    $deposits = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $stmtTotal = $conn->prepare("SELECT SUM(repayment_bank) as total FROM tbl_teller WHERE periodid = :periodid");
    $stmtTotal->execute([':periodid' => $periodId]);
    $total = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

} catch (PDOException $e) {
    $deposits = [];
    $total = 0;
}

$htmlBody = '';
if (count($deposits) > 0) {
    foreach ($deposits as $row) {
        $slip = !empty($row['teller_upload'])
            ? '<a href="uploads/tellers/' . htmlspecialchars($row['teller_upload']) . '" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1"><span class="material-icons-round text-sm">attach_file</span> View</a>'
            : '<span class="text-slate-400 italic text-xs">None</span>';

        $nameEsc   = htmlspecialchars($row['name'], ENT_QUOTES);
        $amount    = number_format($row['repayment_bank'], 2);
        $slipFile  = htmlspecialchars($row['teller_upload'] ?? '', ENT_QUOTES);

        $htmlBody .= '<tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">';
        $htmlBody .= '<td class="px-6 py-4"><div class="flex flex-col"><span class="text-sm font-semibold text-slate-900 dark:text-white">' . $nameEsc . '</span><span class="text-[11px] text-slate-500">ID: ' . htmlspecialchars($row['memberid']) . '</span></div></td>';
        $htmlBody .= '<td class="px-6 py-4 text-sm font-medium">₦' . $amount . '</td>';
        $htmlBody .= '<td class="px-6 py-4 text-sm">' . $slip . '</td>';
        $htmlBody .= '<td class="px-6 py-4 text-right flex items-center justify-end gap-3">';
        $htmlBody .= '<button class="text-blue-500 hover:text-blue-700" onclick="openEditModal(' . $row['teller_id'] . ', \'' . $nameEsc . '\', \'' . $row['repayment_bank'] . '\', \'' . $slipFile . '\')"><span class="material-icons-round text-sm">edit</span></button>';
        $htmlBody .= '<button class="text-red-500 hover:text-red-700" onclick="deleteDeposit(' . $row['teller_id'] . ')"><span class="material-icons-round text-sm">delete</span></button>';
        $htmlBody .= '</td>';
        $htmlBody .= '</tr>';
    }
} else {
    $htmlBody = '<tr><td colspan="4" class="px-6 py-4 text-center text-slate-500 italic">No deposits found for this period</td></tr>';
}

$htmlFooter  = '<tr class="bg-slate-50/50 dark:bg-slate-800/10">';
$htmlFooter .= '<td class="px-6 py-4 font-bold text-sm">Total</td>';
$htmlFooter .= '<td class="px-6 py-4 font-bold text-sm text-primary">₦' . number_format($total, 2) . '</td>';
$htmlFooter .= '<td colspan="2"></td>';
$htmlFooter .= '</tr>';

echo json_encode(['body' => $htmlBody, 'footer' => $htmlFooter]);
?>
