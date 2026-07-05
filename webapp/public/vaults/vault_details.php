<?php

include '../components/logger.php';
// Replace with your database connection details
$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';


$conn = new mysqli($hostname, $username, $password, $database);

if ($conn->connect_error) {
    $logger->alert("Database connection failed");
    die ('A fatal error occurred and has been logged.');
}

$uploadDir = './uploads/'; // Specify the directory where you want to save the uploaded files

include '../components/authenticate.php';
include '../components/authorization.php';

requireCsrfToken();

function isValidPasswordString(string $password): bool
{
    return preg_match('/^[\x20-\x7E]{8,128}$/', $password) === 1;
}

$vaultId = isset($_GET['vault_id']) ? intval($_GET['vault_id']) : (isset($_POST['vaultId']) ? intval($_POST['vaultId']) : 0);
if ($vaultId <= 0) {
    die('Invalid vault selected.');
}

if (!hasPermission('READ', $vaultId)) {
    die('Unauthorized access to this vault.');
}

$userVaultRole = getUserVaultRole($vaultId);
$isVaultOwner = canManageVaultPermissions($vaultId);
$canWrite = canWriteVault($vaultId);
$canDelete = canDeleteVault($vaultId);
$canManagePermissions = canManageVaultPermissions($vaultId);


// Add Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['addUsername']) && isset ($_POST['addWebsite']) && isset ($_POST['addPassword']) && isset ($_POST['vaultId'])) {
    $vaultId = intval($_POST['vaultId']);
    if (!hasPermission('WRITE', $vaultId)) {
        die('Unauthorized action on this vault.');
    }
    $addUsername = $_POST['addUsername'];
    $addWebsite = $_POST['addWebsite'];
    $addPassword = $_POST['addPassword'];
    $addNotes = $_POST['addNotes'];

    // Check if a file is uploaded
    if (!empty ($_FILES['file']['name'])) {
        $uploadFile = $uploadDir . basename($_FILES['file']['name']);

        if (move_uploaded_file($_FILES['file']['tmp_name'], $uploadFile)) {
            $filePath = $uploadFile;
        } else {
            // Handle file upload error            
            die ('Error uploading file.');
        }
    } else {
        // If no file is uploaded, set the file path to NULL
        $filePath = null;
    }

    if (!isValidPasswordString($addPassword)) {
        die('Invalid password format. Password must be 8-128 printable ASCII characters.');
    }

    $stmtAddPassword = null;
    if ($filePath === null) {
        $stmtAddPassword = $conn->prepare("INSERT INTO vault_passwords (vault_id, username, website, password, notes, file_path) VALUES (?, ?, ?, ?, ?, NULL)");
        if ($stmtAddPassword) {
            $stmtAddPassword->bind_param('issss', $vaultId, $addUsername, $addWebsite, $addPassword, $addNotes);
        }
    } else {
        $stmtAddPassword = $conn->prepare("INSERT INTO vault_passwords (vault_id, username, website, password, notes, file_path) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmtAddPassword) {
            $stmtAddPassword->bind_param('isssss', $vaultId, $addUsername, $addWebsite, $addPassword, $addNotes, $filePath);
        }
    }

    if (!$stmtAddPassword || !$stmtAddPassword->execute()) {
        $logger->alert('Failed adding password: ' . ($stmtAddPassword ? $stmtAddPassword->error : $conn->error));
        die ('A fatal error occurred and has been logged.');
    }
    $stmtAddPassword->close();
    // Redirect to the current page after adding the password
    header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
    exit();
}

