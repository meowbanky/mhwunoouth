<?php
/**
 * One-off web-run migration: member portal auth tables.
 *
 * The hosting account has SFTP but no shell, so this is uploaded to the app
 * root, run over HTTPS, then deleted. It is NOT part of the application.
 *
 *   ?token=<TOKEN>&action=check   read-only
 *   ?token=<TOKEN>&action=apply   create the three portal tables
 *
 * Creates only new tables — nothing existing is read or altered, so there is
 * nothing to back up and nothing to roll back beyond dropping them.
 *
 * DELETE THIS FILE FROM THE SERVER once verified.
 */

declare(strict_types=1);

const MIGRATION_TOKEN = '79080509d3f487533e5af1b279d32118';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(MIGRATION_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("403 Forbidden\n");
}

$action = $_GET['action'] ?? 'check';
if (!in_array($action, ['check', 'apply'], true)) {
    http_response_code(400);
    exit("400 Bad Request\n");
}

$env      = parseEnv(__DIR__ . '/.env') ?: parseEnv(__DIR__ . '/../.env');
$database = $env['DB_DATABASE'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host=" . ($env['DB_HOST'] ?? 'localhost') . ";dbname={$database};charset=utf8",
        $env['DB_USERNAME'] ?? '',
        $env['DB_PASSWORD'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit("ERROR: could not connect - " . $e->getMessage() . "\n");
}

echo "MHWUN member portal migration\n";
echo "action   : {$action}\n";
echo "database : {$database}\n";
echo str_repeat('-', 60) . "\n\n";

$tables = ['tbl_member_auth', 'tbl_member_otp', 'tbl_member_throttle'];

echo "CURRENT STATE\n";
$missing = [];
foreach ($tables as $t) {
    $present = tableExists($pdo, $database, $t);
    if (!$present) {
        $missing[] = $t;
    }
    printf("  %-22s : %s\n", $t, $present ? 'present' : 'MISSING');
}
echo "\n";

if (!$missing) {
    echo "Migration already applied - nothing to do.\n";
    report($pdo);
    exit(0);
}

if ($action === 'check') {
    echo "Check only - nothing written. Re-run with &action=apply.\n";
    exit(0);
}

try {
    echo "APPLYING\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_member_auth (
        membersid       INT          NOT NULL,
        email           VARCHAR(190) NOT NULL,
        password_hash   VARCHAR(255) NOT NULL,
        status          ENUM('active','suspended') NOT NULL DEFAULT 'active',
        failed_attempts INT          NOT NULL DEFAULT 0,
        locked_until    DATETIME         NULL,
        last_login_at   DATETIME         NULL,
        created_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (membersid),
        UNIQUE KEY uniq_member_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  + tbl_member_auth\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_member_otp (
        id          INT          NOT NULL AUTO_INCREMENT,
        membersid   INT          NOT NULL,
        purpose     ENUM('register','reset') NOT NULL,
        email       VARCHAR(190) NOT NULL,
        otp_hash    VARCHAR(255) NOT NULL,
        expires_at  DATETIME     NOT NULL,
        attempts    INT          NOT NULL DEFAULT 0,
        consumed_at DATETIME         NULL,
        ip          VARCHAR(45)      NULL,
        created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_member_purpose (membersid, purpose),
        KEY idx_expiry (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  + tbl_member_otp\n";

    $pdo->exec("CREATE TABLE IF NOT EXISTS tbl_member_throttle (
        id         INT         NOT NULL AUTO_INCREMENT,
        ip         VARCHAR(45) NOT NULL,
        action     VARCHAR(40) NOT NULL,
        created_at TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_ip_action_time (ip, action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    echo "  + tbl_member_throttle\n\n";

    echo "Done.\n\n";
    report($pdo);
    echo "\n*** DELETE THIS FILE FROM THE SERVER NOW ***\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "\nERROR: " . $e->getMessage() . "\n";
}

// ---------------------------------------------------------------------------

function report(PDO $pdo): void
{
    echo "VERIFICATION\n";
    foreach (['tbl_member_auth', 'tbl_member_otp', 'tbl_member_throttle'] as $t) {
        $n = (int)$pdo->query("SELECT COUNT(*) FROM `{$t}`")->fetchColumn();
        printf("  %-22s : %d row(s)\n", $t, $n);
    }

    $eligible = (int)$pdo->query("SELECT COUNT(*) FROM tbl_personalinfo WHERE Status = 'Active'")->fetchColumn();
    $withEmail = (int)$pdo->query(
        "SELECT COUNT(*) FROM tbl_personalinfo WHERE Status = 'Active' AND EmailAddress <> '' AND EmailAddress IS NOT NULL"
    )->fetchColumn();

    echo "\n  active members eligible to register : {$eligible}\n";
    echo "  ...of whom have an email on file    : {$withEmail}\n";
}

function tableExists(PDO $pdo, string $schema, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
    );
    $stmt->execute([$schema, $table]);

    return $stmt->fetchColumn() > 0;
}

function parseEnv(string $path): array
{
    if (!file_exists($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $values[trim($name)] = trim($value, " \t\"'");
    }

    return $values;
}
