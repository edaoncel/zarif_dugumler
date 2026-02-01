<?php
// E:\xampp\htdocs\sinem\php\adminUsers\products.php
include_once "../../db/db.php"; 

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
global $pdo;


function getProducts($pdo, $filters = []) {
    $sql = "
        SELECT 
            p.id, 
            p.name, 
            p.price, 
            p.newPrice, 
            p.mainSku, 
            c.name AS category_name, 
            sc.name AS subcategory_name,
            
            -- Toplam Stok Hesaplama (Varyant tablosundan)
            COALESCE(SUM(pv.stockQuantity), 0) AS total_stock,
            
            -- Stoklu Varyantları Birleştirme (Renk|Beden)
            GROUP_CONCAT(
                DISTINCT CASE 
                    WHEN pv.stockQuantity > 0 THEN CONCAT(pv.color, '|', pv.size) 
                    ELSE NULL 
                END 
                SEPARATOR ','
            ) AS stocked_variants_raw,
            
            -- Ürüne ait sıralaması en küçük olan görselin URL'sini getir
            (
                SELECT pi.imageUrl
                FROM productImages pi
                WHERE pi.productId = p.id
                ORDER BY pi.sortOrder ASC
                LIMIT 1
            ) AS first_image_url

        FROM 
            product p
        INNER JOIN 
            categories c ON p.categoryId = c.id
        LEFT JOIN 
            subCategories sc ON p.subCategoryId = sc.id
        LEFT JOIN 
            productVariants pv ON p.id = pv.productId -- Varyantlar ve Stok için JOIN
        
        WHERE 
            p.isDeleted = 0 
        -- Filtreleme koşulları buraya eklenecek...

        GROUP BY 
            p.id, p.name, p.price, p.newPrice, p.mainSku, 
            category_name, subcategory_name
        
        ORDER BY 
            p.id DESC; 
    ";

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Hata yönetimi
        error_log("Ürünleri çekerken SQL hatası: " . $e->getMessage());
        return [];
    }
}

/**
 * Ürünü, varyantları ve görselleriyle birlikte çeker (Düzenleme sayfası için).
 */
function getProductById($pdo, $productId) {
    $sqlProduct = "
        SELECT 
        p.*, 
        c.name AS category_name, c.id AS category_id,
        sc.name AS subcategory_name, sc.id AS subcategory_id
        FROM product p
        LEFT JOIN categories c ON p.categoryId = c.id
        LEFT JOIN subCategories sc ON p.subCategoryId = sc.id
        WHERE p.id = :id AND p.isDeleted = 0
    ";
    $stmtProduct = $pdo->prepare($sqlProduct);
    $stmtProduct->execute([':id' => $productId]);
    $product = $stmtProduct->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        return null;
    }

    // Varyantları Çek
    $sqlVariants = "
        SELECT id, color, colorSlug, colorHexCode, size, stockQuantity, sku
        FROM productVariants
        WHERE productId = :id
        ORDER BY color, size
    ";
    $stmtVariants = $pdo->prepare($sqlVariants);
    $stmtVariants->execute([':id' => $productId]);
    $variants = $stmtVariants->fetchAll(PDO::FETCH_ASSOC);
    $product['variants'] = $variants;

    // Görselleri Çek (colorSlug DAHİL)
    $sqlImages = "
        SELECT id, imageUrl, sortOrder, colorSlug 
        FROM productImages
        WHERE productId = :id
        ORDER BY sortOrder ASC
    ";
    $stmtImages = $pdo->prepare($sqlImages);
    $stmtImages->execute([':id' => $productId]); 
    $images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);
    $product['images'] = $images;

    return $product;
}
// Kategorileri çekme
function getCategories($pdo) {
    $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY sortOrder");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Alt Kategorileri çekme ve ID'ye göre gruplama
function getSubCategoriesGrouped($pdo) {
    $stmt = $pdo->query("SELECT id, categoryId, name FROM subCategories ORDER BY categoryId, sortOrder");
    $subCats = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $subCats[$row['categoryId']][] = ['id' => $row['id'], 'name' => $row['name']];
    }
    return $subCats;
}

// Çekilen veriler
$categories = getCategories($pdo);
$groupedSubCategories = getSubCategoriesGrouped($pdo);

// --- Sabit Seçenekler ---
$standardSizes = ['XS', 'S', 'M', 'L', 'XL', 'XXL']; // Standart bedenler
$genders = ['Kız', 'Erkek', 'Unisex']; 
$materials = ['Örgü', 'Kumaş', 'Deri', 'Sentetik'];
$babyAges = ['0-2 Ay', '3-6 Ay', '6-9 Ay', '9-12 Ay', '12-18 Ay', '18-24 Ay', '2 Yaş', '3 Yaş', '4 Yaş', '5 Yaş', '6 Yaş', '7 Yaş', '8 Yaş', '9 Yaş', '10 Yaş', '11 Yaş', '12 Yaş'];
$shoeSizes = ['18', '19', '20', '21', '22', '23', '24', '25', '26', '27', '28', '29', '30', '31', '32', '33', '34', '35', '36', '37', '38', '39', '40', '41'];
$textileSizes = ['Bebek', 'Tek Kişilik', 'Çift Kişilik', 'King Size'];
$childAdultSizes = ['Çocuk', 'Yetişkin'];


// --- ANA KATEGORİ KOŞUL HARİTASI (JS'ye aktarılacak) ---
$categoryConditions = [];
$ID_MAP = [
    'Kadın' => 1,
    'Çocuk & Bebek' => 2,
    'Ayakkabı' => 3,
    'Ev Tekstili' => 4, 
    'Çanta' => 5,
    'Şapka & Aksesuar' => 6,
];

