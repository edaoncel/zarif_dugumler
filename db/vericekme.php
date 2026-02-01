<?php
include_once "../db/db.php"; 

header('Content-Type: application/json; charset=utf-8');

try {
    $stmt = $pdo->query("SELECT * FROM adminUsers ORDER BY id");
    $veriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($veriler, JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    echo json_encode(['hata' => $e->getMessage()]);
}
