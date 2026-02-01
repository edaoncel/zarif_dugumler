<?php
// E:\xampp\htdocs\sinem\php\users\home.php

session_start();


include_once "../../db/db.php"; 
include_once "../../controller/users/homeController.php"; 

// =================================================================
// DEĞİŞKENLERİN GÜVENLİ TANIMLANMASI VE FOTOĞRAF YOLU
// =================================================================

// Varsayılan fotoğraf yolunu her koşulda tanımla
$currentPhotoSrc = '../../img/default-user.png';

// $isLoggedIn ve $userData değişkenleri homeController'dan gelmelidir.
if ($isLoggedIn && isset($userData['userPhoto']) && $userData['userPhoto']) {
 $userPhotoPath = '../../img/users/'; 
 // Güvenliğiniz için: Sadece dosya adının kullanıldığından emin olun.
 $safeUserPhoto = htmlspecialchars(basename($userData['userPhoto'])); 
 $currentPhotoSrc = $userPhotoPath . $safeUserPhoto;
}

// Başarı/hata mesajlarını al
$message = $_SESSION['profile_message'] ?? null;
unset($_SESSION['profile_message']);

// =================================================================
// METİN LİMİTLEME SABİTLERİ VE FONKSİYONU (Tasarım Koruma)
// =================================================================
define('PRODUCT_NAME_LIMIT', 45); 
define('PRODUCT_DESC_LIMIT', 80); 

/**
* Metni belirli bir limite göre keser ve sonuna '...' ekler.
*/
function truncateText(string $text, int $limit): string {
 if (mb_strlen($text, 'UTF-8') > $limit) {
  $text = mb_substr($text, 0, $limit, 'UTF-8');
  if (mb_strrpos($text, ' ', 0, 'UTF-8') !== false) {
   $text = mb_substr($text, 0, mb_strrpos($text, ' ', 0, 'UTF-8'), 'UTF-8');
  }
  return trim($text) . '...';
 }
 return $text;
}

// slugify fonksiyonu homeController.php'den gelmektedir.
// isValidHexColor fonksiyonu homeController.php'den gelmektedir.
?>

<!DOCTYPE html>
<html lang="tr">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title><?php echo $markaAdi; ?> | Ana Sayfa</title>
 <link rel="stylesheet" href="../../css/users/home.css"> 
 </head>
<body>

 <header class="main-header">
  <h2><a href="home.php"><?php echo $markaAdi; ?></a></h2>
  <div class="user-info">
   <?php if ($isLoggedIn): ?>
    <span class="welcome-text">Hoş geldiniz, **<?php echo $userName; ?>**!</span>
    <a href="cart.php" title="Sepet">🛒</a>
    <a href="favorites.php" title="Favoriler">❤️</a>
    <a href="profile.php" title="Profilim">👤</a>
    <a href="logout.php" title="Çıkış">Çıkış</a>
   <?php else: ?>
    <a href="register.php?action=login" class="login-btn">Giriş Yap</a>
    <a href="register.php?action=register" class="register-btn">Kayıt Ol</a>
    <a href="cart.php" title="Sepet">🛒</a>
   <?php endif; ?>
  </div>
 </header>
 <div class="mobile-menu-toggle-container">
  <button class="hamburger-menu-toggle" aria-expanded="false" aria-controls="category-list">
   <span class="bar"></span>
   <span class="bar"></span>
   <span class="bar"></span>
  </button>
 </div>

<nav class="category-nav">
 <ul class="main-category-list" id="category-list">
  <?php 
  // $categories homeController'dan hiyerarşik olarak gelmelidir.
  if (!empty($categories) && (is_array($categories) || $categories instanceof Traversable)) {
   foreach ($categories as $category) {
    $hasSub = !empty($category['subCategories']);
    // Ana kategori linki için categoryId kullanılır
    $categoryLink = 'category.php?id=' . htmlspecialchars($category['id']); 
    ?>
    <li class="category-item <?php echo $hasSub ? 'has-submenu' : ''; ?>">
     <a href="<?php echo $categoryLink; ?>">
      <?php echo htmlspecialchars($category['name']); ?>
      <?php if ($hasSub): ?>
       <span class="arrow">▼</span>
      <?php endif; ?>
     </a>
     
     <?php if ($hasSub): ?>
      <ul class="submenu">
       <?php foreach ($category['subCategories'] as $sub): ?>
        <li>
         <a href="category.php?id=<?php echo htmlspecialchars($sub['id']); ?>&type=sub">
          <?php echo htmlspecialchars($sub['name']); ?>
         </a>
        </li>
       <?php endforeach; ?>
      </ul>
     <?php endif; ?>
    </li>
    <?php
   }
  } else {
   // Veri yoksa varsayılan linkleri göster
   echo '<li><a href="#">Kategori Yok</a></li>';
  }
  ?>
 </ul>
