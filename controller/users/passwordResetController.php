<?php
ob_start(); 
ob_clean();

session_start();
date_default_timezone_set('Europe/Istanbul');
include_once "../../db/db.php"; 

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../../includes/PHPMailer/src/Exception.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';


header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['hata' => 'Sadece POST isteklerine izin verilir.']);
    exit;
}

$email = trim($_POST['email'] ?? '');
$action = isset($_GET['action']) ? $_GET['action'] : ''; 
$code = trim($_POST['code'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';


if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['hata' => 'Veritabanı bağlantısı yapılamadı.']);
    exit;
}

$genericSuccessMessage = 'Sıfırlama kodu gönderilmiştir. Lütfen e-posta kutunuzu kontrol edin.';
$genericErrorMessage = 'Bu e-posta adresi kayıtlı değil. Lütfen geçerli bir adres giriniz.'; 

// =========================================================
// 1. Şifre Sıfırlama Kodu İsteği (action=request)
// =========================================================
if ($action === 'request') {
    
    // Kullanıcıyı Bul
    $sql = "SELECT id, ad FROM users WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();
    
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['hata' => $genericErrorMessage]);
        exit;
    }


    $code6Digit = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT); 
    $expires = date("Y-m-d H:i:s", time() + 900);
    
    $updateSql = "UPDATE users SET passwordResetToken = :code, passwordResetExperies = :expires WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute(['code' => $code6Digit, 'expires' => $expires, 'id' => $user['id']]);
    
    $mail = new PHPMailer(true);
    
    try {
        $mail->isSMTP();
        $mail->Host      = 'smtp.gmail.com';
        $mail->SMTPAuth  = true;
        $mail->Username  = 'sinemkocabasoglu1@gmail.com'; 
        $mail->Password  = 'iprk whwq yhys qktr';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
        $mail->Port      = 465; 
        $mail->CharSet    = 'UTF-8';
        
        $mail->setFrom('sinemkocabasoglu1@gmail.com', 'Kullanıcı Paneli');
        
        $mail->addAddress($email, $user['ad']); 
        
        $mail->isHTML(true);
        $mail->Subject = 'Kullanıcı Şifre Sıfırlama Kodunuz';
        $mail->Body    = "Merhaba {$user['ad']},<br><br>"
                        . "Kullanıcı şifrenizi sıfırlamak için <strong>aşağıdaki 6 haneli kodu</strong> kullanınız.<br><br>"
                        . "<h3>Sıfırlama Kodunuz:</h3>"
                        . "<span style='font-size: 2em; color: #dc3545; font-weight: bold;'>{$code6Digit}</span>"
                        . "<br><br>"
                        . "Bu kodu girmelisiniz: <a href='http://localhost/sinem/php/resetPassword.php'>http://localhost/sinem/php/resetPassword.php</a><br>"
                        . "Bu kod 15 dakika süreyle geçerlidir. Başka hiç kimseyle paylaşmayınız.";
        $mail->AltBody = "Şifre sıfırlama kodunuz: " . $code6Digit;


 $mail->send();
 
 http_response_code(200);
 echo json_encode([
 'basari' => true,
 'mesaj' => $genericSuccessMessage,
 'redirect_url' => 'resetPassword.php'
 ]);
 
} catch (Exception $e) {
        error_log("Mail Gönderme Hatası: {$mail->ErrorInfo}");
        http_response_code(500);
        echo json_encode(['hata' => 'Sunucu, sıfırlama mailini gönderirken bir sorun yaşadı. Lütfen teknik ekibe ulaşın.']);
    }

} 
// =========================================================
// 2. Kod Doğrulama (action=verifycode)
// =========================================================
elseif ($action === 'verifycode') {
    
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['hata' => 'Lütfen 6 haneli kodu giriniz.']);
        exit;
    }

    $currentTime = date("Y-m-d H:i:s"); 

    $sql = "SELECT id FROM users WHERE passwordResetToken = :code AND passwordResetExperies > :currentTime";
    $stmt = $pdo->prepare($sql);
    
    $stmt->execute(['code' => $code, 'currentTime' => $currentTime]); 
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        
        http_response_code(200);
        echo json_encode([
            'basari' => true,
            'mesaj' => 'Kod doğru. Şimdi yeni şifrenizi giriniz.',
            'next_step' => 'password_form'
        ]);
        exit;
    } else {
        http_response_code(400);
        echo json_encode(['hata' => 'Girdiğiniz kod hatalı veya süresi dolmuştur.']);
        exit;
    }
}
    
// =========================================================
// 3. Şifre Sıfırlama (action=reset)
// =========================================================
elseif ($action === 'reset') {
    
    if (empty($code)) {
        http_response_code(400);
        echo json_encode(['hata' => 'Şifre sıfırlama kodu eksik. Lütfen tekrar deneyin.']);
        exit;
    }
    
    $sql = "SELECT id FROM users WHERE passwordResetToken = :code";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['code' => $code]);
    $user = $stmt->fetch();
    
    if (!$user) {
        http_response_code(400);
        echo json_encode(['hata' => 'Geçersiz şifre sıfırlama kodu.']);
        exit;
    }
    
    $userId = $user['id'];
    
    if (empty($newPassword) || empty($confirmPassword)) {
        http_response_code(400);
        echo json_encode(['hata' => 'Lütfen şifre alanlarını boş bırakmayınız.']);
        exit;
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['hata' => 'Şifreler birbiriyle eşleşmiyor.']);
        exit;
    }
    
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    $updateSql = "UPDATE users SET passwordHash = :hash, passwordResetToken = NULL, passwordResetExperies = NULL WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute(['hash' => $passwordHash, 'id' => $userId]);
    
    unset($_SESSION['reset_user_id']);
    
    http_response_code(200);
    echo json_encode([
        'basari' => true,
        'mesaj' => 'Şifreniz başarıyla güncellendi. Giriş sayfasına yönlendiriliyorsunuz.',
        'redirect_url' => 'users.php'
    ]);
    exit;

} else {
    http_response_code(400); 
    echo json_encode(['hata' => 'Geçersiz işlem.']);
    exit;
}
?>