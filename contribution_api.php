<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once('Connections/hms.php');
require_once('logic/deduction_split.php');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Strip thousands separators from a money field posted by the UI. */
function postedMoney(string $field, float $fallback = 0.0): float
{
    $raw = str_replace(',', '', trim($_POST[$field] ?? ''));

    return is_numeric($raw) ? normaliseMoney((float)$raw) : $fallback;
}

/**
 * Write the member's standing bank loan — the amount every future import will
 * carve out. Zero removes the rule entirely rather than storing a dormant row.
 */
function saveStandingBankLoan(PDO $conn, string $memberId, float $amount, string $note): void
{
    if ($amount <= 0) {
        $conn->prepare("DELETE FROM tbl_bank_loans WHERE membersid = :id")
             ->execute([':id' => $memberId]);
        return;
    }

    $stmt = $conn->prepare(
        "INSERT INTO tbl_bank_loans (membersid, amount, note, updated_by)
         VALUES (:id, :amount, :note, :user)
         ON DUPLICATE KEY UPDATE amount = :amount2, note = :note2, updated_by = :user2"
    );
    $stmt->execute([
        ':id'      => $memberId,
        ':amount'  => $amount,
        ':amount2' => $amount,
        ':note'    => $note !== '' ? $note : null,
        ':note2'   => $note !== '' ? $note : null,
        ':user'    => $_SESSION['UserID'] ?? null,
        ':user2'   => $_SESSION['UserID'] ?? null,
    ]);
}

