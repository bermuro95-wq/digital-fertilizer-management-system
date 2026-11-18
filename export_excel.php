<?php
// Start session but don't output anything before headers
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';

// Get filter parameters
$filter_region = isset($_GET['region']) ? $_GET['region'] : '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

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

// Execute query
$conn = getConnection();
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers for Excel download - MUST be before any output
header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
header('Content-Disposition: attachment; filename="farmers_report_' . date('Y-m-d') . '.xls"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create the Excel content
echo '<html>';
echo '<head>';
echo '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">';
echo '</head>';
echo '<body>';
echo '<table border="1">';
echo '<thead>';
echo '<tr style="background-color: #4CAF50; color: white;">';
echo '<th colspan="12" style="font-size: 16px;">Digital Fertilizer Management System - Farmers Report</th>';
echo '</tr>';
echo '<tr style="background-color: #f0f0f0;">';
echo '<th colspan="12">Generated on: ' . date('F d, Y h:i A');
if(!empty($filter_region)) echo ' | Region: ' . htmlspecialchars($filter_region);
if(!empty($filter_status)) echo ' | Status: ' . htmlspecialchars($filter_status);
echo '</th>';
echo '</tr>';
echo '<tr style="background-color: #ddd;">';
echo '<th>#</th>';
echo '<th>Farmer ID</th>';
echo '<th>Full Name</th>';
echo '<th>Region</th>';
echo '<th>District</th>';
echo '<th>Kebele</th>';
echo '<th>Phone</th>';
echo '<th>Farm Size (ha)</th>';
echo '<th>Crop Type</th>';
echo '<th>Harvest Season</th>';
echo '<th>Status</th>';
echo '<th>Registration Date</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$counter = 1;
if ($result && $result->num_rows > 0) {
    while($farmer = $result->fetch_assoc()) {
        echo '<tr>';
        echo '<td>' . $counter++ . '</td>';
        echo '<td>' . htmlspecialchars($farmer['farmer_id']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['full_name']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['region']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['district']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['kebele']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['phone']) . '</td>';
        echo '<td>' . number_format($farmer['farm_size'], 2) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['crop_type']) . '</td>';
        echo '<td>' . htmlspecialchars($farmer['harvest_season']) . '</td>';
        echo '<td>' . ucfirst($farmer['verification_status']) . '</td>';
        echo '<td>' . date('d/m/Y', strtotime($farmer['created_at'])) . '</td>';
        echo '</tr>';
    }
} else {
    echo '<tr>';
    echo '<td colspan="12" align="center">No farmers found</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '<tfoot>';
echo '<tr style="background-color: #f0f0f0;">';
echo '<td colspan="12">Total Farmers: ' . ($result ? $result->num_rows : 0) . '</td>';
echo '</tr>';
echo '</tfoot>';
echo '</table>';
echo '</body>';
echo '</html>';

// Close connection
$stmt->close();
$conn->close();
exit();
?>