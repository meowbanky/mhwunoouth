<?php
/**
 * Portal bootstrap: session, database, shared helpers.
 *
 * The portal is a read-only view of a member's own records. It deliberately
 * uses its own session name and its own cookie path so a member session can
 * never be confused with an admin session, in either direction.
 */

declare(strict_types=1);

const PORTAL_SESSION_NAME   = 'MHWUNMEMBER';
const OTP_LENGTH            = 6;
const OTP_TTL_MINUTES       = 10;
const OTP_MAX_ATTEMPTS      = 5;
const LOGIN_MAX_FAILURES    = 5;
const LOGIN_LOCKOUT_MINUTES = 15;
const PASSWORD_MIN_LENGTH   = 8;

/** Throttle budgets: action => [max attempts, window in minutes]. */
const THROTTLE_LIMITS = [
    'search'   => [30, 10],
    'verify'   => [10, 15],  // name + payslip ID guesses
    'otp_send' => [5,  15],
    'otp_check'=> [10, 15],
    'login'    => [10, 15],
];

if (session_status() === PHP_SESSION_NONE) {
    session_name(PORTAL_SESSION_NAME);
    session_set_cookie_params([
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => !empty($_SERVER['HTTPS']),
    ]);
    session_start();
}

require_once __DIR__ . '/../../Connections/hms.php';   // $conn (PDO), $hms (mysqli)

/** Escape for HTML output. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/**
 * Read a setting from .env, falling back to a default.
 *
 * Lives here rather than in mailer.php so pages that only need to *name* the
 * sender do not have to pull in PHPMailer to do it.
 */
function portalConfig(string $key, string $default = ''): string
{
    static $env = null;

    if ($env === null) {
        $env  = [];
        $path = __DIR__ . '/../../.env';
        if (is_readable($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $env[trim($name)] = trim($value, " \t\"'");
            }
        }
    }

    return $env[$key] ?? $default;
}

/** Format a naira amount for display. */
function naira($amount): string
{
    return '₦' . number_format((float)$amount, 2);
}

/** Caller's IP, for throttling. Best effort behind a proxy. */
function clientIp(): string
{
    return substr((string)($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/**
 * Has this IP exhausted its budget for an action?
 *
 * Records the attempt as a side effect, so calling it *is* consuming one.
 */
function throttleExceeded(PDO $conn, string $action): bool
{
    [$limit, $windowMinutes] = THROTTLE_LIMITS[$action] ?? [20, 15];
    $ip = clientIp();

    $stmt = $conn->prepare(
        "SELECT COUNT(*) FROM tbl_member_throttle
          WHERE ip = :ip AND action = :action
            AND created_at > DATE_SUB(NOW(), INTERVAL :mins MINUTE)"
    );
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':action', $action);
    $stmt->bindValue(':mins', $windowMinutes, PDO::PARAM_INT);
    $stmt->execute();

    $used = (int)$stmt->fetchColumn();

    $conn->prepare("INSERT INTO tbl_member_throttle (ip, action) VALUES (?, ?)")
         ->execute([$ip, $action]);

    // Opportunistic cleanup so the table cannot grow without bound.
    if (random_int(1, 50) === 1) {
        $conn->exec("DELETE FROM tbl_member_throttle WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)");
    }

    return $used >= $limit;
}

/** Emit a JSON response and stop. */
function jsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function jsonFail(string $message, int $status = 400): void
{
    jsonOut(['status' => 'error', 'message' => $message], $status);
}

function jsonOk(array $data = [], string $message = ''): void
{
    jsonOut(['status' => 'success', 'message' => $message, 'data' => $data]);
}

/** CSRF token for this session, created on first use. */
function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}

function csrfValid(?string $token): bool
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/**
 * Mask an email for display: b***a@gmail.com.
 *
 * Used so a member can confirm which address a code went to without the
 * address itself being disclosed to whoever is looking at the screen.
 */
function maskEmail(string $email): string
{
    [$user, $domain] = array_pad(explode('@', $email, 2), 2, '');
    if ($domain === '') {
        return '***';
    }

    $visible = mb_substr($user, 0, 1) . str_repeat('*', max(1, mb_strlen($user) - 2)) . mb_substr($user, -1);

    return $visible . '@' . $domain;
}
