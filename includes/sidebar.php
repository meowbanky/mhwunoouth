<?php require_once __DIR__ . '/nav_links.php'; ?>
<aside class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex-shrink-0 hidden lg:flex flex-col">
    <div class="p-6 flex items-center space-x-3">
        <div class="bg-white p-1 rounded-lg">
            <img src="image/mhwun_logo.png" alt="MHWUN Logo" class="w-10 h-10 object-contain">
        </div>
        <div>
            <h1 class="font-bold text-lg leading-tight">MHWUN</h1>
            <p class="text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Admin Panel</p>
        </div>
    </div>
    <nav class="flex-1 px-4 space-y-1 overflow-y-auto mt-4">

        <?php
            // Ensure NotificationService is available if we need to fetch balance
            if (!isset($smsBalance)) {
                $smsBalance = 0; // Default
                try {
                     // Check if file exists in likely locations (root or relative)
                     if (file_exists('NotificationService.php')) {
                         require_once('NotificationService.php');
                     } elseif (file_exists('../NotificationService.php')) {
                         require_once('../NotificationService.php');
                     }

                     if (class_exists('class\services\NotificationService') && isset($conn)) {
                         $notificationService = new class\services\NotificationService($conn);
                         // Optional: Caching could be implemented here to reduce API calls
                         $smsBalance = $notificationService->getSMSBalance();
                     }
                } catch (Exception $e) {
                    // Fail silently
                }
            }

            // Ensure helper exists
            if (!function_exists('formatCurrency')) {
                function formatCurrency($amount) {
                    return '₦' . number_format($amount, 2);
                }
            }
        ?>

        <?php foreach (navGroups() as $group): ?>
            <?php if (!empty($group['heading'])): ?>
                <div class="pt-10 pb-4 text-xs font-semibold text-slate-400 px-4 uppercase tracking-wider">
                    <?php echo htmlspecialchars($group['heading']); ?>
                </div>
            <?php endif; ?>

            <?php foreach ($group['links'] as $link): ?>
                <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php
                    echo isNavActive($link)
                        ? 'bg-primary/10 text-primary font-medium'
                        : 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all';
                ?>" href="<?php echo htmlspecialchars($link['href']); ?>">
                    <span class="material-icons-round"><?php echo htmlspecialchars(navIcon($link)); ?></span>
                    <span><?php echo htmlspecialchars(navLabel($link)); ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
    <div class="p-6">
        <div class="bg-slate-100 dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700">
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-1">SMS Balance</p>
            <p class="font-bold text-slate-900 dark:text-white"><?php echo formatCurrency($smsBalance); ?></p>
            <div class="w-full bg-slate-200 dark:bg-slate-700 h-1.5 rounded-full mt-2">
                <div class="bg-primary h-full rounded-full w-2/3"></div>
            </div>
        </div>
    </div>
</aside>
