<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/language.php';
requireAdmin();

$conn = getConnection();
$message = '';
$error = '';

// Check if farmer ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: farmers.php');
    exit();
}

$farmer_id = $_GET['id'];

// Get farmer data
$farmer_query = executeQuery("SELECT * FROM farmers WHERE id = ?", [$farmer_id], 'i');
if ($farmer_query->num_rows == 0) {
    header('Location: farmers.php');
    exit();
}
$farmer = $farmer_query->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_farmer'])) {
    $full_name = $_POST['full_name'];
    $region = $_POST['region'];
    $district = $_POST['district'];
    $kebele = $_POST['kebele'];
    $farm_size = $_POST['farm_size'];
    $crop_type = $_POST['crop_type'];
    $harvest_season = $_POST['harvest_season'];
    $phone = $_POST['phone'];
    
    // Handle file uploads
    $id_proof = $farmer['id_proof'];
    $farm_photo = $farmer['farm_photo'];
    
    $upload_dir = '../assets/uploads/';
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['id_proof']) && $_FILES['id_proof']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png', 'pdf'];
        $ext = strtolower(pathinfo($_FILES['id_proof']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['id_proof']['size'] <= 5242880) {
            // Delete old file
            if ($id_proof && file_exists($upload_dir . $id_proof)) {
                unlink($upload_dir . $id_proof);
            }
            $id_proof = time() . '_id.' . $ext;
            move_uploaded_file($_FILES['id_proof']['tmp_name'], $upload_dir . $id_proof);
        }
    }
    
    if (isset($_FILES['farm_photo']) && $_FILES['farm_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['farm_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['farm_photo']['size'] <= 5242880) {
            // Delete old file
            if ($farm_photo && file_exists($upload_dir . $farm_photo)) {
                unlink($upload_dir . $farm_photo);
            }
            $farm_photo = time() . '_farm.' . $ext;
            move_uploaded_file($_FILES['farm_photo']['tmp_name'], $upload_dir . $farm_photo);
        }
    }
    
    $sql = "UPDATE farmers SET 
            full_name = ?, 
            region = ?, 
            district = ?, 
            kebele = ?, 
            farm_size = ?, 
            crop_type = ?, 
            harvest_season = ?, 
            phone = ?,
            id_proof = ?,
            farm_photo = ?
            WHERE id = ?";
    
    $result = executeUpdate($sql, [
        $full_name, $region, $district, $kebele, 
        $farm_size, $crop_type, $harvest_season, 
        $phone, $id_proof, $farm_photo, $farmer_id
    ], 'ssssdsssssi');
    
    if ($result['success']) {
        $message = __('farmer_updated');
        
        // Log the action
        executeUpdate("INSERT INTO logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)", 
            [getCurrentUserId(), "Updated farmer", "Farmer ID: $farmer_id, Name: $full_name", $_SERVER['REMOTE_ADDR']], 'isss');
        
        // Refresh farmer data
        $farmer_query = executeQuery("SELECT * FROM farmers WHERE id = ?", [$farmer_id], 'i');
        $farmer = $farmer_query->fetch_assoc();
    } else {
        $error = "Update failed!";
    }
}

// Get fertilizer types for dropdown
$fertilizer_types = executeQuery("SELECT * FROM fertilizer_types ORDER BY name");
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Farmer - Admin</title>
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
        .form-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .current-file {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 12px;
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
            <h4 class="mb-0">Edit Farmer</h4>
            <a href="farmers.php" class="btn btn-secondary btn-sm">
                <i class="fas fa-arrow-left"></i> Back to Farmers
            </a>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="POST" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Farmer ID</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($farmer['farmer_id']); ?>" disabled>
                        <small class="text-muted">Farmer ID cannot be changed</small>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Full Name *</label>
                        <input type="text" class="form-control" name="full_name" value="<?php echo htmlspecialchars($farmer['full_name']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Region *</label>
                        <input type="text" class="form-control" name="region" value="<?php echo htmlspecialchars($farmer['region']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">District *</label>
                        <input type="text" class="form-control" name="district" value="<?php echo htmlspecialchars($farmer['district']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Kebele/Village *</label>
                        <input type="text" class="form-control" name="kebele" value="<?php echo htmlspecialchars($farmer['kebele']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Farm Size (hectares) *</label>
                        <input type="number" step="0.01" class="form-control" name="farm_size" value="<?php echo $farmer['farm_size']; ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Crop Type *</label>
                        <input type="text" class="form-control" name="crop_type" value="<?php echo htmlspecialchars($farmer['crop_type']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Harvest Season *</label>
                        <select class="form-select" name="harvest_season" required>
                            <option value="Meher (Autumn)" <?php echo $farmer['harvest_season'] == 'Meher (Autumn)' ? 'selected' : ''; ?>>Meher (Autumn)</option>
                            <option value="Belg (Spring)" <?php echo $farmer['harvest_season'] == 'Belg (Spring)' ? 'selected' : ''; ?>>Belg (Spring)</option>
                            <option value="Bega (Winter)" <?php echo $farmer['harvest_season'] == 'Bega (Winter)' ? 'selected' : ''; ?>>Bega (Winter)</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Phone Number *</label>
                        <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($farmer['phone']); ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Upload ID/Land Proof</label>
                        <input type="file" class="form-control" name="id_proof" accept=".jpg,.jpeg,.png,.pdf">
                        <?php if($farmer['id_proof']): ?>
                        <div class="current-file">
                            <i class="fas fa-file"></i> Current file: <?php echo $farmer['id_proof']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Upload Farm Photo</label>
                        <input type="file" class="form-control" name="farm_photo" accept=".jpg,.jpeg,.png">
                        <?php if($farmer['farm_photo']): ?>
                        <div class="current-file">
                            <i class="fas fa-image"></i> Current photo: <?php echo $farmer['farm_photo']; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <button type="submit" name="update_farmer" class="btn btn-primary">
                            <i class="fas fa-save"></i> Update Farmer
                        </button>
                        <a href="farmers.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php $conn->close(); ?>