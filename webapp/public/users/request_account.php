<?php

define('SKIP_AUTH_CHECK', true);
include '../components/authenticate.php';
include '../components/logger.php';

$servername = "backend-mysql-database";
$username = "user";
$password = "supersecretpw";
$dbname = "password_manager";

$conn = new mysqli($servername, $username, $password, $dbname);

unset($error_message);

if ($conn->connect_error) {
    // die("Connection failed: " . $conn->connect_error);
    $logger->error('DB connection failed: ' . $conn->connect_error);
    die('Connection failed. Please contact support.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrfToken();
    
    //$username = $_POST['username'];
    //$password = $_POST['password'];
    //$firstName = $_POST['first_name'];
    //$lastName = $_POST['last_name'];
    //$email = $_POST['email'];
    
    $username  = trim($_POST['username'] ?? '');
    $password  = $_POST['password'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName  = trim($_POST['last_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    
    // Helper function to detect dangerous characters and malformed payload patterns
    $hasDangerousChars = function($str) {
        $dangerous = ['<', '>', '"', "'", '&', ';', '(', ')', '[', ']', '%', '\\', '/'];
        foreach ($dangerous as $char) {
            if (stripos($str, $char) !== false) {
                return true;
                $logger->warning("Input contains dangerous character '$char': $str");
            }
        }

        // Reject malformed bracketed expressions such as [XYZ%] or [ABCDEF...]
        if (preg_match('/\[[^\]]+\]/', $str)) {
            return true;
            $logger->warning("Input contains suspicious bracketed expression: $str");
        }

        return false;
    };
    
        // Validation checks
    $setValidationError = function(string $logMessage) use (&$error_message, $logger): void {
        if (!isset($error_message)) {
            $error_message = 'Unable to process the request. Please verify your input and try again.';
        }
        $logger->warning($logMessage);
    };

    if ($username === '') {
        $setValidationError('Username is empty during account request.');
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $setValidationError('Username failed regex validation during account request.');
    } elseif ($hasDangerousChars($username)) {
        $setValidationError('Username contains invalid characters during account request.');
    }

    if (!isset($error_message) && strlen($password) < 8) {
        $setValidationError('Password too short during account request.');
    } elseif (!isset($error_message) && !preg_match('/[A-Za-z]/', $password)) {
        $setValidationError('Password missing letter during account request.');
    } elseif (!isset($error_message) && !preg_match('/[0-9]/', $password)) {
        $setValidationError('Password missing number during account request.');
    } elseif (!isset($error_message) && !preg_match('/^[\x20-\x7E]{8,128}$/', $password)) {
        $setValidationError('Password contains invalid characters during account request.');
    }

    if (!isset($error_message) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $setValidationError('Email failed validation during account request.');
    } elseif (!isset($error_message) && $hasDangerousChars($email)) {
        $setValidationError('Email contains invalid characters during account request.');
    }

    if (!isset($error_message) && !preg_match('/^[\p{L} \'\-]{1,50}$/u', $firstName)) {
        $setValidationError('First name contains invalid characters during account request.');
    } elseif (!isset($error_message) && $hasDangerousChars($firstName)) {
        $setValidationError('First name contains invalid characters during account request.');
    }

    if (!isset($error_message) && !preg_match('/^[\p{L} \'\-]{1,50}$/u', $lastName)) {
        $setValidationError('Last name contains invalid characters during account request.');
    } elseif (!isset($error_message) && $hasDangerousChars($lastName)) {
        $setValidationError('Last name contains invalid characters during account request.');
    }
// Only proceed with database insert if no validation errors
    if (!isset($error_message)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, password, default_role_id, approved) VALUES (?, ?, ?, ?, ?, 3, 0)");
        $stmt->bind_param("sssss", $username, $firstName, $lastName, $email, $hashedPassword);
        $stmt->execute();
        $logger->warning("New account request inserted for username: $username");

        if ($stmt->affected_rows > 0) {
            header("Location: /login.php");
            exit();
        } else {
            $error_message = 'Unable to process the request. Please verify your input and try again later.';
            error_log('Request account failed: ' . $conn->error);
            $logger->error('Request account failed: ' . $conn->error);
        }
    }

    $conn->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
    <title>Request Account</title>
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <h2 class="text-center">Request Account</h2>
                <?php if (isset($error_message)) : ?>
                    <div class="alert alert-danger" role="alert">
                        <?php echo htmlspecialchars($error_message, ENT_QUOTES, 'UTF-8'); ?>
                    </div>
                <?php endif; ?>
            <form action="request_account.php" method="post">
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" class="form-control" id="first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" class="form-control" id="last_name" name="last_name" required>
                </div>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" class="form-control" id="email" name="email" required>
                </div>
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" class="form-control" id="password" name="password" required minlength="8" maxlength="128">
                </div>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn btn-primary btn-block">Request Account</button>
                <a href="/login.php" class="btn btn-secondary btn-block mt-2">Return to Login</a>
            </form>
        </div>
    </div>
</body>
</html>
