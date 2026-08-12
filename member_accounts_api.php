<?php
/**
 * Admin management of member portal accounts.
 *
 * Members cannot change their own email address — that is deliberate, since the
 * address is their username and the reset channel. This is where the office
 * does it, along with suspending, unlocking and removing accounts.
 */

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['UserID'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once('Connections/hms.php');

const ACCOUNTS_PER_PAGE = 15;

$action = $_POST['action'] ?? $_GET['action'] ?? '';

/** Who is making this change, for the audit trail on tbl_member_auth. */
function adminActor(): string
{
    return substr((string)($_SESSION['Username'] ?? $_SESSION['UserID'] ?? 'admin'), 0, 50);
}

try {
    if ($action === 'fetch_accounts') {
        $page   = max(1, (int)($_POST['page'] ?? 1));
        $search = trim($_POST['search'] ?? '');
        $filter = $_POST['filter'] ?? 'all';   // all | registered | unregistered | locked | suspended
        $offset = ($page - 1) * ACCOUNTS_PER_PAGE;

        $where  = ["p.Status = 'Active'"];
        $params = [];

        if ($search !== '') {
            $where[] = "(p.Fname LIKE :s OR p.Lname LIKE :s OR p.Mname LIKE :s OR p.patientid LIKE :s OR a.email LIKE :s)";
            $params[':s'] = "%$search%";
        }

        switch ($filter) {
            case 'registered':   $where[] = "a.membersid IS NOT NULL"; break;
            case 'unregistered': $where[] = "a.membersid IS NULL";     break;
            case 'locked':       $where[] = "a.locked_until IS NOT NULL AND a.locked_until > NOW()"; break;
            case 'suspended':    $where[] = "a.status = 'suspended'";  break;
        }

        $whereSql = implode(' AND ', $where);

        $countStmt = $conn->prepare(
            "SELECT COUNT(*)
               FROM tbl_personalinfo p
               LEFT JOIN tbl_member_auth a ON a.membersid = p.patientid
              WHERE $whereSql"
        );
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $conn->prepare(
            "SELECT p.patientid,
                    CONCAT(p.Lname, ', ', p.Fname, ' ', IFNULL(p.Mname, '')) AS fullname,
                    p.Dept,
                    a.email,
                    a.status,
                    a.last_login_at,
                    a.failed_attempts,
                    a.locked_until,
                    a.created_at,
                    (a.locked_until IS NOT NULL AND a.locked_until > NOW()) AS is_locked
               FROM tbl_personalinfo p
               LEFT JOIN tbl_member_auth a ON a.membersid = p.patientid
              WHERE $whereSql
              ORDER BY (a.membersid IS NULL), p.Lname, p.Fname
              LIMIT " . ACCOUNTS_PER_PAGE . " OFFSET $offset"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as &$row) {
            $row['is_registered'] = $row['email'] !== null;
            $row['is_locked']     = (bool)$row['is_locked'];
        }
        unset($row);

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'rows'       => $rows,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages'  => max(1, (int)ceil($total / ACCOUNTS_PER_PAGE)),
                    'total'        => $total,
                ],
            ],
        ]);

    } elseif ($action === 'stats') {
        $stmt = $conn->query(
            "SELECT
                (SELECT COUNT(*) FROM tbl_personalinfo WHERE Status = 'Active')                AS active_members,
                (SELECT COUNT(*) FROM tbl_member_auth)                                          AS registered,
                (SELECT COUNT(*) FROM tbl_member_auth WHERE status = 'suspended')               AS suspended,
                (SELECT COUNT(*) FROM tbl_member_auth
                  WHERE locked_until IS NOT NULL AND locked_until > NOW())                      AS locked"
        );

        echo json_encode(['status' => 'success', 'data' => $stmt->fetch(PDO::FETCH_ASSOC)]);

    } elseif ($action === 'update_email') {
        $memberId = trim($_POST['member_id'] ?? '');
        $email    = strtolower(trim($_POST['email'] ?? ''));

        if ($memberId === '')                                  throw new Exception('Member ID required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))         throw new Exception('Please enter a valid email address');

        $taken = $conn->prepare("SELECT membersid FROM tbl_member_auth WHERE email = ? AND membersid <> ?");
        $taken->execute([$email, $memberId]);
        if ($other = $taken->fetchColumn()) {
            throw new Exception("That email is already used by member $other");
        }

        $stmt = $conn->prepare(
            "UPDATE tbl_member_auth SET email = :email WHERE membersid = :id"
        );
        $stmt->execute([':email' => $email, ':id' => $memberId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception('That member has no portal account yet');
        }

        // Any code already in flight was sent to the old address.
        $conn->prepare("UPDATE tbl_member_otp SET consumed_at = NOW() WHERE membersid = ? AND consumed_at IS NULL")
             ->execute([$memberId]);

        echo json_encode([
            'status'  => 'success',
            'message' => "Email updated to $email. The member now signs in with this address.",
        ]);

    } elseif ($action === 'set_status') {
        $memberId = trim($_POST['member_id'] ?? '');
        $status   = $_POST['new_status'] ?? '';

        if (!in_array($status, ['active', 'suspended'], true)) throw new Exception('Invalid status');

        $stmt = $conn->prepare("UPDATE tbl_member_auth SET status = ? WHERE membersid = ?");
        $stmt->execute([$status, $memberId]);

        if ($stmt->rowCount() === 0) throw new Exception('That member has no portal account yet');

        echo json_encode([
            'status'  => 'success',
            'message' => $status === 'suspended'
                ? 'Account suspended. The member can no longer sign in.'
                : 'Account reactivated.',
        ]);

    } elseif ($action === 'unlock') {
        $memberId = trim($_POST['member_id'] ?? '');

        $stmt = $conn->prepare(
            "UPDATE tbl_member_auth SET failed_attempts = 0, locked_until = NULL WHERE membersid = ?"
        );
        $stmt->execute([$memberId]);

        if ($stmt->rowCount() === 0) throw new Exception('That member has no portal account yet');

        echo json_encode(['status' => 'success', 'message' => 'Account unlocked. The member can sign in again.']);

    } elseif ($action === 'delete_account') {
        // Removes credentials only. Nothing financial is touched, and the
        // member can register again from scratch.
        $memberId = trim($_POST['member_id'] ?? '');
        if ($memberId === '') throw new Exception('Member ID required');

        $conn->beginTransaction();
        $conn->prepare("DELETE FROM tbl_member_otp  WHERE membersid = ?")->execute([$memberId]);
        $stmt = $conn->prepare("DELETE FROM tbl_member_auth WHERE membersid = ?");
        $stmt->execute([$memberId]);
        $conn->commit();

        if ($stmt->rowCount() === 0) throw new Exception('That member has no portal account yet');

        echo json_encode([
            'status'  => 'success',
            'message' => 'Portal account removed. The member may register again. No financial records were affected.',
        ]);

    } else {
        throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    if (isset($conn) && $conn instanceof PDO && $conn->inTransaction()) {
        $conn->rollBack();
    }
    error_log('member_accounts_api [' . $action . ']: ' . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
