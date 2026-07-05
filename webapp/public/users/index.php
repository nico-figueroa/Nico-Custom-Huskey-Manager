<?php

include '../components/authenticate.php';
include '../components/logger.php';

$actionError = isset($_GET['error']) ? $_GET['error'] : '';

$hostname = 'backend-mysql-database';
$username = 'user';
$password = 'supersecretpw';
$database = 'password_manager';

$conn = new mysqli($hostname, $username, $password, $database);

if ($conn->connect_error) {    
    die('A fatal error occurred and has been logged.');
    $logger->error('Database connection failed: ' . $conn->connect_error);
    // die("Connection failed: " . $conn->connect_error);
}

// Fetch users from the database
$searchQuery = "";
$searchTerm = "";
$searchError = '';
$queryUsers = "SELECT * FROM users";

// Handle search request
if (isset($_GET['searchQuery']) && trim($_GET['searchQuery']) !== '') {
    $searchQuery = trim($_GET['searchQuery']);
    $searchTerm = $conn->real_escape_string($searchQuery);
    $queryUsers = "SELECT * FROM users WHERE username LIKE '%$searchTerm%' OR first_name LIKE '%$searchTerm%' OR last_name LIKE '%$searchTerm%' OR email LIKE '%$searchTerm%'";
}

$resultUsers = $conn->query($queryUsers);

if (!$resultUsers) {
    $logger->alert("User search query failed: " . $conn->error);
    $searchError = 'An error occurred while searching users.';
    $resultUsers = $conn->query("SELECT * FROM users WHERE 1=0");
}

// Check if search was performed but returned no results
if ($searchTerm !== '' && $resultUsers->num_rows === 0) {
    $searchError = 'No users match your search criteria.';
    $logger->warning("User searched for '$searchTerm' but no users matched");
}

$editUsers = $conn->query("SELECT * FROM users");
$logger->warning("Fetch users query executed");

