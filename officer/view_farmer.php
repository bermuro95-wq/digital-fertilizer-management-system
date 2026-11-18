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

// Check if farmer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: farmers.php');
    exit();
}

$farmer_id = $_GET['id'];

// Get farmer data with creator info
$farmer_query = executeQuery("
    SELECT f.*, u.name as created_by_name 
    FROM farmers f 
    LEFT JOIN users u ON f.created_by = u.id 
    WHERE f.id = ?
", [$farmer_id], 'i');

if ($farmer_query->num_rows == 0) {
    header('Location: farmers.php');
    exit();
}
$farmer = $farmer_query->fetch_assoc();

// Get distribution history
$distributions = executeQuery("
    SELECT d.*, ft.name as fertilizer_name, u.name as distributed_by_name
    FROM distributions d
    JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id
    LEFT JOIN users u ON d.distributed_by = u.id
    WHERE d.farmer_id = ?
    ORDER BY d.distribution_date DESC
", [$farmer_id], 'i');

// Get total fertilizer received
$total_received = executeQuery("
    SELECT SUM(quantity) as total 
    FROM distributions 
    WHERE farmer_id = ?
", [$farmer_id], 'i');
$total = $total_received->fetch_assoc();

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Farmer - Officer</title>
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
            color: #28a745;
        }
        
        .sidebar-header h5 {
            margin: 10px 0 5px;
            font-weight: 600;
            color: white;
        }
        
        .sidebar-header .badge {
            font-size: 11px;
            padding: 5px 12px;
            background: #28a745 !important;
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
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(40, 167, 69, 0.3);
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
        
        /* Info Cards */
        .info-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
            width: 160px;
            display: inline-block;
        }
        
        .info-value {
            color: #333;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .photo-container {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .photo-container img {
            max-width: 100%;
            max-height: 200px;
            border-radius: 10px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #28a745;
            color: #28a745;
        }
        
        .btn-back {
            background: #6c757d;
            color: white;
        }
        
        .btn-back:hover {
            background: #5a6268;
            color: white;
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
            .info-label {
                width: 100%;
                display: block;
                margin-bottom: 5px;
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
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .table th {
            background: #f8f9fa;
            font-weight: 600;
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
            <span class="badge bg-info">Officer Panel</span>
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
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i>
                <span><?php echo __('reports'); ?></span>
            </a>
            <a class="nav-link" href="profile.php">
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
                <h4 class="mb-0">Farmer Details</h4>
            </div>
            <div>
                <div class="btn-group">
                    <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                    <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                </div>
                <a href="farmers.php" class="btn btn-back btn-sm ms-3">
                    <i class="fas fa-arrow-left"></i> Back to Farmers
                </a>
            </div>
        </div>
        
        <div class="content-wrapper">
            <div class="row">
                <div class="col-md-8">
                    <!-- Personal Information -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-user text-primary"></i> Personal Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Farmer ID:</span>
                                <span class="info-value"><strong><?php echo htmlspecialchars($farmer['farmer_id']); ?></strong></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['full_name']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Phone Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['phone']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Registration Date:</span>
                                <span class="info-value"><?php echo date('d/m/Y h:i A', strtotime($farmer['created_at'])); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Registered By:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['created_by_name'] ?? 'System'); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Verification Status:</span>
                                <span class="status-badge status-<?php echo $farmer['verification_status']; ?>">
                                    <i class="fas <?php echo $farmer['verification_status'] == 'verified' ? 'fa-check-circle' : ($farmer['verification_status'] == 'pending' ? 'fa-clock' : 'fa-times-circle'); ?>"></i>
                                    <?php echo ucfirst($farmer['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Location Information -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-map-marker-alt text-success"></i> Location Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Region:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['region']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">District:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['district']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Kebele/Village:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['kebele']); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Farm Information -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-tractor text-warning"></i> Farm Information
                        </h5>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Farm Size:</span>
                                <span class="info-value"><strong><?php echo number_format($farmer['farm_size'], 2); ?> hectares</strong></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Crop Type:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['crop_type']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Harvest Season:</span>
                                <span class="info-value"><?php echo htmlspecialchars($farmer['harvest_season']); ?></span>
                            </div>
                            <div class="col-md-6 mb-3">
                                <span class="info-label">Total Fertilizer Received:</span>
                                <span class="info-value"><strong class="text-success"><?php echo number_format($total['total'] ?? 0, 2); ?> kg</strong></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Distribution History -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-history text-info"></i> Fertilizer Distribution History
                        </h5>
                        <?php if($distributions && $distributions->num_rows > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-sm table-hover">
                                <thead>
                                    <tr style="background: #f8f9fa;">
                                        <th>Date</th>
                                        <th>Fertilizer Type</th>
                                        <th>Quantity</th>
                                        <th>Season</th>
                                        <th>Distributed By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php while($dist = $distributions->fetch_assoc()): ?>
                                    <tr>
                                        <td><?php echo date('d/m/Y', strtotime($dist['distribution_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($dist['fertilizer_name']); ?></td>
                                        <td><strong><?php echo number_format($dist['quantity'], 2); ?> kg</strong></td>
                                        <td><?php echo htmlspecialchars($dist['season']); ?></td>
                                        <td><?php echo htmlspecialchars($dist['distributed_by_name'] ?? 'System'); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-truck fa-3x mb-2 d-block"></i>
                            <p>No fertilizer distributions recorded for this farmer.</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <!-- Farm Photo -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-camera text-info"></i> Farm Photo
                        </h5>
                        <div class="photo-container">
                            <?php if($farmer['farm_photo'] && file_exists('../assets/uploads/' . $farmer['farm_photo'])): ?>
                                <img src="../assets/uploads/<?php echo $farmer['farm_photo']; ?>" alt="Farm Photo" class="img-fluid">
                                <div class="mt-2">
                                    <small class="text-muted">Uploaded farm image</small>
                                </div>
                            <?php else: ?>
                                <i class="fas fa-image fa-4x text-muted"></i>
                                <p class="text-muted mt-2 mb-0">No farm photo uploaded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- ID Proof -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-id-card text-warning"></i> ID / Land Proof
                        </h5>
                        <div class="photo-container">
                            <?php if($farmer['id_proof']): ?>
                                <?php 
                                $ext = pathinfo($farmer['id_proof'], PATHINFO_EXTENSION);
                                if(in_array($ext, ['jpg', 'jpeg', 'png'])): 
                                ?>
                                    <img src="../assets/uploads/<?php echo $farmer['id_proof']; ?>" alt="ID Proof" class="img-fluid">
                                    <div class="mt-2">
                                        <small class="text-muted">ID Document Image</small>
                                    </div>
                                <?php else: ?>
                                    <i class="fas fa-file-pdf fa-4x text-danger"></i>
                                    <p class="mt-2">
                                        <a href="../assets/uploads/<?php echo $farmer['id_proof']; ?>" target="_blank" class="btn btn-sm btn-danger">
                                            <i class="fas fa-file-pdf"></i> View PDF Document
                                        </a>
                                    </p>
                                <?php endif; ?>
                            <?php else: ?>
                                <i class="fas fa-file-alt fa-4x text-muted"></i>
                                <p class="text-muted mt-2 mb-0">No document uploaded</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Info Card -->
                    <div class="info-card">
                        <h5 class="section-title">
                            <i class="fas fa-info-circle text-info"></i> Quick Information
                        </h5>
                        <div class="d-grid gap-2">
                            <div class="alert alert-info mb-0">
                                <i class="fas fa-calendar-alt"></i> 
                                <strong>Member since:</strong><br>
                                <?php echo date('F d, Y', strtotime($farmer['created_at'])); ?>
                            </div>
                            <?php if($farmer['verification_status'] == 'pending'): ?>
                            <div class="alert alert-warning mb-0">
                                <i class="fas fa-clock"></i> 
                                <strong>Pending Verification</strong><br>
                                This farmer is waiting for verification
                            </div>
                            <?php elseif($farmer['verification_status'] == 'verified'): ?>
                            <div class="alert alert-success mb-0">
                                <i class="fas fa-check-circle"></i> 
                                <strong>Verified Farmer</strong><br>
                                Eligible for fertilizer distribution
                            </div>
                            <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                <i class="fas fa-times-circle"></i> 
                                <strong>Rejected Application</strong><br>
                                This farmer is not eligible
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
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
        
        // Close sidebar on window resize if open
        window.addEventListener('resize', function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                sidebar.classList.remove('active');
                sidebarOverlay.classList.remove('active');
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>