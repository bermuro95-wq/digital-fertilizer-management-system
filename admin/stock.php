<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Handle add stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    $fertilizer_type_id = $_POST['fertilizer_type_id'];
    $quantity = floatval($_POST['quantity']);
    $note = $_POST['note'] ?? '';
    
    // Get current stock
    $current_stock = executeQuery("SELECT quantity FROM stock WHERE fertilizer_type_id = ?", [$fertilizer_type_id], 'i');
    $current = $current_stock->fetch_assoc();
    $new_quantity = $current['quantity'] + $quantity;
    
    // Update stock
    $result = executeUpdate("UPDATE stock SET quantity = ? WHERE fertilizer_type_id = ?", [$new_quantity, $fertilizer_type_id], 'di');
    
    if ($result['success']) {
        $message = __('stock_updated');
        
        // Log the action
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [getCurrentUserId(), "Added stock", "Fertilizer Type ID: $fertilizer_type_id, Added: $quantity kg, Note: $note", $_SERVER['REMOTE_ADDR']], 'isss');
    } else {
        $error = "Failed to update stock!";
    }
}

// Handle remove stock
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_stock'])) {
    $fertilizer_type_id = $_POST['fertilizer_type_id'];
    $quantity = floatval($_POST['quantity']);
    $note = $_POST['note'] ?? '';
    
    $current_stock = executeQuery("SELECT quantity FROM stock WHERE fertilizer_type_id = ?", [$fertilizer_type_id], 'i');
    $current = $current_stock->fetch_assoc();
    
    if ($current['quantity'] >= $quantity) {
        $new_quantity = $current['quantity'] - $quantity;
        $result = executeUpdate("UPDATE stock SET quantity = ? WHERE fertilizer_type_id = ?", [$new_quantity, $fertilizer_type_id], 'di');
        
        if ($result['success']) {
            $message = "Stock removed successfully!";
            executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                [getCurrentUserId(), "Removed stock", "Fertilizer Type ID: $fertilizer_type_id, Removed: $quantity kg, Note: $note", $_SERVER['REMOTE_ADDR']], 'isss');
        } else {
            $error = "Failed to remove stock!";
        }
    } else {
        $error = "Insufficient stock to remove!";
    }
}

// Handle stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adjust_stock'])) {
    $fertilizer_type_id = $_POST['fertilizer_type_id'];
    $new_quantity = floatval($_POST['new_quantity']);
    $note = $_POST['note'] ?? '';
    
    $result = executeUpdate("UPDATE stock SET quantity = ? WHERE fertilizer_type_id = ?", [$new_quantity, $fertilizer_type_id], 'di');
    
    if ($result['success']) {
        $message = "Stock adjusted successfully!";
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [getCurrentUserId(), "Adjusted stock", "Fertilizer Type ID: $fertilizer_type_id, New quantity: $new_quantity kg, Note: $note", $_SERVER['REMOTE_ADDR']], 'isss');
    } else {
        $error = "Failed to adjust stock!";
    }
}

// Get all stock with fertilizer types
$stock_query = "
    SELECT s.*, ft.name, ft.description, ft.unit 
    FROM stock s 
    JOIN fertilizer_types ft ON s.fertilizer_type_id = ft.id 
    ORDER BY ft.name";
$stock_items = executeQuery($stock_query);

// FIXED: Get stock movement history - specified which table's created_at to use
$history_query = "
    SELECT 
        'distribution' as type,
        d.distribution_date as date,
        d.quantity,
        ft.name as fertilizer_name,
        f.full_name as farmer_name,
        u.name as user_name
    FROM distributions d
    JOIN fertilizer_types ft ON d.fertilizer_type_id = ft.id
    JOIN farmers f ON d.farmer_id = f.id
    JOIN users u ON d.distributed_by = u.id
    UNION ALL
    SELECT 
        'adjustment' as type,
        DATE(l.created_at) as date,
        0 as quantity,
        '' as fertilizer_name,
        l.details as farmer_name,
        u.name as user_name
    FROM logs l
    JOIN users u ON l.user_id = u.id
    WHERE l.action LIKE '%stock%'
    ORDER BY date DESC
    LIMIT 20";
$history = executeQuery($history_query);