// Edit Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['editPasswordId']) && isset ($_POST['editUsername']) && isset ($_POST['editPassword']) && isset ($_POST['editWebsite']) && isset ($_POST['vaultId'])) {
    $vaultId = intval($_POST['vaultId']);
    if (!hasPermission('WRITE', $vaultId)) {
        die('Unauthorized action on this vault.');
    }
    $editUsername = $_POST['editUsername'];
    $editWebsite = $_POST['editWebsite'];
    $editPassword = $_POST['editPassword'];
    $editNotes = $_POST['editNotes'];
    $editPasswordId = intval($_POST['editPasswordId']);

    // Check if a new file is uploaded
    if (!empty ($_FILES['editFile']['name'])) {
        $updateFile = $uploadDir . basename($_FILES['editFile']['name']);

        if (move_uploaded_file($_FILES['editFile']['tmp_name'], $updateFile)) {
            $filePath = $updateFile;
        } else {

            die ('Error uploading file.');
        }
    } else {
        // If no new file is uploaded, preserve the existing file path
        $stmtGetFilePath = $conn->prepare("SELECT file_path FROM vault_passwords WHERE password_id = ?");
        if ($stmtGetFilePath) {
            $stmtGetFilePath->bind_param('i', $editPasswordId);
            $stmtGetFilePath->execute();
            $resultGetFilePath = $stmtGetFilePath->get_result();
            if ($resultGetFilePath && $resultGetFilePath->num_rows > 0) {
                $row = $resultGetFilePath->fetch_assoc();
                $existingFilePath = $row['file_path'];
                $filePath = $existingFilePath;
            } else {
                die ('Error retrieving existing file path.');
            }
            $stmtGetFilePath->close();
        } else {
            die ('Error retrieving existing file path.');
        }
    }

    if (!isValidPasswordString($editPassword)) {
        die('Invalid password format. Password must be 8-128 printable ASCII characters.');
    }

    if ($filePath === null) {
        $stmtEditPassword = $conn->prepare("UPDATE vault_passwords SET username = ?, website = ?, password = ?, notes = ?, file_path = NULL WHERE password_id = ?");
        if ($stmtEditPassword) {
            $stmtEditPassword->bind_param('ssssi', $editUsername, $editWebsite, $editPassword, $editNotes, $editPasswordId);
        }
    } else {
        $stmtEditPassword = $conn->prepare("UPDATE vault_passwords SET username = ?, website = ?, password = ?, notes = ?, file_path = ? WHERE password_id = ?");
        if ($stmtEditPassword) {
            $stmtEditPassword->bind_param('sssssi', $editUsername, $editWebsite, $editPassword, $editNotes, $filePath, $editPasswordId);
        }
    }

    if (!$stmtEditPassword || !$stmtEditPassword->execute()) {
        $logger->alert("Database connection failed editing password");
        die ('A fatal error occurred and has been logged.');
    }
    $stmtEditPassword->close();

    $logger->warning("Password for $editUsername was modified");
    // Redirect to the current page after updating the password
    header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
    exit();
}


// Delete Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['deletePasswordId']) && isset ($_POST['vaultId'])) {
    $deletePasswordId = intval($_POST['deletePasswordId']);
    $vaultId = intval($_POST['vaultId']);
    if (!hasPermission('DELETE', $vaultId)) {
        die('Unauthorized action on this vault.');
    }

    $stmtDeletePassword = $conn->prepare("DELETE FROM vault_passwords WHERE password_id = ?");
    if ($stmtDeletePassword) {
        $stmtDeletePassword->bind_param('i', $deletePasswordId);
        if (!$stmtDeletePassword->execute()) {
            $logger->alert("Database connection failed deleting password: " . $stmtDeletePassword->error);
            die ('A fatal error occurred and has been logged.');
        }
        $stmtDeletePassword->close();
    } else {
        $logger->alert("Database connection failed preparing delete password: " . $conn->error);
        die ('A fatal error occurred and has been logged.');
    }

    $logger->warning("Password was deleted from $vaultId");
    // Redirect to the current page after deleting the password
    header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
    exit();
}

// Retrieve vault information
$vaultId = isset ($_GET['vault_id']) ? $_GET['vault_id'] : 0;

$stmtVault = $conn->prepare("SELECT vault_name FROM vaults WHERE vault_id = ?");
if ($stmtVault) {
    $stmtVault->bind_param('i', $vaultId);
    $stmtVault->execute();
    $result = $stmtVault->get_result();
    if (!$result) {
        $logger->alert("Database connection failed");
        die ("Query failed: " . $conn->error);
    }
    $row = $result->fetch_assoc();
    $vaultName = $row['vault_name'];
    $stmtVault->close();
} else {
    $logger->alert("Database connection failed preparing vault select: " . $conn->error);
    die ('A fatal error occurred and has been logged.');
}

//$row = $result->fetch_assoc();
//$vaultName = $row['vault_name'];

$queryPasswords = "SELECT * FROM vault_passwords WHERE vault_id = $vaultId";

$searchQuery = "";
$searchTerm = "";
$searchError = '';
// Handle a Search request
if (isset ($_GET['searchQuery']) && trim($_GET['searchQuery']) !== '') {
    $searchQuery = trim($_GET['searchQuery']);
    $searchTerm = $conn->real_escape_string($searchQuery);
    $queryPasswords = "SELECT * FROM vault_passwords
            WHERE vault_id = $vaultId
            AND (vault_passwords.username LIKE '%$searchTerm%' OR vault_passwords.website LIKE '%$searchTerm%')";
}

// Retrieve passwords for the vault

$resultPasswords = $conn->query($queryPasswords);

