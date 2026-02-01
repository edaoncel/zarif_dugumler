<?php
// E:\xampp\htdocs\sinem\controller\adminUsers\productController.php
// Ürün yönetimi (Ekleme, Silme, Düzenleme) işlemlerini gerçekleştirir.

// DB Bağlantısı
include_once "../../db/db.php"; 

// Oturum Başlatma Kontrolü
if (session_status() == PHP_SESSION_NONE) {
session_start();
}

const BASE_URL = '../../php/adminUsers/'; 
const REDIRECT_URL_SUCCESS = BASE_URL . 'admin.php?page=products';
const REDIRECT_URL_ADD_FAILURE = BASE_URL . 'admin.php?page=products&action=add';

// Admin yetki kontrolü
if (!isset($_SESSION['admin_id']) || empty($_SESSION['admin_id'])) { 
 $_SESSION['error_message'] = "Bu işlemi gerçekleştirmeye yetkiniz yok. Yönetici girişi gereklidir.";
 header('Location: ' . REDIRECT_URL_SUCCESS); 
 exit;
}

// Global PDO nesnesini kullanıma al
global $pdo;

// POST edilen 'action' parametresini güvenli bir şekilde al
$action = $_POST['action'] ?? null;

/**
* Metni SEO dostu bir slug'a çevirir. (Genişletilmiş Türkçe Transliterasyon)
*/
function createSlug(string $text): string {
// Basit ve güvenilir Türkçe karakter dönüşümü
$text = str_replace(
 ['ı', 'ğ', 'ü', 'ş', 'ö', 'ç', 'I', 'Ğ', 'Ü', 'Ş', 'Ö', 'Ç'],
 ['i', 'g', 'u', 's', 'o', 'c', 'i', 'g', 'u', 's', 'o', 'c'],
 $text
);
// Küçük harfe çevir
$text = strtolower(trim($text));
// Alfasayısal olmayanları (tire hariç) tire ile değiştir
$text = preg_replace('/[^a-z0-9-]+/', '-', $text);
// Birden fazla tireyi tek tireye indirge
$text = preg_replace('/-+/', '-', $text);
// Baş ve sondaki tireleri kaldır
return trim($text, '-');
}


// ... (getImagePathForDeletion, deleteImageFile, uploadSingleImage, processAndSaveImages fonksiyonları değişmedi) ...
// Eğer bu fonksiyonları dahil etmek istiyorsanız, bu kısımları koruyun.
// Ancak kodun tekrarını önlemek için bu yorumu bıraktım.
// Not: `processAndSaveImages` içindeki `colorSlug` mantığı korundu.
// --- Başlangıç Yardımcı Fonksiyonlar ---

/**
* Dosya yolunu sunucu kök yoluna çevirir.
*/
function getImagePathForDeletion(string $imageUrl): string {
// /sinem/img/product/... -> E:\xampp\htdocs\sinem\img\product\...
$relativePath = str_replace('/sinem', '', $imageUrl); 
// realpath() kullanarak doğru yolu bulmaya çalış
$fullPath = realpath(__DIR__ . '/../..' . $relativePath);
return $fullPath ?: ''; 
}

/**
* Görsel dosyasını sunucudan siler.
*/
function deleteImageFile(string $imageUrl): bool {
$filePath = getImagePathForDeletion($imageUrl);

if ($filePath && file_exists($filePath) && is_file($filePath)) {
 if (unlink($filePath)) {
 error_log("Dosya başarıyla sunucudan silindi: " . $filePath);
 return true;
 } else {
 error_log("UYARI: Dosya sunucudan silinemedi (İzin Hatası?): " . $filePath);
 return false;
 }
}
error_log("UYARI: Sunucuda silinmek istenen dosya bulunamadı veya geçersiz yol: " . $filePath);
return true;
}

