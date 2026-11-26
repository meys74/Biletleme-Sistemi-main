<?php
require_once 'config.inc.php';
require_once 'dbHandler.inc.php'; // db.php yerine projedeki ana bağlantı dosyasını kullandık

$sql = "SELECT 
            t.ticket_ID AS ticket_id,
            t.ticket_title,
            t.ticket_description,
            t.ticket_status,
            t.ticket_priority,
            t.ticket_created_at,
            u.username AS customer_name,
            d.department_name AS department_name
        FROM tickets t
        LEFT JOIN users u ON t.customer_ID = u.user_ID
        LEFT JOIN departments d ON t.department_ID = d.department_ID
        WHERE 1=1";

$types = "";
$params = [];

// Arama Çubuğu (Search) Kontrolü
if (!empty($_GET['search'])) {
    $searchTerm = trim($_GET['search']);
    $sql .= " AND (t.ticket_title LIKE ? 
               OR t.ticket_description LIKE ? 
               OR u.username LIKE ? 
               OR t.ticket_ID = ?)";
               
    $likeSearch = '%' . $searchTerm . '%';
    $types .= "sssi";
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = $likeSearch;
    $params[] = intval($searchTerm);
}

// Status Filtresi Kontrolü
if (!empty($_GET['status'])) {
    $status = trim($_GET['status']);
    if (strtolower($status) == 'in process' || strtolower($status) == 'in proccess') {
        $sql .= " AND (t.ticket_status = 'in process' OR t.ticket_status = 'in proccess' OR t.ticket_status = 'In Progress')";
    } else {
        $sql .= " AND t.ticket_status = ?";
        $types .= "s";
        $params[] = $status;
    }
}

// Department Filtresi Kontrolü
if (!empty($_GET['department'])) {
    $sql .= " AND t.department_ID = ?";
    $types .= "i";
    $params[] = intval($_GET['department']);
}

$sql .= " ORDER BY t.ticket_created_at DESC";

// MySQLi Prepared Statement Çalıştırma
$stmt = mysqli_prepare($conn, $sql);

if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types, ...$params);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$tickets = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
?>