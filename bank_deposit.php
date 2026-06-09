<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');

// Fetch Periods for Dropdown
try {
    $stmtPeriod = $conn->query("SELECT Periodid, PayrollPeriod FROM tbpayrollperiods ORDER BY Periodid DESC");
    $periods = $stmtPeriod->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching periods: " . $e->getMessage());
}

$col_Batch = isset($_SESSION['period']) ? $_SESSION['period'] : "-1";

// Fetch existing deposits for current period
try {
    $stmtDeposits = $conn->prepare(
        "SELECT t.teller_id, t.memberid, t.periodid, t.teller_upload, t.repayment_bank,
                CONCAT(p.Lname, ' , ', p.Fname, ' ', IFNULL(p.Mname,'')) AS name
         FROM tbl_teller t
         INNER JOIN tbl_personalinfo p ON p.patientid = t.memberid
         WHERE t.periodid = :periodid
         ORDER BY t.teller_id DESC"
    );
    $stmtDeposits->execute([':periodid' => $col_Batch]);
    $deposits = $stmtDeposits->fetchAll(PDO::FETCH_ASSOC);

    $stmtTotal = $conn->prepare("SELECT SUM(repayment_bank) as total FROM tbl_teller WHERE periodid = :periodid");
    $stmtTotal->execute([':periodid' => $col_Batch]);
    $totalDeposit = $stmtTotal->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
} catch (PDOException $e) {
    $deposits = [];
    $totalDeposit = 0;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>

    <div class="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full overflow-y-auto">

        <!-- Header -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Bank Deposits</h2>
                <p class="text-slate-500 dark:text-slate-400">Record member bank teller deposits for the current period.</p>
            </div>
        </header>

        <!-- Notification Area -->
        <div id="notification" class="hidden mb-6 p-4 rounded-lg text-sm font-medium"></div>

        <!-- Add Deposit Form -->
        <section class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mb-10">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="font-semibold text-slate-900 dark:text-white">Add Bank Deposit</h3>
            </div>
            <div class="p-6 md:p-8">
                <form id="addDepositForm" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                    <!-- Member Search -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member ID</label>
                        <div class="relative flex items-center">
                            <input id="txtCoopid" name="coopid" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 pr-10 text-sm focus:ring-primary focus:border-primary" placeholder="Enter Member ID or Name" type="text" onkeyup="lookup(this.value); toggleClearBtn(this.value);" autocomplete="off"/>
                            <button type="button" id="btnClearSearch" class="absolute right-3 text-red-500 hover:text-red-700 hidden" onclick="clearSearch()">
                                <span class="material-icons-round text-sm">close</span>
                            </button>
                            <div id="suggestions" class="absolute top-full left-0 z-50 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg mt-1 hidden max-h-48 overflow-y-auto">
                                <div id="autoSuggestionsList"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Member Name (read-only) -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member Name</label>
                        <input id="txtMemberName" class="w-full bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" readonly type="text" placeholder="Name will appear here"/>
                    </div>

                    <!-- Period -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Period</label>
                        <select id="PeriodId" name="period" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" onchange="loadDepositTable(this.value)">
                            <option value="">Select Period</option>
                            <?php foreach ($periods as $p) { ?>
                                <option value="<?php echo $p['Periodid']; ?>" <?php if (isset($_SESSION['period']) && $_SESSION['period'] == $p['Periodid']) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($p['PayrollPeriod']); ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>

                    <!-- Amount -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Deposit Amount (₦)</label>
                        <input id="txtAmount" name="amount" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" placeholder="0.00" type="text" onkeyup="formatNumber(this)"/>
                    </div>

                    <!-- Teller Upload -->
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Teller Slip (optional)</label>
                        <input id="tellerFile" name="teller_file" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" type="file" accept="image/*,.pdf"/>
                    </div>

                    <div class="md:col-span-2 lg:col-span-3 pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                        <button id="btnAddDeposit" class="bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-semibold text-sm transition-all shadow-lg shadow-primary/20 flex items-center gap-2" type="button">
                            <span class="material-icons-round text-sm">add</span>
                            Add Deposit
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Deposits Table -->
        <section class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex items-center gap-2">
                <span class="material-icons-round text-primary">account_balance</span>
                <h3 class="font-semibold text-slate-900 dark:text-white">Deposits for Selected Period</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/30">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Member</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Amount (₦)</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Teller Slip</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="depositTableBody">
                        <?php if (count($deposits) > 0): ?>
                            <?php foreach ($deposits as $row): ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row['name']); ?></span>
                                            <span class="text-[11px] text-slate-500">ID: <?php echo htmlspecialchars($row['memberid']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">₦<?php echo number_format($row['repayment_bank'], 2); ?></td>
                                    <td class="px-6 py-4 text-sm">
                                        <?php if (!empty($row['teller_upload'])): ?>
                                            <a href="uploads/tellers/<?php echo htmlspecialchars($row['teller_upload']); ?>" target="_blank" class="text-blue-600 hover:underline flex items-center gap-1">
                                                <span class="material-icons-round text-sm">attach_file</span> View
                                            </a>
                                        <?php else: ?>
                                            <span class="text-slate-400 italic text-xs">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 text-right flex items-center justify-end gap-3">
                                        <button class="text-blue-500 hover:text-blue-700" onclick="openEditModal(<?php echo $row['teller_id']; ?>, '<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>', '<?php echo $row['repayment_bank']; ?>', '<?php echo htmlspecialchars($row['teller_upload'] ?? '', ENT_QUOTES); ?>')">
                                            <span class="material-icons-round text-sm">edit</span>
                                        </button>
                                        <button class="text-red-500 hover:text-red-700" onclick="deleteDeposit(<?php echo $row['teller_id']; ?>)">
                                            <span class="material-icons-round text-sm">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-slate-500 italic">No deposits found for this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot id="depositTableFoot">
                        <tr class="bg-slate-50/50 dark:bg-slate-800/10">
                            <td class="px-6 py-4 font-bold text-sm">Total</td>
                            <td class="px-6 py-4 font-bold text-sm text-primary">₦<?php echo number_format($totalDeposit, 2); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

    </div>
</main>

<!-- Edit Deposit Modal -->
<div id="editModal" class="fixed inset-0 z-50 hidden flex items-center justify-center bg-black/50 px-4">
    <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-2xl w-full max-w-md p-8">
        <div class="flex items-center justify-between mb-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Edit Bank Deposit</h3>
            <button onclick="closeEditModal()" class="text-slate-400 hover:text-slate-600">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <form id="editDepositForm" enctype="multipart/form-data" class="space-y-5">
            <input type="hidden" id="editTellerId" name="teller_id">
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-1">Member</label>
                <p id="editMemberName" class="text-sm font-semibold text-slate-800 dark:text-white bg-slate-100 dark:bg-slate-800 px-4 py-2 rounded-lg"></p>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-1">Amount (₦)</label>
                <input id="editAmount" name="amount" type="text" onkeyup="formatNumber(this)" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" placeholder="0.00"/>
            </div>
            <div>
                <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 block mb-1">Replace Teller Slip (optional)</label>
                <div id="editCurrentSlip" class="mb-2 text-sm"></div>
                <input id="editTellerFile" name="teller_file" type="file" accept="image/*,.pdf" class="w-full bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm"/>
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeEditModal()" class="px-6 py-2 rounded-lg text-sm font-medium border border-slate-200 text-slate-600 hover:bg-slate-50">Cancel</button>
                <button type="button" id="btnSaveEdit" class="px-6 py-2 rounded-lg text-sm font-semibold bg-primary text-white hover:bg-blue-700 transition-all">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script>
    function formatNumber(el) {
        var parts = el.value.toString().split(".");
        parts[0] = parts[0].replace(/[^0-9]/g, "");
        var myVal = "", myDec = "";
        if (!parts[1] && el.value.indexOf(".") > 1) myDec = ".00";
        if (parts[1]) myDec = "." + parts[1];
        while (parts[0].length > 3) {
            myVal = "," + parts[0].substr(parts[0].length - 3) + myVal;
            parts[0] = parts[0].substr(0, parts[0].length - 3);
        }
        el.value = parts[0] + myVal + myDec;
    }

    function toggleClearBtn(val) {
        if (val.length > 0) $('#btnClearSearch').removeClass('hidden');
        else $('#btnClearSearch').addClass('hidden');
    }

    function clearSearch() {
        $('#txtCoopid').val('');
        $('#txtMemberName').val('');
        $('#btnClearSearch').addClass('hidden');
        $('#suggestions').hide();
        $('#txtCoopid').focus();
    }

    function lookup(inputString) {
        if (inputString.length === 0) {
            $('#suggestions').hide();
        } else {
            $.post("rpc.php", { queryString: inputString }, function(data) {
                if (data.length > 0) {
                    $('#suggestions').show();
                    $('#autoSuggestionsList').html(data);
                }
            });
        }
    }

    function fill(thisValue, thisName) {
        $('#txtCoopid').val(thisValue);
        $('#txtMemberName').val(thisName);
        toggleClearBtn(thisValue);
        setTimeout(function() { $('#suggestions').hide(); }, 100);
        $('#txtAmount').focus();
    }

    function loadDepositTable(periodId) {
        if (!periodId) return;
        $.getJSON("fetch_deposits.php?period=" + periodId, function(data) {
            $('#depositTableBody').html(data.body);
            $('#depositTableFoot').html(data.footer);
        });
    }

    // ---- Edit Modal ----
    function openEditModal(id, name, amount, slipFile) {
        $('#editTellerId').val(id);
        $('#editMemberName').text(name);
        $('#editAmount').val(parseFloat(amount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}));
        $('#editTellerFile').val('');

        if (slipFile) {
            $('#editCurrentSlip').html('<a href="uploads/tellers/' + slipFile + '" target="_blank" class="text-blue-600 hover:underline text-xs flex items-center gap-1"><span class="material-icons-round text-sm">attach_file</span> Current: ' + slipFile + '</a>');
        } else {
            $('#editCurrentSlip').html('<span class="text-xs text-slate-400 italic">No current slip</span>');
        }

        $('#editModal').removeClass('hidden');
    }

    function closeEditModal() {
        $('#editModal').addClass('hidden');
    }

    // Close modal when clicking backdrop
    $('#editModal').on('click', function(e) {
        if ($(e.target).is('#editModal')) closeEditModal();
    });

    // ---- Delete ----
    function deleteDeposit(tellerId) {
        Swal.fire({
            title: 'Delete this deposit?',
            text: "This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e11d48',
            cancelButtonColor: '#64748b',
            confirmButtonText: 'Yes, delete'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post("bank_deposit_api.php", { action: 'delete_deposit', teller_id: tellerId }, function(res) {
                    if (res.status === 'success') {
                        showNotification('Deposit deleted.', 'success');
                        loadDepositTable($('#PeriodId').val());
                    } else {
                        showNotification(res.message, 'error');
                    }
                }, 'json');
            }
        });
    }

    function showNotification(msg, type) {
        var el = $('#notification');
        el.removeClass('hidden bg-emerald-50 text-emerald-800 border-emerald-200 bg-red-50 text-red-800 border-red-200');
        if (type === 'success') {
            el.addClass('bg-emerald-50 text-emerald-800 border border-emerald-200');
        } else {
            el.addClass('bg-red-50 text-red-800 border border-red-200');
        }
        el.text(msg).removeClass('hidden');
        setTimeout(function() { el.addClass('hidden'); }, 4000);
    }

    $(document).ready(function() {

        // Load table on page load using selected period
        var initPeriod = $('#PeriodId').val();
        if (initPeriod) loadDepositTable(initPeriod);

        // Add Deposit
        $('#btnAddDeposit').click(function() {
            var coopid = $('#txtCoopid').val().trim();
            var amount = $('#txtAmount').val().trim();
            var period = $('#PeriodId').val();

            if (!coopid || !amount) {
                Swal.fire({ icon: 'warning', title: 'Missing Info', text: 'Please fill in Member ID and Amount.' });
                return;
            }
            if (!period) {
                Swal.fire({ icon: 'warning', title: 'No Period', text: 'Please select a period.' });
                return;
            }

            var formData = new FormData($('#addDepositForm')[0]);
            formData.append('action', 'add_deposit');

            $.ajax({
                url: 'bank_deposit_api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({ icon: 'success', title: 'Saved', text: 'Bank deposit recorded.', timer: 1500, showConfirmButton: false })
                            .then(function() {
                                clearSearch();
                                $('#txtAmount').val('');
                                $('#tellerFile').val('');
                                loadDepositTable(period);
                            });
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.message });
                    }
                },
                error: function() {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Request failed. Please try again.' });
                }
            });
        });

        // Save Edit
        $('#btnSaveEdit').click(function() {
            var amount = $('#editAmount').val().trim();
            if (!amount) {
                showNotification('Amount is required.', 'error');
                return;
            }

            var formData = new FormData($('#editDepositForm')[0]);
            formData.append('action', 'edit_deposit');

            $.ajax({
                url: 'bank_deposit_api.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        closeEditModal();
                        showNotification('Deposit updated.', 'success');
                        loadDepositTable($('#PeriodId').val());
                    } else {
                        showNotification(res.message, 'error');
                    }
                },
                error: function() {
                    showNotification('Request failed. Please try again.', 'error');
                }
            });
        });

        // Close suggestions on outside click
        $(document).click(function(e) {
            if (!$(e.target).closest('#txtCoopid, #suggestions').length) {
                $('#suggestions').hide();
            }
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
