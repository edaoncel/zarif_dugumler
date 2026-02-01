<?php
ob_start();
session_start();
date_default_timezone_set('Europe/Istanbul');

// Hata raporlama (Geliştirme aşamasında hataları görmek için, canlıda kapatabilirsin)
error_reporting(E_ALL);
ini_set('display_errors', 0);

include_once "../../db/db.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require '../../includes/PHPMailer/src/Exception.php';
require '../../includes/PHPMailer/src/PHPMailer.php';
require '../../includes/PHPMailer/src/SMTP.php';

header('Content-Type: application/json; charset=utf-8');

// İstek Metodu Kontrolü
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['hata' => 'Sadece POST isteklerine izin verilir.']);
    exit;
}

// Gelen Verileri Temizleme
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$action = $_GET['action'] ?? '';
$code = trim($_POST['code'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Veritabanı Bağlantı Kontrolü
if (!isset($pdo)) {
    http_response_code(500);
    echo json_encode(['hata' => 'Veritabanı bağlantısı yapılamadı.']);
    exit;
}

// Mesaj Tanımları
$genericSuccessMessage = 'Sıfırlama kodu gönderilmiştir. Lütfen e-posta kutunuzu kontrol edin.';
$genericErrorMessage = 'Bu e-posta adresi kayıtlı değil veya bir hata oluştu.';

// =========================================================
// 1. Şifre Sıfırlama Kodu İsteği (action=request)
// =========================================================
if ($action === 'request') {
    if (empty($email)) {
        echo json_encode(['hata' => 'Lütfen e-posta adresinizi giriniz.']);
        exit;
    }

    $sql = "SELECT id, ad FROM adminUsers WHERE email = :email";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        http_response_code(400);
        echo json_encode(['hata' => $genericErrorMessage]);
        exit;
    }

    $code6Digit = (string)random_int(100000, 999999);
    $expires = date("Y-m-d H:i:s", time() + 900); // 15 Dakika

    $updateSql = "UPDATE adminUsers SET passwordResetToken = :code, passwordResetExperies = :expires WHERE id = :id";
    $updateStmt = $pdo->prepare($updateSql);
    $updateStmt->execute(['code' => $code6Digit, 'expires' => $expires, 'id' => $user['id']]);

    $mail = new PHPMailer(true);

    try {
        // SMTP Ayarları
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'sinemkocabasoglu1@gmail.com'; 
        $mail->Password   = 'iprk whwq yhys qktr'; // Google Uygulama Şifresi
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = 465;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom('sinemkocabasoglu1@gmail.com', 'Kullanıcı Paneli');
        $mail->addAddress($email, $user['ad']);

        $mail->isHTML(true);
        $mail->Subject = 'Kullanıcı Şifre Sıfırlama Kodunuz';
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif;'>
                <h2>Merhaba {$user['ad']},</h2>
                <p>Şifrenizi sıfırlamak için aşağıdaki kodu kullanabilirsiniz:</p>
                <h1 style='color: #dc3545; letter-spacing: 5px;'>{$code6Digit}</h1>
                <p>Bu kod 15 dakika geçerlidir.</p>
                <p>Eğer bu işlemi siz yapmadıysanız bu e-postayı dikkate almayın.</p>
            </div>";
        $mail->AltBody = "Şifre sıfırlama kodunuz: " . $code6Digit;

        $mail->send();

        echo json_encode([
            'basari' => true,
            'mesaj' => $genericSuccessMessage,
            'redirect_url' => 'resetPassword.php'
        ]);

    } catch (Exception $e) {
        error_log("Mail Gönderme Hatası: " . $mail->ErrorInfo);
        http_response_code(500);
        echo json_encode(['hata' => 'E-posta gönderilemedi. Lütfen daha sonra tekrar deneyin.']);
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
    $sql = "SELECT id FROM adminUsers WHERE passwordResetToken = :code AND passwordResetExperies > :currentTime";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['code' => $code, 'currentTime' => $currentTime]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $_SESSION['reset_code_verified'] = $code; // Güvenlik için kodu session'a atıyoruz
        echo json_encode([
            'basari' => true,
            'mesaj' => 'Kod doğrulandı. Yeni şifrenizi belirleyin.',
            'next_step' => 'password_form'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['hata' => 'Geçersiz veya süresi dolmuş kod.']);
    }
}

// =========================================================
// 3. Şifre Sıfırlama (action=reset)
// =========================================================
elseif ($action === 'reset') {
    if (empty($code) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['hata' => 'Eksik bilgi gönderildi.']);
        exit;
    }

    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(['hata' => 'Şifreler uyuşmuyor.']);
        exit;
    }

    // Şifre uzunluk kontrolü (Opsiyonel ama önerilir)
    if (strlen($newPassword) < 6) {
        http_response_code(400);
        echo json_encode(['hata' => 'Şifre en az 6 karakter olmalıdır.']);
        exit;
    }

    // Hashleme İşlemi
    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

    // Güncelleme
    $updateSql = "UPDATE adminUsers SET 
                  passwordHash = :hash, 
                  passwordResetToken = NULL, 
                  passwordResetExperies = NULL 
                  WHERE passwordResetToken = :code";
    
    $updateStmt = $pdo->prepare($updateSql);
    $result = $updateStmt->execute([
        'hash' => $passwordHash,
        'code' => $code
    ]);

    if ($result && $updateStmt->rowCount() > 0) {
        unset($_SESSION['reset_code_verified']);
        echo json_encode([
            'basari' => true,
            'mesaj' => 'Şifreniz başarıyla güncellendi. Giriş yapabilirsiniz.',
            'redirect_url' => 'adminUsers.php' 
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['hata' => 'Şifre güncellenemedi. Kod geçersiz olabilir.']);
    }
}

else {
    http_response_code(400);
    echo json_encode(['hata' => 'Geçersiz işlem belirlendi.']);
}