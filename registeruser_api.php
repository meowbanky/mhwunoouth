<?php
header('Content-Type: application/json');
require_once('Connections/hms.php');

$response = ['status' => 'error', 'message' => '', 'data' => []];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        
        $action = $_POST['action'] ?? '';

        // 1. Fetch Users
        if ($action === 'fetch_users') {
            $page = isset($_POST['page']) ? (int)$_POST['page'] : 1;
            $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
            $search = isset($_POST['search']) ? trim($_POST['search']) : '';
            $offset = ($page - 1) * $limit;

            // Base query logic
            $whereSQL = "";
            $params = [];
            
            if (!empty($search)) {
                $whereSQL = "WHERE Username LIKE ? OR firstname LIKE ? OR lastname LIKE ?";
                $term = "%$search%";
                $params = [$term, $term, $term];
            }

            // Count Total
            $countQuery = "SELECT COUNT(*) FROM tblusers $whereSQL";
            $countStmt = $conn->prepare($countQuery);
            $countStmt->execute($params);
            $totalRecords = $countStmt->fetchColumn();
            $totalPages = ceil($totalRecords / $limit);

            // Fetch Data
            $query = "SELECT UserID, Username, firstname, middlename, lastname, dateofRegistration, 'Active' as status 
                      FROM tblusers 
                      $whereSQL 
                      ORDER BY UserID ASC 
                      LIMIT $limit OFFSET $offset";
            
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format data for frontend
            foreach ($users as &$user) {
                // Formatting name
                $user['fullname'] = trim($user['lastname'] . ' ' . $user['firstname'] . ' ' . $user['middlename']);
                // Initials for avatar
                $user['initials'] = strtoupper(substr($user['firstname'], 0, 1) . substr($user['lastname'], 0, 1));
            }

            $response['status'] = 'success';
            $response['data'] = [
                'list' => $users,
                'pagination' => [
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'total_records' => $totalRecords,
                    'limit' => $limit
                ]
            ];
        }

        // 2. Save User (Insert or Update)
        elseif ($action === 'save_user') {
            $userid = $_POST['userid'] ?? '';
            $username = trim($_POST['username'] ?? '');
            $firstname = trim($_POST['firstname'] ?? '');
            $middlename = trim($_POST['middlename'] ?? '');
            $lastname = trim($_POST['lastname'] ?? '');
            $password = $_POST['password'] ?? '';

            if (empty($username) || empty($firstname) || empty($lastname)) {
                throw new Exception("Username, First Name, and Last Name are required.");
            }

            // Check if username exists (for new users or changing username)
            $checkSql = "SELECT COUNT(*) FROM tblusers WHERE Username = ? AND UserID != ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$username, $userid]);
            if ($checkStmt->fetchColumn() > 0) {
                throw new Exception("Username '$username' is already taken.");
            }

            if (!empty($userid)) {
                // UPDATE
                $sql = "UPDATE tblusers SET Username = ?, firstname = ?, middlename = ?, lastname = ? WHERE UserID = ?";
                $params = [$username, $firstname, $middlename, $lastname, $userid];
                
                // Update password only if provided
                if (!empty($password)) {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $sql = "UPDATE tblusers SET Username = ?, firstname = ?, middlename = ?, lastname = ?, UPassword = ?, CPassword = ?, PlainPassword = ? WHERE UserID = ?";
                    $params = [$username, $firstname, $middlename, $lastname, $hashed, $hashed, $password, $userid];
                }

                $stmt = $conn->prepare($sql);
                $stmt->execute($params);
                $response['message'] = "User updated successfully.";

            } else {
                // INSERT
                if (empty($password)) {
                    throw new Exception("Password is required for new users.");
                }
                $hashed = password_hash($password, PASSWORD_DEFAULT);
                $sql = "INSERT INTO tblusers (Username, UPassword, CPassword, PlainPassword, firstname, middlename, lastname, dateofRegistration) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$username, $hashed, $hashed, $password, $firstname, $middlename, $lastname]);
                $response['message'] = "User created successfully.";
            }

            $response['status'] = 'success';
        }

        // 3. Delete User
        elseif ($action === 'delete_user') {
            $userid = $_POST['userid'] ?? '';
            if (empty($userid)) throw new Exception("Invalid User ID.");

            // Prevent deleting self (optional check, assuming session exists)
            session_start();
            if (isset($_SESSION['UserID']) && $_SESSION['UserID'] == $userid) {
                throw new Exception("You cannot delete your own account.");
            }

            $stmt = $conn->prepare("DELETE FROM tblusers WHERE UserID = ?");
            $stmt->execute([$userid]);
            $response['status'] = 'success';
            $response['message'] = "User deleted successfully.";
        }

        else {
            throw new Exception("Invalid Action");
        }

    } else {
        throw new Exception("Invalid Request Method");
    }

} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
?>
