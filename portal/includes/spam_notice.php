<?php
/**
 * "Check your spam folder" notice, shown wherever a one-time code is sent.
 *
 * Codes from a new sending domain routinely land in spam until SPF and DKIM
 * records are published for it. This is the stopgap that keeps members from
 * concluding the code never arrived; naming the sender lets them whitelist it
 * once rather than go hunting every time.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$senderAddress = portalConfig('PORTAL_MAIL_FROM', 'noreply@emmaggi.com');
?>
<div class="mb-4 p-3.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl">
    <div class="flex gap-2.5">
        <span class="material-icons-round text-amber-500 text-xl flex-shrink-0">folder_special</span>
        <div class="text-xs text-amber-800 dark:text-amber-300 leading-relaxed">
            <p class="font-bold mb-1">Can't see the email? Check your spam or junk folder.</p>
            <p>
                It is sent from
                <span class="font-semibold break-all"><?php echo e($senderAddress); ?></span>.
                Mark it as “Not spam” and add it to your contacts so future codes reach your inbox.
            </p>
        </div>
    </div>
</div>
