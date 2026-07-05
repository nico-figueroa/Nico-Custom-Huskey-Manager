<?php

include '../components/authenticate.php';
include '../components/authorization.php';
include '../components/logger.php';

requireCsrfToken();

$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';

$conn = new mysqli($hostname, $username, $password, $database);

if ($conn->connect_error) {
    $logger->alert("Connection failed to MySQL database");
    die ('A fatal error occurred and has been logged.');
}

// Add Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['vaultName'])) {
    $vaultName = $_POST['vaultName'];
    $userId = 1; // Replace with the actual user ID

    $stmt = $conn->prepare("INSERT INTO vaults (vault_name) VALUES (?)");
    if ($stmt) {
        $stmt->bind_param('s', $vaultName);
        if (!$stmt->execute()) {
            $logger->alert("Connection failed adding vault: " . $stmt->error);
            die ('A fatal error occurred and has been logged.');
        }
        $insertedVaultId = $stmt->insert_id;
        $stmt->close();
    } else {
        $logger->alert("Connection failed preparing add vault: " . $conn->error);
        die ('A fatal error occurred and has been logged.');
    }

    $user = $_SESSION['authenticated'];
    $stmtFetchUserId = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    if ($stmtFetchUserId) {
        $stmtFetchUserId->bind_param('s', $user);
        $stmtFetchUserId->execute();
        $resultFetchUserId = $stmtFetchUserId->get_result();
        if ($resultFetchUserId && $resultFetchUserId->num_rows > 0) {
            $row = $resultFetchUserId->fetch_assoc();
            $userId = $row['user_id'];
            $roleId = 1;

            $stmtInsertPermission = $conn->prepare("INSERT INTO vault_permissions (user_id, vault_id, role_id) VALUES (?, ?, ?)");
            if ($stmtInsertPermission) {
                $stmtInsertPermission->bind_param('iii', $userId, $insertedVaultId, $roleId);
                if (!$stmtInsertPermission->execute()) {
                    $logger->alert("Connection failed adding vault permission: " . $stmtInsertPermission->error);
                    die ('A fatal error occurred while adding permission.');
                }
                $stmtInsertPermission->close();
            } else {
                $logger->alert("Connection failed preparing vault permission insert: " . $conn->error);
                die ('A fatal error occurred while adding permission.');
            }
        } else {
            $logger->alert("User with username '$user' not found.");
            die ("User with username '$user' not found.");
        }
        $stmtFetchUserId->close();
    } else {
        $logger->alert("Connection failed preparing fetch user id: " . $conn->error);
        die ('A fatal error occurred and has been logged.');
    }

    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
}

// Edit Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['editVaultName']) && isset ($_POST['editVaultId'])) {
    $editVaultName = $_POST['editVaultName'];
    $editVaultId = intval($_POST['editVaultId']);

    $stmt = $conn->prepare("UPDATE vaults SET vault_name = ? WHERE vault_id = ?");
    if ($stmt) {
        $stmt->bind_param('si', $editVaultName, $editVaultId);
        if (!$stmt->execute()) {
            $logger->alert("Connection failed editing vault: " . $stmt->error);
            die ('A fatal error occurred and has been logged.');
        }
        $stmt->close();
    } else {
        $logger->alert("Connection failed preparing edit vault: " . $conn->error);
        die ('A fatal error occurred and has been logged.');
    }

    $logger->warning("Vault $editVaultName modified");
    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
}

// Delete Vault
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset ($_POST['deleteVaultId']) && !empty ($_POST['deleteVaultId'])) {
    $deleteVaultId = intval($_POST['deleteVaultId']);

    $stmt = $conn->prepare("DELETE FROM vaults WHERE vault_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $deleteVaultId);
        if (!$stmt->execute()) {
            $logger->alert("Connection failed deleting vault: " . $stmt->error);
            die ('A fatal error occurred and has been logged.');
        }
        $stmt->close();
    } else {
        $logger->alert("Connection failed preparing delete vault: " . $conn->error);
        die ('A fatal error occurred and has been logged.');
    }

    $logger->warning("Vault $deleteVaultId deleted");
    header("Location: {$_SERVER['PHP_SELF']}");
    exit();
}

// Retrieve vaults from the database
$searchQuery = "";
$searchTerm = "";
if (isset($_GET['searchQuery']) && trim($_GET['searchQuery']) !== '') {
    $searchQuery = trim($_GET['searchQuery']);
    $searchTerm = $conn->real_escape_string($searchQuery);
}

$query = getAuthorizedVaultsQuery($searchTerm);

$result = $conn->query($query);