foreach ($categories as $cat) {
    $catId = $cat['id'];
    $catName = $cat['name'];

    $condition = [
     'name' => $catName,
     'showSubcategory' => true, 'showGender' => true, 'showSize' => true,
     'sizeOptions' => $standardSizes, 'sizeType' => 'standard', 'genderType' => 'normal'
    ];

    switch ($catId) {
     case $ID_MAP['Kadın']: $condition['showGender'] = false; break;
     case $ID_MAP['Çocuk & Bebek']: $condition['sizeOptions'] = array_merge($standardSizes, $babyAges); $condition['sizeType'] = 'child_baby'; break;
     case $ID_MAP['Ayakkabı']: $condition['showSubcategory'] = false; $condition['showGender'] = true; $condition['sizeOptions'] = $shoeSizes; $condition['sizeType'] = 'shoe'; break;
     case $ID_MAP['Ev Tekstili']: $condition['showGender'] = false; $condition['sizeOptions'] = $textileSizes; $condition['sizeType'] = 'textile'; $condition['genderType'] = 'textile'; break;
     case $ID_MAP['Çanta']: case $ID_MAP['Şapka & Aksesuar']: $condition['showSubcategory'] = false; $condition['sizeOptions'] = $childAdultSizes; $condition['sizeType'] = 'child_adult'; break;
     default: $condition['showSubcategory'] = false; $condition['showGender'] = false; $condition['showSize'] = false; break;
    }
    
    // Alt kategorileri haritaya ekle
    $condition['subcategories'] = $groupedSubCategories[$catId] ?? [];
    
    $categoryConditions[$catId] = $condition;
}

// function getCategories($pdo) {
//     $stmt = $pdo->query("SELECT id, name FROM categories ORDER BY sortOrder ASC");
//     return $stmt->fetchAll(PDO::FETCH_ASSOC);
// }

// function getSubCategoriesGrouped($pdo) {
//     $sql = "SELECT categoryId, id, name FROM subCategories ORDER BY categoryId, sortOrder ASC";
//     $stmt = $pdo->query($sql);
//     $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
//     $grouped = [];
//     foreach ($data as $sub) {
//         $grouped[$sub['categoryId']][] = $sub;
//     }
//     return $grouped;
// }

$viewAction = $_GET['action'] ?? 'list';

?>

<head>
<link href="../../css/adminUsers/product.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>

<script>
const CATEGORY_CONDITIONS = <?php echo json_encode($categoryConditions, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); ?>;
const SUBCATEGORY_DATA = <?php echo json_encode($groupedSubCategories, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); ?>;
const EV_TEKSTILI_ID = <?php echo $ID_MAP['Ev Tekstili'] ?? 0; ?>;
</script>
<?php 
// Bu sayfanın başındaki ana kontrol akışı (add, edit veya list/default)

// ----------------------------------------------------------------
// GÖRÜNÜM 1: YENİ ÜRÜN EKLEME (action=add)
// ----------------------------------------------------------------
if ($viewAction == 'add'): 
?>

    <?php if (isset($_SESSION['success_message'])): ?>
    <div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
    <?php endif; ?>


    <form action="../../controller/adminUsers/productController.php" method="POST" enctype="multipart/form-data" class="product-form" id="productForm">
    <input type="hidden" name="action" value="add_new_product">

    <div class="form-row">
        <div class="form-column">
            <div class="form-group">
                <label for="name">Ürün Adı <span class="required">*</span></label>
                <input type="text" name="name" id="name" required>
            </div>

            <div class="form-group">
                <label for="product_images">Ürün Fotoğrafları (Çoklu Seçim) <span class="required">*</span></label>
                <input type="file" name="product_images[]" id="product_images" accept="image/*" multiple required>
                <small class="form-text text-muted">En az bir görsel yüklenmelidir.</small>
            </div>
            
            <div class="form-group">
                <label for="description">Açıklama <span class="required">*</span></label>
                <textarea name="description" id="description" rows="5" required></textarea> 
            </div>

            <div class="form-group">
                <label for="price">Normal Fiyat (₺) <span class="required">*</span></label>
                <input type="number" name="price" id="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="new_price">İndirimli Fiyat (₺) (İsteğe Bağlı)</label>
                <input type="number" name="new_price" id="new_price" step="0.01" min="0"> 
            </div>
        </div>

        <div class="form-column">
            
            <div class="form-group">
                <label for="mainSku">Ana SKU</label>
                <input type="text" name="mainSku" id="mainSku" placeholder="Örn: MSKU-1234">
                <small class="form-text text-muted">Varyant SKU'larının ön eki olarak kullanılır.</small>
            </div>

            <div class="form-group">
                <label for="shipping_fee">Varsayılan Kargo Ücreti (₺)</label>
                <input type="number" name="shipping_fee" id="shipping_fee" step="0.01" min="0" value="0.00">
            </div>

            <div class="form-group">
                <label for="category_id">Kategori <span class="required">*</span></label>
                <select name="category_id" id="category_id" required>
                    <option value="">Kategori Seçin</option>
                    <?php 
                    // $categories değişkeninin tanımlı olduğunu varsayıyoruz
                    foreach ($categories ?? [] as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['id']); ?>">
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">Varyant matrisini görebilmek için kategori seçin.</small>
            </div>

            <div class="form-group conditional-field" id="subcategory-group" style="display:none;">
                <label for="sub_category_id">Alt Kategori</label> 
                <select name="sub_category_id" id="sub_category_id"> 
                    <option value="">Alt Kategori Seçin</option>
                </select>
            </div>
            
            <div class="form-group conditional-field" id="gender-group" style="display:none;">
                <label for="gender">Cinsiyet <span class="required">*</span></label> 
                <select name="gender" id="gender"> 
                    <option value="">Seçin</option>
                    <?php 
                    // $genders değişkeninin tanımlı olduğunu varsayıyoruz
                    foreach ($genders ?? [] as $g): ?>
                    <option value="<?php echo htmlspecialchars($g); ?>"><?php echo htmlspecialchars($g); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group conditional-field" id="material-group">
                <label for="material">Materyal</label>
                <select name="material" id="material">
                    <option value="">Seçin</option>
                    <?php 
                    // $materials değişkeninin tanımlı olduğunu varsayıyoruz
                    foreach ($materials ?? [] as $m): ?>
                    <option value="<?php echo htmlspecialchars($m); ?>"><?php echo htmlspecialchars($m); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div> 
    
    <div class="form-row" style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px;">
        <div class="form-column full-width">
            <h4>Varyant Bilgileri <span class="required">*</span></h4>
            
            <div class="form-group">
                <label>Renk Varyantları (Ad ve Hex Kodu Seçimi)</label>
                <div id="color-variant-group">
                </div>
                <button type="button" id="add-color-btn" class="btn btn-secondary mt-2">
                    <i class="fas fa-plus"></i> Yeni Renk Ekle
                </button>
                <small class="form-text text-muted">Her renk için ad ve Hex kodu belirtmelisiniz.</small>
            </div>
                        
            <div class="form-group">
                <label for="sizes_input">Bedenler/Numaralar (Virgülle Ayırın)</label>
                <input type="text" name="sizes" id="sizes_input" placeholder="Örn: XS, S, M, L veya 36, 37" required>
            </div>
        </div>
        
        <div class="form-column full-width">
            <div id="stock-matrix-container" style="margin-top: 20px;">
            </div>
        </div>
    </div>

    <div class="form-row">
        <div class="form-group full-width" style="margin-top: 20px;">
            <button type="submit" class="btn btn-primary btn-submit"><i class="fas fa-save"></i> Ürünü Kaydet</button>
        </div>
    </div>
    </form>
