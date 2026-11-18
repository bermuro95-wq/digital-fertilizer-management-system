<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Handle farmer registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'register') {
        $farmer_id = $_POST['farmer_id'];
        $full_name = $_POST['full_name'];
        $region = $_POST['region'];
        $district = $_POST['district'];
        $kebele = $_POST['kebele'];
        $farm_size = $_POST['farm_size'];
        $crop_type = $_POST['crop_type'];
        $harvest_season = $_POST['harvest_season'];
        $phone = $_POST['phone'];
        
        // Handle file uploads
        $id_proof = '';
        $farm_photo = '';
        
        $upload_dir = '../assets/uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
            $ext = strtolower(pathinfo($_FILES['id_proof']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['id_proof']['size'] <= 5242880) {
                $id_proof = time() . '_id.' . $ext;
                move_uploaded_file($_FILES['id_proof']['tmp_name'], $upload_dir . $id_proof);
            }
        }
        
        if (isset($_FILES['farm_photo']) && $_FILES['farm_photo']['error'] === UPLOAD_ERR_OK) {
            $allowed = ['jpg', 'jpeg', 'png'];
            $ext = strtolower(pathinfo($_FILES['farm_photo']['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowed) && $_FILES['farm_photo']['size'] <= 5242880) {
                $farm_photo = time() . '_farm.' . $ext;
                move_uploaded_file($_FILES['farm_photo']['tmp_name'], $upload_dir . $farm_photo);
            }
        }
        
        $sql = "INSERT INTO farmers (farmer_id, full_name, region, district, kebele, farm_size, crop_type, harvest_season, phone, id_proof, farm_photo, verification_status, created_by) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        
        $result = executeUpdate($sql, [
            $farmer_id, $full_name, $region, $district, $kebele, $farm_size, 
            $crop_type, $harvest_season, $phone, $id_proof, $farm_photo, getCurrentUserId()
        ], 'sssssdsssssi');
        
        if ($result['success']) {
            $message = __('farmer_registered');
            
            // Log the action
            $logSql = "INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            executeUpdate($logSql, [getCurrentUserId(), "Registered farmer: $farmer_id", $full_name, $_SERVER['REMOTE_ADDR']], 'isss');
        } else {
            $error = "Registration failed!";
        }
    } elseif ($_POST['action'] === 'verify') {
        $farmer_id = $_POST['farmer_id'];
        $status = $_POST['status'];
        
        $sql = "UPDATE farmers SET verification_status = ? WHERE id = ?";
        $result = executeUpdate($sql, [$status, $farmer_id], 'si');
        
        if ($result['success']) {
            $message = "Farmer verification status updated!";
            
            // Log the action
            $logSql = "INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            executeUpdate($logSql, [getCurrentUserId(), "Updated verification status", "Farmer ID: $farmer_id, New status: $status", $_SERVER['REMOTE_ADDR']], 'isss');
        }
    } elseif ($_POST['action'] === 'delete') {
        $farmer_id = $_POST['farmer_id'];
        
        // Get farmer name for log
        $farmer_info = executeQuery("SELECT full_name, farmer_id FROM farmers WHERE id = ?", [$farmer_id], 'i');
        $farmer_data = $farmer_info->fetch_assoc();
        
        $sql = "DELETE FROM farmers WHERE id = ?";
        $result = executeUpdate($sql, [$farmer_id], 'i');
        
        if ($result['success']) {
            $message = __('farmer_deleted');
            
            // Log the action
            $logSql = "INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)";
            executeUpdate($logSql, [getCurrentUserId(), "Deleted farmer", "Deleted farmer: " . $farmer_data['farmer_id'] . " - " . $farmer_data['full_name'], $_SERVER['REMOTE_ADDR']], 'isss');
        }
    }
}

// Get all farmers with search
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

$sql = "SELECT * FROM farmers WHERE 1=1";
$params = [];
$types = "";

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR farmer_id LIKE ? OR phone LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param]);
    $types .= "sss";
}

