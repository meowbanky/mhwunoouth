<?php
// edit_member.php
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

// Handle Update Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_member') {
    header('Content-Type: application/json');
    
    function clean_input($hms, $data) {
        if ($data === null) return '';
        return mysqli_real_escape_string($hms, htmlspecialchars(stripslashes(trim($data))));
    }

    $fname      = clean_input($hms, $_POST['Fname'] ?? '');
    $mname      = clean_input($hms, $_POST['Mname'] ?? '');
    $lname      = clean_input($hms, $_POST['Lname'] ?? '');
    $gender     = clean_input($hms, $_POST['gender'] ?? '');
    $dob        = clean_input($hms, $_POST['DOB'] ?? '');
    $address    = clean_input($hms, $_POST['Address'] ?? '');
    $city       = clean_input($hms, $_POST['City'] ?? '');
    $state      = clean_input($hms, $_POST['State'] ?? '');
    $mobile     = clean_input($hms, $_POST['MobilePhone'] ?? '');
    $email      = clean_input($hms, $_POST['EmailAddress'] ?? '');
    $dept       = clean_input($hms, $_POST['dept'] ?? '');
    
    // NOK
    $nok_name   = clean_input($hms, $_POST['NOkName'] ?? '');
    $nok_rel    = clean_input($hms, $_POST['NOKRelationship'] ?? '');
    $nok_phone  = clean_input($hms, $_POST['NOKPhone'] ?? '');
    $nok_addr   = clean_input($hms, $_POST['NOKAddress'] ?? '');

    // Update tbl_personalinfo
    $query_update_personal = "UPDATE tbl_personalinfo SET 
        Fname = '$fname', Mname = '$mname', Lname = '$lname', 
        gender = '$gender', DOB = '$dob', Address = '$address', 
        City = '$city', State = '$state', MobilePhone = '$mobile', 
        EmailAddress = '$email', Dept = '$dept' 
        WHERE patientid = '$patientid'";
    
    $update_personal = mysqli_query($hms, $query_update_personal);

    // Update tbl_nok
    $query_check_nok = "SELECT count(*) as count FROM tbl_nok WHERE patientId = '$patientid'";
    $res_check = mysqli_query($hms, $query_check_nok);
    $row_check = mysqli_fetch_assoc($res_check);

    if ($row_check['count'] > 0) {
        $query_update_nok = "UPDATE tbl_nok SET 
            NOkName = '$nok_name', NOKRelationship = '$nok_rel', 
            NOKPhone = '$nok_phone', NOKAddress = '$nok_addr' 
            WHERE patientId = '$patientid'";
    } else {
        $query_update_nok = "INSERT INTO tbl_nok (patientId, NOkName, NOKRelationship, NOKPhone, NOKAddress) 
            VALUES ('$patientid', '$nok_name', '$nok_rel', '$nok_phone', '$nok_addr')";
    }
    
    $update_nok = mysqli_query($hms, $query_update_nok);

    if ($update_personal && $update_nok) {
        echo json_encode(['status' => 'success', 'message' => 'Member details updated successfully!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to update member: ' . mysqli_error($hms)]);
    }
    exit;
}