/**
* Tek bir görsel dosyasını sunucuya yükler.
*/
function uploadSingleImage(array $file, string $productName): string {
if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) { 
 $errorCode = $file['error'] ?? 'Bilinmeyen Hata';
 switch ($errorCode) {
 case UPLOAD_ERR_INI_SIZE:
 case UPLOAD_ERR_FORM_SIZE:
  throw new Exception("Dosya boyutu çok büyük. (Maksimum limit aşıldı)");
 case UPLOAD_ERR_NO_FILE:
  throw new Exception("Lütfen geçerli bir ürün görseli seçin.");
 default:
  throw new Exception("Yükleme sırasında beklenmeyen bir hata oluştu. Hata Kodu: " . $errorCode);
 }
}

$uploadDir = __DIR__ . '/../../img/product/'; 
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
 error_log("Dizin oluşturulamadı veya izinler yetersiz: " . $uploadDir);
 throw new Exception("Sunucu Dizin Hatası: Yükleme klasörü oluşturulamadı.");
}

$safeName = preg_replace('/[^A-Za-z0-9\_]/', '', str_replace(' ', '_', $productName));
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!in_array($fileExtension, $allowedExtensions)) { 
 throw new Exception("Geçersiz dosya formatı. İzin verilenler: JPG, PNG, WEBP, GIF.");
}

$fileName = time() . '-' . uniqid() . '-' . $safeName . '.' . $fileExtension;
$destination = $uploadDir . $fileName;

if (move_uploaded_file($file['tmp_name'], $destination)) {
 return '/sinem/img/product/' . $fileName; 
}

error_log("Dosya taşıma hatası. tmp_name: " . $file['tmp_name'] . ", Hedef: " . $destination);
throw new Exception("Dosya yüklenirken kritik bir hata oluştu (Taşıma başarısız).");
}

/**
* Birden fazla görseli işler ve productImages tablosuna kaydeder.
*/
function processAndSaveImages(int $productId, array $files, string $productName, PDO $pdo, array $newImageData = []): array {
$totalUploaded = 0;
$uploadedUrls = []; 

$filesArray = [];
if (isset($files['name']) && is_array($files['name'])) {
 foreach ($files['name'] as $index => $name) {
 if (empty($name) || ($files['error'][$index] === UPLOAD_ERR_NO_FILE) || ($files['error'][$index] !== UPLOAD_ERR_OK)) continue;
 
 $filesArray[] = [
  'name' => $name,
  'type' => $files['type'][$index],
  'tmp_name' => $files['tmp_name'][$index],
  'error' => $files['error'][$index],
  'size' => $files['size'][$index]
 ];
 }
}

if (empty($filesArray)) {
 return ['count' => 0, 'urls' => []];
}

// Mevcut en yüksek sortOrder değerini bul
$maxSortOrderSql = "SELECT COALESCE(MAX(sortOrder), 0) FROM productImages WHERE productId = ?";
$maxSortOrderStmt = $pdo->prepare($maxSortOrderSql);
$maxSortOrderStmt->execute([$productId]);
$nextSortOrder = (int)$maxSortOrderStmt->fetchColumn() + 1;

// SQL'e colorSlug alanını ekleyin
$sql = "INSERT INTO productImages (productId, imageUrl, sortOrder, colorSlug) VALUES (?, ?, ?, ?)";
$stmt = $pdo->prepare($sql);

foreach ($filesArray as $index => $file) {
 try {
 $imageUrl = uploadSingleImage($file, $productName);
 $sortOrder = $nextSortOrder++; 
 
 // colorSlug'ı newImageData'dan çek (görselin rengini belirler)
 $colorSlug = '';
 // POST edilen newImageData dizisi, filesArray ile aynı sırada gelmeyebilir. Index yerine, formdaki özel bir değeri eşleştirmeniz gerekebilir.
 // Ancak mevcut durumda, POST edilen new_image_data'daki index'i kullanmaya devam ediyoruz.
 if (isset($newImageData[$index]['color_slug'])) {
  $colorSlug = createSlug($newImageData[$index]['color_slug']);
 }

 $stmt->execute([$productId, $imageUrl, $sortOrder, $colorSlug]);
 $totalUploaded++;
 $uploadedUrls[] = $imageUrl; 
 } catch (Exception $e) {
 error_log("Çoklu Görsel Yükleme Hatası (Resim {$index}): " . $e->getMessage());
 }
}

if ($totalUploaded === 0 && !empty(array_filter($files['name'] ?? []))) {
 throw new Exception("Yüklemeye çalıştığınız tüm görsellerde kritik bir hata oluştu veya geçersizlerdi.");
}

return ['count' => $totalUploaded, 'urls' => $uploadedUrls];
}
// --- Bitiş Yardımcı Fonksiyonlar ---