<?php
elseif ($viewAction == 'edit'): 
// ----------------------------------------------------------------
// GÖRÜNÜM 2: ÜRÜN DÜZENLEME (action=edit)
// ----------------------------------------------------------------
 $productId = $_GET['product_id'] ?? null;

 if (!$productId || !is_numeric($productId)) {
  echo "<div class='alert error'>Geçersiz ürün ID'si.</div>";
  exit;
 }

 // getProductById fonksiyonunun tanımlı olduğunu varsayıyoruz
 $productData = getProductById($pdo, $productId); 

 if (!$productData) {
  echo "<div class='alert error'>Ürün bulunamadı veya silinmiş.</div>";
  exit;
 }

 $variantsJson = json_encode($productData['variants'] ?? [], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);
 $imagesJson = json_encode($productData['images'] ?? [], JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE);

 // Renk Adı -> Hex Kodu Eşleştirmesi (Varyantlardan)
 $colorToHexMap = [];
 $currentColors = [];
 foreach ($productData['variants'] ?? [] as $variant) {
  if (!empty($variant['color'])) {
   $colorName = $variant['color'];
   $colorKey = trim(mb_strtolower($variant['color'])); // Anahtar olarak küçük harf kullan

   if (!isset($colorToHexMap[$colorKey])) {
    $colorToHexMap[$colorKey] = $variant['colorHexCode'] ?? '#000000';
   }
   if (!isset($currentColors[$colorKey])) {
    $currentColors[$colorKey] = [
     'color' => htmlspecialchars($colorName),
     'colorHexCode' => htmlspecialchars($variant['colorHexCode'] ?? '#000000') 
    ];
   }
  }
 }
 $currentColorsJson = json_encode(array_values($currentColors), JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); 
 
 // Tüm ürün bedenlerini toplama (Genel beden inputu için)
 $currentSizes = array_unique(array_column($productData['variants'] ?? [], 'size'));
 $sizesString = htmlspecialchars(implode(', ', $currentSizes));


 // Görselleri Renk Slug'a göre grupla (STANDARTLAŞTIRILMIŞ)
 $groupedImages = [];
 foreach ($productData['images'] as $img) {
  $slug = $img['colorSlug'] ?? 'no-color'; 
  // Tüm slug'ları küçük harfe çevir ve boşlukları temizle (Eşleşmeyi Garanti Etmek İçin)
  $standardSlug = trim(mb_strtolower($slug)); 
  
  if (!isset($groupedImages[$standardSlug])) { 
   $groupedImages[$standardSlug] = [];
  }
  $groupedImages[$standardSlug][] = $img;
 }
 
?>

<script>
const PRODUCT_DATA = <?php echo json_encode($productData, JSON_NUMERIC_CHECK | JSON_UNESCAPED_UNICODE); ?>;
const PRODUCT_VARIANTS = <?php echo $variantsJson; ?>;
const PRODUCT_IMAGES = <?php echo $imagesJson; ?>; 
const CURRENT_COLORS = <?php echo $currentColorsJson; ?>;
</script>

<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
<?php endif; ?>

<form action="../../controller/adminUsers/productController.php" method="POST" enctype="multipart/form-data" class="product-form" id="productEditForm">
<input type="hidden" name="action" value="update_product">
<input type="hidden" name="product_id" value="<?php echo htmlspecialchars($productData['id']); ?>">