// Fetch Existing Data
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
                <h2 class="text-3xl font-bold text-slate-900 dark:text-white">Edit Member</h2>
                <p class="text-slate-500 dark:text-slate-400">Update information for <?php echo htmlspecialchars($member['Lname'] . ' ' . $member['Fname']); ?> (ID: <?php echo $patientid; ?>)</p>
            </div>
        </header>

        <form id="editForm" class="max-w-4xl mx-auto space-y-8">
            <input type="hidden" name="action" value="update_member">
            
            <!-- Personal Info Section -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Personal Information</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">First Name <span class="text-red-500">*</span></label>
                        <input type="text" name="Fname" value="<?php echo htmlspecialchars($member['Fname']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Last Name <span class="text-red-500">*</span></label>
                        <input type="text" name="Lname" value="<?php echo htmlspecialchars($member['Lname']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Middle Name</label>
                        <input type="text" name="Mname" value="<?php echo htmlspecialchars($member['Mname']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                    </div>
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Gender <span class="text-red-500">*</span></label>
                        <select name="gender" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                            <option value="Male" <?php echo ($member['gender'] == 'Male' ? 'selected' : ''); ?>>Male</option>
                            <option value="Female" <?php echo ($member['gender'] == 'Female' ? 'selected' : ''); ?>>Female</option>
                        </select>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Date of Birth <span class="text-red-500">*</span></label>
                        <input type="date" name="DOB" value="<?php echo htmlspecialchars($member['DOB']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Department <span class="text-red-500">*</span></label>
                        <input type="text" name="dept" value="<?php echo htmlspecialchars($member['Dept']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                </div>
            </div>

            <!-- Contact Section -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Contact Details</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="md:col-span-2 space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Address <span class="text-red-500">*</span></label>
                        <input type="text" name="Address" value="<?php echo htmlspecialchars($member['Address']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">City <span class="text-red-500">*</span></label>
                        <input type="text" name="City" value="<?php echo htmlspecialchars($member['City']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">State <span class="text-red-500">*</span></label>
                        <input type="text" name="State" value="<?php echo htmlspecialchars($member['State']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div class="space-y-1.5">
                         <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Mobile Phone <span class="text-red-500">*</span></label>
                         <input type="text" name="MobilePhone" value="<?php echo htmlspecialchars($member['MobilePhone']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                     <div class="space-y-1.5">
                         <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Email Address</label>
                         <input type="email" name="EmailAddress" value="<?php echo htmlspecialchars($member['EmailAddress']); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                    </div>
                </div>
            </div>

            <!-- Next of Kin Section -->
            <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                    <h3 class="text-xl font-bold text-slate-900 dark:text-white">Next of Kin</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Full Name <span class="text-red-500">*</span></label>
                        <input type="text" name="NOkName" value="<?php echo htmlspecialchars($nok['NOkName'] ?? ''); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                    <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Relationship <span class="text-red-500">*</span></label>
                        <input type="text" name="NOKRelationship" value="<?php echo htmlspecialchars($nok['NOKRelationship'] ?? ''); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Phone Number <span class="text-red-500">*</span></label>
                        <input type="text" name="NOKPhone" value="<?php echo htmlspecialchars($nok['NOKPhone'] ?? ''); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                     <div class="space-y-1.5">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Address <span class="text-red-500">*</span></label>
                        <input type="text" name="NOKAddress" value="<?php echo htmlspecialchars($nok['NOKAddress'] ?? ''); ?>" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                    </div>
                </div>
            </div>

            <div class="flex justify-end gap-4">
                <a href="memberlist.php" class="px-8 py-3 rounded-xl border border-slate-200 dark:border-slate-800 text-slate-600 dark:text-slate-400 font-semibold hover:bg-slate-50 dark:hover:bg-slate-800 transition-all">Cancel</a>
                <button type="submit" class="bg-primary hover:bg-sky-600 text-white px-10 py-3 rounded-xl font-bold shadow-lg shadow-primary/20 transition-all flex items-center gap-2">
                    <span class="material-icons-round">save</span>
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    $('#editForm').submit(function(e) {
        e.preventDefault();
        
        var formData = $(this).serialize();
        formData += '&id=<?php echo $patientid; ?>';

        Swal.fire({
            title: 'Updating...',
            text: 'Saving member details',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            type: "POST",
            url: "edit_member.php?id=<?php echo $patientid; ?>",
            data: formData,
            dataType: "json",
            success: function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: response.message
                    }).then(() => {
                        window.location.href = 'memberlist.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: response.message
                    });
                }
            },
            error: function() {
                 Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An unexpected error occurred.'
                });
            }
        });
    });
});
</script>

<?php include 'includes/footer.php'; ?>