if (!$resultPasswords) {
    $logger->alert("Password search query failed for vault $vaultId: " . $conn->error);
    $searchError = 'An error occurred while searching passwords.';
    $resultPasswords = $conn->query("SELECT * FROM vault_passwords WHERE vault_id = $vaultId AND 1=0");
}

// Check if search was performed but returned no results
if ($searchTerm !== '' && $resultPasswords->num_rows === 0) {
    $searchError = 'No passwords match your search criteria.';
    $logger->warning("User searched for '$searchTerm' in vault $vaultId but no passwords matched");
}

// Handle file deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['deleteFilePasswordId']) && isset ($_POST['deleteFileSubmit'])) {
    $deleteFilePasswordId = intval($_POST['deleteFilePasswordId']);
    $vaultId = intval($_POST['deleteFileVaultId']);
    if (!hasPermission('WRITE', $vaultId)) {
        die('Unauthorized action on this vault.');
    }

    // Retrieve the file path from the database using the password id
    $stmtGetFilePath = $conn->prepare("SELECT file_path FROM vault_passwords WHERE password_id = ?");
    if ($stmtGetFilePath) {
        $stmtGetFilePath->bind_param('i', $deleteFilePasswordId);
        $stmtGetFilePath->execute();
        $resultGetFilePath = $stmtGetFilePath->get_result();
        if ($resultGetFilePath && $resultGetFilePath->num_rows > 0) {
            $row = $resultGetFilePath->fetch_assoc();
            $filePathToDelete = $row['file_path'];

            // Delete the file from the server
            if ($filePathToDelete && file_exists($filePathToDelete)) {
                if (unlink($filePathToDelete)) {
                    // File deleted successfully
                    // Now update the file path in the database to NULL
                    $stmtUpdateFilePath = $conn->prepare("UPDATE vault_passwords SET file_path = NULL WHERE password_id = ?");
                    if ($stmtUpdateFilePath) {
                        $stmtUpdateFilePath->bind_param('i', $deleteFilePasswordId);
                        if (!$stmtUpdateFilePath->execute()) {
                            die ('A fatal error occurred and has been logged.');
                        }
                        $stmtUpdateFilePath->close();
                    } else {
                        die ('A fatal error occurred and has been logged.');
                    }
                } else {
                    die ('A fatal error occurred while deleting the file.');
                }
            } else {
                die ('The file to be deleted does not exist.');
            }
        } else {
            die ('The file path associated with the password was not found.');
        }
        $stmtGetFilePath->close();
    } else {
        die ('A fatal error occurred and has been logged.');
    }

    // Redirect to the current page after deleting the file
    header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>
        <?php echo $vaultName; ?> Vault
    </title>
    <!-- Add Bootstrap CSS link here -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
</head>

