<?php
// Ensure session is started and user is logged in
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');

// --- Data Fetching Logic ---

// 1. Active Members (using tbl_personalinfo, assuming 'Status' column exists or just counting all)
// Note: Schema says `Status` varchar(10) in tbl_personalinfo.
$activeMembers = 0;
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM tbl_personalinfo WHERE Status = 'Active'");
    $activeMembers = $stmt->fetchColumn();
    // Fallback if 0 (maybe status is different in older data, just count all for now to be safe if 0)
    if ($activeMembers == 0) {
        $stmt = $conn->query("SELECT COUNT(*) FROM tbl_personalinfo");
        $activeMembers = $stmt->fetchColumn();
    }
} catch (PDOException $e) {
    // Handle error silently or log
}

// 2. Total Savings (using tbl_contributions)
$totalSavings = 0;
try {
    $stmt = $conn->query("SELECT SUM(contribution) FROM tbl_contributions");
    $totalSavings = $stmt->fetchColumn();
} catch (PDOException $e) {}

// 3. Active Loans (using tbl_loan)
$totalLoans = 0;
try {
    $stmt = $conn->query("SELECT SUM(loanamount) FROM tbl_loan");
    $totalLoans = $stmt->fetchColumn();
} catch (PDOException $e) {}

// 4. Growth Rate (Mocking for now as complex logic is needed, or we can calculate based on recent registrations)
$growthRate = "2.5%"; // Placeholder

// 5. Recent Transactions (Ledger)
$recentTransactions = [];
try {
    // Joining with tbl_personalinfo to get names. 
    // Assuming memberid in transaction maps to patientid in personalinfo
    $sql = "SELECT t.transactionid, t.memberid, t.Contribution, t.loanAmount, t.loanRepayment, t.withdrawal, t.interest,
                   p.Fname, p.Lname, p.Mname
            FROM tlb_mastertransaction t
            LEFT JOIN tbl_personalinfo p ON t.memberid = p.patientid
            ORDER BY t.transactionid DESC LIMIT 5";
    $stmt = $conn->query($sql);
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {}

// 6. Gender Distribution
$genderStats = ['Male' => 0, 'Female' => 0];
try {
    $stmt = $conn->query("SELECT gender, COUNT(*) as count FROM tbl_personalinfo WHERE Status = 'Active' GROUP BY gender");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Normalize gender string title case
        $g = ucfirst(strtolower(trim($row['gender'])));
        if (isset($genderStats[$g])) {
            $genderStats[$g] = $row['count'];
        } else {
            // Fallback for variations like 'M', 'F' if needed, or just add key
            $genderStats[$g] = $row['count'];
        }
    }
} catch (PDOException $e) {}

// 7. Loan Debt (Total Loan + Int - Repayment)
$loanDebt = 0;
try {
    $stmt = $conn->query("SELECT (SUM(loanAmount) + SUM(interest)) - SUM(loanRepayment) as LoanDebt FROM tlb_mastertransaction");
    $loanDebt = $stmt->fetchColumn();
} catch (PDOException $e) {}

// 8. SMS Balance (External API)
// 8. SMS Balance (External API)
require_once('NotificationService.php');
use class\services\NotificationService;

$smsBalance = 0;
// Note: Dashboard doesn't already have $hms or NotificationService instantiated? 
// $conn (PDO) is available from 'Connections/hms.php' included at top.
try {
    $notificationService = new NotificationService($conn);
    $smsBalance = $notificationService->getSMSBalance();
} catch (Exception $e) {
    // defaults to 0
}

