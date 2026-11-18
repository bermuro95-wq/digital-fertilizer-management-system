<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['user_role']);
}

// Check user role
function hasRole($role) {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === $role;
}

// Check if admin
function isAdmin() {
    return hasRole('admin');
}

// Check if officer
function isOfficer() {
    return hasRole('officer');
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: ../login.php');
        exit();
    }
}

// Require admin access
function requireAdmin() {
    requireLogin();
    if (!isAdmin()) {
        header('Location: ../index.php');
        exit();
    }
}

// Require officer access
function requireOfficer() {
    requireLogin();
    if (!isOfficer()) {
        header('Location: ../index.php');
        exit();
    }
}

// Get current user ID
function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

// Get current user name
function getCurrentUserName() {
    return $_SESSION['user_name'] ?? 'User';
}

// Get current user email
function getCurrentUserEmail() {
    return $_SESSION['user_email'] ?? '';
}

// Get current user role
function getCurrentUserRole() {
    return $_SESSION['user_role'] ?? '';
}

// Set session variables
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
}

// Destroy session (logout)
function destroySession() {
    $_SESSION = array();
    
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    session_destroy();
}

// Regenerate session ID for security
function regenerateSession() {
    session_regenerate_id(true);
}

// Check session timeout (30 minutes)
function checkSessionTimeout() {
    $timeout = 1800; // 30 minutes
    
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        destroySession();
        header('Location: ../login.php?timeout=1');
        exit();
    }
    
    $_SESSION['last_activity'] = time();
}
?>