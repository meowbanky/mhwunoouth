<?php
/**
 * Member portal API.
 *
 * Two groups of actions:
 *   - onboarding (search, verify identity, OTP, set password, login, reset)
 *   - data (everything the signed-in member can read about themselves)
 *
 * Data actions never accept a member ID. They call currentMemberId(), which
 * reads the session and throws otherwise, so a member cannot reach another
 * member's records by editing a request.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/otp.php';
require_once __DIR__ . '/includes/member_data.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Every state-changing action is a POST and must carry the CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrfValid($_POST['csrf'] ?? null)) {
    jsonFail('Your session has expired. Please refresh the page and try again.', 419);
}

try {
    switch ($action) {

        // -------------------------------------------------------------------
        // Onboarding
        // -------------------------------------------------------------------

        /**
         * Search members by name.
         *
         * Returns an opaque handle per match, never the payslip ID — that ID is
         * the second factor, so putting it on screen would defeat the point.
         */
        case 'search_members': {
            $term = trim($_POST['term'] ?? '');
            if (mb_strlen($term) < 3) {
                jsonFail('Please type at least 3 characters of your name.');
            }

            if (throttleExceeded($conn, 'search')) {
                jsonFail('Too many searches. Please wait a few minutes and try again.', 429);
            }

            $stmt = $conn->prepare(
                "SELECT patientid, Fname, Lname, Mname
                   FROM tbl_personalinfo
                  WHERE Status = 'Active'
                    AND (CONCAT(Lname, ' ', Fname) LIKE :t OR CONCAT(Fname, ' ', Lname) LIKE :t2
                         OR Lname LIKE :t3 OR Fname LIKE :t4)
                  ORDER BY Lname, Fname
                  LIMIT 25"
            );
            $like = '%' . $term . '%';
            $stmt->execute([':t' => $like, ':t2' => $like, ':t3' => $like, ':t4' => $like]);

            $matches = [];
            $_SESSION['search_map'] = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $ref = bin2hex(random_bytes(12));
                $_SESSION['search_map'][$ref] = (string)$row['patientid'];

                $matches[] = [
                    'ref'  => $ref,
                    'name' => trim($row['Lname'] . ', ' . $row['Fname'] . ' ' . ($row['Mname'] ?? '')),
                ];
            }

            jsonOk(['matches' => $matches, 'count' => count($matches)]);
        }

        /**
         * Confirm the selected member by payslip ID.
         *
         * The ID is private to the member, so matching it against the name they
         * picked is what proves identity. Failures are counted per IP.
         */
        case 'verify_identity': {
            $ref       = (string)($_POST['ref'] ?? '');
            $payslipId = trim((string)($_POST['payslip_id'] ?? ''));

            if ($ref === '' || $payslipId === '') {
                jsonFail('Please select your name and enter your payslip ID.');
            }

            if (throttleExceeded($conn, 'verify')) {
                jsonFail('Too many attempts. Please wait 15 minutes and try again.', 429);
            }

            $memberId = $_SESSION['search_map'][$ref] ?? null;
            if ($memberId === null) {
                jsonFail('Your search has expired. Please search for your name again.');
            }

            // Deliberately identical wording whether the name or the ID is
            // wrong, so a wrong guess reveals nothing about which part matched.
            if (!hash_equals($memberId, $payslipId)) {
                jsonFail('That payslip ID does not match the name selected.');
            }

            $exists = $conn->prepare("SELECT email FROM tbl_member_auth WHERE membersid = ?");
            $exists->execute([$memberId]);
            if ($row = $exists->fetch(PDO::FETCH_ASSOC)) {
                jsonFail('You already have an account (' . maskEmail((string)$row['email']) . '). Please sign in, or use "Forgot password".');
            }

            $profile = memberProfile($conn, $memberId);
            if (!$profile) {
                jsonFail('Member record not found. Please contact the union office.');
            }

            $_SESSION['pending'] = [
                'member_id' => $memberId,
                'name'      => trim($profile['Fname'] . ' ' . $profile['Lname']),
                'verified'  => true,
                'otp_ok'    => false,
            ];

            jsonOk([
                'name'            => $_SESSION['pending']['name'],
                'suggested_email' => $profile['EmailAddress'] ?? '',
            ], 'Identity confirmed.');
        }

        /** Send the registration code to the address the member supplies. */
        case 'send_registration_otp': {
            $pending = $_SESSION['pending'] ?? null;
            if (empty($pending['verified'])) {
                jsonFail('Please confirm your identity first.', 403);
            }

            $email = strtolower(trim((string)($_POST['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                jsonFail('Please enter a valid email address.');
            }

            if (throttleExceeded($conn, 'otp_send')) {
                jsonFail('Too many codes requested. Please wait 15 minutes.', 429);
            }

            $taken = $conn->prepare("SELECT membersid FROM tbl_member_auth WHERE email = ? AND membersid <> ?");
            $taken->execute([$email, $pending['member_id']]);
            if ($taken->fetchColumn()) {
                jsonFail('That email address is already in use by another member.');
            }

            $_SESSION['pending']['email'] = $email;

            if (!issueOtp($conn, $pending['member_id'], 'register', $email, $pending['name'])) {
                jsonFail('We could not send the code. Please check the address, or contact the union office.', 502);
            }

            jsonOk(['masked_email' => maskEmail($email)], 'A code has been sent to your email.');
        }

        /** Check the registration code. */
        case 'verify_registration_otp': {
            $pending = $_SESSION['pending'] ?? null;
            if (empty($pending['verified']) || empty($pending['email'])) {
                jsonFail('Please start again.', 403);
            }

            if (throttleExceeded($conn, 'otp_check')) {
                jsonFail('Too many attempts. Please wait 15 minutes.', 429);
            }

            $result = verifyOtp($conn, $pending['member_id'], 'register', trim((string)($_POST['code'] ?? '')));
            if (!$result['ok']) {
                jsonFail($result['message']);
            }

            $_SESSION['pending']['otp_ok'] = true;
            jsonOk([], 'Code verified. Choose your password.');
        }

        /** Create the account. */
        case 'set_password': {
            $pending = $_SESSION['pending'] ?? null;
            if (empty($pending['otp_ok'])) {
                jsonFail('Please verify your code first.', 403);
            }

            $problem = passwordProblem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
            if ($problem !== null) {
                jsonFail($problem);
            }

            saveMemberPassword($conn, $pending['member_id'], $pending['email'], (string)$_POST['password']);
            unset($_SESSION['pending'], $_SESSION['search_map']);

            jsonOk([], 'Your account is ready. Please sign in.');
        }

        /** Sign in. */
        case 'login': {
            $email    = strtolower(trim((string)($_POST['email'] ?? '')));
            $password = (string)($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                jsonFail('Please enter your email and password.');
            }

            if (throttleExceeded($conn, 'login')) {
                jsonFail('Too many sign-in attempts. Please wait 15 minutes.', 429);
            }

            $auth = findAuthByEmail($conn, $email);

            // Uniform failure message: never reveal whether the address exists.
            if (!$auth || !password_verify($password, (string)$auth['password_hash'])) {
                if ($auth) {
                    recordLoginFailure($conn, (string)$auth['membersid']);
                }
                jsonFail('Invalid email or password.', 401);
            }

            if (isLockedOut($auth)) {
                jsonFail('This account is temporarily locked after repeated failed attempts. Please try again shortly.', 423);
            }

            if ($auth['status'] !== 'active') {
                jsonFail('This account has been suspended. Please contact the union office.', 403);
            }

            if (($auth['member_status'] ?? '') !== 'Active') {
                jsonFail('Your membership is not active. Please contact the union office.', 403);
            }

            loginMember($conn, (string)$auth['membersid'], trim($auth['Fname'] . ' ' . $auth['Lname']));
            jsonOk(['redirect' => 'dashboard.php'], 'Signed in.');
        }

        /**
         * Begin a password reset.
         *
         * Always reports success, so this cannot be used to discover which
         * addresses have accounts.
         */
        case 'forgot_start': {
            $email = strtolower(trim((string)($_POST['email'] ?? '')));

            if (throttleExceeded($conn, 'otp_send')) {
                jsonFail('Too many requests. Please wait 15 minutes.', 429);
            }

            $auth = filter_var($email, FILTER_VALIDATE_EMAIL) ? findAuthByEmail($conn, $email) : null;

            if ($auth) {
                $_SESSION['reset'] = [
                    'member_id' => (string)$auth['membersid'],
                    'email'     => $email,
                    'otp_ok'    => false,
                ];
                issueOtp($conn, (string)$auth['membersid'], 'reset', $email, trim($auth['Fname'] . ' ' . $auth['Lname']));
            }

            jsonOk([], 'If that email is registered, a reset code has been sent to it.');
        }

        case 'forgot_verify': {
            $reset = $_SESSION['reset'] ?? null;
            if (!$reset) {
                jsonFail('Please request a reset code first.', 403);
            }

            if (throttleExceeded($conn, 'otp_check')) {
                jsonFail('Too many attempts. Please wait 15 minutes.', 429);
            }

            $result = verifyOtp($conn, $reset['member_id'], 'reset', trim((string)($_POST['code'] ?? '')));
            if (!$result['ok']) {
                jsonFail($result['message']);
            }

            $_SESSION['reset']['otp_ok'] = true;
            jsonOk([], 'Code verified. Choose a new password.');
        }

        case 'forgot_reset': {
            $reset = $_SESSION['reset'] ?? null;
            if (empty($reset['otp_ok'])) {
                jsonFail('Please verify your code first.', 403);
            }

            $problem = passwordProblem((string)($_POST['password'] ?? ''), (string)($_POST['confirm'] ?? ''));
            if ($problem !== null) {
                jsonFail($problem);
            }

            saveMemberPassword($conn, $reset['member_id'], $reset['email'], (string)$_POST['password']);
            unset($_SESSION['reset']);

            jsonOk([], 'Your password has been changed. Please sign in.');
        }

        // -------------------------------------------------------------------
        // Data — signed-in member only, always scoped to their own ID
        // -------------------------------------------------------------------

        case 'summary':
        case 'contributions':
        case 'transactions':
        case 'loans':
        case 'repayments':
        case 'bank_loans':
        case 'refunds':
        case 'withdrawals': {
            requireMemberApi();
            $memberId = currentMemberId();

            $data = match ($action) {
                'summary'       => memberSummary($conn, $memberId),
                'contributions' => memberContributions($conn, $memberId),
                'transactions'  => memberTransactions($conn, $memberId),
                'loans'         => memberLoans($conn, $memberId),
                'repayments'    => memberRepayments($conn, $memberId),
                'bank_loans'    => memberBankLoans($conn, $memberId),
                'refunds'       => memberRefunds($conn, $memberId),
                'withdrawals'   => memberWithdrawals($conn, $memberId),
            };

            jsonOk(['rows' => $data]);
        }

        /** Everything at once, for the full report and its exports. */
        case 'full_report': {
            requireMemberApi();
            $memberId = currentMemberId();

            jsonOk([
                'profile'       => memberProfile($conn, $memberId),
                'summary'       => memberSummary($conn, $memberId),
                'contributions' => memberContributions($conn, $memberId),
                'loans'         => memberLoans($conn, $memberId),
                'repayments'    => memberRepayments($conn, $memberId),
                'bank_loans'    => memberBankLoans($conn, $memberId),
                'refunds'       => memberRefunds($conn, $memberId),
                'withdrawals'   => memberWithdrawals($conn, $memberId),
                'generated_at'  => date('d M Y, H:i'),
            ]);
        }

        default:
            jsonFail('Unknown action.', 404);
    }

} catch (Throwable $e) {
    // Log the detail, tell the member nothing that describes the server.
    error_log('portal api [' . $action . ']: ' . $e->getMessage());
    jsonFail('Something went wrong. Please try again, or contact the union office.', 500);
}
