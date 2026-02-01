<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// db/db.php dosyanızın yolu doğru varsayılmıştır.
include_once "../../db/db.php"; 

global $pdo; 

/**
 * Belirli bir kullanıcının temel detaylarını çeker.
 */
function getUserDetails($pdo, $userId) {
    if (!is_numeric($userId)) {
        return ['error' => 'Geçersiz kullanıcı ID.'];
    }
    try {
        $sql = "SELECT id, ad, soyad, email, status, userPhoto FROM users WHERE id = :id"; 
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUserDetails Hatası: " . $e->getMessage());
        return ['error' => 'Veritabanı hatası.'];
    }
}

/**
 * Kullanıcının tüm kayıtlı adreslerini çeker.
 */
function getUserAddresses($pdo, $userId) {
    if (!is_numeric($userId)) {
        return [];
    }
    try {
        $sql = "SELECT * FROM address WHERE userId = :id ORDER BY isDefault DESC, id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUserAddresses Hatası: " . $e->getMessage());
        return []; 
    }
}

/**
 * Kullanıcının sipariş listesini çeker (Özet ve toplam harcama için).
 */
function getUserOrdersList($pdo, $userId) {
  try {
    // GÜNCELLEME: trackingNumber sütunu eklendi
    $sql = "SELECT id, totalAmount, status, orderDate, trackingNumber FROM orders WHERE userId = :id ORDER BY orderDate DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  } catch (PDOException $e) {
    error_log("getUserOrdersList Hatası: " . $e->getMessage());
    return [];
  }
}

/**
 * Kullanıcının tüm siparişlerindeki ürün detaylarını çeker ve sipariş bazında gruplar.
 * YENİLİK: Ürünün ana resmini productimages tablosundan çeker.
 */
function getUserOrderItems($pdo, $userId) {
    if (!is_numeric($userId)) {
        return [];
    }
    try {
        $sql = "
            SELECT 
                o.id AS orderId, 
                o.status AS orderStatus,
                oi.quantity, 
                oi.price AS itemPrice, 
                (oi.quantity * oi.price) AS totalItemPrice,
                p.name AS productName, 
                p.id AS productId,
                p.shippingFee AS productShippingFee,
                pv.color AS variantColor,
                pv.size AS variantSize,
                pi.imageUrl AS productImageUrl     -- productimages'tan çekiliyor
            FROM orders o
            JOIN orderitems oi ON o.id = oi.orderId 
            LEFT JOIN product p ON oi.productId = p.id     
            LEFT JOIN productvariants pv ON oi.variantId = pv.id
            LEFT JOIN (                                  -- Ana resmi almak için alt sorgu
                SELECT productId, imageUrl 
                FROM productimages 
                GROUP BY productId 
                ORDER BY id ASC
            ) pi ON pi.productId = p.id
            WHERE o.userId = :userId
            ORDER BY o.orderDate DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':userId' => $userId]);
        
        $groupedOrders = [];
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($items as $item) {
            $orderId = $item['orderId'];
            if (!isset($groupedOrders[$orderId])) {
                $groupedOrders[$orderId] = [
                    'orderId' => $orderId,
                    'orderStatus' => $item['orderStatus'],
                    'items' => []
                ];
            }
            $groupedOrders[$orderId]['items'][] = $item;
        }

        return array_values($groupedOrders);
    } catch (PDOException $e) {
        error_log("getUserOrderItems Hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcının favori ürünlerini çeker.
 * YENİLİK: Ürünün resmini productimages tablosundan çeker.
 */
function getUserFavoritesList($pdo, $userId) {
    try {
        $sql = "
            SELECT 
                p.id AS productId, 
                p.name, 
                pi.imageUrl         -- productimages'tan çekiliyor
            FROM favorites f 
            INNER JOIN product p ON f.productId = p.id 
            LEFT JOIN (                                  -- Ana resmi almak için alt sorgu
                SELECT productId, imageUrl 
                FROM productimages 
                GROUP BY productId 
                ORDER BY id ASC
            ) pi ON pi.productId = p.id
            WHERE f.userId = :id
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUserFavoritesList Hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Kullanıcı durumunu (aktif/pasif) günceller.
 */
function toggleUserStatus($pdo, $userId, $newStatus) {
    if (!in_array($newStatus, ['active', 'passive'])) {
        return ['success' => false, 'message' => 'Geçersiz durum.'];
    }

    try {
        $sql = "UPDATE users SET status = :status WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $userId);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $turkishStatus = ($newStatus == 'active') ? 'Aktif Müşteri' : 'Pasif Müşteri';
            $statusClass = ($newStatus == 'active') ? 'success' : 'danger';
            
            return [
                'success' => true, 
                'message' => 'Kullanıcı durumu başarıyla güncellendi.',
                'new_status' => $newStatus,
                'turkish_status' => $turkishStatus,
                'status_class' => $statusClass
            ];
        } else {
            return ['success' => false, 'message' => 'Kullanıcı bulunamadı veya durum zaten günceldi.'];
        }

    } catch (PDOException $e) {
        error_log("toggleUserStatus Hatası: " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası: İşlem tamamlanamadı.'];
    }
}

/**
 * Belirli bir siparişin toplam tutarını (ürünler + kargo) hesaplar.
 * NOT: Normalde bu değerin veritabanında (orders.totalAmount) tutulması gerekir.
 */
function calculateOrderTotal($pdo, $orderId) {
    if (!is_numeric($orderId)) {
        return 0;
    }
    
    try {
        $sql = "
            SELECT 
                (oi.quantity * oi.price) AS itemSubtotal,
                p.shippingFee
            FROM orderitems oi
            JOIN product p ON oi.productId = p.id
            WHERE oi.orderId = :orderId
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':orderId' => $orderId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = 0;
        foreach ($items as $item) {
            // Toplam Kalem Fiyatı (Adet * Birim Fiyat)
            $total += (float)$item['itemSubtotal'];
            // Kargo Ücreti
            $total += (float)$item['shippingFee'];
        }
        return $total;
        
    } catch (PDOException $e) {
        error_log("calculateOrderTotal Hatası: " . $e->getMessage());
        return 0;
    }
}

// ---------------------------------------------------------------------
// --- AJAX İŞLEYİCİ KISMI: Kullanıcı Durumunu Günceller ---
// ---------------------------------------------------------------------

if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
    }
    
    $userId = $_POST['user_id'] ?? null;
    $newStatus = $_POST['new_status'] ?? null;

    if (!$userId || !$newStatus || !is_numeric($userId)) {
        echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz parametre.']);
        exit;
    }

    $userId = (int)$userId;
    
    $result = toggleUserStatus($pdo, $userId, $newStatus);
    echo json_encode($result);
    exit;
}
?>