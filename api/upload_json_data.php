<?php
ob_start();

if (session_status() == PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['UserID']) || trim($_SESSION['UserID']) == '') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

require_once(__DIR__ . '/../Connections/hms.php');

try {
    $input   = file_get_contents('php://input');
    $request = json_decode($input, true);

    if (!$request) throw new Exception('Invalid JSON data');

    if (!isset($request['local_period']) || !isset($request['data']) || !is_array($request['data'])) {
        throw new Exception('Missing required fields: local_period and data');
    }

    $localPeriodId   = (int)$request['local_period'];
    $data            = $request['data'];

    if (empty($data))           throw new Exception('No data to upload');
    if ($localPeriodId <= 0)    throw new Exception('Invalid local period ID');

    // Read fixed monthly contribution from settings
    $settingResult = mysqli_query($hms, "SELECT contribution FROM tbl_settings LIMIT 1");
    if (!$settingResult || mysqli_num_rows($settingResult) == 0) {
        throw new Exception('Failed to load default contribution from settings');
    }
    $defaultContribution = floatval(mysqli_fetch_assoc($settingResult)['contribution']);

    mysqli_begin_transaction($hms);

    $successCount      = 0;
    $errorCount        = 0;
    $notFound          = [];
    $processedIds      = []; // patientid values successfully processed
    $errors            = [];

    foreach ($data as $record) {
        // patientid is INT in this project
        $staffId = (int)$record['staff_id'];
        $amount  = floatval($record['amount']);

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

        // Outstanding loan balance from master transaction
        $stmtLoan = mysqli_prepare($hms,
            "SELECT (SUM(IFNULL(loanAmount,0)) + SUM(IFNULL(interest,0))) - SUM(IFNULL(loanRepayment,0)) AS balance
             FROM tlb_mastertransaction WHERE memberid = ?");
        mysqli_stmt_bind_param($stmtLoan, 'i', $staffId);
        mysqli_stmt_execute($stmtLoan);
        $loanRow    = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtLoan));
        $loanStatus = floatval($loanRow['balance'] ?? 0);
        mysqli_stmt_close($stmtLoan);

        // Split: contribution / loan repayment / special savings
        $contribution   = $defaultContribution;
        $loanRepayment  = 0;
        $specialSavings = 0;

        if ($amount >= $defaultContribution && $loanStatus > 0) {
            $loanRepayment = $amount - $defaultContribution;
        } elseif ($amount >= $defaultContribution) {
            $specialSavings = $amount - $defaultContribution;
        }
        // else: amount < default → all zeros for loan/special

        $processedIds[] = $staffId;

        // Check if a record already exists for this (membersid, period_id)
        $stmtCheck = mysqli_prepare($hms,
            "SELECT COUNT(*) AS cnt FROM tbl_contributions WHERE membersid = ? AND period_id = ?");
        mysqli_stmt_bind_param($stmtCheck, 'ii', $staffId, $localPeriodId);
        mysqli_stmt_execute($stmtCheck);
        mysqli_stmt_bind_result($stmtCheck, $cnt);
        mysqli_stmt_fetch($stmtCheck);
        mysqli_stmt_close($stmtCheck);

        if ($cnt > 0) {
            $stmt = mysqli_prepare($hms,
                "UPDATE tbl_contributions
                 SET contribution = ?, loan = ?, special_savings = ?
                 WHERE membersid = ? AND period_id = ?");
            mysqli_stmt_bind_param($stmt, 'dddii', $contribution, $loanRepayment, $specialSavings, $staffId, $localPeriodId);
        } else {
            $stmt = mysqli_prepare($hms,
                "INSERT INTO tbl_contributions (contribution, loan, special_savings, membersid, period_id)
                 VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'dddii', $contribution, $loanRepayment, $specialSavings, $staffId, $localPeriodId);
        }

        if (mysqli_stmt_execute($stmt)) {
            $successCount++;
        } else {
            $errorCount++;
            $errors[] = "Failed for staff {$staffId}: " . mysqli_stmt_error($stmt);
        }
        mysqli_stmt_close($stmt);
    }

    // Zero out contributions for this period for any active staff NOT in the uploaded list
    if (!empty($processedIds)) {
        $idList = implode(',', $processedIds);
        $stmtZero = mysqli_prepare($hms,
            "UPDATE tbl_contributions
             SET contribution = 0, loan = 0, special_savings = 0
             WHERE period_id = ? AND membersid IN (
                 SELECT patientid FROM tbl_personalinfo WHERE patientid NOT IN ({$idList})
             )");
        mysqli_stmt_bind_param($stmtZero, 'i', $localPeriodId);
        mysqli_stmt_execute($stmtZero);
        mysqli_stmt_close($stmtZero);
    }

    // Commit if success rate is acceptable (> 50%)
    if ($successCount > 0 && $errorCount < ($successCount / 2)) {
        mysqli_commit($hms);

        $notFoundDetails = [];
        $notFoundList    = [];
        foreach ($notFound as $s) {
            $notFoundList[]    = "{$s['staff_id']} ({$s['name']}) - ₦" . number_format($s['amount'], 2);
            $notFoundDetails[] = $s;
        }

        echo json_encode([
            'success' => true,
            'message' => "Upload completed: {$successCount} records processed successfully",
            'details' => "{$successCount} succeeded, {$errorCount} failed",
            'data'    => [
                'total'           => count($data),
                'success'         => $successCount,
                'errors'          => $errorCount,
                'not_found_count' => count($notFound),
                'not_found_list'  => $notFoundDetails,
                'error_messages'  => $errors,
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
