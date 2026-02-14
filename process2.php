<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
require_once('Connections/hms.php');
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
                    <div class="space-y-4">
                        <!-- Scope Selection -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Process Scope</label>
                                <select id="MemberScope" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                                    <option value="all">All Active Members</option>
                                    <option value="single">Single Member</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Period Mode</label>
                                <select id="PeriodScope" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                                    <option value="single">Single Period</option>
                                    <option value="range">Period Range</option>
                                </select>
                            </div>
                        </div>

                        <!-- Dynamic Inputs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Member Select (Hidden by default) -->
                            <div id="MemberSelectContainer" class="hidden md:col-span-2">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Member</label>
                                <select id="MemberSelect" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                                    <option value="">Loading members...</option>
                                </select>
                            </div>

                            <!-- Start Period (Only for Range) -->
                            <div id="StartPeriodContainer" class="hidden">
                                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">From Period</label>
                                <select id="StartPeriodSelect" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                                    <option value="">Select Start</option>
                                </select>
                            </div>

                            <!-- End Period / Single Period -->
                            <div class="w-full">
                                <label id="EndPeriodLabel" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Select Period</label>
                                <div class="relative">
                                    <select id="PeriodSelect" class="w-full pl-4 pr-10 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all appearance-none cursor-pointer">
                                        <option value="na">Loading periods...</option>
                                    </select>
                                    <span class="material-icons-round absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                                </div>
                            </div>
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
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<style>
    /* Select2 Tailwind Overrides */
    .select2-container .select2-selection--single {
        height: 46px !important;
        border-radius: 0.75rem !important;
        border-color: #e2e8f0 !important;
        background-color: #f8fafc !important;
        display: flex;
        align-items: center;
    }
    .dark .select2-container .select2-selection--single {
        background-color: #0f172a !important;
        border-color: #334155 !important;
        color: #f1f5f9 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #334155 !important;
        padding-left: 1rem !important;
        font-size: 0.875rem !important;
    }
    .dark .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #e2e8f0 !important;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 44px !important;
        right: 10px !important;
    }
    .select2-dropdown {
        border-radius: 0.75rem !important;
        border-color: #e2e8f0 !important;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1) !important;
    }
    .dark .select2-dropdown {
        background-color: #1e293b !important;
        border-color: #334155 !important;
        color: #f1f5f9 !important;
    }
    .select2-search__field {
        border-radius: 0.5rem !important;
    }