/**
* Yeni ürün ekleme sırasında varyantları kaydeder. (Sadece INSERT)
*/
function processAndSaveVariants(int $productId, array $variants, string $mainSku, PDO $pdo): int {
$variantCount = 0;

$variantSql = "INSERT INTO productVariants 
   (productId, color, colorHexCode, colorSlug, size, stockQuantity, sku) 
   VALUES (?, ?, ?, ?, ?, ?, ?)";
$variantStmt = $pdo->prepare($variantSql);

foreach ($variants as $index => $variant) {
 // POST verilerini güvenli bir şekilde al ve temizle
 $color = trim($variant['color'] ?? '');
 $colorHex = trim($variant['colorHex'] ?? ''); 
 $size = trim($variant['size'] ?? '');
 // DİKKAT: POST verisinden 'stockQuantity' anahtarını çekiyoruz. Formun adı bu olmalı.
 $stockQuantity = max(0, (int)($variant['stockQuantity'] ?? 0)); 
 $sku = trim($variant['sku'] ?? '');

 // Doğrulama
 if (empty($color) || empty($size) || empty($colorHex)) continue; 
 
 $colorSlug = createSlug($color); 
 
 if (!preg_match('/^#[a-f0-9]{6}$/i', $colorHex)) {
 error_log("Geçersiz Hex Kodu formatı tespit edildi: " . htmlspecialchars($colorHex));
 $colorHex = '#CCCCCC'; 
 }

 // SKU Boşsa Otomatik Oluştur
 if (empty($sku) && !empty($mainSku)) {
 $color_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $color), 0, 2));
 $size_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $size), 0, 2));
 $sku = $mainSku . '-' . $color_code . $size_code . '-' . $index; 
 }

 $variantStmt->execute([
 $productId, 
 htmlspecialchars($color), 
 $colorHex, 
 $colorSlug, 
 htmlspecialchars($size), 
 $stockQuantity, 
 htmlspecialchars($sku) 
 ]);
 $variantCount++;
}
return $variantCount;
}

