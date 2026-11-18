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

$sql .= " ORDER BY created_at DESC";
$farmers = executeQuery($sql, $params, $types);

// Set headers for Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="farmers_report_' . date('Y-m-d') . '.xls"');
header('Cache-Control: max-age=0');

// Add UTF-8 BOM for proper encoding
echo "\xEF\xBB\xBF";

// Create HTML table for Excel
?>
<table border="1">
    <thead>
        <tr>
            <th colspan="12" style="background-color: #4CAF50; color: white; font-size: 16px;">
                Digital Fertilizer Management System - Farmers Report
            </th>
        </tr>
        <tr>
            <th colspan="12" style="background-color: #f0f0f0;">
                Generated on: <?php echo date('F d, Y h:i A'); ?>
                <?php if(!empty($filter_region)): ?> | Region: <?php echo htmlspecialchars($filter_region); ?><?php endif; ?>
                <?php if(!empty($filter_status)): ?> | Status: <?php echo htmlspecialchars($filter_status); ?><?php endif; ?>
            </th>
        </tr>
        <tr>
            <th>#</th>
            <th>Farmer ID</th>
            <th>Full Name</th>
            <th>Region</th>
            <th>District</th>
            <th>Kebele</th>
            <th>Phone</th>
            <th>Farm Size (ha)</th>
            <th>Crop Type</th>
            <th>Harvest Season</th>
            <th>Status</th>
            <th>Registration Date</th>
         </tr>
    </thead>
    <tbody>
        <?php 
        $counter = 1;
        while($farmer = $farmers->fetch_assoc()): 
        ?>
        <tr>
            <td><?php echo $counter++; ?></td>
            <td><?php echo htmlspecialchars($farmer['farmer_id']); ?></td>
            <td><?php echo htmlspecialchars($farmer['full_name']); ?></td>
            <td><?php echo htmlspecialchars($farmer['region']); ?></td>
            <td><?php echo htmlspecialchars($farmer['district']); ?></td>
            <td><?php echo htmlspecialchars($farmer['kebele']); ?></td>
            <td><?php echo htmlspecialchars($farmer['phone']); ?></td>
            <td><?php echo number_format($farmer['farm_size'], 2); ?></td>
            <td><?php echo htmlspecialchars($farmer['crop_type']); ?></td>
            <td><?php echo htmlspecialchars($farmer['harvest_season']); ?></td>
            <td><?php echo ucfirst($farmer['verification_status']); ?></td>
            <td><?php echo date('d/m/Y', strtotime($farmer['created_at'])); ?></td>
        </tr>
        <?php endwhile; ?>
        <?php if($farmers->num_rows == 0): ?>
        <tr>
            <td colspan="12" style="text-align: center;">No farmers found</td>
        </tr>
        <?php endif; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="12" style="background-color: #f0f0f0;">
                Total Farmers: <?php echo $farmers->num_rows; ?>
            </td>
        </tr>
    </tfoot>
</table>
<?php $conn->close(); ?>