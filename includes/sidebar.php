<aside class="w-64 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex-shrink-0 hidden lg:flex flex-col">
    <div class="p-6 flex items-center space-x-3">
        <div class="bg-primary p-2 rounded-lg">
            <span class="material-icons-round text-white text-2xl">health_and_safety</span>
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

            $currentPage = basename($_SERVER['PHP_SELF']);
            
            function isActive($page, $current) {
                // Determine active state style
                if ($page === $current) {
                    return 'bg-primary/10 text-primary font-medium';
                }
                return 'text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all';
            }
        ?>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('dashboard.php', $currentPage); ?>" href="dashboard.php">
            <span class="material-icons-round">dashboard</span>
            <span>Dashboard</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('memberlist.php', $currentPage); ?>" href="memberlist.php">
            <span class="material-icons-round">group</span>
            <span>Members</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('addloan.php', $currentPage); ?>" href="addloan.php">
            <span class="material-icons-round">payments</span>
            <span>Loans &amp; Finance</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('loanContri_Compare.php', $currentPage); ?>" href="loanContri_Compare.php">
            <span class="material-icons-round">compare_arrows</span>
            <span>Loan Comparison</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('editContributions.php', $currentPage); ?>" href="editContributions.php">
            <span class="material-icons-round">volunteer_activism</span>
            <span>Contributions</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('mastertransaction.php', $currentPage); ?>" href="mastertransaction.php">
            <span class="material-icons-round">assessment</span>
            <span>Reports</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('status.php', $currentPage); ?>" href="status.php">
            <span class="material-icons-round">analytics</span>
            <span>Status</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('bulksms.php', basename($currentPage)); ?>" href="bulksms.php">
            <span class="material-icons-round">sms</span>
            <span>SMS Center</span>
        </a>
        <div class="pt-10 pb-4 text-xs font-semibold text-slate-400 px-4 uppercase tracking-wider">System</div>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('transact_period.php', $currentPage); ?>" href="transact_period.php">
            <span class="material-icons-round">calendar_today</span>
            <span>Period</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('process2.php', $currentPage); ?>" href="process2.php">
            <span class="material-icons-round">fact_check</span>
            <span>Process Transaction</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('registeruser.php', $currentPage); ?>" href="registeruser.php">
            <span class="material-icons-round">manage_accounts</span>
            <span>User Management</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 rounded-xl <?php echo isActive('backup2.php', $currentPage); ?>" href="backup2.php">
            <span class="material-icons-round">backup</span>
            <span>Backup</span>
        </a>
        <a class="flex items-center space-x-3 px-4 py-3 text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all" href="index.php">
            <span class="material-icons-round">logout</span>
            <span>Logout</span>
        </a>
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
