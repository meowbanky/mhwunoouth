<?php
/**
 * Shared page chrome for the portal.
 *
 * Two shells: a narrow centred card for the sign-in flows, and a full app
 * layout for the signed-in member. Styling matches the admin app so the two
 * feel like one system.
 */

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function portalHead(string $title, bool $withExportLibs = false): void
{
    $csrf = csrfToken();
    ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo e($title); ?> · MHWUN OOUTH Member Portal</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,typography"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet"/>
    <link rel="icon" type="image/png" href="../image/mhwun_logo.png">
    <script>
      tailwind.config = {
        darkMode: "class",
        theme: { extend: { colors: {
          primary: "#0284c7", secondary: "#0d9488",
          "background-light": "#f1f5f9", "background-dark": "#0f172a",
        }, fontFamily: { display: ["Inter", "sans-serif"] } } },
      };
    </script>
    <style>body { font-family: 'Inter', sans-serif; }</style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <?php if ($withExportLibs): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
    <?php endif; ?>
    <script>
      const CSRF = <?php echo json_encode($csrf); ?>;
      // Every POST carries the token; no caller has to remember to add it.
      function api(action, data = {}) {
          return $.post('api.php', Object.assign({ action, csrf: CSRF }, data), null, 'json');
      }
      function money(v) {
          return '₦' + parseFloat(v || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
      }
      if (localStorage.getItem('theme') === 'dark' ||
          (!('theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
          document.documentElement.classList.add('dark');
      }
    </script>
</head>
<body class="bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100">
<?php
}

/** Narrow centred card used by sign in, register and reset. */
function authShellOpen(string $heading, string $subheading): void
{
    ?>
<div class="min-h-screen flex flex-col items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="flex flex-col items-center mb-6">
            <img src="../image/mhwun_logo.png" alt="MHWUN" class="w-14 h-14 object-contain mb-3">
            <h1 class="text-xl font-bold">MHWUN OOUTH Branch</h1>
            <p class="text-xs uppercase tracking-widest text-slate-500 font-semibold">Member Portal</p>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-7">
            <h2 class="text-lg font-bold mb-1"><?php echo e($heading); ?></h2>
            <p class="text-sm text-slate-500 dark:text-slate-400 mb-6"><?php echo e($subheading); ?></p>
    <?php
}

function authShellClose(): void
{
    ?>
        </div>
        <p class="text-center text-[11px] text-slate-400 mt-6 uppercase tracking-widest">
            &copy; <?php echo date('Y'); ?> MHWUN OOUTH Branch
        </p>
    </div>
</div>
</body>
</html>
    <?php
}

function portalFoot(): void
{
    ?>
</body>
</html>
    <?php
}