<input type="hidden" name="images_to_delete" id="images-to-delete-input" value=""> 
<div id="images-data-container"></div> <div class="form-row">
 <div class="form-column">
 <div class="form-group">
 <label for="name">Ürün Adı <span class="required">*</span></label>
 <input type="text" name="name" id="name" required value="<?php echo htmlspecialchars($productData['name']); ?>">
 </div>
 <div class="form-group">
 <label for="description">Açıklama <span class="required">*</span></label>
 <textarea name="description" id="description" rows="5" required><?php echo htmlspecialchars($productData['description']); ?></textarea> 
 </div>
 <div class="form-group">
 <label for="price">Normal Fiyat (₺) <span class="required">*</span></label>
 <input type="number" name="price" id="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($productData['price']); ?>">
 </div>
 <div class="form-group">
 <label for="new_price">İndirimli Fiyat (₺) (İsteğe Bağlı)</label>
 <input type="number" name="new_price" id="new_price" step="0.01" min="0" value="<?php echo htmlspecialchars($productData['newPrice'] ?? ''); ?>"> 
 </div>
 </div>

 <div class="form-column">
 <div class="form-group">
 <label for="mainSku">Ana SKU</label>
 <input type="text" name="mainSku" id="mainSku" placeholder="Örn: MSKU-1234" value="<?php echo htmlspecialchars($productData['mainSku']); ?>">
 </div>
 <div class="form-group">
 <label for="shipping_fee">Varsayılan Kargo Ücreti (₺)</label>
 <input type="number" name="shipping_fee" id="shipping_fee" step="0.01" min="0" value="<?php echo htmlspecialchars($productData['shippingFee']); ?>">
 </div>
 <div class="form-group">
 <label for="category_id">Kategori <span class="required">*</span></label>
 <select name="category_id" id="category_id" required>
 <option value="">Kategori Seçin</option>
 <?php foreach ($categories ?? [] as $cat): ?>
 <option value="<?php echo htmlspecialchars($cat['id']); ?>" 
 <?php echo $cat['id'] == $productData['categoryId'] ? 'selected' : ''; ?>>
 <?php echo htmlspecialchars($cat['name']); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="form-group conditional-field" id="subcategory-group">
 <label for="sub_category_id">Alt Kategori</label> 
 <select name="sub_category_id" id="sub_category_id">
 <option value="">Alt Kategori Seçin</option>
 <?php 
 $currentSubCats = $groupedSubCategories[$productData['categoryId']] ?? [];
 foreach ($currentSubCats as $subCat): 
 ?>
 <option 
 value="<?php echo htmlspecialchars($subCat['id']); ?>"
 <?php if ($productData['subCategoryId'] == $subCat['id']) { echo 'selected'; } ?>
 >
 <?php echo htmlspecialchars($subCat['name']); ?>
 </option>
 <?php endforeach; ?>
 </select>
 </div>
  
  <div class="form-group conditional-field" id="gender-group">
  <label for="gender">Cinsiyet <span class="required">*</span></label> 
  <select name="gender" id="gender"> 
  <?php $currentGender = htmlspecialchars($productData['gender'] ?? ''); ?>
  <option value="" disabled selected hidden>
   <?php echo !empty($currentGender) ? $currentGender : 'Cinsiyet Seçin'; ?>
  </option>
  
  <?php foreach ($genders ?? [] as $g): ?>
  <option value="<?php echo htmlspecialchars($g); ?>" 
   <?php echo $g == $productData['gender'] ? 'selected' : ''; ?>>
   <?php echo htmlspecialchars($g); ?>
  </option>
  <?php endforeach; ?>
  </select>
 </div>
 
  <div class="form-group conditional-field" id="material-group">
  <label for="material">Materyal</label>
  <select name="material" id="material">
  <?php $currentMaterial = htmlspecialchars($productData['material'] ?? ''); ?>
  <option value="" disabled selected hidden>
   <?php echo !empty($currentMaterial) ? $currentMaterial : 'Materyal Seçin'; ?>
  </option>

  <?php foreach ($materials ?? [] as $m): ?>
  <option value="<?php echo htmlspecialchars($m); ?>" 
   <?php echo $m == $productData['material'] ? 'selected' : ''; ?>>
   <?php echo htmlspecialchars($m); ?>
  </option>
  <?php endforeach; ?>
  </select>
 </div>
 </div>
</div> 

<div class="form-row" style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px;">
 <div class="form-column full-width">
 <h4>Mevcut Görselleri Renk Grubuna Göre Yönetin (Sıra, Silme, Renk Atama)</h4>
 
 <div id="current-images-list" class="image-sortable-list">
 
<?php
  foreach ($groupedImages as $colorSlug => $imagesInGroup): 
   
   $colorNameFromSlug = str_replace('-', ' ', $colorSlug); 
   $knownHexCode = $colorToHexMap[trim(mb_strtolower($colorNameFromSlug))] ?? '#000000'; 
   
   $currentSizesForColor = [];
   $totalColorStockCalculated = 0; 

   $currentColorSlugLower = trim(mb_strtolower($colorSlug)); 

   foreach ($productData['variants'] ?? [] as $variant) {
    $variantColorSlugLower = trim(mb_strtolower($variant['colorSlug'] ?? ''));

    if ($variantColorSlugLower === $currentColorSlugLower) {
     $stock = (int)($variant['stockQuantity'] ?? 0);
     $totalColorStockCalculated += $stock; 
     
     if (!empty($variant['size']) && $stock > 0) {
      $currentSizesForColor[] = $variant['size'];
     }
    }
   }

   $totalColorStock = $totalColorStockCalculated;
   $sizesForColorString = htmlspecialchars(implode(', ', array_unique($currentSizesForColor)));
   ?>
   