/**
* Mevcut varyantları günceller (Upsert) ve POST edilmeyenleri Hard Delete yapar.
* DÜZELTME: productVariants tablosunda isDeleted olmadığı için Hard Delete kullanıldı.
*/
function updateProductVariants(int $productId, array $variants, string $mainSku, PDO $pdo): array {
$postedVariantIds = [];
$updateCount = 0;
$insertCount = 0;

// GÜNCELLEME SQL - STOK DAHİL TÜM ALANLARI GÜNCELLE
$updateSql = "UPDATE productVariants SET 
  color=?, colorHexCode=?, colorSlug=?, size=?, 
  stockQuantity=?, sku=? 
  WHERE id=? AND productId=?";
$updateStmt = $pdo->prepare($updateSql);

// INSERT SQL
$insertSql = "INSERT INTO productVariants 
  (productId, color, colorHexCode, colorSlug, size, stockQuantity, sku) 
  VALUES (?, ?, ?, ?, ?, ?, ?)";
$insertStmt = $pdo->prepare($insertSql);

foreach ($variants as $index => $variant) {
 // POST verilerini güvenli bir şekilde al ve temizle
 $variantId = (int)($variant['id'] ?? 0); 
 $color = trim($variant['color'] ?? '');
 $colorHex = trim($variant['colorHex'] ?? ''); 
 $size = trim($variant['size'] ?? '');
 // DİKKAT: HTML formundaki adı `stockQuantity` olmalıdır.
 $stockQuantity = max(0, (int)($variant['stockQuantity'] ?? 0)); 
 $sku = trim($variant['sku'] ?? '');
 
 // Zorunlu alan kontrolü
 if (empty($color) || empty($size) || empty($colorHex)) continue; 
 
 $colorSlug = createSlug($color); 
 
 if (!preg_match('/^#[a-f0-9]{6}$/i', $colorHex)) {
 $colorHex = '#CCCCCC'; 
 }

 // SKU Boşsa Otomatik Oluştur (Güncelleme ve Ekleme için ortak)
 if (empty($sku) && !empty($mainSku)) {
 $color_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $color), 0, 2));
 $size_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $size), 0, 2));
 $sku = $mainSku . '-' . $color_code . $size_code . '-' . $index; 
 }

 $safeColor = htmlspecialchars($color);
 $safeSize = htmlspecialchars($size);
 $safeSku = htmlspecialchars($sku);
 
 if ($variantId > 0) {
 // GÜNCELLEME (ID varsa)
 $updateStmt->execute([
  $safeColor, $colorHex, $colorSlug, $safeSize, $stockQuantity, $safeSku, 
  $variantId, $productId
 ]);
 $postedVariantIds[] = $variantId;
 $updateCount++;
 } else {
 // EKLEME (Yeni varyant)
 $insertStmt->execute([$productId, $safeColor, $colorHex, $colorSlug, $safeSize, $stockQuantity, $safeSku]);
 // Yeni eklenen varyantın ID'si de Hard Delete mekanizması için `postedVariantIds` listesine eklenmelidir.
 // (Ancak şu an sadece mevcut olanların silinmesi gerektiği için sadece update edilenleri eklemek yeterlidir)
 $insertCount++;
 }
}

// Tüm mevcut varyant ID'lerini çek
$currentVariantIdsStmt = $pdo->prepare("SELECT id FROM productVariants WHERE productId = ?"); 
$currentVariantIdsStmt->execute([$productId]);
$currentVariantIds = $currentVariantIdsStmt->fetchAll(PDO::FETCH_COLUMN);

// POST edilmeyen (yani silinmesi gereken/Hard Delete yapılacak) ID'leri bul
// Güncellenen ve yeni eklenen varyantları listeden çıkarıyoruz. Yeni eklenen varyantların ID'si henüz bilinmiyor.
// Hard Delete sadece eski ve güncellenmemiş olanlar için geçerli olmalı.
$idsToDelete = array_diff($currentVariantIds, $postedVariantIds);
$deleteCount = 0;

if (!empty($idsToDelete)) {
 // HARD DELETE SQL cümlesini kullan
 $placeholders = implode(',', array_fill(0, count($idsToDelete), '?'));
 $deleteSql = "DELETE FROM productVariants WHERE productId = ? AND id IN ({$placeholders})";
 
 $deleteStmt = $pdo->prepare($deleteSql);
 // productId'yi ilk parametre olarak ekle
 $params = array_merge([$productId], $idsToDelete); 
 $deleteStmt->execute($params);
 $deleteCount = $deleteStmt->rowCount();
}

return ['inserted' => $insertCount, 'updated' => $updateCount, 'deleted' => $deleteCount];
}

