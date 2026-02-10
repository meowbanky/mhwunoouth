<?php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

require_once('Connections/hms.php');
// $conn is available from hms.php

// Handle AJAX Request for adding loan
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_loan') {
    header('Content-Type: application/json');
    
    $coopId = isset($_POST['coopid']) ? trim($_POST['coopid']) : '';
    $amount = isset($_POST['amount']) ? str_replace(',', '', $_POST['amount']) : 0;
    // Prioritize POST period, then Session, then 0
    $period = isset($_POST['period']) ? $_POST['period'] : (isset($_SESSION['period']) ? $_SESSION['period'] : 0);
    
    // Server-Side Interest Calculation
    try {
        $stmtSettings = $conn->query("SELECT * FROM tbl_settings LIMIT 1");
        $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
        $rate = isset($settings['Interest']) ? floatval($settings['Interest']) : 0.1;
    } catch (PDOException $e) {
        $rate = 0.1;
    }
    
    // Recalculate interest to ignore frontend manipulation
    $interest = floatval($amount) * $rate;

    if (empty($coopId) || empty($amount) || empty($period)) {
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields (ID, Amount, Period).']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Insert into tbl_loan
        $stmt = $conn->prepare("INSERT INTO tbl_loan (memberid, periodid, loanamount, interest) VALUES (:memberid, :periodid, :loanamount, :interest)");
        $stmt->execute([
            ':memberid' => $coopId,
            ':periodid' => $period,
            ':loanamount' => floatval($amount) + 100, // Preserving original logic
            ':interest' => $interest
        ]);
        $loanID = $conn->lastInsertId();

        // 2. Insert into tlb_mastertransaction
         $stmtMaster = $conn->prepare("INSERT INTO tlb_mastertransaction (periodid, memberid, loanid, loanAmount, interest) VALUES (:periodid, :memberid, :loanid, :loanamount, :interest)");
         $stmtMaster->execute([
            ':periodid' => $period,
            ':memberid' => $coopId,
            ':loanid' => $loanID,
            ':loanamount' => floatval($amount) + 100,
            ':interest' => $interest
         ]);

        // 3. Insert into tbl_bank_schedule
        // Fetch name first
        $stmtName = $conn->prepare("SELECT concat(ifnull(Lname,''),' ',ifnull(Fname,''),' ',ifnull(Mname,'')) as `name` FROM tbl_personalinfo WHERE patientid = :patientid");
        $stmtName->execute([':patientid' => $coopId]);
        $row_name = $stmtName->fetch(PDO::FETCH_ASSOC);
        $memberName = $row_name ? $row_name['name'] : '';

        $stmtBank = $conn->prepare("INSERT INTO tbl_bank_schedule (memberid, name, periodid, loanamount) VALUES (:memberid, :name, :periodid, :loanamount)");
        $stmtBank->execute([
            ':memberid' => $coopId,
            ':name' => $memberName,
            ':periodid' => $period,
            ':loanamount' => floatval($amount)
        ]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Loan added successfully']);

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

// Fetch Recent Loans (Batch) for Table
$col_Batch = isset($_SESSION['period']) ? $_SESSION['period'] : "-1";

try {
    $query_Batch = "SELECT CONCAT(tbl_personalinfo.Lname,' , ',tbl_personalinfo.Fname,' ',(ifnull(tbl_personalinfo.Mname,' '))) AS `name`, 
                        (tbl_loan.loanamount + tbl_loan.interest) as loanamount, 
                        tbl_loan.loanid, tbl_loan.periodid, tbl_loan.memberid, 
                        tbl_contributions.loan as loanrepayment 
                        FROM tbl_personalinfo 
                        INNER JOIN tbl_loan ON tbl_loan.memberid = tbl_personalinfo.patientid 
                        LEFT JOIN tbl_contributions ON tbl_contributions.membersid = tbl_personalinfo.patientid 
                        WHERE tbl_loan.periodid = :periodid ORDER BY tbl_loan.loanid DESC";
    $stmtBatch = $conn->prepare($query_Batch);
    $stmtBatch->execute([':periodid' => $col_Batch]);
    $batchLoans = $stmtBatch->fetchAll(PDO::FETCH_ASSOC);

    // Calculate Totals
    $stmtSum = $conn->prepare("SELECT (sum(loanamount)+sum(interest)) as amount FROM tbl_loan WHERE periodId = :periodid");
    $stmtSum->execute([':periodid' => $col_Batch]);
    $row_batchsum = $stmtSum->fetch(PDO::FETCH_ASSOC);
    $totalLoanAmount = $row_batchsum['amount'] ?? 0;

} catch (PDOException $e) {
    die("Error fetching loans: " . $e->getMessage());
}

// Fetch Interest Rate from Settings
try {
    $stmtSettings = $conn->query("SELECT * FROM tbl_settings LIMIT 1");
    $settings = $stmtSettings->fetch(PDO::FETCH_ASSOC);
    $interestRate = isset($settings['Interest']) ? $settings['Interest'] : 0.1; // Default 10% if not found
} catch (PDOException $e) {
    $interestRate = 0.1;
}

?>
<input type="hidden" id="interestRate" value="<?php echo htmlspecialchars($interestRate); ?>" />
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>
    
    <!-- Main Content -->
    <div class="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full overflow-y-auto">
        
        <!-- Header -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <h2 class="text-2xl font-bold text-slate-900 dark:text-white">Loan Application</h2>
                <p class="text-slate-500 dark:text-slate-400">Create and preview new loan entries for staff members.</p>
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

        <!-- Add Loan Form -->
        <section class="bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mb-10">
            <div class="p-6 border-b border-slate-200 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/20">
                <h3 class="font-semibold text-slate-900 dark:text-white">Add New Loan</h3>
            </div>
            <div class="p-6 md:p-8">
                <form id="addLoanForm" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member ID</label>
                        <div class="relative">
                            <input id="txtCoopid" name="coopid" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" placeholder="Enter Member ID (e.g. coop-001)" type="text" onkeyup="lookup(this.value);" autocomplete="off"/>
                            <div id="suggestions" class="absolute z-50 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg mt-1 hidden max-h-48 overflow-y-auto">
                                <div id="autoSuggestionsList"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Member Name</label>
                        <input id="txtMemberName" class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-700 dark:text-slate-300" readonly="" type="text" placeholder="Name will appear here"/>
                    </div>
                    
                    <div class="space-y-1">
                         <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Current Balance (₦)</label>
                         <input id="txtLoanBalance" class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400" readonly="" type="text" value="0.00"/>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Amount Granted (₦)</label>
                        <input id="txtAmount" name="amount" class="w-full bg-slate-50 dark:bg-slate-900/50 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm focus:ring-primary focus:border-primary" placeholder="0.00" type="text" onkeyup="formatNumber(this)" onblur="calculateInterest()"/>
                    </div>
                    
                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Interest (₦)</label>
                        <input id="txtInterest" name="interest" class="w-full bg-slate-100 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-lg px-4 py-2 text-sm font-medium text-slate-600 dark:text-slate-400" type="text" value="0.00" readonly=""/>
                    </div>

                    <div class="space-y-1">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-500 dark:text-slate-400">Repayment Period</label>
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

                    <div class="md:col-span-2 lg:col-span-3 pt-4 border-t border-slate-100 dark:border-slate-800 flex justify-end">
                        <button id="btnAddLoan" class="bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-semibold text-sm transition-all shadow-lg shadow-primary/20 flex items-center gap-2" type="button">
                            <span class="material-icons-round text-sm">add</span>
                            Add Loan to Schedule
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
                    <h3 class="font-semibold text-slate-900 dark:text-white">Loan Preview Schedule</h3>
                </div>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 dark:bg-slate-800/30">
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Name</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Loan Amount</th>
                            <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">Ref</th>
                             <th class="px-6 py-4 text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800" id="loanTableBody">
                        <?php if (count($batchLoans) > 0) { ?>
                            <?php foreach ($batchLoans as $row) { ?>
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors">
                                    <td class="px-6 py-4">
                                        <div class="flex flex-col">
                                            <span class="text-sm font-semibold text-slate-900 dark:text-white"><?php echo htmlspecialchars($row['name']); ?></span>
                                            <span class="text-[11px] text-slate-500">ID: <?php echo htmlspecialchars($row['memberid']); ?></span>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 text-sm font-medium">₦<?php echo number_format($row['loanamount'], 2); ?></td>
                                    <td class="px-6 py-4 text-sm font-medium text-slate-500">#<?php echo htmlspecialchars($row['loanid']); ?></td>
                                    <td class="px-6 py-4 text-right">
                                         <button class="text-red-500 hover:text-red-700" onclick="deleteLoan(<?php echo $row['loanid']; ?>)"><span class="material-icons-round text-sm">delete</span></button>
                                    </td>
                                </tr>
                            <?php } ?>
                        <?php } else { ?>
                            <tr><td colspan="4" class="px-6 py-4 text-center text-slate-500 italic">No loans found for this period</td></tr>
                        <?php } ?>
                    </tbody>
                    <tfoot id="loanTableFoot">
                        <tr class="bg-slate-50/50 dark:bg-slate-800/10">
                            <td class="px-6 py-4 font-bold text-sm">Totals</td>
                            <td class="px-6 py-4 font-bold text-sm text-primary">₦<?php echo number_format($totalLoanAmount, 2); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </section>

    </div>
</main>

<!-- Scripts -->
<script src="jquery-1.2.1.pack.js"></script>
<script>
    // Include the original formatNumber logic
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
        // Hide with a slight delay to allow click to register if needed, though immediate is usually fine with onclick
        setTimeout(function() {
             $('#suggestions').hide();
        }, 100);
        getLoanBalance(thisValue);
        
        // Autofocus Amount
        $('#txtAmount').focus();
    }
    
    // Get Loan Balance via AJAX
    function getLoanBalance(id) {
        $.get("loanBalance.php?id="+id, function(data){
            $('#txtLoanBalance').val(data.trim());
        });
    }


    // Set Period Logic
    function setPeriod(periodId) {
        if (periodId) {
            loadLoanTable(periodId);
        }
    }

    // Load Loan Table
    function loadLoanTable(periodId = null) {
        // If no period passed, try to get from dropdown
        if (!periodId) {
            periodId = $('#PeriodId').val();
        }
        
        // If still empty, maybe default or nothing
        var url = "fetch_loans.php";
        if (periodId) {
            url += "?period=" + periodId;
        }

        $.getJSON(url, function(data) {
            $('#loanTableBody').html(data.body);
            $('#loanTableFoot').html(data.footer);
        });
    }

    // Delete Loan
    function deleteLoan(loanID) {
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
                 $.get("deleteLoan.php?loanID="+loanID, function(data){
                    Swal.fire(
                        'Deleted!',
                        'Loan has been deleted.',
                        'success'
                    ).then(() => {
                        loadLoanTable(); // Refresh table without reload
                    });
                });
            }
        })
    }
    
    // Set Period Logic

    // Calculate Interest
    function calculateInterest() {
        var amountStr = $('#txtAmount').val().replace(/,/g, '');
        var amount = parseFloat(amountStr);
        var rate = parseFloat($('#interestRate').val());
        
        if (!isNaN(amount) && !isNaN(rate)) {
            var interest = amount * rate;
            $('#txtInterest').val(interest); 
        }
    }

    // Add Loan Submission
    $(document).ready(function() {
        $('#btnAddLoan').click(function() {
            var coopid = $('#txtCoopid').val();
            var amount = $('#txtAmount').val();
            var interest = $('#txtInterest').val();
            var period = $('#PeriodId').val();

            if(coopid == '' || amount == '') {
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
                    text: 'Please select a repayment period.'
                });
                return;
            }

            $.post("addloan.php", {
                action: 'add_loan',
                coopid: coopid,
                amount: amount,
                interest: interest,
                period: period
            }, function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: 'Loan added successfully',
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => {
                        // Clear inputs
                        $('#txtCoopid').val('');
                        $('#txtMemberName').val('');
                        $('#txtLoanBalance').val('0.00');
                        $('#txtAmount').val('');
                        $('#txtInterest').val('0.00');
                        
                        loadLoanTable(); // Refresh table
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
