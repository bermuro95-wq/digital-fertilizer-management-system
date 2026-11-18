<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Create backup directory if not exists
$backup_dir = '../backups/';
if (!file_exists($backup_dir)) {
    mkdir($backup_dir, 0777, true);
}

// Handle database backup
if (isset($_POST['backup_database'])) {
    $backup_type = $_POST['backup_type'] ?? 'full';
    $filename = 'fertilizer_system_backup_' . date('Y-m-d_H-i-s') . '.sql';
    $filepath = $backup_dir . $filename;
    
    // Get all tables
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }
    
    // If specific tables selected
    if ($backup_type == 'selected' && isset($_POST['tables'])) {
        $tables = $_POST['tables'];
    }
    
    // Create backup SQL
    $backup_sql = "-- Digital Fertilizer Management System Backup\n";
    $backup_sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup_sql .= "-- Database: fertilizer_system\n\n";
    $backup_sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    
    foreach ($tables as $table) {
        // Get create table statement
        $create_result = $conn->query("SHOW CREATE TABLE $table");
        $create_row = $create_result->fetch_row();
        $backup_sql .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup_sql .= $create_row[1] . ";\n\n";
        
        // Get data
        $data_result = $conn->query("SELECT * FROM $table");
        if ($data_result->num_rows > 0) {
            $backup_sql .= "INSERT INTO `$table` VALUES\n";
            $rows = [];
            while ($row = $data_result->fetch_assoc()) {
                $values = [];
                foreach ($row as $value) {
                    if ($value === null) {
                        $values[] = "NULL";
                    } else {
                        $values[] = "'" . $conn->real_escape_string($value) . "'";
                    }
                }
                $rows[] = "(" . implode(", ", $values) . ")";
            }
            $backup_sql .= implode(",\n", $rows) . ";\n\n";
        }
    }
    
    $backup_sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    
    // Save backup file
    if (file_put_contents($filepath, $backup_sql)) {
        $message = "Database backup created successfully! Filename: " . $filename;
        
        // Log the backup
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [getCurrentUserId(), "Database Backup", "Backup created: $filename, Type: $backup_type", $_SERVER['REMOTE_ADDR']], 'isss');
    } else {
        $error = "Failed to create backup! Check permissions on backups folder.";
    }
}

// Handle backup download
if (isset($_GET['download'])) {
    $filename = basename($_GET['download']);
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit();
    } else {
        $error = "File not found!";
    }
}

// Handle backup deletion
if (isset($_GET['delete'])) {
    $filename = basename($_GET['delete']);
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath)) {
        if (unlink($filepath)) {
            $message = "Backup file deleted successfully!";
            
            // Log the deletion
            executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                [getCurrentUserId(), "Backup Deleted", "Deleted backup: $filename", $_SERVER['REMOTE_ADDR']], 'isss');
        } else {
            $error = "Failed to delete backup file!";
        }
    } else {
        $error = "File not found!";
    }
}

// Handle restore backup
if (isset($_POST['restore_backup'])) {
    $filename = basename($_POST['backup_file']);
    $filepath = $backup_dir . $filename;
    
    if (file_exists($filepath)) {
        // Read SQL file
        $sql_content = file_get_contents($filepath);
        
        // Split SQL commands
        $commands = explode(';', $sql_content);
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($commands as $command) {
            $command = trim($command);
            if (!empty($command) && $command != 'SET FOREIGN_KEY_CHECKS=0' && $command != 'SET FOREIGN_KEY_CHECKS=1') {
                if ($conn->query($command) === TRUE) {
                    $success_count++;
                } else {
                    $error_count++;
                }
            }
        }
        
        if ($error_count == 0) {
            $message = "Database restored successfully from: $filename ($success_count commands executed)";
            
            // Log the restore
            executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
                [getCurrentUserId(), "Database Restore", "Restored from backup: $filename", $_SERVER['REMOTE_ADDR']], 'isss');
        } else {
            $error = "Restore completed with $error_count errors. $success_count commands succeeded.";
        }
    } else {
        $error = "Backup file not found!";
    }
}

// Get list of backup files
$backup_files = glob($backup_dir . "*.sql");
rsort($backup_files); // Sort by newest first

// Get database statistics
$tables_count = 0;
$result = $conn->query("SHOW TABLES");
$tables_count = $result->num_rows;

