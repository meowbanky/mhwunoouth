<?php
/**
 * Applies migrations/2026_08_12_bank_loan_deductions.sql to the live database.
 *
 * Expects an SSH tunnel to the server's MySQL port, e.g.
 *     ssh -f -N -L 3307:127.0.0.1:3306 emmaggic@147.124.214.13
 *
 * Usage:
 *     php migrations/run_bank_loan_migration.php --check    # inspect only, no writes
 *     php migrations/run_bank_loan_migration.php --apply    # back up, then migrate
 *
 * Safe to run more than once: every step checks whether it has already been
 * done, so a repeat run reports "already applied" instead of erroring or
 * double-backfilling.
 */

declare(strict_types=1);

const TUNNEL_HOST = '127.0.0.1';
const TUNNEL_PORT = 3307;

$mode = $argv[1] ?? '--check';
if (!in_array($mode, ['--check', '--apply'], true)) {
    fwrite(STDERR, "Usage: php migrations/run_bank_loan_migration.php [--check|--apply]\n");
    exit(2);
}

$env = parseEnv(__DIR__ . '/../.env');
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

if ($database === '' || $username === '') {
    fwrite(STDERR, "ERROR: DB_DATABASE / DB_USERNAME missing from .env\n");
    exit(1);
}

echo "Connecting to {$database} via " . TUNNEL_HOST . ':' . TUNNEL_PORT . " ...\n";

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8', TUNNEL_HOST, TUNNEL_PORT, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 15]
    );
} catch (PDOException $e) {
    fwrite(STDERR, "ERROR: could not connect — " . $e->getMessage() . "\n");
    fwrite(STDERR, "Is the SSH tunnel up? Check with: pgrep -fl '3307:127.0.0.1:3306'\n");
    exit(1);
}

echo "Connected. Server: " . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n\n";

// --- Inspect current state -------------------------------------------------
$hasBankLoan = columnExists($pdo, $database, 'tbl_contributions', 'bank_loan');
$hasGross    = columnExists($pdo, $database, 'tbl_contributions', 'gross_deduction');
$hasTable    = tableExists($pdo, $database, 'tbl_bank_loans');
$rowCount    = (int)$pdo->query('SELECT COUNT(*) FROM tbl_contributions')->fetchColumn();

echo "Current state\n";
echo "  tbl_contributions rows      : " . number_format($rowCount) . "\n";
echo "  tbl_contributions.bank_loan : " . ($hasBankLoan ? 'present' : 'MISSING') . "\n";
echo "  tbl_contributions.gross_ded : " . ($hasGross    ? 'present' : 'MISSING') . "\n";
echo "  tbl_bank_loans table        : " . ($hasTable    ? 'present' : 'MISSING') . "\n\n";

if ($hasBankLoan && $hasGross && $hasTable) {
    echo "Migration already applied.\n\n";
    report($pdo);
    exit(0);
}

if ($mode === '--check') {
    echo "Check only — nothing written. Re-run with --apply to migrate.\n";
    exit(0);
}

// --- Back up before any DDL ------------------------------------------------
$backup = 'tbl_contributions_bak_' . date('Ymd_His');
echo "Backing up tbl_contributions to {$backup} ...\n";
$pdo->exec("CREATE TABLE `{$backup}` AS SELECT * FROM tbl_contributions");
$backedUp = (int)$pdo->query("SELECT COUNT(*) FROM `{$backup}`")->fetchColumn();

if ($backedUp !== $rowCount) {
    fwrite(STDERR, "ERROR: backup has {$backedUp} rows but source has {$rowCount}. Stopping.\n");
    exit(1);
}
echo "  backed up " . number_format($backedUp) . " rows\n\n";

// --- Apply -----------------------------------------------------------------
echo "Applying migration ...\n";

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

    // Backfill only alongside the column's creation, so a repeat run can never
    // overwrite gross figures that the importer has since written.
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

echo "\nMigration applied. Backup retained as {$backup}\n\n";
report($pdo);

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

    echo "Verification\n";
    echo "  unbalanced rows   : {$unbalanced}" . ($unbalanced === 0 ? '  OK' : '  <-- INVESTIGATE') . "\n";
    echo "  total contributions: " . number_format((float)$totals['contributions'], 2) . "\n";
    echo "  total union loans  : " . number_format((float)$totals['union_loans'],   2) . "\n";
    echo "  total bank loans   : " . number_format((float)$totals['bank_loans'],    2) . "\n";
    echo "  total gross        : " . number_format((float)$totals['gross'],         2) . "\n";
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
