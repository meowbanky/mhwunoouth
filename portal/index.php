<?php
/** Member sign in. */

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';

if (isMemberLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

portalHead('Sign in');
authShellOpen('Sign in', 'Use the email address you registered with.');
?>

<form id="loginForm" class="space-y-4">
    <div>
        <label class="block text-sm font-semibold mb-1.5" for="email">Email address</label>
        <input id="email" name="email" type="email" autocomplete="username" required
               class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
               placeholder="you@example.com">
    </div>

    <div>
        <label class="block text-sm font-semibold mb-1.5" for="password">Password</label>
        <div class="relative">
            <input id="password" name="password" type="password" autocomplete="current-password" required
                   class="w-full px-4 py-3 pr-12 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm"
                   placeholder="••••••••">
            <button type="button" onclick="togglePassword()" tabindex="-1"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                <span class="material-icons-round text-lg" id="pwIcon">visibility</span>
            </button>
        </div>
    </div>

    <button type="submit" id="loginBtn"
            class="w-full bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
        <span class="material-icons-round text-lg">login</span> Sign in
    </button>
</form>

<div class="mt-6 pt-5 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between text-sm">
    <a href="forgot_password.php" class="text-primary hover:underline font-medium">Forgot password?</a>
    <a href="register.php" class="text-primary hover:underline font-medium">Create an account</a>
</div>

<script>
    function togglePassword() {
        const f = document.getElementById('password');
        const shown = f.type === 'text';
        f.type = shown ? 'password' : 'text';
        document.getElementById('pwIcon').textContent = shown ? 'visibility' : 'visibility_off';
    }

    $('#loginForm').on('submit', function (e) {
        e.preventDefault();
        const btn = $('#loginBtn').prop('disabled', true);

        api('login', { email: $('#email').val(), password: $('#password').val() })
            .done(res => { window.location = res.data.redirect || 'dashboard.php'; })
            .fail(xhr => {
                btn.prop('disabled', false);
                Swal.fire('Sign in failed', xhr.responseJSON?.message || 'Please try again.', 'error');
            });
    });
</script>

<?php authShellClose(); ?>
