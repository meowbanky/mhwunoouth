<?php
/**
 * Member authentication.
 *
 * The single rule this file exists to enforce: the member ID used for every
 * data query comes from the session and nowhere else. No endpoint accepts a
 * member ID from the request, so a signed-in member cannot read another
 * member's records by editing a parameter.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

/** Is someone signed in? */
function isMemberLoggedIn(): bool
{
    return !empty($_SESSION['member_id']);
}

/**
 * The signed-in member's ID. The only sanctioned source of it.
 *
 * @throws RuntimeException when called without a session, which would be a
 *         programming error rather than an expected state.
 */
function currentMemberId(): string
{
    if (empty($_SESSION['member_id'])) {
        throw new RuntimeException('No member session');
    }

    return (string)$_SESSION['member_id'];
}

function currentMemberName(): string
{
    return (string)($_SESSION['member_name'] ?? '');
}

/** Redirect to the login page unless signed in. For page requests. */
function requireMemberPage(): void
{
    if (!isMemberLoggedIn()) {
        header('Location: index.php');
        exit;
    }
}

/** Return 401 JSON unless signed in. For API requests. */
function requireMemberApi(): void
{
    if (!isMemberLoggedIn()) {
        jsonFail('Your session has expired. Please sign in again.', 401);
    }
}

/** Establish a session for a member. Regenerates the ID to prevent fixation. */
function loginMember(PDO $conn, string $memberId, string $displayName): void
{
    session_regenerate_id(true);

    $_SESSION['member_id']   = $memberId;
    $_SESSION['member_name'] = $displayName;
    $_SESSION['login_at']    = time();

    $conn->prepare(
        "UPDATE tbl_member_auth
            SET last_login_at = NOW(), failed_attempts = 0, locked_until = NULL
          WHERE membersid = ?"
    )->execute([$memberId]);
}

function logoutMember(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }

    session_destroy();
}

/**
 * Look up a member's credential row by email.
 *
 * @return array|null null when no account exists for that address.
 */
function findAuthByEmail(PDO $conn, string $email): ?array
{
    $stmt = $conn->prepare(
        "SELECT a.membersid, a.email, a.password_hash, a.status, a.failed_attempts, a.locked_until,
                p.Fname, p.Lname, p.Mname, p.Status AS member_status
           FROM tbl_member_auth a
           INNER JOIN tbl_personalinfo p ON p.patientid = a.membersid
          WHERE a.email = ?"
    );
    $stmt->execute([$email]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

/** Is this account currently locked out after repeated failures? */
function isLockedOut(array $auth): bool
{
    return !empty($auth['locked_until']) && strtotime((string)$auth['locked_until']) > time();
}

/** Record a failed sign-in, locking the account once the limit is reached. */
function recordLoginFailure(PDO $conn, string $memberId): void
{
    $conn->prepare(
        "UPDATE tbl_member_auth
            SET failed_attempts = failed_attempts + 1,
                locked_until = CASE WHEN failed_attempts + 1 >= :max
                                    THEN DATE_ADD(NOW(), INTERVAL :mins MINUTE)
                                    ELSE locked_until END
          WHERE membersid = :id"
    )->execute([
        ':max'  => LOGIN_MAX_FAILURES,
        ':mins' => LOGIN_LOCKOUT_MINUTES,
        ':id'   => $memberId,
    ]);
}

/**
 * Validate a proposed password.
 *
 * @return string|null an error message, or null when acceptable.
 */
function passwordProblem(string $password, string $confirm): ?string
{
    if (mb_strlen($password) < PASSWORD_MIN_LENGTH) {
        return 'Password must be at least ' . PASSWORD_MIN_LENGTH . ' characters.';
    }

    if ($password !== $confirm) {
        return 'The two passwords do not match.';
    }

    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }

    return null;
}

/** Create or replace a member's credentials. */
function saveMemberPassword(PDO $conn, string $memberId, string $email, string $password): void
{
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $conn->prepare(
        "INSERT INTO tbl_member_auth (membersid, email, password_hash)
         VALUES (:id, :email, :hash)
         ON DUPLICATE KEY UPDATE password_hash = :hash2, failed_attempts = 0, locked_until = NULL"
    )->execute([
        ':id'    => $memberId,
        ':email' => $email,
        ':hash'  => $hash,
        ':hash2' => $hash,
    ]);
}