<body>

    <?php include '../components/nav-bar.php'; ?>


    <div class="container mt-4">
        <h2>
            <?php echo htmlspecialchars($vaultName); ?> Vault Passwords
        </h2>
        <?php if ($canWrite): ?>
            <button type="button" class="btn btn-primary mb-2" data-toggle="modal" data-target="#addPasswordModal">
                Add Password
            </button>
        <?php endif; ?>
        <?php if ($canManagePermissions): ?>
            <a href="./vault_permissions.php?vault_id=<?php echo $vaultId ?>" class="btn btn-warning mb-2"> Edit Vault
                Permissions </a>
        <?php endif; ?>

        <input type="text" id="searchInput" placeholder="Search for passwords..."
            class="form-control mb-3">
        
        <?php if (!empty ($searchQuery)) {
            // If $searchQuery is not blank, display the label with its value
            echo "<label>Search Results for : " . htmlspecialchars($searchQuery) . "</label>"; 
        }
        
        if ($searchError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($searchError); ?></div>
        <?php else: ?>
        <table class="table table-bordered" id="passwordTable">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Website</th>
                    <th>Password</th>
                    <th>Notes</th>
                    <th>Actions</th>
                    <th>File</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($rowPassword = $resultPasswords->fetch_assoc()): ?>
                    <tr data-password-id="<?php echo $rowPassword['password_id']; ?>">
                        <td>
                            <?php echo htmlspecialchars($rowPassword['username']); ?>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($rowPassword['website']); ?>
                        </td>
                        <td>
                            <input type="password" class="password-field" value="<?php echo htmlspecialchars($rowPassword['password']); ?>"
                                disabled>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($rowPassword['notes']); ?>
                        </td>
                        <td>
                            <button class="btn btn-primary btn-sm show-password-btn" data-entry-id="<?= $rowPassword['password_id'] ?>">Show Password</button>
                            <?php if ($canWrite): ?>
                                <button class="btn btn-warning btn-sm edit-password-btn" data-toggle="modal"
                                    data-target="#editPasswordModal" data-password-notes="<?php echo htmlspecialchars($rowPassword['notes']); ?>"
                                    data-password-password="<?php echo htmlspecialchars($rowPassword['password']); ?>"
                                    data-password-website="<?php echo htmlspecialchars($rowPassword['website']); ?>"
                                    data-password-username="<?php echo htmlspecialchars($rowPassword['username']); ?>"
                                    data-password-id="<?php echo $rowPassword['password_id']; ?>">Edit</button>
                            <?php endif; ?>
                            <?php if ($canDelete): ?>
                                <button class="btn btn-danger btn-sm delete-password-btn" data-toggle="modal"
                                    data-target="#deletePasswordModal"
                                    data-password-id="<?php echo $rowPassword['password_id']; ?>">Delete</button>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!empty ($rowPassword['file_path'])): ?>
                                <a href="download_file.php?file=<?php echo urlencode($rowPassword['file_path']); ?>&vault_id=<?php echo urlencode($vaultId); ?>"
                                    target="_blank">Download File</a>
                                <?php if ($canWrite): ?>
                                    <form method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                                        <input type="hidden" name="deleteFilePasswordId"
                                            value="<?php echo $rowPassword['password_id']; ?>">
                                        <input type="hidden" name="deleteFileVaultId" value="<?php echo $vaultId; ?>">
                                        <button type="submit" name="deleteFileSubmit" class="btn btn-danger btn-sm">Delete
                                            File</button>
                                    </form>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; // end searchError check ?>
    </div>

    <div class="modal" id="addPasswordModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Add New Password</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Add form for adding a new password here -->
                    <form method="POST" id="addPasswordForm" enctype="multipart/form-data">
                        <input type="hidden" id="addVaultId" name="vaultId" value="<?php echo $vaultId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="addUsername">Username:</label>
                            <input type="text" class="form-control" id="addUsername" name="addUsername" required>
                        </div>
                        <div class="form-group">
                            <label for="addWebsite">Website:</label>
                            <input type="text" class="form-control" id="addWebsite" name="addWebsite" required>
                        </div>
                        <div class="form-group">
                            <label for="addPassword">Password:</label>
                            <input type="password" class="form-control" id="addPassword" name="addPassword" required minlength="8" maxlength="128">
                        </div>
                        <div class="form-group">
                            <label for="addNotes">Notes:</label>
                            <textarea class="form-control" id="addNotes" name="addNotes" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="file">File:</label>
                            <input type="file" name="file">
                        </div>
                        <button type="submit" class="btn btn-primary">Add Password</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for editing a password -->
    <div class="modal" id="editPasswordModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Edit Password</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Add form for editing a password here -->
                    <form method="POST" id="editPasswordForm" enctype="multipart/form-data">
                        <input type="hidden" id="editVaultId" name="vaultId" value="<?php echo $vaultId; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="editUsername">Username:</label>
                            <input type="text" class="form-control" id="editUsername" name="editUsername" required>
                        </div>
                        <div class="form-group">
                            <label for="editWebsite">Website:</label>
                            <input type="text" class="form-control" id="editWebsite" name="editWebsite" required>
                        </div>
                        <div class="form-group">
                            <label for="editPassword">Password:</label>
                            <input type="password" class="form-control" id="editPassword" name="editPassword" required minlength="8" maxlength="128">
                        </div>
                        <div class="form-group">
                            <label for="editNotes">Notes:</label>
                            <textarea class="form-control" id="editNotes" name="editNotes" rows="3"></textarea>
                        </div>
                        <div class="form-group">
                            <label for="editFile">File:</label>
                            <input type="file" name="editFile">
                        </div>
                        <input type="hidden" id="editPasswordId" name="editPasswordId">
                        <button type="submit" class="btn btn-warning">Update Password</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for deleting a password -->
    <div class="modal" id="deletePasswordModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Delete Password</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <p>Are you sure you want to delete this password?</p>
                    <!-- Add hidden input for password ID -->
                    <form method="POST" id="deletePasswordForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="deleteVaultId" name="vaultId" value="<?php echo $vaultId; ?>">
                        <input type="hidden" id="deletePasswordId" name="deletePasswordId">
                        <button type="submit" class="btn btn-danger" id="confirmDeletePasswordBtn">Delete</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Add Bootstrap JS and Popper.js scripts here -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha384-5AkRS45j4ukf+JbWAfHL8P4onPA9p0KwwP7pUdjSQA3ss9edbJUJc/XcYAiheSSz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>
    <script src="/js/site.js"></script>
    <!-- Add your custom JavaScript script for handling modals and row click redirection -->
    </body>

</html>

<?php
$conn->close();
?>