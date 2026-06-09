<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once('Connections/hms.php');

$action = $_POST['action'] ?? '';

$uploadDir = __DIR__ . '/uploads/tellers/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

try {
    // ------------------------------------------------------------------
    // Add Deposit
    // ------------------------------------------------------------------
    if ($action === 'add_deposit') {
        $memberId = trim($_POST['coopid'] ?? '');
        $periodId = trim($_POST['period'] ?? '');
        $amount   = str_replace(',', '', trim($_POST['amount'] ?? ''));

        if (empty($memberId) || empty($periodId) || !is_numeric($amount) || floatval($amount) <= 0) {
            throw new Exception("Member ID, Period, and a valid Amount are required.");
        }

        // Check member exists
        $stmtCheck = $conn->prepare("SELECT patientid FROM tbl_personalinfo WHERE patientid = :id LIMIT 1");
        $stmtCheck->execute([':id' => $memberId]);
        if (!$stmtCheck->fetch()) {
            throw new Exception("Member not found.");
        }

        // Check for duplicate deposit (same member + period)
        $stmtDup = $conn->prepare("SELECT teller_id FROM tbl_teller WHERE memberid = :mid AND periodid = :pid LIMIT 1");
        $stmtDup->execute([':mid' => $memberId, ':pid' => $periodId]);
        if ($stmtDup->fetch()) {
            throw new Exception("A deposit for this member in the selected period already exists.");
        }

        // Handle file upload
        $tellerFilename = '';
        if (!empty($_FILES['teller_file']['name'])) {
            $file     = $_FILES['teller_file'];
            $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

            if (!in_array($ext, $allowed)) {
                throw new Exception("Invalid file type. Allowed: JPG, PNG, GIF, PDF.");
            }
            if ($file['size'] > 5 * 1024 * 1024) {
                throw new Exception("File too large. Maximum 5MB.");
            }
            if ($file['error'] !== UPLOAD_ERR_OK) {
                throw new Exception("File upload failed.");
            }

            $tellerFilename = 'teller_' . $memberId . '_' . $periodId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $tellerFilename)) {
                throw new Exception("Could not save uploaded file.");
            }
        }

        $stmt = $conn->prepare(
            "INSERT INTO tbl_teller (memberid, periodid, teller_upload, repayment_bank)
             VALUES (:memberid, :periodid, :teller_upload, :repayment_bank)"
        );
        $stmt->execute([
            ':memberid'      => $memberId,
            ':periodid'      => $periodId,
            ':teller_upload' => $tellerFilename,
            ':repayment_bank'=> floatval($amount),
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Deposit recorded successfully.']);

    // ------------------------------------------------------------------
    // Delete Deposit
    // ------------------------------------------------------------------
    } elseif ($action === 'delete_deposit') {
        $tellerId = intval($_POST['teller_id'] ?? 0);
        if ($tellerId <= 0) throw new Exception("Invalid deposit ID.");

        // Fetch file before deleting
        $stmtFetch = $conn->prepare("SELECT teller_upload FROM tbl_teller WHERE teller_id = :id");
        $stmtFetch->execute([':id' => $tellerId]);
        $row = $stmtFetch->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new Exception("Deposit record not found.");

        // Delete file if exists
        if (!empty($row['teller_upload'])) {
            $filePath = $uploadDir . $row['teller_upload'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $stmtDel = $conn->prepare("DELETE FROM tbl_teller WHERE teller_id = :id");
        $stmtDel->execute([':id' => $tellerId]);

        echo json_encode(['status' => 'success', 'message' => 'Deposit deleted.']);

    // ------------------------------------------------------------------
    // Edit Deposit
    // ------------------------------------------------------------------
    } elseif ($action === 'edit_deposit') {
        $tellerId = intval($_POST['teller_id'] ?? 0);
        $amount   = str_replace(',', '', trim($_POST['amount'] ?? ''));

        if ($tellerId <= 0) throw new Exception("Invalid deposit ID.");
        if (!is_numeric($amount) || floatval($amount) <= 0) throw new Exception("Enter a valid amount.");

        // Fetch current record
        $stmtFetch = $conn->prepare("SELECT teller_upload FROM tbl_teller WHERE teller_id = :id");
        $stmtFetch->execute([':id' => $tellerId]);
        $row = $stmtFetch->fetch(PDO::FETCH_ASSOC);
        if (!$row) throw new Exception("Deposit record not found.");

        $tellerFilename = $row['teller_upload'];

        // Handle new file upload (optional)
        if (!empty($_FILES['teller_file']['name'])) {
            $file    = $_FILES['teller_file'];
            $ext     = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'pdf'];

            if (!in_array($ext, $allowed)) throw new Exception("Invalid file type. Allowed: JPG, PNG, GIF, PDF.");
            if ($file['size'] > 5 * 1024 * 1024) throw new Exception("File too large. Maximum 5MB.");
            if ($file['error'] !== UPLOAD_ERR_OK) throw new Exception("File upload failed.");

            // Delete old file
            if (!empty($tellerFilename) && file_exists($uploadDir . $tellerFilename)) {
                unlink($uploadDir . $tellerFilename);
            }

            $tellerFilename = 'teller_edit_' . $tellerId . '_' . time() . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $tellerFilename)) {
                throw new Exception("Could not save uploaded file.");
            }
        }

        $stmtUp = $conn->prepare("UPDATE tbl_teller SET repayment_bank = :amount, teller_upload = :teller WHERE teller_id = :id");
        $stmtUp->execute([':amount' => floatval($amount), ':teller' => $tellerFilename, ':id' => $tellerId]);

        echo json_encode(['status' => 'success', 'message' => 'Deposit updated successfully.']);

    } else {
        throw new Exception("Invalid action.");
    }

} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>
