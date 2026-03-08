<?php
// member_details.php
session_start();
if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit();
}
require_once('Connections/hms.php');

$patientid = isset($_GET['id']) ? mysqli_real_escape_string($hms, $_GET['id']) : '';

if (!$patientid) {
    header("Location: memberlist.php");
    exit();
}

// Fetch Comprehensive Data
$query_member = "SELECT * FROM tbl_personalinfo WHERE patientid = '$patientid'";
$res_member = mysqli_query($hms, $query_member);
$member = mysqli_fetch_assoc($res_member);

if (!$member) {
    header("Location: memberlist.php");
    exit();
}

$query_nok = "SELECT * FROM tbl_nok WHERE patientId = '$patientid'";
$res_nok = mysqli_query($hms, $query_nok);
$nok = mysqli_fetch_assoc($res_nok);

// Fetch Savings Summary (Contribution - Withdrawal)
$query_savings = "SELECT (SUM(IFNULL(Contribution,0)) - SUM(IFNULL(withdrawal,0))) as total FROM tlb_mastertransaction WHERE memberid = '$patientid'";
$res_savings = mysqli_query($hms, $query_savings);
$savings = mysqli_fetch_assoc($res_savings)['total'] ?? 0;

// Fetch Outstanding Loan (Principal + Interest - Repayments)
$query_loans = "SELECT (SUM(IFNULL(loanAmount,0)) + SUM(IFNULL(interest,0)) - SUM(IFNULL(loanRepayment,0)) - SUM(IFNULL(repayment_bank,0))) as balance FROM tlb_mastertransaction WHERE memberid = '$patientid'";
$res_loans = mysqli_query($hms, $query_loans);
$loan_data = mysqli_fetch_assoc($res_loans);
$loan_balance = $loan_data['balance'] ?? 0;

