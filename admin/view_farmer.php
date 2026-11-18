<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

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
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Farmer - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 12px 20px;
            display: block;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
        }
        .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 260px;
            padding: 20px;
            background: #f4f6f9;
            min-height: 100vh;
        }
        .top-navbar {
            background: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .info-label {
            font-weight: bold;
            color: #555;
            width: 180px;
            display: inline-block;
        }
        .info-value {
            color: #333;
        }
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="text-center py-4">
            <i class="fas fa-tractor fa-3x text-white"></i>
            <h5 class="text-white mt-2">Fertilizer MS</h5>
        </div>
        <nav class="nav flex-column">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
            <a class="nav-link active" href="farmers.php">
                <i class="fas fa-users"></i> Farmers
            </a>
            <a class="nav-link" href="distribution.php">
                <i class="fas fa-truck"></i> Distribution
            </a>
            <a class="nav-link" href="stock.php">
                <i class="fas fa-boxes"></i> Stock
            </a>
            <a class="nav-link" href="reports.php">
                <i class="fas fa-chart-bar"></i> Reports
            </a>
            <hr>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Farmer Details</h4>
            <div>
                <a href="edit_farmer.php?id=<?php echo $farmer_id; ?>" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit"></i> Edit Farmer
                </a>
                <a href="farmers.php" class="btn btn-secondary btn-sm">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-8">
                <!-- Personal Information -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-user text-primary"></i> Personal Information
                    </h5>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Farmer ID:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['farmer_id']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Full Name:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['full_name']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Phone Number:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['phone']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Registration Date:</span>
                            <span class="info-value"><?php echo date('d/m/Y', strtotime($farmer['created_at'])); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Registered By:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['created_by_name'] ?? 'System'); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Verification Status:</span>
                            <span class="status-badge status-<?php echo $farmer['verification_status']; ?>">
                                <?php echo ucfirst($farmer['verification_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Location Information -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-map-marker-alt text-success"></i> Location Information
                    </h5>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Region:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['region']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">District:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['district']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Kebele/Village:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['kebele']); ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Farm Information -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-tractor text-warning"></i> Farm Information
                    </h5>
                    <div class="row">
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Farm Size:</span>
                            <span class="info-value"><?php echo number_format($farmer['farm_size'], 2); ?> hectares</span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Crop Type:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['crop_type']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Harvest Season:</span>
                            <span class="info-value"><?php echo htmlspecialchars($farmer['harvest_season']); ?></span>
                        </div>
                        <div class="col-md-6 mb-2">
                            <span class="info-label">Total Fertilizer Received:</span>
                            <span class="info-value"><strong><?php echo number_format($total['total'] ?? 0, 2); ?> kg</strong></span>
                        </div>
                    </div>
                </div>
                
                <!-- Distribution History -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-history text-info"></i> Fertilizer Distribution History
                    </h5>
                    <?php if($distributions->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
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
                                    <td><?php echo number_format($dist['quantity'], 2); ?> kg</td>
                                    <td><?php echo htmlspecialchars($dist['season']); ?></td>
                                    <td><?php echo htmlspecialchars($dist['distributed_by_name'] ?? 'System'); ?></td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <p class="text-muted">No fertilizer distributions recorded for this farmer.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="col-md-4">
                <!-- Farm Photo -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-camera text-info"></i> Farm Photo
                    </h5>
                    <div class="photo-container">
                        <?php if($farmer['farm_photo'] && file_exists('../assets/uploads/' . $farmer['farm_photo'])): ?>
                            <img src="../assets/uploads/<?php echo $farmer['farm_photo']; ?>" alt="Farm Photo" class="img-fluid">
                        <?php else: ?>
                            <i class="fas fa-image fa-4x text-muted"></i>
                            <p class="text-muted mt-2">No farm photo uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- ID Proof -->
                <div class="info-card">
                    <h5 class="mb-3">
                        <i class="fas fa-id-card text-warning"></i> ID / Land Proof
                    </h5>
                    <div class="photo-container">
                        <?php if($farmer['id_proof']): ?>
                            <?php 
                            $ext = pathinfo($farmer['id_proof'], PATHINFO_EXTENSION);
                            if(in_array($ext, ['jpg', 'jpeg', 'png'])): 
                            ?>
                                <img src="../assets/uploads/<?php echo $farmer['id_proof']; ?>" alt="ID Proof" class="img-fluid">
                            <?php else: ?>
                                <i class="fas fa-file-pdf fa-4x text-danger"></i>
                                <p class="mt-2">
                                    <a href="../assets/uploads/<?php echo $farmer['id_proof']; ?>" target="_blank" class="btn btn-sm btn-danger">
                                        <i class="fas fa-file-pdf"></i> View PDF
                                    </a>
                                </p>
                            <?php endif; ?>
                        <?php else: ?>
                            <i class="fas fa-file-alt fa-4x text-muted"></i>
                            <p class="text-muted mt-2">No document uploaded</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>