<?php 
include_once('mysql/mysql2i.class.php');
require_once('Connections/hms.php');

$col_loanBalance = "-1";
if (isset($_GET['id'])) {
  $col_loanBalance = $_GET['id'];
}

// Ensure database connection (hms.php handles PDO $conn or fallback mysqli $hms)
// Since we are refactoring, let's use prepared statements if PDO is available, or stick to safe mysqli.
// Given hms.php provided earlier had both, let's stick to simple mysqli for now for minimal breakage in this file,
// OR switch to PDO. Since addloan.php uses PDO, using PDO here is consistent.

if (isset($conn)) {
    // PDO Path
    try {
        $stmt = $conn->prepare("SELECT ((sum(loanAmount)+sum(interest))- (sum(loanRepayment)+sum(repayment_bank))) as 'balance' FROM tlb_mastertransaction WHERE memberid = :memberid");
        $stmt->execute([':memberid' => $col_loanBalance]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $row['balance'] ?? 0;
    } catch(PDOException $e) {
        $balance = 0;
    }
} else {
    // MySQLi Fallback (Existing Logic Cleaned)
    mysqli_select_db($hms, $database_hms);
    $safeId = mysqli_real_escape_string($hms, $col_loanBalance);
    $query_loanBalance = "SELECT ((sum(tlb_mastertransaction.loanAmount)+sum(interest))- (sum(tlb_mastertransaction.loanRepayment)+sum(tlb_mastertransaction.repayment_bank))) as 'balance' FROM tlb_mastertransaction WHERE memberid = '$safeId'";
    $loanBalance = mysqli_query($hms, $query_loanBalance);
    $row_loanBalance = mysqli_fetch_assoc($loanBalance);
    $balance = $row_loanBalance['balance'] ?? 0;
}

// Return only the formatted number
echo number_format($balance, 2, '.', ',');
?>
