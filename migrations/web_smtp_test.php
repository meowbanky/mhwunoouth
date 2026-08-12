<?php
/**
 * One-off SMTP check.
 *
 * Uploaded to the app root, run over HTTPS, then deleted. Confirms the portal
 * can actually deliver mail before members are told to register.
 *
 *   ?token=<TOKEN>&to=you@example.com
 *
 * DELETE THIS FILE FROM THE SERVER once the test passes.
 */

declare(strict_types=1);

const TEST_TOKEN = 'f0a1c7d92b4e83615ad7c0e2f9b48d13';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

if (!hash_equals(TEST_TOKEN, (string)($_GET['token'] ?? ''))) {
    http_response_code(403);
    exit("403 Forbidden\n");
}

$to = (string)($_GET['to'] ?? '');
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    exit("Pass a valid address: &to=you@example.com\n");
}

require_once __DIR__ . '/portal/includes/mailer.php';

echo "MHWUN portal SMTP test\n";
echo str_repeat('-', 60) . "\n\n";

printf("  host     : %s\n", portalConfig('SMTP_HOST', '(not set)'));
printf("  port     : %s\n", portalConfig('SMTP_PORT', '(not set)'));
printf("  security : %s\n", portalConfig('SMTP_SECURE', '(not set)'));
printf("  username : %s\n", portalConfig('SMTP_USERNAME', '(not set)'));
printf("  password : %s\n", portalConfig('SMTP_PASSWORD') !== '' ? '(set)' : '(NOT SET)');
printf("  from     : %s\n", portalConfig('PORTAL_MAIL_FROM', '(not set)'));
printf("  sending  : %s\n\n", $to);

$error = null;
$start = microtime(true);

$ok = sendMail(
    $to,
    'MHWUN portal SMTP test',
    '<p style="font-family:Arial">This is a test from the MHWUN member portal.</p>'
    . '<p style="font-family:Arial">If you are reading this, one-time codes will reach members.</p>',
    $error
);

printf("  result   : %s  (%.2fs)\n", $ok ? 'SENT' : 'FAILED', microtime(true) - $start);

if (!$ok) {
    echo "  reason   : " . ($error ?? 'unknown') . "\n\n";
    echo "Common causes:\n";
    echo "  - wrong password, or the mailbox does not exist\n";
    echo "  - outbound port 465 blocked by the host\n";
    echo "  - SMTP_SECURE should be 'tls' with port 587 instead\n";
    http_response_code(500);
    exit;
}

echo "\nCheck the inbox (and the spam folder).\n";
echo "\n*** DELETE THIS FILE FROM THE SERVER NOW ***\n";
