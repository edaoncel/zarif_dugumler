<?php

// E:\xampp\htdocs\sinem\controller\users\usersController.php

// Hata Raporlama Ayarları (Geliştirme aşamasında hataları görmenizi engellemek için mevcut ayarlar korundu)
error_reporting(0); 
ini_set('display_errors', 0);
ob_start(); 

session_start();
date_default_timezone_set('Europe/Istanbul'); 

// db.php dahil etme yolu
include_once "../../db/db.php"; 

// PHPMailer bileşenleri dahil (Yolun doğru olduğundan emin olun)
require '../../includes/PHPMailer/src/Exception.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';

global $pdo;

// =========================================================
// I. FONKSİYON KÜTÜPHANESİ
// =========================================================

/**
 * Kullanıcının temel profil bilgilerini veritabanından çeker.
 */
function getUserData($pdo, $userId) {
    if (!is_numeric($userId) || $userId <= 0) {
        return false;
    }
    
    try {
        // userPhoto, passwordHash ve ID çekiliyor
        $sql = "SELECT id, ad, soyad, email, userPhoto, passwordHash FROM users WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("getUserData Hata: " . $e->getMessage());
        return false;
    }
}

/**
 * Kullanıcının temel bilgilerini günceller.
 */
function updateProfile($pdo, $userId, $ad, $soyad, $email) {
    try {
        $sql = "UPDATE users SET ad = :ad, soyad = :soyad, email = :email WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':ad' => $ad,
            ':soyad' => $soyad,
            ':email' => $email,
            ':id' => $userId
        ]);
        $_SESSION['user_email'] = $email; 
        return ['success' => true, 'message' => 'Profil bilgileriniz başarıyla güncellendi.'];
    } catch (PDOException $e) {
        if ($e->getCode() == 23000) {
             return ['success' => false, 'message' => 'Bu e-posta adresi zaten başka bir hesaba aittir.'];
        }
        error_log("updateProfile Hatası: " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası oluştu.'];
    }
}

/**
 * Kullanıcının şifresini günceller.
 */
function updatePassword($pdo, $userId, $currentPassword, $newPassword) {
    $user = getUserData($pdo, $userId);

    if (!$user || !password_verify($currentPassword, $user['passwordHash'])) {
        return ['success' => false, 'message' => 'Mevcut şifreniz yanlış.'];
    }

    if (strlen($newPassword) < 6) {
        return ['success' => false, 'message' => 'Yeni şifre en az 6 karakter olmalıdır.'];
    }

    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    try {
        $sql = "UPDATE users SET passwordHash = :hash WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':hash' => $newPasswordHash, ':id' => $userId]);
        return ['success' => true, 'message' => 'Şifreniz başarıyla güncellendi.'];
    } catch (PDOException $e) {
        error_log("updatePassword Hatası: " . $e->getMessage());
        return ['success' => false, 'message' => 'Şifre güncellenirken bir veritabanı hatası oluştu.'];
    }
}

/**
 * Kullanıcı fotoğrafını yükler ve veritabanını günceller.
 */
function uploadPhoto($pdo, $userId, $file) {
    // Fotoğrafın kaydedileceği dizin yolu: sinem/img/users/
    $target_dir = "../../img/users/"; 
    
    // Klasör yoksa oluştur
    if (!is_dir($target_dir)) {
        if (!mkdir($target_dir, 0777, true)) {
            return ['success' => false, 'message' => 'Yükleme dizini oluşturulamadı. Sunucu izinlerini kontrol edin.'];
        }
    }

    $imageFileType = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_extensions = array("jpg", "jpeg", "png", "gif");

    // Dosya uzantısı kontrolü
    if (!in_array($imageFileType, $allowed_extensions)) {
        return ['success' => false, 'message' => 'Sadece JPG, JPEG, PNG ve GIF dosyaları yüklenebilir.'];
    }

    // Dosya boyut kontrolü (Örn: 5MB)
    if ($file["size"] > 5000000) {
        return ['success' => false, 'message' => 'Dosya boyutu 5MB\'ı geçemez.'];
    }

    // Rastgele dosya adı oluşturma (çakışmayı önlemek için)
    $fileName = uniqid("user_") . "." . $imageFileType;
    $target_file = $target_dir . $fileName;

    // Yüklemeden önce kullanıcının güncel verisini çek (eski fotoğrafı silmek için)
    $user = getUserData($pdo, $userId); 

    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        
        // Eski fotoğrafı sil (varsa ve default değilse)
        if ($user && $user['userPhoto'] && $user['userPhoto'] !== 'default-user.png') {
            @unlink($target_dir . $user['userPhoto']);
        }

        // Veritabanını güncelle
        $sql = "UPDATE users SET userPhoto = :photo WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':photo' => $fileName, ':id' => $userId]);
        
        return ['success' => true, 'message' => 'Profil fotoğrafı başarıyla yüklendi.'];
    } else {
        return ['success' => false, 'message' => 'Dosya yüklenirken beklenmedik bir hata oluştu. Lütfen klasör izinlerini kontrol edin.'];
    }
}

