<?php

define('SKIP_AUTH_CHECK', true);
include './components/authenticate.php';
include './components/logger.php';

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

// Check for brute force attacks 
// Prefer the forwarded client IP when behind a proxy/load-balancer

if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
    $ip = trim($ips[0]);
} else {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}
$blocked = false;

$stmt_check = $conn->prepare("SELECT COUNT(*) as attempts FROM failed_logins WHERE ip_address = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
if ($stmt_check) {
    $stmt_check->bind_param('s', $ip);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    if ($result_check && ($row = $result_check->fetch_assoc())) {
        $attempts = $row['attempts'];
        if ($attempts >= 5) {
            $blocked = true;
            $error_message = 'Too many failed login attempts from your IP. Please try again later.';
        }
    }
    $stmt_check->close();
}

// Check if the form is submitted
//$blocked = false; // Temporary disable for brute force check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$blocked) {
    requireCsrfToken();
    
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

        $_SESSION['authenticated'] = $username;
 
        if ($userFromDB['default_role_id'] == 1){        
            $_SESSION['isSiteAdministrator'] = 1;             
        } else{
            unset($_SESSION['isSiteAdministrator']);
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
        $stmt_check2 = $conn->prepare(
            "SELECT COUNT(*) as attempts 
            FROM failed_logins 
            WHERE ip_address = ? 
            AND attempt_time > DATE_SUB(NOW(), INTERVAL 1 MINUTE)"
        );

        $stmt_check2->bind_param("s", $ip);
        $stmt_check2->execute();
        $result_check2 = $stmt_check2->get_result();

        if ($result_check2 && ($row2 = $result_check2->fetch_assoc())) {
            if ($row2['attempts'] >= 5) {
                $logger->warning("Brute force attack detected from IP: $ip");
            }
        }

        $stmt_check2->close();

    }

    } else {
        $error_message = 'Invalid username or password.';
        $logger->warning("Login failed for username: $username");
        // Record failed attempt for non-existent usernames as well
        $stmt_insert2 = $conn->prepare("INSERT INTO failed_logins (ip_address, username) VALUES (?, ?)");
        $dummyUser = $username ?? '';
        if ($stmt_insert2) {
            $stmt_insert2->bind_param("ss", $ip, $dummyUser);
            $stmt_insert2->execute();
            $stmt_insert2->close();
        } else {
            error_log('Failed to prepare failed_logins insert (user not found): ' . $conn->error);
            $logger->error("Failed to prepare failed_logins insert for non-existent user: $username");
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
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
    <title>Login Page</title>
</head>
<body class="login-page">
    <canvas id="matrix-canvas"></canvas>
    <div id="intro-sequence">
        <span id="intro-text"></span>
        <button id="skip-intro" class="skip-btn">Skip Intro</button>
    </div>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">Educational Password Manager<br>Login</h2>
                <?php if (isset($error_message)) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo $error_message; ?>
                    </div>
                <?php endif; ?>
            <form action="login.php" method="post">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8" maxlength="128">
                </div>
                <button type="submit" class="btn btn-primary btn-block">Login</button>
            </form>
            <!--<div class="mt-3 text-center"> -->
                <a href="./users/request_account.php" class="btn btn-secondary btn-block">Request an Account</a>
            <!--</div> -->
        </div>
    </div>
    <script src="/js/matrix.js"></script>
</body>
</html>
