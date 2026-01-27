<?php
require_once('Connections/hms.php');

// Ensure database connection
if (!$conn) {
    echo 'ERROR: Could not connect to the database.';
    exit;
}

if (isset($_POST['queryString'])) {
    $queryString = trim($_POST['queryString']);
    
    if (strlen($queryString) > 0) {
        try {
            // Using PDO Prepared Statements for security
            $query = "SELECT patientid, Fname, Mname, Lname, MobilePhone 
                      FROM tbl_personalinfo 
                      WHERE patientid LIKE :search 
                         OR Fname LIKE :search 
                         OR Mname LIKE :search 
                         OR Lname LIKE :search 
                         OR MobilePhone LIKE :search 
                      LIMIT 5";
                      
            $stmt = $conn->prepare($query);
            $likeSearch = "%" . $queryString . "%";
            $stmt->bindParam(':search', $likeSearch, PDO::PARAM_STR);
            $stmt->execute();
            
            $results = $stmt->fetchAll(PDO::FETCH_OBJ);

            if ($results) {
                foreach ($results as $result) {
                    $fullName = htmlspecialchars($result->Lname . ' ' . $result->Fname . ' ' . $result->Mname, ENT_QUOTES);
                    // Output list item
                    echo '<li class="px-4 py-3 hover:bg-slate-50 dark:hover:bg-slate-700 cursor-pointer border-b border-slate-100 dark:border-slate-700 last:border-0 transition-colors list-none" onClick="fill(\''.$result->patientid.'\', \''.addslashes($fullName).'\');">
                            <div class="font-bold text-slate-800 dark:text-white text-sm">'. $result->Lname.' '. $result->Fname . ' '. $result->Mname .'</div>
                            <div class="text-xs text-slate-500 dark:text-slate-400">ID: '.$result->patientid.' | Mobile: '.$result->MobilePhone.'</div>
                        </li>';
                }
            }
        } catch (PDOException $e) {
            echo 'ERROR: There was a problem with the query.';
        }
    }
} else {
    echo 'There should be no direct access to this script!';
}
?>