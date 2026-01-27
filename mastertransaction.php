<?php
require_once('logic/transaction_logic.php');

session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location:index.php");
    exit();
}

$editFormAction = $_SERVER['PHP_SELF'];
?>
<?php
$pageTitle = "Master Transaction";
include 'includes/header.php';
include 'includes/sidebar.php';
?>

<div class="relative flex flex-col flex-1 overflow-y-auto overflow-x-hidden">
    <?php include 'includes/topbar.php'; ?>
    
    <!-- Old Scripts & Styles -->
    <!-- Modern jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script type="text/javascript">
        $(document).ready(function() {
            // Auto focus on load
            $('#member_name').focus();
            
            // Show/Hide Clear Button logic
            $('#member_name').on('input', function() {
                if($(this).val().length > 0) {
                    $('#clearBtn').removeClass('hidden');
                } else {
                    $('#clearBtn').addClass('hidden');
                }
            });
        });

        // --- Deletion Logic ---

        async function deletetrans() {
            var selectedIds = [];
            $('input[name="memberid"]:checked').each(function() {
                selectedIds.push($(this).val());
            });

            if (selectedIds.length === 0) {
                Swal.fire({
                    title: 'No Selection',
                    text: 'Please select at least one item to delete.',
                    icon: 'warning',
                    confirmButtonColor: '#0ea5e9'
                });
                return;
            }

            const result = await Swal.fire({
                title: 'Are you sure?',
                text: "You are about to delete " + selectedIds.length + " transaction(s). This cannot be undone.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#ef4444',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete items!'
            });

            if(!result.isConfirmed) return;

            // Initialize Modal
            $('#processingModal').removeClass('hidden');
            $('#processSpinner').removeClass('hidden');
            $('#processCompleteIcon').addClass('hidden');
            $('#btnCloseProcess').addClass('hidden');
            $('#processLog').addClass('hidden').empty();
            $('#progressBar').css('width', '0%');
            $('#processTitle').text('Deleting Transactions...');
            
            let successCount = 0;
            let failCount = 0;
            const total = selectedIds.length;

            for (let i = 0; i < total; i++) {
                let val = selectedIds[i];
                let parts = val.split(",");
                let memberId = parts[0];
                let periodId = parts[1];
                let displayIdx = i + 1;

                // Update UI
                const percent = Math.round((displayIdx / total) * 100);
                $('#progressBar').css('width', percent + '%');
                $('#progressCount').text(`${displayIdx} / ${total}`);
                $('#progressPercent').text(`${percent}%`);
                $('#processStatus').text(`Deleting Member ID: ${memberId}...`);

                try {
                    await $.ajax({
                        url: 'deletetransaction.php',
                        type: 'GET',
                        data: { periodid: periodId, memberid: memberId }
                    });
                    successCount++;
                } catch (err) {
                    failCount++;
                    log(`Error deleting Member ${memberId}: ${err.statusText}`);
                }
            }

            // Finish
            $('#processSpinner').addClass('hidden');
            $('#processCompleteIcon').removeClass('hidden');
            $('#processTitle').text('Deletion Complete');
            
            let msg = `Successfully deleted ${successCount} items.`;
            if (failCount > 0) msg += ` Failed: ${failCount}.`;
            $('#processStatus').text(msg);

            // Reload automatically after short delay or show close button
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        }

        function log(msg) {
            const div = $('<div>').text(`[${new Date().toLocaleTimeString()}] ${msg}`);
            $('#processLog').removeClass('hidden').append(div);
        }

        function toPeriod() {
            var $from = $("#period_from");
            var $to = $("#period_to");
            
            if ($to.val() === 'na') {
                $to.val($from.val());
            }
        }

        function printTeller() {
            var mrn = $('#mrn').val();
            if(mrn) {
                var url = 'viewTeller.php?id=' + mrn;
                window.open(url, '', 'top=0,left=0,toolbar=no,resizable=no,status=no,width=800,height=600');
            } else {
                 Swal.fire('Error', 'Member ID not found for print.', 'error');
            }
        }

        function getMasterTransaction() {
            var fromPeriod = $('#period_from').val();
            var toPeriod = $('#period_to').val();
            var coopId = $('#staff_id').val();

            if (fromPeriod === "na" || !fromPeriod) {
                Swal.fire('Missing Input', "Please select 'From Period'", 'warning').then(() => $('#period_from').focus());
                return;
            }
            if (toPeriod === "na" || !toPeriod) {
                Swal.fire('Missing Input', "Please select 'To Period'", 'warning').then(() => $('#period_to').focus());
                return;
            }
            if (parseInt(fromPeriod) > parseInt(toPeriod)) {
                Swal.fire('Invalid Range', "'From Period' cannot be greater than 'To Period'", 'error');
                return;
            }

            // Show Loading
            $('#status').html('<div class="p-8 text-center text-slate-500"><div class="inline-block animate-spin rounded-full h-8 w-8 border-4 border-slate-200 border-r-primary mb-2"></div><p>Loading transactions...</p></div>').show();

            $.ajax({
                url: 'getMasterTransaction.php',
                type: 'GET',
                data: {
                    id: coopId,
                    periodTo: toPeriod,
                    periodfrom: fromPeriod
                },
                success: function(response) {
                    $('#status').html(response).fadeIn();
                },
                error: function(xhr, status, error) {
                    Swal.fire('Error', 'Failed to fetch data: ' + error, 'error');
                    $('#status').html('<div class="p-4 text-red-500">Error loading data.</div>');
                }
            });
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
            $('#staff_id').val(thisValue);
            $('#member_name').val(thisName);
            $('#clearBtn').removeClass('hidden'); // Show X icon
            setTimeout(function() {
                $('#suggestions').hide();
            }, 200);
        }

        function checknum(element) {
            if (isNaN(element.value)) {
                Swal.fire('Invalid Input', "Please enter numeric value only", 'error');
                element.value = "";
                element.focus();
                return false;
            }
            return true;
        }

        // Auto Logout replaced with modern redirection if needed, otherwise removed as "unused"
        // If critical for security, standard practice is server-side session expiry + client redirect.
        // I will trust the PHP session check at the top of the file.
        
    </script>
    
    <!-- Processing Modal -->
    <div id="processingModal" class="fixed inset-0 z-50 hidden" data-backdrop="static">
        <div class="absolute inset-0 bg-slate-900/80 backdrop-blur-sm transition-opacity"></div>
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-full max-w-lg bg-white dark:bg-slate-900 rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-800 p-8">
            
            <div class="text-center mb-6">
                <div id="processSpinner" class="inline-block animate-spin rounded-full h-12 w-12 border-4 border-primary border-r-transparent mb-4"></div>
                <div id="processCompleteIcon" class="hidden inline-flex items-center justify-center h-16 w-16 rounded-full bg-emerald-100 text-emerald-600 mb-4">
                    <span class="material-icons-round text-4xl">check</span>
                </div>
                
                <h3 class="text-xl font-bold" id="processTitle">Processing</h3>
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
                <button id="btnCloseProcess" onclick="window.location.reload()" class="hidden px-6 py-2.5 bg-slate-900 dark:bg-white text-white dark:text-slate-900 font-medium rounded-xl hover:opacity-90 transition-all">
                    Close & Refresh
                </button>
            </div>
        </div>
    </div>
    
    <main class="w-full flex-grow p-6">
        <!-- Page Title & Subtitle -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-2xl font-bold text-slate-800 dark:text-white">Member Transaction Search</h3>
                <p class="text-slate-500 dark:text-slate-400 text-sm">Query and track member transactions across the organization.</p>
            </div>
        </div>

        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="material-icons-round text-primary">person_search</span>
                    <h4 class="font-semibold">Search Member</h4>
                </div>
            </div>
            <div class="p-6">
                <!-- Form Wrapper -->
                <form name="eduEntry" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 items-end" onsubmit="return false;">
                    <input type="hidden" name="hiddenField" id="hiddenField" />
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Name</label>
                        <div class="relative">
                            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">person</span>
                            <input id="member_name" name="member_name" onkeyup="lookup(this.value);" class="w-full pl-10 pr-10 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary transition-all" placeholder="Enter name..." type="text" autocomplete="off"/>
                            <button id="clearBtn" type="button" onclick="clearSearch()" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-red-500 transition-colors hidden" title="Clear Search">
                                <span class="material-icons-round text-sm">close</span>
                            </button>
                            
                            <!-- Suggestions Box -->
                            <div id="suggestions" class="absolute z-100 w-full bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-lg mt-1 hidden max-h-48 overflow-y-auto">
                                <div id="autoSuggestionsList"></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Staff No. / ID</label>
                        <div class="relative">
                            <span class="material-icons-round absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">badge</span>
                            <input id="staff_id" name="staff_id" class="w-full pl-10 bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary transition-all" placeholder="ID populates here" type="text" readonly />
                        </div>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">Period From</label>
                        <select id="period_from" name="period_from" class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="na">Select Period</option>
                            <?php
                            if (!empty($all_periods)) {
                                foreach($all_periods as $period) { 
                            ?>
                            <option value="<?php echo $period['Periodid']?>"><?php echo $period['PayrollPeriod']?></option>
                            <?php 
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="space-y-2">
                        <label class="text-sm font-medium text-slate-700 dark:text-slate-300">To</label>
                        <select id="period_to" name="period_to" class="w-full bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg focus:ring-primary focus:border-primary">
                            <option value="na">Select Period</option>
                            <?php 
                            if (!empty($all_periods)) {
                                foreach($all_periods as $period) { 
                            ?>
                            <option value="<?php echo $period['Periodid']?>"><?php echo $period['PayrollPeriod']?></option>
                            <?php 
                                }
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2 lg:col-span-4 flex justify-end">
                        <button type="button" onclick="getMasterTransaction();" class="bg-primary hover:bg-blue-700 text-white font-semibold py-2.5 px-8 rounded-lg shadow-lg shadow-blue-500/20 flex items-center gap-2 transition-all active:scale-95">
                            <span class="material-icons-round text-sm">search</span>
                            Search Database
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden mt-6" style="min-height: 200px;">
            <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex items-center justify-between">
                <h4 class="font-semibold">Transaction Results</h4>
                <button class="text-slate-500 hover:text-primary p-1">
                    <span class="material-icons-round">filter_list</span>
                </button>
            </div>
            <!-- The status div where AJAX results are loaded -->
            <div id="status" class="overflow-x-auto p-4">
                <p class="text-slate-500 text-center">Select parameters and search to view transactions.</p>
            </div>
            <!-- Waiting text/icon could go here if implemented -->
            <div id="wait" style="visibility:hidden; text-align:center; padding: 20px;">
                Loading...
            </div>
        </div>

    </main>

    <?php include 'includes/footer.php'; ?>
