<?php
/**
 * OTP delivery over SMTP.
 *
 * Sends through the cPanel mailbox via PHPMailer (vendored in portal/lib, since
 * this project has no Composer). Falls back to PHP's mail() only if SMTP is
 * unconfigured, so a missing .env entry degrades rather than breaks.
 *
 * Configure in .env:
 *     PORTAL_MAIL_FROM       sender address (should match SMTP_USERNAME)
 *     PORTAL_MAIL_FROM_NAME  display name
 *     SMTP_HOST / SMTP_PORT / SMTP_SECURE / SMTP_USERNAME / SMTP_PASSWORD
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../lib/PHPMailer/Exception.php';
require_once __DIR__ . '/../lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** Read a portal setting from .env, falling back to a default. */
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

/**
 * Send the one-time code.
 *
 * @param string $purpose 'register' or 'reset', which only changes the wording.
 */
function sendOtpEmail(string $email, string $memberName, string $code, string $purpose): bool
{
    $isReset = $purpose === 'reset';
    $subject = $isReset
        ? 'Your MHWUN password reset code'
        : 'Your MHWUN registration code';

    $intro = $isReset
        ? 'We received a request to reset the password on your MHWUN member account.'
        : 'Welcome to the MHWUN OOUTH member portal. Use the code below to finish setting up your account.';

    $greeting = $memberName !== '' ? 'Hello ' . $memberName . ',' : 'Hello,';

    $body = <<<HTML
<!doctype html>
<html>
<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e2e8f0">
    <div style="background:#0284c7;padding:20px 24px;color:#ffffff">
      <h1 style="margin:0;font-size:18px">MHWUN OOUTH Branch</h1>
      <p style="margin:4px 0 0;font-size:12px;opacity:.85">Member Portal</p>
    </div>
    <div style="padding:24px">
      <p style="margin:0 0 12px">{$greeting}</p>
      <p style="margin:0 0 20px;font-size:14px;line-height:1.6">{$intro}</p>
      <div style="text-align:center;margin:24px 0">
        <div style="display:inline-block;background:#f1f5f9;border:1px solid #cbd5e1;border-radius:10px;padding:16px 28px">
          <span style="font-size:30px;font-weight:bold;letter-spacing:8px;color:#0284c7">{$code}</span>
        </div>
      </div>
      <p style="margin:0 0 8px;font-size:13px;color:#475569">
        This code expires in <strong>@TTL@ minutes</strong> and can only be used once.
      </p>
      <p style="margin:0;font-size:13px;color:#475569">
        If you did not request this, you can ignore this email &mdash; nothing has changed on your account.
        Please tell the union office if you receive these unexpectedly.
      </p>
    </div>
    <div style="background:#f8fafc;padding:14px 24px;border-top:1px solid #e2e8f0;font-size:11px;color:#64748b">
      This is an automated message. Please do not reply.
    </div>
  </div>
</body>
</html>
HTML;

    $body = str_replace('@TTL@', (string)OTP_TTL_MINUTES, $body);

    return sendMail($email, $subject, $body);
}

/**
 * Deliver an HTML message.
 *
 * Failures are logged with detail but reported to the caller as a plain false —
 * an SMTP error string names the mail host and account, which must never reach
 * whoever triggered the send.
 *
 * @param string|null $error Receives the underlying reason, for diagnostics
 *                           that only ever go to an authenticated admin.
 */
function sendMail(string $to, string $subject, string $htmlBody, ?string &$error = null): bool
{
    $fromAddress = portalConfig('PORTAL_MAIL_FROM', 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'emmaggi.com'));
    $fromName    = portalConfig('PORTAL_MAIL_FROM_NAME', 'MHWUN OOUTH');
    $smtpHost    = portalConfig('SMTP_HOST');

    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid recipient address';
        error_log('portal mailer: refusing to send to invalid address');
        return false;
    }

    // No SMTP configured: fall back so a missing setting degrades gracefully.
    if ($smtpHost === '') {
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . sprintf('%s <%s>', $fromName, $fromAddress),
            'Reply-To: ' . $fromAddress,
            'X-Mailer: MHWUN-Portal',
        ]);

        $sent = @mail($to, $subject, $htmlBody, $headers, '-f' . $fromAddress);
        if (!$sent) {
            $error = 'mail() returned false and no SMTP host is configured';
            error_log('portal mailer: ' . $error);
        }

        return $sent;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $smtpHost;
        $mail->SMTPAuth   = true;
        $mail->Username   = portalConfig('SMTP_USERNAME', $fromAddress);
        $mail->Password   = portalConfig('SMTP_PASSWORD');
        $mail->Port       = (int)portalConfig('SMTP_PORT', '465');
        $mail->CharSet    = 'UTF-8';
        $mail->Timeout    = 20;

        // 465 is implicit TLS (SMTPS); 587 is STARTTLS.
        $mail->SMTPSecure = portalConfig('SMTP_SECURE', 'ssl') === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;

        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($to);
        $mail->addReplyTo($fromAddress, $fromName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = trim(strip_tags(preg_replace('/<(br|\/p|\/div)[^>]*>/i', "\n", $htmlBody)));

        $mail->send();

        return true;

    } catch (PHPMailerException $e) {
        $error = $mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage();
        error_log('portal mailer (SMTP): ' . $error);

        return false;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        error_log('portal mailer (SMTP, unexpected): ' . $error);

        return false;
    }
}
