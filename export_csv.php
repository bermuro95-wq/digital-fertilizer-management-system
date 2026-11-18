<?php
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

// Execute query
$conn = getConnection();
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="farmers_report_' . date('Y-m-d') . '.csv"');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Pragma: public');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'ID',
    'Farmer ID', 
    'Full Name',
    'Region',
    'District',
    'Kebele',
    'Phone',
    'Farm Size (ha)',
    'Crop Type',
    'Harvest Season',
    'Status',
    'Registration Date'
]);

// Add data
$counter = 1;
if ($result && $result->num_rows > 0) {
    while($farmer = $result->fetch_assoc()) {
        fputcsv($output, [
            $counter++,
            $farmer['farmer_id'],
            $farmer['full_name'],
            $farmer['region'],
            $farmer['district'],
            $farmer['kebele'],
            $farmer['phone'],
            $farmer['farm_size'],
            $farmer['crop_type'],
            $farmer['harvest_season'],
            ucfirst($farmer['verification_status']),
            date('d/m/Y', strtotime($farmer['created_at']))
        ]);
    }
} else {
    fputcsv($output, ['No farmers found', '', '', '', '', '', '', '', '', '', '', '']);
}

fclose($output);
$stmt->close();
$conn->close();
exit();
?>