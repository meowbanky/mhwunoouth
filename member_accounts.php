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

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>

    <div class="flex-1 overflow-y-auto p-6">
        <div class="max-w-7xl mx-auto space-y-6">

            <!-- Header -->
            <div class="bg-gradient-to-r from-primary to-secondary rounded-2xl shadow-lg p-6 text-white">
                <div class="flex items-center justify-between flex-wrap gap-4">
                    <div>
                        <h1 class="text-2xl font-bold mb-1 flex items-center gap-3">
                            <span class="material-icons-round text-3xl">manage_accounts</span>
                            Member Portal Accounts
                        </h1>
                        <p class="text-white/80 text-sm">Manage sign-in access for the member self-service portal</p>
                    </div>
                    <a href="portal/" target="_blank" rel="noopener"
                       class="bg-white/20 hover:bg-white/30 backdrop-blur-sm px-4 py-2.5 rounded-xl text-sm font-medium flex items-center gap-2 transition-colors">
                        <span class="material-icons-round text-lg">open_in_new</span> Open portal
                    </a>
                </div>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Active Members</p>
                    <p class="text-2xl font-bold text-slate-800 dark:text-white" id="statMembers">—</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Registered</p>
                    <p class="text-2xl font-bold text-emerald-600 dark:text-emerald-400" id="statRegistered">—</p>
                    <p class="text-xs text-slate-500 mt-1"><span id="statPercent">0</span>% signed up</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Locked Out</p>
                    <p class="text-2xl font-bold text-amber-500" id="statLocked">—</p>
                </div>
                <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 p-5">
                    <p class="text-xs font-medium uppercase tracking-wider text-slate-500 mb-1">Suspended</p>
                    <p class="text-2xl font-bold text-red-500" id="statSuspended">—</p>
                </div>
            </div>

            <!-- Controls + table -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
                <div class="p-5 border-b border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row gap-3 sm:items-center justify-between">
                    <div class="relative flex-1 max-w-sm">
                        <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        <input id="search" type="text" placeholder="Search name, ID or email..."
                               class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm">
                    </div>
                    <select id="filter"
                            class="px-4 py-2.5 bg-slate-50 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary outline-none text-sm">
                        <option value="all">All members</option>
                        <option value="registered">Registered only</option>
                        <option value="unregistered">Not yet registered</option>
                        <option value="locked">Locked out</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 dark:bg-slate-800/50 border-b border-slate-200 dark:border-slate-800">
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Member</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Login Email</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider">Last Sign-in</th>
                                <th class="px-6 py-4 text-xs font-bold text-slate-500 uppercase tracking-wider text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="tableBody" class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr><td colspan="5" class="p-10 text-center text-slate-400">Loading...</td></tr>
                        </tbody>
                    </table>
                </div>

                <div class="px-6 py-4 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between bg-slate-50/50 dark:bg-slate-800/30">
                    <div class="text-sm text-slate-500">
                        Page <span id="pagCurrent" class="font-bold">1</span> of <span id="pagTotal" class="font-bold">1</span>
                        <span class="hidden sm:inline">· <span id="pagCount">0</span> member(s)</span>
                    </div>
                    <div class="flex gap-2">
                        <button onclick="changePage(-1)" id="btnPrev" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-40 transition-colors">Previous</button>
                        <button onclick="changePage(1)" id="btnNext" class="px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 hover:bg-white dark:hover:bg-slate-700 disabled:opacity-40 transition-colors">Next</button>
                    </div>
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
    let currentPage = 1, totalPages = 1, searchTimer;

    $(function () { loadStats(); load(1); });

    function api(action, data = {}) {
        return $.post('member_accounts_api.php', Object.assign({ action }, data), null, 'json');
    }

    function loadStats() {
        api('stats').done(res => {
            if (res.status !== 'success') return;
            const d = res.data;
            $('#statMembers').text(d.active_members);
            $('#statRegistered').text(d.registered);
            $('#statLocked').text(d.locked);
            $('#statSuspended').text(d.suspended);
            $('#statPercent').text(d.active_members > 0 ? Math.round(d.registered / d.active_members * 100) : 0);
        });
    }

    function load(page) {
        currentPage = page;
        api('fetch_accounts', { page, search: $('#search').val(), filter: $('#filter').val() })
            .done(res => {
                if (res.status !== 'success') { Swal.fire('Error', res.message, 'error'); return; }
                render(res.data.rows);
                const p = res.data.pagination;
                totalPages = p.total_pages;
                $('#pagCurrent').text(p.current_page);
                $('#pagTotal').text(p.total_pages);
                $('#pagCount').text(p.total);
                $('#btnPrev').prop('disabled', p.current_page <= 1);
                $('#btnNext').prop('disabled', p.current_page >= p.total_pages);
            })
            .fail(() => Swal.fire('Error', 'Could not load accounts.', 'error'));
    }

    function esc(s) { return $('<div>').text(s == null ? '' : s).html(); }

    function statusBadge(r) {
        if (!r.is_registered) return '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 dark:bg-slate-800 text-slate-500">Not registered</span>';
        if (r.status === 'suspended') return '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400">Suspended</span>';
        if (r.is_locked) return '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">Locked</span>';
        return '<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">Active</span>';
    }

    function actions(r) {
        if (!r.is_registered) {
            return '<span class="text-xs text-slate-400 italic">Member registers themselves</span>';
        }

        const id = esc(r.patientid);
        const btn = (fn, icon, label, cls) =>
            `<button onclick="${fn}" title="${label}" class="p-2 rounded-lg transition-colors ${cls}"><span class="material-icons-round text-lg">${icon}</span></button>`;

        let html = btn(`editEmail('${id}', '${esc(r.email)}')`, 'edit', 'Change email', 'text-primary hover:bg-primary/10');
        if (r.is_locked) {
            html += btn(`unlock('${id}')`, 'lock_open', 'Unlock', 'text-amber-600 hover:bg-amber-50 dark:hover:bg-amber-900/20');
        }
        html += r.status === 'suspended'
            ? btn(`setStatus('${id}', 'active')`, 'play_circle', 'Reactivate', 'text-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20')
            : btn(`setStatus('${id}', 'suspended')`, 'block', 'Suspend', 'text-orange-600 hover:bg-orange-50 dark:hover:bg-orange-900/20');
        html += btn(`removeAccount('${id}')`, 'delete', 'Remove account', 'text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20');

        return html;
    }

    function render(rows) {
        if (!rows.length) {
            $('#tableBody').html('<tr><td colspan="5" class="p-10 text-center text-slate-400">No members match.</td></tr>');
            return;
        }

        $('#tableBody').html(rows.map(r => `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition-colors">
                <td class="px-6 py-4">
                    <p class="font-medium text-sm text-slate-900 dark:text-slate-100">${esc(r.fullname)}</p>
                    <p class="text-xs text-slate-500 font-mono">ID: ${esc(r.patientid)}${r.Dept ? ' · ' + esc(r.Dept) : ''}</p>
                </td>
                <td class="px-6 py-4 text-sm ${r.is_registered ? 'text-slate-700 dark:text-slate-300' : 'text-slate-400 italic'}">
                    ${r.is_registered ? esc(r.email) : '—'}
                </td>
                <td class="px-6 py-4">${statusBadge(r)}${r.failed_attempts > 0 ? `<p class="text-[10px] text-slate-400 mt-1">${r.failed_attempts} failed attempt(s)</p>` : ''}</td>
                <td class="px-6 py-4 text-sm text-slate-500">${r.last_login_at ? esc(r.last_login_at) : '<span class="italic text-slate-400">Never</span>'}</td>
                <td class="px-6 py-4"><div class="flex items-center gap-1 justify-end">${actions(r)}</div></td>
            </tr>`).join(''));
    }

    async function editEmail(id, current) {
        const { value: email } = await Swal.fire({
            title: 'Change login email',
            html: `<p style="font-size:13px;color:#64748b;margin-bottom:12px">
                     This address is the member's username and where password reset codes are sent.
                     Confirm the change with them first.</p>`,
            input: 'email',
            inputValue: current,
            inputPlaceholder: 'member@example.com',
            showCancelButton: true,
            confirmButtonText: 'Update email',
            confirmButtonColor: '#0284c7',
        });
        if (!email) return;

        api('update_email', { member_id: id, email })
            .done(res => {
                if (res.status === 'success') {
                    Swal.fire('Updated', res.message, 'success');
                    load(currentPage);
                } else {
                    Swal.fire('Could not update', res.message, 'error');
                }
            });
    }

    async function setStatus(id, newStatus) {
        const suspending = newStatus === 'suspended';
        const ok = await Swal.fire({
            title: suspending ? 'Suspend this account?' : 'Reactivate this account?',
            text: suspending
                ? 'The member will be signed out and unable to sign in until reactivated. Their records are untouched.'
                : 'The member will be able to sign in again.',
            icon: suspending ? 'warning' : 'question',
            showCancelButton: true,
            confirmButtonText: suspending ? 'Suspend' : 'Reactivate',
            confirmButtonColor: suspending ? '#ef4444' : '#10b981',
        });
        if (!ok.isConfirmed) return;

        api('set_status', { member_id: id, new_status: newStatus })
            .done(res => {
                Swal.fire(res.status === 'success' ? 'Done' : 'Error', res.message, res.status === 'success' ? 'success' : 'error');
                if (res.status === 'success') { load(currentPage); loadStats(); }
            });
    }

    function unlock(id) {
        api('unlock', { member_id: id })
            .done(res => {
                Swal.fire(res.status === 'success' ? 'Unlocked' : 'Error', res.message, res.status === 'success' ? 'success' : 'error');
                if (res.status === 'success') { load(currentPage); loadStats(); }
            });
    }

    async function removeAccount(id) {
        const ok = await Swal.fire({
            title: 'Remove portal account?',
            html: `<p style="font-size:13px;color:#64748b">
                     Deletes the sign-in credentials only. Contributions, loans and all financial records are untouched.
                     The member can register again from scratch.</p>`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Remove account',
            confirmButtonColor: '#ef4444',
        });
        if (!ok.isConfirmed) return;

        api('delete_account', { member_id: id })
            .done(res => {
                Swal.fire(res.status === 'success' ? 'Removed' : 'Error', res.message, res.status === 'success' ? 'success' : 'error');
                if (res.status === 'success') { load(currentPage); loadStats(); }
            });
    }

    window.changePage = function (delta) {
        const next = currentPage + delta;
        if (next >= 1 && next <= totalPages) load(next);
    };

    $('#search').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => load(1), 300);
    });

    $('#filter').on('change', () => load(1));
</script>

<?php include 'includes/footer.php'; ?>
