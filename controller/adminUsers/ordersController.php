<?php
// ordersController.php

// Oturum ve Veritabanı bağlantısı
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// db.php'yi dahil etmeliyiz. Gerçek yolu projenize göre düzeltiniz.
include_once "../../db/db.php"; 

// --- GÜVENLİK KONTROLÜ (AJAX ve POST İşlemleri İçin) ---
global $pdo; 

$is_ajax_request = isset($_POST['action']) || isset($_GET['action']);

if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
    if ($is_ajax_request) {
        http_response_code(403); 
        echo json_encode(['success' => false, 'message' => 'Yetkisiz İşlem.']);
        exit;
    }
}

// Sipariş durumu listesi
$controllerOrderStatuses = ['Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled'];


/**
 * Siparişleri çeker (Basitleştirilmiş Liste Görünümü).
 */
function getOrders($pdo, $filters = []) {
    $sql = "
        SELECT 
            o.*, 
            CONCAT(u.ad, ' ', u.soyad) AS fullName 
        FROM orders o
        INNER JOIN users u ON o.userId = u.id
        ORDER BY o.orderDate DESC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute(); 
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function getOrderDetails($pdo, $orderId) {
    if (!is_numeric($orderId) || $orderId <= 0) { 
        error_log("getOrderDetails: Geçersiz Sipariş ID formatı verildi: $orderId");
        return false; 
    } 

    try {
        // --- 1. Ana Sipariş, Kullanıcı ve Teslimat Adresi Bilgilerini Çekme (Değişmedi) ---
        // ... (Bu kısım aynı kalır)

        $sqlOrder = "
            SELECT 
                o.id AS orderId,
                o.userId,
                o.totalAmount, 
                o.status, 
                o.orderDate,
                o.paymentMethod,
                o.shippingCompany,
                o.trackingNumber,
                o.invoiceNote,
                o.shippingNote,
                
                CONCAT(u.ad, ' ', u.soyad) AS fullName,    -- ESKİ İSİM
                u.email AS email,                          -- ESKİ İSİM
                
                a.title AS addressTitle,
                a.fullname AS addressFullname,
                a.phone AS addressPhone,
                a.city AS addressCity,
                a.district AS addressDistrict,
                a.addressDetail AS addressDetail,
                a.zipCode AS addressZipCode
                
            FROM orders o 
            LEFT JOIN users u ON o.userId = u.id
            LEFT JOIN address a ON o.addressId = a.id
            WHERE o.id = :orderId
        ";
        
        $stmtOrder = $pdo->prepare($sqlOrder);
        // ... (Hazırlama ve çalıştırma kodları)
        $stmtOrder->execute([':orderId' => $orderId]);
        $order = $stmtOrder->fetch(PDO::FETCH_ASSOC);

        if (!$order) { 
            error_log("getOrderDetails: Sipariş ID $orderId veritabanında bulunamadı.");
            return false; 
        } 
        
$sqlItems = "
        SELECT 
            oi.productId,
            oi.quantity, 
            oi.price AS price, 
            p.name AS productName,
            p.mainSku,
            p.shippingFee,
            
            -- VARYANT BİLGİLERİ productvariants tablosundan çekildi
            pv.color, 
            pv.size,
            
            -- Grup içinde en düşük sortOrder'a sahip resmin URL'sini çek
            SUBSTRING_INDEX(GROUP_CONCAT(pi.imageUrl ORDER BY pi.sortOrder ASC), ',', 1) AS imageUrl
            
        FROM orderitems oi
        
        -- KRİTİK EKLENTİ: productvariants tablosu ile birleşim (oi.variantId kullanılmalı)
        LEFT JOIN productvariants pv ON oi.variantId = pv.id  
        
        JOIN product p ON oi.productId = p.id
        
        -- productImages tablosuna sol birleşim yapıyoruz
        LEFT JOIN productImages pi ON p.id = pi.productId 
        
        WHERE oi.orderId = :orderId
        
        -- Her bir ürün için ayrı bir satır döndür
        GROUP BY 
            oi.id, oi.productId, oi.quantity, oi.price, pv.color, pv.size, 
            p.name, p.mainSku, p.shippingFee
    ";

        $stmtItems = $pdo->prepare($sqlItems);

        if (!$stmtItems) {
            error_log("getOrderDetails: PDO Statement (Sipariş Kalemleri) hazırlanamadı!");
            $order['items'] = [];
            return $order;
        }

        $stmtItems->execute([':orderId' => $orderId]);
        $orderItems = $stmtItems->fetchAll(PDO::FETCH_ASSOC);
        
        // Ana sipariş dizisine ürünleri ekle
        $order['items'] = $orderItems;
        
        // ... (Adres bilgilerini düzenleme ve unset kısmı aynı kalır)
        $order['addressDetail'] = [
            'title' => $order['addressTitle'] ?? null,
            'fullname' => $order['addressFullname'] ?? null,
            'phone' => $order['addressPhone'] ?? null,
            'city' => $order['addressCity'] ?? null,
            'district' => $order['addressDistrict'] ?? null,
            'detail' => $order['addressDetail'] ?? null,
            'zipCode' => $order['addressZipCode'] ?? null,
        ];

        unset(
            $order['addressTitle'], 
            $order['addressFullname'], 
            $order['addressPhone'], 
            $order['addressCity'], 
            $order['addressDistrict'], 
            $order['addressDetail'], 
            $order['addressZipCode']
        );
        
        return $order;

    } catch (PDOException $e) {
        error_log("getOrderDetails (SQL) Hatası: " . $e->getMessage()); 
        return false; 
    } catch (Exception $e) {
        error_log("getOrderDetails (Genel) Hatası: " . $e->getMessage()); 
        return false;
    }
}


function getOrderAddress($pdo, $orderId) {
    
    // orders tablosundan addressId'yi ve userId'yi çek
    $stmt = $pdo->prepare("SELECT addressId, userId FROM orders WHERE id = :orderId");
    $stmt->execute([':orderId' => $orderId]);
    $orderData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Adres ID'si yoksa veya sipariş yoksa null döndür
    if (!$orderData || !$orderData['addressId']) {
        return null; 
    }

    // address tablosundan adresi çek (addressId üzerinden)
    $stmtAddress = $pdo->prepare("
        SELECT * FROM address 
        WHERE id = :addressId
    ");
    // Kullanıcı ID kontrolünü yapmamayı tercih ediyoruz, çünkü orders tablosunda addressId varsa 
    // yöneticinin bu adresi görmesi gerekir, kullanıcı hatası yüzünden değil.
    $stmtAddress->execute([
        ':addressId' => $orderData['addressId']
    ]);
    
    return $stmtAddress->fetch(PDO::FETCH_ASSOC);
}

// ----------------------------------------------------------------------------------
// --- GEREKSİZ FONKSİYONLAR (orderDetail.php'deki hatayı gidermek için eklenmiştir) ---
// ----------------------------------------------------------------------------------

/**
 * getOrderDetails fonksiyonu içinde ürünler çekildiği için bu artık gereksizdir.
 * orderDetail.php'de bu satırı yorumdan çıkarabilirsiniz.
 */
// function getOrderItemsForDetail($pdo, $orderId) {
//return getOrderDetails($pdo, $orderId)['items'] ?? [];
// }


// ----------------------------------------------------------------------------------
// --- DİĞER FONKSİYONLAR --- 
// ----------------------------------------------------------------------------------

function getProductOrderStatusCounts($pdo, $productId) {
    global $controllerOrderStatuses;
    $counts = array_fill_keys($controllerOrderStatuses, 0);

    if (!$productId) { return $counts; }
    // ... fonksiyon içeriği ...
}


/**
 * Sipariş durumu güncelleme (AJAX çağrısı için kullanılır)
 */
function updateOrderStatus($pdo, $orderId, $newStatus) {
    global $controllerOrderStatuses;
    
    // 1. Durumun geçerli olup olmadığını kontrol et
    if (!in_array($newStatus, $controllerOrderStatuses)) {
        return ['success' => false, 'message' => 'Geçersiz sipariş durumu.'];
    }

    // 2. KRİTİK ADIM: Durumu veritabanında güncelle
    $sql = "
        UPDATE orders 
        SET status = :newStatus
        WHERE id = :orderId
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':newStatus' => $newStatus,
            ':orderId' => $orderId
        ]);

        if ($stmt->rowCount() > 0) {
            // Başarılı güncelleme
            return ['success' => true, 'message' => 'Durum başarıyla güncellendi.'];
        } else {
            // Sipariş ID bulunamadı veya durum zaten aynıydı
            return ['success' => false, 'message' => 'Sipariş bulunamadı veya durum zaten günceldi.'];
        }
    } catch (PDOException $e) {
        // SQL veya veritabanı hatası
        error_log("Durum Güncelleme Hatası (ID: $orderId): " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()];
    }
}


