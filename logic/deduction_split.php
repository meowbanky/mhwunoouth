<?php
/**
 * Salary deduction split.
 *
 * The payroll API sends a single figure per member: the total deducted from
 * their salary on the union's behalf. That figure can contain three things:
 *
 *     gross = contribution + union loan repayment + bank loan
 *
 * The bank loan is a debt the union does not track — it is collected through
 * the same payroll deduction but belongs to a third party. It must be carved
 * out before the union's loan engine sees the money, otherwise members' union
 * loans are over-credited and the engine starts refunding the overshoot.
 *
 * This file is pure arithmetic with no database access, because the two
 * callers speak to the database through different drivers:
 *   - api/upload_json_data.php  (mysqli)
 *   - contribution_api.php      (PDO)
 */

/** Rounding drift below this is treated as zero when comparing money. */
const DEDUCTION_BALANCE_TOLERANCE = 0.005;

/** Money is stored to two decimal places throughout the schema. */
const DEDUCTION_MONEY_PRECISION = 2;

/**
 * Split a gross salary deduction into its three destinations.
 *
 * Allocation order is contribution, then bank loan, then whatever remains is
 * the union loan repayment. So when the deduction falls short, the shortfall
 * is borne by the bank loan and the member's savings stay whole.
 *
 * @param float $gross               Total deducted from salary for this period.
 * @param float $defaultContribution The member's standing monthly contribution.
 * @param float $bankLoan            The member's standing monthly bank loan, 0 if none.
 *
 * @return array{gross:float,contribution:float,loan:float,bank_loan:float,shortfall:float}
 *         `shortfall` is how much of the requested contribution + bank loan the
 *         deduction could not cover; non-zero means the row needs review.
 */
function splitSalaryDeduction(float $gross, float $defaultContribution, float $bankLoan): array
{
    $gross               = normaliseMoney($gross);
    $defaultContribution = normaliseMoney($defaultContribution);
    $bankLoan            = normaliseMoney($bankLoan);

    $contribution = min($gross, $defaultContribution);
    $afterContribution = round($gross - $contribution, DEDUCTION_MONEY_PRECISION);

    $bankLoanApplied = min($afterContribution, $bankLoan);

    // Derive the union's share as the residual so the three parts always add
    // back up to the gross exactly, whatever the rounding did.
    $loan = round($afterContribution - $bankLoanApplied, DEDUCTION_MONEY_PRECISION);

    $requested = round($defaultContribution + $bankLoan, DEDUCTION_MONEY_PRECISION);
    $shortfall = max(0.0, round($requested - $gross, DEDUCTION_MONEY_PRECISION));

    return [
        'gross'        => $gross,
        'contribution' => $contribution,
        'loan'         => $loan,
        'bank_loan'    => $bankLoanApplied,
        'shortfall'    => $shortfall,
    ];
}

/**
 * Does a hand-edited split still add up to the gross deduction?
 *
 * Used to reject manual edits that would break the reconciliation between the
 * payroll figure and what the union recorded.
 */
function isSplitBalanced(float $gross, float $contribution, float $loan, float $bankLoan): bool
{
    $difference = abs($gross - ($contribution + $loan + $bankLoan));

    return $difference < DEDUCTION_BALANCE_TOLERANCE;
}

/** Clamp a money value to a non-negative figure at the schema's precision. */
function normaliseMoney(float $amount): float
{
    if (!is_finite($amount) || $amount <= 0) {
        return 0.0;
    }

    return round($amount, DEDUCTION_MONEY_PRECISION);
}
