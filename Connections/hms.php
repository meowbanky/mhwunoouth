<?php
# FileName="Connection_php_mysql.htm"
# Type="MYSQL"
# HTTP="true"

// Simple .env parser
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        
        // Remove quotes if present
        $value = trim($value);
        if (substr($value, 0, 1) === '"' && substr($value, -1) === '"') {
            $value = substr($value, 1, -1);
        } elseif (substr($value, 0, 1) === "'" && substr($value, -1) === "'") {
            $value = substr($value, 1, -1);
        }
        
        $_ENV[trim($name)] = $value;
    }
} else {
    // Fallback or error - keeping variables empty or setting defaults could be an option
    // But for now let's assume .env exists if we just created it. 
    // If you want to keep hardcoded fallbacks during transition, you could.
    // However, the request is to "move" them.
}

$hostname_hms = $_ENV['DB_HOST'] ?? "localhost";
$database_hms = $_ENV['DB_DATABASE'] ?? "";
$username_hms = $_ENV['DB_USERNAME'] ?? "";
$password_hms = $_ENV['DB_PASSWORD'] ?? "";

try {
    // PDO Connection (Modern)
    $conn = new PDO("mysql:host=$hostname_hms;dbname=$database_hms", $username_hms, $password_hms, array(PDO::ATTR_PERSISTENT => true));
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->exec("SET NAMES 'utf8'");

    // MySQLi Connection (Legacy Support)
    $hms = mysqli_connect($hostname_hms, $username_hms, $password_hms, $database_hms);
    if (!$hms) {
        throw new Exception("MySQLi Connection failed: " . mysqli_connect_error());
    }
    mysqli_set_charset($hms, "utf8");

} catch (PDOException $e) {
    error_log("Failed Connection: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
} catch (Exception $e) {
    error_log("Failed Connection: " . $e->getMessage());
    die("Database connection failed. Please contact administrator.");
}
?>