if (!empty($status_filter)) {
    $sql .= " AND verification_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";
$farmers = executeQuery($sql, $params, $types);

// Get fertilizer types for dropdown
$fertilizer_types = executeQuery("SELECT * FROM fertilizer_types ORDER BY name");

// Get statistics for dashboard
$total_farmers = executeQuery("SELECT COUNT(*) as total FROM farmers")->fetch_assoc()['total'];
$verified_farmers = executeQuery("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'verified'")->fetch_assoc()['total'];
$pending_farmers = executeQuery("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'pending'")->fetch_assoc()['total'];
$rejected_farmers = executeQuery("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'rejected'")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('farmers'); ?> - Admin</title>
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
        
        /* Stat Cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stat-icon {
            font-size: 48px;
            opacity: 0.2;
            position: absolute;
            right: 15px;
            top: 15px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            margin: 10px 0 5px;
        }
        
        .stat-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Table Styles */
        .table-container {
            background: white;
            border-radius: 12px;
            padding: 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }
        
        .table-container-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e9ecef;
            background: white;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #e9ecef;
            color: #495057;
            font-weight: 600;
            padding: 15px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
        }
        
        /* Badge Styles */
        .badge-pending {
            background: #fff3cd;
            color: #856404;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-verified {
            background: #d4edda;
            color: #155724;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Button Styles */
        .btn-register {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* Modal Styles */
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-close-white {
            filter: brightness(0) invert(1);
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
        
        /* Verification Buttons */
        .btn-verify {
            padding: 4px 8px;
            font-size: 12px;
            margin: 2px;
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
            <a class="nav-link active" href="farmers.php">
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
                <h4 class="mb-0"><?php echo __('farmers'); ?></h4>
            </div>
            <div>
                <div class="btn-group">
                    <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                    <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                </div>
                <button class="btn btn-primary btn-register ms-3" data-bs-toggle="modal" data-bs-target="#registerModal">
                    <i class="fas fa-plus"></i> <?php echo __('register_farmer'); ?>
                </button>
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
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-number"><?php echo $total_farmers; ?></div>
                        <div class="stat-label">Total Farmers</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-check-circle stat-icon" style="color: #28a745;"></i>
                        <div class="stat-number" style="color: #28a745;"><?php echo $verified_farmers; ?></div>
                        <div class="stat-label">Verified</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-clock stat-icon" style="color: #ffc107;"></i>
                        <div class="stat-number" style="color: #ffc107;"><?php echo $pending_farmers; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <i class="fas fa-times-circle stat-icon" style="color: #dc3545;"></i>
                        <div class="stat-number" style="color: #dc3545;"><?php echo $rejected_farmers; ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <input type="text" class="form-control" name="search" placeholder="<?php echo __('search'); ?> by name, ID or phone..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter == 'pending' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                <option value="verified" <?php echo $status_filter == 'verified' ? 'selected' : ''; ?>><?php echo __('verified'); ?></option>
                                <option value="rejected" <?php echo $status_filter == 'rejected' ? 'selected' : ''; ?>><?php echo __('rejected'); ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> <?php echo __('search'); ?>
                            </button>
                            <a href="farmers.php" class="btn btn-secondary">
                                <i class="fas fa-sync"></i> Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Farmers Table -->
            <div class="table-container">
                <div class="table-container-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-list text-primary"></i> Farmers List
                    </h5>
                    <span class="badge bg-secondary">Total: <?php echo $farmers->num_rows; ?> farmers</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th><?php echo __('full_name'); ?></th>
                                <th><?php echo __('farmer_id'); ?></th>
                                <th><?php echo __('region'); ?></th>
                                <th><?php echo __('phone'); ?></th>
                                <th><?php echo __('verification_status'); ?></th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($farmers && $farmers->num_rows > 0): ?>
                                <?php while($farmer = $farmers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $farmer['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($farmer['full_name']); ?></td>
                                    <td><span class="text-muted"><?php echo htmlspecialchars($farmer['farmer_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($farmer['region']); ?></td>
                                    <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
                                    <td>
                                        <span class="badge-<?php echo $farmer['verification_status']; ?>">
                                            <?php echo ucfirst($farmer['verification_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <!-- View Button -->
                                        <a href="view_farmer.php?id=<?php echo $farmer['id']; ?>" class="btn btn-sm btn-info" title="View Farmer">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        
                                        <!-- Edit Button -->
                                        <a href="edit_farmer.php?id=<?php echo $farmer['id']; ?>" class="btn btn-sm btn-primary" title="Edit Farmer">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        
                                        <!-- Verify/Reject Buttons (Admin only) -->
                                        <?php if (isAdmin()): ?>
                                            <?php if ($farmer['verification_status'] == 'pending'): ?>
                                                <button class="btn btn-sm btn-success btn-verify" onclick="verifyFarmer(<?php echo $farmer['id']; ?>, 'verified')" title="Verify Farmer">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger btn-verify" onclick="verifyFarmer(<?php echo $farmer['id']; ?>, 'rejected')" title="Reject Farmer">
                                                    <i class="fas fa-times-circle"></i>
                                                </button>
                                            <?php elseif ($farmer['verification_status'] == 'verified'): ?>
                                                <button class="btn btn-sm btn-warning btn-verify" onclick="verifyFarmer(<?php echo $farmer['id']; ?>, 'pending')" title="Reset to Pending">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            <?php elseif ($farmer['verification_status'] == 'rejected'): ?>
                                                <button class="btn btn-sm btn-success btn-verify" onclick="verifyFarmer(<?php echo $farmer['id']; ?>, 'verified')" title="Verify Farmer">
                                                    <i class="fas fa-check-circle"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button -->
                                            <button class="btn btn-sm btn-danger" onclick="deleteFarmer(<?php echo $farmer['id']; ?>)" title="Delete Farmer">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-inbox fa-4x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">No farmers found</p>
                                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#registerModal">
                                            <i class="fas fa-plus"></i> Register First Farmer
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-user-plus"></i> <?php echo __('register_farmer'); ?>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="register">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('full_name'); ?> *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('farmer_id'); ?> *</label>
                                <input type="text" class="form-control" name="farmer_id" placeholder="e.g., FARM001" required>
                                <small class="text-muted">Unique identifier for the farmer</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('region'); ?> *</label>
                                <input type="text" class="form-control" name="region" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('district'); ?> *</label>
                                <input type="text" class="form-control" name="district" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('kebele'); ?> *</label>
                                <input type="text" class="form-control" name="kebele" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('farm_size'); ?> *</label>
                                <input type="number" step="0.01" class="form-control" name="farm_size" placeholder="in hectares" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('crop_type'); ?> *</label>
                                <input type="text" class="form-control" name="crop_type" placeholder="e.g., Teff, Maize, Wheat" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('harvest_season'); ?> *</label>
                                <select class="form-select" name="harvest_season" required>
                                    <option value="">Select Season</option>
                                    <option>Meher (Autumn)</option>
                                    <option>Belg (Spring)</option>
                                    <option>Bega (Winter)</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('phone'); ?> *</label>
                                <input type="tel" class="form-control" name="phone" placeholder="e.g., 0912345678" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('upload_id'); ?></label>
                                <input type="file" class="form-control" name="id_proof" accept=".jpg,.jpeg,.png,.pdf">
                                <small class="text-muted">Max 5MB (JPG, PNG, PDF)</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label"><?php echo __('upload_photo'); ?></label>
                                <input type="file" class="form-control" name="farm_photo" accept=".jpg,.jpeg,.png">
                                <small class="text-muted">Max 5MB (JPG, PNG)</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                        <button type="submit" class="btn btn-primary"><?php echo __('submit'); ?></button>
                    </div>
                </form>
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
        
        // Verify Farmer Function
        function verifyFarmer(id, status) {
            let message = '';
            if (status === 'verified') {
                message = 'Are you sure you want to VERIFY this farmer?';
            } else if (status === 'rejected') {
                message = 'Are you sure you want to REJECT this farmer?';
            } else if (status === 'pending') {
                message = 'Are you sure you want to set this farmer back to PENDING?';
            }
            
            if (confirm(message)) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="verify">' +
                    '<input type="hidden" name="farmer_id" value="' + id + '">' +
                    '<input type="hidden" name="status" value="' + status + '">' +
                    '</form>').appendTo('body').submit();
            }
        }
        
        // Delete Farmer Function
        function deleteFarmer(id) {
            if (confirm('⚠️ WARNING: This will permanently delete the farmer and all related records! Are you absolutely sure?')) {
                $('<form method="POST">' +
                    '<input type="hidden" name="action" value="delete">' +
                    '<input type="hidden" name="farmer_id" value="' + id + '">' +
                    '</form>').appendTo('body').submit();
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