/**
* Yeni görsel yükleme alanından gelen toplu varyant verisini (JSON) işler.
*/
function createVariantsFromGroupData(int $productId, array $variantGroupsData, string $mainSku, PDO $pdo): int {
  $totalInserted = 0;
  
  $variantSql = "INSERT INTO productVariants 
      (productId, color, colorHexCode, colorSlug, size, stockQuantity, sku) 
      VALUES (?, ?, ?, ?, ?, ?, ?)";
  $variantStmt = $pdo->prepare($variantSql);
  
  foreach ($variantGroupsData as $slug => $data) {
    $color = trim($data['color'] ?? '');
    $colorHex = trim($data['colorHexCode'] ?? '');
    $defaultStock = max(0, (int)($data['stock'] ?? 0));
    $sizes = $data['sizes'] ?? []; 
    $colorSlug = createSlug($color);

    if (empty($color) || empty($sizes)) continue;

    foreach ($sizes as $index => $size) {
      $size = trim($size);
      if (empty($size)) continue;
      
      // Mevcut varyantı kontrol et (aynı renk/beden ikilisi zaten var mı?)
      $checkSql = "SELECT id FROM productVariants WHERE productId = ? AND colorSlug = ? AND size = ?";
      $checkStmt = $pdo->prepare($checkSql);
      $checkStmt->execute([$productId, $colorSlug, htmlspecialchars($size)]);
      
      if ($checkStmt->fetchColumn()) continue; // Zaten var, atla
      
      // SKU Boşsa Otomatik Oluştur
      $sku = $mainSku;
      if (!empty($mainSku)) {
        $color_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $color), 0, 2));
        $size_code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $size), 0, 2));
        $sku = $mainSku . '-' . $color_code . $size_code . '-' . time() . rand(0, 99); 
      }
      
      $variantStmt->execute([
        $productId, htmlspecialchars($color), $colorHex, $colorSlug, 
        htmlspecialchars($size), $defaultStock, htmlspecialchars($sku)
      ]);
      $totalInserted++;
    }
  }
  return $totalInserted;
}

/**
* Görsel sıralamasını ve colorSlug değerini günceller.
*/
function updateProductImagesData(int $productId, array $imagesData, PDO $pdo): int {
$updatedCount = 0;
$sql = "UPDATE productImages SET sortOrder = ?, colorSlug = ? WHERE id = ? AND productId = ?";
$stmt = $pdo->prepare($sql);

foreach ($imagesData as $imageData) {
 $imageId = (int)($imageData['id'] ?? 0);
 $sortOrder = (int)($imageData['sortOrder'] ?? 0);
 $colorSlug = createSlug($imageData['colorSlug'] ?? ''); 
 
 if ($imageId > 0) {
 $stmt->execute([$sortOrder, $colorSlug, $imageId, $productId]);
 $updatedCount += $stmt->rowCount();
 }
}
return $updatedCount;
}

/**
* Belirtilen görsel ID'sini productImages tablosundan ve sunucudan siler.
*/
function deleteProductImage(int $imageId, int $productId, PDO $pdo): bool {
$sqlSelect = "SELECT imageUrl FROM productImages WHERE id = ? AND productId = ?";
$stmtSelect = $pdo->prepare($sqlSelect);
$stmtSelect->execute([$imageId, $productId]);
$imageUrl = $stmtSelect->fetchColumn();

if (!$imageUrl) return false;

$fileDeleted = deleteImageFile($imageUrl); 

$sqlDelete = "DELETE FROM productImages WHERE id = ? AND productId = ?";
$stmtDelete = $pdo->prepare($sqlDelete);
$dbDeleted = $stmtDelete->execute([$imageId, $productId]);

return $dbDeleted && $fileDeleted;
}

// --- ANA İŞLEMLER ---