</style>
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
                const startSelect = $('#StartPeriodSelect');
                
                select.empty().append('<option value="na">Select Period</option>');
                startSelect.empty().append('<option value="">Select Start</option>');
                
                // Sort periods chronologically for range logic if needed, but ID DESC is usually fine if we handle logic correctly.
                // Actually, for "From -> To", it might be easier if we have the list.
                // Let's keep the raw data for logic usage
                window.allPeriods = res.data; 

                res.data.forEach(p => {
                    select.append(`<option value="${p.Periodid}">${p.PayrollPeriod}</option>`);
                    startSelect.append(`<option value="${p.Periodid}">${p.PayrollPeriod}</option>`);
                });
            }
        }, 'json');
    }

    // Load Members logic
    function loadMembers() {
        if ($('#MemberSelect option').length > 1) return; // Already loaded

        $.post('process2_api.php', { action: 'fetch_all_members' }, function(res) {
            if (res.status === 'success') {
                const select = $('#MemberSelect');
                select.empty().append('<option value="">Select a Member</option>');
                res.data.forEach(m => {
                    select.append(`<option value="${m.patientid}">${m.fullname} (${m.patientid})</option>`);
                });
                
                // Initialize Select2
                $('#MemberSelect').select2({
                    width: '100%',
                    placeholder: "Search for a member...",
                    allowClear: true
                });
            }
        }, 'json');
    }

    // UI Event Listeners
    $('#MemberScope').change(function() {
        if ($(this).val() === 'single') {
            $('#MemberSelectContainer').removeClass('hidden');
            loadMembers();
        } else {
            $('#MemberSelectContainer').addClass('hidden');
        }
    });

    $('#PeriodScope').change(function() {
        if ($(this).val() === 'range') {
            $('#StartPeriodContainer').removeClass('hidden');
            $('#EndPeriodLabel').text('To Period');
        } else {
            $('#StartPeriodContainer').addClass('hidden');
            $('#EndPeriodLabel').text('Select Period');
        }
    });

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
        const memberScope = $('#MemberScope').val();
        const periodScope = $('#PeriodScope').val();
        
        // 1. Resolve Periods
        let targetPeriods = [];
        const endPeriodId = $('#PeriodSelect').val();

        if (endPeriodId === 'na' || !endPeriodId) {
            Swal.fire({ icon: 'error', title: 'Selection Required', text: 'Please select a Period.' });
            return;
        }

        if (periodScope === 'single') {
            targetPeriods.push(endPeriodId);
        } else {
            const startPeriodId = $('#StartPeriodSelect').val();
            if (!startPeriodId) {
                Swal.fire({ icon: 'error', title: 'Selection Required', text: 'Please select a Start Period.' });
                return;
            }
            
            // Logic: Find all periods between start and end (inclusive)
            // Assuming IDs are sequential or at least ordered via window.allPeriods
            // We need to find the indices in window.allPeriods (which is DESC order usually)
            
            const pList = window.allPeriods; // defined in loadPeriods
            const idxStart = pList.findIndex(p => p.Periodid == startPeriodId);
            const idxEnd = pList.findIndex(p => p.Periodid == endPeriodId);

            if (idxStart === -1 || idxEnd === -1) {
                Swal.fire({ icon: 'error', title: 'Error', text: 'Invalid Period Selection.' });
                return;
            }

            // In DESC list: [105, 104, 103, 102]. Start=102 (idx 3), End=104 (idx 1). 
            // Range is min(idxStart, idxEnd) to max(idxStart, idxEnd)
            // But usually User selects Start (Oldest) to End (Newest) or vice/versa.
            // We'll just take the range between them.
            
            const minIdx = Math.min(idxStart, idxEnd);
            const maxIdx = Math.max(idxStart, idxEnd);
            
            // Extract and map just IDs
            for (let i = minIdx; i <= maxIdx; i++) {
                targetPeriods.push(pList[i].Periodid);
            }
        }

        // 2. Resolve Members
        let targetMembers = []; // If empty, means "ALL" - but better to make it explicit if we can.
                                // Actually, existing logic fetches "All Active" on backend if specific list not sent.
                                // But for progress bar accuracy, we MUST know the count beforehand.
                                // So let's modify the flow: 
                                // - If Single Member: [id]
                                // - If All Members: Call 'fetch_members_to_process' ONCE to get the big list.
        
        const singleMemberId = $('#MemberSelect').val();
        if (memberScope === 'single') {
            if (!singleMemberId) {
                Swal.fire({ icon: 'error', title: 'Selection Required', text: 'Please select a Member.' });
                return;
            }
            targetMembers = [singleMemberId];
        }

        // CONFIRMATION
        const periodText = periodScope === 'single' ? 'this period' : `${targetPeriods.length} periods`;
        const memberText = memberScope === 'single' ? '1 member' : 'ALL members';
        
        const result = await Swal.fire({
            title: 'Are you sure?',
            text: `Process transactions for ${memberText} across ${periodText}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#0ea5e9',
            cancelButtonColor: '#ef4444',
            confirmButtonText: 'Yes, process it!'
        });

        if (!result.isConfirmed) return;

        // INIT UI
        $('#processingModal').removeClass('hidden');
        $('#processSpinner').removeClass('hidden');
        $('#processCompleteIcon').addClass('hidden');
        $('#btnCloseProcess').addClass('hidden');
        $('#processLog').addClass('hidden').empty();
        $('#progressBar').css('width', '0%');
        $('#processTitle').text('Preparing Batch...');
        
        try {
            // Fetch ALL members if scope is 'all'
            if (memberScope === 'all') {
                $('#processStatus').text('Fetching full member list...');
                // We pick the FIRST period ID to initialize the list? 
                // Or just use any valid ID? The logic in backend just queries "Active" members, period_id wasn't strictly used for the LIST itself in process.php logic (it was just WHERE Status='Active').
                // But my API implementation on line 30 of process_transaction_api.php doesn't use period_id for fetching members.
                
                const initRes = await $.post('process_transaction_api.php', { 
                    action: 'fetch_members_to_process',
                    period_id: targetPeriods[0] // just to satisfy param requirement if any
                }, null, 'json');

                if (initRes.status !== 'success') throw new Error(initRes.message);
                targetMembers = initRes.data;
            }

            // MAIN LOOP
            const totalOps = targetPeriods.length * targetMembers.length;
            if (totalOps === 0) {
                finishProcess(0, 0);
                log('Nothing to process.');
                return;
            }

            let currentOp = 0;
            let successCount = 0;
            let failCount = 0;

            $('#processTitle').text('Processing Transactions');

            // Iterate Periods -> then Members? Or Members -> then Periods?
            // Usually safest to finish one period completely before moving to next?
            // "starting from the lower period id to the last selected period id"
            // We should sort targetPeriods ASC (Oldest first) just to be safe so logic flows naturally.
            
            targetPeriods.sort((a, b) => a - b); // Ascending numeric sort

            for (const pId of targetPeriods) {
                for (const mId of targetMembers) {
                    currentOp++;
                    
                    // Update UI
                    const percent = Math.round((currentOp / totalOps) * 100);
                    $('#progressBar').css('width', percent + '%');
                    $('#progressCount').text(`${currentOp} / ${totalOps}`);
                    $('#progressPercent').text(`${percent}%`);
                    $('#processStatus').text(`Pd: ${pId} | Mem: ${mId}`);

                    try {
                        const procRes = await $.post('process_transaction_api.php', {
                            action: 'process_member',
                            member_id: mId,
                            period_id: pId
                        }, null, 'json');

                        if (procRes.status === 'success') {
                            successCount++;
                        } else {
                            throw new Error(procRes.message);
                        }
                    } catch (err) {
                        failCount++;
                        // Only log failures to avoid spamming the log area
                        // log(`Err P:${pId} M:${mId} -> ${err.message || err}`); 
                        // Actually, user might want to see errors.
                        log(`[${pId}-${mId}] Error: ${err.message || err}`);
                        $('#processLog').removeClass('hidden');
                    }
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
        
        // Calculate distinct periods count for summary
        
        let msg = `Completed. Success: ${success}, Failed: ${fail}.`;
        $('#processStatus').text(msg);
        $('#btnCloseProcess').removeClass('hidden');
    }

    function log(msg) {
        const div = $('<div>').text(msg);
        $('#processLog').append(div);
    }

    window.closeProcessModal = function() {
        $('#processingModal').addClass('hidden');
        loadDeductions(1); 
    }
</script>

<?php include 'includes/footer.php'; ?>
