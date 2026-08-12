<?php
/**
 * Member registration.
 *
 * Four steps: find your name, prove it with your payslip ID, verify an emailed
 * code, then choose a password. The name list deliberately shows no IDs — the
 * payslip ID is the second factor and must not appear on screen.
 */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isMemberLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

portalHead('Register');
authShellOpen('Create your account', 'Four quick steps. You will need your payslip.');
?>

<!-- Progress -->
<div class="flex items-center gap-1.5 mb-7" id="steps">
    <?php foreach (['Name', 'Verify', 'Code', 'Password'] as $i => $label): ?>
        <div class="flex-1">
            <div class="h-1.5 rounded-full bg-slate-200 dark:bg-slate-700 step-bar" data-step="<?php echo $i + 1; ?>"></div>
            <p class="text-[10px] mt-1.5 font-semibold uppercase tracking-wide text-slate-400 step-label" data-step="<?php echo $i + 1; ?>"><?php echo e($label); ?></p>
        </div>
    <?php endforeach; ?>
</div>

<!-- Step 1: find your name -->
<section class="step" data-step="1">
    <label class="block text-sm font-semibold mb-1.5">Search for your name</label>
    <div class="relative">
        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
        <input id="nameSearch" type="text" autocomplete="off" placeholder="Type at least 3 letters..."
               class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm">
    </div>
    <div id="results" class="mt-3 space-y-1.5 max-h-64 overflow-y-auto"></div>
    <p class="text-xs text-slate-500 mt-3">Can't find yourself? Your membership may not be active — please contact the union office.</p>
</section>

<!-- Step 2: payslip ID -->
<section class="step hidden" data-step="2">
    <div class="p-3 bg-slate-50 dark:bg-slate-800 rounded-xl mb-4 flex items-center gap-3">
        <span class="material-icons-round text-primary">person</span>
        <div class="min-w-0">
            <p class="text-sm font-semibold truncate" id="chosenName">—</p>
            <button type="button" onclick="goStep(1)" class="text-xs text-primary hover:underline">Not you? Search again</button>
        </div>
    </div>

    <label class="block text-sm font-semibold mb-1.5" for="payslipId">Your payslip ID</label>
    <input id="payslipId" type="text" inputmode="numeric" autocomplete="off"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm font-mono tracking-wider"
           placeholder="Enter the ID shown on your payslip">
    <p class="text-xs text-slate-500 mt-2">This is the staff number printed on your payslip. It confirms the account is yours.</p>

    <button type="button" id="verifyBtn" onclick="verifyIdentity()"
            class="w-full mt-5 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
        Confirm my identity
    </button>
</section>

<!-- Step 3: email + code -->
<section class="step hidden" data-step="3">
    <div id="emailStage">
        <label class="block text-sm font-semibold mb-1.5" for="email">Your email address</label>
        <input id="email" type="email" autocomplete="email"
               class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
               placeholder="you@example.com">
        <div class="mt-2 p-3 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg">
            <p class="text-xs text-amber-800 dark:text-amber-300">
                <span class="font-bold">Choose carefully.</span> This becomes your username, and only the union office can change it later.
            </p>
        </div>
        <button type="button" id="sendOtpBtn" onclick="sendOtp()"
                class="w-full mt-4 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
            Send me a code
        </button>
    </div>

    <div id="codeStage" class="hidden">
        <p class="text-sm text-slate-600 dark:text-slate-400 mb-4">
            We sent a <?php echo OTP_LENGTH; ?>-digit code to <span id="maskedEmail" class="font-semibold text-slate-900 dark:text-white"></span>.
            It expires in <?php echo OTP_TTL_MINUTES; ?> minutes.
        </p>
        <input id="otpCode" type="text" inputmode="numeric" maxlength="<?php echo OTP_LENGTH; ?>" autocomplete="one-time-code"
               class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-center text-2xl font-bold tracking-[0.5em]"
               placeholder="------">
        <button type="button" id="verifyOtpBtn" onclick="verifyCode()"
                class="w-full mt-4 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
            Verify code
        </button>
        <div class="flex justify-between mt-3 text-sm">
            <button type="button" onclick="backToEmail()" class="text-slate-500 hover:underline">Change email</button>
            <button type="button" onclick="sendOtp()" class="text-primary hover:underline font-medium">Resend code</button>
        </div>
    </div>
</section>