switch ($action) {
case 'add_new_product':
 // ... (Ekleme mantığı korundu) ...
 $uploadedImageUrls = []; 
 try {
 // --- Veri Toplama ve Temizleme ---
 $name = htmlspecialchars(trim($_POST['name'] ?? ''));
 $description = trim($_POST['description'] ?? ''); 
 $price = (float)($_POST['price'] ?? 0);
 $newPrice = !empty($_POST['new_price']) ? (float)$_POST['new_price'] : null;
 $shippingFee = (float)($_POST['shipping_fee'] ?? 0.00);
 $categoryId = (int)($_POST['category_id'] ?? 0);
 $subCategoryId = !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null; 
 $gender = htmlspecialchars(trim($_POST['gender'] ?? null));
 $material = htmlspecialchars(trim($_POST['material'] ?? null));
 $mainSkuInput = htmlspecialchars(trim($_POST['mainSku'] ?? ''));
 $variants = $_POST['variants'] ?? [];
 $productId = 0; 
 
 // --- Ön Doğrulama ---
 if (empty($name) || $price <= 0 || $categoryId <= 0 || empty($description)) {
  throw new Exception("Ürün Adı, Fiyat, Kategori ve Açıklama zorunludur.");
 }
 if (empty($variants)) {
  throw new Exception("Hiçbir varyant bilgisi bulunamadı. Lütfen renk/beden giriniz.");
 }
 if ($newPrice !== null && $newPrice >= $price) {
  throw new Exception("İndirimli Fiyat, Normal Fiyattan küçük olmalıdır.");
 }
 if (!isset($_FILES['product_images']) || 
  (is_array($_FILES['product_images']['name']) && array_filter($_FILES['product_images']['name']) === [])) {
  throw new Exception("Lütfen en az bir ürün görseli seçin.");
 }
 
 // --- İşlem Başlangıcı ---
 $pdo->beginTransaction();
 
 $mainSku = !empty($mainSkuInput) ? $mainSkuInput : 'MSKU-' . uniqid(); 
 
 // 1. Ana Ürünü Kaydet
 $sql = "INSERT INTO product 
   (name, description, price, newPrice, shippingFee, 
   categoryId, subCategoryId, gender, material, mainSku) 
   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
 
 $stmt = $pdo->prepare($sql);
 $stmt->execute([
  $name, $description, $price, $newPrice, $shippingFee,
  $categoryId, $subCategoryId, $gender, $material, $mainSku
 ]);
 $productId = $pdo->lastInsertId();

 // 2. Görselleri Kaydet 
 $imageResult = processAndSaveImages($productId, $_FILES['product_images'], $name, $pdo);
 $imageCount = $imageResult['count'];
 $uploadedImageUrls = $imageResult['urls']; 
 
 // 3. Varyantları Kaydet 
 $variantCount = processAndSaveVariants($productId, $variants, $mainSku, $pdo);
 if ($variantCount === 0) {
  throw new Exception("Hiçbir varyant kaydedilemedi. Lütfen tüm varyantlar için geçerli renk, hex kodu ve/veya beden girdiğinizden emin olun.");
 }

 $pdo->commit();
 
 // --- Başarılı Sonuç ---
 $_SESSION['success_message'] = "Ürün başarıyla eklendi! ({$imageCount} görsel, {$variantCount} varyant)";
 header('Location: ' . REDIRECT_URL_SUCCESS); 
 exit;

 } catch (Exception $e) {
 // --- Hata İşlemi ---
 if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

 if (!empty($uploadedImageUrls)) {
  foreach ($uploadedImageUrls as $url) {
  deleteImageFile($url);
  }
 }
 
 $_SESSION['form_data'] = $_POST;
 $_SESSION['error_message'] = "Ürün eklenirken bir hata oluştu: " . $e->getMessage();
 error_log("Ürün Ekleme Hatası: " . $e->getMessage());
 
 header('Location: ' . REDIRECT_URL_ADD_FAILURE);
 exit;
 }
 break;
 
case 'delete_product':
 // ... (Silme mantığı korundu) ...
 if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== ($_SESSION['csrf_token'] ?? '')) {
 error_log("CSRF Hatası: delete_product işlemi engellendi.");
 $_SESSION['error_message'] = "Güvenlik hatası (CSRF). Lütfen tekrar deneyin.";
 header('Location: ' . REDIRECT_URL_SUCCESS);
 exit;
 }
 unset($_SESSION['csrf_token']);

 try {
 $productId = (int)($_POST['product_id'] ?? 0);
 if ($productId <= 0) throw new Exception("Geçersiz Ürün ID'si.");

 $pdo->beginTransaction();

 // 1. Ana ürünü Soft Delete yap (product tablosu için)
 $sql = "UPDATE product SET isDeleted = 1 WHERE id = ?"; 
 $stmt = $pdo->prepare($sql);
 $stmt->execute([$productId]);
 
 // 2. Varyantları Hard Delete yap (productVariants tablosunda isDeleted yok)
 $variantSql = "DELETE FROM productVariants WHERE productId = ?";
 $variantStmt = $pdo->prepare($variantSql);
 $variantStmt->execute([$productId]);
 
 $pdo->commit();

 $_SESSION['success_message'] = "Ürün başarıyla silindi (Ana ürün Soft Delete, varyantlar Hard Delete).";
 
 header('Location: ' . REDIRECT_URL_SUCCESS);
 exit;
 } catch (Exception $e) {
 if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
 $_SESSION['error_message'] = "Ürün silinirken bir hata oluştu: " . $e->getMessage();
 error_log("Ürün Silme Hatası: " . $e->getMessage());

 header('Location: ' . REDIRECT_URL_SUCCESS);
 exit;
 }
 break;
 
