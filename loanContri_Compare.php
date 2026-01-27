<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Tailwind Config Extension -->
<script>
    tailwind.config.theme.extend.colors['surface-light'] = '#ffffff';
    tailwind.config.theme.extend.colors['surface-dark'] = '#1e293b';
</script>

<div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-200">
    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <main class="w-full flex-grow p-6 md:p-8">
        <header class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Loan Contribution Comparison</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Real-time analysis of staff loan balances vs deduction amounts.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="flex items-center gap-2 bg-primary hover:bg-sky-600 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-lg shadow-primary/20">
                    <span class="material-icons-round text-[20px]">print</span>
                    <span>Print Report</span>
                </button>
            </div>
        </header>

        <!-- Stats Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <!-- Total Loan Balance -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                        <span class="material-icons-round text-primary">account_balance_wallet</span>
                    </div>
                </div>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium uppercase tracking-wider">Total Loan Balance</p>
                <h3 class="text-2xl font-bold mt-1" id="statTotalBalance">...</h3>
            </div>

            <!-- Total Repayment -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-teal-100 dark:bg-teal-900/30 rounded-xl flex items-center justify-center">
                        <span class="material-icons-round text-secondary">payments</span>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded bg-green-100 dark:bg-green-900/30 text-green-600">Standard</span>
                </div>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium uppercase tracking-wider">Total Monthly Deduction</p>
                <h3 class="text-2xl font-bold mt-1" id="statTotalRepayment">...</h3>
            </div>

            <!-- Pending Reductions -->
            <div class="bg-surface-light dark:bg-surface-dark p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
                        <span class="material-icons-round text-orange-600">error_outline</span>
                    </div>
                    <span class="text-xs font-semibold px-2 py-1 rounded bg-red-100 dark:bg-red-900/30 text-red-600">Requires Action</span>
                </div>
                <p class="text-slate-500 dark:text-slate-400 text-sm font-medium uppercase tracking-wider">Reduce Repayment</p>
                <h3 class="text-2xl font-bold mt-1" id="statPendingReductions">...</h3>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="relative w-full md:w-auto flex-1">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                    <input id="tableSearch" class="pl-10 pr-4 py-2 bg-background-light dark:bg-background-dark border-transparent focus:ring-primary rounded-xl text-sm w-full md:w-64" placeholder="Search staff or name..." type="text"/>
                </div>
                <select id="filterStatus" class="bg-background-light dark:bg-background-dark border-transparent focus:ring-primary rounded-xl text-sm py-2 px-4">
                    <option value="All">All Statuses</option>
                    <option value="Normal">Normal</option>
                    <option value="Reduce Repayment">Reduce Repayment</option>
                </select>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
            <div class="overflow-x-auto custom-scrollbar">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Staff No.</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Member Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Loan Balance</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-right">Loan Repayment</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Remark</th>
                        </tr>
                    </thead>
                    <tbody id="comparisonTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <!-- Rows injected via JS -->
                         <tr>
                             <td colspan="5" class="px-6 py-10 text-center text-slate-500">
                                 <div class="flex flex-col items-center justify-center">
                                     <span class="material-icons-round text-4xl mb-2 animate-spin">refresh</span>
                                     <p>Loading analytics...</p>
                                 </div>
                             </td>
                         </tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination (Visual Only for now) -->
            <div class="px-6 py-4 bg-slate-50 dark:bg-slate-800/30 border-t border-slate-200 dark:border-slate-800 flex flex-col md:flex-row items-center justify-between gap-4">
                <span class="text-sm text-slate-500 dark:text-slate-400" id="showingText">Showing 0 entries</span>
            </div>
        </div>

    </main>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    
    // Formatting currency
    const fmt = (val) => '₦ ' + parseFloat(val || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });
    
    let allData = [];

    // 1. Fetch Data
    function loadData() {
        $.ajax({
            url: 'loanContri_api.php',
            type: 'POST',
            data: { action: 'fetch_comparison' },
            dataType: 'json',
            success: function(res) {
                if(res.status === 'success') {
                    allData = res.data.list;

                    // Update Top Cards
                    $('#statTotalBalance').text(fmt(res.data.summary.total_loan_balance));
                    $('#statTotalRepayment').text(fmt(res.data.summary.total_repayment));
                    $('#statPendingReductions').text(res.data.summary.pending_reductions + ' Members');

                    renderTable(allData);
                } else {
                    alert('Error: ' + res.message);
                }
            },
            error: function() {
                alert('Failed to load data.');
            }
        });
    }

    // 2. Render Table
    function renderTable(data) {
        const tbody = $('#comparisonTableBody');
        tbody.empty();

        if(data.length === 0) {
            tbody.html('<tr><td colspan="5" class="px-6 py-4 text-center text-slate-500">No records found.</td></tr>');
            $('#showingText').text('Showing 0 entries');
            return;
        }

        data.forEach(row => {
            // Logic for status styling
            let statusBadge = '';
            let rowClass = 'hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors';
            
            if (row.status === 'Reduce Repayment') {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-orange-100 dark:bg-orange-900/30 text-orange-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span> Reduce Repayment
                </span>`;
                rowClass = 'bg-orange-50/30 dark:bg-orange-900/10 hover:bg-orange-50/50 dark:hover:bg-orange-900/20 transition-colors';
            } else {
                statusBadge = `<span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Normal
                </span>`;
            }

            const tr = `
                <tr class="${rowClass}">
                    <td class="px-6 py-4 font-semibold text-slate-600 dark:text-slate-400 text-sm">${row.staff_no}</td>
                    <td class="px-6 py-4"><div class="font-medium text-slate-800 dark:text-slate-200">${row.name}</div></td>
                    <td class="px-6 py-4 text-right font-mono text-sm font-medium text-slate-700 dark:text-slate-300">${fmt(row.loan_balance)}</td>
                    <td class="px-6 py-4 text-right font-mono text-sm font-medium ${row.status === 'Reduce Repayment' ? 'text-orange-600 font-bold' : 'text-slate-700 dark:text-slate-300'}">${fmt(row.loan_repayment)}</td>
                    <td class="px-6 py-4">${statusBadge}</td>
                </tr>
            `;
            tbody.append(tr);
        });

        $('#showingText').text(`Showing ${data.length} entries`);
    }

    // 3. Filtering
    function filterData() {
        const term = $('#tableSearch').val().toLowerCase();
        const stat = $('#filterStatus').val();

        const filtered = allData.filter(item => {
            const matchesSearch = item.name.toLowerCase().includes(term) || item.staff_no.toString().includes(term);
            const matchesStatus = stat === 'All' || item.status === stat;
            return matchesSearch && matchesStatus;
        });

        renderTable(filtered);
    }

    $('#tableSearch').on('keyup', filterData);
    $('#filterStatus').on('change', filterData);

    // Initial Load
    loadData();

});
</script>

<?php include 'includes/footer.php'; ?>
