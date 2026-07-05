<?php include './components/authenticate.php';?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Password Manager</title>
    <!--<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
    <!-- Bootstrap JS and other scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha384-5AkRS45j4ukf+JbWAfHL8P4onPA9p0KwwP7pUdjSQA3ss9edbJUJc/XcYAiheSSz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>
    <script src="/js/site.js"></script>
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