</nav>

 <main class="container">
  
  <?php if ($message): ?>
   <div class="system-message"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <section class="promotions">
   <h2>Sezon Sonu İndirimleri Başladı!</h2>
   <p>%50'ye varan indirimleri kaçırmayın. Hemen **Kayıt Ol** ve alışverişe başla!</p>
  </section>

  <section class="product-list">
   <h2>Öne Çıkan Ürünler</h2>
   <div class="products-grid">
    <?php 
    if (isset($featuredProducts) && is_array($featuredProducts)):
     foreach ($featuredProducts as $product):
      $productId = $product['id']; 
      
      // Metin Limitlerini Uygulama
      $productName = truncateText($product['name'], PRODUCT_NAME_LIMIT);
      $productDescription = truncateText($product['description'] ?? '', PRODUCT_DESC_LIMIT);
      
      // Görselleri hazırla
      $productImages = $product['images'] ?? []; 
      $firstImage = $product['firstImageUrl'] ?? '../../img/default-product.jpg'; 
      ?>
      <div class="product-card" data-product-id="<?php echo $productId; ?>">
       <div class="product-image-wrapper">
        
        <div class="image-slider">
          
          <?php 
          // Tüm görselleri kaydırıcı içine yerleştir
          if (!empty($productImages)):
            foreach ($productImages as $index => $imageUrlData): 
                            // DEĞİŞİKLİK 2: Controller'dan gelen görsel verisinin (imageUrlData)
                            // ilgili renk slug'ını içerdiğini varsayıyoruz. 
                            $safeImageUrl = htmlspecialchars($imageUrlData['url'] ?? $firstImage);
                            $imageColorSlug = strtolower(htmlspecialchars($imageUrlData['colorSlug'] ?? 'default')); 

              $activeClass = ($index === 0) ? 'active' : ''; // İlk resmi aktif yap
            ?>
            <a href="product_detail.php?id=<?php echo $productId; ?>" 
                            class="slide-item <?php echo $activeClass; ?>"
                            data-color-slug="<?php echo $imageColorSlug; ?>">
              <img src="<?php echo $safeImageUrl; ?>" alt="<?php echo htmlspecialchars($product['name']) . ' - Görsel ' . ($index + 1); ?>">
            </a>
            <?php
            endforeach;
          else: 
            // Görsel yoksa varsayılan resmi göster
            ?>
            <a href="product_detail.php?id=<?php echo $productId; ?>" class="slide-item active" data-color-slug="default">
              <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
            </a>
          <?php endif; ?>

        </div>
        <?php if (count($productImages) > 1): ?>
          <button class="slider-btn prev-btn" aria-label="Önceki Görsel">❮</button>
          <button class="slider-btn next-btn" aria-label="Sonraki Görsel">❯</button>
        <?php endif; ?>
        </div>
       <div class="product-details">
        <h3 class="product-name" title="<?php echo htmlspecialchars($product['name']); ?>">
         <?php echo $productName; ?>
        </h3>
        <p class="product-description"><?php echo $productDescription; ?></p>
        <p class="product-price"><?php echo htmlspecialchars($product['price']); ?></p>
        
        <div class="color-options">
     <?php 
     if (!empty($product['colors'])):
      foreach ($product['colors'] as $colorData): 
       
       $colorSlug = strtolower(slugify($colorData['color'])); 
       $colorHex = htmlspecialchars($colorData['colorHexCode'] ?? '#CCCCCC'); 
       
       // GÜVENLİK İYİLEŞTİRMESİ: Hex kodunu doğrula
       if (!function_exists('isValidHexColor') || !isValidHexColor($colorHex)) {
         $colorHex = '#CCCCCC'; // Geçersizse varsayılan değer
       }
       ?>
       <a href="product_detail.php?id=<?php echo $productId; ?>&color=<?php echo $colorSlug; ?>" 
       class="color-circle" 
                data-color-slug="<?php echo $colorSlug; ?>"        style="background-color: <?php echo $colorHex; ?>;"
       title="<?php echo htmlspecialchars($colorData['color']); ?>">
       </a>
       <?php
      endforeach;
     endif;
     ?>
    </div>
        
        <div class="actions">
         <?php if ($isLoggedIn): ?>
          <button class="add-to-cart-btn" data-product-id="<?php echo $productId; ?>">🛒</button>
          <button class="favorite-btn" data-product-id="<?php echo $productId; ?>">❤️</button>
         <?php else: ?>
          <a href="register.php?redirect=cart&product=<?php echo $productId; ?>" class="add-to-cart-btn warning-btn">🛒</a>
          <a href="register.php?redirect=favorite&product=<?php echo $productId; ?>" class="favorite-btn warning-btn">❤️</a>
         <?php endif; ?>
        </div>
       </div>
      </div>
      <?php
     endforeach;
    endif;
    ?>
   </div>
  </section>
 </main>

 <footer class="main-footer">
  <p>&copy; 2025 <?php echo $markaAdi; ?>. Tüm Hakları Saklıdır.</p>
 </footer>
 
 <?php if (!$isLoggedIn): ?>
 <script>
  document.querySelectorAll('.warning-btn').forEach(button => {
   button.addEventListener('click', (e) => {
    if (!confirm('Bu işlemi yapmak için giriş yapmalısınız. Giriş sayfasına yönlendiriliyorsunuz.')) {
     e.preventDefault(); 
    }
   });
  });
 </script>
 <?php endif; ?>

 <script src="../../js/users/home.js"></script>
</body>
</html>