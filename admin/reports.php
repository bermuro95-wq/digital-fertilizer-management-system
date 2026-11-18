<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();

// Get unique regions for filter
$regions = executeQuery("SELECT DISTINCT region FROM farmers ORDER BY region");
$statuses = ['pending', 'verified', 'rejected'];

// Get distribution statistics
$dist_stats = executeQuery("
    SELECT 
        ft.name as fertilizer_name,
        SUM(d.quantity) as total_distributed,
        COUNT(DISTINCT d.farmer_id) as farmers_count
    FROM distributions d
    JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id
    GROUP BY ft.id
");
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('reports'); ?> - Admin</title>
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
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-export {
            margin: 5px;
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
            <a class="nav-link active" href="reports.php">
                <i class="fas fa-chart-bar"></i> <?php echo __('reports'); ?>
            </a>
            <a class="nav-link" href="logs.php">
                <i class="fas fa-history"></i> <?php echo __('logs'); ?>
            </a>
            <hr>
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
            </a>
        </nav>
    </div>
    
    <div class="main-content">
        <div class="top-navbar d-flex justify-content-between align-items-center">
            <h4 class="mb-0"><?php echo __('reports'); ?></h4>
            <div>
                <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
            </div>
        </div>
        
        <!-- Report Filters -->
        <div class="report-card">
            <h5 class="mb-3">Generate Farmers Report</h5>
            <form method="GET" action="../export_pdf.php" target="_blank" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Region</label>
                    <select name="region" class="form-select">
                        <option value="">All Regions</option>
                        <?php while($region = $regions->fetch_assoc()): ?>
                        <option value="<?php echo $region['region']; ?>"><?php echo $region['region']; ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Verification Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach($statuses as $status): ?>
                        <option value="<?php echo $status; ?>"><?php echo ucfirst($status); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" name="date_from" class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" name="date_to" class="form-control">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-danger btn-export">
                        <i class="fas fa-file-pdf"></i> Export to PDF
                    </button>
                    <button type="submit" formaction="../export_excel.php" class="btn btn-success btn-export">
                        <i class="fas fa-file-excel"></i> Export to Excel
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Distribution Statistics -->
        <div class="report-card">
            <h5 class="mb-3">Fertilizer Distribution Statistics</h5>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>Fertilizer Type</th>
                            <th>Total Distributed (kg)</th>
                            <th>Number of Farmers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($stat = $dist_stats->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['fertilizer_name']); ?></td>
                            <td><?php echo number_format($stat['total_distributed'], 2); ?></td>
                            <td><?php echo $stat['farmers_count']; ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Quick Report Links -->
        <div class="row">
            <div class="col-md-4">
                <div class="report-card text-center">
                    <i class="fas fa-users fa-3x text-primary mb-3"></i>
                    <h5>Farmers Report</h5>
                    <p>Complete list of all registered farmers</p>
                    <a href="../export_pdf.php" target="_blank" class="btn btn-primary btn-sm">Generate</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>