<div class="color-group-container card mb-4" data-color-slug="<?= htmlspecialchars($colorSlug) ?>">

 <div class="card-body color-group-controls" style="padding: 15px;">
  <div style="display: flex; gap: 20px; align-items: flex-start;">
   
   <div class="input-control-item" style="flex-basis: 25%;">
    <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Renk Adı:</label>
    <input type="text" class="group-color-name-input form-control form-control-sm" 
     value="<?= htmlspecialchars($colorNameFromSlug) ?>" 
     placeholder="Örn: Kırmızı">
   </div>
   
   <div class="input-control-item" style="flex-basis: 15%; display: flex; flex-direction: column;">
    <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Hex Kodu:</label>
    <input type="color" class="group-color-hex-input form-control form-control-sm form-control-color" 
     value="<?= htmlspecialchars($knownHexCode) ?>" 
     title="Renk Seçici" style="height: 35px;">
   </div>
   
   <div class="input-control-item" style="flex-basis: 30%;">
    <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Toplu Stok:</label>
    <div style="display: flex; gap: 5px;">
     <input type="number" min="0" class="group-new-stock-input form-control form-control-sm"
      placeholder="Yeni stok girin" 
      value="<?= $totalColorStock ?? 0 ?>"> 
    </div>
   </div>

   <div class="input-control-item" style="flex-basis: 30%;">
    <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Bedenler:</label>
    <input type="text" 
     class="group-sizes-input form-control form-control-sm" 
     placeholder="Bedenleri virgülle ayırın (S, M, L)"
     value="<?= $sizesForColorString ?>">
    <small class="text-danger mt-1" style="font-size: 0.7em; display: block;">Dikkat: Bu, stok matrisindeki aynı renkteki tüm bedenlerin stoğunu günceller.</small>
   </div>
  </div>
 </div>

 <div class="images-in-group-list image-sortable-list card-footer" style="display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; border-top: 1px solid #ddd; background-color: #f7f7f7;">
  <?php foreach ($imagesInGroup as $img): ?>
   <div class="image-item shadow-sm" data-id="<?= $img['id'] ?>" style="width: 100px; height: 100px; overflow: hidden; position: relative; border: 1px solid #ccc; border-radius: 4px; background-color: #fff;">
    <img src="<?= htmlspecialchars($img['imageUrl']) ?>" style="width: 100%; height: 100%; object-fit: cover; display: block;">
    
        <span class="delete-image-btn old-image" data-id="<?= $img['id'] ?>" title="Görseli Sil"
     style="position: absolute; top: -8px; right: -8px; cursor: pointer; color: white; background: #dc3545; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);">
     <i class="fas fa-times" style="font-size: 0.8em;"></i>
    </span>
    
    <input type="hidden" class="image-id-input" value="<?= $img['id'] ?>">
    <input type="hidden" class="image-sortOrder-input" value="<?= $img['sortOrder'] ?>">
   </div>
  <?php endforeach; ?>
 </div>
</div>
  <?php endforeach; ?>
  
 <?php if (empty($productData['images'])): ?>
 <p class="text-muted" id="no-image-message">Bu ürüne ait kayıtlı görsel bulunmamaktadır.</p>
 <?php endif; ?>
 </div>

 <label for="new_product_images" style="margin-top: 15px; display: block;">Yeni Görsel Ekle (Çoklu Seçim)</label>
 <input type="file" name="new_product_images[]" id="new_product_images" accept="image/*" multiple> 
 <small class="form-text text-muted">Yeni seçilen görseller, otomatik olarak yeni bir renk grubu oluşturacak veya mevcut renk grubuna dahil edilecektir.</small>
 </div>
</div>
 
 <div class="form-row" style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 20px;">
 <div class="form-column full-width">
 
 </div>
 </div>


<div class="form-row">
 <div class="form-group full-width" style="margin-top: 20px;">
 <button type="submit" class="btn btn-primary btn-submit"><i class="fas fa-save"></i> Ürünü Güncelle</button>
 </div>
