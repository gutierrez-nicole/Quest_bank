<?php
require_once __DIR__ . '/../app/bootstrap.php';
$pdo = getDBConnection();
$sql = file_get_contents('php://stdin');
if (empty($sql)) {
    echo json_encode([]);
    exit;
}
$stmt = $pdo->query($sql);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
