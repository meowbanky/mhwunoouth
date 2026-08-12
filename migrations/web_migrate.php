<?php
/**
 * One-off web-run migration: bank loan deductions.
 *
 * The hosting account has SFTP but no shell, so this is uploaded to the app
 * root and run over HTTPS, then deleted. It is NOT part of the application.
 *
 *   ?token=<TOKEN>&action=check   read-only; reports current schema
 *   ?token=<TOKEN>&action=apply   backs up tbl_contributions, then migrates
 *
 * Guards:
 *   - a random token, required on every request
 *   - idempotent: each step checks whether it has already been done
 *   - refuses to migrate unless the backup copy matches the source row count
 *
 * DELETE THIS FILE FROM THE SERVER once the migration has been verified.
 */

declare(strict_types=1);

const MIGRATION_TOKEN = 'fe3d346194c728b88439dc9ded387dd0';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$token = $_GET['token'] ?? '';
if (!hash_equals(MIGRATION_TOKEN, (string)$token)) {
    http_response_code(403);
    exit("403 Forbidden\n");
}

$action = $_GET['action'] ?? 'check';
if (!in_array($action, ['check', 'apply'], true)) {
    http_response_code(400);
    exit("400 Bad Request: action must be 'check' or 'apply'\n");
}

// Works whether this file sits in the app root or in migrations/ under it.
$env      = parseEnv(__DIR__ . '/.env') ?: parseEnv(__DIR__ . '/../.env');
$host     = $env['DB_HOST']     ?? 'localhost';
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

if ($database === '' || $username === '') {
    http_response_code(500);
    exit("ERROR: DB_DATABASE / DB_USERNAME missing from .env\n");
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$database};charset=utf8",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    exit("ERROR: could not connect - " . $e->getMessage() . "\n");
}

echo "MHWUN bank loan migration\n";
echo "action   : {$action}\n";
echo "database : {$database}\n";
echo "server   : " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
echo str_repeat('-', 60) . "\n\n";

try {
    $hasBankLoan = columnExists($pdo, $database, 'tbl_contributions', 'bank_loan');
    $hasGross    = columnExists($pdo, $database, 'tbl_contributions', 'gross_deduction');
    $hasTable    = tableExists($pdo, $database, 'tbl_bank_loans');
    $rowCount    = (int)$pdo->query('SELECT COUNT(*) FROM tbl_contributions')->fetchColumn();

    echo "CURRENT STATE\n";
    echo "  tbl_contributions rows           : " . number_format($rowCount) . "\n";
    echo "  tbl_contributions.bank_loan      : " . ($hasBankLoan ? 'present' : 'MISSING') . "\n";
    echo "  tbl_contributions.gross_deduction: " . ($hasGross    ? 'present' : 'MISSING') . "\n";
    echo "  tbl_bank_loans table             : " . ($hasTable    ? 'present' : 'MISSING') . "\n\n";

    if ($hasBankLoan && $hasGross && $hasTable) {
        echo "Migration already applied - nothing to do.\n\n";
        report($pdo);
        exit(0);
    }

    if ($action === 'check') {
        echo "Check only - nothing written.\n";
        echo "Re-run with &action=apply to migrate.\n";
        exit(0);
    }

    // --- Back up before any DDL --------------------------------------------
    $backup = 'tbl_contributions_bak_' . date('Ymd_His');
    echo "BACKUP\n";
    $pdo->exec("CREATE TABLE `{$backup}` AS SELECT * FROM tbl_contributions");
    $backedUp = (int)$pdo->query("SELECT COUNT(*) FROM `{$backup}`")->fetchColumn();

    if ($backedUp !== $rowCount) {
        http_response_code(500);
        exit("  ERROR: backup has {$backedUp} rows, source has {$rowCount}. Stopped before any schema change.\n");
    }
    echo "  {$backup}: " . number_format($backedUp) . " rows\n\n";

    // --- Apply --------------------------------------------------------------
    echo "APPLYING\n";

    if (!$hasBankLoan) {
        $pdo->exec("ALTER TABLE tbl_contributions
                    ADD COLUMN bank_loan DECIMAL(12,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Untracked bank loan repayment carved out of the salary deduction'");
        echo "  + tbl_contributions.bank_loan\n";
    }

    if (!$hasGross) {
        $pdo->exec("ALTER TABLE tbl_contributions
                    ADD COLUMN gross_deduction DECIMAL(12,2) NOT NULL DEFAULT 0.00
                    COMMENT 'Total deducted from salary; equals contribution + loan + bank_loan'");
        echo "  + tbl_contributions.gross_deduction\n";

        // Backfill only alongside the column's creation, so a repeat run can
        // never overwrite gross figures the importer has since written.
        $filled = $pdo->exec("UPDATE tbl_contributions SET gross_deduction = contribution + loan");
        echo "  + backfilled gross_deduction on " . number_format((int)$filled) . " rows\n";
    }

    if (!$hasTable) {
        $pdo->exec("CREATE TABLE tbl_bank_loans (
                        membersid  INT           NOT NULL,
                        amount     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                        note       VARCHAR(255)      NULL,
                        updated_by VARCHAR(50)       NULL,
                        updated_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        PRIMARY KEY (membersid)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        echo "  + tbl_bank_loans\n";
    }

    echo "\nDone. Backup retained as {$backup}\n\n";
    report($pdo);

    echo "\n*** DELETE THIS FILE FROM THE SERVER NOW ***\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "\nERROR: " . $e->getMessage() . "\n";
    echo "No further statements were run.\n";
}

// ---------------------------------------------------------------------------

function report(PDO $pdo): void
{
    $unbalanced = (int)$pdo->query(
        "SELECT COUNT(*) FROM tbl_contributions
          WHERE ABS(gross_deduction - (contribution + loan + bank_loan)) > 0.005"
    )->fetchColumn();

    $totals = $pdo->query(
        "SELECT IFNULL(SUM(contribution), 0)    AS contributions,
                IFNULL(SUM(loan), 0)            AS union_loans,
                IFNULL(SUM(bank_loan), 0)       AS bank_loans,
                IFNULL(SUM(gross_deduction), 0) AS gross
           FROM tbl_contributions"
    )->fetch(PDO::FETCH_ASSOC);

    echo "VERIFICATION\n";
    echo "  unbalanced rows     : {$unbalanced}" . ($unbalanced === 0 ? '   OK' : '   <-- INVESTIGATE') . "\n";
    echo "  total contributions : " . number_format((float)$totals['contributions'], 2) . "\n";
    echo "  total union loans   : " . number_format((float)$totals['union_loans'],   2) . "\n";
    echo "  total bank loans    : " . number_format((float)$totals['bank_loans'],    2) . "\n";
    echo "  total gross         : " . number_format((float)$totals['gross'],         2) . "\n";
}

function columnExists(PDO $pdo, string $schema, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$schema, $table, $column]);

    return $stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $schema, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?"
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
