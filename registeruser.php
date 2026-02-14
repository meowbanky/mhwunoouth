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
                <h2 class="text-2xl font-bold tracking-tight">User Management</h2>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Manage system administrators and user access.</p>
            </div>
            <div class="flex items-center gap-3">
                <button id="btnNewUser" class="flex items-center gap-2 bg-primary hover:bg-sky-600 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-lg shadow-primary/20">
                    <span class="material-icons-round text-[20px]">person_add</span>
                    <span>Add New User</span>
                </button>
            </div>
        </header>

        <!-- Search & Filter -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl p-4 mb-6 flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="relative w-full md:w-auto flex-1">
                    <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-[20px]">search</span>
                    <input id="searchInput" class="pl-10 pr-4 py-2 bg-background-light dark:bg-background-dark border-transparent focus:ring-primary rounded-xl text-sm w-full md:w-64" placeholder="Search users..." type="text"/>
                </div>
            </div>
            <div class="text-sm text-slate-500 dark:text-slate-400">
                <span id="userCount">0</span> Users found
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-surface-light dark:bg-surface-dark border border-slate-200 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm flex flex-col">
            <div class="overflow-x-auto custom-scrollbar flex-grow">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">User</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Username</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Role</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Registered</th>
                            <th class="px-6 py-4 text-xs font-bold text-slate-500 dark:text-slate-400 uppercase tracking-wider text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="usersTableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                        <!-- Rows -->
                         <tr><td colspan="5" class="p-6 text-center">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                <div class="text-sm text-slate-500 dark:text-slate-400">
                    Showing page <span id="pagCurrent" class="font-bold text-slate-700 dark:text-slate-200">1</span> of <span id="pagTotal" class="font-bold text-slate-700 dark:text-slate-200">1</span>
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="changePage(-1)" id="btnPrev" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Previous
                    </button>
                    <button onclick="changePage(1)" id="btnNext" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors">
                        Next
                    </button>
                </div>
            </div>
        </div>

    </main>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" class="fixed inset-0 z-50 hidden">
    <!-- Backdrop -->
    <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm transition-opacity" onclick="closeModal()"></div>
    
    <!-- Modal Content -->
    <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-surface-light dark:bg-surface-dark rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-6 animate-in fade-in zoom-in-95 duration-200">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-xl font-bold" id="modalTitle">Add New User</h3>
            <button onclick="closeModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition-colors">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        
        <form id="userForm" class="space-y-4">
            <input type="hidden" id="editUserId" name="userid" value="">
            <input type="hidden" name="action" value="save_user">
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">First Name</label>
                    <input type="text" name="firstname" id="firstname" required class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Last Name</label>
                    <input type="text" name="lastname" id="lastname" required class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Middle Name (Optional)</label>
                <input type="text" name="middlename" id="middlename" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Username</label>
                <input type="text" name="username" id="username" required class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all">
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">Password <span class="text-xs text-slate-400 font-normal" id="passHint">(Required for new users)</span></label>
                <input type="password" name="password" id="password" class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl focus:ring-primary focus:border-primary outline-none transition-all">
            </div>

            <div class="pt-4 flex justify-end gap-3">
                <button type="button" onclick="closeModal()" class="px-5 py-2 text-sm font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-colors">Cancel</button>
                <button type="submit" class="px-5 py-2 text-sm font-medium text-white bg-primary hover:bg-sky-600 rounded-xl shadow-lg shadow-primary/20 transition-all">Save User</button>
            </div>
        </form>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script>
    let allUsers = [];
    let currentPage = 1;
    let totalPages = 1;
    const limit = 10;

    // --- Modal Logic ---
    function openModal() {
        $('#userModal').removeClass('hidden');
    }
    
    function closeModal() {
        $('#userModal').addClass('hidden');
        $('#userForm')[0].reset();
        $('#editUserId').val('');
        $('#modalTitle').text('Add New User');
        $('#passHint').text('(Required for new users)');
    }

    // --- Actions ---
    function loadUsers(page = 1) {
        const search = $('#searchInput').val();
        
        $.post('registeruser_api.php', { 
            action: 'fetch_users',
            page: page,
            limit: limit,
            search: search
        }, function(res) {
            if (res.status === 'success') {
                allUsers = res.data.list;
                currentPage = res.data.pagination.current_page;
                totalPages = res.data.pagination.total_pages;
                
                renderUsers(allUsers, res.data.pagination.total_records);
                updatePagination();
            }
        }, 'json');
    }

    function renderUsers(users, totalCount) {
        const tbody = $('#usersTableBody');
        tbody.empty();
        $('#userCount').text(totalCount);

        if (users.length === 0) {
            tbody.html('<tr><td colspan="5" class="p-6 text-center text-slate-500">No users found.</td></tr>');
            return;
        }

        users.forEach(u => {
            const tr = `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors group">
                    <td class="px-6 py-4">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center font-bold text-slate-600 dark:text-slate-300">
                                ${u.initials}
                            </div>
                            <div class="font-medium text-slate-900 dark:text-slate-100">${u.fullname}</div>
                        </div>
                    </td>
                    <td class="px-6 py-4 font-mono text-sm text-slate-600 dark:text-slate-400">${u.Username}</td>
                    <td class="px-6 py-4">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300">
                            Administrator
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-500">${u.dateofRegistration}</td>
                    <td class="px-6 py-4 text-center">
                        <div class="flex items-center justify-center gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button onclick="editUser(${u.UserID})" class="p-2 text-slate-400 hover:text-primary hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all" title="Edit">
                                <span class="material-icons-round text-lg">edit</span>
                            </button>
                            <button onclick="deleteUser(${u.UserID})" class="p-2 text-slate-400 hover:text-red-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-all" title="Delete">
                                <span class="material-icons-round text-lg">delete</span>
                            </button>
                        </div>
                    </td>
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
            loadUsers(newPage);
        }
    }

    // Edit Setup
    window.editUser = function(id) {
        const user = allUsers.find(u => u.UserID == id);
        if (!user) return;

        $('#editUserId').val(user.UserID);
        $('#firstname').val(user.firstname);
        $('#middlename').val(user.middlename);
        $('#lastname').val(user.lastname);
        $('#username').val(user.Username);
        
        $('#modalTitle').text('Edit User');
        $('#passHint').text('(Leave blank to keep current)');
        
        openModal();
    };

    // Delete
    window.deleteUser = function(id) {
        if (!confirm('Are you sure you want to delete this user?')) return;
        
        $.post('registeruser_api.php', { action: 'delete_user', userid: id }, function(res) {
            if (res.status === 'success') {
                loadUsers(currentPage); 
            } else {
                alert(res.message);
            }
        }, 'json');
    };

    // --- Event Listeners ---
    
    $('#btnNewUser').click(openModal);

    $('#userForm').submit(function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        
        $.post('registeruser_api.php', formData, function(res) {
            if (res.status === 'success') {
                closeModal();
                loadUsers(); // Reload to page 1 for new entries
            } else {
                alert(res.message);
            }
        }, 'json').fail(function() {
            alert('Server error occurred.');
        });
    });

    // Debounce search
    let searchTimeout;
    $('#searchInput').on('keyup', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            loadUsers(1); // Reset to page 1
        }, 300);
    });

    // Init
    loadUsers();

</script>

<?php include 'includes/footer.php'; ?>
