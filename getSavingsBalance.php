<?php 
include_once('mysql/mysql2i.class.php');
require_once('Connections/hms.php');

$col_Balance = "-1";
if (isset($_GET['id'])) {
  $col_Balance = $_GET['id'];
}

if (isset($conn)) {
    try {
        $stmt = $conn->prepare("SELECT (IFNULL(SUM(Contribution),0) + IFNULL(SUM(withdrawal),0)) AS balance FROM tlb_mastertransaction WHERE memberid = :memberid");
        $stmt->execute([':memberid' => $col_Balance]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $balance = $row['balance'] ?? 0;
    } catch(PDOException $e) {
        $balance = 0;
    }
} else {
    // MySQLi Fallback
    mysqli_select_db($hms, $database_hms);
    $safeId = mysqli_real_escape_string($hms, $col_Balance);
    $query = "SELECT (IFNULL(SUM(Contribution),0) + IFNULL(SUM(withdrawal),0)) AS balance FROM tlb_mastertransaction WHERE memberid = '$safeId'";
    $res = mysqli_query($hms, $query);
    $row_res = mysqli_fetch_assoc($res);
    $balance = $row_res['balance'] ?? 0;
}

// Return only the formatted number
echo number_format($balance, 2, '.', ',');
?>
