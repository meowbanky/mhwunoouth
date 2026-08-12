<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}
require_once('Connections/hms.php');
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>

    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Page Header -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-2xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-bold mb-1 flex items-center gap-3">
                            <span class="material-icons-round text-3xl">account_balance</span>
                            Bank Loan Deductions
                        </h1>
                        <p class="text-white/80 text-sm">Money set aside from payroll for bank loans the union does not track</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur-sm p-4 rounded-xl hidden md:block">
                        <span class="material-icons-round text-3xl">receipt_long</span>
                    </div>
                </div>
            </div>

            <!-- Period Selector -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-5 flex flex-col sm:flex-row items-end gap-4">
                <div class="flex-1 w-full">
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                        <span class="material-icons-round text-sm align-middle text-purple-600">calendar_month</span>
                        Payroll Period
                    </label>
                    <select id="periodSelect"
                        class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-purple-500 text-sm outline-none">
                        <option value="">Loading periods...</option>
                    </select>
                </div>
                <button id="exportBtn" disabled
                    class="w-full sm:w-auto px-5 py-2.5 bg-purple-600 hover:bg-purple-700 text-white rounded-xl font-medium transition-colors disabled:opacity-40 disabled:cursor-not-allowed flex items-center justify-center gap-2">
                    <span class="material-icons-round text-base">file_download</span> Export Excel
                </button>
            </div>

            <!-- Reconciliation Summary -->
            <div id="summarySection" class="hidden space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Contributions</p>
                        <p class="text-xl font-bold text-slate-800 dark:text-white" id="totalContribution">₦0.00</p>
                    </div>
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Union Loan Repayments</p>
                        <p class="text-xl font-bold text-slate-800 dark:text-white" id="totalLoan">₦0.00</p>
                    </div>
                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-2xl border border-purple-200 dark:border-purple-800 p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-purple-600 dark:text-purple-400 mb-1">Bank Loans</p>
                        <p class="text-xl font-bold text-purple-700 dark:text-purple-300" id="totalBankLoan">₦0.00</p>
                        <p class="text-xs text-purple-600 dark:text-purple-400 mt-1"><span id="memberCount">0</span> member(s)</p>
                    </div>
                    <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                        <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Gross from Payroll</p>
                        <p class="text-xl font-bold text-primary" id="totalGross">₦0.00</p>
                    </div>
                </div>

                <!-- The reconciliation line: the whole point of the record -->
                <div id="balanceBanner" class="rounded-2xl border p-5 flex items-start gap-3"></div>
            </div>

            <!-- Detail Table -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between gap-4 flex-wrap">
                    <h3 class="font-bold text-slate-800 dark:text-slate-200">Members with a Bank Loan Deduction</h3>
                    <input type="text" id="searchInput" placeholder="Search..." disabled
                        class="px-3 py-2 border border-slate-300 dark:border-slate-700 rounded-xl text-sm bg-white dark:bg-slate-800 text-slate-800 dark:text-white focus:ring-2 focus:ring-purple-500/20 outline-none">
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Member ID</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contribution (₦)</th>
                                <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Union Loan (₦)</th>
                                <th class="px-6 py-4 text-xs font-bold text-right text-purple-500 dark:text-purple-400 uppercase tracking-wider">Bank Loan (₦)</th>
                                <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Gross (₦)</th>
                            </tr>
                        </thead>
                        <tbody id="reportTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr><td colspan="6" class="p-10 text-center text-slate-400">Select a payroll period to begin.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <footer class="py-4 text-[11px] text-slate-400 font-medium uppercase tracking-widest">
                <p>© <?php echo date('Y'); ?> MHWUN OOUTH Branch. All Rights Reserved.</p>
            </footer>
        </div>
    </div>