if (!$result) {
    $logger->alert("Connection failed retrieving vaults");
    die ("Query failed: " . $conn->error);
}
// If a search was performed but returned no authorized vaults, check whether
// any vaults exist that match the search. If they do, the user lacks
// permissions for the specified vault(s) and we should show an explicit error.
$searchError = '';
if ($searchTerm !== '' && $result->num_rows === 0) {
    $searchEscaped = $conn->real_escape_string($searchTerm);
    $globalQuery = "SELECT vault_id, vault_name FROM vaults WHERE vault_name LIKE '%$searchEscaped%'";
    $globalResult = $conn->query($globalQuery);
    if ($globalResult && $globalResult->num_rows > 0) {
        $searchError = 'You have not been granted permissions for the specified vault.';
        $logger->warning("User searched for '$searchTerm' but lacks permissions for matching vaults");
    } else {
        $searchError = 'No vaults match your search.';
        $logger->warning("User searched for '$searchTerm' but no vaults match the search");
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Vaults</title>
    <!-- Add Bootstrap CSS link here -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha384-5AkRS45j4ukf+JbWAfHL8P4onPA9p0KwwP7pUdjSQA3ss9edbJUJc/XcYAiheSSz" crossorigin="anonymous"></script>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
</head>

<body>

    <?php include '../components/nav-bar.php'; ?>

    <div class="container mt-4">
        <h2>Password Vaults</h2>

        <!-- Add button to open a modal for adding a new vault -->
        <button type="button" class="btn btn-primary mb-2" data-toggle="modal" data-target="#addVaultModal">
            Add Vault
        </button>

        <!-- Search input -->
        <input type="text" id="searchInput" placeholder="Search for vaults..."
            class="form-control mb-3">
        
        <?php if (!empty ($searchQuery)) {
            // If $searchQuery is not blank, display the label with its value
            echo "<label>Search Results for : " . htmlspecialchars($searchQuery) . "</label>"; 
        }
        
        if ($searchError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($searchError); ?></div>
        <?php else: ?>
        
        <!-- Table to display vaults -->
        <table class="table table-bordered" id="vaultTable">
            <thead>
                <tr>
                    <th>Vault Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <?php echo htmlspecialchars($row['vault_name']); ?>
                        </td>
                        <td>
                            <a href="vault_details.php?vault_id=<?php echo $row['vault_id']; ?>"
                                class="btn btn-primary btn-sm" role="button" aria-disabled="true">View Vault</a>

                            <?php
                                $canManageVault = (isset($_SESSION['isSiteAdministrator']) && $_SESSION['isSiteAdministrator'] == true) || (isset($row['role']) && $row['role'] === 'Owner');
                            ?>

                            <?php if ($canManageVault): ?>
                                <!-- Edit button to open a modal for editing a vault -->
                                <button class="btn btn-warning btn-sm edit-btn" data-toggle="modal"
                                    data-target="#editVaultModal" data-vault-name="<?php echo htmlspecialchars($row['vault_name']); ?>"
                                    data-vault-id="<?php echo $row['vault_id']; ?>">Edit</button>

                                <!-- Delete button to open a modal for deleting a vault -->
                                <button class="btn btn-danger btn-sm delete-btn" data-toggle="modal"
                                    data-target="#deleteVaultModal" data-vault-name="<?php echo htmlspecialchars($row['vault_name']); ?>"
                                    data-vault-id="<?php echo $row['vault_id']; ?>">Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; // end searchError check ?>
    </div>

    <!-- Modal for adding a new vault -->
    <div class="modal" id="addVaultModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Add New Vault</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Add form for adding a new vault here -->
                    <form method="POST" id="addVaultForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="vaultName">Vault Name:</label>
                            <input type="text" class="form-control" id="vaultName" name="vaultName" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Vault</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for editing a vault -->
    <div class="modal" id="editVaultModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Edit Vault</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Add form for editing a vault here -->
                    <form method="POST" id="editVaultForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <input type="hidden" id="editVaultId" name="editVaultId">
                            <label for="editVaultName">Vault Name:</label>
                            <input type="text" class="form-control" id="editVaultName" name="editVaultName" required>
                        </div>
                        <button type="submit" class="btn btn-warning">Update Vault</button>
                    </form>
                </div>

            </div>
        </div>
    </div>

    <!-- Modal for deleting a vault -->
    <div class="modal" id="deleteVaultModal">
        <div class="modal-dialog">
            <div class="modal-content">

                <!-- Modal Header -->
                <div class="modal-header">
                    <h4 class="modal-title">Delete Vault</h4>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <p id="deleteWarningPara"></p>
                    <!-- Add hidden input for vault ID -->
                    <form method="POST" id="deleteVaultForm">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" id="deleteVaultId" name="deleteVaultId">
                        <button type="submit" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
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
    <!-- Add your custom JavaScript script for handling modals, filtering, and row click redirection -->
    </body>

</html>

<?php
$conn->close();
?>