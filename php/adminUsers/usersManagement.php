<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include_once "../../db/db.php"; 

if (!isset($pdo) || $pdo === null) {
    die("Veritabanı bağlantısı kurulamadı. Lütfen db.php dosyasını ve dosya yolunu kontrol edin.");
}

$page_action = isset($_GET['action']) ? $_GET['action'] : 'list';
$error_message = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

$success_message = isset($_GET['success']) ? htmlspecialchars($_GET['success']) : '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message']; 
    unset($_SESSION['success_message']); 
}

$edit_user = null; // Düzenleme modunda kullanılacak kullanıcı verisi

try {
    // DÜZENLEME (EDIT) Modu Kontrolü: Kullanıcı verisini çek
    if ($page_action === 'edit') {
        if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
            header("Location: admin.php?page=usersManagement&error=" . urlencode("Geçersiz kullanıcı ID'si belirtildi."));
            exit();
        }
        
        $user_id = (int)$_GET['id'];
        
        $sql_edit = "SELECT id, ad, soyad, email FROM users WHERE id = :id";
        $stmt_edit = $pdo->prepare($sql_edit);
        $stmt_edit->bindParam(':id', $user_id, PDO::PARAM_INT);
        $stmt_edit->execute();
        $edit_user = $stmt_edit->fetch(PDO::FETCH_ASSOC);

        if (!$edit_user) {
            header("Location: admin.php?page=usersManagement&error=" . urlencode("Düzenlenecek kullanıcı bulunamadı."));
            exit();
        }
    }
    
    $sql_list = "SELECT id, ad, soyad, email FROM users ORDER BY id ASC";
    $stmt_list = $pdo->prepare($sql_list);
    $stmt_list->execute();
    $users = $stmt_list->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Gerçek uygulamada bu hatayı kullanıcıya göstermemelisiniz.
    die("Veritabanı sorgusu sırasında bir hata oluştu: " . $e->getMessage()); 
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<title>Kullanıcı Yönetimi</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0"> 
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<link rel="stylesheet" href="../../css/adminUsers/usersManagement.css"> 

</head>
<body>

<div class="container">
<div class="header-row">

<?php if ($page_action !== 'add' && $page_action !== 'edit'): ?>
 <a href="admin.php?page=usersManagement&action=add" class="btn-add-users">
  <i class="fas fa-plus"></i> Kullanıcı Ekle 
 </a>
<?php else: ?>
<?php endif; ?>

</div>

  <?php 
    // Hata mesajını kırmızı (danger) olarak göster
    if ($error_message): ?>
 <div class="alert alert-danger">
 <i class="fas fa-exclamation-triangle"></i> Hata: **<?php echo $error_message; ?>**
 </div>
<?php endif; ?>

<?php 
    // Oturumdan gelen mesajı göster (Başarı veya Silme Hatası olabilir)
    if ($success_message): 
    $alert_class = (strpos($success_message, 'başarıyla') !== false || strpos($success_message, 'eklendi') !== false || strpos($success_message, 'güncellendi') !== false || strpos($success_message, 'sıfırlandı') !== false) ? 'alert-success' : 'alert-danger';
?>
 <div class="alert <?php echo $alert_class; ?>">
 <i class="fas fa-check-circle"></i> Mesaj: **<?php echo $success_message; ?>**
 </div>
<?php endif; ?>


  <?php if ($page_action === 'add'): ?>
 <div class="form-card">
 <h3>Yeni Kullanıcı Kaydı</h3><br>
    <form id="addUsersForm" action="../../controller/adminUsers/usersManagementController.php" method="POST">
     <input type="hidden" name="action" value="add_users">

  <div class="form-group">
  <label for="ad">Adı <span style="color: red;">*</span></label>
  <input type="text" id="ad" name="ad" required placeholder="Kullanıcı Adı">
  </div>

  <div class="form-group">
  <label for="soyad">Soyadı <span style="color: red;">*</span></label>
  <input type="text" id="soyad" name="soyad" required placeholder="Kullanıcı Soyadı">
  </div>
  
  <div class="form-group">
  <label for="email">E-Posta <span style="color: red;">*</span></label>
  <input type="email" id="email" name="email" required placeholder="ornek@eposta.com">
  </div>

  <div class="form-group">
  <label for="password">Şifre (Min. 6 karakter) <span style="color: red;">*</span></label>
    <div class="password-wrapper">
      <input type="password" id="password" name="password" required placeholder="Şifre">
      <span class="toggle-password" onclick="togglePasswordVisibility('password', 'toggleIcon1')">
        <i class="fas fa-eye" id="toggleIcon1"></i>
      </span>
    </div>
  </div>

  <div class="form-group">
  <label for="password_confirm">Şifre Tekrarı <span style="color: red;">*</span></label>
    <div class="password-wrapper">
      <input type="password" id="password_confirm" name="password_confirm" required placeholder="Şifreyi Onayla">
      <span class="toggle-password" onclick="togglePasswordVisibility('password_confirm', 'toggleIcon2')">
        <i class="fas fa-eye" id="toggleIcon2"></i>
      </span>
    </div>
  </div>

  <div class="form-group" style="margin-top: 20px;">
  <button type="submit" class="btn-form-submit">
    <i class="fas fa-user-plus"></i> Kullanıcı Ekle
  </button>
  <a href="admin.php?page=usersManagement" class="btn-form-cancel">İptal</a>
  </div>
 </form>
 </div>

