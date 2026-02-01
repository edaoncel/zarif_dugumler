<?php
// Oturum başlatma (Admin kontrolü yapıldığı için gereklidir)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
// Bağlantıyı dahil edin. Bu dosyanın $pdo değişkenini oluşturduğunu varsayıyoruz.
include_once "../../db/db.php"; 


// ----------------------------------------------------------------------
// 1. VERİ ÇEKME FONKSİYONU
// ----------------------------------------------------------------------

function getAdminFavoritesData() {
    global $pdo; // Bağlantı değişkeni $pdo
    $favorites = [];
    
    // YENİ SQL SORGUSU: productimages tablosundan ana resmi çekmek için LEFT JOIN kullanıldı.
    $sql = "
        SELECT 
            f.id AS favorite_id, 
            u.ad, u.soyad,   
            p.name AS product_name, 
            p.id AS product_id,
            pi.imageUrl      -- Resmi productimages tablosundan alıyoruz
        FROM favorites f
        JOIN users u ON f.userId = u.id  
        JOIN product p ON f.productId = p.id 
        LEFT JOIN (                                  -- Ana resmi almak için alt sorgu
            SELECT productId, imageUrl 
            FROM productimages 
            GROUP BY productId 
            ORDER BY id ASC
        ) pi ON pi.productId = p.id
        ORDER BY f.id DESC
    ";

    try {
        $stmt = $pdo->query($sql);
        $favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Hata ayıklama loglaması
        error_log("Favori verileri çekilirken hata oluştu: " . $e->getMessage());
    }
    return $favorites;
}


// ----------------------------------------------------------------------
// 2. SİLME FONKSİYONU
// ----------------------------------------------------------------------

function deleteFavorite($favoriteId) {
    global $pdo; // DÜZELTİLDİ: $db yerine $pdo kullanıldı
    
    $sql = "DELETE FROM favorites WHERE id = :id";
    
    try {
        $stmt = $pdo->prepare($sql); // DÜZELTİLDİ: $db yerine $pdo kullanıldı
        $stmt->bindParam(':id', $favoriteId, PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return ['status' => 'success', 'message' => 'Favori kaydı başarıyla silindi.'];
        } else {
            return ['status' => 'error', 'message' => 'Silinecek favori kaydı bulunamadı.'];
        }
    } catch (PDOException $e) {
        error_log("Favori silinirken hata oluştu: " . $e->getMessage());
        return ['status' => 'error', 'message' => 'Veritabanı hatası.'];
    }
}

// ----------------------------------------------------------------------
// 3. AJAX İŞLEME BLOĞU (SİLME İSTEĞİ)
// ----------------------------------------------------------------------

if (isset($_POST['action']) && $_POST['action'] === 'delete' && isset($_POST['favorite_id'])) {
    // Admin kontrolü, AJAX isteklerinde yetkisiz erişimi engeller
    if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Yetkisiz erişim.']);
        exit;
    }

    $favoriteId = (int)$_POST['favorite_id'];
    $result = deleteFavorite($favoriteId);
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit; // KRİTİK: AJAX isteği bittiğinde çıkış yap.
}

// ----------------------------------------------------------------------
// 4. VERİ ÇEKME BLOĞU (SAYFAYA DAHİL EDİLİNCE)
// ----------------------------------------------------------------------

// Eğer bu dosya (favoritesController.php) bir view dosyasına (favorites.php)
// dahil edildiyse, tabloyu doldurmak için veriyi çeker.
$favoritesData = getAdminFavoritesData();

?>