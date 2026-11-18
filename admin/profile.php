<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Get current user data
$user_id = getCurrentUserId();
$user_query = executeQuery("SELECT * FROM users WHERE id = ?", [$user_id], 'i');
$user = $user_query->fetch_assoc();

// Create profile images directory if not exists
$profile_dir = '../assets/profile_images/';
if (!file_exists($profile_dir)) {
    mkdir($profile_dir, 0777, true);
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = $_POST['name'];
    $email = $_POST['email'];
    
    // Check if email already exists for other users
    $check_email = executeQuery("SELECT id FROM users WHERE email = ? AND id != ?", [$email, $user_id], 'si');
    
    if ($check_email->num_rows > 0) {
        $error = "Email address already exists!";
    } else {
        $result = executeUpdate("UPDATE users SET name = ?, email = ? WHERE id = ?", [$name, $email, $user_id], 'ssi');
        
        if ($result['success']) {
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $message = "Profile updated successfully!";
            
            // Log the action
            executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                [$user_id, "Profile Updated", "Admin updated their profile information", $_SERVER['REMOTE_ADDR']], 'isss');
        } else {
            $error = "Failed to update profile!";
        }
    }
}

// Handle profile photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
        $file_size = $_FILES['profile_photo']['size'];
        
        // Validate file type
        if (in_array($ext, $allowed)) {
            // Validate file size (max 2MB)
            if ($file_size <= 2097152) {
                // Delete old photo if exists
                $old_photo = $user['profile_photo'] ?? '';
                if (!empty($old_photo) && file_exists($profile_dir . $old_photo)) {
                    unlink($profile_dir . $old_photo);
                }
                
                // Generate unique filename
                $new_filename = 'user_' . $user_id . '_' . time() . '.' . $ext;
                $upload_path = $profile_dir . $new_filename;
                
                // Upload file
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
                    // Update database
                    $result = executeUpdate("UPDATE users SET profile_photo = ? WHERE id = ?", [$new_filename, $user_id], 'si');
                    
                    if ($result['success']) {
                        $_SESSION['user_photo'] = $new_filename;
                        $message = "Profile photo updated successfully!";
                        
                        // Log the action
                        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                            [$user_id, "Profile Photo Updated", "Admin updated their profile photo", $_SERVER['REMOTE_ADDR']], 'isss');
                    } else {
                        $error = "Failed to update database!";
                    }
                } else {
                    $error = "Failed to upload file!";
                }
            } else {
                $error = "File too large! Maximum size is 2MB.";
            }
        } else {
            $error = "Invalid file type! Only JPG, JPEG, PNG, GIF are allowed.";
        }
    } else {
        $error = "Please select a file to upload.";
    }
}

// Handle remove photo
if (isset($_GET['remove_photo'])) {
    $old_photo = $user['profile_photo'] ?? '';
    if (!empty($old_photo) && file_exists($profile_dir . $old_photo)) {
        unlink($profile_dir . $old_photo);
    }
    
    $result = executeUpdate("UPDATE users SET profile_photo = NULL WHERE id = ?", [$user_id], 'i');
    
    if ($result['success']) {
        $_SESSION['user_photo'] = null;
        $message = "Profile photo removed successfully!";
        
        // Log the action
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [$user_id, "Profile Photo Removed", "Admin removed their profile photo", $_SERVER['REMOTE_ADDR']], 'isss');
    } else {
        $error = "Failed to remove photo!";
    }
    
    header('Location: profile.php');
    exit();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Verify current password
    $password_query = executeQuery("SELECT password FROM users WHERE id = ?", [$user_id], 'i');
    $user_data = $password_query->fetch_assoc();
    
    if (password_verify($current_password, $user_data['password'])) {
        if ($new_password === $confirm_password) {
            if (strlen($new_password) >= 6) {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $result = executeUpdate("UPDATE users SET password = ? WHERE id = ?", [$hashed_password, $user_id], 'si');
                
                if ($result['success']) {
                    $message = "Password changed successfully!";
                    
                    // Log the action
                    executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                        [$user_id, "Password Changed", "Admin changed their password", $_SERVER['REMOTE_ADDR']], 'isss');
                } else {
                    $error = "Failed to change password!";
                }
            } else {
                $error = "New password must be at least 6 characters long!";
            }
        } else {
            $error = "New passwords do not match!";
        }
    } else {
        $error = "Current password is incorrect!";
    }
}