try {
    if ($action === 'fetch_periods') {
        $stmt = $conn->query("SELECT Periodid, PayrollPeriod, PhysicalYear FROM tbpayrollperiods ORDER BY Periodid DESC");
        $periods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'data' => $periods]);

    } elseif ($action === 'fetch_member') {
        $id       = $_POST['id']        ?? $_GET['id']        ?? null;
        $periodId = $_POST['period_id'] ?? $_GET['period_id'] ?? null;

        if (!$id)       throw new Exception("Member ID required");
        if (!$periodId) throw new Exception("Period ID required");

        // Personal info
        $stmt = $conn->prepare("SELECT patientid, Fname, Lname, Mname FROM tbl_personalinfo WHERE patientid = :id");
        $stmt->execute([':id' => $id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$member) throw new Exception("Member not found");

        // Contribution for this period
        $stmt_c = $conn->prepare(
            "SELECT contribution, loan, bank_loan, gross_deduction FROM tbl_contributions
             WHERE membersid = :id AND period_id = :pid"
        );
        $stmt_c->execute([':id' => $id, ':pid' => $periodId]);
        $contrib = $stmt_c->fetch(PDO::FETCH_ASSOC);
        if (!$contrib) {
            $contrib = ['contribution' => 0, 'loan' => 0, 'bank_loan' => 0, 'gross_deduction' => 0];
        }

        // Standing bank loan: the recurring amount future imports will carve out.
        $stmt_bl = $conn->prepare("SELECT amount, note FROM tbl_bank_loans WHERE membersid = :id");
        $stmt_bl->execute([':id' => $id]);
        $standingBankLoan = $stmt_bl->fetch(PDO::FETCH_ASSOC) ?: ['amount' => 0, 'note' => null];

        // Has this period already been posted to the ledger? Editing afterwards
        // does not rewrite tlb_mastertransaction, so the admin has to know.
        $stmt_p = $conn->prepare(
            "SELECT COUNT(*) FROM tlb_mastertransaction
             WHERE memberid = :id AND periodid = :pid AND completed = 1 AND (Contribution > 0 OR refund > 0)"
        );
        $stmt_p->execute([':id' => $id, ':pid' => $periodId]);
        $isProcessed = $stmt_p->fetchColumn() > 0;

        // Loan balance
        $stmt_bal = $conn->prepare(
            "SELECT ((SUM(loanAmount) + SUM(interest)) - SUM(loanRepayment)) as loanbalance
             FROM tlb_mastertransaction WHERE memberid = :id"
        );
        $stmt_bal->execute([':id' => $id]);
        $bal_row = $stmt_bal->fetch(PDO::FETCH_ASSOC);

        // Refund for this member + period
        $stmt_ref = $conn->prepare(
            "SELECT refundid, amount FROM tbl_refund WHERE membersid = :id AND periodid = :pid ORDER BY refundid DESC"
        );
        $stmt_ref->execute([':id' => $id, ':pid' => $periodId]);
        $refunds = $stmt_ref->fetchAll(PDO::FETCH_ASSOC);
        $refundTotal = array_sum(array_column($refunds, 'amount'));

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'member'              => $member,
                'contribution'        => $contrib,
                'loan_balance'        => $bal_row['loanbalance'] ?? 0,
                'refunds'             => $refunds,
                'refund_total'        => $refundTotal,
                'standing_bank_loan'  => (float)$standingBankLoan['amount'],
                'bank_loan_note'      => $standingBankLoan['note'],
                'is_processed'        => $isProcessed,
            ]
        ]);

    } elseif ($action === 'fetch_directory') {
        $page   = isset($_POST['page'])   ? (int)$_POST['page'] : 1;
        $limit  = 10;
        $offset = ($page - 1) * $limit;
        $search = trim($_POST['search'] ?? '');

        $sql    = "SELECT patientid, Fname, Lname, Mname, passport FROM tbl_personalinfo WHERE Status = 'Active'";
        $params = [];

        if (!empty($search)) {
            $sql .= " AND (Fname LIKE :s OR Lname LIKE :s OR Mname LIKE :s OR patientid LIKE :s)";
            $params[':s'] = "%$search%";
        }

        $countSql  = str_replace("SELECT patientid, Fname, Lname, Mname, passport", "SELECT COUNT(*) as total", $sql);
        $stmtCount = $conn->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
        $totalPages   = ceil($totalRecords / $limit);

        $sql  .= " ORDER BY patientid ASC LIMIT $offset, $limit";
        $stmt  = $conn->prepare($sql);
        $stmt->execute($params);
        $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'members'    => $members,
                'pagination' => [
                    'current_page'  => $page,
                    'total_pages'   => $totalPages,
                    'total_records' => $totalRecords
                ]
            ]
        ]);

    } elseif ($action === 'update_record') {
        $id       = $_POST['member_id']   ?? '';
        $periodId = $_POST['period_id']   ?? '';

        if (!$id)       throw new Exception("Member ID required");
        if (!$periodId) throw new Exception("Period ID required");

        $contrib  = postedMoney('contribution_amount');
        $loan     = postedMoney('loan_repayment');
        $bankLoan = postedMoney('bank_loan');

        // The gross is the payroll figure this split has to reconcile against.
        // If the admin has deliberately changed the parts so they no longer add
        // up, their edit wins and the gross moves with it — but we say so.
        $postedGross = postedMoney('gross_deduction');
        $partsTotal  = round($contrib + $loan + $bankLoan, DEDUCTION_MONEY_PRECISION);
        $grossMoved  = !isSplitBalanced($postedGross, $contrib, $loan, $bankLoan);
        $gross       = $grossMoved ? $partsTotal : $postedGross;

        $conn->beginTransaction();

        $check = $conn->prepare(
            "SELECT COUNT(*) FROM tbl_contributions WHERE membersid = :id AND period_id = :pid"
        );
        $check->execute([':id' => $id, ':pid' => $periodId]);

        if ($check->fetchColumn() > 0) {
            $stmt = $conn->prepare(
                "UPDATE tbl_contributions
                    SET contribution = :contrib, loan = :loan, bank_loan = :bank, gross_deduction = :gross
                  WHERE membersid = :id AND period_id = :pid"
            );
        } else {
            $stmt = $conn->prepare(
                "INSERT INTO tbl_contributions (contribution, loan, bank_loan, gross_deduction, membersid, period_id)
                 VALUES (:contrib, :loan, :bank, :gross, :id, :pid)"
            );
        }

        $stmt->execute([
            ':contrib' => $contrib,
            ':loan'    => $loan,
            ':bank'    => $bankLoan,
            ':gross'   => $gross,
            ':id'      => $id,
            ':pid'     => $periodId,
        ]);

        // "Recurring" means every future import carves this amount out for the
        // member, until it is set to zero or stopped.
        $isRecurring = ($_POST['bank_loan_recurring'] ?? '0') === '1';
        if ($isRecurring) {
            saveStandingBankLoan($conn, $id, $bankLoan, trim($_POST['bank_loan_note'] ?? ''));
        }

        $conn->commit();

        $message = 'Record updated successfully';
        if ($grossMoved) {
            $message .= '. Total from salary adjusted to ₦' . number_format($gross, 2) . ' to match the parts.';
        }
        if ($isRecurring && $bankLoan > 0) {
            $message .= ' Recurring bank loan of ₦' . number_format($bankLoan, 2) . ' saved for future imports.';
        }
        if ($isRecurring && $bankLoan <= 0) {
            $message .= ' Recurring bank loan cleared.';
        }

        echo json_encode([
            'status'      => 'success',
            'message'     => $message,
            'gross_moved' => $grossMoved,
            'gross'       => $gross,
        ]);

    } elseif ($action === 'clear_standing_bank_loan') {
        $id = $_POST['member_id'] ?? '';
        if (!$id) throw new Exception("Member ID required");

        saveStandingBankLoan($conn, $id, 0.0, '');

        echo json_encode([
            'status'  => 'success',
            'message' => 'Recurring bank loan stopped. Future imports will no longer set money aside for this member.',
        ]);

    } elseif ($action === 'bank_loan_report') {
        $periodId = (int)($_POST['period_id'] ?? $_GET['period_id'] ?? 0);
        if ($periodId <= 0) throw new Exception("Period ID required");

        $stmt = $conn->prepare(
            "SELECT c.membersid,
                    CONCAT(p.Lname, ', ', p.Fname, ' ', IFNULL(p.Mname, '')) AS fullname,
                    c.contribution, c.loan, c.bank_loan, c.gross_deduction
               FROM tbl_contributions c
               INNER JOIN tbl_personalinfo p ON p.patientid = c.membersid
              WHERE c.period_id = :pid AND c.bank_loan > 0
              ORDER BY c.bank_loan DESC"
        );
        $stmt->execute([':pid' => $periodId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Period-wide totals, including members with no bank loan, so the page
        // can prove the period reconciles.
        $stmt_t = $conn->prepare(
            "SELECT IFNULL(SUM(contribution), 0)    AS total_contribution,
                    IFNULL(SUM(loan), 0)            AS total_loan,
                    IFNULL(SUM(bank_loan), 0)       AS total_bank_loan,
                    IFNULL(SUM(gross_deduction), 0) AS total_gross
               FROM tbl_contributions
              WHERE period_id = :pid"
        );
        $stmt_t->execute([':pid' => $periodId]);
        $totals = $stmt_t->fetch(PDO::FETCH_ASSOC);

        $parts = $totals['total_contribution'] + $totals['total_loan'] + $totals['total_bank_loan'];

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'rows'        => $rows,
                'totals'      => $totals,
                'is_balanced' => isSplitBalanced(
                    (float)$totals['total_gross'],
                    (float)$totals['total_contribution'],
                    (float)$totals['total_loan'],
                    (float)$totals['total_bank_loan']
                ),
                'difference'  => round($totals['total_gross'] - $parts, DEDUCTION_MONEY_PRECISION),
                'member_count' => count($rows),
            ]
        ]);

    } elseif ($action === 'delete_refund') {
        $refundId = (int)($_POST['refund_id'] ?? 0);
        if (!$refundId) throw new Exception("Refund ID required");

        $stmt = $conn->prepare("DELETE FROM tbl_refund WHERE refundid = :rid");
        $stmt->execute([':rid' => $refundId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Refund deleted']);
        } else {
            throw new Exception("Refund not found");
        }

    } else {
        throw new Exception("Invalid action");
    }

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('contribution_api [' . $action . ']: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
