<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

include './components/loggly-logger.php';

$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';

$conn = new mysqli($hostname, $username, $password, $database);

$conn->query("CREATE TABLE IF NOT EXISTS failed_logins (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45),
    username VARCHAR(255),
    attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

if ($conn->connect_error) {
    $logger->alert("Database connection failed");
    die("Connection failed: " . $conn->connect_error);
}

unset($error_message);

//if ($conn->connect_error) {
//    $errorMessage = "Connection failed: " . $conn->connect_error;    
//    die($errorMessage);
//}

// Check for brute force attacks 
// Prefer the forwarded client IP when behind a proxy/load-balancer
if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
$sql_check = "SELECT COUNT(*) as attempts FROM failed_logins WHERE ip_address = '$ip' AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
$result_check = $conn->query($sql_check);
$blocked = false;
if ($result_check) {
    $row = $result_check->fetch_assoc();
    $attempts = $row['attempts'];
    if ($attempts >= 5) {
        $blocked = true;
        $error_message = 'Too many failed login attempts from your IP. Please try again later.';
    }
}

// Check if the form is submitted
//$blocked = false; // Temporary disable for brute force check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    
    $username = $_POST['username'];
    $password = $_POST['password'];

//    $sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password' AND approved = 1";
//    $result = $conn->query($sql);

    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND approved = 1");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
       
        $userFromDB = $result->fetch_assoc();

        if (password_verify($password, $userFromDB['password'])) {

        //$_COOKIE['authenticated'] = $username;
        $_SESSION['authenticated'] = $username;
        // setcookie('authenticated', $username, time() + 3600, '/');     

        if ($userFromDB['default_role_id'] == 1){        
            $_SESSION['isSiteAdministrator'] = 1;
            // setcookie('isSiteAdministrator', true, time() + 3600, '/');                
        } else{
            unset($_SESSION['isSiteAdministrator']);
            // setcookie('isSiteAdministrator', '', -1, '/'); 
        }
        header("Location: index.php");
        exit();
    } else {
        $error_message = 'Invalid username or password.';  
        $logger->warning("Login failed for username: $username");
        $stmt_insert = $conn->prepare("INSERT INTO failed_logins (ip_address, username) VALUES (?, ?)");
        if ($stmt_insert) {
            $stmt_insert->bind_param("ss", $ip, $username);
            $stmt_insert->execute();
            $stmt_insert->close();
        } else {
            error_log('Failed to prepare failed_logins insert: ' . $conn->error);
        }
        // Check if now blocked
        $result_check2 = $conn->query($sql_check);
        if ($result_check2) {
            $row2 = $result_check2->fetch_assoc();
            if ($row2['attempts'] >= 5) {
                $logger->warning("Brute force attack detected from IP: $ip");
            }
        }
    }

    } else {
        $error_message = 'Invalid username or password.';
        // Record failed attempt for non-existent usernames as well
        $stmt_insert2 = $conn->prepare("INSERT INTO failed_logins (ip_address, username) VALUES (?, ?)");
        $dummyUser = $username ?? '';
        if ($stmt_insert2) {
            $stmt_insert2->bind_param("ss", $ip, $dummyUser);
            $stmt_insert2->execute();
            $stmt_insert2->close();
        } else {
            error_log('Failed to prepare failed_logins insert (user not found): ' . $conn->error);
        }
    }
}

$conn->close();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <title>Login Page</title>
</head>
<body>
    <div class="container mt-5">
        <div class="col-md-6 offset-md-3">
            <h2 class="text-center">Login</h2>
            <?php if (isset($error_message)) : ?>
                <div class="alert alert-danger" role="alert">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            <form action="login.php" method="post">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            <div class="mt-3 text-center">
                <a href="./users/request_account.php" class="btn btn-secondary btn-block">Request an Account</a>
            </div>
        </div>
    </div>
</body>
</html>
