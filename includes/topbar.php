<?php
// Ensure NotificationService is available if we need to fetch balance
// Placed at top to ensure variable is available throughout the file
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
<header class="h-16 bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between px-8 z-10 flex-shrink-0 relative">
    <div class="flex items-center flex-1 max-w-xl">
        <div class="relative w-full group hidden md:block">
            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
            <input class="w-full pl-10 pr-4 py-2 bg-slate-100 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary/20 transition-all text-sm outline-none" placeholder="Search members, transactions, records..." type="text"/>
        </div>
        <!-- Mobile Hamburger -->
        <button id="mobile-menu-btn" class="md:hidden p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
            <span class="material-icons-round">menu</span>
        </button>
    </div>
    
    <div class="flex items-center space-x-4">
        <!-- Desktop Nav -->
        <nav class="hidden md:flex items-center gap-6 text-sm font-medium">
            <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors" href="dashboard.php">Dashboard</a>
            <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors" href="memberlist.php">Members</a>
            <a class="text-slate-600 dark:text-slate-300 hover:text-primary dark:hover:text-primary transition-colors" href="editContributions.php">Contributions</a>
        </nav>
        
        <div class="flex items-center space-x-2">
            <!-- SMS Balance Badge -->
            <div class="flex items-center bg-slate-100 dark:bg-slate-800 rounded-lg px-2 py-1.5 border border-slate-200 dark:border-slate-700">
                <span class="material-icons-round text-primary text-sm mr-1">account_balance_wallet</span>
                <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300"><?php echo formatCurrency($smsBalance); ?></span>
            </div>

            <button class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-all relative">
                <span class="material-icons-round">notifications</span>
                <span class="absolute top-2 right-2.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-900"></span>
            </button>
            <button class="p-2 text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-full transition-all" id="theme-toggle">
                <span class="material-icons-round dark:!hidden">dark_mode</span>
                <span class="material-icons-round !hidden dark:!block">light_mode</span>
            </button>
        </div>
        
        <div class="h-8 w-[1px] bg-slate-200 dark:bg-slate-800 mx-2 hidden sm:block"></div>
        
        <div class="hidden sm:flex items-center space-x-3 cursor-pointer group">
            <div class="text-right hidden md:block">
                <p class="text-sm font-semibold dark:text-white group-hover:text-primary transition-colors">
                    <?php echo isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] : 'Admin User'; ?>
                </p>
                <p class="text-[10px] text-slate-500 uppercase tracking-wider">Super Admin</p>
            </div>
            <img alt="User avatar" class="w-9 h-9 rounded-xl" src="https://lh3.googleusercontent.com/aida-public/AB6AXuCCXfNuKg4qtB6xmAD4xa-bjGwkv54wdMFauW3W-rhXyTXlxCDI_lhILBBE6PFQJEgxjHfhmmTdd0VLjaVlae0Ilw4KvwNVRIVoJCbTAGs2_8a8vjoHt0fqL3uPI9vJgsXoKbna77aS_oVXTIne76_1xNddjDHpfnW7nphss8XTrP6ZGJ1ArsMz9gNAvKS7SPJgTbiAaLbdsk1QUCEE6hu_HoDFxO2-a0D8Sh2SUX-xgVBU6a6tT0rB6iE3jjavssgOrl-6mp5bUtA1"/>
        </div>
    </div>
</header>

<!-- Mobile Menu Overlay -->
<!-- Increased z-index to 100 to ensure it covers the header elements -->
<div id="mobile-menu" class="fixed inset-0 z-[100] bg-white dark:bg-slate-900 hidden flex-col p-6 overflow-y-auto animate-slide-in transform transition-transform duration-300">
    <div class="flex justify-between items-center mb-6 flex-shrink-0">
        <div class="flex items-center gap-3">
             <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary font-bold">
                 <?php echo substr($_SESSION['Username'] ?? 'A', 0, 2); ?>
             </div>
             <div>
                 <p class="font-bold text-slate-900 dark:text-white"><?php echo isset($_SESSION['FirstName']) ? $_SESSION['FirstName'] : 'Admin User'; ?></p>
                 <p class="text-xs text-slate-500 uppercase tracking-wider">MHWUN OOUTH Branch</p>
             </div>
        </div>
        <button id="close-mobile-menu" class="p-2 rounded-full hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-500 dark:text-slate-400">
            <span class="material-icons-round text-2xl">close</span>
        </button>
    </div>
    
    <div class="mb-8 flex-shrink-0">
        <input class="w-full pl-4 pr-4 py-3 bg-slate-100 dark:bg-slate-800 border-none rounded-xl text-lg outline-none" placeholder="Search..." type="text"/>
    </div>

    <nav class="flex flex-col gap-1 text-sm font-medium pb-8">
        <?php
        // Helper for mobile active state (inline to avoid conflicts)
        if (!function_exists('isActiveMobile')) {
            function isActiveMobile($page) {
                $current = basename($_SERVER['PHP_SELF']);
                if ($page === $current) {
                    return 'bg-primary/10 text-primary font-bold';
                }
                return 'hover:bg-slate-100 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200';
            }
        }
        ?>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('dashboard.php'); ?>" href="dashboard.php">
            <span class="material-icons-round text-xl">dashboard</span> Dashboard
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('memberlist.php'); ?>" href="memberlist.php">
            <span class="material-icons-round text-xl">group</span> Members
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('addloan.php'); ?>" href="addloan.php">
            <span class="material-icons-round text-xl">payments</span> Loans
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('loanContri_Compare.php'); ?>" href="loanContri_Compare.php">
            <span class="material-icons-round text-xl">compare_arrows</span> Loan Comparison
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('editContributions.php'); ?>" href="editContributions.php">
            <span class="material-icons-round text-xl">volunteer_activism</span> Contributions
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('mastertransaction.php'); ?>" href="mastertransaction.php">
            <span class="material-icons-round text-xl">assessment</span> Reports
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('status.php'); ?>" href="status.php">
            <span class="material-icons-round text-xl">analytics</span> Status
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('bulksms.php'); ?>" href="bulksms.php">
            <span class="material-icons-round text-xl">sms</span> SMS Center
        </a>
        
        <div class="px-3 pt-4 pb-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">System</div>
        
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('transact_period.php'); ?>" href="transact_period.php">
            <span class="material-icons-round text-xl">calendar_today</span> Period
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('process2.php'); ?>" href="process2.php">
            <span class="material-icons-round text-xl">fact_check</span> Process Transaction
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('registeruser.php'); ?>" href="registeruser.php">
            <span class="material-icons-round text-xl">settings</span> User Settings
        </a>
        <a class="p-3 rounded-xl flex items-center gap-3 <?php echo isActiveMobile('backup2.php'); ?>" href="backup2.php">
            <span class="material-icons-round text-xl">backup</span> Backup
        </a>
        <a class="p-3 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center gap-3 text-slate-700 dark:text-slate-200" href="logout.php">
            <span class="material-icons-round text-xl">logout</span> Logout
        </a>
    </nav>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const btn = document.getElementById('mobile-menu-btn');
        const menu = document.getElementById('mobile-menu');
        const close = document.getElementById('close-mobile-menu');

        if(btn && menu && close) {
            btn.addEventListener('click', () => {
                menu.classList.remove('hidden');
                menu.classList.add('flex');
                document.body.style.overflow = 'hidden'; // Lock background scrolling
            });
            close.addEventListener('click', () => {
                menu.classList.add('hidden');
                menu.classList.remove('flex');
                document.body.style.overflow = ''; // Unlock background scrolling
            });
        }
    });
</script>
