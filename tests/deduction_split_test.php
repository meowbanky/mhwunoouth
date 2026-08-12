<?php
/**
 * Tests for logic/deduction_split.php
 *
 * Standalone runner (this project has no Composer/PHPUnit).
 *   php tests/deduction_split_test.php
 */

declare(strict_types=1);

require_once(__DIR__ . '/../logic/deduction_split.php');

$passed = 0;
$failed = 0;

function assertSame_(string $name, $expected, $actual): void
{
    global $passed, $failed;
    if ($expected === $actual) {
        $passed++;
        echo "  PASS  {$name}\n";
        return;
    }
    $failed++;
    echo "  FAIL  {$name}\n";
    echo "        expected: " . var_export($expected, true) . "\n";
    echo "        actual:   " . var_export($actual, true) . "\n";
}

function assertSplit(string $name, array $expected, array $actual): void
{
    foreach ($expected as $key => $value) {
        assertSame_("{$name} [{$key}]", $value, $actual[$key]);
    }
}

echo "\nsplitSalaryDeduction()\n";

// --- Arrange/Act/Assert: the scenario that motivated this feature -----------
// Member A: 100,000 deducted from salary, 80,000 of it is an untracked bank
// loan. Default contribution 5,000. The union may only repay its own loan with
// what is left: 15,000.
assertSplit(
    'carves the bank loan out before the union loan',
    ['contribution' => 5000.0, 'bank_loan' => 80000.0, 'loan' => 15000.0, 'shortfall' => 0.0],
    splitSalaryDeduction(100000.0, 5000.0, 80000.0)
);

// --- No bank loan: behaviour must be identical to the pre-feature importer --
assertSplit(
    'without a bank loan the remainder is all union loan repayment',
    ['contribution' => 5000.0, 'bank_loan' => 0.0, 'loan' => 95000.0, 'shortfall' => 0.0],
    splitSalaryDeduction(100000.0, 5000.0, 0.0)
);

// --- Deduction covers the contribution only --------------------------------
assertSplit(
    'contribution only, nothing left over',
    ['contribution' => 5000.0, 'bank_loan' => 0.0, 'loan' => 0.0, 'shortfall' => 0.0],
    splitSalaryDeduction(5000.0, 5000.0, 0.0)
);

// --- Shortfall: contribution wins, bank loan absorbs the shortage ----------
assertSplit(
    'shortfall reduces the bank loan, never the contribution',
    ['contribution' => 5000.0, 'bank_loan' => 55000.0, 'loan' => 0.0, 'shortfall' => 25000.0],
    splitSalaryDeduction(60000.0, 5000.0, 80000.0)
);

// --- Extreme shortfall: gross cannot even cover the contribution -----------
assertSplit(
    'gross below the contribution clamps everything downstream to zero',
    ['contribution' => 3000.0, 'bank_loan' => 0.0, 'loan' => 0.0, 'shortfall' => 82000.0],
    splitSalaryDeduction(3000.0, 5000.0, 80000.0)
);

// --- Member dropped from the payroll file ----------------------------------
assertSplit(
    'zero gross produces a zeroed split, not a phantom bank loan',
    ['contribution' => 0.0, 'bank_loan' => 0.0, 'loan' => 0.0, 'shortfall' => 85000.0],
    splitSalaryDeduction(0.0, 5000.0, 80000.0)
);

// --- Defensive: negative inputs are treated as zero ------------------------
assertSplit(
    'negative gross is clamped to zero',
    ['gross' => 0.0, 'contribution' => 0.0, 'bank_loan' => 0.0, 'loan' => 0.0],
    splitSalaryDeduction(-100.0, 5000.0, 80000.0)
);

assertSplit(
    'negative bank loan is treated as no bank loan',
    ['contribution' => 5000.0, 'bank_loan' => 0.0, 'loan' => 95000.0, 'shortfall' => 0.0],
    splitSalaryDeduction(100000.0, 5000.0, -80000.0)
);

// --- The invariant, including on figures that do not divide cleanly --------
echo "\ninvariant: gross === contribution + loan + bank_loan\n";

$cases = [
    [100000.0, 5000.0, 80000.0],
    [99999.99, 5000.55, 80000.33],
    [60000.0,  5000.0,  80000.0],
    [1.01,     0.33,    0.33],
    [0.0,      5000.0,  80000.0],
    [12345.67, 1234.56, 2345.67],
];

foreach ($cases as [$gross, $default, $bank]) {
    $split = splitSalaryDeduction($gross, $default, $bank);
    $sum   = round($split['contribution'] + $split['loan'] + $split['bank_loan'], 2);
    assertSame_(
        sprintf('balances for gross=%s default=%s bank=%s', $gross, $default, $bank),
        $split['gross'],
        $sum
    );
}

echo "\nisSplitBalanced()\n";

assertSame_('accepts an exact split',        true,  isSplitBalanced(100000.0, 5000.0, 15000.0, 80000.0));
assertSame_('rejects an over-allocation',    false, isSplitBalanced(100000.0, 5000.0, 15000.0, 80000.01));
assertSame_('rejects an under-allocation',   false, isSplitBalanced(100000.0, 5000.0, 15000.0, 79999.0));
assertSame_('tolerates sub-kobo drift',      true,  isSplitBalanced(100000.0, 5000.0, 15000.0, 80000.001));

echo "\n" . str_repeat('-', 46) . "\n";
echo ($failed === 0 ? "OK" : "FAILED") . ": {$passed} passed, {$failed} failed\n\n";

exit($failed === 0 ? 0 : 1);