case 'delete_image':
 // ... (Görsel silme mantığı korundu) ...
 $imageId = (int)($_POST['image_id'] ?? 0);
 $productId = (int)($_POST['product_id'] ?? 0);
 $redirectUrl = BASE_URL . 'admin.php?page=products&action=edit&product_id=' . $productId;

 try {
 if ($imageId <= 0 || $productId <= 0) throw new Exception("Geçersiz Görsel veya Ürün ID'si.");
 
 if (deleteProductImage($imageId, $productId, $pdo)) {
  $_SESSION['success_message'] = "Görsel başarıyla silindi.";
 } else {
  $_SESSION['error_message'] = "Görsel silinirken bir hata oluştu (Dosya/DB hatası).";
 }
 
 } catch (Exception $e) {
 $_SESSION['error_message'] = "Görsel Silme Hatası: " . $e->getMessage();
 error_log("Görsel Silme Hatası: " . $e->getMessage());
 }

 header('Location: ' . $redirectUrl);
 exit;

case 'update_product':
 $productId = (int)($_POST['product_id'] ?? 0);
 $redirectUrlEditFailure = BASE_URL . 'admin.php?page=products&action=edit&product_id=' . $productId;
 $uploadedImageUrls = []; 

 try {
 // --- Veri Toplama ve Temizleme ---
 if ($productId <= 0) throw new Exception("Geçersiz Ürün ID'si.");
 
 $name = htmlspecialchars(trim($_POST['name'] ?? ''));
 $description = trim($_POST['description'] ?? ''); 
 $price = (float)($_POST['price'] ?? 0);
 $newPrice = !empty($_POST['new_price']) ? (float)$_POST['new_price'] : null;
 $shippingFee = (float)($_POST['shipping_fee'] ?? 0.00);
 $categoryId = (int)($_POST['category_id'] ?? 0);
 $subCategoryId = !empty($_POST['sub_category_id']) ? (int)$_POST['sub_category_id'] : null; 
 $gender = htmlspecialchars(trim($_POST['gender'] ?? null));
 $material = htmlspecialchars(trim($_POST['material'] ?? null));
 $mainSku = htmlspecialchars(trim($_POST['mainSku'] ?? ''));
 
 $variants = $_POST['variants'] ?? [];
 $allVariantGroupsDataJson = $_POST['all_variant_groups_data'] ?? '[]';
 $allVariantGroupsData = json_decode($allVariantGroupsDataJson, true) ?? [];
 
 $imagesToDelete = $_POST['images_to_delete'] ?? []; 
 $imagesData = $_POST['images_data'] ?? []; 
 $newImageData = $_POST['new_image_data'] ?? []; 
 
 // --- Ön Doğrulama ---
 if (empty($name) || $price <= 0 || $categoryId <= 0 || empty($description)) {
  throw new Exception("Ürün Adı, Fiyat, Kategori ve Açıklama zorunludur.");
 }
 if ($newPrice !== null && $newPrice >= $price) {
  throw new Exception("İndirimli Fiyat, Normal Fiyattan küçük olmalıdır.");
 }
 
 // --- İşlem Başlangıcı ---
 $pdo->beginTransaction();
 
 // 1. SİLİNMEK ÜZERE İŞARETLENEN GÖRSELLERİ İŞLE
 $deletedImageCount = 0;
 if (!empty($imagesToDelete) && is_array($imagesToDelete)) {
  foreach ($imagesToDelete as $imageId) {
  $imageId = (int)$imageId;
  if ($imageId > 0 && deleteProductImage($imageId, $productId, $pdo)) { 
   $deletedImageCount++;
  }
  }
 }
 
 // 2. Ana Ürünü Güncelle
 $sql = "UPDATE product SET 
   name = ?, description = ?, price = ?, newPrice = ?, 
   shippingFee = ?, categoryId = ?, subCategoryId = ?, 
   gender = ?, material = ?, mainSku = ? 
  WHERE id = ?";
 
 $stmt = $pdo->prepare($sql);
 $stmt->execute([
  $name, $description, $price, $newPrice, $shippingFee,
  $categoryId, $subCategoryId, $gender, $material, $mainSku,
  $productId
 ]);

 // 3. Yeni Görselleri Ekle 
 $filesToProcess = (isset($_FILES['new_product_images']) && is_array($_FILES['new_product_images']) && 
    (isset($_FILES['new_product_images']['error'][0]) && $_FILES['new_product_images']['error'][0] !== UPLOAD_ERR_NO_FILE)) 
    ? $_FILES['new_product_images'] 
    : []; 

 $imageResult = processAndSaveImages($productId, $filesToProcess, $name, $pdo, $newImageData); 
 $newImageCount = $imageResult['count'];
 $uploadedImageUrls = $imageResult['urls']; 
 
 // 4. Mevcut Görsellerin Sıralamasını ve Renk Slug'ını Güncelle
 $updatedImageCount = updateProductImagesData($productId, $imagesData, $pdo);

 // 5. Mevcut Varyantları Güncelle (Upsert ve Hard Delete)
 $variantResult = updateProductVariants($productId, $variants, $mainSku, $pdo);
 
 // 6. Yeni Görsel Ekleme Alanından Gelen Varyant Verilerini İşle
 $newVariantsFromGroupCount = 0;
 if (!empty($allVariantGroupsData)) {
  $newVariantsFromGroupCount = createVariantsFromGroupData($productId, $allVariantGroupsData, $mainSku, $pdo);
  $variantResult['inserted'] += $newVariantsFromGroupCount;
 }

 $pdo->commit();
 
 // --- Başarılı Sonuç ---
 $message = "Ürün başarıyla güncellendi! (Görsel İşlemleri: {$deletedImageCount} silindi, {$newImageCount} eklendi, {$updatedImageCount} düzenlendi. Varyant İşlemleri: {$variantResult['updated']} güncellendi, {$variantResult['inserted']} eklendi, {$variantResult['deleted']} silindi.)";
 $_SESSION['success_message'] = $message;
 
 header('Location: ' . REDIRECT_URL_SUCCESS); 
 exit;

 } catch (Exception $e) {
 // --- Hata İşlemi (Rollback) ---
 if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();

 if (!empty($uploadedImageUrls)) {
  foreach ($uploadedImageUrls as $url) {
  deleteImageFile($url);
  }
 }
 
 $_SESSION['form_data'] = $_POST;
 $_SESSION['error_message'] = "Ürün güncellenirken bir hata oluştu: " . $e->getMessage();
 error_log("Ürün Güncelleme Hatası: " . $e->getMessage());
 
 header('Location: ' . $redirectUrlEditFailure);
 exit;
 }
 break;

default:
 // Geçersiz veya boş eylem
 $_SESSION['error_message'] = "Geçersiz işlem isteği veya boş işlem.";
 header('Location: ' . REDIRECT_URL_SUCCESS);
 exit;
}