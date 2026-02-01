<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1); 

ob_start();

date_default_timezone_set('Europe/Istanbul');

// Veritabanı bağlantısını dahil et
include_once "../../db/db.php"; 

// PHPMailer dosyalarını dahil et (Gerekli ise)
require '../../includes/PHPMailer/src/Exception.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';


// =======================================================
// BÖLÜM 2: PROFİL FOTOĞRAFI SİLME İŞLEMİ (MEVCUT)
// =======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_photo') {
    
    $admin_id = $_POST['admin_id'] ?? 0;

    if ($admin_id == 0) {
        $_SESSION['hata_mesaji'] = "Geçersiz istek.";
        header("Location: ../../php/adminUsers/admin.php");
        exit;
    }

    try {
        global $pdo;
        
        // 1. Eski dosya yolunu veritabanından çek (fiziksel silme için)
        $stmt_select = $pdo->prepare("SELECT adminPhoto FROM adminUsers WHERE id = :id");
        $stmt_select->bindParam(':id', $admin_id, PDO::PARAM_INT);
        $stmt_select->execute();
        $old_path = $stmt_select->fetchColumn();

        // 2. Veritabanını güncelle (yolu NULL yap)
        $stmt_update = $pdo->prepare("UPDATE adminUsers SET adminPhoto = NULL WHERE id = :id");
        $stmt_update->bindParam(':id', $admin_id, PDO::PARAM_INT);
        $stmt_update->execute();

        // 3. Fiziksel dosyayı sunucudan sil (Eğer varsa)
        if ($old_path && file_exists($old_path)) {
            unlink($old_path);
        }
        
        $_SESSION['basari_mesaji'] = "Profil fotoğrafınız başarıyla silindi.";
        $_SESSION['admin_profil_fotolari'] = null;

    } catch (Exception $e) {
        $_SESSION['hata_mesaji'] = "Fotoğraf silinirken bir veritabanı hatası oluştu.";
        error_log("DB Hata (Silme): " . $e->getMessage());
    }
    
    // Admin paneline yönlendir
    header("Location: ../../php/adminUsers/admin.php"); 
    exit;
}


// =======================================================
// BÖLÜM 3: PROFİL FOTOĞRAFI YÜKLEME VE GÜNCELLEME İŞLEMİ (MEVCUT)
// =======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_photo') {
    
    $admin_id = $_POST['admin_id'] ?? 0;
    
    if ($admin_id == 0 || !isset($_FILES['admin_photo']) || $_FILES['admin_photo']['error'] !== 0) {
        $_SESSION['hata_mesaji'] = "Geçersiz istek veya dosya yüklenemedi.";
        header("Location: ../../php/adminUsers/admin.php");
        exit;
    }

    $file = $_FILES['admin_photo'];
    $file_tmp = $file['tmp_name'];
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif'];
    $max_size = 5 * 1024 * 1024; // 5 MB Maksimum Boyut

    if (!in_array($file_ext, $allowed_ext)) {
        $_SESSION['hata_mesaji'] = "Sadece JPG, JPEG, PNG ve GIF dosyaları izinlidir.";
    } elseif ($file['size'] > $max_size) {
        $_SESSION['hata_mesaji'] = "Dosya boyutu 5MB'ı geçemez.";
    } else {
        
        // --- Dosyayı Kaydetme ---
        $upload_dir = '../../img/adminUsers/'; 
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true); 
        }
        
        // Benzersiz dosya adı oluşturma
        $new_file_name = $admin_id . '_' . time() . '.' . $file_ext;
        $target_file = $upload_dir . $new_file_name;
        
        // VERİTABANI YOLU
        $db_path = "../../img/adminUsers/" . $new_file_name;

        if (move_uploaded_file($file_tmp, $target_file)) {
            
            // --- Veritabanını Güncelleme ---
            try {
                global $pdo;
                
                $stmt = $pdo->prepare("UPDATE adminUsers SET adminPhoto = :path WHERE id = :id");
                $stmt->bindParam(':path', $db_path);
                $stmt->bindParam(':id', $admin_id, PDO::PARAM_INT);
                $stmt->execute();
                
                $_SESSION['basari_mesaji'] = "Profil fotoğrafı başarıyla güncellendi!";
                $_SESSION['admin_profil_fotolari'] = $db_path;

            } catch (Exception $e) {
                $_SESSION['hata_mesaji'] = "Veritabanı güncelleme hatası.";
                error_log("DB Hata: " . $e->getMessage());
            }
        } else {
            $_SESSION['hata_mesaji'] = "Dosya sunucuya taşınamadı.";
        }
    }
    
    // Admin paneline yönlendir
    header("Location: ../../php/adminUsers/admin.php");
    exit;
}


// =======================================================
// BÖLÜM 4: YÖNETİCİ BİLGİSİNİ ÇEKME VE OTURUMA ATAMA (MEVCUT)
// =======================================================

if (isset($_SESSION['admin_loggedIn']) && $_SESSION['admin_loggedIn'] === true) {
    
    $admin_id = $_SESSION['admin_id'] ?? 0;

    try {
        global $pdo; 
        
        $sql = "
            SELECT 
                ad, 
                soyad, 
                adminPhoto
            FROM adminUsers
            WHERE id = :admin_id
        ";

        $stmt = $pdo->prepare($sql); 
        $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin) {
            $full_name = htmlspecialchars($admin['ad'] . ' ' . $admin['soyad']);
            $_SESSION['admin_full_name'] = $full_name;
            $_SESSION['admin_profil_fotolari'] = $admin['adminPhoto'] ?? null; 
        } else {
            $_SESSION['admin_full_name'] = "Yönetici";
            $_SESSION['admin_profil_fotolari'] = null;
        }

    } catch (Exception $e) {
        error_log("Admin veri çekme hatası: " . $e->getMessage());
        $_SESSION['admin_full_name'] = "Veritabanı Hatası";
    }
} else {
    $_SESSION['admin_full_name'] = "Misafir";
    $_SESSION['admin_profil_fotolari'] = null;
}

ob_end_flush();
?>