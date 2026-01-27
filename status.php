<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<!-- Tailwind Config Extension for this page -->
<script>
    tailwind.config.theme.extend.colors['surface-light'] = '#ffffff';
    tailwind.config.theme.extend.colors['surface-dark'] = '#1e293b';
</script>
<style>
    .stats-gradient {
        background: linear-gradient(135deg, #0056b3 0%, #003d7a 100%);
    }
</style>

<!-- Main Content Area -->
<div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden bg-background-light dark:bg-background-dark">
    <!-- Topbar -->
    <?php include 'includes/topbar.php'; ?>

    <main class="w-full flex-grow p-6 md:p-8">
        <div class="max-w-7xl mx-auto">
            <header class="mb-8">
                <h2 class="text-2xl font-bold tracking-tight text-slate-800 dark:text-white">Search Member Status</h2>
                <p class="text-slate-500 dark:text-slate-400">Look up member savings and contributions statement</p>
            </header>

            <div class="grid grid-cols-1 xl:grid-cols-12 gap-8">
                
                <!-- Left Column: Search Form -->
                <div class="xl:col-span-7">
                    <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                        <div class="p-6 border-b border-slate-200 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                            <h3 class="font-semibold flex items-center gap-2 text-slate-800 dark:text-white">
                                <span class="material-icons-round text-primary text-sm">filter_list</span>
                                Search Filters
                            </h3>
                        </div>
                        
                        <form id="statusForm" class="p-8 space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Member Search -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Member Name / ID</label>
                                    <div class="relative">
                                        <input type="text" id="memberSearch" autocomplete="off"
                                            class="w-full pl-4 pr-10 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all text-slate-900 dark:text-white" 
                                            placeholder="Enter name or ID..." />
                                        <input type="hidden" id="selectedMemberId" name="member_id">
                                        
                                        <!-- Clear Button -->
                                        <button type="button" id="clearSearch" class="absolute right-3 top-2.5 text-slate-400 hover:text-red-500 hidden cursor-pointer z-10">
                                            <span class="material-icons-round text-lg">close</span>
                                        </button>

                                        <!-- Suggestions -->
                                        <div id="suggestions" class="absolute z-50 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl mt-1 hidden max-h-60 overflow-y-auto"></div>
                                    </div>
                                </div>

                                <!-- Period Selector -->
                                <div class="space-y-2 md:col-span-2">
                                    <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Reporting Period</label>
                                    <select id="periodSelect" name="period_id" required
                                        class="w-full px-4 py-2.5 bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg focus:ring-2 focus:ring-primary focus:border-primary transition-all appearance-none text-slate-900 dark:text-white">
                                        <option value="">Select Period</option>
                                    </select>
                                </div>
                            </div>

                            <div class="pt-4">
                                <button type="submit" id="btnCheck" class="w-full bg-primary hover:bg-blue-700 text-white font-semibold py-3 rounded-lg flex items-center justify-center gap-2 shadow-lg shadow-primary/20 transition-all">
                                    <span class="material-icons-round">search</span>
                                    Search Member Status
                                </button>
                            </div>
                        </form>
                    </div>

                     <!-- Quick Tip Box -->
                     <div class="mt-6 p-4 bg-amber-50 dark:bg-amber-900/10 border border-amber-100 dark:border-amber-900/30 rounded-xl flex gap-4">
                        <span class="material-icons-round text-amber-500">lightbulb</span>
                        <p class="text-xs text-amber-800 dark:text-amber-200 leading-relaxed">
                            <strong class="block mb-1">Quick Tip:</strong>
                            You can search by partial name if you're unsure of the exact spelling. The system will provide suggestions.
                        </p>
                    </div>
                </div>

                <!-- Right Column: Results / Default Info -->
                <div class="xl:col-span-5 space-y-6" id="rightColumn">
                    
                    <!-- Global Stats (Always Visible initially, hidden when search result active) -->
                    <div id="globalStatsWrapper" class="space-y-6 animate-fade-in-up">
                        
                         <!-- Total Union Savings Card -->
                        <div class="stats-gradient rounded-2xl p-6 text-white shadow-xl relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-10 scale-150 group-hover:scale-125 transition-transform duration-500">
                                <span class="material-icons-round text-9xl">savings</span>
                            </div>
                           <!-- Loading Skeleton -->
                            <div id="globalStatsLoading" class="animate-pulse">
                                <div class="h-4 bg-white/20 rounded w-1/3 mb-4"></div>
                                <div class="h-8 bg-white/20 rounded w-2/3"></div>
                            </div>
                            <!-- Real Content -->
                             <div id="globalStatsContent" class="hidden flex flex-col relative z-10">
                                <div class="flex justify-between items-start mb-6">
                                    <div>
                                        <p class="text-blue-100 text-sm font-medium uppercase tracking-wider">Total Union Savings</p>
                                        <h3 id="globalSavings" class="text-3xl font-bold mt-1">₦ 0.00</h3>
                                    </div>
                                    <div class="bg-white/20 p-2 rounded-lg">
                                        <span class="material-icons-round">payments</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-white/10">
                                    <div>
                                        <p class="text-blue-100 text-xs">Total Members</p>
                                        <p id="globalMembers" class="text-xl font-bold">0</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-blue-100 text-xs">Active Loans</p>
                                        <p id="globalLoans" class="text-xl font-bold">0</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                         <!-- Gender Distribution Card -->
                        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-800 p-6 shadow-sm">
                            <h4 class="font-bold text-sm text-slate-400 uppercase tracking-widest mb-6">Gender Distribution</h4>
                            <div id="genderStatsContent" class="space-y-6">
                                <div class="relative pt-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons-round text-pink-500 text-sm">female</span>
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Female Members</span>
                                        </div>
                                        <span id="genderFemale" class="text-sm font-bold text-slate-900 dark:text-white">0</span>
                                    </div>
                                    <div class="overflow-hidden h-3 text-xs flex rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div id="barFemale" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-pink-500 transition-all duration-1000" style="width:0%"></div>
                                    </div>
                                </div>
                                <div class="relative pt-1">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="material-icons-round text-blue-500 text-sm">male</span>
                                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300">Male Members</span>
                                        </div>
                                        <span id="genderMale" class="text-sm font-bold text-slate-900 dark:text-white">0</span>
                                    </div>
                                    <div class="overflow-hidden h-3 text-xs flex rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div id="barMale" class="shadow-none flex flex-col text-center whitespace-nowrap text-white justify-center bg-blue-500 transition-all duration-1000" style="width:0%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Member Results (Hidden by default) -->
                    <div id="memberResults" class="hidden space-y-6 animate-fade-in-up">
                        
                        <!-- Main Card -->
                        <div class="stats-gradient rounded-2xl p-6 text-white shadow-xl relative overflow-hidden group">
                            <div class="absolute top-0 right-0 p-4 opacity-10 scale-150 group-hover:scale-125 transition-transform duration-500">
                                <span class="material-icons-round text-9xl">savings</span>
                            </div>
                           
                             <div class="flex flex-col relative z-10">
                                <p class="text-blue-100 text-xs font-medium uppercase tracking-wider mb-1">Total Contribution</p>
                                <h3 id="resContribution" class="text-4xl font-bold tracking-tight">₦0.00</h3>
                                
                                <div class="mt-6 pt-6 border-t border-white/10 flex items-center gap-4">
                                     <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center font-bold text-sm" id="resAvatar">MB</div>
                                     <div>
                                         <p id="resName" class="font-bold text-sm leading-tight">Member Name</p>
                                         <p id="resId" class="text-blue-200 text-xs">ID: 00000</p>
                                     </div>
                                </div>
                            </div>
                        </div>

                        <!-- Loan Balance -->
                        <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-800 p-6 relative overflow-hidden">
                            <div class="absolute top-4 right-4 text-orange-500 opacity-10">
                                <span class="material-icons-round text-6xl">account_balance_wallet</span>
                            </div>
                            <h4 class="font-bold text-xs text-slate-500 uppercase tracking-widest mb-1">Loan Balance</h4>
                            <h3 id="resLoanBal" class="text-2xl font-bold text-slate-800 dark:text-white">₦0.00</h3>
                        </div>

                        <!-- Breakdown Grid -->
                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                                <p class="text-xs text-slate-500 mb-1">Total Loan</p>
                                <p id="resLoanTotal" class="text-lg font-bold text-slate-800 dark:text-white">₦0.00</p>
                            </div>
                             <div class="bg-white dark:bg-surface-dark rounded-2xl border border-slate-200 dark:border-slate-800 p-4">
                                <p class="text-xs text-slate-500 mb-1">Withdrawals</p>
                                <p id="resWithdrawal" class="text-lg font-bold text-slate-800 dark:text-white">₦0.00</p>
                            </div>
                        </div>
                        
                         <!-- Back Button -->
                         <button id="backToStats" class="text-sm text-primary font-bold hover:underline flex items-center gap-1">
                             <span class="material-icons-round text-sm">arrow_back</span> Back to Global Stats
                         </button>

                    </div>

                </div>
            </div>

            <!-- Footer -->
            <div class="mt-12 pt-8 border-t border-slate-200 dark:border-slate-800 flex flex-col md:flex-row justify-between items-center text-slate-400 text-xs gap-4">
                <p>© <?php echo date("Y"); ?> Medical and Health Workers Union of Nigeria (OOUTH Branch)</p>
                <div class="flex gap-4">
                    <a class="hover:text-primary transition-colors" href="#">Help Center</a>
                    <a class="hover:text-primary transition-colors" href="#">Privacy Policy</a>
                </div>
            </div>
        </div>
    </main>
</div>

<!-- Custom Scripts -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
$(document).ready(function() {
    
    // Format Currency Helper
    const fmt = (val) => '₦ ' + parseFloat(val || 0).toLocaleString('en-NG', { minimumFractionDigits: 2 });

    // 0. Load Global Stats
    function loadGlobalStats() {
        $.ajax({
            url: 'status_api.php',
            data: { action: 'get_global_stats' },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success') {
                    const d = response.data;
                    
                    // Update Text
                    $('#globalSavings').text(fmt(d.total_savings));
                    $('#globalMembers').text(d.total_members);
                    $('#globalLoans').text(d.active_loans);

                    // Update Gender
                    $('#genderFemale').text(d.gender.Female || 0);
                    $('#genderMale').text(d.gender.Male || 0);

                    // Update Bars
                    const total = (parseInt(d.gender.Female) || 0) + (parseInt(d.gender.Male) || 0);
                    const pctFemale = total > 0 ? ((d.gender.Female / total) * 100) : 0;
                    const pctMale = total > 0 ? ((d.gender.Male / total) * 100) : 0;

                    $('#barFemale').css('width', pctFemale + '%');
                    $('#barMale').css('width', pctMale + '%');

                    // Show Content
                    $('#globalStatsLoading').addClass('hidden');
                    $('#globalStatsContent').removeClass('hidden');
                }
            }
        });
    }
    loadGlobalStats();

    // 1. Fetch Periods
    $.ajax({
        url: 'status_api.php',
        data: { action: 'fetch_periods' },
        dataType: 'json',
        success: function(response) {
            const select = $('#periodSelect');
            if (response.data) {
                response.data.forEach(p => {
                    select.append(`<option value="${p.Periodid}">${p.PayrollPeriod}</option>`);
                });
            }
        }
    });

    // 2. Member Search (Autocomplete via RPC)
    let debounce;
    $('#memberSearch').on('keyup', function() {
        const query = $(this).val();
        const suggestionsInfo = $('#suggestions');
        const clearBtn = $('#clearSearch');
        
        if(query.length > 0) clearBtn.removeClass('hidden'); 
        else clearBtn.addClass('hidden');

        // Clear debounce
        clearTimeout(debounce);

        if (query.length < 2) {
            suggestionsInfo.hide();
            return;
        }

        debounce = setTimeout(() => {
            $.ajax({
                url: 'rpc.php',
                type: 'POST',
                data: { queryString: query },
                success: function(data) {
                    if(data.length > 0) {
                        // rpc.php returns <li> items, so wrap them in a ul or just dump them
                        // But our design expects a clean div list. 
                        // Since rpc.php returns <li onClick="fill(...)">...</li>
                        // We need to style those LIs or wrap them. 
                        // The existing rpc.php classes are basic Tailwind, let's just use them.
                        // We need to make sure the global 'fill' function exists.
                        suggestionsInfo.html('<ul class="w-full">' + data + '</ul>').show();
                    } else {
                        suggestionsInfo.hide();
                    }
                }
            });
        }, 300);
    });

    // Global Fill Function (required by rpc.php onclick)
    window.fill = function(id, name) {
        $('#memberSearch').val(name);
        $('#selectedMemberId').val(id);
        $('#suggestions').hide();
    };

    // Clear Search Action
    $('#clearSearch').on('click', function() {
        $('#memberSearch').val('');
        $('#selectedMemberId').val('');
        $('#memberResults').addClass('hidden');
        $('#globalStatsWrapper').removeClass('hidden'); // Show Global Stats again
        $(this).addClass('hidden');
    });

    $('#backToStats').on('click', function() {
        $('#clearSearch').trigger('click');
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#memberSearch, #suggestions').length) {
            $('#suggestions').hide();
        }
    });

    // 3. Search Action (Get Status)
    $('#statusForm').on('submit', function(e) {
        e.preventDefault();
        
        const memberId = $('#selectedMemberId').val();
        const periodId = $('#periodSelect').val();

        if (!memberId) {
            Swal.fire('Warning', 'Please select a member from the suggestions.', 'warning');
            return;
        }
        if (!periodId) {
            Swal.fire('Warning', 'Please select a reporting period.', 'warning');
            return;
        }

        const btn = $('#btnCheck');
        btn.prop('disabled', true).html('<span class="animate-spin material-icons-round text-sm mr-2">refresh</span> Searching...');

        $.ajax({
            url: 'status_api.php',
            type: 'POST',
            data: { action: 'get_status', member_id: memberId, period_id: periodId },
            dataType: 'json',
            success: function(response) {
                if(response.status === 'success' && response.data) {
                    renderResult(response.data);
                } else {
                    Swal.fire('Info', response.message || 'No records found', 'info');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to fetch status', 'error');
            },
            complete: function() {
                btn.prop('disabled', false).html('<span class="material-icons-round">search</span> Search Member Status');
            }
        });
    });

    function renderResult(data) {
        // Toggle View: Hide Global Stats, Show Member Results
        $('#globalStatsWrapper').addClass('hidden');
        $('#memberResults').removeClass('hidden');

        // Update Data
        $('#resName').text(data.namess);
        $('#resId').text(`ID: ${data.patientid}`);
        $('#resAvatar').text(data.namess.split(' ').map(n=>n[0]).join('').substring(0,2));

        $('#resContribution').text(fmt(data.Contribution));
        $('#resLoanBal').text(fmt(data.Loanbalance));
        $('#resLoanTotal').text(fmt(data.Loan));
        $('#resWithdrawal').text(fmt(data.withdrawal));
    }

});
</script>

<?php include 'includes/footer.php'; ?>
