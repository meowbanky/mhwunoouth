<?php
/** End the member session. */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';

logoutMember();

header('Location: index.php');
exit;
