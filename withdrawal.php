<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');
// $conn is available from hms.php

// Handle AJAX Request for adding withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_withdrawal') {
    header('Content-Type: application/json');
    
    $coopId = isset($_POST['coopid']) ? trim($_POST['coopid']) : '';
    $amount = isset($_POST['amount']) ? str_replace(',', '', $_POST['amount']) : 0;
    // Prioritize POST period, then Session, then 0
    $period = isset($_POST['period']) ? $_POST['period'] : (isset($_SESSION['period']) ? $_SESSION['period'] : 0);
    
    if (empty($coopId) || empty($amount) || empty($period)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields (ID, Amount, Period).']);
        exit;
    }

    $withdrawalAmount = floatval($amount);

    try {
        $conn->beginTransaction();

        // Check the current balance first to validate it in backend
        $stmtBalance = $conn->prepare("SELECT (IFNULL(SUM(Contribution),0) + IFNULL(SUM(withdrawal),0)) AS balance FROM tlb_mastertransaction WHERE memberid = :memberid");
        $stmtBalance->execute([':memberid' => $coopId]);
        $rowBalance = $stmtBalance->fetch(PDO::FETCH_ASSOC);
        $currentBalance = $rowBalance['balance'] ?? 0;

        if ($withdrawalAmount > $currentBalance) {
            echo json_encode(['status' => 'error', 'message' => 'Insufficient savings balance.']);
            $conn->rollBack();
            exit;
        }

        // Insert into tlb_mastertransaction as a negative amount for withdrawal
        $negativeAmount = -$withdrawalAmount;
        $stmtMaster = $conn->prepare("INSERT INTO tlb_mastertransaction (periodid, memberid, withdrawal) VALUES (:periodid, :memberid, :withdrawal)");
        $stmtMaster->execute([
            ':periodid' => $period,
            ':memberid' => $coopId,
            ':withdrawal' => $negativeAmount
        ]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Withdrawal posted successfully']);

    } catch (PDOException $e) {
        $conn->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
    
    exit;
}

// Fetch Periods for Dropdown
try {
    $stmtPeriod = $conn->query("SELECT Periodid, PayrollPeriod FROM tbpayrollperiods ORDER BY Periodid DESC");
    $periods = $stmtPeriod->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Error fetching periods: " . $e->getMessage());
}

// Fetch Recent Withdrawals (Batch) for Table
$col_Batch = isset($_SESSION['period']) ? $_SESSION['period'] : "-1";

try {
    $query_Batch = "SELECT CONCAT(tbl_personalinfo.Lname,' , ',tbl_personalinfo.Fname,' ',(ifnull(tbl_personalinfo.Mname,' '))) AS `name`, 
                        abs(tlb_mastertransaction.withdrawal) as withdrawalamount, 
                        tlb_mastertransaction.transactionid, tlb_mastertransaction.periodid, tlb_mastertransaction.memberid 
                        FROM tbl_personalinfo 
                        INNER JOIN tlb_mastertransaction ON tlb_mastertransaction.memberid = tbl_personalinfo.patientid 
                        WHERE tlb_mastertransaction.periodid = :periodid 
                        AND tlb_mastertransaction.withdrawal < 0 
                        ORDER BY tlb_mastertransaction.transactionid DESC";
    $stmtBatch = $conn->prepare($query_Batch);
    $stmtBatch->execute([':periodid' => $col_Batch]);
    $batchWithdrawals = $stmtBatch->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Totals
    $stmtSum = $conn->prepare("SELECT abs(sum(withdrawal)) as amount FROM tlb_mastertransaction WHERE periodId = :periodid AND withdrawal < 0");
    $stmtSum->execute([':periodid' => $col_Batch]);
    $row_batchsum = $stmtSum->fetch(PDO::FETCH_ASSOC);
    $totalWithdrawalAmount = $row_batchsum['amount'] ?? 0;

} catch (PDOException $e) {
    die("Error fetching withdrawals: " . $e->getMessage());
}

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full overflow-y-auto">
        
        <!-- Header -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Withdrawal Application</h2>
                <p class="text-slate-500 dark:text-slate-400">Post new savings withdrawals for staff members.</p>
            </div>
            <div class="flex items-center gap-2">
                <button class="flex items-center gap-2 px-4 py-2 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg text-sm font-medium hover:bg-slate-50 transition-colors">
                    <span class="material-icons-round text-sm">print</span>
                    Print Schedule
                </button>
            </div>
        </header>

        <!-- Notification Area -->
        <div id="notification" class="hidden mb-6 p-4 rounded-lg bg-emerald-50 text-emerald-800 border border-emerald-200 text-sm font-medium"></div>

        <!-- Add Withdrawal Form -->
        <section class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mb-10">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="font-semibold text-slate-900 dark:text-white">Add New Withdrawal</h3>
            </div>
            <div class="p-6 md:p-8">
                <form id="addWithdrawalForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member ID</label>
                        <div class="relative flex items-center">
                            <input id="txtCoopid" name="coopid" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 pr-10 text-sm focus:ring-primary focus:border-primary" placeholder="Enter Member ID (e.g. coop-001)" type="text" onkeyup="lookup(this.value); toggleClearBtn(this.value);" autocomplete="off"/>
                            <button type="button" id="btnClearSearch" class="absolute right-3 text-red-500 hover:text-red-700 hidden flex items-center justify-center" onclick="clearSearch()">
                                <span class="material-icons-round text-sm">close</span>
                            </button>
                            <div id="suggestions" class="absolute top-full left-0 z-50 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg mt-1 hidden max-h-48 overflow-y-auto">
                                <div id="autoSuggestionsList"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member Name</label>
                        <input id="txtMemberName" class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" readonly="" type="text" placeholder="Name will appear here"/>
                    </div>
                    
                    <div class="space-y-1">
                         <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Available Savings Balance (₦)</label>
                         <input id="txtSavingsBalance" class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400" readonly="" type="text" value="0.00"/>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Withdrawal Amount (₦)</label>
                        <input id="txtAmount" name="amount" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" placeholder="0.00" type="text" onkeyup="formatNumber(this)"/>
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Posting Period</label>
                        <div class="flex gap-2">
                            <select id="PeriodId" name="period" class="flex-1 bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" onchange="setPeriod(this.value)">
                                <option value="">Select Period</option>
                                <?php foreach ($periods as $p) { ?>
                                    <option value="<?php echo $p['Periodid']?>" <?php if(isset($_SESSION['period']) && $_SESSION['period'] == $p['Periodid']) echo 'selected="selected"'; ?>><?php echo $p['PayrollPeriod']?></option>
                                <?php } ?>
                            </select>
                            <!-- Hidden input for consistency -->
                            <input type="hidden" name="periodset" id="periodset" value="<?php echo isset($_SESSION['period']) ? $_SESSION['period'] : ''; ?>">
                        </div>
                    </div>

                    <div class="md:col-span-2 lg:col-span-1 pt-4 flex justify-end items-end">
                        <button id="btnAddWithdrawal" class="w-full bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-semibold text-sm transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2" type="button">
                            <span class="material-icons-round text-sm">add</span>
                            Post Withdrawal
                        </button>
                    </div>
                </form>
            </div>
        </section>

        <!-- Preview Schedule -->
        <section class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <span class="material-icons-round text-primary">visibility</span>
                    <h3 class="font-semibold text-slate-900 dark:text-white">Withdrawal Preview Schedule</h3>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/30">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Name</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Withdrawal Amount</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Ref (Txn ID)</th>
                             <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="withdrawalTableBody">
                        <?php if (count($batchWithdrawals) > 0) { ?>
                            <?php foreach ($batchWithdrawals as $row) { ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row['name']); ?></span>
                                            <span class="text-[11px] text-slate-500">ID: <?php echo htmlspecialchars($row['memberid']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">₦<?php echo number_format($row['withdrawalamount'], 2); ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-500">#<?php echo htmlspecialchars($row['transactionid']); ?></td>
                                    <td class="px-6 py-4 text-right">
                                         <button class="text-red-500 hover:text-red-700" onclick="deleteWithdrawal(<?php echo $row['transactionid']; ?>)"><span class="material-icons-round text-sm">delete</span></button>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-slate-500 italic">No withdrawals found for this period</td></tr>
                        <?php } ?>
                    </tbody>
                    <tfoot id="withdrawalTableFoot">
                        <tr class="bg-slate-50/50 dark:bg-slate-800/10">
                            <td class="px-6 py-4 font-bold text-sm">Totals</td>
                            <td class="px-6 py-4 font-bold text-sm text-primary">₦<?php echo number_format($totalWithdrawalAmount, 2); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

    </div>
</main>

<!-- Scripts -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script>
    function formatNumber(myElement) {
        var myVal = "";
        var myDec = "";
        var parts = myElement.value.toString().split(".");
        parts[0] = parts[0].replace(/[^0-9]/g,""); 
        if ( ! parts[1] && myElement.value.indexOf(".") > 1 ) { myDec = ".00" }
        if ( parts[1] ) { myDec = "."+parts[1] }
        while ( parts[0].length > 3 ) {
            myVal = ","+parts[0].substr(parts[0].length-3, parts[0].length )+myVal;
            parts[0] = parts[0].substr(0, parts[0].length-3)
        }
        myElement.value = parts[0]+myVal+myDec;
    }

    // Toggle Clear Button
    function toggleClearBtn(val) {
        if (val.length > 0) {
            $('#btnClearSearch').removeClass('hidden');
        } else {
            $('#btnClearSearch').addClass('hidden');
        }
    }

    // Clear Search
    function clearSearch() {
        $('#txtCoopid').val('');
        $('#txtMemberName').val('');
        $('#txtSavingsBalance').val('0.00');
        $('#btnClearSearch').addClass('hidden');
        $('#suggestions').hide();
        $('#txtCoopid').focus();
    }

    // Lookup
    function lookup(inputString) {
        if(inputString.length == 0) {
            $('#suggestions').hide();
        } else {
            $.post("rpc.php", {queryString: ""+inputString+""}, function(data){
                if(data.length >0) {
                    $('#suggestions').show();
                    $('#autoSuggestionsList').html(data);
                }
            });
        }
    }

    // Fill
    function fill(thisValue, thisName) {
        $('#txtCoopid').val(thisValue);
        $('#txtMemberName').val(thisName);
        toggleClearBtn(thisValue);
        setTimeout(function() {
             $('#suggestions').hide();
        }, 100);
        getSavingsBalance(thisValue);
        
        $('#txtAmount').focus();
    }
    
    // Get Savings Balance via AJAX
    function getSavingsBalance(id) {
        $.get("getSavingsBalance.php?id="+id, function(data){
            $('#txtSavingsBalance').val(data.trim());
        });
    }

    // Set Period Logic
    function setPeriod(periodId) {
        if (periodId) {
            loadWithdrawalTable(periodId);
        }
    }

    // Load Withdrawal Table
    function loadWithdrawalTable(periodId = null) {
        if (!periodId) {
            periodId = $('#PeriodId').val();
        }
        
        var url = "fetch_withdrawals.php";
        if (periodId) {
            url += "?period=" + periodId;
        }

        $.getJSON(url, function(data) {
            $('#withdrawalTableBody').html(data.body);
            $('#withdrawalTableFoot').html(data.footer);
        });
    }

    // Delete Withdrawal
    function deleteWithdrawal(transactionId) {
        Swal.fire({
            title: 'Are you sure?',
            text: "You won't be able to revert this!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                 $.get("deleteWithdrawal.php?transactionid="+transactionId, function(data){
                    if (data.status === 'success') {
                        Swal.fire(
                            'Deleted!',
                            'Withdrawal has been deleted.',
                            'success'
                        ).then(() => {
                            loadWithdrawalTable(); 
                        });
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                }, "json");
            }
        })
    }
    
    // Add Withdrawal Submission
    $(document).ready(function() {
        $('#btnAddWithdrawal').click(function() {
            var coopid = $('#txtCoopid').val();
            var amountStr = $('#txtAmount').val().replace(/,/g, '');
            var balanceStr = $('#txtSavingsBalance').val().replace(/,/g, '');
            var period = $('#PeriodId').val();

            if(coopid == '' || amountStr == '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill member ID and Amount'
                });
                return;
            }
            
            if(period == '') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Period',
                    text: 'Please select a posting period.'
                });
                return;
            }

            var amount = parseFloat(amountStr);
            var balance = parseFloat(balanceStr);

            if(amount > balance) {
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Funds',
                    text: 'Withdrawal amount cannot exceed the available savings balance.'
                });
                return;
            }

            $.post("withdrawal.php", {
                action: 'add_withdrawal',
                coopid: coopid,
                amount: amountStr, // Send as string or number, backend parses it
                period: period
            }, function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Withdrawal posted successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Clear inputs
                        $('#txtCoopid').val('');
                        $('#txtMemberName').val('');
                        $('#txtSavingsBalance').val('0.00');
                        $('#txtAmount').val('');
                        
                        loadWithdrawalTable(); 
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: response.message
                    });
                }
            }, 'json');
        });
    });
</script>

<?php include 'includes/footer.php'; ?>
