<?php
ob_start();

if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['UserID']) || trim($_SESSION['UserID']) == '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

try {
    if (!file_exists(__DIR__ . '/../Connections/hms.php')) {
        throw new Exception('hms.php not found at: ' . __DIR__ . '/../Connections/');
    }
    require_once(__DIR__ . '/../Connections/hms.php');
    require_once(__DIR__ . '/../logic/deduction_split.php');

    $input   = file_get_contents('php://input');
    $request = json_decode($input, true);

    if (!$request) throw new Exception('Invalid JSON data');

    if (!isset($request['local_period']) || !isset($request['data']) || !is_array($request['data'])) {
        throw new Exception('Missing required fields: local_period and data');
    }

    $localPeriodId = (int)$request['local_period'];
    $data          = $request['data'];

    if (empty($data))        throw new Exception('No data to upload');
    if ($localPeriodId <= 0) throw new Exception('Invalid local period ID');

    // Global fallback from settings
    $globalDefault = 0;
    $settingResult = mysqli_query($hms, "SELECT contribution FROM tbl_settings LIMIT 1");
    if ($settingResult && mysqli_num_rows($settingResult) > 0) {
        $globalDefault = floatval(mysqli_fetch_assoc($settingResult)['contribution']);
    }

    // Per-member defaults (constant, no period filter); falls back to globalDefault if not set
    $defaultMap = [];
    $dcResult = mysqli_query($hms, "SELECT membersid, contribution FROM tbl_default_contributions");
    while ($dcRow = mysqli_fetch_assoc($dcResult)) {
        $defaultMap[(int)$dcRow['membersid']] = floatval($dcRow['contribution']);
    }

    // Standing bank loan deductions (constant, no period filter). These are debts
    // the union does not track; the money rides in on the same payroll deduction
    // and must be set aside before the remainder is treated as loan repayment.
    $bankLoanMap = [];
    $blResult = mysqli_query($hms, "SELECT membersid, amount FROM tbl_bank_loans WHERE amount > 0");
    if ($blResult) {
        while ($blRow = mysqli_fetch_assoc($blResult)) {
            $bankLoanMap[(int)$blRow['membersid']] = floatval($blRow['amount']);
        }
    }

    mysqli_begin_transaction($hms);

    $successCount = 0;
    $errorCount   = 0;
    $notFound     = [];
    $processedIds = [];
    $errors       = [];
    $shortfalls   = [];
    $bankLoanTotal = 0.0;

    foreach ($data as $record) {
        $staffId = (int)($record['staff_id'] ?? 0);
        $amount  = floatval($record['amount'] ?? 0);

        if ($staffId <= 0) continue;

        // Verify staff exists in tbl_personalinfo
        $stmtStaff = mysqli_prepare($hms, "SELECT patientid FROM tbl_personalinfo WHERE patientid = ?");
        mysqli_stmt_bind_param($stmtStaff, 'i', $staffId);
        mysqli_stmt_execute($stmtStaff);
        $staffFound = mysqli_num_rows(mysqli_stmt_get_result($stmtStaff)) > 0;
        mysqli_stmt_close($stmtStaff);

        if (!$staffFound) {
            $notFound[] = [
                'staff_id' => $staffId,
                'name'     => $record['name'] ?? 'Unknown',
                'amount'   => $amount,
            ];
            $errorCount++;
            continue;
        }

        // Member's contribution: individual default if set, otherwise global setting
        $memberDefault = $defaultMap[$staffId] ?? $globalDefault;
        $memberBankLoan = $bankLoanMap[$staffId] ?? 0.0;

        // Split: contribution first, then the untracked bank loan, and only what
        // remains is repayment against the union's own loan.
        $split         = splitSalaryDeduction($amount, $memberDefault, $memberBankLoan);
        $contribution  = $split['contribution'];
        $loanRepayment = $split['loan'];
        $bankLoan      = $split['bank_loan'];
        $grossAmount   = $split['gross'];

        $bankLoanTotal += $bankLoan;

        // Payroll sent less than the member's contribution plus their standing
        // bank loan. We import what actually arrived rather than crediting money
        // that was never deducted, and flag the row so the admin can act on it.
        if ($split['shortfall'] > 0) {
            $shortfalls[] = [
                'staff_id'         => $staffId,
                'name'             => $record['name'] ?? 'Unknown',
                'gross'            => $grossAmount,
                'expected_total'   => round($memberDefault + $memberBankLoan, 2),
                'expected_contrib' => $memberDefault,
                'applied_contrib'  => $contribution,
                'expected_bank'    => $memberBankLoan,
                'applied_bank'     => $bankLoan,
                'shortfall'        => $split['shortfall'],
            ];
        }

        $processedIds[] = $staffId;

        // Upsert into tbl_contributions
        $stmtCheck = mysqli_prepare($hms,
            "SELECT COUNT(*) AS cnt FROM tbl_contributions WHERE membersid = ? AND period_id = ?");
        mysqli_stmt_bind_param($stmtCheck, 'ii', $staffId, $localPeriodId);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_bind_result($stmtCheck, $cnt);
        mysqli_stmt_fetch($stmtCheck);
        mysqli_stmt_close($stmtCheck);

        if ($cnt > 0) {
            $stmt = mysqli_prepare($hms,
                "UPDATE tbl_contributions SET contribution = ?, loan = ?, bank_loan = ?, gross_deduction = ?
                 WHERE membersid = ? AND period_id = ?");
            mysqli_stmt_bind_param($stmt, 'ddddii', $contribution, $loanRepayment, $bankLoan, $grossAmount, $staffId, $localPeriodId);
        } else {
            $stmt = mysqli_prepare($hms,
                "INSERT INTO tbl_contributions (contribution, loan, bank_loan, gross_deduction, membersid, period_id)
                 VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'ddddii', $contribution, $loanRepayment, $bankLoan, $grossAmount, $staffId, $localPeriodId);
        }

        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = "Failed for staff {$staffId}: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    // Zero out contributions for this period for any member NOT in the uploaded
    // list. bank_loan and gross_deduction must be zeroed too: a member absent
    // from the payroll file had nothing deducted, so leaving a stale bank loan
    // behind would both overstate the bank's collection and break the period's
    // reconciliation.
    if (!empty($processedIds)) {
        $idList   = implode(',', $processedIds);
        $stmtZero = mysqli_prepare($hms,
            "UPDATE tbl_contributions SET contribution = 0, loan = 0, bank_loan = 0, gross_deduction = 0
             WHERE period_id = ? AND membersid NOT IN ($idList)");
        mysqli_stmt_bind_param($stmtZero, 'i', $localPeriodId);
        mysqli_stmt_execute($stmtZero);
        mysqli_stmt_close($stmtZero);
    }

    if ($successCount > 0 && $errorCount < ($successCount / 2)) {
        mysqli_commit($hms);

        $details = "{$successCount} succeeded, {$errorCount} failed";
        if ($bankLoanTotal > 0) {
            $details .= '. Bank loan set aside: ₦' . number_format($bankLoanTotal, 2);
        }

        echo json_encode([
            'success' => true,
            'message' => "Upload completed: {$successCount} records processed successfully",
            'details' => $details,
            'data'    => [
                'total'            => count($data),
                'success'          => $successCount,
                'errors'           => $errorCount,
                'not_found_count'  => count($notFound),
                'not_found_list'   => $notFound,
                'error_messages'   => $errors,
                'bank_loan_total'  => round($bankLoanTotal, 2),
                'shortfall_count'  => count($shortfalls),
                'shortfall_list'   => $shortfalls,
            ],
        ]);
    } else {
        mysqli_rollback($hms);
        throw new Exception("Upload failed: too many errors ({$errorCount} errors, {$successCount} succeeded). Transaction rolled back.");
    }

} catch (Exception $e) {
    if (isset($hms)) mysqli_rollback($hms);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

ob_end_flush();
