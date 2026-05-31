<?php include './components/authenticate.php';?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Manager</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <!-- Bootstrap JS and other scripts -->
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <style>
        .footer-custom {
            background-color: #062caa !important;
        }
    </style>
</head>
<body>

<!-- Navigation Bar -->
<?php include './components/nav-bar.php'?>

<!-- Main Content Area -->
<div class="container mt-4">
    
    <h2>Welcome to the Educational Password Manager</h2>

    <p>This is a practice application for learning about password management and security. You can add, view, and manage your passwords securely.</p>
    <div class="alert alert-info" role="alert">
        <h4 class="alert-heading">Important Notice!</h4>
        <p>This application is for educational purposes only. Do not use real passwords or sensitive information while testing.</p>
</div>

</div>

<!-- Footer -->
<footer class="footer mt-5 py-3 bg-dark text-white footer-custom">
    <div class="container text-center">
        <span>&copy; 2024 UW HusKey Manager. All rights reserved.</span>
    </div>
</footer>

</body>
</html>
