<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); 
}
include_once "../../db/db.php"; 

$base_url = "../../php/adminUsers/admin.php?page=usersManagement";

// PDO bağlantısının varlığını kontrol et
if (!isset($pdo) || $pdo === null) {
    header("Location: " . $base_url . "&error=" . urlencode("Veritabanı bağlantısı kurulamadı. Lütfen db.php dosya yolunu kontrol edin."));
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['action'])) {
    
    $action = $_POST['action'];

    // =========================================================
    // 1. kullanıcı Ekleme
    // =========================================================
    if ($action === 'add_users') {
        
        $required_fields = ['ad', 'soyad', 'email', 'password', 'password_confirm'];
        foreach ($required_fields as $field) {
            if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                $error = "Lütfen tüm zorunlu alanları doldurun.";
                header("Location: " . $base_url . "&action=add&error=" . urlencode($error));
                exit();
            }
        }

        $ad = trim($_POST['ad']);
        $soyad = trim($_POST['soyad']);
        $email = trim($_POST['email']);
        $password = $_POST['password'];
        $password_confirm = $_POST['password_confirm'];

        if (strlen($password) < 6) {
            $error = "Şifre en az 6 karakter olmalıdır.";
            header("Location: " . $base_url . "&action=add&error=" . urlencode($error));
            exit();
        }
        // ... (Diğer kontroller) ...

        try {
            // E-posta Tekrarlılığı Kontrolü
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
            $stmt->execute([':email' => $email]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Bu E-Posta adresi zaten kullanımda.";
                header("Location: " . $base_url . "&action=add&error=" . urlencode($error));
                exit();
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Veritabanına Ekleme İşlemi
            $sql = "INSERT INTO users (ad, soyad, email, passwordHash) VALUES (:ad, :soyad, :email, :password_hash)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':ad' => $ad,
                ':soyad' => $soyad,
                ':email' => $email,
                ':password_hash' => $hashed_password
            ]);

            $success = "Kullanıcı başarıyla eklendi: **" . $ad . " " . $soyad . "**";
            $_SESSION['success_message'] = $success; 
            header("Location: " . $base_url);
            exit();

        } catch (PDOException $e) {
            $error = "Veritabanı kaydı sırasında bir hata oluştu: " . $e->getMessage();
            header("Location: " . $base_url . "&action=add&error=" . urlencode($error));
            exit();
        }

    } 

    // =========================================================
    // 2. Kullanıcı Bilgilerini Güncelleme
    // =========================================================
    elseif ($action === 'update_users_info') {
        
        $required_fields = ['id', 'ad', 'soyad', 'email'];
        // ... (Gerekli alan kontrolü) ...
        $id = (int)$_POST['id'];
        $ad = trim($_POST['ad']);
        $soyad = trim($_POST['soyad']);
        $email = trim($_POST['email']);
        
        // E-posta formatı kontrolü
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = "Geçerli bir e-posta adresi giriniz.";
            header("Location: " . $base_url . "&action=edit&id=" . $id . "&error=" . urlencode($error));
            exit();
        }

        try {
            // E-posta tekrarlılığı kontrolü (Kendi e-postası hariç)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :id");
            $stmt->execute([':email' => $email, ':id' => $id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Bu E-Posta adresi başka bir kullanıcı tarafından kullanılıyor.";
                header("Location: " . $base_url . "&action=edit&id=" . $id . "&error=" . urlencode($error));
                exit();
            }

            // Veritabanı Güncelleme
            $sql = "UPDATE users SET ad = :ad, soyad = :soyad, email = :email WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':ad' => $ad,
                ':soyad' => $soyad,
                ':email' => $email,
                ':id' => $id
            ]);

            $success = "Kullanıcı bilgileri başarıyla güncellendi: **" . $ad . " " . $soyad . "**";
            $_SESSION['success_message'] = $success; 
            header("Location: " . $base_url);
            exit();

        } catch (PDOException $e) {
            $error = "Bilgiler güncellenirken bir hata oluştu: " . $e->getMessage();
            header("Location: " . $base_url . "&action=edit&id=" . $id . "&error=" . urlencode($error));
            exit();
        }
    }
    
    // =========================================================
    // 3. Kullanıcı Şifresini Sıfırlama
    // =========================================================
    elseif ($action === 'reset_users_password') {
        
        $required_fields = ['id', 'new_password', 'new_password_confirm'];
        // ... (Gerekli alan kontrolü) ...

        $id = (int)$_POST['id'];
        $new_password = $_POST['new_password'];
        $new_password_confirm = $_POST['new_password_confirm'];

        // Şifre uzunluk ve eşleşme kontrolü
        if (strlen($new_password) < 6 || $new_password !== $new_password_confirm) {
            $error = "Yeni şifreler eşleşmiyor veya en az 6 karakter değil.";
            header("Location: " . $base_url . "&action=edit&id=" . $id . "&error=" . urlencode($error));
            exit();
        }

        try {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

            // Veritabanı Güncelleme
            $sql = "UPDATE users SET passwordHash = :passwordHash WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            
            $stmt->execute([
                ':passwordHash' => $hashed_password,
                ':id' => $id
            ]);

            $success = "Kullanıcının şifresi başarıyla sıfırlandı.";
            $_SESSION['success_message'] = $success; 
            header("Location: " . $base_url);
            exit();

        } catch (PDOException $e) {
            $error = "Şifre sıfırlanırken bir hata oluştu: " . $e->getMessage();
            header("Location: " . $base_url . "&action=edit&id=" . $id . "&error=" . urlencode($error));
            exit();
        }
    }
    
    // =========================================================
    // 4. Kullanıcı Silme
    // =========================================================
    elseif ($action === 'delete_users') {
        
        if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
            $error = "Silinecek kullanıcı ID'si geçersiz.";
            $_SESSION['success_message'] = $error; 
            header("Location: " . $base_url);
            exit();
        }

        $id = (int)$_POST['id'];

        try {
            // Veritabanından silme işlemi
            $sql = "DELETE FROM users WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                $success = "Kullanıcı başarıyla silindi.";
                $_SESSION['success_message'] = $success; 
            } else {
                $error = "Silme işlemi başarısız oldu veya kullanıcı bulunamadı.";
                $_SESSION['success_message'] = $error;
            }
            
            header("Location: " . $base_url);
            exit();

        } catch (PDOException $e) {
            $error = "Silme işlemi sırasında bir veritabanı hatası oluştu: " . $e->getMessage();
            $_SESSION['success_message'] = $error; // Silme hatasını da oturumla taşıyoruz
            header("Location: " . $base_url);
            exit();
        }
    }

} else {
    // POST isteği değilse veya action belirtilmemişse, listeleme sayfasına yönlendir
    header("Location: " . $base_url . "&error=" . urlencode("Geçersiz istek metodu veya işlem."));
    exit();
}
?>