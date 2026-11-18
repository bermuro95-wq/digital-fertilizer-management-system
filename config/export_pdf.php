<?php
require_once 'config/session.php';
require_once 'config/database.php';
requireLogin();

// Check if user has permission to export
if (!isAdmin() && !isOfficer()) {
    header('Location: index.php');
    exit();
}

// Get filter parameters
$filter_region = $_GET['region'] ?? '';
$filter_status = $_GET['status'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';

// Build query
$sql = "SELECT * FROM farmers WHERE 1=1";
$params = [];
$types = "";

if (!empty($filter_region)) {
    $sql .= " AND region = ?";
    $params[] = $filter_region;
    $types .= "s";
}

if (!empty($filter_status)) {
    $sql .= " AND verification_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_date_from)) {
    $sql .= " AND DATE(created_at) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= " AND DATE(created_at) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

$sql .= " ORDER BY created_at DESC";
$farmers = executeQuery($sql, $params, $types);

// Get statistics
$stats_sql = "SELECT 
    COUNT(*) as total_farmers,
    SUM(CASE WHEN verification_status = 'verified' THEN 1 ELSE 0 END) as verified,
    SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected
    FROM farmers WHERE 1=1";
$stats_result = executeQuery($stats_sql, $params, $types);
$stats = $stats_result->fetch_assoc();

// Set PDF headers
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Farmers Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #4CAF50;
            padding-bottom: 10px;
        }
        .header h1 {
            margin: 0;
            color: #4CAF50;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .stats {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            padding: 15px;
            background: #f5f5f5;
            border-radius: 5px;
        }
        .stat-box {
            text-align: center;
            flex: 1;
        }
        .stat-box h3 {
            margin: 0;
            color: #333;
        }
        .stat-box .number {
            font-size: 24px;
            font-weight: bold;
            color: #4CAF50;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #4CAF50;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 12px;
            color: #666;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: bold;
        }
        .badge-verified {
            background: #d4edda;
            color: #155724;
        }
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        .badge-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        @media print {
            body {
                margin: 0;
                padding: 10px;
            }
            .no-print {
                display: none;
            }
        }
        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-print {
            background: #4CAF50;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
        }
        .btn-print:hover {
            background: #45a049;
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Print / Save as PDF</button>
        <button onclick="window.close()" class="btn-print" style="background: #666; margin-left: 10px;">Close</button>
    </div>

    <div class="header">
        <h1>Digital Fertilizer Management System</h1>
        <p>Farmers Registration Report</p>
        <p>Generated on: <?php echo date('F d, Y h:i A'); ?></p>
        <?php if(!empty($filter_region)): ?>
        <p>Region: <?php echo htmlspecialchars($filter_region); ?></p>
        <?php endif; ?>
    </div>

    <div class="stats">
        <div class="stat-box">
            <h3>Total Farmers</h3>
            <div class="number"><?php echo $stats['total_farmers']; ?></div>
        </div>
        <div class="stat-box">
            <h3>Verified</h3>
            <div class="number" style="color: #28a745;"><?php echo $stats['verified']; ?></div>
        </div>
        <div class="stat-box">
            <h3>Pending</h3>
            <div class="number" style="color: #ffc107;"><?php echo $stats['pending']; ?></div>
        </div>
        <div class="stat-box">
            <h3>Rejected</h3>
            <div class="number" style="color: #dc3545;"><?php echo $stats['rejected']; ?></div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Farmer ID</th>
                <th>Full Name</th>
                <th>Region</th>
                <th>District</th>
                <th>Phone</th>
                <th>Farm Size</th>
                <th>Crop Type</th>
                <th>Status</th>
                <th>Registration Date</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $counter = 1;
            while($farmer = $farmers->fetch_assoc()): 
                $status_class = '';
                if($farmer['verification_status'] == 'verified') $status_class = 'badge-verified';
                elseif($farmer['verification_status'] == 'pending') $status_class = 'badge-pending';
                else $status_class = 'badge-rejected';
            ?>
            <tr>
                <td><?php echo $counter++; ?></td>
                <td><?php echo htmlspecialchars($farmer['farmer_id']); ?></td>
                <td><?php echo htmlspecialchars($farmer['full_name']); ?></td>
                <td><?php echo htmlspecialchars($farmer['region']); ?></td>
                <td><?php echo htmlspecialchars($farmer['district']); ?></td>
                <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
                <td><?php echo number_format($farmer['farm_size'], 2); ?> ha</td>
                <td><?php echo htmlspecialchars($farmer['crop_type']); ?></td>
                <td><span class="badge <?php echo $status_class; ?>"><?php echo ucfirst($farmer['verification_status']); ?></span></td>
                <td><?php echo date('d/m/Y', strtotime($farmer['created_at'])); ?></td>
            </tr>
            <?php endwhile; ?>
            <?php if($farmers->num_rows == 0): ?>
            <tr>
                <td colspan="10" style="text-align: center;">No farmers found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="footer">
        <p>This is a computer-generated document. No signature is required.</p>
        <p>&copy; <?php echo date('Y'); ?> Digital Fertilizer Management System</p>
    </div>

    <script>
        // Auto print (optional - remove if you don't want auto print)
        // setTimeout(function() { window.print(); }, 1000);
    </script>
</body>
</html>
<?php $conn->close(); ?>