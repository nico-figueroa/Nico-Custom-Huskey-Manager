<?php

function getCurrentUsername() {
    return $_SESSION['authenticated'] ?? null;
}

function isSiteAdmin() {
    return isset($_SESSION['isSiteAdministrator']) && $_SESSION['isSiteAdministrator'] == true;
}

function requireSiteAdministrator() {
    if (!isSiteAdmin()) {
        header('Location: /index.php');
        exit;
    }
}

function getLogger() {
    return $GLOBALS['logger'] ?? null;
}

function getDbConnection() {
    return $GLOBALS['conn'] ?? null;
}

function getUserVaultRole($vaultId) {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }

    if (isSiteAdmin()) {
        return 'Owner';
    }

    $username = getCurrentUsername();
    if (!$username) {
        return null;
    }

    $usernameEscaped = $conn->real_escape_string($username);
    $query = "SELECT roles.role AS role
              FROM vault_permissions
              JOIN users ON vault_permissions.user_id = users.user_id
              JOIN roles ON vault_permissions.role_id = roles.role_id
              WHERE vault_permissions.vault_id = $vaultId
              AND users.username = '$usernameEscaped'";

    $result = $conn->query($query);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc()['role'];
    }

    return null;
}

function hasPermission($operation, $vaultId) {
    $logger = getLogger();
    $userVaultRole = getUserVaultRole($vaultId);
    $op = strtoupper($operation);

    if ($userVaultRole === 'Owner') {
        return true;
    }

    if ($userVaultRole === 'Editor' && $op !== 'DELETE') {
        return true;
    }

    if ($userVaultRole === 'Viewer' && $op === 'READ') {
        return true;
    }

    if ($logger) {
        $logger->warning(getCurrentUsername() . " is attempting the unauthorized action of : $operation on Vault ID : $vaultId");
    }

    return false;
}

function canReadVault($vaultId) {
    return hasPermission('READ', $vaultId);
}

function canWriteVault($vaultId) {
    return hasPermission('WRITE', $vaultId);
}

function canDeleteVault($vaultId) {
    return hasPermission('DELETE', $vaultId);
}

function canManageVaultPermissions($vaultId) {
    $userVaultRole = getUserVaultRole($vaultId);
    return $userVaultRole === 'Owner';
}

function getAuthorizedVaultsQuery($searchTerm = '') {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }

    $searchTerm = trim($searchTerm);
    $searchClause = '';

    if ($searchTerm !== '') {
        $searchEscaped = $conn->real_escape_string($searchTerm);
        $searchClause = " AND vaults.vault_name LIKE '%$searchEscaped%'";
    }

    if (isSiteAdmin()) {
        return "SELECT vaults.vault_id, vaults.vault_name, 'Owner' AS role FROM vaults" . ($searchClause ? " WHERE " . substr($searchClause, 5) : '');
    }

    $username = getCurrentUsername();
    if (!$username) {
        return "SELECT vaults.vault_id, vaults.vault_name, roles.role FROM vaults WHERE 0";
    }

    $usernameEscaped = $conn->real_escape_string($username);
    $query = "SELECT vaults.vault_id, vaults.vault_name, roles.role
              FROM vaults
              JOIN vault_permissions ON vaults.vault_id = vault_permissions.vault_id
              JOIN users ON vault_permissions.user_id = users.user_id
              JOIN roles ON vault_permissions.role_id = roles.role_id
              WHERE users.username = '$usernameEscaped'";

    if ($searchClause !== '') {
        $query .= $searchClause;
    }

    return $query;
}

function ensureVaultPermission($operation, $vaultId) {
    if (!hasPermission($operation, $vaultId)) {
        die('Unauthorized access to this vault.');
    }
}

?>