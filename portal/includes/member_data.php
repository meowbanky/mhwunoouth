<?php
/**
 * A member's own financial records.
 *
 * Every function here takes the member ID as its first argument and every
 * query filters on it. Callers must pass currentMemberId() — the value from
 * the session — and never anything derived from the request.
 *
 * Read-only throughout. The portal writes nothing to the financial tables.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Name, department and status for the header. */
function memberProfile(PDO $conn, string $memberId): ?array
{
    $stmt = $conn->prepare(
        "SELECT patientid, Fname, Lname, Mname, Dept, EmailAddress, MobilePhone, DateOfReg, Status
           FROM tbl_personalinfo
          WHERE patientid = ?"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/**
 * Headline balances.
 *
 * Loan balance mirrors the formula the processing engine uses, so the figure
 * a member sees is the same one the office works from:
 *     (loanAmount + interest) - (loanRepayment + repayment_bank)
 */
function memberSummary(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT IFNULL(SUM(Contribution), 0)                                   AS total_contribution,
                IFNULL(SUM(loanAmount), 0)                                     AS total_loan,
                IFNULL(SUM(interest), 0)                                       AS total_interest,
                IFNULL(SUM(loanRepayment), 0)                                  AS total_repayment,
                IFNULL(SUM(repayment_bank), 0)                                 AS total_bank_repayment,
                IFNULL(SUM(withdrawal), 0)                                     AS total_withdrawal,
                IFNULL(SUM(refund), 0)                                         AS total_refund
           FROM tlb_mastertransaction
          WHERE memberid = ?"
    );
    $stmt->execute([$memberId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalContribution = (float)($row['total_contribution'] ?? 0);
    $totalWithdrawal   = (float)($row['total_withdrawal'] ?? 0);
    $loanIssued        = (float)($row['total_loan'] ?? 0) + (float)($row['total_interest'] ?? 0);
    $loanRepaid        = (float)($row['total_repayment'] ?? 0) + (float)($row['total_bank_repayment'] ?? 0);

    // Bank loan is recorded per period on the deduction, not in the ledger,
    // because the union does not track that debt.
    $bankStmt = $conn->prepare(
        "SELECT IFNULL(SUM(bank_loan), 0) FROM tbl_contributions WHERE membersid = ?"
    );
    $bankStmt->execute([$memberId]);

    return [
        'total_contribution' => $totalContribution,
        'total_withdrawal'   => $totalWithdrawal,
        'savings_balance'    => $totalContribution - $totalWithdrawal,
        'loan_issued'        => $loanIssued,
        'loan_repaid'        => $loanRepaid,
        'loan_balance'       => $loanIssued - $loanRepaid,
        'total_refund'       => (float)($row['total_refund'] ?? 0),
        'total_bank_loan'    => (float)$bankStmt->fetchColumn(),
    ];
}

/**
 * Contribution history — what was actually credited, period by period.
 *
 * This reads the ledger, not tbl_contributions. That table holds the *current*
 * deduction instruction and only carries rows for periods that have been
 * imported, so using it here showed a member years of savings in the headline
 * total and an empty table underneath. See memberDeductions() for the split.
 */
function memberContributions(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT m.periodid AS period_id,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                m.Contribution AS contribution
           FROM tlb_mastertransaction m
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = m.periodid
          WHERE m.memberid = ? AND m.Contribution > 0
          ORDER BY m.periodid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * How each payroll deduction was split.
 *
 * Only covers periods imported from payroll, so it is typically much shorter
 * than the contribution history above.
 */
function memberDeductions(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT c.period_id,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                c.contribution,
                c.loan            AS union_loan,
                c.bank_loan,
                c.gross_deduction
           FROM tbl_contributions c
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = c.period_id
          WHERE c.membersid = ? AND c.gross_deduction > 0
          ORDER BY c.period_id DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Every posted ledger row, newest first. */
function memberTransactions(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT m.transactionid,
                m.periodid,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                m.Contribution,
                m.loanAmount,
                m.interest,
                m.loanRepayment,
                m.repayment_bank,
                m.refund,
                m.withdrawal
           FROM tlb_mastertransaction m
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = m.periodid
          WHERE m.memberid = ?
          ORDER BY m.periodid DESC, m.transactionid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Loans taken, with interest. */
function memberLoans(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT m.transactionid,
                m.periodid,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                m.loanAmount,
                m.interest,
                (m.loanAmount + m.interest) AS total_repayable
           FROM tlb_mastertransaction m
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = m.periodid
          WHERE m.memberid = ? AND m.loanAmount > 0
          ORDER BY m.periodid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Repayments, from payroll and from bank deposits. */
function memberRepayments(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT m.periodid,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                m.loanRepayment,
                m.repayment_bank,
                (m.loanRepayment + m.repayment_bank) AS total_repaid
           FROM tlb_mastertransaction m
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = m.periodid
          WHERE m.memberid = ? AND (m.loanRepayment > 0 OR m.repayment_bank > 0)
          ORDER BY m.periodid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Bank loan deductions — money set aside that the union does not track. */
function memberBankLoans(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT c.period_id,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                c.bank_loan,
                c.gross_deduction
           FROM tbl_contributions c
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = c.period_id
          WHERE c.membersid = ? AND c.bank_loan > 0
          ORDER BY c.period_id DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Refunds issued when a loan deduction overshot the balance. */
function memberRefunds(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT r.refundid,
                r.periodid,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                r.amount
           FROM tbl_refund r
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = r.periodid
          WHERE r.membersid = ?
          ORDER BY r.periodid DESC, r.refundid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/** Withdrawals against savings. */
function memberWithdrawals(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT m.transactionid,
                m.periodid,
                pp.PayrollPeriod,
                pp.PhysicalYear,
                m.withdrawal
           FROM tlb_mastertransaction m
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = m.periodid
          WHERE m.memberid = ? AND m.withdrawal > 0
          ORDER BY m.periodid DESC"
    );
    $stmt->execute([$memberId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Label a period for display.
 *
 * PayrollPeriod usually already carries the year ("June - 2018"), so appending
 * PhysicalYear blindly produced "June - 2018 2018". Tolerates rows whose
 * payroll period has since been deleted.
 */
function periodLabel(array $row): string
{
    $name = trim((string)($row['PayrollPeriod'] ?? ''));
    $year = trim((string)($row['PhysicalYear'] ?? ''));

    if ($name === '' && $year === '') {
        return 'Period ' . ($row['period_id'] ?? $row['periodid'] ?? '—');
    }

    if ($year !== '' && strpos($name, $year) === false) {
        $name = trim($name . ' ' . $year);
    }

    return $name !== '' ? $name : $year;
}

/**
 * The distinct periods this member has any activity in, newest first.
 *
 * Drives the statement's period filter, so it spans both the ledger and the
 * deduction table rather than either alone.
 */
function memberPeriods(PDO $conn, string $memberId): array
{
    $stmt = $conn->prepare(
        "SELECT p.periodid, pp.PayrollPeriod, pp.PhysicalYear
           FROM (
                SELECT DISTINCT periodid  FROM tlb_mastertransaction WHERE memberid = :id
                UNION
                SELECT DISTINCT period_id FROM tbl_contributions     WHERE membersid = :id2 AND gross_deduction > 0
           ) p
           LEFT JOIN tbpayrollperiods pp ON pp.Periodid = p.periodid
          WHERE p.periodid IS NOT NULL
          ORDER BY p.periodid DESC"
    );
    $stmt->execute([':id' => $memberId, ':id2' => $memberId]);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = [
            'periodid' => (int)$row['periodid'],
            'label'    => periodLabel($row + ['period_id' => $row['periodid']]),
        ];
    }

    return $rows;
}
