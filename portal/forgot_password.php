<?php
/**
 * Password reset.
 *
 * Same OTP mechanics as registration, but the code always goes to the address
 * already registered — never one supplied now — so a reset cannot be used to
 * move an account onto somebody else's inbox.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isMemberLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

portalHead('Reset password');
authShellOpen('Reset your password', 'We will email a code to your registered address.');
?>

<section class="stage" data-stage="1">
    <label class="block text-sm font-semibold mb-1.5" for="email">Your registered email</label>
    <input id="email" type="email" autocomplete="username"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
           placeholder="you@example.com">
    <button type="button" id="startBtn" onclick="start()"
            class="w-full mt-4 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
        Send reset code
    </button>
</section>

<section class="stage hidden" data-stage="2">
    <p class="text-sm text-slate-600 dark:text-slate-400 mb-4" id="sentNote"></p>
    <input id="otpCode" type="text" inputmode="numeric" maxlength="<?php echo OTP_LENGTH; ?>" autocomplete="one-time-code"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-center text-2xl font-bold tracking-[0.5em]"
           placeholder="------">
    <button type="button" id="verifyBtn" onclick="verify()"
            class="w-full mt-4 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
        Verify code
    </button>
    <button type="button" onclick="goStage(1)" class="w-full mt-2 text-sm text-slate-500 hover:underline">Use a different email</button>
</section>

<section class="stage hidden" data-stage="3">
    <label class="block text-sm font-semibold mb-1.5" for="pw1">New password</label>
    <input id="pw1" type="password" autocomplete="new-password"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
           placeholder="At least <?php echo PASSWORD_MIN_LENGTH; ?> characters">

    <label class="block text-sm font-semibold mb-1.5 mt-4" for="pw2">Confirm new password</label>
    <input id="pw2" type="password" autocomplete="new-password"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
           placeholder="Type it again">

    <button type="button" id="resetBtn" onclick="reset()"
            class="w-full mt-5 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
        Change my password
    </button>
</section>

<div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 text-sm text-center">
    Remembered it? <a href="index.php" class="text-primary hover:underline font-medium">Sign in</a>
</div>

<script>
    function goStage(n) {
        $('.stage').addClass('hidden').filter('[data-stage="' + n + '"]').removeClass('hidden');
    }

    function start() {
        const email = $('#email').val().trim();
        if (!email) { Swal.fire('Email required', 'Please enter your registered email address.', 'warning'); return; }

        const btn = $('#startBtn').prop('disabled', true).text('Sending...');
        api('forgot_start', { email })
            .done(res => {
                btn.prop('disabled', false).text('Send reset code');
                // Deliberately non-committal: we never confirm whether the
                // address is registered.
                $('#sentNote').text(res.message + ' It expires in <?php echo OTP_TTL_MINUTES; ?> minutes.');
                goStage(2);
                $('#otpCode').val('').trigger('focus');
            })
            .fail(xhr => {
                btn.prop('disabled', false).text('Send reset code');
                Swal.fire('Could not send', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }

    function verify() {
        const btn = $('#verifyBtn').prop('disabled', true);
        api('forgot_verify', { code: $('#otpCode').val().trim() })
            .done(() => { btn.prop('disabled', false); goStage(3); setTimeout(() => $('#pw1').trigger('focus'), 100); })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Incorrect code', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }

    function reset() {
        const btn = $('#resetBtn').prop('disabled', true);
        api('forgot_reset', { password: $('#pw1').val(), confirm: $('#pw2').val() })
            .done(res => {
                Swal.fire({ icon: 'success', title: 'Password changed', text: res.message })
                    .then(() => window.location = 'index.php');
            })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Could not change password', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }
</script>

<?php authShellClose(); ?>
