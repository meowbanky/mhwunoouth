<?php
/**
 * Standalone script to send transaction notification SMS to all 
 * processed members for a given period.
 * 
 * Usage (CLI):   php send_notifications_only.php <period_id>
 * Usage (HTTP):  send_notifications_only.php?period_id=<period_id>
 */

header('Content-Type: application/json');
require_once('Connections/hms.php');
require_once('NotificationService.php');
use class\services\NotificationService;

// --- Get period_id from CLI arg or query string ---
if (php_sapi_name() === 'cli') {
    $periodId = $argv[1] ?? null;
} else {
    $periodId = $_GET['period_id'] ?? $_POST['period_id'] ?? null;
}

if (empty($periodId)) {
    echo json_encode(['status' => 'error', 'message' => 'period_id is required. Usage: php send_notifications_only.php <period_id>']);
    exit(1);
}

$results = [
    'status' => 'success',
    'period_id' => $periodId,
    'sent' => 0,
    'skipped' => 0,
    'failed' => 0,
    'errors' => [],
    'skipped_members' => []
];

try {
    // Initialize NotificationService
    $notificationService = new NotificationService($conn);

    // 1. Check SMS balance first
    $estimatedCostPerMember = 5.0;
    $currentBalance = $notificationService->getSMSBalance();
    echo json_encode(['info' => "Current SMS Balance: $currentBalance"]) . "\n";

    // 2. Get all processed members for this period
    $query = "SELECT DISTINCT memberid 
              FROM tlb_mastertransaction 
              WHERE periodid = ? AND completed = 1
              ORDER BY memberid ASC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$periodId]);
    $members = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $totalMembers = count($members);
    echo json_encode(['info' => "Found $totalMembers processed members for period $periodId"]) . "\n";

    if ($totalMembers === 0) {
        $results['status'] = 'error';
        $results['message'] = "No processed members found for period $periodId";
        echo json_encode($results);
        exit;
    }

    // 3. Check if we have enough balance for all members
    $totalEstimatedCost = $totalMembers * $estimatedCostPerMember;
    if ($currentBalance < $totalEstimatedCost) {
        $affordableCount = floor($currentBalance / $estimatedCostPerMember);
        echo json_encode([
            'warning' => "Insufficient balance for all $totalMembers members. Balance: $currentBalance, Estimated cost: $totalEstimatedCost. Can afford ~$affordableCount members."
        ]) . "\n";
    }

    // 4. Send notifications one by one
    foreach ($members as $index => $memberId) {
        $memberNum = $index + 1;

        // Re-check balance before each send
        if ($currentBalance < $estimatedCostPerMember) {
            $results['skipped']++;
            $results['skipped_members'][] = $memberId;
            echo json_encode(['skip' => "[$memberNum/$totalMembers] Member $memberId - Insufficient balance ($currentBalance)"]) . "\n";
            continue;
        }

        try {
            $sent = $notificationService->sendTransactionNotification($memberId, $periodId);
            if ($sent) {
                $results['sent']++;
                $currentBalance -= $estimatedCostPerMember; // Approximate deduction
                echo json_encode(['ok' => "[$memberNum/$totalMembers] Member $memberId - SMS sent. Remaining balance: ~$currentBalance"]) . "\n";
            } else {
                $results['failed']++;
                $results['errors'][] = "Member $memberId: sendTransactionNotification returned false";
                echo json_encode(['fail' => "[$memberNum/$totalMembers] Member $memberId - Failed to send"]) . "\n";
            }
        } catch (Exception $e) {
            $results['failed']++;
            $results['errors'][] = "Member $memberId: " . $e->getMessage();
            echo json_encode(['fail' => "[$memberNum/$totalMembers] Member $memberId - Error: " . $e->getMessage()]) . "\n";
        }

        // Small delay to avoid rate-limiting
        usleep(200000); // 200ms between sends
    }

    // Skip remaining members if balance ran out
    if ($results['skipped'] > 0) {
        $results['message'] = "Completed with {$results['sent']} sent, {$results['skipped']} skipped (insufficient balance), {$results['failed']} failed.";
    } else {
        $results['message'] = "Completed. {$results['sent']} sent, {$results['failed']} failed out of $totalMembers members.";
    }

} catch (Exception $e) {
    $results['status'] = 'error';
    $results['message'] = $e->getMessage();
}

echo "\n" . json_encode($results, JSON_PRETTY_PRINT) . "\n";
?>
