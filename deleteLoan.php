<?php
require_once('Connections/hms.php');
session_start();

// Expecting loanID via GET
if (isset($_GET['loanID']) && !empty($_GET['loanID'])) {
    $loanID = $_GET['loanID'];

    try {
        // 1. Fetch Loan Details to identify correlated records
        $stmtFetch = $conn->prepare("SELECT memberid, periodid, loanamount FROM tbl_loan WHERE loanid = :loanid");
        $stmtFetch->execute([':loanid' => $loanID]);
        $loan = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        if ($loan) {
            $memberId = $loan['memberid'];
            $periodId = $loan['periodid'];
            $loanAmountWithFee = floatval($loan['loanamount']);
            
            // Logic from addloan.php: tbl_loan stores (Amount + 100), tbl_bank_schedule stores (Amount)
            $originalAmount = $loanAmountWithFee - 100;

            $conn->beginTransaction();

            // 2. Delete from tbl_loan
            $stmtDelLoan = $conn->prepare("DELETE FROM tbl_loan WHERE loanid = :loanid");
            $stmtDelLoan->execute([':loanid' => $loanID]);

            // 3. Delete from tlb_mastertransaction
            // It has a loanid column so we can be precise
            $stmtDelMaster = $conn->prepare("DELETE FROM tlb_mastertransaction WHERE loanid = :loanid");
            $stmtDelMaster->execute([':loanid' => $loanID]);

            // 4. Delete from tbl_bank_schedule
            // Matches member, period, and original amount. 
            // LIMIT 1 ensures we don't delete duplicates if they happen to exist? 
            // Ideally, we delete the specific one, but we don't have a unique ID link easily.
            // Safe bet: Delete one matching record.
            $stmtDelBank = $conn->prepare("DELETE FROM tbl_bank_schedule WHERE memberid = :memberid AND periodid = :periodid AND loanamount = :amount LIMIT 1");
            $stmtDelBank->execute([
                ':memberid' => $memberId,
                ':periodid' => $periodId,
                ':amount' => $originalAmount
            ]);

            $conn->commit();
            
            // Return success JSON or plain text depending on legacy usage? 
            // The frontend calls `$.get("deleteLoan.php?loanID="+loanID, function(data){...})`
            // and likely ignores parsing if it just expects 200 OK.
            // But let's return JSON to be clean.
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'message' => 'Loan deleted']);

        } else {
             // Loan found?
             header('Content-Type: application/json');
             echo json_encode(['status' => 'error', 'message' => 'Loan not found']);
        }

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'No Loan ID provided']);
}
?>
