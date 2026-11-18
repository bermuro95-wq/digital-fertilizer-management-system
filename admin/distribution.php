<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Get fertilizer types
$fertilizer_types = executeQuery("SELECT * FROM fertilizer_types");

// Get stock levels
$stock_levels = [];
$stock_result = executeQuery("SELECT s.*, ft.name FROM stock s JOIN fertilizer_types ft ON s.fertilizer_type_id = ft.id");
while($stock = $stock_result->fetch_assoc()) {
    $stock_levels[$stock['fertilizer_type_id']] = $stock;
}

// Handle distribution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['distribute'])) {
    $farmer_id = $_POST['farmer_id'];
    $fertilizer_type_id = $_POST['fertilizer_type_id'];
    $quantity = floatval($_POST['quantity']);
    $distribution_date = $_POST['distribution_date'];
    $season = $_POST['season'];
    
    // Check if farmer is verified
    $farmer_check = executeQuery("SELECT verification_status FROM farmers WHERE id = ?", [$farmer_id], 'i');
    $farmer = $farmer_check->fetch_assoc();
    
    if ($farmer['verification_status'] !== 'verified') {
        $error = "Farmer must be verified before receiving fertilizer!";
    } else {
        // Check stock availability
        $stock_query = executeQuery("SELECT quantity FROM stock WHERE fertilizer_type_id = ?", [$fertilizer_type_id], 'i');
        $stock = $stock_query->fetch_assoc();
        
        if ($stock['quantity'] >= $quantity) {
            // Deduct from stock
            $new_quantity = $stock['quantity'] - $quantity;
            executeUpdate("UPDATE stock SET quantity = ? WHERE fertilizer_type_id = ?", [$new_quantity, $fertilizer_type_id], 'di');
            
            // Record distribution
            $sql = "INSERT INTO distributions (farmer_id, fertilizer_type_id, quantity, distribution_date, season, distributed_by) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $result = executeUpdate($sql, [$farmer_id, $fertilizer_type_id, $quantity, $distribution_date, $season, getCurrentUserId()], 'iidssi');
            
            if ($result['success']) {
                $message = __('distribution_success');
                
                // Log the action
                executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                    [getCurrentUserId(), "Distributed fertilizer", "Type ID: $fertilizer_type_id, Quantity: $quantity kg to farmer ID: $farmer_id", $_SERVER['REMOTE_ADDR']], 'isss');
            } else {
                $error = "Distribution failed!";
            }
        } else {
            $error = __('insufficient_stock');
        }
    }
}

// Handle add stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $fertilizer_type_id = $_POST['fertilizer_type_id'];
    $quantity = floatval($_POST['quantity']);
    
    $current_stock = executeQuery("SELECT quantity FROM stock WHERE fertilizer_type_id = ?", [$fertilizer_type_id], 'i');
    $current = $current_stock->fetch_assoc();
    $new_quantity = $current['quantity'] + $quantity;
    
    $result = executeUpdate("UPDATE stock SET quantity = ? WHERE fertilizer_type_id = ?", [$new_quantity, $fertilizer_type_id], 'di');
    
    if ($result['success']) {
        $message = __('stock_updated');
        
        // Log the action
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [getCurrentUserId(), "Added stock", "Type ID: $fertilizer_type_id, Added: $quantity kg", $_SERVER['REMOTE_ADDR']], 'isss');
    }
}

// Get farmers for dropdown (only verified)
$farmers = executeQuery("SELECT id, full_name, farmer_id FROM farmers WHERE verification_status = 'verified' ORDER BY full_name");

// Get distribution history
$history_query = "SELECT d.*, f.full_name, f.farmer_id, ft.name as fertilizer_name 
                 FROM distributions d 
                 JOIN farmers f ON d.farmer_id = f.id 
                 JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id 
                 ORDER BY d.created_at DESC LIMIT 50";
