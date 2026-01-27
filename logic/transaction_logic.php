<?php
require_once('Connections/hms.php');

// Fetch Transaction Status (Summary)
try {
    $query_status = "SELECT 
                        tbl_personalinfo.patientid, 
                        CONCAT(tbl_personalinfo.Lname, ' , ', tbl_personalinfo.Fname, ' ', IFNULL(tbl_personalinfo.Mname, '')) AS namess, 
                        SUM(tlb_mastertransaction.Contribution) AS contribution, 
                        (SUM(tlb_mastertransaction.loanAmount) + SUM(tlb_mastertransaction.interest)) AS Loan, 
                        ((SUM(tlb_mastertransaction.loanAmount) + SUM(tlb_mastertransaction.interest)) - SUM(tlb_mastertransaction.loanRepayment)) AS Loanbalance, 
                        SUM(tlb_mastertransaction.withdrawal) AS withdrawal 
                     FROM tlb_mastertransaction 
                     INNER JOIN tbl_personalinfo ON tbl_personalinfo.patientid = tlb_mastertransaction.memberid 
                     GROUP BY patientid";
    
    $stmt_status = $conn->prepare($query_status);
    $stmt_status->execute();
    $row_status = $stmt_status->fetch(PDO::FETCH_ASSOC);
    $totalRows_status = $stmt_status->rowCount();
} catch (PDOException $e) {
    echo "Error fetching status: " . $e->getMessage();
}

// Fetch Payroll Periods (For Dropdowns)
try {
    $query_period = "SELECT Periodid, PayrollPeriod FROM tbpayrollperiods ORDER BY Periodid DESC";
    $stmt_period = $conn->prepare($query_period);
    $stmt_period->execute();
    
    // We fetch all periods into an array to be reused for both dropdowns
    $all_periods = $stmt_period->fetchAll(PDO::FETCH_ASSOC);
    $totalRows_Period = count($all_periods);
} catch (PDOException $e) {
    echo "Error fetching periods: " . $e->getMessage();
}
?>
