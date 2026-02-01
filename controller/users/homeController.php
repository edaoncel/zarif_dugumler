<?php
// E:\xampp\htdocs\sinem\controller\users\homeController.php

// Veritabanı bağlantısı dahil edildiğinde $pdo değişkeninin oluştuğu varsayılır.
include_once "../../db/db.php"; 

// PDO nesnesinin adının $pdo olduğu varsayılmıştır.
if (!isset($pdo) || !$pdo instanceof PDO) {
  // Veritabanı bağlantısı yoksa, güvenli varsayılanlar ayarla ve controller'ı sonlandır.
  $markaAdi = "ZARİF DÜĞÜMLER"; 
  $categories = [];
  $featuredProducts = [];
  $isLoggedIn = false;
  $userName = '';
  $userData = [];
  error_log("Hata: PDO nesnesi bulunamadı veya geçersiz.");
  return;
}

// =================================================================
// GENEL YARDIMCI FONKSİYONLAR
// =================================================================

/**
* Metni URL dostu hale getirir (slug).
*/
function slugify(string $text): string {
  $text = mb_strtolower($text, 'UTF-8');
  $text = str_replace(array('ı', 'İ', 'ş', 'Ş', 'ğ', 'Ğ', 'ü', 'Ü', 'ö', 'Ö', 'ç', 'Ç'), 
            array('i', 'i', 's', 's', 'g', 'g', 'u', 'u', 'o', 'o', 'c', 'c'), 
            $text);
  $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
  $text = preg_replace('/[\s-]+/', '-', $text);
  return trim($text, '-');
}

/**
* Belirli bir ID'ye göre kullanıcı verilerini çeker.
*/
function getUserDataFromDB(PDO $pdo, int $userId): array {
  $sql = "SELECT username, userPhoto FROM users WHERE id = :id";
  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?? [];
  } catch (PDOException $e) {
    error_log("Kullanıcı veri çekme hatası: " . $e->getMessage()); 
    return [];
  }
}

/**
 * Renk hex kodunun geçerli bir format olup olmadığını kontrol eder.
 */
function isValidHexColor(string $hex): bool {
  return preg_match('/^#([a-fA-F0-9]{3}){1,2}$/', $hex);
}

// =================================================================
// KATEGORİ VE ÜRÜN ÇEKME FONKSİYONLARI
// =================================================================

/**
* Veritabanından tüm kategorileri ve bunlara bağlı alt kategorileri hiyerarşik olarak çeker.
*/
function getAllCategories(PDO $pdo): array {
  $sql = "
    SELECT 
      c.id AS categoryId, 
      c.name AS categoryName,
      sc.id AS subCategoryId,
      sc.name AS subCategoryName
    FROM categories c
    LEFT JOIN subCategories sc ON c.id = sc.categoryId
    ORDER BY c.sortOrder ASC, c.name ASC, sc.sortOrder ASC, sc.name ASC
  ";
    $categories = [];
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($results as $row) {
            $cId = $row['categoryId'];
            $cName = $row['categoryName'];
            $scId = $row['subCategoryId'];
            $scName = $row['subCategoryName'];

            if (!isset($categories[$cId])) {
                $categories[$cId] = [
                    'id' => $cId,
                    'name' => $cName,
                    'subCategories' => []
                ];
            }

            if ($scId !== null) {
                $categories[$cId]['subCategories'][] = [
                    'id' => $scId,
                    'name' => $scName,
                ];
            }
        }
        
        return array_values($categories); 
    } catch (PDOException $e) {
        error_log("Kategori çekme hatası: " . $e->getMessage()); 
        return [];
    }
}


/**
 * Öne çıkan ürünleri, görsellerini ve renk (varyant) bilgilerini çeker.
 */
function getFeaturedProducts(PDO $pdo): array {
  // Ana ürün verilerini çek
  $sql = "
    SELECT 
      p.id, p.name, p.description, p.price
    FROM product p
    WHERE p.isDeleted = 0 
    ORDER BY p.id ASC
  ";

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($products)) {
      return [];
    }

    foreach ($products as $key => $product) {
      $productId = $product['id'];

      // 1. Renk Varyantlarını Çekme (colorSlug burada mevcut)
      $colorSql = "
        SELECT DISTINCT 
          color, 
          colorHexCode,
          colorSlug
        FROM productVariants 
        WHERE productId = :id 
        AND stockQuantity > 0
        ORDER BY colorSlug ASC
      ";
      $colorStmt = $pdo->prepare($colorSql);
      $colorStmt->execute([':id' => $productId]);
      $products[$key]['colors'] = $colorStmt->fetchAll(PDO::FETCH_ASSOC);

            
      // 2. Ürünün TÜM Görsellerini Çekme (BASİT VE HATASIZ SORGULAMA)
            // productImages tablosunda sadece imageUrl ve sortOrder sütunlarını istiyoruz.
      $imageSql = "
                SELECT 
                    imageUrl
                FROM productImages 
                WHERE productId = :id 
                ORDER BY sortOrder ASC
            ";
            
      $imageStmt = $pdo->prepare($imageSql);
      $imageStmt->execute([':id' => $productId]);
      $allImages = $imageStmt->fetchAll(PDO::FETCH_COLUMN, 0); // Sadece URL dizisi çek

            $processedImages = [];
            $firstImageUrl = '../../img/default-product.jpg';
            
            // DİKKAT: Görsel tablosunda renk bilgisi olmadığı için, 
            // home.php'deki JavaScript'in eşleştirme yapabilmesi için her URL'ye geçici bir colorSlug atamalıyız.
            // Bu, slider'ın çökmesini engeller ancak renk seçimine göre görsel değişimi için veritabanında daha güçlü bir ilişki gerekir.
            $colorSlugs = array_column($products[$key]['colors'], 'colorSlug');
            $numColors = count($colorSlugs);
            
            foreach ($allImages as $index => $url) {
                // Eğer hiç renk varyantı yoksa 'default' kullan, varsa döngüsel olarak renk slug'ı ata.
                $assignedColorSlug = 'default';
                if ($numColors > 0) {
                    $assignedColorSlug = $colorSlugs[$index % $numColors];
                }
                
                $processedImages[] = [
                    'url' => $url,
                    'colorSlug' => $assignedColorSlug
                ];

                if ($index === 0) {
                    $firstImageUrl = $url;
                }
            }


      // Ürün listesine görselleri ekle
      $products[$key]['images'] = $processedImages; 

      // Varsayılan/İlk görsel yolu
      $products[$key]['firstImageUrl'] = $firstImageUrl;
      
    }

    return $products;

  } catch (PDOException $e) {
    error_log("KRİTİK ÜRÜN ÇEKME HATASI: " . $e->getMessage()); 
    return [];
  }
}


// =================================================================
// SAYFA VERİLERİNİ HAZIRLAMA VE OTURUM KONTROLÜ
// =================================================================

// --- Marka Adı ---
$markaAdi = "ZARİF DÜĞÜMLER";

// --- OTURUM KONTROLÜ ---
$isLoggedIn = false; 
$userName = '';
$userData = []; 

if (isset($_SESSION['user_id'])) {
  $userId = (int)$_SESSION['user_id'];
  $userData = getUserDataFromDB($pdo, $userId); 
  
  if (!empty($userData)) {
    $isLoggedIn = true;
    $userName = htmlspecialchars($userData['username']);
  }
}

// --- Ana Veri Çekimleri ---
$categories = getAllCategories($pdo); 
$featuredProducts = getFeaturedProducts($pdo);
?>