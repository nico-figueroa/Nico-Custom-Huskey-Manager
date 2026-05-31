<?php

$servername = "backend-mysql-database";
$username = "user";
$password = "supersecretpw";
$dbname = "password_manager";

$conn = new mysqli($servername, $username, $password, $dbname);

unset($error_message);

if ($conn->connect_error) {
    $logger->alert("Database connection failed");
    // die("Connection failed: " . $conn->connect_error);
    error_log('DB connection failed: ' . $conn->connect_error);
    die('Connection failed. Please contact support.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
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
            }
        }

        // Reject malformed bracketed expressions such as [XYZ%] or [ABCDEF...]
        if (preg_match('/\[[^\]]+\]/', $str)) {
            return true;
        }

        return false;
    };
    
    // Validation checks
    if ($username === '') {
        $error_message = 'Username is required.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) {
        $error_message = 'Username may only contain letters, numbers, and underscores (3-30 characters).';
    } elseif ($hasDangerousChars($username)) {
        $error_message = 'Username contains invalid characters.';
    }
    
    if (!isset($error_message) && strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    }
    
    if (!isset($error_message) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } elseif (!isset($error_message) && $hasDangerousChars($email)) {
        $error_message = 'Email contains invalid characters.';
    }
    
    if (!isset($error_message) && !preg_match('/^[\p{L} \'-]{1,50}$/u', $firstName)) {
        $error_message = 'First name contains invalid characters.';
    } elseif (!isset($error_message) && $hasDangerousChars($firstName)) {
        $error_message = 'First name contains invalid characters.';
    }
    
    if (!isset($error_message) && !preg_match('/^[\p{L} \'-]{1,50}$/u', $lastName)) {
        $error_message = 'Last name contains invalid characters.';
    } elseif (!isset($error_message) && $hasDangerousChars($lastName)) {
        $error_message = 'Last name contains invalid characters.';
    }
    
    // Only proceed with database insert if no validation errors
    if (!isset($error_message)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, password, default_role_id, approved) VALUES (?, ?, ?, ?, ?, 3, 0)");
        $stmt->bind_param("sssss", $username, $firstName, $lastName, $email, $hashedPassword);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            header("Location: /login.php");
            exit();
        } else {
            $error_message = 'Error creating account. Please try again later.';
            error_log('Request account failed: ' . $conn->error);
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
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <title>Request Account</title>
</head>
<body>
    <div class="container mt-5">
        <div class="col-md-6 offset-md-3">
            <h2 class="text-center">Request Account</h2>
            <?php if (isset($error_message)) : ?>
                <div class="alert alert-danger" role="alert">
                    <!-- <?php echo $error_message; ?> -->
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
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Request Account</button>
            </form>
        </div>
    </div>
</body>
</html>
