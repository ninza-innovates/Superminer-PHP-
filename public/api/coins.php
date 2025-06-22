<?php
// /var/www/html/superminer/public/api/coins.php
require_once '../../includes/config.php';
require_once '../../includes/api.php';

// Set JSON content type
header('Content-Type: application/json');

// Validate request origin (basic security)
// if (!isset($_SERVER['HTTP_REFERER']) || !str_contains($_SERVER['HTTP_REFERER'], 'aleksad')) {
//     http_response_code(403);
//     echo json_encode(['error' => 'Unauthorized']);
//     exit;
// }

// Fetch fresh data
$coins = fetchMiningData(true); // Force API refresh
echo json_encode([
    'data' => $coins,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>