<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden bg-background-light dark:bg-background-dark text-slate-900 dark:text-slate-100 min-h-screen transition-colors duration-200">
    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <main class="w-full flex-grow p-6 md:p-8">
        <header class="flex flex-col md:flex-row md:items-center justify-between mb-8 gap-4">
            <div>
                <h2 class="text-2xl font-bold tracking-tight">Process Transaction</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Review deductions and process payroll periods.</p>
            </div>
        </header>

        <!-- Process Action Card -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl p-6 mb-8 shadow-sm">
            <h3 class="text-lg font-bold mb-4 flex items-center gap-2">
                <span class="material-icons-round text-primary">play_circle</span>
                Run Process
            </h3>
            <div class="flex flex-col md:flex-row items-end gap-4">
                <div class="w-full md:w-1/3">
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Payroll Period</label>
                    <div class="relative">
                        <select id="PeriodSelect" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                            <option value="na">Loading periods...</option>
                        </select>
                        <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>
                <button onclick="runProcess()" class="w-full md:w-auto px-6 py-2.5 bg-primary hover:bg-sky-600 text-white font-medium rounded-xl shadow-lg shadow-primary/20 transition-all flex items-center justify-center gap-2">
                    <span>Process Period</span>
                    <span class="material-icons-round text-lg">arrow_forward</span>
                </button>
            </div>
        </div>

        <!-- Deductions Table -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center">
                <h3 class="font-bold text-slate-800 dark:text-slate-200">Current Member Deductions</h3>
                <div class="text-sm font-semibold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 px-3 py-1 rounded-full">
                    Grand Total: ₦<span id="grandTotalDisplay">0.00</span>
                </div>
            </div>
            
            <div class="overflow-x-auto custom-scrollbar flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Member ID</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Name</th>
                            <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Contribution (₦)</th>
                            <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Loan Repayment (₦)</th>
                            <th class="px-6 py-4 text-xs font-bold text-right text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total (₦)</th>
                        </tr>
                    </thead>
                    <tbody id="deductionsTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                         <tr><td colspan="5" class="p-6 text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    Page <span id="pagCurrent" class="font-bold">1</span> of <span id="pagTotal" class="font-bold">1</span>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="changePage(-1)" id="btnPrev" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-50 transition-colors">Previous</button>
                    <button onclick="changePage(1)" id="btnNext" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-50 transition-colors">Next</button>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Processing Modal -->