// Get user statistics
$stats = [];
$stats['farmers_registered'] = getValue("SELECT COUNT(*) FROM farmers WHERE created_by = ?", [$user_id], 'i');
$stats['distributions_made'] = getValue("SELECT COUNT(*) FROM distributions WHERE distributed_by = ?", [$user_id], 'i');
$stats['total_fertilizer'] = getValue("SELECT SUM(quantity) FROM distributions WHERE distributed_by = ?", [$user_id], 'i') ?? 0;
$stats['total_farmers'] = getValue("SELECT COUNT(*) FROM farmers", [], '');
$stats['total_distributions'] = getValue("SELECT COUNT(*) FROM distributions", [], '');
$stats['total_stock'] = getValue("SELECT SUM(quantity) FROM stock", [], '');

// Get recent activity
$recent_activity = executeQuery("
    SELECT * FROM logs 
    WHERE user_id = ? 
    ORDER BY created_at DESC 
    LIMIT 5
", [$user_id], 'i');

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('profile'); ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f6f9;
        }
        
        /* Sidebar Styles */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            z-index: 1000;
            transition: all 0.3s;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
        }
        
        .sidebar-header {
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header i {
            font-size: 48px;
            margin-bottom: 10px;
            color: #667eea;
        }
        
        .sidebar-header h5 {
            margin: 10px 0 5px;
            font-weight: 600;
            color: white;
        }
        
        .sidebar-header .badge {
            font-size: 11px;
            padding: 5px 12px;
            background: #667eea !important;
        }
        
        .sidebar .nav {
            flex-direction: column;
            padding: 0 15px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 15px;
            margin: 4px 0;
            border-radius: 10px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .sidebar .nav-link i {
            width: 22px;
            font-size: 18px;
            text-align: center;
        }
        
        .sidebar .nav-link span {
            flex: 1;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
            color: white;
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .sidebar hr {
            margin: 15px;
            border-color: rgba(255,255,255,0.1);
        }
        
        /* Main Content Styles */
        .main-content {
            margin-left: 260px;
            min-height: 100vh;
            background: #f4f6f9;
            transition: all 0.3s;
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px 25px;
            position: sticky;
            top: 0;
            z-index: 999;
        }
        
        .content-wrapper {
            padding: 25px;
        }
        
        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            position: relative;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            background: white;
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 4px solid white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-avatar i {
            font-size: 60px;
            color: #667eea;
        }
        
        .profile-name {
            color: white;
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .profile-role {
            color: rgba(255,255,255,0.9);
            font-size: 14px;
        }
        
        .profile-body {
            padding: 25px;
        }
        
        /* Photo Upload Area */
        .photo-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .photo-upload-area:hover {
            border-color: #667eea;
            background: #f8f9fa;
        }
        
        .photo-upload-area i {
            font-size: 48px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        /* Stat Boxes */
        .stat-box {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 15px;
            transition: transform 0.3s;
        }
        
        .stat-box:hover {
            transform: translateY(-3px);
        }
        
        .stat-box i {
            font-size: 32px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-box h3 {
            margin: 0;
            font-size: 28px;
            font-weight: bold;
        }
        
        .stat-box p {
            margin: 0;
            color: #666;
            font-size: 13px;
        }
        
        /* Activity Item */
        .activity-item {
            border-left: 3px solid #667eea;
            padding: 12px 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        
        .activity-time {
            font-size: 11px;
            color: #999;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -260px;
                position: fixed;
                z-index: 1050;
            }
            .sidebar.active {
                margin-left: 0;
            }
            .main-content {
                margin-left: 0;
            }
            .menu-toggle {
                display: block !important;
            }
        }
        
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            margin-right: 15px;
        }
        
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        .btn-remove-photo {
            background: #dc3545;
            color: white;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            font-size: 12px;
        }
        
        .btn-remove-photo:hover {
            background: #c82333;
        }
        
        .preview-image {
            max-width: 100px;
            max-height: 100px;
            border-radius: 10px;
            margin-top: 10px;
        }
        
        .file-input-label {
            cursor: pointer;
            display: inline-block;
        }
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-tractor"></i>
            <h5>Fertilizer MS</h5>
            <span class="badge bg-info">Admin Panel</span>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span><?php echo __('dashboard'); ?></span>
            </a>
            <a class="nav-link" href="farmers.php">
                <i class="fas fa-users"></i>
                <span><?php echo __('farmers'); ?></span>
            </a>
            <a class="nav-link" href="distribution.php">
                <i class="fas fa-truck"></i>
                <span><?php echo __('distribution'); ?></span>
            </a>
            <a class="nav-link" href="stock.php">
                <i class="fas fa-boxes"></i>
                <span><?php echo __('stock'); ?></span>
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span><?php echo __('reports'); ?></span>
            </a>
            <a class="nav-link" href="logs.php">
                <i class="fas fa-history"></i>
                <span><?php echo __('logs'); ?></span>
            </a>
            <a class="nav-link" href="backup.php">
                <i class="fas fa-database"></i>
                <span><?php echo __('backup'); ?></span>
            </a>
            <a class="nav-link active" href="profile.php">
                <i class="fas fa-user-circle"></i>
                <span><?php echo __('profile'); ?></span>
            </a>
            <hr>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span><?php echo __('logout'); ?></span>
            </a>
        </nav>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center">
                <button class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h4 class="mb-0"><?php echo __('profile'); ?></h4>
            </div>
            <div>
                <div class="btn-group">
                    <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                    <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                </div>
            </div>
        </div>
        
        <div class="content-wrapper">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <div class="row">
                <!-- Profile Information Column -->
                <div class="col-md-4">
                    <div class="profile-card">
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php 
                                $profile_photo = $user['profile_photo'] ?? '';
                                if (!empty($profile_photo) && file_exists('../assets/profile_images/' . $profile_photo)): 
                                ?>
                                    <img src="../assets/profile_images/<?php echo $profile_photo; ?>" alt="Profile Photo">
                                <?php else: ?>
                                    <i class="fas fa-user-circle"></i>
                                <?php endif; ?>
                            </div>
                            <div class="profile-name"><?php echo htmlspecialchars($user['name']); ?></div>
                            <div class="profile-role">
                                <span class="badge bg-light text-dark"><?php echo ucfirst($user['role']); ?></span>
                            </div>
                        </div>
                        <div class="profile-body">
                            <!-- Photo Upload Form -->
                            <h6 class="mb-3">
                                <i class="fas fa-camera text-primary"></i> Profile Photo
                            </h6>
                            <form method="POST" enctype="multipart/form-data" id="photoUploadForm">
                                <div class="photo-upload-area" onclick="document.getElementById('profile_photo').click()">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <p class="mb-0">Click to upload photo</p>
                                    <small class="text-muted">JPG, PNG, GIF (Max 2MB)</small>
                                    <input type="file" id="profile_photo" name="profile_photo" style="display: none;" accept="image/jpeg,image/png,image/gif" onchange="previewAndUpload()">
                                </div>
                                <input type="hidden" name="upload_photo" value="1">
                                <div id="imagePreview" class="text-center mt-2"></div>
                            </form>
                            
                            <?php if (!empty($profile_photo) && file_exists('../assets/profile_images/' . $profile_photo)): ?>
                            <div class="text-center mt-2">
                                <a href="?remove_photo=1" class="btn-remove-photo" onclick="return confirm('Are you sure you want to remove your profile photo?')">
                                    <i class="fas fa-trash"></i> Remove Photo
                                </a>
                            </div>
                            <?php endif; ?>
                            
                            <hr>
                            
                            <!-- Statistics -->
                            <h6 class="mb-3">
                                <i class="fas fa-chart-line text-success"></i> My Statistics
                            </h6>
                            <div class="row">
                                <div class="col-6">
                                    <div class="stat-box">
                                        <i class="fas fa-users"></i>
                                        <h3><?php echo $stats['farmers_registered']; ?></h3>
                                        <p>Farmers Registered</p>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="stat-box">
                                        <i class="fas fa-truck"></i>
                                        <h3><?php echo $stats['distributions_made']; ?></h3>
                                        <p>Distributions</p>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="stat-box">
                                        <i class="fas fa-weight-hanging"></i>
                                        <h3><?php echo number_format($stats['total_fertilizer'], 2); ?> kg</h3>
                                        <p>Total Fertilizer Distributed</p>
                                    </div>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <!-- System Stats -->
                            <h6 class="mb-3">
                                <i class="fas fa-chart-bar text-info"></i> System Overview
                            </h6>
                            <div class="stat-box">
                                <i class="fas fa-database"></i>
                                <h3><?php echo $stats['total_farmers']; ?></h3>
                                <p>Total Farmers in System</p>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-tasks"></i>
                                <h3><?php echo $stats['total_distributions']; ?></h3>
                                <p>Total Distributions</p>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-boxes"></i>
                                <h3><?php echo number_format($stats['total_stock'], 0); ?> kg</h3>
                                <p>Total Stock Available</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Edit Profile Column -->
                <div class="col-md-8">
                    <div class="profile-card">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-edit text-primary"></i> Edit Profile Information
                            </h5>
                        </div>
                        <div class="profile-body">
                            <form method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('full_name'); ?> *</label>
                                    <input type="text" class="form-control" name="name" 
                                           value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label"><?php echo __('email'); ?> *</label>
                                    <input type="email" class="form-control" name="email" 
                                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" class="form-control" value="<?php echo ucfirst($user['role']); ?>" disabled>
                                    <small class="text-muted">Role cannot be changed</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Member Since</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('F d, Y h:i A', strtotime($user['created_at'])); ?>" disabled>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Last Login</label>
                                    <input type="text" class="form-control" 
                                           value="<?php echo date('F d, Y h:i A'); ?>" disabled>
                                </div>
                                <button type="submit" name="update_profile" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Change Password Section -->
                    <div class="profile-card mt-4">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-key text-warning"></i> Change Password
                            </h5>
                        </div>
                        <div class="profile-body">
                            <form method="POST" action="" onsubmit="return validatePassword()">
                                <div class="mb-3">
                                    <label class="form-label">Current Password *</label>
                                    <input type="password" class="form-control" name="current_password" id="current_password" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">New Password *</label>
                                    <input type="password" class="form-control" name="new_password" id="new_password" required>
                                    <small class="text-muted">Minimum 6 characters, include letters and numbers</small>
                                    <div class="progress mt-2" style="height: 5px;">
                                        <div id="passwordStrength" class="progress-bar" role="progressbar" style="width: 0%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Confirm New Password *</label>
                                    <input type="password" class="form-control" name="confirm_password" id="confirm_password" required>
                                    <small id="passwordMatch" class="text-muted"></small>
                                </div>
                                <button type="submit" name="change_password" class="btn btn-warning">
                                    <i class="fas fa-key"></i> Change Password
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="profile-card mt-4">
                        <div class="card-header bg-white p-3 border-bottom">
                            <h5 class="mb-0">
                                <i class="fas fa-history text-info"></i> Recent Activity
                            </h5>
                        </div>
                        <div class="profile-body">
                            <?php if($recent_activity && $recent_activity->num_rows > 0): ?>
                                <?php while($activity = $recent_activity->fetch_assoc()): ?>
                                <div class="activity-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                                            <p class="mb-0 text-muted small"><?php echo htmlspecialchars($activity['details']); ?></p>
                                        </div>
                                        <div class="activity-time">
                                            <i class="far fa-clock"></i> <?php echo date('d/m/Y H:i', strtotime($activity['created_at'])); ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-2"></i>
                                    <p>No recent activity found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
            });
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            });
        }
        
        // Password validation
        function validatePassword() {
            var newPassword = document.getElementById('new_password').value;
            var confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword.length < 6) {
                alert('New password must be at least 6 characters long!');
                return false;
            }
            
            if (newPassword !== confirmPassword) {
                alert('New passwords do not match!');
                return false;
            }
            
            return true;
        }
        
        // Password strength meter
        document.getElementById('new_password')?.addEventListener('input', function() {
            var password = this.value;
            var strength = 0;
            var strengthBar = document.getElementById('passwordStrength');
            
            if (password.length >= 6) strength += 25;
            if (password.match(/[a-z]+/)) strength += 25;
            if (password.match(/[A-Z]+/)) strength += 25;
            if (password.match(/[0-9]+/)) strength += 25;
            
            strengthBar.style.width = strength + '%';
            
            if (strength < 50) {
                strengthBar.className = 'progress-bar bg-danger';
            } else if (strength < 75) {
                strengthBar.className = 'progress-bar bg-warning';
            } else {
                strengthBar.className = 'progress-bar bg-success';
            }
        });
        
        // Password match checker
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            var password = document.getElementById('new_password').value;
            var confirm = this.value;
            var matchMsg = document.getElementById('passwordMatch');
            
            if (password === confirm && password.length > 0) {
                matchMsg.innerHTML = '<i class="fas fa-check-circle text-success"></i> Passwords match!';
                matchMsg.style.color = '#28a745';
            } else if (confirm.length > 0) {
                matchMsg.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Passwords do not match!';
                matchMsg.style.color = '#dc3545';
            } else {
                matchMsg.innerHTML = '';
            }
        });
        
        // Photo preview and upload
        function previewAndUpload() {
            var fileInput = document.getElementById('profile_photo');
            var file = fileInput.files[0];
            var preview = document.getElementById('imagePreview');
            
            if (file) {
                var reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = '<img src="' + e.target.result + '" class="preview-image" alt="Preview">';
                };
                reader.readAsDataURL(file);
                
                // Auto-submit the form
                document.getElementById('photoUploadForm').submit();
            }
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    </script>
</body>
</html>
<?php $conn->close(); ?>