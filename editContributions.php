<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}
require_once('Connections/hms.php');

// Fetch Global Stats
$grand_total_savings = 0;
try {
    $stmt_stats = $conn->query("SELECT SUM(contribution) as total_savings FROM tbl_contributions");
    $stats = $stmt_stats->fetch(PDO::FETCH_ASSOC);
    $grand_total_savings = $stats['total_savings'] ?? 0;
} catch (PDOException $e) { }

// Initial total count for display
$total_active_members = 0;
try {
   $stmtCount = $conn->query("SELECT COUNT(*) as total FROM tbl_personalinfo WHERE Status = 'Active'");
   $total_active_members = $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
} catch(PDOException $e) { }

function format_money($amount) {
    return number_format((float)$amount, 2);
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>
    
    <!-- Period Selector (always visible, full-width) -->
    <div class="px-6 pt-6">
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm p-5 flex flex-col sm:flex-row items-end gap-4">
            <div class="flex-1">
                <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2">
                    <span class="material-icons-round text-sm align-middle text-primary">calendar_month</span>
                    Payroll Period
                </label>
                <select id="periodSelect"
                    class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary text-sm outline-none">
                    <option value="">Loading periods...</option>
                </select>
            </div>
            <div class="bg-primary/5 dark:bg-slate-800 rounded-xl px-4 py-3 text-center min-w-[160px]">
                <p class="text-slate-500 dark:text-slate-400 text-xs font-medium uppercase tracking-wider mb-1">Total Savings (Global)</p>
                <p class="text-xl font-bold text-primary">₦<?php echo format_money($grand_total_savings); ?></p>
            </div>
        </div>
    </div>

    <div class="flex-1 p-6 pt-4 flex gap-6 overflow-hidden">

        <!-- Inner Sidebar: Member Directory -->
        <aside id="memberSidebar" class="w-full lg:w-1/3 min-w-[320px] max-w-[400px] flex flex-col gap-4 h-full lg:flex">
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 flex flex-col h-full overflow-hidden">
                <div class="p-5 border-b border-slate-100 dark:border-slate-800">
                    <h2 class="text-lg font-bold mb-4">Member Directory</h2>
                    
                    <!-- Search Box -->
                    <div class="relative mb-2">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        <input id="directorySearch" class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary text-sm transition-all" placeholder="Search members..." type="text"/>
                    </div>
                </div>
                
                <div class="flex-1 overflow-y-auto custom-scrollbar p-2 relative" id="memberListContainer">
                    <!-- List items will be injected here via JS -->
                    <div class="flex flex-col items-center justify-center h-40 text-slate-400">
                        <span class="material-icons-round animate-spin mb-2">refresh</span>
                        <span class="text-xs">Loading directory...</span>
                    </div>
                </div>
                
                <!-- Pagination Footer -->
                <div class="p-3 bg-slate-50 dark:bg-slate-800/50 border-t border-slate-100 dark:border-slate-800 flex items-center justify-between">
                    <button id="prevPageBtn" class="p-2 rounded-lg hover:bg-white dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors" disabled>
                        <span class="material-icons-round text-slate-500">chevron_left</span>
                    </button>
                    <span class="text-xs font-bold text-slate-500" id="paginationInfo">Page 1</span>
                    <button id="nextPageBtn" class="p-2 rounded-lg hover:bg-white dark:hover:bg-slate-700 disabled:opacity-30 disabled:cursor-not-allowed transition-colors">
                        <span class="material-icons-round text-slate-500">chevron_right</span>
                    </button>
                </div>
            </div>
        </aside>

        <!-- Main Content (Edit Form) -->
        <section id="mainContent" class="hidden lg:flex flex-1 flex-col gap-6 overflow-y-auto custom-scrollbar pr-2 relative w-full">
             <!-- Mobile Back Button -->
             <button onclick="showlist()" class="lg:hidden mb-2 flex items-center gap-2 text-slate-500 font-medium">
                <span class="material-icons-round">arrow_back</span> Back to Directory
             </button>
             <!-- Loader Overlay -->
            <div id="loader" class="absolute inset-0 bg-white/50 dark:bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-10 hidden">
                 <div class="text-primary font-bold flex flex-col items-center">
                      <span class="material-icons-round animate-spin text-3xl">refresh</span>
                      <span class="text-xs mt-2">Loading...</span>
                 </div>
            </div>

            <!-- Empty State (Default) -->
            <div id="emptyState" class="flex-1 flex flex-col items-center justify-center p-10 bg-white dark:bg-slate-900 rounded-2xl border border-dashed border-slate-300 dark:border-slate-700 text-slate-400">
                <div class="w-16 h-16 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mb-4">
                    <span class="material-icons-round text-3xl">touch_app</span>
                </div>
                <h3 class="text-lg font-bold text-slate-700 dark:text-slate-300 mb-2">Select a Member</h3>
                <p class="text-sm text-center max-w-xs">Use the directory list to find a member. You can search by name or ID.</p>
            </div>

            <!-- Edit Form (Hidden by default) -->
            <div id="editFormContainer" class="hidden bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col">
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center flex-wrap gap-4">
                    <div>
                        <h2 class="text-xl font-bold">Edit Contribution</h2>
                        <p class="text-sm text-slate-500 dark:text-slate-400">
                            Modify records for <span id="memberNameDisplay" class="text-slate-900 dark:text-white font-semibold">...</span>
                            &nbsp;·&nbsp; Period: <span id="periodNameDisplay" class="text-primary font-semibold">...</span>
                        </p>
                    </div>
                    <span id="memberIdBadge" class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full text-xs font-bold uppercase">ID: ...</span>
                </div>

                <!-- Already-processed warning: edits here do not rewrite the ledger -->
                <div id="processedWarning" class="hidden mx-8 mt-6 p-4 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-xl flex items-start gap-3">
                    <span class="material-icons-round text-amber-500 mt-0.5">warning</span>
                    <div class="text-sm text-amber-800 dark:text-amber-300">
                        <p class="font-bold">This period has already been processed</p>
                        <p class="mt-1">Saving changes here updates the deduction record, but it does not rewrite the transactions already posted to the ledger for this period. Reverse the posted transaction first if the loan balance needs correcting.</p>
                    </div>
                </div>

                <form id="contributionForm" class="p-8 grid grid-cols-1 md:grid-cols-2 gap-10">
                    <input type="hidden" name="member_id"  id="hiddenMemberId">
                    <input type="hidden" name="period_id"  id="hiddenPeriodId">
                    <input type="hidden" name="action"     value="update_record">
                    <input type="hidden" name="gross_deduction" id="hiddenGross" value="0">

                    <div class="space-y-6">
                        <!-- Contribution Input -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Monthly Contribution (₦)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">₦</span>
                                <input name="contribution_amount" id="contribInput" 
                                       class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary text-lg font-bold transition-all" 
                                       type="text" 
                                       oninput="formatInput(this); rebalanceAllocation();"
                                />
                            </div>
                        </div>

                        <!-- Loan Repayment Input -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Union Loan Repayment (₦)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">₦</span>
                                <input name="loan_repayment" id="loanInput"
                                       class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary text-lg font-bold transition-all"
                                       type="text"
                                       oninput="formatInput(this); calculateTotal();"
                                />
                            </div>
                            <p class="text-xs text-slate-500 mt-1.5">This is the only figure the loan engine repays with.</p>
                        </div>

                        <!-- Bank Loan Input (untracked third-party debt) -->
                        <div class="p-4 bg-purple-50 dark:bg-purple-900/10 border border-purple-200 dark:border-purple-800/50 rounded-xl">
                            <label class="block text-sm font-semibold mb-2 flex items-center gap-2 text-purple-900 dark:text-purple-200">
                                <span class="material-icons-round text-lg">account_balance</span>
                                Bank Loan Deduction (₦)
                            </label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-purple-400">₦</span>
                                <input name="bank_loan" id="bankLoanInput"
                                       class="w-full pl-10 pr-4 py-3 bg-white dark:bg-slate-800 border border-purple-200 dark:border-purple-800 rounded-xl focus:ring-2 focus:ring-purple-500 text-lg font-bold transition-all"
                                       type="text"
                                       oninput="formatInput(this); rebalanceAllocation();"
                                />
                            </div>
                            <p class="text-xs text-purple-700 dark:text-purple-400 mt-1.5">
                                Money deducted from salary for a bank loan the union does not track. Entering an amount here reduces the union loan repayment by the same amount.
                            </p>

                            <label class="flex items-start gap-2 mt-3 cursor-pointer">
                                <input type="checkbox" name="bank_loan_recurring" id="bankLoanRecurring" value="1" checked
                                       onchange="updateRecurringHint();"
                                       class="mt-0.5 w-4 h-4 text-purple-600 border-purple-300 rounded focus:ring-purple-500 cursor-pointer">
                                <span class="text-xs text-purple-800 dark:text-purple-300">
                                    <span class="font-semibold">Recurring deduction</span> — apply this amount automatically on every future API import, until it is set to zero or stopped.
                                </span>
                            </label>

                            <p id="recurringHint" class="hidden mt-2 px-3 py-2 rounded-lg text-xs font-medium"></p>

                            <div id="standingBankLoanRow" class="hidden mt-3 pt-3 border-t border-purple-200 dark:border-purple-800 flex items-center justify-between gap-3">
                                <p class="text-xs text-purple-800 dark:text-purple-300">
                                    Currently recurring: <span id="standingBankLoanAmount" class="font-bold">₦0.00</span>
                                </p>
                                <button type="button" onclick="stopRecurringBankLoan()"
                                        class="px-3 py-1.5 bg-white dark:bg-slate-800 border border-purple-300 dark:border-purple-700 text-purple-700 dark:text-purple-300 rounded-lg text-xs font-semibold hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors flex items-center gap-1">
                                    <span class="material-icons-round text-sm">block</span> Stop recurring
                                </button>
                            </div>
                        </div>

                        <!-- Balance check against the payroll figure -->
                        <div id="balancePanel" class="bg-primary/5 p-4 rounded-xl border border-primary/10 space-y-2">
                            <div class="flex justify-between items-center pb-2 border-b border-primary/10">
                                <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Total from Salary</span>
                                <span class="text-xl font-bold text-primary" id="visualTotal">₦0.00</span>
                            </div>
                            <div class="flex justify-between items-center text-sm">
                                <span class="text-slate-500">Allocated</span>
                                <span class="font-semibold" id="visualAllocated">₦0.00</span>
                            </div>
                            <div id="balanceStatus" class="flex items-center gap-2 text-sm font-semibold pt-1"></div>
                        </div>

                        <div class="pt-4 flex gap-3">
                            <button type="submit" class="flex-1 bg-primary text-white py-3.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                                <span class="material-icons-round text-xl">save</span>
                                Update Record
                            </button>
                            <button type="button" onclick="resetView()" class="px-6 py-3.5 bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400 rounded-xl font-bold hover:bg-slate-200 dark:hover:bg-slate-700 transition-all flex items-center">
                                Close
                            </button>
                        </div>
                    </div>

                    <!-- Side Info Panel -->
                    <div class="bg-slate-50 dark:bg-slate-800/40 rounded-2xl p-6 border border-slate-100 dark:border-slate-800 h-fit space-y-4">
                        <h3 class="font-bold text-sm uppercase tracking-wider text-slate-500 dark:text-slate-400 flex items-center gap-2">
                            <span class="material-icons-round text-lg">info</span>
                            Financial Status
                        </h3>

                        <!-- Loan Balance -->
                        <div class="flex justify-between items-center pb-3 border-b border-slate-200 dark:border-slate-700">
                            <div>
                                <p class="text-sm font-bold opacity-50">Current Loan Balance</p>
                                <p class="text-2xl font-bold text-red-500" id="loanBalanceDisplay">₦0.00</p>
                            </div>
                        </div>

                        <!-- Salary Breakdown -->
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-3">Salary Deduction Breakdown</p>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Contribution</span>
                                    <span id="breakdownContrib" class="font-semibold">₦0.00</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Union Loan Repayment</span>
                                    <span id="breakdownLoan" class="font-semibold">₦0.00</span>
                                </div>
                                <div class="flex justify-between text-purple-600 dark:text-purple-400" id="breakdownBankRow">
                                    <span class="flex items-center gap-1">
                                        <span class="material-icons-round text-sm">account_balance</span> Bank Loan
                                    </span>
                                    <span id="breakdownBank" class="font-semibold">₦0.00</span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-slate-200 dark:border-slate-700 font-bold">
                                    <span>Total from Salary</span>
                                    <span id="breakdownTotal" class="text-primary">₦0.00</span>
                                </div>
                                <div class="flex justify-between text-orange-600 dark:text-orange-400 pt-2 border-t border-dashed border-slate-200 dark:border-slate-700" id="breakdownRefundRow">
                                    <span class="flex items-center gap-1">
                                        <span class="material-icons-round text-sm">undo</span> Refund issued
                                    </span>
                                    <span id="breakdownRefund" class="font-semibold">₦0.00</span>
                                </div>
                            </div>
                        </div>

                        <!-- Refund Records -->
                        <div id="refundSection" class="hidden">
                            <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-2">Refund Records</p>
                            <div id="refundList" class="space-y-2"></div>
                        </div>

                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-xs rounded-lg space-y-2">
                            <p><span class="font-bold">Note:</span> Increasing the union loan repayment reduces the loan balance faster during the next payroll cycle.</p>
                            <p>Bank loan money is set aside and never touches the loan balance — but it is recorded here so the period reconciles against payroll.</p>
                        </div>
                    </div>
                </form>
            </div>

            <footer class="mt-auto py-4 flex justify-between items-center text-[11px] text-slate-400 font-medium uppercase tracking-widest">
                <p>© <?php echo date('Y'); ?> MHWUN OOUTH Branch. All Rights Reserved.</p>
            </footer>
        </section>
    </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script>
    // Global State
    let currentPage = 1;
    let totalPages = 1;
    let currentSearch = "";
    let searchTimeout;
    let selectedPeriodId = null;
    let selectedPeriodName = '';

    $(document).ready(function() {
        loadPeriods();
        fetchDirectory(1);
    });

    function loadPeriods() {
        $.post('contribution_api.php', { action: 'fetch_periods' }, function(res) {
            const sel = $('#periodSelect');
            sel.empty().append('<option value="">— Select Period —</option>');
            if (res.status === 'success') {
                res.data.forEach(p => {
                    sel.append(`<option value="${p.Periodid}">${p.PayrollPeriod} (${p.PhysicalYear})</option>`);
                });
            }
        }, 'json');
    }

    $('#periodSelect').on('change', function() {
        selectedPeriodId   = $(this).val() || null;
        selectedPeriodName = $(this).find('option:selected').text();
        // Reset any open form when period changes
        resetView();
    });

    // --- DIRECTORY/PAGINATION LOGIC ---
    function fetchDirectory(page, search = "") {
        currentPage = page;
        $('#memberListContainer').addClass('opacity-50 pointer-events-none');
        
        $.ajax({
            url: 'contribution_api.php',
            type: 'POST',
            data: { 
                action: 'fetch_directory', 
                page: page, 
                search: search 
            },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    renderDirectory(response.data.members);
                    updatePagination(response.data.pagination);
                }
                $('#memberListContainer').removeClass('opacity-50 pointer-events-none');
            },
            error: function() {
                $('#memberListContainer').html('<div class="p-4 text-center text-red-500 text-sm">Failed to load directory.</div>');
                $('#memberListContainer').removeClass('opacity-50 pointer-events-none');
            }
        });
    }

    function renderDirectory(members) {
        const container = $('#memberListContainer');
        container.empty();
        
        if (members.length === 0) {
            container.html('<div class="p-8 text-center text-slate-400 italic text-sm">No members found</div>');
            return;
        }

        members.forEach(m => {
            const fullName = (m.Lname + ", " + m.Fname + " " + (m.Mname || "")).trim();
            const avatarUrl = getAvatar(m.passport, fullName);
            
            const html = `
                <div onclick="loadMember('${m.patientid}')" 
                   id="member-${m.patientid}"
                   class="member-item w-full flex items-center gap-4 p-4 rounded-xl text-left mb-2 cursor-pointer transition-all border border-transparent hover:bg-slate-50 dark:hover:bg-slate-800/50 group">
                    
                    <div class="w-10 h-10 rounded-lg bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400 flex items-center justify-center font-bold text-sm overflow-hidden">
                        <img src="${avatarUrl}" class="w-full h-full object-cover">
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-sm truncate text-slate-800 dark:text-slate-200 member-name">
                            ${fullName}
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1">
                            <span class="material-icons-round text-[14px]">badge</span> ID: <span class="member-id">${m.patientid}</span>
                        </p>
                    </div>
                    
                    <span class="material-icons-round text-slate-300 opacity-0 group-hover:opacity-100 transition-opacity">chevron_right</span>
                </div>
            `;
            container.append(html);
        });
    }

    // Avatar Logic: Handle specific invalid path
    function getAvatar(passportPath, name) {
        if (passportPath && passportPath !== 'image_upload/abc.png' && (passportPath.includes('/') || passportPath.includes('http'))) {
             return passportPath;
        }
        return 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=random&color=fff';
    }

    function updatePagination(pagination) {
        totalPages = pagination.total_pages;
        $('#paginationInfo').text(`Page ${currentPage} of ${totalPages}`);
        
        $('#prevPageBtn').prop('disabled', currentPage <= 1);
        $('#nextPageBtn').prop('disabled', currentPage >= totalPages);
    }

    // Search Handling
    $('#directorySearch').on('keyup', function() {
        const val = $(this).val();
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            currentSearch = val;
            fetchDirectory(1, currentSearch);
        }, 300); // 300ms debounce
    });

    // Pagination Click
    $('#prevPageBtn').click(() => {
        if (currentPage > 1) fetchDirectory(currentPage - 1, currentSearch);
    });
    $('#nextPageBtn').click(() => {
        if (currentPage < totalPages) fetchDirectory(currentPage + 1, currentSearch);
    });

    // --- LOAD MEMBER DATA (AJAX) ---
    function loadMember(id) {
        if (!selectedPeriodId) {
            Swal.fire('Select Period', 'Please select a payroll period before editing a member.', 'warning');
            return;
        }

        $('#loader').fadeIn(100);

        if (window.innerWidth < 1024) {
            $('#memberSidebar').addClass('hidden');
            $('#mainContent').removeClass('hidden').addClass('flex');
        }

        $('.member-item').removeClass('bg-primary/5 border-primary/20').addClass('border-transparent');
        $('.member-item').find('.material-icons-round:last').addClass('opacity-0');
        $('#member-' + id).removeClass('border-transparent').addClass('bg-primary/5 border-primary/20');
        $('#member-' + id).find('.material-icons-round:last').removeClass('opacity-0');

        $.ajax({
            url: 'contribution_api.php',
            type: 'POST',
            data: { action: 'fetch_member', id: id, period_id: selectedPeriodId },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;

                    $('#memberNameDisplay').text(data.member.Lname + ' ' + data.member.Fname);
                    $('#periodNameDisplay').text(selectedPeriodName);
                    $('#memberIdBadge').text('ID: ' + data.member.patientid);
                    $('#hiddenMemberId').val(data.member.patientid);
                    $('#hiddenPeriodId').val(selectedPeriodId);

                    $('#contribInput').val(formatMoney(data.contribution.contribution));
                    $('#loanInput').val(formatMoney(data.contribution.loan));
                    $('#bankLoanInput').val(formatMoney(data.contribution.bank_loan));
                    $('#loanBalanceDisplay').text('₦' + formatMoney(data.loan_balance));

                    // The payroll figure this split has to reconcile against.
                    $('#hiddenGross').val(parseFloat(data.contribution.gross_deduction) || 0);

                    renderStandingBankLoan(data.standing_bank_loan || 0);
                    $('#processedWarning').toggleClass('hidden', !data.is_processed);

                    renderRefunds(data.refunds || [], data.refund_total || 0);
                    calculateTotal();
                    $('#emptyState').hide();
                    $('#editFormContainer').fadeIn(200);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
                $('#loader').fadeOut(100);
            },
            error: function() {
                Swal.fire('Error', 'Failed to fetch member details.', 'error');
                $('#loader').fadeOut(100);
            }
        });
    }

    // --- HANDLE FORM SUBMISSION (AJAX) ---
    $('#contributionForm').on('submit', function(e) {
        e.preventDefault();
        $('#loader').fadeIn(100);

        $.ajax({
            url: 'contribution_api.php',
            type: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    Swal.fire({
                        icon: response.gross_moved ? 'warning' : 'success',
                        title: 'Saved',
                        text: response.message,
                        timer: response.gross_moved ? undefined : 1800,
                        showConfirmButton: !!response.gross_moved
                    });
                    // Reload so the panel reflects whatever the server settled on.
                    loadMember($('#hiddenMemberId').val());
                } else {
                    Swal.fire('Error', 'Update failed: ' + response.message, 'error');
                }
                $('#loader').fadeOut(100);
            },
            error: function() {
                Swal.fire('Error', 'Server communication error.', 'error');
                $('#loader').fadeOut(100);
            }
        });
    });

    function resetView() {
        $('#editFormContainer').hide();
        $('#processedWarning').addClass('hidden');
        $('#emptyState').fadeIn(200);
        $('.member-item').removeClass('bg-primary/5 border-primary/20').addClass('border-transparent');
    }

    function formatInput(input) {
        let value = input.value.replace(/[^0-9.]/g, '');
        if (value) {
            let parts = value.split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
            input.value = parts.join('.');
        }
    }
    
    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function showlist() {
        $('#mainContent').addClass('hidden').removeClass('flex');
        $('#memberSidebar').removeClass('hidden').addClass('flex');
    }

    let currentRefundTotal = 0;

    function renderRefunds(refunds, total) {
        currentRefundTotal = parseFloat(total) || 0;
        $('#breakdownRefund').text('₦' + formatMoney(currentRefundTotal));

        if (refunds.length === 0) {
            $('#refundSection').addClass('hidden');
            $('#refundList').empty();
        } else {
            $('#refundSection').removeClass('hidden');
            const html = refunds.map(r => `
                <div class="flex items-center justify-between bg-orange-50 dark:bg-orange-900/20 border border-orange-200 dark:border-orange-800 rounded-lg px-3 py-2" id="refund-${r.refundid}">
                    <span class="text-sm font-semibold text-orange-700 dark:text-orange-300">₦${formatMoney(r.amount)}</span>
                    <button onclick="deleteRefund(${r.refundid})"
                        class="text-red-500 hover:text-red-700 transition-colors ml-3 flex items-center gap-1 text-xs font-medium">
                        <span class="material-icons-round text-sm">delete</span> Delete
                    </button>
                </div>`).join('');
            $('#refundList').html(html);
        }
        updateBreakdown();
    }

    // Sub-kobo drift is rounding noise, not an imbalance.
    const BALANCE_TOLERANCE = 0.005;

    function readMoney(selector) {
        return parseFloat(String($(selector).val() || '').replace(/,/g, '')) || 0;
    }

    function updateBreakdown() {
        const contrib = readMoney('#contribInput');
        const loan    = readMoney('#loanInput');
        const bank    = readMoney('#bankLoanInput');

        // Refund is an outcome of processing, not part of the payroll split, so
        // it sits outside the total that has to reconcile against payroll.
        $('#breakdownContrib').text('₦' + formatMoney(contrib));
        $('#breakdownLoan').text('₦'    + formatMoney(loan));
        $('#breakdownBank').text('₦'    + formatMoney(bank));
        $('#breakdownTotal').text('₦'   + formatMoney(contrib + loan + bank));
        $('#breakdownBankRow').toggleClass('hidden', bank <= 0);
    }

    /**
     * The payroll figure is fixed, so money moved into the bank loan has to come
     * out of the union loan repayment. Only applies once a gross is known —
     * on a period with no imported record the three fields are free entry.
     */
    function rebalanceAllocation() {
        const gross = parseFloat($('#hiddenGross').val()) || 0;

        if (gross > 0) {
            const remainder = gross - readMoney('#contribInput') - readMoney('#bankLoanInput');
            $('#loanInput').val(formatMoney(Math.max(0, remainder)));
        }

        calculateTotal();
    }

    // The amount future imports will carve out for the member currently on screen.
    let currentStandingBankLoan = 0;

    function renderStandingBankLoan(amount) {
        currentStandingBankLoan = parseFloat(amount) || 0;
        $('#standingBankLoanAmount').text('₦' + formatMoney(currentStandingBankLoan));
        $('#standingBankLoanRow').toggleClass('hidden', currentStandingBankLoan <= 0);
        $('#bankLoanRecurring').prop('checked', true);
        updateRecurringHint();
    }

    /**
     * Saving a zero with "recurring" ticked cancels the standing deduction. That
     * is a legitimate way to end it, but it must never happen silently when the
     * admin only meant to zero out a single month.
     */
    function updateRecurringHint() {
        const hint      = $('#recurringHint');
        const entered   = readMoney('#bankLoanInput');
        const recurring = $('#bankLoanRecurring').is(':checked');

        if (recurring && entered <= 0 && currentStandingBankLoan > 0) {
            hint.attr('class', 'mt-2 px-3 py-2 rounded-lg text-xs font-medium bg-red-100 dark:bg-red-900/30 text-red-700 dark:text-red-300')
                .text('Saving ₦0.00 with this ticked will stop the recurring ₦' + formatMoney(currentStandingBankLoan) +
                      ' deduction. Untick it to zero this period only.')
                .removeClass('hidden');
            return;
        }

        if (recurring && entered > 0 && Math.abs(entered - currentStandingBankLoan) >= BALANCE_TOLERANCE) {
            hint.attr('class', 'mt-2 px-3 py-2 rounded-lg text-xs font-medium bg-purple-100 dark:bg-purple-900/30 text-purple-700 dark:text-purple-300')
                .text('Future imports will carve out ₦' + formatMoney(entered) + ' for this member.')
                .removeClass('hidden');
            return;
        }

        hint.addClass('hidden');
    }

    async function stopRecurringBankLoan() {
        const memberId = $('#hiddenMemberId').val();
        if (!memberId) return;

        const confirm = await Swal.fire({
            title: 'Stop recurring bank loan?',
            text: 'Future API imports will no longer set money aside for this member. Records already saved for past periods are kept.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, stop it',
            confirmButtonColor: '#7c3aed'
        });
        if (!confirm.isConfirmed) return;

        $.post('contribution_api.php', { action: 'clear_standing_bank_loan', member_id: memberId }, function(res) {
            if (res.status === 'success') {
                renderStandingBankLoan(0);
                Swal.fire({ icon: 'success', title: 'Stopped', text: res.message, timer: 2200, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    }

    async function deleteRefund(refundId) {
        const confirm = await Swal.fire({
            title: 'Delete Refund?',
            text: 'This will permanently remove the refund record.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete',
            confirmButtonColor: '#ef4444'
        });
        if (!confirm.isConfirmed) return;

        $.post('contribution_api.php', { action: 'delete_refund', refund_id: refundId }, function(res) {
            if (res.status === 'success') {
                $('#refund-' + refundId).remove();
                // Recheck if any refunds remain
                if ($('#refundList').children().length === 0) {
                    currentRefundTotal = 0;
                    $('#refundSection').addClass('hidden');
                    $('#breakdownRefundRow').addClass('hidden');
                } else {
                    // Recalculate total from remaining rows (reload member to be safe)
                    loadMember($('#hiddenMemberId').val());
                    return;
                }
                updateBreakdown();
                Swal.fire({ icon: 'success', title: 'Deleted', timer: 1200, showConfirmButton: false });
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }, 'json');
    }

    function calculateTotal() {
        if (!document.getElementById('contribInput')) return;

        const allocated = readMoney('#contribInput') + readMoney('#loanInput') + readMoney('#bankLoanInput');
        const gross     = parseFloat($('#hiddenGross').val()) || 0;

        // With no imported figure to reconcile against, the parts define the total.
        const total = gross > 0 ? gross : allocated;

        $('#visualTotal').text('₦' + formatMoney(total));
        $('#visualAllocated').text('₦' + formatMoney(allocated));
        renderBalanceStatus(total - allocated, gross);
        updateBreakdown();
        updateRecurringHint();
    }

    function renderBalanceStatus(difference, gross) {
        const status = $('#balanceStatus');

        if (gross <= 0) {
            status.html('<span class="material-icons-round text-sm text-slate-400">edit</span>' +
                        '<span class="text-slate-500">No payroll figure imported — the amounts you enter set the total.</span>');
            return;
        }

        if (Math.abs(difference) < BALANCE_TOLERANCE) {
            status.html('<span class="material-icons-round text-sm text-emerald-500">check_circle</span>' +
                        '<span class="text-emerald-600 dark:text-emerald-400">Balanced against payroll</span>');
            return;
        }

        const label = difference > 0 ? 'Unallocated' : 'Over-allocated by';
        status.html('<span class="material-icons-round text-sm text-red-500">error</span>' +
                    '<span class="text-red-600 dark:text-red-400">' + label + ' ₦' +
                    formatMoney(Math.abs(difference)) + ' — saving will reset the total to match.</span>');
    }
</script>

<?php include 'includes/footer.php'; ?>
