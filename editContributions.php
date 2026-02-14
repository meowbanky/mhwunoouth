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
} catch (PDOException $e) {
    // Ignore
}

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
    
    <div class="flex-1 p-6 flex gap-6 overflow-hidden">
        
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

            <!-- Top Stats Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="bg-white dark:bg-slate-900 p-5 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <p class="text-slate-500 dark:text-slate-400 text-xs font-medium uppercase tracking-wider mb-1">Total Savings (Global)</p>
                    <p class="text-2xl font-bold">₦<?php echo format_money($grand_total_savings); ?></p>
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
                        <p class="text-sm text-slate-500 dark:text-slate-400">Modify records for <span id="memberNameDisplay" class="text-slate-900 dark:text-white font-semibold">...</span></p>
                    </div>
                    <span id="memberIdBadge" class="px-3 py-1 bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400 rounded-full text-xs font-bold uppercase">ID: ...</span>
                </div>

                <form id="contributionForm" class="p-8 grid grid-cols-1 md:grid-cols-2 gap-10">
                    <input type="hidden" name="member_id" id="hiddenMemberId">
                    <input type="hidden" name="action" value="update_record">
                    
                    <div class="space-y-6">
                        <!-- Contribution Input -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Monthly Contribution (₦)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">₦</span>
                                <input name="contribution_amount" id="contribInput" 
                                       class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary text-lg font-bold transition-all" 
                                       type="text" 
                                       oninput="formatInput(this); calculateTotal();"
                                />
                            </div>
                        </div>

                        <!-- Loan Repayment Input -->
                        <div>
                            <label class="block text-sm font-semibold mb-2">Loan Repayment (₦)</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 font-bold text-slate-400">₦</span>
                                <input name="loan_repayment" id="loanInput" 
                                       class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-800 border-none rounded-xl focus:ring-2 focus:ring-primary text-lg font-bold transition-all" 
                                       type="text" 
                                       oninput="formatInput(this); calculateTotal();"
                                />
                            </div>
                        </div>

                        <!-- Total Display (Calculated Visual Only) -->
                        <div class="bg-primary/5 p-4 rounded-xl border border-primary/10">
                             <div class="flex justify-between items-center">
                                 <span class="text-sm font-medium text-slate-600 dark:text-slate-400">Total Deduction</span>
                                 <span class="text-xl font-bold text-primary" id="visualTotal">₦0.00</span>
                             </div>
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
                    <div class="bg-slate-50 dark:bg-slate-800/40 rounded-2xl p-6 border border-slate-100 dark:border-slate-800 h-fit">
                        <h3 class="font-bold text-sm uppercase tracking-wider text-slate-500 dark:text-slate-400 mb-6 flex items-center gap-2">
                            <span class="material-icons-round text-lg">info</span>
                            Financial Status
                        </h3>
                        
                        <div class="space-y-4">
                             <div class="flex justify-between items-center pb-3 border-b border-slate-200 dark:border-slate-700">
                                <div>
                                    <p class="text-sm font-bold opacity-50">Current Loan Balance</p>
                                    <p class="text-2xl font-bold text-red-500" id="loanBalanceDisplay">₦0.00</p>
                                </div>
                            </div>
                            
                            <div class="p-3 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 text-xs rounded-lg">
                                <span class="font-bold">Note:</span> Increasing the loan repayment amount here will reduce the loan balance faster during the next payroll cycle.
                            </div>
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

    // First Load
    $(document).ready(function() {
        fetchDirectory(1);
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
        // Show loader
        $('#loader').fadeIn(100);
        
        // Mobile: Switch view
        if (window.innerWidth < 1024) {
            $('#memberSidebar').addClass('hidden');
            $('#mainContent').removeClass('hidden').addClass('flex');
        }
        
        // Highlight active sidebar item
        $('.member-item').removeClass('bg-primary/5 border-primary/20').addClass('border-transparent');
        $('.member-item').find('.material-icons-round:last').addClass('opacity-0');
        
        $('#member-' + id).removeClass('border-transparent').addClass('bg-primary/5 border-primary/20');
        $('#member-' + id).find('.material-icons-round:last').removeClass('opacity-0');

        $.ajax({
            url: 'contribution_api.php',
            type: 'POST',
            data: { action: 'fetch_member', id: id },
            dataType: 'json',
            success: function(response) {
                if (response.status === 'success') {
                    const data = response.data;
                    
                    // Populate Form
                    $('#memberNameDisplay').text(data.member.Lname + ' ' + data.member.Fname);
                    $('#memberIdBadge').text('ID: ' + data.member.patientid);
                    $('#hiddenMemberId').val(data.member.patientid);
                    
                    $('#contribInput').val(formatMoney(data.contribution.contribution));
                    $('#loanInput').val(formatMoney(data.contribution.loan));
                    $('#loanBalanceDisplay').text('₦' + formatMoney(data.loan_balance));
                    
                    calculateTotal();

                    // Switch View
                    $('#emptyState').hide();
                    $('#editFormContainer').fadeIn(200);
                    
                    // Mobile sidebar toggle check could go here
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
                        icon: 'success',
                        title: 'Saved',
                        text: 'Record updated successfully!',
                        timer: 1500,
                        showConfirmButton: false
                    });
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

    function calculateTotal() {
        const contribEl = document.getElementById('contribInput');
        const loanEl = document.getElementById('loanInput');
        if (!contribEl || !loanEl) return;

        let contrib = parseFloat(contribEl.value.replace(/,/g, '') || 0);
        let loan = parseFloat(loanEl.value.replace(/,/g, '') || 0);
        let total = contrib + loan;
        
        const totalEl = document.getElementById('visualTotal');
        if(totalEl) totalEl.innerText = '₦' + total.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }
</script>

<?php include 'includes/footer.php'; ?>
