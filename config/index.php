<?php
require_once 'config/session.php';
require_once 'config/language.php';

// Redirect to login if not logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit();
}

// Redirect based on role
if (isAdmin()) {
    header('Location: admin/dashboard.php');
} elseif (isOfficer()) {
    header('Location: officer/dashboard.php');
} else {
    header('Location: login.php');
}
exit();
?>