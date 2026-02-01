<?php
session_start();

$activePage = $_GET['page'] ?? 'home';

// Kontrolcü dosyasını dahil et
include_once "../../controller/adminUsers/adminController.php";


// Güvenlik kontrolü
if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
    header("Location: adminUsers.php"); 
    exit;
}

// Oturumdan verileri al
$adminName = $_SESSION['admin_full_name'] ?? 'Yönetici Adı';
$profilePhotoPath = $_SESSION['admin_profil_fotolari'] ?? null;
$adminId = $_SESSION['admin_id'] ?? 0; 
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Paneli</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../css/adminUsers/admin.css">
</head>
<body>
<div class="admin-container">
    
    <div class="sidebar" id="sidebar">
        
        <div class="hamburger-menu-btn-container">
            <div class="menu-btn-5" id="hamburgerBtn" onclick="menuBtnFunction(this)">
                <span></span>
                <span></span>
            </div>
        </div>
        <div class="profile-area">
            
            <div class="profile-photo-container">
                <div class="profile-photo">
                    <?php if ($profilePhotoPath): ?>
                        <img id="current-profile-photo" src="<?php echo htmlspecialchars($profilePhotoPath); ?>" alt="<?php echo $adminName; ?> Profil Fotoğrafı">
                    <?php else: ?>
                        <i id="current-profile-photo" class="fas fa-user-circle fa-6x"></i>
                    <?php endif; ?>
                </div>
                
                <label for="photo-upload-input" class="edit-icon">
                    <i class="fas fa-camera"></i>
                </label>
                <?php if ($profilePhotoPath): ?>
                    <a href="#" id="delete-photo-link" class="delete-icon" title="Profil Fotoğrafını Sil">
                        <i class="fas fa-trash"></i>
                    </a>
                <?php endif; ?>
                
                <form id="photo-upload-form" action="../../controller/adminUsers/adminController.php" method="POST" enctype="multipart/form-data" style="display:none;">
                    <input type="file" name="admin_photo" id="photo-upload-input" accept="image/*">
                    <input type="hidden" name="action" value="upload_photo">
                    <input type="hidden" name="admin_id" value="<?php echo $adminId; ?>">
                </form>
                <form id="photo-delete-form" action="../../controller/adminUsers/adminController.php" method="POST" style="display:none;">
                    <input type="hidden" name="action" value="delete_photo">
                    <input type="hidden" name="admin_id" value="<?php echo $adminId; ?>">
                </form>
            </div> 
            
            <p class="admin-name"><?php echo $adminName; ?></p>
        </div>

        <nav class="main-nav" id="mainNav">
            <ul>
                <li><a href="?page=home" class="<?php echo ($activePage == 'home' ? 'active' : ''); ?>"><i class="fas fa-home"></i> Anasayfa</a></li>
                <li><a href="?page=products" class="<?php echo ($activePage == 'products' ? 'active' : ''); ?>"><i class="fas fa-box"></i> Ürünler</a></li>
                <li><a href="?page=orders" class="<?php echo ($activePage == 'orders' ? 'active' : ''); ?>"><i class="fas fa-box-open"></i> Siparişler</a></li>
                <li><a href="?page=about" class="<?php echo ($activePage == 'about' ? 'active' : ''); ?>"><i class="fas fa-info-circle"></i> Hakkımda</a></li>
                <li><a href="?page=contact" class="<?php echo ($activePage == 'contact' ? 'active' : ''); ?>"><i class="fas fa-envelope"></i> İletişim</a></li>
                <li><a href="?page=favorites" class="<?php echo ($activePage == 'favorites' ? 'active' : ''); ?>"><i class="fas fa-heart"></i> Favoriler</a></li>
                <li><a href="?page=usersManagement" class="<?php echo ($activePage == 'usersManagement' ? 'active' : ''); ?>"><i class="fas fa-users"></i> Kullanıcılar</a></li>
                <li><a href="?page=adminManagement" class="<?php echo ($activePage == 'adminManagement' ? 'active' : ''); ?>"><i class="fas fa-user-shield"></i> Yöneticiler</a></li>
                <li><a href="?page=reviews" class="<?php echo ($activePage == 'reviews' ? 'active' : ''); ?>"><i class="fas fa-star-half-alt"></i> Değerlendirme</a></li>
                <a href="logout.php" class="logout-link"><i class="fas fa-sign-out-alt"></i> Çıkış Yap</a>
            </ul>
        </nav>
    </div>

    <div class="content-area">
        
        <?php 
        // Mesajları göster (3 saniye kaybolma CSS'i altta mevcut)
        if (isset($_SESSION['basari_mesaji'])): ?>
            <div class="alert success"><?php echo $_SESSION['basari_mesaji']; unset($_SESSION['basari_mesaji']); ?></div>
        <?php elseif (isset($_SESSION['hata_mesaji'])): ?>
            <div class="alert error"><?php echo $_SESSION['hata_mesaji']; unset($_SESSION['hata_mesaji']); ?></div>
        <?php endif; ?>

        <?php
// Aktif sayfaya göre içeriği dahil et
switch ($activePage) {
    case 'home':
        include 'home.php';
        break;
    case 'products':
        include 'products.php';
        break;
    case 'productDetails':
        include 'productDetails.php';
        break;
    case 'orders':
        include 'orders.php';
        break;
    case 'userDetails':
        include 'userDetails.php';
        break;
    case 'about':
        include 'about.php';
        break;
    case 'contact':
        include 'contact.php';
        break;
    case 'favorites':
        include 'favorites.php';
        break;
    case 'usersManagement':
        include 'usersManagement.php';
        break;
    case 'adminManagement':
        include 'adminManagement.php';
        break;
    case 'reviews':
        include 'reviews.php';
        break;
    case 'orderDetail':
        include 'orderDetail.php';
        break;
    default:
        // Eğer tanımsız bir sayfa gelirse, Anasayfa'yı göster (veya bir 404 hatası)
        include 'error.php';
        break;
}
?>
    </div>
</div>

<script src="../../js/adminUsers/admin.js"></script>
</body>
</html>