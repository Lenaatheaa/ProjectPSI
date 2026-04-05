<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Database connection
$servername = "127.0.0.1";
$username = "root";
$password = "";
$dbname = "jalanyukproject";

try {
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query untuk mendapatkan distribusi status grup
    $statusQuery = "SELECT status, COUNT(*) as count FROM groups GROUP BY status";
    $statusStmt = $pdo->prepare($statusQuery);
    $statusStmt->execute();
    $statusData = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query untuk statistik grup
    $statsQuery = "SELECT 
        COUNT(*) as total_groups,
        COUNT(CASE WHEN status = 'active' THEN 1 END) as active_groups,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_groups,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_groups,
        COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_groups,
        AVG(target_amount) as avg_target,
        MAX(target_amount) as max_target,
        SUM(target_amount) as total_target
    FROM groups";
    
    $statsStmt = $pdo->prepare($statsQuery);
    $statsStmt->execute();
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
    
    // Query untuk mendapatkan grup dengan target tertinggi
    $maxGroupQuery = "SELECT name, target_amount FROM groups ORDER BY target_amount DESC LIMIT 1";
    $maxGroupStmt = $pdo->prepare($maxGroupQuery);
    $maxGroupStmt->execute();
    $maxGroup = $maxGroupStmt->fetch(PDO::FETCH_ASSOC);
    
    // Format data untuk response
    $response = [
        'status_distribution' => $statusData,
        'stats' => [
            'total_groups' => (int)$stats['total_groups'],
            'active_groups' => (int)$stats['active_groups'],
            'completed_groups' => (int)$stats['completed_groups'],
            'cancelled_groups' => (int)$stats['cancelled_groups'],
            'inactive_groups' => (int)$stats['inactive_groups'],
            'avg_target' => number_format($stats['avg_target'], 0, ',', '.'),
            'max_target' => number_format($stats['max_target'], 0, ',', '.'),
            'total_target' => number_format($stats['total_target'], 0, ',', '.'),
            'max_group_name' => $maxGroup['name'] ?? 'N/A'
        ]
    ];
    
    echo json_encode($response);
    
} catch(PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>