$result = $conn->query("SELECT 
    (SELECT COUNT(*) FROM farmers) as farmers,
    (SELECT COUNT(*) FROM users) as users,
    (SELECT COUNT(*) FROM distributions) as distributions");
$stats = $result->fetch_assoc();

// Get table list for selective backup
$table_list = [];
$result = $conn->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $table_list[] = $row[0];
}
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('backup'); ?> - Admin</title>
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
        .backup-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .file-item {
            border-left: 3px solid #3498db;
            margin-bottom: 10px;
            padding: 10px;
            background: #f8f9fa;
            transition: all 0.3s;
        }
        .file-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .stats-box {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 15px;
        }
        .btn-backup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
        }
        .btn-backup:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
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
                    <a class="nav-link active" href="backup.php">
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
                    <h4 class="mb-0"><?php echo __('backup'); ?> & Restore</h4>
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
                    
                    <div class="row">
                        <!-- Backup Section -->
                        <div class="col-md-6">
                            <div class="backup-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-download text-primary"></i> Create Backup
                                </h5>
                                <form method="POST" action="">
                                    <div class="mb-3">
                                        <label class="form-label">Backup Type</label>
                                        <select name="backup_type" id="backup_type" class="form-select" onchange="toggleTables()">
                                            <option value="full">Full Database Backup (All Tables)</option>
                                            <option value="selected">Select Specific Tables</option>
                                        </select>
                                    </div>
                                    
                                    <div id="tables_selection" style="display: none;">
                                        <label class="form-label">Select Tables to Backup:</label>
                                        <div class="mb-3 border p-3 rounded" style="max-height: 200px; overflow-y: auto;">
                                            <?php foreach($table_list as $table): ?>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="tables[]" value="<?php echo $table; ?>" id="table_<?php echo $table; ?>">
                                                <label class="form-check-label" for="table_<?php echo $table; ?>">
                                                    <?php echo $table; ?>
                                                </label>
                                            </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                    
                                    <button type="submit" name="backup_database" class="btn btn-primary btn-backup w-100">
                                        <i class="fas fa-database"></i> Create Backup Now
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Database Statistics -->
                            <div class="backup-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-chart-pie text-info"></i> Database Statistics
                                </h5>
                                <div class="row">
                                    <div class="col-6">
                                        <div class="stats-box">
                                            <i class="fas fa-table fa-2x"></i>
                                            <h3><?php echo $tables_count; ?></h3>
                                            <small>Total Tables</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stats-box" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                                            <i class="fas fa-users fa-2x"></i>
                                            <h3><?php echo $stats['farmers']; ?></h3>
                                            <small>Farmers</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stats-box" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                                            <i class="fas fa-user-shield fa-2x"></i>
                                            <h3><?php echo $stats['users']; ?></h3>
                                            <small>Users</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="stats-box" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                                            <i class="fas fa-truck fa-2x"></i>
                                            <h3><?php echo $stats['distributions']; ?></h3>
                                            <small>Distributions</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Restore Section -->
                        <div class="col-md-6">
                            <div class="backup-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-upload text-success"></i> Restore Database
                                </h5>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <strong>Warning:</strong> Restoring will overwrite current data. This action cannot be undone!
                                </div>
                                <form method="POST" action="" onsubmit="return confirm('WARNING: This will overwrite your current database! Are you absolutely sure?')">
                                    <div class="mb-3">
                                        <label class="form-label">Select Backup File to Restore</label>
                                        <select name="backup_file" class="form-select" required>
                                            <option value="">Select a backup file...</option>
                                            <?php foreach($backup_files as $file): 
                                                $filename = basename($file);
                                                $filesize = filesize($file);
                                                $filedate = date('Y-m-d H:i:s', filemtime($file));
                                            ?>
                                            <option value="<?php echo $filename; ?>">
                                                <?php echo $filename; ?> (<?php echo number_format($filesize / 1024, 2); ?> KB - <?php echo $filedate; ?>)
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <button type="submit" name="restore_backup" class="btn btn-warning w-100">
                                        <i class="fas fa-undo-alt"></i> Restore Database
                                    </button>
                                </form>
                            </div>
                            
                            <!-- Backup Files List -->
                            <div class="backup-card">
                                <h5 class="mb-3">
                                    <i class="fas fa-file-archive text-secondary"></i> Existing Backups
                                </h5>
                                <?php if(count($backup_files) > 0): ?>
                                    <div class="list-group">
                                        <?php 
                                        $display_count = 0;
                                        foreach($backup_files as $file): 
                                            if($display_count++ >= 10) break;
                                            $filename = basename($file);
                                            $filesize = filesize($file);
                                            $filedate = date('Y-m-d H:i:s', filemtime($file));
                                        ?>
                                        <div class="file-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-database text-primary"></i>
                                                <strong><?php echo $filename; ?></strong><br>
                                                <small class="text-muted">
                                                    <?php echo number_format($filesize / 1024, 2); ?> KB | <?php echo $filedate; ?>
                                                </small>
                                            </div>
                                            <div>
                                                <a href="?download=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-success">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                                <a href="?delete=<?php echo urlencode($filename); ?>" class="btn btn-sm btn-danger" 
                                                   onclick="return confirm('Are you sure you want to delete this backup?')">
                                                    <i class="fas fa-trash"></i>
                                                </a>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php if(count($backup_files) > 10): ?>
                                    <p class="text-muted mt-2">Showing last 10 backups. Total: <?php echo count($backup_files); ?> backups</p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-4">
                                        <i class="fas fa-folder-open fa-3x mb-2"></i>
                                        <p>No backup files found. Create your first backup!</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Backup Tips -->
                    <div class="backup-card">
                        <h5 class="mb-3">
                            <i class="fas fa-lightbulb text-warning"></i> Backup Tips
                        </h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas fa-clock fa-2x text-primary mb-2"></i>
                                    <h6>Regular Backups</h6>
                                    <small class="text-muted">Schedule weekly backups to prevent data loss</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas fa-cloud-upload-alt fa-2x text-info mb-2"></i>
                                    <h6>Offsite Storage</h6>
                                    <small class="text-muted">Download backups and store them in cloud storage</small>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-center">
                                    <i class="fas fa-shield-alt fa-2x text-success mb-2"></i>
                                    <h6>Test Restores</h6>
                                    <small class="text-muted">Periodically test backup restoration process</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleTables() {
            var backupType = document.getElementById('backup_type').value;
            var tablesDiv = document.getElementById('tables_selection');
            if (backupType === 'selected') {
                tablesDiv.style.display = 'block';
            } else {
                tablesDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>