// Handle form submissions (e.g., Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Require CSRF token for POST requests
    requireCsrfToken();

    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        switch ($action) {
                case 'add_user':
                // Handle adding a user
                if (isset($_POST['username']) && isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email']) && isset($_POST['password'])) {
                    $username = $conn->real_escape_string($_POST['username']);
                    $first_name = $conn->real_escape_string($_POST['first_name']);
                    $last_name = $conn->real_escape_string($_POST['last_name']);
                    $email = $conn->real_escape_string($_POST['email']);
                    $password = $_POST['password'];

                    // Hash the password before storing
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $defaultRoleId = 3;
                    $approved = 1;

                    $stmt = $conn->prepare("INSERT INTO users (username, first_name, last_name, email, password, default_role_id, approved) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    if ($stmt) {
                        $stmt->bind_param("sssssii", $username, $first_name, $last_name, $email, $hashedPassword, $defaultRoleId, $approved);
                        $result = $stmt->execute();
                        $logger->warning("Add user query executed for username: $username");
                        if (!$result) {
                            $errorText = $stmt->error;
                            error_log('Add user execute failed: ' . $errorText);
                            if ($stmt->errno === 1062) {
                                die('A fatal error occurred and has been logged. DB error: Username already exists.');
                                $logger->error('Add user failed: Username already exists. ' . $errorText);
                            }
                            die('A fatal error occurred and has been logged. DB error: ' . htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8'));
                            $logger->error('Add user execute failed: ' . $errorText);
                        }
                        $stmt->close();
                    } else {
                        $errorText = $conn->error;
                        error_log('Add user prepare failed: ' . $errorText);
                        die('A fatal error occurred and has been logged. DB error: ' . htmlspecialchars($errorText, ENT_QUOTES, 'UTF-8'));
                        $logger->error('Add user prepare failed: ' . $errorText);
                    }

                    // Redirect to the current page after handling a POST
                    header("Location: {$_SERVER['PHP_SELF']}");
                    exit();
                }
                break;

                case 'edit_user':
                // Handle editing a user
                if (isset($_POST['user_id']) && isset($_POST['username']) && isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['email'])) {
                    $user_id = $_POST['user_id'];
                    $username = $conn->real_escape_string($_POST['username']);
                    $first_name = $conn->real_escape_string($_POST['first_name']);
                    $last_name = $conn->real_escape_string($_POST['last_name']);
                    $email = $conn->real_escape_string($_POST['email']);
                    
                    //convert the appoved return value to a database value
                    if (isset($_POST['approved']) && $_POST['approved'] == 'on') {
                        $approved = 1;
                    } else {
                        $approved = 0;
                    }

                    // If a new password was provided, hash it and include in the update
                    if (isset($_POST['password']) && trim($_POST['password']) !== '') {
                        $newPassword = $_POST['password'];
                        $hashedNew = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET username=?, first_name=?, last_name=?, email=?, password=?, approved=? WHERE user_id=?");
                        if ($stmt) {
                            $stmt->bind_param("sssssii", $username, $first_name, $last_name, $email, $hashedNew, $approved, $user_id);
                            $result = $stmt->execute();
                            $logger->warning("Edit user query executed with password update for user_id: $user_id");
                            if (!$result) {
                                error_log('Edit user execute failed (with password): ' . $stmt->error);
                                die('A fatal error occurred and has been logged.');
                                $logger->error('Edit user execute failed (with password): ' . $stmt->error);
                            }
                            $stmt->close();
                        } else {
                            error_log('Edit user prepare failed (with password): ' . $conn->error);
                            die('A fatal error occurred and has been logged.');
                            $logger->error('Edit user prepare failed (with password): ' . $conn->error);
                        }
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET username=?, first_name=?, last_name=?, email=?, approved=? WHERE user_id=?");
                        if ($stmt) {
                            $stmt->bind_param("ssssii", $username, $first_name, $last_name, $email, $approved, $user_id);
                            $result = $stmt->execute();
                            if (!$result) {
                                error_log('Edit user execute failed: ' . $stmt->error);
                                die('A fatal error occurred and has been logged.');
                                $logger->error('Edit user execute failed: ' . $stmt->error);
                            }
                            $stmt->close();
                        } else {
                            error_log('Edit user prepare failed: ' . $conn->error);
                            die('A fatal error occurred and has been logged.');
                            $logger->error('Edit user prepare failed: ' . $conn->error);
                        }
                    }

                    
                    header("Location: {$_SERVER['PHP_SELF']}");
                    exit();
                }
                break;

            case 'delete_user':
                // Handle deleting a user (delete dependent permissions first)
                if (isset($_POST['user_id'])) {
                            $user_id = intval($_POST['user_id']);

                            // Prevent deleting the last admin (default_role_id = 1)
                            $stmtCheck = $conn->prepare("SELECT default_role_id FROM users WHERE user_id = ?");
                            if ($stmtCheck) {
                                $stmtCheck->bind_param('i', $user_id);
                                $stmtCheck->execute();
                                $resCheck = $stmtCheck->get_result();
                                $row = $resCheck->fetch_assoc();
                                $stmtCheck->close();
                                if ($row && intval($row['default_role_id']) === 1) {
                                    $countRes = $conn->query("SELECT COUNT(*) AS cnt FROM users WHERE default_role_id = 1");
                                    $cntRow = $countRes->fetch_assoc();
                                    $adminCount = intval($cntRow['cnt']);
                                    if ($adminCount <= 1) {
                                        $logger->warning("Attempt to delete last admin user_id: $user_id");
                                        header("Location: {$_SERVER['PHP_SELF']}?error=last_admin");
                                        exit();
                                    }
                                }
                            }

                    try {
                        $logger->info("Delete request for user_id: $user_id by session user: " . ($_SESSION['user_id'] ?? 'unknown'));
                        $conn->begin_transaction();

                        // Remove any vault permissions for this user
                        $stmt = $conn->prepare("DELETE FROM vault_permissions WHERE user_id = ?");
                        if (!$stmt) {
                            throw new Exception('Prepare failed for vault_permissions delete: ' . $conn->error);
                        }
                        $stmt->bind_param('i', $user_id);
                        $ok = $stmt->execute();
                        $stmt->close();
                        if (!$ok) {
                            throw new Exception('Failed deleting vault_permissions: ' . $conn->error);
                        }

                        // Delete the user
                        $stmt2 = $conn->prepare("DELETE FROM users WHERE user_id = ?");
                        if (!$stmt2) {
                            throw new Exception('Prepare failed for users delete: ' . $conn->error);
                        }
                        $stmt2->bind_param('i', $user_id);
                        $ok2 = $stmt2->execute();
                        if (!$ok2) {
                            $err = $stmt2->error ?: $conn->error;
                            $stmt2->close();
                            throw new Exception('Failed deleting user: ' . $err);
                        }
                        $stmt2->close();

                        $logger->warning("Delete user executed for user_id: $user_id");
                        $conn->commit();
                    } catch (Exception $e) {
                        $conn->rollback();
                        $logger->error('Error deleting user: ' . $e->getMessage());
                        header("Location: {$_SERVER['PHP_SELF']}?error=delete_failed");
                        exit();
                    }

                    header("Location: {$_SERVER['PHP_SELF']}");
                    exit();
                }

                break;
        }
    }
}

?>