?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>
    
    <div class="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full overflow-y-auto">
        
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
                <a href="memberlist.php" class="inline-flex items-center gap-2 text-primary hover:underline text-sm font-medium mb-2">
                    <span class="material-icons-round text-sm">arrow_back</span>
                    Back to Member List
                </a>
                <div class="flex items-center gap-4">
                    <h2 class="text-3xl font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($member['Lname'] . ', ' . $member['Fname']); ?></h2>
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?php echo (strtolower($member['Status']) === 'active') ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-red-100 text-slate-700 dark:bg-red-800 dark:text-slate-400'; ?>">
                        <?php echo $member['Status'] ?: 'Active'; ?>
                    </span>
                </div>
                <p class="text-slate-500 dark:text-slate-400 mt-1">Member ID: <?php echo $patientid; ?> • Registered on <?php echo date('M d, Y', strtotime($member['DateOfReg'])); ?></p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="toggleMemberStatus('<?php echo $patientid; ?>')" class="flex items-center gap-2 px-4 py-2 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-700 dark:text-slate-300 font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    <span class="material-icons-round text-[20px]">swap_horiz</span>
                    Change Status
                </button>
                <a href="edit_member.php?id=<?php echo $patientid; ?>" class="flex items-center gap-2 bg-primary hover:bg-sky-600 text-white px-5 py-2.5 rounded-xl font-medium transition-all shadow-lg shadow-primary/20">
                    <span class="material-icons-round text-[20px]">edit</span>
                    Edit Member
                </a>
            </div>
        </header>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Sidebar: Key Stats & Photo -->
            <div class="lg:col-span-1 space-y-8">
                <!-- Profile Card -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-6 text-center">
                    <div class="w-32 h-32 rounded-3xl bg-slate-100 dark:bg-slate-800 mx-auto mb-4 flex items-center justify-center overflow-hidden border-4 border-slate-50 dark:border-slate-850">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($member['Fname'] . ' ' . $member['Lname']); ?>&size=128&background=random" class="w-full h-full object-cover">
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white"><?php echo htmlspecialchars($member['Fname'] . ' ' . $member['Lname']); ?></h3>
                    <p class="text-slate-500 text-sm mb-6"><?php echo htmlspecialchars($member['Dept']); ?></p>
                    
                    <div class="grid grid-cols-2 gap-4 border-t border-slate-100 dark:border-slate-800 pt-6">
                        <div>
                            <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Gender</p>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($member['gender']); ?></p>
                        </div>
                        <div>
                            <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider mb-1">Blood Group</p>
                            <p class="text-sm font-semibold text-slate-700 dark:text-slate-300"><?php echo htmlspecialchars($member['bloodGroup'] ?: '—'); ?></p>
                        </div>
                    </div>
                </div>

                <!-- Financial Summary -->
                <div class="bg-slate-900 rounded-2xl p-6 text-white shadow-xl">
                    <h4 class="text-slate-400 text-xs font-bold uppercase tracking-widest mb-6">Financial Summary</h4>
                    <div class="space-y-6">
                        <div>
                            <p class="text-blue-100/60 text-xs mb-1">Total Savings</p>
                            <p class="text-2xl font-bold">₦<?php echo number_format($savings, 2); ?></p>
                        </div>
                        <div class="pt-6 border-t border-white/10">
                            <p class="text-blue-100/60 text-xs mb-1">Outstanding Loan</p>
                            <p class="text-2xl font-bold text-orange-400">₦<?php echo number_format($loan_balance, 2); ?></p>
                        </div>
                    </div>
                    <a href="status.php?search=<?php echo $patientid; ?>" class="mt-8 w-full inline-flex items-center justify-center gap-2 px-4 py-3 bg-white/10 hover:bg-white/20 rounded-xl text-sm font-semibold transition-all">
                        <span class="material-icons-round text-sm">analytics</span>
                        View Full Statement
                    </a>
                </div>
            </div>

            <!-- Main Info Area -->
            <div class="lg:col-span-2 space-y-8">
                <!-- Full Personal Details -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="px-8 py-5 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center">
                        <h3 class="font-bold text-slate-800 dark:text-white">Detailed Information</h3>
                    </div>
                    <div class="p-8">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                            <div class="space-y-1">
                                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Full Legal Name</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($member['sfxname'] . ' ' . $member['Fname'] . ' ' . $member['Mname'] . ' ' . $member['Lname']); ?></p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Date of Birth</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo $member['DOB'] ? date('M d, Y', strtotime($member['DOB'])) : '—'; ?></p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Email Address</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($member['EmailAddress'] ?: '—'); ?></p>
                            </div>
                            <div class="space-y-1">
                                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Phone Number</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($member['MobilePhone'] ?: '—'); ?></p>
                            </div>
                            <div class="space-y-1 md:col-span-2">
                                <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Residential Address</p>
                                <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($member['Address'] . ', ' . $member['City'] . ', ' . $member['State']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Next of Kin -->
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden">
                    <div class="px-8 py-5 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="font-bold text-slate-800 dark:text-white flex items-center gap-2">
                            <span class="material-icons-round text-primary text-sm">family_restroom</span>
                            Next of Kin Information
                        </h3>
                    </div>
                    <div class="p-8">
                        <?php if ($nok): ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-8">
                                <div class="space-y-1">
                                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Full Name</p>
                                    <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($nok['NOkName']); ?></p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Relationship</p>
                                    <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($nok['NOKRelationship']); ?></p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Phone Number</p>
                                    <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($nok['NOKPhone']); ?></p>
                                </div>
                                <div class="space-y-1">
                                    <p class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Address</p>
                                    <p class="text-slate-700 dark:text-slate-200 font-medium"><?php echo htmlspecialchars($nok['NOKAddress']); ?></p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-6">
                                <p class="text-slate-500 italic">No Next of Kin information recorded for this member.</p>
                                <a href="edit_member.php?id=<?php echo $patientid; ?>" class="text-primary hover:underline text-sm font-bold mt-2 inline-block">Add NOK Information</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
window.toggleMemberStatus = function(patientid) {
    Swal.fire({
        title: 'Change Status?',
        text: 'Are you sure you want to toggle the status for this member?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#0ea5e9',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, toggle it!'
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Updating...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'member_api.php',
                type: 'POST',
                data: { action: 'toggle_status', patientid: patientid },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated',
                            text: res.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while toggling status.'
                    });
                }
            });
        }
    });
};
</script>

<?php include 'includes/footer.php'; ?>
