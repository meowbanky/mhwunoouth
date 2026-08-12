<?php
/**
 * One-time codes for registration and password reset.
 *
 * Codes are stored as hashes, never in clear: a leaked tbl_member_otp must not
 * hand anybody a working code. Each code is single-use, expires quickly, and
 * carries its own attempt counter so it cannot be guessed digit by digit.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/mailer.php';

/**
 * Generate a code, store its hash, and send it to the address given.
 *
 * Any earlier unconsumed code for the same member and purpose is invalidated
 * first, so only the most recent code can ever work.
 *
 * @return bool whether delivery succeeded.
 */
function issueOtp(PDO $conn, string $memberId, string $purpose, string $email, string $memberName): bool
{
    $conn->prepare(
        "UPDATE tbl_member_otp SET consumed_at = NOW()
          WHERE membersid = ? AND purpose = ? AND consumed_at IS NULL"
    )->execute([$memberId, $purpose]);

    $code = str_pad((string)random_int(0, 10 ** OTP_LENGTH - 1), OTP_LENGTH, '0', STR_PAD_LEFT);

    $conn->prepare(
        "INSERT INTO tbl_member_otp (membersid, purpose, email, otp_hash, expires_at, ip)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)"
    )->execute([
        $memberId,
        $purpose,
        $email,
        password_hash($code, PASSWORD_DEFAULT),
        OTP_TTL_MINUTES,
        clientIp(),
    ]);

    return sendOtpEmail($email, $memberName, $code, $purpose);
}

/**
 * Check a code supplied by the member.
 *
 * Consumes the code on success. On failure the attempt is counted, and the
 * code is burned once the attempt cap is hit.
 *
 * @return array{ok: bool, message: string}
 */
function verifyOtp(PDO $conn, string $memberId, string $purpose, string $code): array
{
    $stmt = $conn->prepare(
        "SELECT id, otp_hash, attempts, expires_at
           FROM tbl_member_otp
          WHERE membersid = ? AND purpose = ? AND consumed_at IS NULL
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute([$memberId, $purpose]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'message' => 'No active code. Please request a new one.'];
    }

    if (strtotime((string)$row['expires_at']) < time()) {
        return ['ok' => false, 'message' => 'That code has expired. Please request a new one.'];
    }

    if ((int)$row['attempts'] >= OTP_MAX_ATTEMPTS) {
        $conn->prepare("UPDATE tbl_member_otp SET consumed_at = NOW() WHERE id = ?")->execute([$row['id']]);

        return ['ok' => false, 'message' => 'Too many incorrect attempts. Please request a new code.'];
    }

    if (!password_verify($code, (string)$row['otp_hash'])) {
        $conn->prepare("UPDATE tbl_member_otp SET attempts = attempts + 1 WHERE id = ?")->execute([$row['id']]);
        $left = OTP_MAX_ATTEMPTS - ((int)$row['attempts'] + 1);

        return [
            'ok'      => false,
            'message' => $left > 0
                ? "Incorrect code. {$left} attempt" . ($left === 1 ? '' : 's') . ' remaining.'
                : 'Too many incorrect attempts. Please request a new code.',
        ];
    }

    $conn->prepare("UPDATE tbl_member_otp SET consumed_at = NOW() WHERE id = ?")->execute([$row['id']]);

    return ['ok' => true, 'message' => 'Code verified.'];
}