<!-- Step 4: password -->
<section class="step hidden" data-step="4">
    <label class="block text-sm font-semibold mb-1.5" for="pw1">Choose a password</label>
    <input id="pw1" type="password" autocomplete="new-password"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
           placeholder="At least <?php echo PASSWORD_MIN_LENGTH; ?> characters">

    <label class="block text-sm font-semibold mb-1.5 mt-4" for="pw2">Confirm password</label>
    <input id="pw2" type="password" autocomplete="new-password"
           class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
           placeholder="Type it again">

    <p class="text-xs text-slate-500 mt-2">Must be at least <?php echo PASSWORD_MIN_LENGTH; ?> characters and include a letter and a number.</p>

    <button type="button" id="finishBtn" onclick="finish()"
            class="w-full mt-5 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
        Create my account
    </button>
</section>

<div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 text-sm text-center">
    Already registered? <a href="index.php" class="text-primary hover:underline font-medium">Sign in</a>
</div>

<script>
    let chosenRef = null;
    let searchTimer;

    goStep(1);

    function goStep(n) {
        $('.step').addClass('hidden').filter('[data-step="' + n + '"]').removeClass('hidden');
        $('.step-bar').each(function () {
            const s = +$(this).data('step');
            $(this).toggleClass('bg-primary', s <= n).toggleClass('bg-slate-200 dark:bg-slate-700', s > n);
        });
        $('.step-label').each(function () {
            const s = +$(this).data('step');
            $(this).toggleClass('text-primary', s <= n).toggleClass('text-slate-400', s > n);
        });
    }

    $('#nameSearch').on('input', function () {
        const term = $(this).val();
        clearTimeout(searchTimer);
        if (term.length < 3) { $('#results').empty(); return; }
        searchTimer = setTimeout(() => runSearch(term), 300);
    });

    function runSearch(term) {
        api('search_members', { term })
            .done(res => {
                const rows = res.data.matches;
                if (!rows.length) {
                    $('#results').html('<p class="text-sm text-slate-400 italic p-3">No active member found with that name.</p>');
                    return;
                }
                // Names only — the payslip ID is the second factor and is never shown.
                $('#results').html(rows.map(m => `
                    <button type="button" onclick="choose('${m.ref}', this)"
                        class="w-full text-left px-4 py-3 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-primary hover:bg-primary/5 transition-all text-sm font-medium">
                        ${$('<div>').text(m.name).html()}
                    </button>`).join(''));
            })
            .fail(xhr => Swal.fire('Search failed', xhr.responseJSON?.message || 'Please try again.', 'error'));
    }

    function choose(ref, el) {
        chosenRef = ref;
        $('#chosenName').text($(el).text().trim());
        $('#payslipId').val('');
        goStep(2);
        setTimeout(() => $('#payslipId').trigger('focus'), 100);
    }

    function verifyIdentity() {
        const id = $('#payslipId').val().trim();
        if (!id) { Swal.fire('Payslip ID required', 'Please enter the ID from your payslip.', 'warning'); return; }

        const btn = $('#verifyBtn').prop('disabled', true);
        api('verify_identity', { ref: chosenRef, payslip_id: id })
            .done(res => {
                btn.prop('disabled', false);
                if (res.data.suggested_email) $('#email').val(res.data.suggested_email);
                goStep(3);
            })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Could not confirm', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }

    function sendOtp() {
        const email = $('#email').val().trim();
        if (!email) { Swal.fire('Email required', 'Please enter your email address.', 'warning'); return; }

        const btn = $('#sendOtpBtn').prop('disabled', true).text('Sending...');
        api('send_registration_otp', { email })
            .done(res => {
                btn.prop('disabled', false).text('Send me a code');
                $('#maskedEmail').text(res.data.masked_email);
                $('#emailStage').addClass('hidden');
                $('#codeStage').removeClass('hidden');
                $('#otpCode').val('').trigger('focus');
            })
            .fail(xhr => {
                btn.prop('disabled', false).text('Send me a code');
                Swal.fire('Could not send code', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }

    function backToEmail() {
        $('#codeStage').addClass('hidden');
        $('#emailStage').removeClass('hidden');
    }

    function verifyCode() {
        const code = $('#otpCode').val().trim();
        if (!code) { return; }

        const btn = $('#verifyOtpBtn').prop('disabled', true);
        api('verify_registration_otp', { code })
            .done(() => { btn.prop('disabled', false); goStep(4); setTimeout(() => $('#pw1').trigger('focus'), 100); })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Incorrect code', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }

    function finish() {
        const btn = $('#finishBtn').prop('disabled', true);
        api('set_password', { password: $('#pw1').val(), confirm: $('#pw2').val() })
            .done(res => {
                Swal.fire({ icon: 'success', title: 'Account created', text: res.message })
                    .then(() => window.location = 'index.php');
            })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Could not create account', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    }
</script>

<?php authShellClose(); ?>
