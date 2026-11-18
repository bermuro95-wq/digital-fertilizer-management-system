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

// Get unique regions for filter
$regions = executeQuery("SELECT DISTINCT region FROM farmers ORDER BY region");
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('reports'); ?> - Officer</title>
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
        .report-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
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
                    <span class="badge bg-info mt-2">Officer</span>
                </div>
                <nav class="nav flex-column">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                    </a>
                    <a class="nav-link" href="farmers.php">
                        <i class="fas fa-users"></i> <?php echo __('farmers'); ?>
                    </a>
                    <a class="nav-link active" href="reports.php">
                        <i class="fas fa-chart-bar"></i> <?php echo __('reports'); ?>
                    </a>
                    <hr class="bg-secondary">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                    </a>
                </nav>
            </div>
            
            <div class="col-md-10 p-0 main-content">
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo __('reports'); ?></h4>
                    <div>
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="btn-group">
                            <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                            <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <div class="report-card">
                        <h5 class="mb-3">Generate Farmers Report</h5>
                        <form method="GET" action="../export_pdf.php" target="_blank" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Region</label>
                                <select name="region" class="form-select">
                                    <option value="">All Regions</option>
                                    <?php while($region = $regions->fetch_assoc()): ?>
                                    <option value="<?php echo $region['region']; ?>"><?php echo $region['region']; ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Verification Status</label>
                                <select name="status" class="form-select">
                                    <option value="">All Status</option>
                                    <option value="verified">Verified</option>
                                    <option value="pending">Pending</option>
                                    <option value="rejected">Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-file-pdf"></i> Export to PDF
                                    </button>
                                    <button type="submit" formaction="../export_excel.php" class="btn btn-success">
                                        <i class="fas fa-file-excel"></i> Export to Excel
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>