// Helper function to format currency
function formatCurrency($amount) {
    return '₦' . number_format($amount, 2);
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden">
    
    <?php include 'includes/topbar.php'; ?>

    <!-- Main Content Area -->
    <div class="flex-1 overflow-y-auto p-8">
        <div class="max-w-7xl mx-auto space-y-8">
            
            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <!-- Active Members -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-blue-50 dark:bg-blue-900/20 text-blue-600 rounded-xl flex items-center justify-center">
                            <span class="material-icons-round">people</span>
                        </div>
                        <span class="text-xs font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full">+2.4%</span>
                    </div>
                    <h3 class="text-slate-500 dark:text-slate-400 text-sm font-medium">Active Members</h3>
                    <p class="text-2xl font-bold mt-1"><?php echo number_format($activeMembers); ?></p>
                </div>

                <!-- Total Savings -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-teal-50 dark:bg-teal-900/20 text-teal-600 rounded-xl flex items-center justify-center">
                            <span class="material-icons-round">account_balance_wallet</span>
                        </div>
                        <span class="text-xs font-medium text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-2 py-1 rounded-full">+5.1%</span>
                    </div>
                    <h3 class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Savings</h3>
                    <p class="text-2xl font-bold mt-1"><?php echo formatCurrency($totalSavings); ?></p>
                </div>

                <!-- Active Loans -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-amber-50 dark:bg-amber-900/20 text-amber-600 rounded-xl flex items-center justify-center">
                            <span class="material-icons-round">real_estate_agent</span>
                        </div>
                        <span class="text-xs font-medium text-red-600 bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded-full">-1.2%</span>
                    </div>
                    <h3 class="text-slate-500 dark:text-slate-400 text-sm font-medium">Total Loans (Disbursed)</h3>
                    <p class="text-2xl font-bold mt-1"><?php echo formatCurrency($totalLoans); ?></p>
                </div>

                <!-- Net Loan Debt -->
                <div class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-indigo-50 dark:bg-indigo-900/20 text-indigo-600 rounded-xl flex items-center justify-center">
                            <span class="material-icons-round">account_balance</span>
                        </div>
                        <span class="text-xs font-medium text-amber-600 bg-amber-50 dark:bg-amber-900/20 px-2 py-1 rounded-full">Outstanding</span>
                    </div>
                    <h3 class="text-slate-500 dark:text-slate-400 text-sm font-medium">Net Loan Debt</h3>
                    <p class="text-2xl font-bold mt-1"><?php echo formatCurrency($loanDebt); ?></p>
                </div>
            </div>

            <!-- Main Content Splits -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Center/Left Column (Quick Actions & Table) -->
                <div class="lg:col-span-2 space-y-6">
                    
                    <!-- Quick Actions Header -->
                    <div class="flex items-center justify-between">
                        <h2 class="text-xl font-bold flex items-center space-x-2">
                            <span class="material-icons-round text-primary">auto_awesome</span>
                            <span>Quick Actions</span>
                        </h2>
                        <button class="text-primary text-sm font-semibold hover:underline">View All Tools</button>
                    </div>

                    <!-- Quick Actions Bento Grid -->
                    <div class="bento-grid">
                        <button onclick="window.location.href='registration.php'" class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-primary/50 transition-all text-left flex flex-col items-center justify-center group">
                            <div class="w-14 h-14 bg-blue-100 dark:bg-blue-900/30 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <span class="material-icons-round text-blue-600 text-3xl">person_add</span>
                            </div>
                            <span class="text-sm font-semibold text-center">New Member</span>
                        </button>
                        <button onclick="window.location.href='registration_search.php'" class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-primary/50 transition-all text-left flex flex-col items-center justify-center group">
                            <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900/30 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <span class="material-icons-round text-teal-600 text-3xl">edit_note</span>
                            </div>
                            <span class="text-sm font-semibold text-center">Edit Member</span>
                        </button>
                        <button onclick="window.location.href='addloan.php'" class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-primary/50 transition-all text-left flex flex-col items-center justify-center group">
                            <div class="w-14 h-14 bg-amber-100 dark:bg-amber-900/30 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <span class="material-icons-round text-amber-600 text-3xl">add_card</span>
                            </div>
                            <span class="text-sm font-semibold text-center">Add Loan</span>
                        </button>
                        
                        <button onclick="window.location.href='editContributions.php'" class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm hover:shadow-md hover:border-primary/50 transition-all text-left flex flex-col items-center justify-center group">
                            <div class="w-14 h-14 bg-indigo-100 dark:bg-indigo-900/30 rounded-2xl flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <span class="material-icons-round text-indigo-600 text-3xl">volunteer_activism</span>
                            </div>
                            <span class="text-sm font-semibold text-center">Contributions</span>
                        </button>
                    </div>

                    <!-- Recent Ledger Preview -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl border border-slate-200 dark:border-slate-800 overflow-hidden shadow-sm">
                        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/50">
                            <h3 class="font-bold flex items-center space-x-2">
                                <span class="material-icons-round text-secondary">menu_book</span>
                                <span>Recent Transactions</span>
                            </h3>
                            <button class="text-sm font-medium text-slate-500 hover:text-primary">Download Report</button>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead class="bg-slate-50 dark:bg-slate-800/20 text-xs font-semibold uppercase tracking-wider text-slate-400">
                                    <tr>
                                        <th class="px-6 py-4">Transaction ID</th>
                                        <th class="px-6 py-4">Member</th>
                                        <th class="px-6 py-4">Type</th>
                                        <th class="px-6 py-4">Amount</th>
                                        <!-- <th class="px-6 py-4">Date</th> -->
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                    <?php if (count($recentTransactions) > 0): ?>
                                        <?php foreach ($recentTransactions as $txn): ?>
                                            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                                                <td class="px-6 py-4 text-sm font-mono text-slate-500">TXN-<?php echo $txn['transactionid']; ?></td>
                                                <td class="px-6 py-4 text-sm font-medium">
                                                    <?php echo $txn['Fname'] . ' ' . $txn['Lname']; ?>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <?php 
                                                        if ($txn['Contribution'] > 0) {
                                                            echo '<span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-green-100 dark:bg-green-900/30 text-green-600">Contribution</span>';
                                                        } elseif ($txn['loanAmount'] > 0) {
                                                            echo '<span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-amber-100 dark:bg-amber-900/30 text-amber-600">Loan Disbursed</span>';
                                                        } elseif ($txn['loanRepayment'] > 0) {
                                                            echo '<span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600">Loan Repay</span>';
                                                        } elseif ($txn['withdrawal'] > 0) {
                                                            echo '<span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-red-100 dark:bg-red-900/30 text-red-600">Withdrawal</span>';
                                                        } else {
                                                            echo '<span class="px-2 py-1 text-[10px] font-bold uppercase rounded bg-gray-100 dark:bg-gray-900/30 text-gray-600">Other</span>';
                                                        }
                                                    ?>
                                                </td>
                                                <td class="px-6 py-4 text-sm font-bold">
                                                    <?php 
                                                        $amt = max($txn['Contribution'], $txn['loanAmount'], $txn['loanRepayment'], $txn['withdrawal']);
                                                        echo formatCurrency($amt); 
                                                    ?>
                                                </td>
                                                <!-- <td class="px-6 py-4 text-sm text-slate-500">Jan 22, 2025</td> -->
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-slate-500 text-sm">No recent transactions found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Right Column (Alerts & Stats) -->
                <div class="space-y-8">
                    
                    <!-- SMS Widget -->
                    <div class="bg-primary rounded-3xl p-6 text-white shadow-lg relative overflow-hidden group">
                        <div class="absolute -right-8 -bottom-8 opacity-20 group-hover:scale-110 transition-transform duration-500">
                            <span class="material-icons-round text-[180px]">notifications_active</span>
                        </div>
                        <div class="relative z-10">
                            <div class="flex items-center space-x-2 mb-4">
                                <span class="material-icons-round">campaign</span>
                                <h3 class="font-bold tracking-wide">SMS ALERTS</h3>
                            </div>
                            <p class="text-3xl font-black mb-1"><?php echo formatCurrency($smsBalance); ?></p>
                            <p class="text-primary-100 text-sm opacity-80">Available SMS Balance.</p>
                            <button onclick="window.location.href='bulksms.php'" class="mt-6 w-full py-3 bg-white text-primary font-bold rounded-2xl hover:bg-slate-100 transition-colors shadow-sm">Process Batch Now</button>
                        </div>
                    </div>

                    <!-- Demographics -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <h3 class="font-bold mb-6 flex items-center space-x-2">
                            <span class="material-icons-round text-secondary">pie_chart</span>
                            <span>Member Distribution</span>
                        </h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-500">Female</span>
                                    <span class="font-semibold"><?php echo isset($genderStats['Female']) ? number_format($genderStats['Female']) : 0; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                    <div class="bg-rose-500 h-full w-[60%]"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-slate-500">Male</span>
                                    <span class="font-semibold"><?php echo isset($genderStats['Male']) ? number_format($genderStats['Male']) : 0; ?></span>
                                </div>
                                <div class="w-full bg-slate-100 dark:bg-slate-800 h-2 rounded-full overflow-hidden">
                                    <div class="bg-blue-500 h-full w-[40%]"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Deadlines -->
                    <div class="bg-white dark:bg-slate-900 rounded-3xl p-6 border border-slate-200 dark:border-slate-800 shadow-sm">
                        <h3 class="font-bold mb-4 flex items-center space-x-2">
                            <span class="material-icons-round text-primary">event</span>
                            <span>Deadlines</span>
                        </h3>
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3 p-3 rounded-2xl bg-slate-50 dark:bg-slate-800/50 border border-slate-100 dark:border-slate-700">
                                <div class="bg-white dark:bg-slate-700 p-2 rounded-lg text-center min-w-[40px]">
                                    <p class="text-[10px] text-slate-400 uppercase font-bold"><?php echo date('M'); ?></p>
                                    <p class="text-lg font-bold leading-tight">25</p>
                                </div>
                                <div>
                                    <p class="text-sm font-semibold">Monthly Deduction</p>
                                    <p class="text-xs text-slate-500">Processing starts at 9:00 AM</p>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</main>
<?php include 'includes/footer.php'; ?>