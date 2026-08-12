-- ---------------------------------------------------------------------------
-- Bank loan deductions
--
-- Some members repay a bank loan through the same payroll deduction that funds
-- their union contribution and union loan repayment. The union does not track
-- the bank loan itself, but it must be carved out of the deduction before the
-- loan engine runs, and it must be recorded so the period reconciles:
--
--     gross_deduction = contribution + loan + bank_loan
--
-- Run once, against the live database, before deploying the PHP changes.
-- Safe to run on a live system: additive columns with defaults, and the
-- backfill makes every existing row satisfy the invariant retroactively.
-- ---------------------------------------------------------------------------

-- 1. Per-period record: what was actually set aside, and what payroll sent.
ALTER TABLE tbl_contributions
    ADD COLUMN bank_loan       DECIMAL(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Untracked bank loan repayment carved out of the salary deduction',
    ADD COLUMN gross_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00
        COMMENT 'Total deducted from salary; equals contribution + loan + bank_loan';

-- 2. Backfill: before this feature the whole deduction was contribution + loan,
--    so the invariant holds for historic rows once gross is filled in.
UPDATE tbl_contributions
   SET gross_deduction = contribution + loan,
       bank_loan       = 0.00;

-- 3. Standing instruction: the recurring monthly bank loan per member, applied
--    automatically by every future API import until set to zero or removed.
--    Deliberately has no period column — it is a rule, not a transaction.
CREATE TABLE IF NOT EXISTS tbl_bank_loans (
    membersid  INT           NOT NULL,
    amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    note       VARCHAR(255)      NULL,
    updated_by VARCHAR(50)       NULL,
    updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (membersid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------------
-- Verification — every row should report balanced = 0.
--
--   SELECT COUNT(*) AS unbalanced
--     FROM tbl_contributions
--    WHERE ABS(gross_deduction - (contribution + loan + bank_loan)) > 0.005;
--
-- Reconcile a single period:
--
--   SELECT SUM(contribution) AS contributions,
--          SUM(loan)         AS union_loans,
--          SUM(bank_loan)    AS bank_loans,
--          SUM(gross_deduction) AS gross
--     FROM tbl_contributions
--    WHERE period_id = ?;
-- ---------------------------------------------------------------------------
