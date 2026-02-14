<?php
session_start();
require_once('Connections/hms.php');

if (!isset($_SESSION['UserID'])) {
    header("Location: index.php");
    exit;
}

// Handle AJAX Registration
if (isset($_REQUEST['check_mrn'])) {
    header('Content-Type: application/json');
    $mrn = htmlspecialchars(stripslashes(trim($_REQUEST['check_mrn'])));
    $stmt = $conn->prepare("SELECT count(*) FROM tbl_personalinfo WHERE patientid = :id");
    $stmt->execute([':id' => $mrn]);
    echo json_encode(['exists' => $stmt->fetchColumn() > 0]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    header('Content-Type: application/json');

    // Helper to sanitize
    // Helper to sanitize
    function clean_input($data) {
        if ($data === null) return '';
        return htmlspecialchars(stripslashes(trim($data)));
    }

    // Capture Data (using null coalescing to prevent undefined key warnings)
    $staff_no   = clean_input($_POST['new_mrn'] ?? '');
    $title      = clean_input($_POST['sfxname'] ?? '');
    $fname      = clean_input($_POST['Fname'] ?? '');
    $mname      = clean_input($_POST['Mname'] ?? '');
    $lname      = clean_input($_POST['Lname'] ?? '');
    $gender     = clean_input($_POST['gender'] ?? '');
    $dob        = clean_input($_POST['DOB'] ?? '');
    $address    = clean_input($_POST['Address'] ?? '');
    $city       = clean_input($_POST['City'] ?? '');
    $state      = clean_input($_POST['State'] ?? '');
    $mobile     = clean_input($_POST['MobilePhone'] ?? '');
    $email      = clean_input($_POST['EmailAddress'] ?? '');
    $dept       = clean_input($_POST['dept'] ?? '');
    
    // NOK
    $nok_name   = clean_input($_POST['NOkName'] ?? '');
    $nok_rel    = clean_input($_POST['NOKRelationship'] ?? '');
    $nok_phone  = clean_input($_POST['NOKPhone'] ?? '');
    $nok_addr   = clean_input($_POST['NOKAddress'] ?? '');

    // Validation
    if (empty($staff_no) || empty($fname) || empty($lname)) {
        echo json_encode(['status' => 'error', 'message' => 'Staff Number, First Name, and Last Name are required.']);
        exit;
    }

    // Check for duplicates in tbl_personalinfo
    $stmtCheck1 = $conn->prepare("SELECT count(*) FROM tbl_personalinfo WHERE patientid = :id");
    $stmtCheck1->execute([':id' => $staff_no]);
    if ($stmtCheck1->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Error: Staff Number (' . $staff_no . ') already exists in Personal Info.']);
        exit;
    }

    // Check for duplicates in tblusers
    $stmtCheck2 = $conn->prepare("SELECT count(*) FROM tblusers WHERE UserID = :id");
    $stmtCheck2->execute([':id' => $staff_no]);
    if ($stmtCheck2->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Error: Staff Number (' . $staff_no . ') already exists in User Accounts. Please contact admin to clear orphaned records.']);
        exit;
    }

    try {
        $conn->beginTransaction();

        // 1. Insert into tbl_personalinfo
        $stmtFunc = $conn->prepare("INSERT INTO tbl_personalinfo 
            (patientid, sfxname, Fname, Mname, Lname, gender, DOB, Address, City, `State`, MobilePhone, EmailAddress, DateOfReg, status, Dept, ordered_id) 
            VALUES (:id, :title, :fname, :mname, :lname, :gender, :dob, :addr, :city, :state, :mobile, :email, NOW(), 'Active', :dept, :orderid)");
        
        $stmtFunc->execute([
            ':id' => $staff_no,
            ':title' => $title,
            ':fname' => $fname,
            ':mname' => $mname,
            ':lname' => $lname,
            ':gender' => $gender,
            ':dob' => $dob, // Ensure this matches input name="DOB"
            ':addr' => $address,
            ':city' => $city,
            ':state' => $state,
            ':mobile' => $mobile,
            ':email' => $email,
            ':dept' => $dept,
            ':orderid' => $staff_no
        ]);
        
        // Log if DOB is empty for debugging (can be removed later)
        if (empty($dob)) {
            // error_log("Warning: DOB is empty for " . $staff_no);
        }

        // 2. Insert into tblusers (Generate Password)
        $plainPassword = substr(str_shuffle("abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        $stmtUser = $conn->prepare("INSERT INTO tblusers 
            (UserID, firstname, middlename, lastname, Username, UPassword, CPassword, PlainPassword, dateofRegistration) 
            VALUES (:id, :fname, :mname, :lname, :id, :pass, :pass, :plain, NOW())");
        
        $stmtUser->execute([
            ':id' => $staff_no,
            ':fname' => $fname,
            ':mname' => $mname,
            ':lname' => $lname,
            ':pass' => $hashedPassword,
            ':plain' => $plainPassword
        ]);

        // 3. Insert into tbl_nok
        $stmtNok = $conn->prepare("INSERT INTO tbl_nok 
            (patientId, NOkName, NOKRelationship, NOKPhone, NOKAddress) 
            VALUES (:id, :name, :rel, :phone, :addr)");
        
        $stmtNok->execute([
            ':id' => $staff_no,
            ':name' => $nok_name,
            ':rel' => $nok_rel,
            ':phone' => $nok_phone,
            ':addr' => $nok_addr
        ]);

        // 4. Insert into tbl_contributions
        // Fetch contribution setting
        $stmtSet = $conn->query("SELECT contribution FROM tbl_settings LIMIT 1");
        $rowSet = $stmtSet->fetch(PDO::FETCH_ASSOC);
        $contribution = $rowSet['contribution'] ?? 0;

        $stmtCon = $conn->prepare("INSERT INTO tbl_contributions (membersid, contribution) VALUES (:id, :contrib)");
        $stmtCon->execute([
            ':id' => $staff_no,
            ':contrib' => $contribution
        ]);

        $conn->commit();
        echo json_encode(['status' => 'success', 'message' => 'Member registered successfully! Password: ' . $plainPassword]);

    } catch (PDOException $e) {
        $conn->rollBack();
        // Check for duplicate entry
        if ($e->getCode() == 23000) {
            echo json_encode(['status' => 'error', 'message' => 'Database Integrity Error: ' . $e->getMessage()]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }
    exit;
}
?>
<?php include 'includes/header.php'; ?>
<?php include 'includes/sidebar.php'; ?>

<main class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50 dark:bg-slate-950">
    <?php include 'includes/topbar.php'; ?>
    
    <div class="flex-1 p-6 lg:p-10 max-w-7xl mx-auto w-full overflow-y-auto">
        
        <header class="mb-8 text-center max-w-2xl mx-auto">
            <h2 class="text-3xl font-bold text-slate-900 dark:text-white mb-2">Register New Member</h2>
            <p class="text-slate-500 dark:text-slate-400">Complete the form below to add a new member to the system.</p>
        </header>

        <!-- Progress Steps -->
        <div class="mb-12 max-w-2xl mx-auto">
            <div class="flex items-center justify-between relative">
                <div class="absolute top-1/2 left-0 w-full h-0.5 bg-slate-200 dark:bg-slate-800 -translate-y-1/2 -z-10"></div>
                
                <div class="step-indicator flex flex-col items-center gap-2 bg-slate-50 dark:bg-slate-950 px-4 transition-all duration-300" id="progress-step-1">
                    <div class="w-10 h-10 rounded-full bg-primary text-white flex items-center justify-center font-bold ring-4 ring-slate-50 dark:ring-slate-950 transition-all duration-300 shadow-lg shadow-primary/30">1</div>
                    <span class="text-xs font-bold text-primary uppercase tracking-wider transition-colors duration-300">Personal</span>
                </div>
                
                <div class="step-indicator flex flex-col items-center gap-2 bg-slate-50 dark:bg-slate-950 px-4 transition-all duration-300 opacity-60" id="progress-step-2">
                    <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-800 border-2 border-slate-300 dark:border-slate-700 text-slate-500 flex items-center justify-center font-bold ring-4 ring-slate-50 dark:ring-slate-950 transition-all duration-300">2</div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider transition-colors duration-300">Contact</span>
                </div>
                
                <div class="step-indicator flex flex-col items-center gap-2 bg-slate-50 dark:bg-slate-950 px-4 transition-all duration-300 opacity-60" id="progress-step-3">
                    <div class="w-10 h-10 rounded-full bg-white dark:bg-slate-800 border-2 border-slate-300 dark:border-slate-700 text-slate-500 flex items-center justify-center font-bold ring-4 ring-slate-50 dark:ring-slate-950 transition-all duration-300">3</div>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider transition-colors duration-300">Next of Kin</span>
                </div>
            </div>
        </div>

        <form id="regForm" class="max-w-4xl mx-auto" novalidate>
            
            <!-- Step 1: Personal Info -->
            <div id="step1" class="form-step">
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                    <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Personal Information</h3>
                        <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">Core identification details.</p>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Staff Number <span class="text-red-500">*</span></label>
                            <input type="text" name="new_mrn" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" placeholder="e.g. SN-8829" required>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Title <span class="text-red-500">*</span></label>
                            <select name="sfxname" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                                <option value="Mr.">Mr.</option>
                                <option value="Mrs.">Mrs.</option>
                                <option value="Ms.">Ms.</option>
                                <option value="Dr.">Dr.</option>
                                <option value="Prof.">Prof.</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">First Name <span class="text-red-500">*</span></label>
                            <input type="text" name="Fname" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Last Name <span class="text-red-500">*</span></label>
                            <input type="text" name="Lname" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Middle Name</label>
                            <input type="text" name="Mname" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                        </div>
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Gender <span class="text-red-500">*</span></label>
                            <select name="gender" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Date of Birth <span class="text-red-500">*</span></label>
                            <input type="date" name="DOB" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Department <span class="text-red-500">*</span></label>
                            <input type="text" name="dept" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                    </div>
                    <div class="flex justify-end pt-8">
                        <button type="button" class="btn-next bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-semibold shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2">
                            Next Step
                            <span class="material-icons-round">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 2: Contact Info -->
            <div id="step2" class="form-step hidden">
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                     <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Contact Details</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2 space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Address <span class="text-red-500">*</span></label>
                            <input type="text" name="Address" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">City <span class="text-red-500">*</span></label>
                            <input type="text" name="City" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">State <span class="text-red-500">*</span></label>
                            <input type="text" name="State" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-1.5">
                             <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Mobile Phone <span class="text-red-500">*</span></label>
                             <input type="text" name="MobilePhone" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                         <div class="space-y-1.5">
                             <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Email Address</label>
                             <input type="email" name="EmailAddress" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary">
                        </div>
                    </div>
                    <div class="flex justify-between pt-8">
                        <button type="button" class="btn-prev px-6 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-2">
                            <span class="material-icons-round">arrow_back</span>
                            Back
                        </button>
                        <button type="button" class="btn-next bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-semibold shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2">
                            Next Step
                            <span class="material-icons-round">arrow_forward</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Step 3: Next of Kin -->
            <div id="step3" class="form-step hidden">
                <div class="bg-white dark:bg-slate-900 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-800 p-8">
                     <div class="border-b border-slate-100 dark:border-slate-800 pb-4 mb-8">
                        <h3 class="text-xl font-bold text-slate-900 dark:text-white">Next of Kin</h3>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="NOkName" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                        <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Relationship <span class="text-red-500">*</span></label>
                            <input type="text" name="NOKRelationship" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Phone Number <span class="text-red-500">*</span></label>
                            <input type="text" name="NOKPhone" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                         <div class="space-y-1.5">
                            <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300">Address <span class="text-red-500">*</span></label>
                            <input type="text" name="NOKAddress" class="block w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 focus:ring-primary focus:border-primary" required>
                        </div>
                    </div>
                    <div class="flex justify-between pt-8">
                        <button type="button" class="btn-prev px-6 py-2.5 rounded-lg text-sm font-semibold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors flex items-center gap-2">
                            <span class="material-icons-round">arrow_back</span>
                            Back
                        </button>
                        <button type="submit" id="btnSubmit" class="bg-primary hover:bg-blue-700 text-white px-8 py-2.5 rounded-lg font-bold shadow-lg shadow-blue-500/30 transition-all flex items-center gap-2">
                            <span class="material-icons-round">save</span>
                            Register Member
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</main>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.8.0/jquery.min.js"></script>
<script>
$(document).ready(function() {
    var currentStep = 1;
    
    // MRN Check
    $('input[name="new_mrn"]').blur(function() {
        var mrn = $(this).val();
        if(mrn) {
            $.getJSON("registration.php?check_mrn="+mrn, function(data) {
                if(data.exists) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Duplicate Staff Number',
                        text: 'This Staff Number ('+mrn+') already exists.',
                        timer: 3000
                    });
                    $('input[name="new_mrn"]').addClass('border-red-500').val('').focus();
                } else {
                    $('input[name="new_mrn"]').removeClass('border-red-500').addClass('border-green-500');
                }
            });
        }
    });

    // Remove red border on focus
    $('input, select').focus(function() {
        $(this).removeClass('border-red-500 ring-1 ring-red-500');
    });

    // Function to update progress bar UI
    function updateProgressBar(step) {
        // Reset all
        $('.step-indicator').addClass('opacity-60').find('div').removeClass('bg-primary text-white shadow-lg').addClass('bg-white dark:bg-slate-800 border-2 border-slate-300 text-slate-500');
        $('.step-indicator span').removeClass('text-primary font-bold').addClass('text-slate-500 font-semibold');
        
        // Activate current and previous
        for(var i = 1; i <= step; i++) {
             var $el = $('#progress-step-' + i);
             $el.removeClass('opacity-60');
             
             // If it's the active step
             if(i === step) {
                 $el.find('div').removeClass('bg-white dark:bg-slate-800 border-2 border-slate-300 text-slate-500').addClass('bg-primary text-white shadow-lg shadow-primary/30');
                 $el.find('span').removeClass('text-slate-500 font-semibold').addClass('text-primary font-bold');
             } else {
                 // Completed steps
                 $el.find('div').removeClass('bg-white border-2 border-slate-300 text-slate-500').addClass('bg-emerald-500 text-white border-none').html('<span class="material-icons-round text-sm">check</span>');
                 $el.find('span').addClass('text-emerald-600');
             }
        }
    }

    // Consolidated validation function
    function validateStep(stepContainer) {
        var isValid = true;
        
        stepContainer.find('input, select').each(function() {
             // Use native DOM checking for reliability with old jQuery + HTML5
             var isRequired = this.getAttribute('required');
             
             // Check if attribute exists (it will be '' or 'required' if present, null if not)
             if(isRequired !== null) {
                // Check value (handle whitespace)
                var val = $(this).val();
                if(!val || $.trim(val) === '') {
                    isValid = false;
                     $(this).removeClass('border-green-500').addClass('border-red-500 ring-1 ring-red-500').focus();
                     
                     // Show alert only once
                     if($('.swal2-container').length === 0) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Required Field',
                            text: 'Please fill in all required fields.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                     }
                    return false; // Break loop
                } else {
                    $(this).removeClass('border-red-500 ring-1 ring-red-500').addClass('border-green-500');
                }
            }
        });
        return isValid;
    }

    // Next Button Click
    $('.btn-next').click(function() {
        var $currentStepDiv = $('#step' + currentStep);
        
        if(validateStep($currentStepDiv)) {
            $currentStepDiv.addClass('hidden');
            currentStep++;
            $('#step' + currentStep).removeClass('hidden').addClass('animate-fade-in-right');
            updateProgressBar(currentStep);
            window.scrollTo(0, 0);
        }
    });

    // Previous Button Click
    $('.btn-prev').click(function() {
        $('#step' + currentStep).addClass('hidden');
        currentStep--;
        $('#step' + currentStep).removeClass('hidden').addClass('animate-fade-in-left');
        updateProgressBar(currentStep);
        window.scrollTo(0, 0);
    });

    // Final Submission
    $('#regForm').submit(function(e) {
        e.preventDefault();
        
        // Block early submission if not on final step
        if(currentStep < 3) {
            return false;
        }
        
        // Validate Validation for Step 3 (current visible step)
        var $currentStepDiv = $('#step' + currentStep);
        if(!validateStep($currentStepDiv)) {
            return; // Stop submission if invalid
        }

        var formData = $(this).serialize();
        formData += '&action=register';

        Swal.fire({
            title: 'Processing...',
            text: 'Registering new member',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            type: "POST",
            url: "registration.php",
            data: formData,
            dataType: "json",
            success: function(response) {
                if(response.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Registration Successful',
                        html: response.message,
                        confirmButtonText: 'Great!'
                    }).then(() => {
                        // Reset form and go to step 1
                        $('#regForm')[0].reset();
                        currentStep = 1;
                        $('.form-step').addClass('hidden');
                        $('#step1').removeClass('hidden');
                        updateProgressBar(1);
                        $('.step-indicator div').html(function(i){ return i+1; });
                        $('input').removeClass('border-green-500');
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Registration Failed',
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
