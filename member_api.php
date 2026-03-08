<?php
// member_api.php
session_start();
if (!isset($_SESSION['UserID'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access.']);
    exit;
}

require_once('Connections/hms.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];

    if ($action === 'toggle_status') {
        $patientid = mysqli_real_escape_string($hms, $_POST['patientid']);
        
        // Fetch current status
        $query = "SELECT Status FROM tbl_personalinfo WHERE patientid = '$patientid'";
        $result = mysqli_query($hms, $query);
        $row = mysqli_fetch_assoc($result);
        
        if ($row) {
            $currentStatus = $row['Status'];
            $newStatus = ($currentStatus === 'Active') ? 'In-Active' : 'Active';
            
            $updateQuery = "UPDATE tbl_personalinfo SET Status = '$newStatus' WHERE patientid = '$patientid'";
            if (mysqli_query($hms, $updateQuery)) {
                echo json_encode([
                    'status' => 'success', 
                    'message' => "Member status updated to $newStatus",
                    'newStatus' => $newStatus
                ]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Failed to update status.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Member not found.']);
        }
        exit;
    }
}

echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
exit;
?>
