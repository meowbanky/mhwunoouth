<?php
require_once('Connections/hms.php');

if (isset($_GET['periodid']) && isset($_GET['memberid'])) {
    
    // Ensure inputs are integers for safety, though prepared statements handle this
    $periodId = intval($_GET['periodid']);
    $memberId = intval($_GET['memberid']);

    if (!$conn) {
        die("Database connection failed.");
    }

    try {
        $conn->beginTransaction();

        // Delete from master transaction
        $stmt1 = $conn->prepare("DELETE FROM tlb_mastertransaction WHERE periodid = :periodid AND memberid = :memberid");
        $stmt1->bindParam(':periodid', $periodId, PDO::PARAM_INT);
        $stmt1->bindParam(':memberid', $memberId, PDO::PARAM_INT);
        $stmt1->execute();

        // Delete from loan
        $stmt2 = $conn->prepare("DELETE FROM tbl_loan WHERE periodid = :periodid AND memberid = :memberid");
        $stmt2->bindParam(':periodid', $periodId, PDO::PARAM_INT);
        $stmt2->bindParam(':memberid', $memberId, PDO::PARAM_INT);
        $stmt2->execute();

        // Delete from refund
        $stmt3 = $conn->prepare("DELETE FROM tbl_refund WHERE periodid = :periodid AND membersid = :memberid");
        $stmt3->bindParam(':periodid', $periodId, PDO::PARAM_INT);
        $stmt3->bindParam(':memberid', $memberId, PDO::PARAM_INT);
        $stmt3->execute();

        $conn->commit();
        
        // Return success/empty response as expected by the AJAX caller
        // Or if the caller expects a redirect (the AJAX code in mastertransaction checks for status 200)
        
    } catch (PDOException $e) {
        $conn->rollBack();
        header('HTTP/1.1 500 Internal Server Error');
        echo "Error: " . $e->getMessage();
    }
}
?>