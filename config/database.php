<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fertilizer_system');

// Create connection
function getConnection() {
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Connection failed: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        return $conn;
    } catch (Exception $e) {
        die("Database connection error: " . $e->getMessage());
    }
}

// Execute query with prepared statement
function executeQuery($sql, $params = [], $types = "") {
    $conn = getConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    $conn->close();
    
    return $result;
}

// Execute update/insert/delete query
function executeUpdate($sql, $params = [], $types = "") {
    $conn = getConnection();
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        return ['success' => false, 'error' => $conn->error];
    }
    
    if (!empty($params)) {
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        $stmt->bind_param($types, ...$params);
    }
    
    $result = $stmt->execute();
    $affected = $stmt->affected_rows;
    $insert_id = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    return ['success' => $result, 'affected' => $affected, 'insert_id' => $insert_id];
}

// Get single row
function getRow($sql, $params = [], $types = "") {
    $result = executeQuery($sql, $params, $types);
    if ($result && $result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Get single value
function getValue($sql, $params = [], $types = "") {
    $row = getRow($sql, $params, $types);
    if ($row) {
        return reset($row);
    }
    return null;
}
?>