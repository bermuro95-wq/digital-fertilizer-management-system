<?php
require_once 'config/session.php';
require_once 'config/database.php';
requireLogin();

// Check if user has permission to export
if (!isAdmin() && !isOfficer()) {
    header('Location: index.php');
    exit();
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=farmers_export_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Headers
fputcsv($output, [
    'ID', 'Farmer ID', 'Full Name', 'Region', 'District', 'Kebele', 
    'Farm Size (ha)', 'Crop Type', 'Harvest Season', 'Phone', 
    'Verification Status', 'Registration Date'
]);

// Get data
$conn = getConnection();
$result = $conn->query("SELECT * FROM farmers ORDER BY created_at DESC");

while($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['id'],
        $row['farmer_id'],
        $row['full_name'],
        $row['region'],
        $row['district'],
        $row['kebele'],
        $row['farm_size'],
        $row['crop_type'],
        $row['harvest_season'],
        $row['phone'],
        $row['verification_status'],
        $row['created_at']
    ]);
}

$conn->close();
fclose($output);
exit();
?>