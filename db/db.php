<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3307;dbname=sinem;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage()); 
    $pdo = null; 
}