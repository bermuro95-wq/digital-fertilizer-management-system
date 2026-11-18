<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireLogin();

if (!isOfficer()) {
    header('Location: ../index.php');
    exit();
}

$conn = getConnection();

// Get statistics
$result = $conn->query("SELECT COUNT(*) as total FROM farmers");
$total_farmers = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'verified'");
$verified_farmers = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'pending'");
$pending_farmers = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM farmers WHERE created_by = " . getCurrentUserId());
$my_farmers = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT SUM(quantity) as total FROM distributions WHERE distributed_by = " . getCurrentUserId());
$my_distribution = $result->fetch_assoc()['total'] ?? 0;

$result = $conn->query("SELECT COUNT(*) as total FROM distributions WHERE distributed_by = " . getCurrentUserId() . " AND DATE(distribution_date) = CURDATE()");
$today_distributions = $result->fetch_assoc()['total'];

// Recent farmers added
$recentFarmers = $conn->query("SELECT * FROM farmers ORDER BY created_at DESC LIMIT 5");

// Recent distributions
$recentDistributions = $conn->query("
    SELECT d.*, f.full_name, f.farmer_id, ft.name as fertilizer_name 
    FROM distributions d 
    JOIN farmers f ON d.farmer_id = f.id 
    JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id 
    WHERE d.distributed_by = " . getCurrentUserId() . "
    ORDER BY d.created_at DESC LIMIT 5
");

// Get current page name
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo __('dashboard'); ?> - Officer Panel | Fertilizer Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* ========================================
           GLOBAL STYLES
           ======================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', 'Roboto', 'Tahoma', Geneva, Verdana, sans-serif;
            background: #f4f6f9;
            overflow-x: hidden;
        }
        
        /* ========================================
           SIDEBAR STYLES
           ======================================== */
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #2c3e50 0%, #1a252f 100%);
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 280px;
            z-index: 1000;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 2px 0 20px rgba(0,0,0,0.15);
            overflow-y: auto;
        }
        
        /* Custom scrollbar for sidebar */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: #667eea;
            border-radius: 5px;
        }
        
        .sidebar-header {
            padding: 30px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar-header i {
            font-size: 56px;
            margin-bottom: 12px;
            color: #667eea;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }
        
        .sidebar-header h5 {
            margin: 12px 0 8px;
            font-weight: 700;
            color: white;
            font-size: 1.3rem;
            letter-spacing: 1px;
        }
        
        .sidebar-header .badge {
            font-size: 11px;
            padding: 5px 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-radius: 20px;
            font-weight: 500;
            letter-spacing: 0.5px;
        }
        
        .sidebar .nav {
            flex-direction: column;
            padding: 0 15px;
        }
        
        .sidebar .nav-link {
            color: rgba(255,255,255,0.85);
            padding: 12px 18px;
            margin: 6px 0;
            border-radius: 12px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 14px;
            font-weight: 500;
            font-size: 15px;
            position: relative;
            overflow: hidden;
        }
        
        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 3px;
            background: #667eea;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .sidebar .nav-link:hover::before {
            transform: scaleY(1);
        }
        
        .sidebar .nav-link i {
            width: 24px;
            font-size: 18px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .sidebar .nav-link span {
            flex: 1;
        }
        
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
            transform: translateX(5px);
            color: white;
        }
        
        .sidebar .nav-link:hover i {
            transform: scale(1.1);
        }
        
        .sidebar .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .sidebar .nav-link.active i {
            color: white;
        }
        
        .sidebar hr {
            margin: 20px 15px;
            border-color: rgba(255,255,255,0.1);
        }
        
        /* ========================================
           MAIN CONTENT STYLES
           ======================================== */
        .main-content {
            margin-left: 280px;
            min-height: 100vh;
            background: #f4f6f9;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .top-navbar {
            background: white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            padding: 15px 25px;
            position: sticky;
            top: 0;
            z-index: 999;
            backdrop-filter: blur(10px);
        }
        
        .content-wrapper {
            padding: 25px;
        }
        
        /* ========================================
           STATISTICS CARDS
           ======================================== */
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 25px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .stat-card-primary::before {
            background: linear-gradient(90deg, #667eea, #764ba2);
        }
        
        .stat-card-success::before {
            background: linear-gradient(90deg, #28a745, #20c997);
        }
        
        .stat-card-info::before {
            background: linear-gradient(90deg, #17a2b8, #0dcaf0);
        }
        
        .stat-card-warning::before {
            background: linear-gradient(90deg, #ffc107, #fd7e14);
        }
        
        .stat-card-danger::before {
            background: linear-gradient(90deg, #dc3545, #f86c6b);
        }
        
        .stat-icon {
            font-size: 52px;
            opacity: 0.15;
            position: absolute;
            right: 20px;
            top: 20px;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover .stat-icon {
            opacity: 0.25;
            transform: scale(1.1);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 800;
            margin: 12px 0 5px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .stat-subtitle {
            font-size: 12px;
            color: #adb5bd;
        }
        
        /* ========================================
           TABLE STYLES
           ======================================== */
        .table-container {
            background: white;
            border-radius: 16px;
            padding: 0;
            box-shadow: 0 2px 12px rgba(0,0,0,0.05);
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
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
            color: #495057;
        }
        
        .table tbody tr {
            transition: background 0.2s ease;
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
        
        /* Welcome Section */
        .welcome-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.3);
        }
        
        /* Quick Actions */
        .quick-action-btn {
            padding: 15px;
            border-radius: 12px;
            transition: all 0.3s ease;
            text-align: center;
        }
        
        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        
        /* System Info */
        .system-info {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 16px;
            padding: 20px;
        }
        
        .system-info-item {
            padding: 12px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .system-info-item:last-child {
            border-bottom: none;
        }
        
        /* Footer */
        .footer {
            background: white;
            padding: 20px 25px;
            text-align: center;
            color: #6c757d;
            font-size: 13px;
            margin-top: 30px;
            border-top: 1px solid #e9ecef;
        }
        
        /* ========================================
           BUTTON STYLES
           ======================================== */
        .btn-primary-custom {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 8px 20px;
            border-radius: 25px;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        /* ========================================
           RESPONSIVE DESIGN
           ======================================== */
        @media (max-width: 992px) {
            .sidebar {
                width: 260px;
            }
            .main-content {
                margin-left: 260px;
            }
            .stat-number {
                font-size: 28px;
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                margin-left: -280px;
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
            
            .stat-card {
                margin-bottom: 15px;
            }
            
            .content-wrapper {
                padding: 15px;
            }
            
            .welcome-section {
                padding: 20px;
            }
            
            .welcome-section h3 {
                font-size: 1.2rem;
            }
        }
        
        @media (max-width: 576px) {
            .stat-number {
                font-size: 24px;
            }
            .stat-icon {
                font-size: 40px;
            }
            .table-container-header h5 {
                font-size: 1rem;
            }
        }
        
        /* Menu Toggle Button */
        .menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #333;
            margin-right: 15px;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.3s ease;
        }
        
        .menu-toggle:hover {
            background: rgba(0,0,0,0.05);
        }
        
        /* Sidebar Overlay for Mobile */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            backdrop-filter: blur(2px);
        }
        
        .sidebar-overlay.active {
            display: block;
        }
        
        /* Animation Classes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .animate-fadeInUp {
            animation: fadeInUp 0.5s ease forwards;
        }
        
        /* Loading Spinner */
        .spinner-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: white;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Utility Classes */
        .cursor-pointer {
            cursor: pointer;
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="spinner-wrapper" id="spinner">
        <div class="spinner"></div>
    </div>
    
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <i class="fas fa-tractor"></i>
            <h5>Fertilizer MS</h5>
            <span class="badge bg-info">Officer Panel</span>
        </div>
        
        <nav class="nav flex-column">
            <a class="nav-link <?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i>
                <span><?php echo __('dashboard'); ?></span>
            </a>
            <a class="nav-link <?php echo $current_page == 'farmers.php' ? 'active' : ''; ?>" href="farmers.php">
                <i class="fas fa-users"></i>
                <span><?php echo __('farmers'); ?></span>
            </a>
            <a class="nav-link <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>" href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span><?php echo __('reports'); ?></span>
            </a>
            <a class="nav-link <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>" href="profile.php">
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
                <h4 class="mb-0 fw-bold"><?php echo __('dashboard'); ?></h4>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="language-switcher">
                    <a href="?lang=en" class="btn btn-sm btn-outline-primary rounded-pill">EN</a>
                    <a href="?lang=am" class="btn btn-sm btn-outline-primary rounded-pill">አማ</a>
                </div>
                <div class="dropdown">
                    <button class="btn btn-link text-dark text-decoration-none dropdown-toggle d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle fa-2x text-primary"></i>
                        <span class="d-none d-md-inline"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item py-2" href="profile.php">
                            <i class="fas fa-user me-2"></i> <?php echo __('profile'); ?>
                        </a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item py-2 text-danger" href="../logout.php">
                            <i class="fas fa-sign-out-alt me-2"></i> <?php echo __('logout'); ?>
                        </a></li>
                    </ul>
                </div>
            </div>
        </div>
        
        <div class="content-wrapper">
            <!-- Welcome Section -->
            <div class="welcome-section animate-fadeInUp">
                <div class="d-flex justify-content-between align-items-center flex-wrap">
                    <div>
                        <h3 class="mb-2 fw-bold">Welcome back, <?php echo htmlspecialchars($_SESSION['user_name']); ?>!</h3>
                        <p class="mb-0 opacity-75">Here's what's happening with your fertilizer distribution today.</p>
                    </div>
                    <div class="text-center mt-3 mt-sm-0">
                        <i class="fas fa-smile-wink fa-4x opacity-50"></i>
                        <small class="d-block mt-1"><?php echo date('l, F d, Y'); ?></small>
                    </div>
                </div>
            </div>
            
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card stat-card-primary">
                        <i class="fas fa-users stat-icon"></i>
                        <div class="stat-number"><?php echo number_format($total_farmers); ?></div>
                        <div class="stat-label"><?php echo __('total_farmers'); ?></div>
                        <div class="stat-subtitle">System wide</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card stat-card-success">
                        <i class="fas fa-check-circle stat-icon"></i>
                        <div class="stat-number"><?php echo number_format($verified_farmers); ?></div>
                        <div class="stat-label"><?php echo __('verified_farmers'); ?></div>
                        <div class="stat-subtitle">Approved farmers</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card stat-card-warning">
                        <i class="fas fa-clock stat-icon"></i>
                        <div class="stat-number"><?php echo number_format($pending_farmers); ?></div>
                        <div class="stat-label"><?php echo __('pending_farmers'); ?></div>
                        <div class="stat-subtitle">Awaiting verification</div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="stat-card stat-card-info">
                        <i class="fas fa-truck stat-icon"></i>
                        <div class="stat-number"><?php echo number_format($my_distribution, 2); ?> kg</div>
                        <div class="stat-label">My Distributions</div>
                        <div class="stat-subtitle">Today: <?php echo $today_distributions; ?> deliveries</div>
                    </div>
                </div>
            </div>
            
            <div class="row mt-3">
                <div class="col-md-6">
                    <div class="stat-card stat-card-primary">
                        <i class="fas fa-user-plus stat-icon"></i>
                        <div class="stat-number"><?php echo $my_farmers; ?></div>
                        <div class="stat-label">My Registrations</div>
                        <div class="stat-subtitle">Farmers I registered</div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="stat-card stat-card-success">
                        <i class="fas fa-tasks stat-icon"></i>
                        <div class="stat-number"><?php echo $recentDistributions->num_rows; ?></div>
                        <div class="stat-label">Recent Activities</div>
                        <div class="stat-subtitle">Last 5 distributions</div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Farmers Table -->
            <div class="table-container mt-4">
                <div class="table-container-header d-flex justify-content-between align-items-center flex-wrap">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-users text-primary me-2"></i> Recently Registered Farmers
                    </h5>
                    <a href="farmers.php" class="btn btn-primary-custom btn-sm">
                        View All <i class="fas fa-arrow-right ms-1"></i>
                    </a>
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
                                <th><?php echo __('actions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recentFarmers && $recentFarmers->num_rows > 0): ?>
                                <?php while($farmer = $recentFarmers->fetch_assoc()): ?>
                                <tr>
                                    <td><strong>#<?php echo $farmer['id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($farmer['full_name']); ?></td>
                                    <td><span class="text-muted"><?php echo htmlspecialchars($farmer['farmer_id']); ?></span></td>
                                    <td><?php echo htmlspecialchars($farmer['region']); ?></td>
                                    <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
                                    <td>
                                        <span class="badge-<?php echo $farmer['verification_status']; ?>">
                                            <?php echo __($farmer['verification_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="view_farmer.php?id=<?php echo $farmer['id']; ?>" class="btn btn-sm btn-info text-white" title="View">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <i class="fas fa-inbox fa-4x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">No farmers registered yet</p>
                                        <a href="farmers.php?action=register" class="btn btn-primary mt-3">Register First Farmer</a>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Recent Distributions -->
            <div class="table-container mt-4">
                <div class="table-container-header">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-truck text-success me-2"></i> My Recent Distributions
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Farmer</th>
                                <th>Farmer ID</th>
                                <th>Fertilizer</th>
                                <th>Quantity</th>
                                <th>Season</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($recentDistributions && $recentDistributions->num_rows > 0): ?>
                                <?php while($dist = $recentDistributions->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y', strtotime($dist['distribution_date'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($dist['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($dist['farmer_id']); ?></td>
                                    <td><?php echo htmlspecialchars($dist['fertilizer_name']); ?></td>
                                    <td><?php echo number_format($dist['quantity'], 2); ?> kg</td>
                                    <td><?php echo htmlspecialchars($dist['season']); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <i class="fas fa-truck fa-4x text-muted mb-3 d-block"></i>
                                        <p class="text-muted mb-0">No distributions recorded yet</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Quick Actions & System Info -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="table-container-header">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-bolt text-warning me-2"></i> Quick Actions
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="row g-3">
                                <div class="col-12">
                                    <a href="farmers.php?action=register" class="btn btn-primary w-100 quick-action-btn">
                                        <i class="fas fa-user-plus me-2"></i> Register New Farmer
                                    </a>
                                </div>
                                <div class="col-12">
                                    <a href="reports.php" class="btn btn-success w-100 quick-action-btn">
                                        <i class="fas fa-chart-bar me-2"></i> Generate Reports
                                    </a>
                                </div>
                                <div class="col-12">
                                    <a href="profile.php" class="btn btn-info w-100 quick-action-btn">
                                        <i class="fas fa-user-edit me-2"></i> Update My Profile
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="table-container">
                        <div class="table-container-header">
                            <h5 class="mb-0 fw-bold">
                                <i class="fas fa-info-circle text-info me-2"></i> System Information
                            </h5>
                        </div>
                        <div class="p-4">
                            <div class="system-info">
                                <div class="system-info-item d-flex justify-content-between">
                                    <span><i class="fas fa-check-circle text-success me-2"></i> System Status:</span>
                                    <span class="fw-bold text-success">Operational</span>
                                </div>
                                <div class="system-info-item d-flex justify-content-between">
                                    <span><i class="fas fa-calendar-alt text-primary me-2"></i> Today's Date:</span>
                                    <span class="fw-bold"><?php echo date('F d, Y'); ?></span>
                                </div>
                                <div class="system-info-item d-flex justify-content-between">
                                    <span><i class="fas fa-clock text-info me-2"></i> Server Time:</span>
                                    <span class="fw-bold"><?php echo date('h:i A'); ?></span>
                                </div>
                                <div class="system-info-item d-flex justify-content-between">
                                    <span><i class="fas fa-user-shield text-warning me-2"></i> Your Role:</span>
                                    <span class="fw-bold text-uppercase"><?php echo ucfirst(getCurrentUserRole()); ?></span>
                                </div>
                                <div class="system-info-item d-flex justify-content-between">
                                    <span><i class="fas fa-database text-secondary me-2"></i> PHP Version:</span>
                                    <span class="fw-bold"><?php echo phpversion(); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> Digital Fertilizer Management System. All rights reserved.</p>
            <p class="mb-0 mt-2 small">
                <i class="fas fa-shield-alt text-primary"></i> Secure System | 
                <i class="fas fa-globe text-info"></i> Bilingual Support (EN/አማ)
            </p>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Hide loading spinner
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('spinner').style.display = 'none';
            }, 500);
        });
        
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');
        
        if (menuToggle) {
            menuToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebarOverlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            });
        }
        
        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function() {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            });
        }
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
                document.body.style.overflow = '';
            }
        });
        
        // Add animation to stat cards
        const statCards = document.querySelectorAll('.stat-card');
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);
        
        statCards.forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(20px)';
            card.style.transition = 'all 0.5s ease';
            observer.observe(card);
        });
        
        // Logout confirmation
        const logoutLinks = document.querySelectorAll('a[href*="logout.php"]');
        logoutLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>