</div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const newImagesInput = document.getElementById('new_product_images');
    const currentImagesList = document.getElementById('current-images-list');
    const productEditForm = document.getElementById('productEditForm');
    const imagesToDeleteInput = document.getElementById('images-to-delete-input');
    const imageDataContainer = document.getElementById('images-data-container');
    
    let imagesToDelete = []; // Silinecek eski görsel ID'lerini tutar

    // Renk grubunun HTML yapısını oluşturan fonksiyon
    function createColorGroupContainer(colorSlug, colorName, hexCode, imageUrl, imageId, isNew = false) {
        
        // 1. Slug'ı sanitize et (küçük harf, tire ile değiştirme)
        const sanitizedSlug = colorSlug.toLowerCase().trim().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');

        // 2. Aynı sluga ait mevcut bir grup var mı kontrol et
        let groupContainer = document.querySelector(`.color-group-container[data-color-slug="${sanitizedSlug}"]`);
        
        if (!groupContainer) {
            // Eğer yoksa, yeni bir grup container'ı oluştur
            groupContainer = document.createElement('div');
            groupContainer.className = 'color-group-container card mb-4';
            groupContainer.setAttribute('data-color-slug', sanitizedSlug);
            
            groupContainer.innerHTML = `
                <div class="card-body color-group-controls" style="padding: 15px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        
                        <div class="input-control-item" style="flex-basis: 25%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Renk Adı:</label>
                            <input type="text" class="group-color-name-input form-control form-control-sm" 
                                value="${colorName}" 
                                placeholder="Örn: Kırmızı">
                        </div>
                        
                        <div class="input-control-item" style="flex-basis: 15%; display: flex; flex-direction: column;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Hex Kodu:</label>
                            <input type="color" class="group-color-hex-input form-control form-control-sm form-control-color" 
                                value="${hexCode}" 
                                title="Renk Seçici" style="height: 35px;">
                        </div>
                        
                        <div class="input-control-item" style="flex-basis: 30%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Toplu Stok:</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="number" min="0" class="group-new-stock-input form-control form-control-sm"
                                    placeholder="Yeni stok girin" value="${isNew ? 1 : 0}"> 
                            </div>
                        </div>

                        <div class="input-control-item" style="flex-basis: 30%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Bedenler:</label>
                            <input type="text" 
                                class="group-sizes-input form-control form-control-sm" 
                                placeholder="Bedenleri virgülle ayırın (S, M, L)"
                                value="">
                            <small class="text-danger mt-1" style="font-size: 0.7em; display: block;">Dikkat: Bu, stok matrisindeki aynı renkteki tüm bedenlerin stoğunu günceller.</small>
                        </div>
                    </div>
                </div>
                <div class="images-in-group-list image-sortable-list card-footer" style="display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; border-top: 1px solid #ddd; background-color: #f7f7f7;">
                </div>
            `;
            // Yeni grubu mevcut listeye ekle
            currentImagesList.appendChild(groupContainer);
            
            const noImageMessage = document.getElementById('no-image-message');
            if (noImageMessage) { noImageMessage.style.display = 'none'; }
        }

        // 3. Görsel öğesini oluştur
        const imageItem = document.createElement('div');
        imageItem.className = 'image-item shadow-sm';
        if (isNew) { imageItem.classList.add('new-image-item'); } // Yeni görseller için class
        imageItem.setAttribute('data-id', imageId); 
        imageItem.style.cssText = 'width: 100px; height: 100px; overflow: hidden; position: relative; border: 1px solid #ccc; border-radius: 4px; background-color: #fff;';

        const deleteButtonClass = isNew ? 'delete-new-image-btn' : 'delete-image-btn old-image';
        
        imageItem.innerHTML = `
            <img src="${imageUrl}" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            
            <span class="${deleteButtonClass}" data-id="${imageId}" title="Görseli Sil"
                style="position: absolute; top: -8px; right: -8px; cursor: pointer; color: white; background: #dc3545; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);">
                <i class="fas fa-times" style="font-size: 0.8em;"></i>
            </span>
            
            <input type="hidden" class="image-id-input" value="${imageId}">
            <input type="hidden" class="image-sortOrder-input" value="0">
            ${isNew ? `<input type="hidden" class="image-is-new-input" name="new_image_data[${imageId}][is_new]" value="1">
                       <input type="hidden" class="image-color-slug-input" name="new_image_data[${imageId}][color_slug]" value="${sanitizedSlug}">` 
                  : ''}
        `;

        // Görseli gruba ekle
        const imageGroupList = groupContainer.querySelector('.images-in-group-list');
        imageGroupList.appendChild(imageItem);
        
        // 4. Dinleyicileri Kur
        setupGroupListeners(groupContainer);
        setupDeleteListener(imageItem);
    }
    
    // Grup inputlarındaki değişiklikleri izleyen fonksiyon
    function setupGroupListeners(groupContainer) {
        const colorNameInput = groupContainer.querySelector('.group-color-name-input');
        
        // Renk Adı değiştiğinde data-color-slug ve görsel hidden inputlarını güncelle
        colorNameInput.addEventListener('input', function() {
            // Slug'ı sanitize et: küçük harf, boşlukları tire yap, özel karakterleri kaldır
            const newSlug = this.value.toLowerCase().trim().replace(/[^a-z0-9]/g, '-').replace(/-+/g, '-');
            groupContainer.setAttribute('data-color-slug', newSlug);
            
            // Bu gruptaki TÜM yeni görsellerin hidden color_slug inputunu güncelle
            groupContainer.querySelectorAll('.image-is-new-input').forEach(input => {
                const newSlugInput = input.parentElement.querySelector('.image-color-slug-input');
                if (newSlugInput) {
                    newSlugInput.value = newSlug;
                }
            });
        });
    }
    
    // Silme butonlarını dinleyen fonksiyon
    function setupDeleteListener(imageItem) {
        const deleteBtn = imageItem.querySelector('.delete-image-btn, .delete-new-image-btn');
        if (!deleteBtn) return;
        
        deleteBtn.addEventListener('click', function() {
            const imageId = this.getAttribute('data-id');
            const isOldImage = this.classList.contains('old-image');
            const groupContainer = imageItem.closest('.color-group-container');
            const imageGroupList = groupContainer.querySelector('.images-in-group-list');
            
            if (isOldImage) {
                // ESKİ görsel: ID'sini silinecekler listesine ekle
                imagesToDelete.push(imageId);
                imagesToDeleteInput.value = imagesToDelete.join(',');
            }
            // YENİ görsel: Sadece DOM'dan kaldırılır. name="new_image_data[...]" alanları silinir ve controller'a gitmez.
            
            // Görseli DOM'dan kaldır
            imageItem.remove();
            
            // Eğer grup içinde başka görsel kalmadıysa, grubu da kaldır
            if (imageGroupList.children.length === 0) {
                groupContainer.remove();
                
                // Eğer hiç görsel kalmadıysa 'görsel yok' mesajını göster
                if (currentImagesList.children.length === 0) {
                    const noImageMessage = document.getElementById('no-image-message');
                    if (noImageMessage) { noImageMessage.style.display = 'block'; }
                }
            }
        });
    }
    
    // Tüm mevcut eski görsel silme butonlarına listener ekle
    document.querySelectorAll('.delete-image-btn.old-image').forEach(imageItem => {
        setupDeleteListener(imageItem.closest('.image-item'));
    });
    
    // Yeni dosyalar yüklendiğinde
    newImagesInput.addEventListener('change', function(event) {
        const files = event.target.files;
        
        const defaultColorName = 'Yeni Renk';
        const defaultHexCode = '#cccccc';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            // YENİ görseller için eşsiz bir ID
            const tempImageId = `new-img-${Date.now()}-${i}`; 

            const reader = new FileReader();
            reader.onload = function(e) {
                // Yeni görsel eklendiğinde grup oluşturma/ekleme fonksiyonunu çağır
                createColorGroupContainer(defaultColorName, defaultColorName, defaultHexCode, e.target.result, tempImageId, true);
            };
            reader.readAsDataURL(file);
        }
        this.value = ''; // Aynı isimli dosyayı tekrar seçme sorununu çözer
    });

    // Form gönderildiğinde: Tüm verileri topla ve hidden input'lara yerleştir
    productEditForm.addEventListener('submit', function(e) {
        
        // 1. Görsel Sıralama/Slug Verilerini Topla (Eski + Yeni)
        let currentSortOrder = 1;

        // Kontrol amaçlı hidden inputları temizle
        imageDataContainer.innerHTML = '';
        
        document.querySelectorAll('.color-group-container').forEach(group => {
            const groupSlug = group.getAttribute('data-color-slug');
            
            group.querySelectorAll('.image-item').forEach(imageItem => {
                const imageId = imageItem.getAttribute('data-id');
                const isNew = imageItem.classList.contains('new-image-item');

                if (!isNew) {
                    // ESKİ Görsel: ID ve sıralamayı hidden input'a ekle
                    const inputPrefix = `images_data[${imageId}]`;
                    
                    const idInput = document.createElement('input');
                    idInput.type = 'hidden';
                    idInput.name = `${inputPrefix}[id]`;
                    idInput.value = imageId;
                    
                    const sortInput = document.createElement('input');
                    sortInput.type = 'hidden';
                    sortInput.name = `${inputPrefix}[sortOrder]`;
                    sortInput.value = currentSortOrder;
                    
                    const slugInput = document.createElement('input');
                    slugInput.type = 'hidden';
                    slugInput.name = `${inputPrefix}[colorSlug]`;
                    slugInput.value = groupSlug;
                    
                    imageDataContainer.appendChild(idInput);
                    imageDataContainer.appendChild(sortInput);
                    imageDataContainer.appendChild(slugInput);

                } else {
                    // YENİ Görsel: Sadece sıralama ve güncel slug bilgisini güncelle
                    // (Zaten name="new_image_data[...]" ile oluşmuşlardı)
                    const sortInput = imageItem.querySelector('.image-sortOrder-input');
                    const slugInput = imageItem.querySelector('.image-color-slug-input');

                    if (sortInput) sortInput.value = currentSortOrder;
                    if (slugInput) slugInput.value = groupSlug;
                }
                currentSortOrder++;
            });
        });
        
        // 2. Tüm Renk Varyant Grubu Verilerini Topla
        let allVariantGroupsData = {};

        document.querySelectorAll('.color-group-container').forEach(group => {
            const colorSlug = group.getAttribute('data-color-slug');
            const colorName = group.querySelector('.group-color-name-input').value;
            const colorHexCode = group.querySelector('.group-color-hex-input').value;
            const stock = group.querySelector('.group-new-stock-input').value;
            const sizesString = group.querySelector('.group-sizes-input').value;
            
            const sizesArray = sizesString.split(',').map(s => s.trim()).filter(s => s.length > 0);
            
            allVariantGroupsData[colorSlug] = {
                color: colorName,
                colorSlug: colorSlug,
                colorHexCode: colorHexCode,
                stock: stock,
                sizes: sizesArray
            };
        });
        
        // Toplanan varyant verisini hidden input'a ekle (Controller'a JSON olarak gider)
        let hiddenVariantInput = document.createElement('input');
        hiddenVariantInput.type = 'hidden';
        hiddenVariantInput.name = 'all_variant_groups_data';
        hiddenVariantInput.value = JSON.stringify(allVariantGroupsData);
        productEditForm.appendChild(hiddenVariantInput);
        
        // Son Kontrol: images_to_delete inputunu güncelle (Zaten güncel olması gerekiyor, bu sadece güvence)
        imagesToDeleteInput.value = imagesToDelete.join(',');
    });
});
</script>
<?php
else:
// ----------------------------------------------------------------
// GÖRÜNÜM 3: ÜRÜN LİSTESİ (action=list veya default)
// ----------------------------------------------------------------
    $products = getProducts($pdo);
