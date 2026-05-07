<?php
session_start();
require_once('Connections/hms.php');

if(isset($_GET['transactionid']) && !empty($_GET['transactionid'])) {
    $transactionid = $_GET['transactionid'];
    try {
        $stmt = $conn->prepare("DELETE FROM tlb_mastertransaction WHERE transactionid = :transactionid AND withdrawal < 0");
        $stmt->execute([':transactionid' => $transactionid]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Withdrawal deleted']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Withdrawal not found or already deleted']);
        }
    } catch(PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No Transaction ID provided']);
}
?>