// --- AJAX İŞLEYİCİ KISMI ---
if (isset($_POST['action']) && $_POST['action'] === 'update_status') {
    header('Content-Type: application/json');
    
    $orderId = $_POST['order_id'] ?? null;
    $newStatus = $_POST['new_status'] ?? null;
    
    if (!$orderId || !$newStatus || !is_numeric($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz parametre.']);
        exit;
    }

    $orderId = (int)$orderId;
    
    $result = updateOrderStatus($pdo, $orderId, $newStatus);
    echo json_encode($result);
    exit;
}

// ordersController.php içine eklenecek kısım

// ... (Mevcut updateOrderStatus fonksiyonunun hemen altına ekleyin) ...

/**
 * Sipariş detay bilgilerini (Notlar, Kargo Firması, Takip No) günceller.
 */
function updateOrderDetails($pdo, $orderId, $shippingCompany, $trackingNumber, $invoiceNote, $shippingNote) {
    
    // Gelen verileri temizle
    $shippingCompany = trim($shippingCompany);
    $trackingNumber = trim($trackingNumber);
    // Notlar için daha güvenli bir filtreleme yapabilirsiniz, ancak TEXT alanı olduğu için trim yeterli
    $invoiceNote = trim($invoiceNote); 
    $shippingNote = trim($shippingNote);

    $sql = "
        UPDATE orders 
        SET 
            shippingCompany = :shippingCompany,
            trackingNumber = :trackingNumber,
            invoiceNote = :invoiceNote,
            shippingNote = :shippingNote
        WHERE id = :orderId
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':shippingCompany', $shippingCompany);
        $stmt->bindParam(':trackingNumber', $trackingNumber);
        $stmt->bindParam(':invoiceNote', $invoiceNote);
        $stmt->bindParam(':shippingNote', $shippingNote);
        $stmt->bindParam(':orderId', $orderId, PDO::PARAM_INT);
        $stmt->execute();

        // Her zaman bir satır etkilenecek (güncelleme olsa da olmasa da), bu yüzden success true döndürelim
        // Veri değişmediyse rowCount 0 olabilir, ama biz işlemi başarılı sayıyoruz.
        return ['success' => true, 'message' => 'Sipariş detayları başarıyla kaydedildi.'];
    } catch (PDOException $e) {
        error_log("Detay Güncelleme Hatası (ID: $orderId): " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası: ' . $e->getMessage()];
    }
}


// --- AJAX İŞLEYİCİ KISMI ---

// ... (Mevcut 'update_status' AJAX işleyicisinin altına, yeni bir 'update_details' işleyicisi ekleyin) ...

if (isset($_POST['action']) && $_POST['action'] === 'update_details') {
    header('Content-Type: application/json');
    
    $orderId = $_POST['order_id'] ?? null;
    
    if (!$orderId || !is_numeric($orderId)) {
        echo json_encode(['success' => false, 'message' => 'Eksik veya geçersiz Sipariş ID.']);
        exit;
    }

    // Yeni Alanlar
    $shippingCompany = $_POST['shipping_company'] ?? null;
    $trackingNumber = $_POST['tracking_number'] ?? null;
    $invoiceNote = $_POST['invoice_note'] ?? null;
    $shippingNote= $_POST['shipping_note'] ?? null;

    $orderId = (int)$orderId;
    
    $result = updateOrderDetails($pdo, $orderId, $shippingCompany, $trackingNumber, $invoiceNote, $shippingNote);
    echo json_encode($result);
    exit;
}

// ... (Mevcut 'update_status' AJAX işleyicisi bu bloğun üstünde kalacak) ...

// ... (Kalan PHP kodu) ...
?>