?>

<div class="content-header">
    <h2>Ürün Listesi</h2>
    <a href="?page=products&action=add" class="btn btn-primary"><i class="fas fa-plus"></i> Yeni Ürün Ekle</a>
</div>
    
<?php if (isset($_SESSION['success_message'])): ?>
<div class="alert success"><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error_message'])): ?>
<div class="alert error"><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></div>
<?php endif; ?>


<div class="product-list-table">
    <table class="data-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Fotoğraf</th>
                <th>Adı (SKU)</th>
                <th>Kategori</th>
                <th>Stoklu Varyantlar</th> 
                <th class="text-center">Toplam Stok</th>
                <th>Fiyat (₺)</th>
                <th>İşlemler</th> 
            </tr>
        </thead>
        <tbody>
            <?php if (empty($products)): ?>
            <tr>
                <td colspan="8" class="text-center">Henüz kayıtlı ürün bulunmamaktadır.</td> 
            </tr>
            <?php else: ?>
                <?php foreach ($products as $product): ?>
                <tr>
                    <td data-label="ID"><?php echo htmlspecialchars($product['id']); ?></td>

                    <td data-label="Fotoğraf" class="product-image-cell">
                        <?php 
                            // SQL'den gelen ilk görsel URL'sini kullan
                            $imgUrl = $product['first_image_url'] ?? null; 
                            // **DOĞRU DETAY SAYFASI URL'Sİ TANIMLANIYOR**
                            $productDetailUrl = "admin.php?page=productDetails&product_id=" . $product['id']; 
                        ?>
                        
                        <?php if ($imgUrl): ?>
                            <a href="<?php echo htmlspecialchars($productDetailUrl); ?>">
                                <img 
                                    src="<?php echo htmlspecialchars($imgUrl); ?>" 
                                    alt="<?php echo htmlspecialchars($product['name'] ?? 'Ürün Görseli'); ?>" 
                                    style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;"
                                >
                            </a>
                        <?php else: ?>
                            <a href="<?php echo htmlspecialchars($productDetailUrl); ?>">
                                <span style="font-size: 0.8em; color: #999;">Görsel Yok</span>
                            </a>
                        <?php endif; ?>
                        
                    </td>
                    
                    <td data-label="Adı">
                        <strong><?php echo htmlspecialchars($product['name']); ?></strong><br>
                        <small class="text-muted">SKU: <?php echo htmlspecialchars($product['mainSku']); ?></small>
                    </td>
                    <td data-label="Kategori">
                        <?php echo htmlspecialchars($product['category_name'] ?? 'Yok'); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($product['subcategory_name'] ?? '-'); ?></small>
                    </td>

                    <td data-label="Varyantlar">
                        <?php 
                            $variantsRaw = $product['stocked_variants_raw'] ?? '';
                            $variantGroups = [];
                            
                            if (!empty($variantsRaw)) {
                                $pairs = array_filter(explode(',', $variantsRaw));
                                foreach ($pairs as $pair) {
                                    if (strpos($pair, '|') !== false) {
                                        list($color, $size) = explode('|', $pair, 2);
                                        $color = trim($color);
                                        $size = trim($size);
                                        
                                        if (!empty($color) && !empty($size)) {
                                            // Her rengin sadece ilk 3 bedenini göster
                                            if (count($variantGroups[$color] ?? []) < 3) {
                                                $variantGroups[$color][] = $size;
                                            }
                                        }
                                    }
                                }
                            }

                            if (!empty($variantGroups)): 
                                $counter = 0;
                                foreach ($variantGroups as $color => $sizes):
                                    if ($counter >= 2) { echo "..."; break; } // Sadece ilk 2 rengi göster
                            ?>
                            <div>
                                <strong><?php echo htmlspecialchars($color); ?>:</strong> 
                                <small><?php echo htmlspecialchars(implode(', ', array_unique($sizes))); ?></small>
                            </div>
                            <?php 
                                    $counter++;
                                endforeach;
                            else:
                                echo "<small class='text-muted'>Stoklu varyant yok</small>";
                            endif;
                            ?>
                    </td>
                    