</main>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    let reportRows = [];
    let selectedPeriodName = '';

    $(document).ready(loadPeriods);

    function loadPeriods() {
        $.post('contribution_api.php', { action: 'fetch_periods' }, function(res) {
            const sel = $('#periodSelect');
            sel.empty().append('<option value="">— Select Period —</option>');
            if (res.status === 'success') {
                res.data.forEach(p => {
                    sel.append(`<option value="${p.Periodid}">${p.PayrollPeriod} (${p.PhysicalYear})</option>`);
                });
            } else {
                Swal.fire('Error', res.message || 'Failed to load periods', 'error');
            }
        }, 'json');
    }

    $('#periodSelect').on('change', function() {
        const periodId = $(this).val();
        selectedPeriodName = $(this).find('option:selected').text();

        if (!periodId) {
            resetReport();
            return;
        }
        loadReport(periodId);
    });

    function resetReport() {
        reportRows = [];
        $('#summarySection').addClass('hidden');
        $('#exportBtn').prop('disabled', true);
        $('#searchInput').prop('disabled', true).val('');
        $('#reportTableBody').html('<tr><td colspan="6" class="p-10 text-center text-slate-400">Select a payroll period to begin.</td></tr>');
    }

    function loadReport(periodId) {
        $('#reportTableBody').html('<tr><td colspan="6" class="p-10 text-center text-slate-400">Loading...</td></tr>');

        $.post('contribution_api.php', { action: 'bank_loan_report', period_id: periodId }, function(res) {
            if (res.status !== 'success') {
                Swal.fire('Error', res.message || 'Failed to load report', 'error');
                resetReport();
                return;
            }

            reportRows = res.data.rows || [];
            renderSummary(res.data);
            renderTable(reportRows);

            $('#exportBtn').prop('disabled', reportRows.length === 0);
            $('#searchInput').prop('disabled', reportRows.length === 0);
        }, 'json').fail(function() {
            Swal.fire('Error', 'Server communication error.', 'error');
            resetReport();
        });
    }

    function renderSummary(data) {
        const t = data.totals;
        $('#summarySection').removeClass('hidden');
        $('#totalContribution').text(money(t.total_contribution));
        $('#totalLoan').text(money(t.total_loan));
        $('#totalBankLoan').text(money(t.total_bank_loan));
        $('#totalGross').text(money(t.total_gross));
        $('#memberCount').text(data.member_count);

        const banner = $('#balanceBanner');
        if (data.is_balanced) {
            banner.attr('class', 'rounded-2xl border p-5 flex items-start gap-3 bg-emerald-50 dark:bg-emerald-900/20 border-emerald-200 dark:border-emerald-800');
            banner.html(`
                <span class="material-icons-round text-emerald-500">check_circle</span>
                <div class="text-sm">
                    <p class="font-bold text-emerald-800 dark:text-emerald-300">Period reconciles with payroll</p>
                    <p class="text-emerald-700 dark:text-emerald-400 mt-1">
                        ${money(t.total_contribution)} contributions + ${money(t.total_loan)} union loans + ${money(t.total_bank_loan)} bank loans = ${money(t.total_gross)} gross.
                    </p>
                </div>`);
        } else {
            banner.attr('class', 'rounded-2xl border p-5 flex items-start gap-3 bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-800');
            banner.html(`
                <span class="material-icons-round text-red-500">error</span>
                <div class="text-sm">
                    <p class="font-bold text-red-800 dark:text-red-300">Period does not reconcile — off by ${money(Math.abs(data.difference))}</p>
                    <p class="text-red-700 dark:text-red-400 mt-1">
                        The parts add up to ${money(t.total_contribution + t.total_loan + t.total_bank_loan)} but payroll sent ${money(t.total_gross)}.
                        This usually means a record was edited without the total being updated, or the period predates bank loan tracking.
                    </p>
                </div>`);
        }
    }

    function renderTable(rows) {
        const tbody = $('#reportTableBody');

        if (rows.length === 0) {
            tbody.html('<tr><td colspan="6" class="p-10 text-center text-slate-400">No bank loan deductions recorded for this period.</td></tr>');
            return;
        }

        tbody.html(rows.map(r => `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <td class="px-6 py-4 font-mono text-sm">${r.membersid}</td>
                <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">${r.fullname}</td>
                <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-300">${money(r.contribution)}</td>
                <td class="px-6 py-4 text-right font-mono text-slate-600 dark:text-slate-300">${money(r.loan)}</td>
                <td class="px-6 py-4 text-right font-mono font-bold text-purple-600 dark:text-purple-400">${money(r.bank_loan)}</td>
                <td class="px-6 py-4 text-right font-mono text-slate-500 dark:text-slate-400">${money(r.gross_deduction)}</td>
            </tr>`).join(''));
    }

    $('#searchInput').on('input', function() {
        const q = $(this).val().toLowerCase().trim();
        renderTable(q
            ? reportRows.filter(r => String(r.membersid).toLowerCase().includes(q) || String(r.fullname).toLowerCase().includes(q))
            : reportRows);
    });

    $('#exportBtn').on('click', function() {
        if (!reportRows.length) return;

        const rows = reportRows.map((r, i) => ({
            'S/N': i + 1,
            'Member ID': r.membersid,
            'Name': r.fullname,
            'Contribution (N)': parseFloat(r.contribution),
            'Union Loan (N)': parseFloat(r.loan),
            'Bank Loan (N)': parseFloat(r.bank_loan),
            'Gross (N)': parseFloat(r.gross_deduction),
        }));

        const wb = XLSX.utils.book_new();
        const ws = XLSX.utils.json_to_sheet(rows);
        ws['!cols'] = [{wch:5},{wch:14},{wch:40},{wch:18},{wch:18},{wch:18},{wch:16}];
        XLSX.utils.book_append_sheet(wb, ws, 'Bank Loan Deductions');
        XLSX.writeFile(wb, `BankLoans_${selectedPeriodName.replace(/[^a-zA-Z0-9]/g, '_')}.xlsx`);
    });

    function money(amount) {
        return '₦' + parseFloat(amount || 0).toLocaleString('en-NG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
</script>

<?php include 'includes/footer.php'; ?>