// Calculate total stock
$total_stock = 0;
$low_stock_count = 0;
$stock_items_data = [];
if ($stock_items) {
    while($item = $stock_items->fetch_assoc()) {
        $total_stock += $item['quantity'];
        if($item['quantity'] < 500) {
            $low_stock_count++;
        }
        $stock_items_data[] = $item;
    }
}
$stock_items = $stock_items_data;

// Get fertilizer types for dropdown
$fertilizer_types = executeQuery("SELECT * FROM fertilizer_types ORDER BY name");
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('stock'); ?> - Admin</title>
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
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stock-low {
            border-left: 4px solid #dc3545;
            background: #fff3f3;
        }
        .stock-medium {
            border-left: 4px solid #ffc107;
            background: #fffbf0;
        }
        .stock-high {
            border-left: 4px solid #28a745;
            background: #f0fff4;
        }
        .btn-action {
            margin: 2px;
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
            <a class="nav-link active" href="stock.php">
                <i class="fas fa-boxes"></i> <?php echo __('stock'); ?>
            </a>
            <a class="nav-link" href="reports.php">
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
            <h4 class="mb-0"><?php echo __('stock'); ?> Management</h4>
            <div>
                <a href="?lang=en" class="btn btn-sm btn-outline-primary">EN</a>
                <a href="?lang=am" class="btn btn-sm btn-outline-primary">አማ</a>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-boxes fa-3x text-primary"></i>
                    <h3><?php echo number_format($total_stock, 2); ?> kg</h3>
                    <p>Total Stock</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-list fa-3x text-info"></i>
                    <h3><?php echo count($stock_items); ?></h3>
                    <p>Fertilizer Types</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card text-center">
                    <i class="fas fa-exclamation-triangle fa-3x text-warning"></i>
                    <h3 class="text-danger"><?php echo $low_stock_count; ?></h3>
                    <p>Low Stock Alerts</p>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Fertilizer Stock Levels</h5>
                <button class="btn btn-primary btn-sm float-end" data-bs-toggle="modal" data-bs-target="#addStockModal">
                    <i class="fas fa-plus"></i> Add Stock
                </button>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Fertilizer Type</th>
                                <th>Current Stock (kg)</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($stock_items as $item): 
                                $stock_class = '';
                                $status_text = 'Good';
                                $status_class = 'success';
                                
                                if($item['quantity'] < 500) {
                                    $stock_class = 'stock-low';
                                    $status_text = 'Critical';
                                    $status_class = 'danger';
                                } elseif($item['quantity'] < 1000) {
                                    $stock_class = 'stock-medium';
                                    $status_text = 'Low';
                                    $status_class = 'warning';
                                }
                            ?>
                            <tr class="<?php echo $stock_class; ?>">
                                <td><strong><?php echo htmlspecialchars($item['name']); ?></strong></td>
                                <td><strong><?php echo number_format($item['quantity'], 2); ?> kg</strong></td>
                                <td><span class="badge bg-<?php echo $status_class; ?>"><?php echo $status_text; ?></span></td>
                                <td>
                                    <button class="btn btn-sm btn-warning" onclick="adjustStock(<?php echo $item['fertilizer_type_id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['quantity']; ?>)">
                                        Adjust
                                    </button>
                                 </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Stock Modal -->
    <div class="modal fade" id="addStockModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">Add Stock</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="add_stock" value="1">
                        <div class="mb-3">
                            <label>Fertilizer Type</label>
                            <select class="form-control" name="fertilizer_type_id" required>
                                <option value="">Select</option>
                                <?php while($type = $fertilizer_types->fetch_assoc()): ?>
                                <option value="<?php echo $type['id']; ?>"><?php echo $type['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label>Quantity (kg)</label>
                            <input type="number" step="0.01" class="form-control" name="quantity" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function adjustStock(id, name, currentStock) {
            let newStock = prompt("Enter new stock quantity for " + name + " (Current: " + currentStock + " kg):", currentStock);
            if (newStock !== null && newStock !== "") {
                let form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = '<input type="hidden" name="adjust_stock" value="1">' +
                                '<input type="hidden" name="fertilizer_type_id" value="' + id + '">' +
                                '<input type="hidden" name="new_quantity" value="' + newStock + '">';
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>