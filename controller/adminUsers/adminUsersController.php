<?php
error_reporting(0); 
ini_set('display_errors', 0); 

ob_start(); 

session_start();
date_default_timezone_set('Europe/Istanbul'); 

include_once "../../db/db.php"; 

require '../../includes/PHPMailer/src/Exception.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['hata' => 'Sadece POST isteklerine izin verilir.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? ''; 
$action = isset($_GET['action']) ? $_GET['action'] : 'login'; 

if (!isset($pdo)) {
    http_response_code(500);
    ob_clean(); 
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['hata' => 'Sunucu hatası: Veritabanı bağlantısı yapılamadı.']);
    exit;
}

// =========================================================
// 1. KAYIT İŞLEMİ (action=register)
// =========================================================
if ($action === 'register') {
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
    
    $sql = "INSERT INTO adminUsers (ad, soyad, email, passwordHash) VALUES (:ad, :soyad, :email, :passwordHash)";
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            'ad' => $ad,
            'soyad' => $soyad,
            'email' => $email,
            'passwordHash' => $passwordHash
        ]);
        
        http_response_code(201);
        ob_clean(); 
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'basari' => true, 
            'mesaj' => 'Kayıt başarılı! Şimdi giriş yapabilirsiniz.',
            'redirect_url' => 'adminUsers.php' 
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

    $sql = "SELECT id, passwordHash FROM adminUsers WHERE email = :email";
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
    
    $sql = "SELECT id, passwordHash FROM adminusers WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['passwordHash'])) {
        
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_email'] = $email;
        $_SESSION['admin_loggedIn'] = true; 
            
        if ($remember_me) {
            
            try {
                $selector = bin2hex(random_bytes(16));
                $validator = bin2hex(random_bytes(32)); 
                $validatorHash = hash('sha256', $validator);
                $expiryTime = time() + (86400 * 30);
                $expires = date('Y-m-d H:i:s', $expiryTime);

                $deleteSql = "DELETE FROM user_auth_tokens WHERE user_id = :id";
                $pdo->prepare($deleteSql)->execute(['id' => $user['id']]);

                $insertSql = "INSERT INTO user_auth_tokens (user_id, selector, validator_hash, expires) 
                              VALUES (:user_id, :selector, :validator_hash, :expires)";
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
            'mesaj' => 'Giriş başarılı. Yönetici sayfasına yönlendiriliyorsunuz.',
            'redirect_url' => '../../php/adminUsers/admin.php'
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
else {
    http_response_code(400); 
    ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['hata' => 'Geçersiz işlem (action) belirtildi.']);
    exit;
}

ob_end_flush();