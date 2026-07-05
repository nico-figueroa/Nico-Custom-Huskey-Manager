<?php

include '../components/authenticate.php';
include '../components/authorization.php';
include '../components/logger.php';

requireSiteAdministrator();

$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';

$conn = new mysqli($hostname, $username, $password, $database);

if ($conn->connect_error) {
    $logger->error("Database connection failed: " . $conn->connect_error);
    die("Connection failed: " . $conn->connect_error);
}

// Fetch users, roles, and vaults from the database
$queryUsers = "SELECT * FROM users";
$resultUsers = $conn->query($queryUsers);
$logger->warning("Fetched users from database.");

$queryRoles = "SELECT * FROM roles";
$resultRoles = $conn->query($queryRoles);
$logger->warning("Fetched roles from database.");

$queryVaults = "SELECT * FROM vaults";
$resultVaults = $conn->query($queryVaults);
$logger->warning("Fetched vaults from database.");

// Handle form submissions

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'addPermission' && isset($_POST['user_id']) && isset($_POST['role_id']) && isset($_POST['vault_id'])) {
            $userId = $_POST['user_id'];
            $roleId = $_POST['role_id'];
            $vaultId = $_POST['vault_id'];

            // Perform the necessary database operations to manage user-role-vault relationships
            // For example, you can insert, update, or delete records in the vault_permissions table
            $query = "INSERT INTO vault_permissions (user_id, role_id, vault_id) VALUES ($userId, $roleId, $vaultId)";
            $result = $conn->query($query);
            $logger->warning("Added permission: user_id=$userId, role_id=$roleId, vault_id=$vaultId");

            if (!$result) {
                $logger->error("Error managing user-role-vault relationship: " . $conn->error);
                die("Error managing user-role-vault relationship: " . $conn->error);
            }

            // Redirect to the current page after managing the relationship
            header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
            exit();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'delete' && isset($_POST['permission_id']) && isset($_POST['vault_id'])) {
            $permissionId = $_POST['permission_id'];
            $vaultId = $_POST['vault_id'];

            // Perform the necessary database operations to delete the permission
            $queryDelete = "DELETE FROM vault_permissions WHERE permission_id = $permissionId";
            $resultDelete = $conn->query($queryDelete);
            $logger->warning("Deleted permission: permission_id=$permissionId, vault_id=$vaultId");

            if (!$resultDelete) {
                $logger->error("Error deleting permission: " . $conn->error);
                die("Error deleting permission: " . $conn->error);
            }

            // Redirect to the current page after deleting the permission
            header("Location: {$_SERVER['PHP_SELF']}?vault_id=$vaultId");
            exit();
        }
    }
}

// Initialize variables for selected vault and permissions
$selectedVaultId = isset($_GET['vault_id']) ? $_GET['vault_id'] : null;
$selectedVaultName = null;
$permissions = array();

// Fetch selected vault information and associated permissions
if ($selectedVaultId) {
    $queryVault = "SELECT vault_name FROM vaults WHERE vault_id = $selectedVaultId";
    $resultVault = $conn->query($queryVault);
    $selectedVault = $resultVault->fetch_assoc();
    $selectedVaultName = $selectedVault['vault_name'];

    $queryPermissions = "SELECT u.username, r.role, p.permission_id, u.user_id, r.role_id
                         FROM vault_permissions p
                         JOIN users u ON p.user_id = u.user_id
                         JOIN roles r ON p.role_id = r.role_id
                         WHERE p.vault_id = $selectedVaultId";
    $resultPermissions = $conn->query($queryPermissions);
    $logger->warning("Fetched permissions for vault_id=$selectedVaultId from database.");

    while ($permission = $resultPermissions->fetch_assoc()) {
        $permissions[] = $permission;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>User-Role-Vault Relationship Management</title>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
    <!-- Bootstrap JS and other scripts -->
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha384-5AkRS45j4ukf+JbWAfHL8P4onPA9p0KwwP7pUdjSQA3ss9edbJUJc/XcYAiheSSz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>
    <script src="/js/site.js"></script>
</head>

<body>
    <?php include '../components/nav-bar.php' ?>

    <div class="container mt-4">
        <h2>User-Role-Vault Relationship Management</h2>

        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
            <div class="form-group">
                <label for="vault">Select Vault:</label>
                <select class="form-control" id="vault" name="vault_id" required>
                    <option value="" disabled selected>Select a Vault</option>
                    <?php while ($vault = $resultVaults->fetch_assoc()): ?>
                        <option value="<?php echo $vault['vault_id']; ?>" <?php echo ($selectedVaultId == $vault['vault_id']) ? 'selected' : ''; ?>>
                            <?php echo $vault['vault_name']; ?>
                        </option>
                    <?php endwhile; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedVaultId): ?>
            <h3>Permissions for Vault:
                <?php echo $selectedVaultName; ?>
            </h3>

            <table class="table">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissions as $permission): ?>
                        <tr>
                            <td>
                                <?php echo $permission['username']; ?>
                            </td>
                            <td>
                                <?php echo $permission['role']; ?>
                            </td>
                            <td>
                                <!-- Delete button to open the delete modal -->
                                <button class="btn btn-danger btn-sm delete-btn" data-toggle="modal" data-target="#deleteModal"
                                    data-permission-id="<?php echo $permission['permission_id']; ?>"
                                    data-vault-id="<?php echo $selectedVaultId; ?>">
                                    Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <!-- Add Permission button to open a modal for adding a new permission -->
            <button class="btn btn-success" data-toggle="modal" data-target="#addModal">Add Permission</button>

            <!-- Delete Permission Modal -->
            <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel"
                aria-hidden="true">
                <!-- ... (Remaining modal code) ... -->
                <form id="deleteForm" method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                    <input type="hidden" name="vault_id" id="deleteVaultId" value="">
                    <input type="hidden" name="permission_id" id="deletePermissionId" value="">
                    <input type="hidden" name="action" value="delete">
                </form>
                </div>
            <!-- Add Permission Modal -->
            <div class="modal fade" id="addModal" tabindex="-1" role="dialog" aria-labelledby="addModalLabel"
                aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="addModalLabel">Add Permission</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <!-- Add your form for adding permission here -->
                            <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                                <input type="hidden" name="vault_id" value="<?php echo $selectedVaultId; ?>">
                                <input type="hidden" name="action" value="addPermission">
                                <div class="form-group">
                                    <label for="user">Select User:</label>
                                    <select class="form-control" id="user" name="user_id" required>
                                        <?php mysqli_data_seek($resultUsers, 0); ?>
                                        <?php while ($user = $resultUsers->fetch_assoc()): ?>
                                            <option value="<?php echo $user['user_id']; ?>">
                                                <?php echo $user['username']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label for="role">Select Role:</label>
                                    <select class="form-control" id="role" name="role_id" required>
                                        <?php mysqli_data_seek($resultRoles, 0); ?>
                                        <?php while ($role = $resultRoles->fetch_assoc()): ?>
                                            <option value="<?php echo $role['role_id']; ?>">
                                                <?php echo $role['role']; ?>
                                            </option>
                                        <?php endwhile; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary">Add Permission</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php endif; ?>
    </div>

</body>

</html>

<?php
$conn->close();
?>