<td data-label="Toplam Stok" class="text-center">
    <span class="badge <?php echo ((int)$product['total_stock'] > 0) ? 'badge-success' : 'badge-danger'; ?>">
        **<?php echo htmlspecialchars($product['total_stock']); ?>** Adet
    </span>
</td>
                    
                    <td data-label="Fiyat">
                        <?php
                        $price = number_format((float)$product['price'], 2, ',', '.') . ' ₺';
                        $newPrice = $product['newPrice'] ? number_format((float)$product['newPrice'], 2, ',', '.') . ' ₺' : null;

                        if ($product['newPrice'] && $product['newPrice'] < $product['price']) {
                            echo "<span class='old-price' style='text-decoration: line-through; color: #999;'>{$price}</span><br>";
                            echo "<span class='new-price' style='font-weight: bold; color: #d9534f;'>{$newPrice}</span>";
                        } else {
                            echo $price;
                        }
                        ?>
                    </td>
                    
                    <td data-label="İşlemler" class="action-buttons">
                        <a href="admin.php?page=products&action=edit&product_id=<?php echo $product['id']; ?>" 
                        class="btn btn-icon btn-edit" 
                        title="Düzenle">
                        <i class="fas fa-pencil-alt"></i>
                        </a>

                        <button class="btn btn-icon btn-delete delete-product-btn" 
                        data-product-id="<?php echo htmlspecialchars($product['id']); ?>" 
                        title="Sil">
                        <i class="fas fa-trash-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
    
<script>
    document.querySelectorAll('.delete-product-btn').forEach(button => {
        button.addEventListener('click', function() {
            const productId = this.dataset.productId;
            if (confirm(`ID'si ${productId} olan ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.`)) {
                window.location.href = `../../controller/adminUsers/productController.php?action=delete_product&product_id=${productId}`;
            }
        });
    });
</script>

<?php
endif;
?>
<script src="../../js/adminUsers/product.js"></script>