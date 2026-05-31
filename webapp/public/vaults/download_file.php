<?php

include '../components/authenticate.php';

$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';

$conn = new mysqli($hostname, $username, $password, $database);
if ($conn->connect_error) {
    die('A fatal error occurred and has been logged.');
}

// Make the connection available to the authorization helper
$GLOBALS['conn'] = $conn;

include '../components/authorization.php';

if (isset($_GET['file']) && isset($_GET['vault_id'])) {
    $filePath = $_GET['file'];
    $vaultId = intval($_GET['vault_id']);

    if (!canReadVault($vaultId)) {
        die('Unauthorized access to this vault.');
    }

    if (file_exists($filePath)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($filePath));

        ob_clean();
        flush();

        readfile($filePath);
        exit;
    } else {
        echo $filePath;
        die('File not found.');
    }
} else {
    die('Invalid file request.');
}

?>