<?php
require_once('Connections/hms.php');

if (!isset($_SESSION)) {
    session_start();
}

if (isset($_POST['uname']) && isset($_POST['passwd'])) {
    $loginUsername = trim($_POST['uname']);
    $password = trim($_POST['passwd']);

    try {
        // Use the PDO connection $conn from hms.php
        $stmt = $conn->prepare("SELECT UserID, firstname, lastname, UPassword FROM tblusers WHERE Username = :username AND status = 'Active' AND access = 1");
        $stmt->bindParam(':username', $loginUsername, PDO::PARAM_STR);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Verify password
            // Note: If passwords are not currently hashed in the DB (checked existing code used password_verify but had a plain text fallback logic implied or maybe not?), 
            // The previous code used password_verify. I will stick to that.
            if (password_verify($password, $row['UPassword'])) {
                session_regenerate_id(true); // Secure session regeneration

                $_SESSION['FirstName'] = $row['lastname'] . ", " . $row['firstname'];
                $_SESSION['UserID'] = $row['UserID'];

                header("Location: dashboard.php");
                exit;
            } else {
                // Password incorrect
                header("Location: index.php?error=invalid_credentials");
                exit;
            }
        } else {
            // User not found or not active
            header("Location: index.php?error=invalid_credentials");
            exit;
        }
    } catch (PDOException $e) {
        // Log error and redirect with generic error
        error_log("Login Error: " . $e->getMessage());
        header("Location: index.php?error=system_error");
        exit;
    }
} else {
    // Missing fields
    header("Location: index.php");
    exit;
}
?>