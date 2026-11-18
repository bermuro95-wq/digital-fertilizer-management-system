<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Get total count
$count_result = $conn->query("SELECT COUNT(*) as total FROM logs");
$total = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total / $limit);

// Get logs with user info
$logs_query = "
    SELECT l.*, u.name as user_name 
    FROM logs l 
    LEFT JOIN users u ON l.user_id = u.id 
    ORDER BY l.created_at DESC 
    LIMIT $offset, $limit";
$logs = $conn->query($logs_query);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('logs'); ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background: #2c3e50;
        }
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 15px 20px;
        }
        .sidebar .nav-link:hover {
            background: #34495e;
            padding-left: 30px;
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
            <div class="col-md-2 p-0 sidebar">
                <div class="text-center py-4">
                    <i class="fas fa-tractor fa-3x text-white"></i>
                    <h5 class="text-white mt-2">Fertilizer MS</h5>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
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
                    <a class="nav-link active" href="logs.php">
                        <i class="fas fa-history"></i> <?php echo __('logs'); ?>
                    </a>
                    <hr class="bg-secondary">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                    </a>
                </nav>
            </div>
            
            <div class="col-md-10 p-0 main-content">
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo __('logs'); ?></h4>
                    <div>
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="btn-group">
                            <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                            <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Timestamp</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($log = $logs->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                            <td><?php echo htmlspecialchars($log['user_name'] ?? 'System'); ?></td>
                                            <td><?php echo htmlspecialchars($log['action']); ?></td>
                                            <td><?php echo htmlspecialchars($log['details']); ?></td>
                                            <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Pagination -->
                            <?php if($total_pages > 1): ?>
                            <nav>
                                <ul class="pagination justify-content-center">
                                    <?php for($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
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