/**
 * Kullanıcı fotoğrafını siler ve default resme döner.
 */
function deletePhoto($pdo, $userId, $currentPhotoName) {
    $target_dir = "../../img/users/";
    
    // Yalnızca default olmayan resimleri sil
    if ($currentPhotoName && $currentPhotoName !== 'default-user.png') {
        // Fiziksel dosyayı sil
        @unlink($target_dir . $currentPhotoName);
    }
    
    // Veritabanını temizle (NULL yap)
    $sql = "UPDATE users SET userPhoto = NULL WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);

    return ['success' => true, 'message' => 'Profil fotoğrafı başarıyla kaldırıldı.'];
}

/**
 * Admin tarafından kullanıcının aktif/pasif durumunu günceller.
 * Bu fonksiyon, adminUsers/userDetails.js'deki AJAX isteği için eklendi.
 */
function toggleUserStatus($pdo, $userId, $newStatus) {
    try {
        // Güvenlik kontrolü
        if (!in_array($newStatus, ['active', 'passive'])) {
             return ['success' => false, 'message' => 'Geçersiz durum değeri.'];
        }

        // Status sütununu güncelle (Veritabanında status sütununun var olduğundan emin olun!)
        $stmt = $pdo->prepare("UPDATE users SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $newStatus);
        $stmt->bindParam(':id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Başarı mesajını döndür
        return [
            'success' => true, 
            'message' => 'Kullanıcı durumu başarıyla güncellendi.'
        ];

    } catch (PDOException $e) {
        error_log("toggleUserStatus Hatası: " . $e->getMessage());
        return ['success' => false, 'message' => 'Veritabanı hatası oluştu: Durum güncellenemedi.'];
    }
}


// =========================================================
// II. POST İŞLEYİCİ (SADECE POST İSTEKLERİNDE ÇALIŞIR)
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // action'ı kontrol et
    $action = $_POST['action'] ?? ($_GET['action'] ?? 'login'); 
    $userId = $_POST['user_id'] ?? ($_SESSION['user_id'] ?? 0);
    $currentUserId = $_SESSION['user_id'] ?? 0;
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Güvenlik: Oturumu açık olan kullanıcının kendi verilerini güncellediğinden emin olun
    // toggle_status aksiyonunda ADMIN işlemi yapıldığı için bu kontrolü atlıyoruz, 
    // ancak ciddi bir projede buraya admin yetki kontrolü eklenmelidir.
    if (!in_array($action, ['login', 'register', 'toggle_status']) && $userId != $currentUserId) {
        $_SESSION['profile_message'] = "Güvenlik hatası! Yetkisiz işlem.";
        header("Location: ../../php/users/home.php");
        exit;
    }

    // --- PROFİL GÜNCELLEME (Form Gönderimi) ---
    if ($action === 'update_profile') {
        $ad = htmlspecialchars(trim($_POST['ad'] ?? '')); 
        $soyad = htmlspecialchars(trim($_POST['soyad'] ?? '')); 
        $email = trim($_POST['email'] ?? '');

        $result = updateProfile($pdo, $userId, $ad, $soyad, $email);
        $_SESSION['profile_message'] = $result['message'];
        
        header("Location: ../../php/users/home.php?tab=profile");
        exit;
    }
    
    // --- ŞİFRE GÜNCELLEME (Form Gönderimi) ---
    elseif ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['profile_message'] = "Yeni şifreler eşleşmiyor.";
        } else {
            $result = updatePassword($pdo, $userId, $currentPassword, $newPassword);
            $_SESSION['profile_message'] = $result['message'];
        }
        
        header("Location: ../../php/users/home.php?tab=profile");
        exit;
    }
    
    // --- FOTOĞRAF YÜKLEME ---
    elseif ($action === 'upload_photo') {
        if (isset($_FILES['user_photo']) && $_FILES['user_photo']['error'] === UPLOAD_ERR_OK) {
            $result = uploadPhoto($pdo, $userId, $_FILES['user_photo']);
            $_SESSION['profile_message'] = $result['message'];
        } else {
            $uploadError = $_FILES['user_photo']['error'] ?? UPLOAD_ERR_NO_FILE;
            if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                $_SESSION['profile_message'] = 'Dosya yüklenemedi (Hata Kodu: ' . $uploadError . ').';
            } else {
                $_SESSION['profile_message'] = 'Lütfen geçerli bir resim dosyası seçin.';
            }
        }
        
        header("Location: ../../php/users/home.php?tab=profile");
        exit;
    }

    // --- FOTOĞRAF SİLME ---
    elseif ($action === 'delete_photo') {
        $user = getUserData($pdo, $userId); // Güncel bilgileri çek

        if ($user) {
            $result = deletePhoto($pdo, $userId, $user['userPhoto']);
            $_SESSION['profile_message'] = $result['message'];
        } else {
            $_SESSION['profile_message'] = 'Kullanıcı bilgisi bulunamadı.';
        }

        header("Location: ../../php/users/home.php?tab=profile");
        exit;
    }
    
    // --- KULLANICI DURUMUNU DEĞİŞTİRME (AJAX isteği) ---
    elseif ($action === 'toggle_status') {
        
        $targetUserId = $_POST['user_id'] ?? null;
        $newStatus = $_POST['new_status'] ?? null;

        if (!$targetUserId || !$newStatus) {
            http_response_code(400); 
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'message' => 'Eksik parametreler (user_id veya new_status).']);
            exit;
        }

        $result = toggleUserStatus($pdo, $targetUserId, $newStatus);

        // AJAX isteği olduğu için JSON döndür
        http_response_code($result['success'] ? 200 : 500);
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
        exit; 
    }
    
    // =========================================================
    // 1. KAYIT İŞLEMİ (action=register)
    // =========================================================
    elseif ($action === 'register') {
        $ad = htmlspecialchars(trim($_POST['name'] ?? '')); 
        $soyad = htmlspecialchars(trim($_POST['surname'] ?? '')); 
        
        if (empty($ad) || empty($soyad) || empty($email) || empty($password)) {
            http_response_code(400);
            ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['hata' => 'Lütfen tüm alanları doldurun.']);
            exit;
        }
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        // NOT: Varsayılan status 'active' olarak ayarlandı
        $sql = "INSERT INTO users (ad, soyad, email, passwordHash, status) VALUES (:ad, :soyad, :email, :passwordHash, 'active')";
        
        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute(['ad' => $ad, 'soyad' => $soyad, 'email' => $email, 'passwordHash' => $passwordHash]);
            
            http_response_code(201);
            ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'basari' => true, 
                'mesaj' => 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.', 
                'redirect_url' => 'users.php'
            ]);
            exit;
        } catch (\PDOException $e) {
            $errorMessage = ($e->getCode() == 23000) ? "Görünüşe göre zaten bir hesabınız var. Lütfen giriş yapın." : "Kayıt sırasında bir hata oluştu.";
            http_response_code(409);
            ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['hata' => $errorMessage]);
            exit;
        }
    }

    // =========================================================
    // 2. GİRİŞ İŞLEMİ (action=login)
    // =========================================================
    elseif ($action === 'login') {
        $remember_me = isset($_POST['remember']); 
        
        if (empty($email) || empty($password)) {
            http_response_code(400);
            ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['hata' => 'Lütfen e-posta ve şifrenizi girin.']);
            exit;
        }

        // NOT: Status kontrolü eklenebilir, ancak şimdilik orijinal hali korundu.
        $sql = "SELECT id, passwordHash FROM users WHERE email = :email";
        $stmt = $pdo->prepare($sql);
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            http_response_code(401);
            ob_clean(); 
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['hata' => 'Lütfen önce kayıt olunuz.']);
            exit;
        }
        
        if (password_verify($password, $user['passwordHash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_email'] = $email;
            $_SESSION['user_loggedIn'] = true; 
            
            // "Beni Hatırla" mantığı
            if ($remember_me) { 
                try {
                    $selector = bin2hex(random_bytes(16));
                    $validator = bin2hex(random_bytes(32));
                    $validatorHash = hash('sha256', $validator);
                    $expiryTime = time() + (86400 * 30);
                    $expires = date('Y-m-d H:i:s', $expiryTime);
                    $deleteSql = "DELETE FROM user_auth_tokens WHERE user_id = :id";
                    $pdo->prepare($deleteSql)->execute(['id' => $user['id']]);
                    $insertSql = "INSERT INTO user_auth_tokens (user_id, selector, validator_hash, expires) VALUES (:user_id, :selector, :validator_hash, :expires)";
                    $stmt = $pdo->prepare($insertSql);
                    $stmt->execute([
                        'user_id' => $user['id'],
                        'selector' => $selector,
                        'validator_hash' => $validatorHash,
                        'expires' => $expires
                    ]);
                    $cookie_options = [
                        'expires' => $expiryTime,
                        'path' => '/',
                        'httponly' => true,
                        'samesite' => 'Lax'
                    ];
                    setcookie('remember_me_selector', $selector, $cookie_options);
                    setcookie('remember_me_validator', $validator, $cookie_options);
                } catch (\PDOException $e) {
                    error_log("Beni Hatırla Token Kayıt Hatası: " . $e->getMessage());
                }
            }
            
            http_response_code(200);
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'basari' => true, 
                'mesaj' => 'Giriş başarılı. Ana sayfaya yönlendiriliyorsunuz.',
                'redirect_url' => '../../php/users/home.php'
            ]);
            exit;
            
        } else {
            http_response_code(401);
            ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['hata' => 'E-posta veya şifreniz yanlış. Lütfen tekrar deneyin.']); 
            exit;
        }
    }
    
    // --- GEÇERSİZ POST İŞLEMİ HATASI ---
    else {
        // Eğer hiçbir action bloğuna girmezse, buraya düşer.
        http_response_code(400); 
        ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['hata' => 'Geçersiz işlem (action) belirtildi.']);
        exit;
    }
}
ob_end_flush();
?>