<?php
require_once('Connections/hms.php');

// Ensure database connection
if (!$conn) {
    echo '<div class="p-4 text-red-500 font-bold">Database connection failed.</div>';
    exit;
}

// Fetch transactions based on parameters using PDO
function fetchTransactions($conn, $periodFrom, $periodTo, $id = '')
{
  $queryParams = [];
  
  // Correlated subqueries for running totals
  // Note: Using 'tm' alias for the inner tables
  $query = "SELECT
  tbl_personalinfo.patientid,
  MIN(tlb_mastertransaction.transactionid) transactionid,
  concat(tbl_personalinfo.Lname,' , ', tbl_personalinfo.Fname,' ', ifnull( tbl_personalinfo.Mname,'')) AS namess,
  IFNULL(sum(tlb_mastertransaction.repayment_bank),0) AS bankPayment,
  Sum(tlb_mastertransaction.Contribution) AS Contribution,
  (Sum(tlb_mastertransaction.loanAmount) + Sum(tlb_mastertransaction.interest)) AS loan,
  Sum(tlb_mastertransaction.loanRepayment) AS loanrepayments,
  Sum(tlb_mastertransaction.withdrawal) AS withrawals,
  (Sum(tlb_mastertransaction.Contribution) + Sum(tlb_mastertransaction.loanRepayment)+ MIN(IFNULL(tbl_refund.amount,0))+IFNULL(sum(tlb_mastertransaction.repayment_bank),0) ) AS total,
  tbpayrollperiods.PayrollPeriod,
  tlb_mastertransaction.periodid,
  MIN(IFNULL(tbl_refund.amount,0)) AS 'refund',
  
  -- Running Contribution Balance (History <= Current Period)
  (SELECT SUM(IFNULL(tm.Contribution,0)) - SUM(IFNULL(tm.withdrawal,0))
   FROM tlb_mastertransaction tm 
   WHERE tm.memberid = tbl_personalinfo.patientid 
   AND tm.periodid <= tlb_mastertransaction.periodid
  ) as RunningContribution,

  -- Running Loan Balance (History <= Current Period)
  (SELECT SUM(IFNULL(tm.loanAmount,0)) + SUM(IFNULL(tm.interest,0)) - SUM(IFNULL(tm.loanRepayment,0)) - SUM(IFNULL(tm.repayment_bank,0))
   FROM tlb_mastertransaction tm 
   WHERE tm.memberid = tbl_personalinfo.patientid 
   AND tm.periodid <= tlb_mastertransaction.periodid
  ) as RunningLoanBal

  FROM
  tbl_personalinfo
  INNER JOIN tlb_mastertransaction ON tbl_personalinfo.patientid = tlb_mastertransaction.memberid
  INNER JOIN tbpayrollperiods ON tbpayrollperiods.Periodid = tlb_mastertransaction.periodid
  LEFT JOIN tbl_refund ON tbl_refund.membersid = tbl_personalinfo.patientid AND tbl_refund.periodid = tbpayrollperiods.Periodid
  WHERE tbpayrollperiods.Periodid BETWEEN ? AND ?";

  $queryParams[] = $periodFrom;
  $queryParams[] = $periodTo;

  if ($id !== '') {
    $query .= " AND tbl_personalinfo.patientid = ?";
    $queryParams[] = $id;
  }

  $query .= " GROUP BY tlb_mastertransaction.periodid, tbl_personalinfo.patientid order by tbl_personalinfo.patientid";

  try {
      $stmt = $conn->prepare($query);
      $stmt->execute($queryParams);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch(PDOException $e) {
      // Log error in production
      return [];
  }
}

// Parameters from URL
$periodFrom = isset($_GET['periodfrom']) ? $_GET['periodfrom'] : '-1';
$periodTo = isset($_GET['periodTo']) ? $_GET['periodTo'] : '-1';
$id = isset($_GET['id']) ? $_GET['id'] : null;

// Fetching the data
$transactions = fetchTransactions($conn, $periodFrom, $periodTo, $id);

?>
<div class="overflow-hidden bg-white dark:bg-slate-900 rounded-lg shadow-sm border border-slate-200 dark:border-slate-800">
    <div class="flex justify-end p-4 border-b border-slate-100 dark:border-slate-800">
        <button type="button" class="bg-red-500 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg shadow-md transition-colors text-sm flex items-center gap-2" onclick="deletetrans()">
            <span class="material-icons-round text-sm">delete</span>
            Delete Selected
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead class="bg-slate-50 dark:bg-slate-800/50">
                <tr>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">
                        <div class="flex items-center gap-2">
                            <input type="checkbox" id="checkAll" onclick="toggleAll(this)" checked class="rounded border-slate-300 text-primary focus:ring-primary h-4 w-4" />
                            <span>Select</span>
                        </div>
                    </th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Member ID</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Period</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Name</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Wel. Savings</th>
                    <th class="px-6 py-3 text-xs font-semibold text-white bg-blue-500 uppercase tracking-wider">Contr. Bal</th> <!-- New -->
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Loan</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Bank Repay</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Loan Repay</th>
                     <th class="px-6 py-3 text-xs font-semibold text-white bg-red-500 uppercase tracking-wider">Loan Bal</th> <!-- New -->
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Interest</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Refund</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Withdrawal</th>
                    <th class="px-6 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wider">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm">
                <?php 
                $Contribution = 0;
                $loan = 0;
                $bankPayment = 0;
                $loanrepayments = 0;
                $refund = 0;
                $withrawals = 0;
                $grandTotal = 0;
                $interest = 0;

                // Running Balances calculated via SQL now
                // $cumContribution = 0;
                // $cumLoanBal = 0;

                foreach ($transactions as $row_status) { 
                    // Calculate Cumulative Balances for this row
                    // Contribution Balance = (Previous + Contribution) - Withdrawal
                    // $cumContribution += ($row_status['Contribution'] - $row_status['withrawals']);

                    // Loan Balance = (Previous + Loan) - (LoanRepayment + BankPayment)
                    // Note: 'loan' in query includes interest already (loanAmount + interest)
                    // $cumLoanBal += ($row_status['loan'] - ($row_status['loanrepayments'] + $row_status['bankPayment']));
                ?>
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
                    <td class="px-6 py-4">
                        <input name="memberid" type="checkbox" id="memberid" value="<?php echo $row_status['patientid']; ?>,<?php echo $row_status['periodid']; ?>" checked="checked" class="rounded border-slate-300 text-primary focus:ring-primary"/>
                    </td>
                    <td class="px-6 py-4 font-medium text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($row_status['patientid']); ?></td>
                    <td class="px-6 py-4 text-slate-500 dark:text-slate-400"><?php echo $row_status['PayrollPeriod']; ?></td>
                    <td class="px-6 py-4 text-slate-700 dark:text-slate-300"><?php echo $row_status['namess']; ?></td>
                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php $Contribution = $Contribution + $row_status['Contribution'];
                        echo number_format($row_status['Contribution'], 2, '.', ','); ?>
                    </td>
                    
                    <!-- Cumulative Contribution Balance (From SQL) -->
                    <td class="px-6 py-4 text-right font-mono font-bold text-blue-600 bg-blue-50 dark:bg-blue-900/10">
                        <?php echo number_format($row_status['RunningContribution'], 2, '.', ','); ?>
                    </td>

                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php $loan = $loan + $row_status['loan'];
                        echo number_format($row_status['loan'], 2, '.', ','); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php if ($row_status['bankPayment'] > 0) {
                            $bankPayment = $bankPayment + $row_status['bankPayment'];
                        ?>
                        <a href="viewTeller.php?from=<?php echo $periodFrom; ?>&to=<?php echo $periodTo; ?>&id=<?php echo $row_status['patientid'] ?>" target="new" class="text-primary hover:underline">
                            <?php echo number_format($row_status['bankPayment'], 2, '.', ','); ?>
                        </a>
                        <?php } else { echo number_format(0, 2, '.', ','); } ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php
                        $loanrepayments = $loanrepayments + $row_status['loanrepayments'];
                        echo number_format($row_status['loanrepayments'], 2, '.', ','); ?>
                    </td>

                    <!-- Cumulative Loan Balance (From SQL) -->
                    <td class="px-6 py-4 text-right font-mono font-bold text-red-600 bg-red-50 dark:bg-red-900/10">
                        <?php echo number_format($row_status['RunningLoanBal'], 2, '.', ','); ?>
                    </td>

                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php $interest = $interest + ($row_status['loanrepayments'] * (0.1));
                        echo number_format((($row_status['loanrepayments']) * (0.1)), 2, '.', ','); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php $refund = $refund + $row_status['refund'];
                        echo number_format($row_status['refund'], 2, '.', ','); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-400">
                        <?php $withrawals = $withrawals + $row_status['withrawals'];
                        echo number_format($row_status['withrawals'], 2, '.', ','); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-mono font-bold text-slate-800 dark:text-slate-200">
                        <?php $grandTotal = $grandTotal + $row_status['total'];
                        echo number_format($row_status['total'], 2, '.', ','); ?>
                    </td>
                </tr> 
                <?php } ?>
            </tbody>
            <tfoot class="bg-slate-100 dark:bg-slate-800 border-t-2 border-slate-200 dark:border-slate-700 font-bold text-sm">
                <tr>
                    <td class="px-6 py-4" colspan="4">Total</td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($Contribution, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right">-</td> <!-- Bal placeholder -->
                    <td class="px-6 py-4 text-right"><?php echo number_format($loan, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($bankPayment, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($loanrepayments, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right">-</td> <!-- Bal placeholder -->
                    <td class="px-6 py-4 text-right"><?php echo number_format($interest, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($refund, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($withrawals, 2, '.', ','); ?></td>
                    <td class="px-6 py-4 text-right"><?php echo number_format($grandTotal, 2, '.', ','); ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<script>
    function toggleAll(source) {
        var checkboxes = document.getElementsByName('memberid');
        for(var i=0, n=checkboxes.length;i<n;i++) {
            checkboxes[i].checked = source.checked;
        }
    }
</script>