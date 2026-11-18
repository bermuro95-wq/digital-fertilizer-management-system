<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();

// Get statistics
$stats = [];

// Total farmers
$result = $conn->query("SELECT COUNT(*) as total FROM farmers");
$stats['total_farmers'] = $result->fetch_assoc()['total'];

// Pending verification
$result = $conn->query("SELECT COUNT(*) as total FROM farmers WHERE verification_status = 'pending'");
$stats['pending_verification'] = $result->fetch_assoc()['total'];

// Total distributed fertilizer
$result = $conn->query("SELECT SUM(quantity) as total FROM distributions");
$stats['total_distributed'] = $result->fetch_assoc()['total'] ?? 0;

// Current stock
$result = $conn->query("SELECT SUM(quantity) as total FROM stock");
$stats['current_stock'] = $result->fetch_assoc()['total'] ?? 0;

// Recent distributions
$recentQuery = "SELECT d.*, f.full_name, ft.name as fertilizer_name 
                 FROM distributions d 
                 JOIN farmers f ON d.farmer_id = f.id 
                 JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id 
                 ORDER BY d.created_at DESC LIMIT 5";
$recentDistributions = $conn->query($recentQuery);

// Low stock alert
$lowStockQuery = "SELECT ft.name, s.quantity 
                  FROM stock s 
                  JOIN fertilizer_types ft ON s.fertilizer_type_id = ft.id 
                  WHERE s.quantity < 1000";
$lowStockItems = $conn->query($lowStockQuery);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('dashboard'); ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }
        .stat-card i {
            font-size: 48px;
            opacity: 0.7;
        }
        .stat-card .stat-number {
            font-size: 32px;
            font-weight: bold;
        }
        .bg-primary-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .bg-success-gradient {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
        }
        .bg-warning-gradient {
            background: linear-gradient(135deg, #ffe259 0%, #ffa751 100%);
        }
        .bg-info-gradient {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
        }
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            padding-left: 30px;
        }
        .sidebar .nav-link.active {
            background: #3498db;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .top-navbar {
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <i class="fas fa-tractor fa-3x text-white"></i>
                    <h5 class="text-white mt-2">Fertilizer MS</h5>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                    </a>
                    <a class="nav-link" href="farmers.php">
                        <i class="fas fa-users"></i> <?php echo __('farmers'); ?>
                    </a>
                    <a class="nav-link" href="distribution.php">
                        <i class="fas fa-truck"></i> <?php echo __('distribution'); ?>
                    </a>
                    <a class="nav-link" href="stock.php">
                        <i class="fas fa-boxes"></i> <?php echo __('stock'); ?>
                    </a>
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> <?php echo __('reports'); ?>
                    </a>
                    <a class="nav-link" href="logs.php">
                        <i class="fas fa-history"></i> <?php echo __('logs'); ?>
                    </a>
                    <a class="nav-link" href="backup.php">
                        <i class="fas fa-database"></i> <?php echo __('backup'); ?>
                    </a>
                    <hr class="bg-secondary">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-0 main-content">
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo __('dashboard'); ?></h4>
                    <div>
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="btn-group">
                            <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                            <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <!-- Statistics Cards -->
                    <div class="row">
                        <div class="col-md-3">
                            <div class="stat-card bg-primary-gradient">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo $stats['total_farmers']; ?></div>
                                        <div><?php echo __('total_farmers'); ?></div>
                                    </div>
                                    <i class="fas fa-users"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-success-gradient">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['total_distributed'], 2); ?> kg</div>
                                        <div><?php echo __('total_distributed'); ?></div>
                                    </div>
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-warning-gradient">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo number_format($stats['current_stock'], 2); ?> kg</div>
                                        <div><?php echo __('current_stock'); ?></div>
                                    </div>
                                    <i class="fas fa-boxes"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="stat-card bg-info-gradient">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="stat-number"><?php echo $stats['pending_verification']; ?></div>
                                        <div><?php echo __('pending_verification'); ?></div>
                                    </div>
                                    <i class="fas fa-clock"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Low Stock Alert -->
                    <?php if ($lowStockItems->num_rows > 0): ?>
                    <div class="alert alert-warning mt-3">
                        <strong><i class="fas fa-exclamation-triangle"></i> <?php echo __('low_stock_alert'); ?>:</strong>
                        <ul class="mb-0 mt-2">
                            <?php while($item = $lowStockItems->fetch_assoc()): ?>
                            <li><?php echo $item['name']; ?>: <?php echo number_format($item['quantity'], 2); ?> kg remaining</li>
                            <?php endwhile; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Recent Distributions -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Fertilizer Distributions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Farmer Name</th>
                                            <th>Fertilizer Type</th>
                                            <th>Quantity (kg)</th>
                                            <th>Distribution Date</th>
                                            <th>Season</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($dist = $recentDistributions->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($dist['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($dist['fertilizer_name']); ?></td>
                                            <td><?php echo number_format($dist['quantity'], 2); ?></td>
                                            <td><?php echo date('d/m/Y', strtotime($dist['distribution_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($dist['season']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <?php if ($recentDistributions->num_rows == 0): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No distributions recorded yet</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>