$history = executeQuery($history_query);
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('distribution'); ?> - Admin</title>
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
        .stock-card {
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .stock-low {
            border-left-color: #dc3545;
        }
        .stock-medium {
            border-left-color: #ffc107;
        }
        .stock-high {
            border-left-color: #28a745;
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
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> <?php echo __('dashboard'); ?>
                    </a>
                    <a class="nav-link" href="farmers.php">
                        <i class="fas fa-users"></i> <?php echo __('farmers'); ?>
                    </a>
                    <a class="nav-link active" href="distribution.php">
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
                    <hr class="bg-secondary">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> <?php echo __('logout'); ?>
                    </a>
                </nav>
            </div>
            
            <!-- Main Content -->
            <div class="col-md-10 p-0 main-content">
                <div class="top-navbar d-flex justify-content-between align-items-center">
                    <h4 class="mb-0"><?php echo __('distribution'); ?></h4>
                    <div>
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                        <div class="btn-group">
                            <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                            <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
                        </div>
                    </div>
                </div>
                
                <div class="p-4">
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <div class="row">
                        <!-- Distribution Form -->
                        <div class="col-md-5">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">Distribute Fertilizer</h5>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label">Select Farmer *</label>
                                            <select class="form-select" name="farmer_id" required>
                                                <option value="">Select Farmer</option>
                                                <?php while($farmer = $farmers->fetch_assoc()): ?>
                                                <option value="<?php echo $farmer['id']; ?>">
                                                    <?php echo htmlspecialchars($farmer['full_name'] . ' - ' . $farmer['farmer_id']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Fertilizer Type *</label>
                                            <select class="form-select" name="fertilizer_type_id" required onchange="updateStockInfo(this.value)">
                                                <option value="">Select Fertilizer</option>
                                                <?php 
                                                $types_result = executeQuery("SELECT * FROM fertilizer_types");
                                                while($type = $types_result->fetch_assoc()): 
                                                ?>
                                                <option value="<?php echo $type['id']; ?>">
                                                    <?php echo htmlspecialchars($type['name']); ?>
                                                </option>
                                                <?php endwhile; ?>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Available Stock: <span id="stock-display">0</span> kg</label>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Quantity (kg) *</label>
                                            <input type="number" step="0.01" class="form-control" name="quantity" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Distribution Date *</label>
                                            <input type="date" class="form-control" name="distribution_date" value="<?php echo date('Y-m-d'); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Season</label>
                                            <select class="form-select" name="season">
                                                <option value="Meher (Autumn)">Meher (Autumn)</option>
                                                <option value="Belg (Spring)">Belg (Spring)</option>
                                                <option value="Bega (Winter)">Bega (Winter)</option>
                                            </select>
                                        </div>
                                        
                                        <button type="submit" name="distribute" class="btn btn-primary w-100">
                                            <i class="fas fa-truck"></i> Distribute
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stock Levels -->
                        <div class="col-md-7">
                            <div class="card">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">Current Stock Levels</h5>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addStockModal">
                                        <i class="fas fa-plus"></i> Add Stock
                                    </button>
                                </div>
                                <div class="card-body">
                                    <?php foreach($stock_levels as $stock): 
                                        $stock_class = 'stock-high';
                                        if ($stock['quantity'] < 500) $stock_class = 'stock-low';
                                        elseif ($stock['quantity'] < 1000) $stock_class = 'stock-medium';
                                    ?>
                                    <div class="stock-card <?php echo $stock_class; ?> p-3 bg-light rounded">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($stock['name']); ?></h6>
                                                <small class="text-muted">Available: <?php echo number_format($stock['quantity'], 2); ?> kg</small>
                                            </div>
                                            <?php if ($stock['quantity'] < 500): ?>
                                            <span class="badge bg-danger">Low Stock!</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Distribution History -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Distribution History</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Farmer Name</th>
                                            <th>Farmer ID</th>
                                            <th>Fertilizer Type</th>
                                            <th>Quantity</th>
                                            <th>Season</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($record = $history->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($record['distribution_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                            <td><?php echo htmlspecialchars($record['farmer_id']); ?></td>
                                            <td><?php echo htmlspecialchars($record['fertilizer_name']); ?></td>
                                            <td><?php echo number_format($record['quantity'], 2); ?> kg</td>
                                            <td><?php echo htmlspecialchars($record['season']); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Fertilizer Type *</label>
                            <select class="form-select" name="fertilizer_type_id" required>
                                <option value="">Select</option>
                                <?php 
                                $types_add = executeQuery("SELECT * FROM fertilizer_types");
                                while($type = $types_add->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity to Add (kg) *</label>
                            <input type="number" step="0.01" class="form-control" name="quantity" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_stock" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const stockData = <?php 
            $stock_json = [];
            foreach($stock_levels as $stock) {
                $stock_json[$stock['fertilizer_type_id']] = $stock['quantity'];
            }
            echo json_encode($stock_json);
        ?>;
        
        function updateStockInfo(typeId) {
            const stock = stockData[typeId] || 0;
            document.getElementById('stock-display').textContent = stock;
        }
    </script>
</body>
</html>