<?php endif; ?>

  <?php if ($page_action === 'edit' && $edit_user): ?>
 <div class="form-card">
 <h3><?php echo htmlspecialchars($edit_user['ad'] . ' ' . $edit_user['soyad']); ?> Bilgilerini Düzenle</h3><br>
  
      <form id="updateInfoForm" action="../../controller/adminUsers/usersManagementController.php" method="POST">
    <input type="hidden" name="action" value="update_users_info">
    <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">

   <div class="form-group">
   <label for="ad">Adı <span style="color: red;">*</span></label>
   <input type="text" id="ad" name="ad" required value="<?php echo htmlspecialchars($edit_user['ad']); ?>" placeholder="Kullanıcı Adı">
   </div>

   <div class="form-group">
   <label for="soyad">Soyadı <span style="color: red;">*</span></label>
   <input type="text" id="soyad" name="soyad" required value="<?php echo htmlspecialchars($edit_user['soyad']); ?>" placeholder="Kullanıcı Soyadı">
   </div>
   
   <div class="form-group">
   <label for="email">E-Posta <span style="color: red;">*</span></label>
   <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($edit_user['email']); ?>" placeholder="ornek@eposta.com">
   </div>

   <div class="form-group" style="margin-top: 20px;">
   <button type="submit" class="btn-form-submit" style="background-color: #ffc107; color: black;">
    <i class="fas fa-save"></i> Bilgileri Kaydet
   </button>
   <a href="admin.php?page=usersManagement" class="btn-form-cancel">İptal</a>
   </div>
   </form>
      
      <hr style="margin: 30px 0; border-top: 1px dashed #ccc;">

      <h3>Şifre Sıfırlama</h3><br>
      
      <form id="resetPassForm" action="../../controller/adminUsers/usersManagementController.php" method="POST">
          <input type="hidden" name="action" value="reset_users_password">
          <input type="hidden" name="id" value="<?php echo $edit_user['id']; ?>">

          <div class="form-group">
              <label for="new_password">Yeni Şifre (Min. 6 karakter) <span style="color: red;">*</span></label>
              <div class="password-wrapper">
                <input type="password" id="new_password" name="new_password" required placeholder="Yeni Şifre">
                <span class="toggle-password" onclick="togglePasswordVisibility('new_password', 'toggleIcon3')">
                    <i class="fas fa-eye" id="toggleIcon3"></i>
                </span>
              </div>
          </div>

          <div class="form-group">
              <label for="new_password_confirm">Yeni Şifre Tekrarı <span style="color: red;">*</span></label>
              <div class="password-wrapper">
                <input type="password" id="new_password_confirm" name="new_password_confirm" required placeholder="Yeni Şifreyi Onayla">
                <span class="toggle-password" onclick="togglePasswordVisibility('new_password_confirm', 'toggleIcon4')">
                    <i class="fas fa-eye" id="toggleIcon4"></i>
                </span>
              </div>
          </div>

          <div class="form-group" style="margin-top: 20px;">
              <button type="submit" class="btn-form-submit" style="background-color: #dc3545;">
                  <i class="fas fa-key"></i> Şifreyi Sıfırla
              </button>
          </div>
      </form>
 </div>
<?php endif; ?>

  <?php if ($page_action === 'list'): ?>
<?php if (empty($users)): ?>
 <div class="alert alert-warning" style="padding: 15px; border: 1px solid #ffcc00; background-color: #fff3cd; color: #664d03; margin-top: 20px;">
 Kayıtlı Kullanıcı bulunmamaktadır.
 </div>
<?php else: ?>
 <div class="table-responsive">
 <table class="users-table">
  <thead>
  <tr>
  <th>ID</th>
  <th>ADI SOYADI</th>
  <th>E-POSTA</th>
  <th style="text-align: center;">İŞLEMLER</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($users as $user): ?>
  <tr>
   <td><?php echo htmlspecialchars($user['id']); ?></td>
 <td>
    <a href="http://localhost/sinem/php/adminUsers/admin.php?page=userDetails&id=<?php echo htmlspecialchars($user['id']); ?>">
        <?php 
            // Tıklanabilir metin olarak Ad ve Soyad'ı göster
            echo htmlspecialchars($user['ad']) . ' ' . htmlspecialchars($user['soyad']); 
        ?>
    </a>
</td>
   <td><?php echo htmlspecialchars($user['email']); ?></td>
   <td style="text-align: center;">
             <a href="admin.php?page=usersManagement&action=edit&id=<?php echo $user['id']; ?>" class="action-btn btn-edit">
   <i class="fas fa-pen-alt"></i> 
   </a>
   
             <a href="#" class="action-btn btn-delete delete-btn" 
        onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad'], ENT_QUOTES); ?>')">
   <i class="fas fa-trash-alt"></i> 
   </a>
   </td>
  </tr>
  <?php endforeach; ?>
  </tbody>
 </table>
 </div>
<?php endif; ?>
<?php endif; // list action sonu ?>

</div>
<script src="../../js/adminUsers/usersManagement.js"></script>
</body>
</html>