<?php include '../components/nav-bar.php'?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>User Management</title>
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css" integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous"> -->
    <link rel="stylesheet" href="/css/matrix.css">
    <script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha384-5AkRS45j4ukf+JbWAfHL8P4onPA9p0KwwP7pUdjSQA3ss9edbJUJc/XcYAiheSSz" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.min.js" integrity="sha384-+sLIOodYLS7CIrQpBjl+C7nPvqq+FbNUBDunl/OZv93DB7Ln/533i8e/mZXLi/P+" crossorigin="anonymous"></script>
    <script src="/js/site.js"></script>
    <!-- Add additional scripts as needed -->
</head>
<body>
    <div class="container mt-4">
        <?php if ($actionError === 'last_admin'): ?>
            <div class="alert alert-warning" role="alert">
                Cannot delete the last administrator account. Assign another admin before deleting this user.
            </div>
        <?php endif; ?>
        <?php if ($actionError === 'delete_failed'): ?>
            <div class="alert alert-danger" role="alert">
                Failed to delete user. The operation was logged; check server logs for details.
            </div>
        <?php endif; ?>
        <h2>User Management</h2>

        <!-- Add User button to open a modal for adding a new user -->
        <button class="btn btn-success mb-10" data-toggle="modal" data-target="#addUserModal">Add User</button>

        <input type="text" id="searchInput" placeholder="Search for users by username, name, or email..." class="form-control mb-3">
        
        <?php if (!empty ($searchQuery)) {
            echo "<label>Search Results for : " . htmlspecialchars($searchQuery) . "</label>"; 
        }
        
        if ($searchError !== ''): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($searchError); ?></div>
        <?php else: ?>
        
        <!-- User Table -->
        <table id="usersTable" class="table">
            <thead>
            <tr>
                <th>Username</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($user = $resultUsers->fetch_assoc()) : ?>
                <tr>
                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                    <td><?php echo htmlspecialchars($user['first_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['last_name']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <!-- Edit button to open a modal for editing a user -->
                        <button class="btn btn-warning btn-sm edit-btn" data-toggle="modal"  data-first_name="<?php echo htmlspecialchars($user['first_name']); ?>"  data-last_name="<?php echo htmlspecialchars($user['last_name']); ?>" data-approved="<?php echo $user['approved']; ?>" data-email="<?php echo htmlspecialchars($user['email']); ?>" data-username="<?php echo htmlspecialchars($user['username']); ?>" data-userid="<?php echo $user['user_id']; ?>" data-target="#editUserModal">
                            Edit
                        </button>
                        <!-- Delete button to open a modal for deleting a user -->
                        <button class="btn btn-danger btn-sm delete-btn" data-toggle="modal" data-userid="<?php echo $user['user_id']; ?>" data-target="#deleteUserModal">
                            Delete
                        </button>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        <?php endif; // end searchError check ?>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" role="dialog" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Add your form for adding a user here -->
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="add_user">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="form-group">
                            <label for="username">Username:</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="firstName">First Name:</label>
                            <input type="text" class="form-control" id="firstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="lastName">Last Name:</label>
                            <input type="text" class="form-control" id="lastName" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="email">Email:</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="form-group">
                            <label for="password">Password:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Approved User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" role="dialog" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">Edit User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <!-- Add your form for editing a user here -->
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="edit_user">
                        <input type="hidden" name="user_id" id="editUserId" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                            
                        <div class="form-group">
                            <label for="editUsername">Username:</label>
                            <input type="text" class="form-control" id="editUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="editFirstName">First Name:</label>
                            <input type="text" class="form-control" id="editFirstName" name="first_name" required>
                        </div>
                        <div class="form-group">
                            <label for="editLastName">Last Name:</label>
                            <input type="text" class="form-control" id="editLastName" name="last_name" required>
                        </div>
                        <div class="form-group">
                            <label for="editEmail">Email:</label>
                            <input type="email" class="form-control" id="editEmail" name="email" required>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="approved" id="editApproved">
                            <label class="form-check-label" for="editApproved">User Approved</label>
                        </div>                   
                        <div class="form-group">
                            <label for="editPassword">Password (leave blank to keep current):</label>
                            <input type="password" class="form-control" id="editPassword" name="password" placeholder="Leave blank to keep current">
                        </div>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" role="dialog" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">Delete User</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this user?</p>
                    <!-- Add your form for deleting a user here -->
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="deleteUserId" value="">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(getCsrfToken(), ENT_QUOTES, 'UTF-8'); ?>">
                        <button type="submit" class="btn btn-danger">Delete User</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    </div>


</body>

</html>

<?php
$conn->close();
?>