<div id="processingModal" class="fixed inset-0 z-50 hidden" data-backdrop="static">
    <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity"></div>
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-8">
        
        <div class="text-center mb-6">
            <div id="processSpinner" class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary border-r-transparent mb-4"></div>
            <div id="processCompleteIcon" class="hidden inline-flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 text-emerald-600 mb-4">
                <span class="material-icons-round text-4xl">check</span>
            </div>
            
            <h3 class="text-xl font-bold" id="processTitle">Processing Transactions</h3>
            <p class="text-slate-500 dark:text-slate-400 mt-1" id="processStatus">Initializing...</p>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-200 dark:bg-slate-700 h-4 rounded-full overflow-hidden mb-2">
            <div id="progressBar" class="bg-primary h-full transition-all duration-200 ease-out" style="width: 0%"></div>
        </div>
        <div class="flex justify-between text-xs font-semibold text-slate-500 uppercase tracking-wide mb-6">
            <span id="progressCount">0 / 0</span>
            <span id="progressPercent">0%</span>
        </div>

        <!-- Log Area (Hidden initially, shown on error or completion) -->
        <div id="processLog" class="hidden h-32 overflow-y-auto bg-slate-50 dark:bg-slate-950 rounded-lg p-3 font-mono text-xs text-slate-600 dark:text-slate-400 mb-6 border border-slate-200 dark:border-slate-800">
            <!-- Logs go here -->
        </div>

        <div class="flex justify-center">
            <button id="btnCloseProcess" onclick="closeProcessModal()" class="hidden px-6 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-medium rounded-xl hover:opacity-90 transition-all">
                Close & Refresh
            </button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    let currentPage = 1;
    let totalPages = 1;

    $(document).ready(function() {
        loadPeriods();
        loadDeductions(1);
    });

    function loadPeriods() {
        $.post('process2_api.php', { action: 'fetch_periods' }, function(res) {
            if (res.status === 'success') {
                const select = $('#PeriodSelect');
                select.empty();
                select.append('<option value="na">Select Period</option>');
                res.data.forEach(p => {
                    select.append(`<option value="${p.Periodid}">${p.PayrollPeriod}</option>`);
                });
            }
        }, 'json');
    }

    function loadDeductions(page) {
        $.post('process2_api.php', { action: 'fetch_deductions', page: page, limit: 20 }, function(res) {
            if (res.status === 'success') {
                renderTable(res.data.list);
                $('#grandTotalDisplay').text(res.data.grand_total);
                
                currentPage = res.data.pagination.current_page;
                totalPages = res.data.pagination.total_pages;
                updatePagination();
            }
        }, 'json');
    }

    function renderTable(rows) {
        const tbody = $('#deductionsTableBody');
        tbody.empty();
        
        if (rows.length === 0) {
            tbody.html('<tr><td colspan="5" class="p-6 text-center text-slate-500">No records found.</td></tr>');
            return;
        }

        rows.forEach(row => {
            const tr = `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                    <td class="px-6 py-4 font-mono text-sm">${row.patientid}</td>
                    <td class="px-6 py-4 font-medium text-slate-900 dark:text-slate-100">${row.fullname}</td>
                    <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300 font-mono">${row.contribution_fmt}</td>
                    <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300 font-mono">${row.loan_fmt}</td>
                    <td class="px-6 py-4 text-right font-bold text-slate-800 dark:text-white font-mono">${row.total_fmt}</td>
                </tr>
            `;
            tbody.append(tr);
        });
    }

    function updatePagination() {
        $('#pagCurrent').text(currentPage);
        $('#pagTotal').text(totalPages);
        $('#btnPrev').prop('disabled', currentPage <= 1);
        $('#btnNext').prop('disabled', currentPage >= totalPages);
    }

    window.changePage = function(delta) {
        const newPage = currentPage + delta;
        if (newPage >= 1 && newPage <= totalPages) {
            loadDeductions(newPage);
        }
    }

    // --- Processing Logic ---

    window.runProcess = async function() {
        const periodId = $('#PeriodSelect').val();
        if (periodId === 'na' || !periodId) {
            Swal.fire({
                icon: 'error',
                title: 'Selection Required',
                text: 'Please select a Period to Process Transaction',
                confirmButtonColor: '#0ea5e9'
            });
            return;
        }

        const result = await Swal.fire({
            title: 'Are you sure?',
            text: "You are about to process transactions for this period. This action cannot be easily undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ea5e9',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, process it!'
        });

        if (!result.isConfirmed) return;

        // Reset UI
        $('#processingModal').removeClass('hidden');
        $('#processSpinner').removeClass('hidden');
        $('#processCompleteIcon').addClass('hidden');
        $('#btnCloseProcess').addClass('hidden');
        $('#processLog').addClass('hidden').empty();
        $('#progressBar').css('width', '0%');
        $('#processTitle').text('Preparing Batch...');
        $('#processStatus').text('Fetching member list...');

        try {
            // 1. Fetch Members
            const initRes = await $.post('process_transaction_api.php', { 
                action: 'fetch_members_to_process',
                period_id: periodId
            }, null, 'json');

            if (initRes.status !== 'success') throw new Error(initRes.message);

            const memberIds = initRes.data;
            const total = memberIds.length;
            
            if (total === 0) {
                finishProcess(0, 0);
                log('No active members found to process.');
                return;
            }

            $('#processTitle').text('Processing Transactions');
            
            let successCount = 0;
            let failCount = 0;

            // 2. Loop & Process
            for (let i = 0; i < total; i++) {
                const memberId = memberIds[i];
                const displayIdx = i + 1;
                
                // Update UI
                const percent = Math.round((displayIdx / total) * 100);
                $('#progressBar').css('width', percent + '%');
                $('#progressCount').text(`${displayIdx} / ${total}`);
                $('#progressPercent').text(`${percent}%`);
                $('#processStatus').text(`Processing Member ID: ${memberId}...`);

                try {
                    const procRes = await $.post('process_transaction_api.php', {
                        action: 'process_member',
                        member_id: memberId,
                        period_id: periodId
                    }, null, 'json');

                    if (procRes.status === 'success') {
                        successCount++;
                    } else {
                        throw new Error(procRes.message);
                    }
                } catch (err) {
                    failCount++;
                    log(`Error Member ${memberId}: ${err.message || err}`);
                    $('#processLog').removeClass('hidden'); // Show log if errors occur
                }
            }

            finishProcess(successCount, failCount);

        } catch (e) {
            alert('Critical Error: ' + e.message);
            $('#processingModal').addClass('hidden');
        }
    }

    function finishProcess(success, fail) {
        $('#processSpinner').addClass('hidden');
        $('#processCompleteIcon').removeClass('hidden');
        $('#processTitle').text('Processing Complete');
        
        let msg = `Successfully processed ${success} transactions.`;
        if (fail > 0) msg += ` Failed: ${fail}. Check logs below.`;
        
        $('#processStatus').text(msg);
        $('#btnCloseProcess').removeClass('hidden');
    }

    function log(msg) {
        const div = $('<div>').text(`[${new Date().toLocaleTimeString()}] ${msg}`);
        $('#processLog').append(div);
    }

    window.closeProcessModal = function() {
        $('#processingModal').addClass('hidden');
        loadDeductions(1); // Refresh table to show new balances (if logic updates them immediately, though deductions table reads from contributions, not mastertransaction. But good practice.)
    }
</script>

<?php include 'includes/footer.php'; ?>
