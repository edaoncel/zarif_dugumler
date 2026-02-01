<?php
global $pdo;

/**
 * Ürün ID'sine göre temel bilgileri, varyantları VE GÖRSELLERİ çeker.
 * @param PDO $pdo PDO bağlantı nesnesi
 * @param int $productId Ürün ID'si
 * @return array|null Ürün verileri veya null
 */
function getProductById($pdo, $productId) {
    // Ürün temel bilgileri ve kategori/alt kategori adlarını çekme
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

    // Ürün varyantlarını çekme (Aynı kalır)
    $sqlVariants = "
        SELECT color, size, stockQuantity, sku
        FROM productVariants
        WHERE productId = :id
        ORDER BY color, size
    ";
    $stmtVariants = $pdo->prepare($sqlVariants);
    $stmtVariants->execute([':id' => $productId]);
    $variants = $stmtVariants->fetchAll(PDO::FETCH_ASSOC);

    $product['variants'] = $variants;

    // === YENİ EKLEME: Ürün görsellerini çekme ===
    $sqlImages = "
        SELECT id, imageUrl 
        FROM productImages
        WHERE productId = :id
        ORDER BY sortOrder ASC, id ASC 
    ";
    $stmtImages = $pdo->prepare($sqlImages);
    $stmtImages->execute([':id' => $productId]);
    $images = $stmtImages->fetchAll(PDO::FETCH_ASSOC);

    // Eğer productImages tablosunda görsel yoksa, product tablosundaki ana görseli kullan (geriye dönük uyumluluk)
    // NOT: product tablosundaki imageUrl sütunu artık kullanılmamalıdır.
    if (empty($images) && !empty($product['imageUrl'])) {
        $images[] = ['id' => 0, 'imageUrl' => $product['imageUrl']];
    }
    
    $product['images'] = $images;

    return $product;
}

// ----------------------------------------------------------------
// SAYFA BAŞLANGICI
// ----------------------------------------------------------------

// ... (ProductId ve $productData kontrol kısmı aynı kalır) ...

$productId = $_GET['product_id'] ?? null;

if (!$productId || !is_numeric($productId)) {
    die("<div style='padding: 20px; font-family: Arial; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb;'>Hata: Geçersiz veya eksik ürün ID'si.</div>");
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    die("<div style='padding: 20px; font-family: Arial; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb;'>Hata: Veritabanı bağlantısı (global \$pdo) tanımlı değil.</div>");
} else {
    $productData = getProductById($pdo, $productId); 
}


if (!$productData) {
    die("<div style='padding: 20px; font-family: Arial; background-color:#f8d7da; color:#721c24; border:1px solid #f5c6cb;'>Hata: Ürün bulunamadı veya silinmiş.</div>");
}

// === DEĞİŞİKLİK: Tekil $imgSrc yerine ilk görselin yolunu alıyoruz ===
$mainImageSrc = '';
if (!empty($productData['images'])) {
    $mainImageSrc = htmlspecialchars($productData['images'][0]['imageUrl']);
} else {
    // Görsel yoksa varsayılan görsel
    $mainImageSrc = '/sinem/img/default-product.jpg'; 
}

// Görsel yolu düzeltmesi (gerekirse)
if (strpos($mainImageSrc, '/sinem') !== 0 && $mainImageSrc != '/sinem/img/default-product.jpg') {
    $mainImageSrc = '/sinem' . $mainImageSrc; 
}

// Toplam stok hesaplaması (Aynı kalır)
$totalStock = array_sum(array_column($productData['variants'], 'stockQuantity'));

?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ürün Detayları: <?php echo htmlspecialchars($productData['name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="../../css/adminUsers/product.css" rel="stylesheet"> 
    <style>
        /* Slayt ve Küçük Görsel Önizleme için Temel CSS */
        .image-gallery-container {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .product-main-image {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
        }
        .thumbnail-wrapper {
            display: flex;
            overflow-x: auto;
            gap: 10px;
            padding: 5px 0;
        }
        .thumbnail-image {
            width: 70px; /* Küçük resim boyutu */
            height: 70px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        .thumbnail-image.active {
            border-color: var(--primary-color, #007bff);
        }
    </style>
</head>
<body>
    
    <div class="page-header-wrapper">
        <div class="product-title-card">
            <i class="fas fa-tshirt" style="color: var(--primary-color); margin-right: 10px;"></i>
            <h2><?php echo htmlspecialchars($productData['name']); ?></h2>
            <p>Ürün ID: <?php echo htmlspecialchars($productData['id']); ?> | Ana SKU: <?php echo htmlspecialchars($productData['mainSku'] ?? '-'); ?></p>
            
            <div class="btn-primary-wrapper">
                <a href="admin.php?page=products&action=edit&product_id=<?php echo $productData['id']; ?>" class="btn btn-primary"><i class="fas fa-pencil-alt"></i> Düzenle</a>
            </div>
        </div>
    </div>
    
    <div class="main-content-grid">
        
        <div class="info-col">
            <div class="tab-card">
                <div class="tab-nav">
                    <button class="tab-button active" data-tab="basic"><i class="fas fa-info-circle"></i> Temel Bilgiler</button>
                    <button class="tab-button" data-tab="pricing"><i class="fas fa-tags"></i> Fiyatlar</button>
                    <button class="tab-button" data-tab="description"><i class="fas fa-align-left"></i> Açıklama</button>
                    <button class="tab-button" data-tab="variants"><i class="fas fa-boxes"></i> Stok</button>
                </div>
                
                <div class="tab-content">
                    <div id="basic" class="tab-pane active">
                        <div class="info-item"><strong>Kategori:</strong> <span><?php echo htmlspecialchars($productData['category_name'] ?? '-'); ?></span></div>
                        <div class="info-item"><strong>Alt Kategori:</strong> <span><?php echo htmlspecialchars($productData['subcategory_name'] ?? '-'); ?></span></div>
                        
                        <?php if (!empty($productData['gender'])): ?>
                            <div class="info-item"><strong>Cinsiyet:</strong> <span><?php echo htmlspecialchars($productData['gender']); ?></span></div>
                        <?php endif; ?>
                        
                        <div class="info-item"><strong>Materyal:</strong> <span><?php echo htmlspecialchars($productData['material'] ?? '-'); ?></span></div>
                    </div>
                    
                    <div id="pricing" class="tab-pane">
                        <div class="info-item">
                            <strong>Kargo Ücreti:</strong> 
                            <span><?php echo number_format($productData['shippingFee'], 2, ',', '.') . ' ₺'; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>Normal Fiyat:</strong>
                            <span class="old-price"><?php echo number_format($productData['price'], 2, ',', '.') . ' ₺'; ?></span>
                        </div>
                        <div class="info-item">
                            <strong>İndirimli Fiyat:</strong> 
                            <?php if ($productData['newPrice'] && $productData['newPrice'] < $productData['price']): ?>
                                <span class="new-price"><?php echo number_format($productData['newPrice'], 2, ',', '.') . ' ₺'; ?></span>
                            <?php else: ?>
                                <span>-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div id="description" class="tab-pane">
                        <h4 style="color: var(--text-main); border-bottom: 1px solid var(--border-color); padding-bottom: 10px; margin-top: 0; font-weight: 600;">Ürün Detaylı Açıklaması</h4>
                        <p style="white-space: pre-wrap; line-height: 1.7; color: var(--text-muted);">
                            <?php echo htmlspecialchars($productData['description']); ?>
                        </p>
                    </div>
                    
                    <div id="variants" class="tab-pane">
                        <?php if (empty($productData['variants'])): ?>
                            <p style="color: var(--danger-color); font-weight: 600;">Bu ürün için tanımlanmış varyant bulunmamaktadır.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="variant-table">
                                    <thead>
                                        <tr>
                                            <th>Renk</th>
                                            <th>Beden/Numara</th>
                                            <th>SKU</th>
                                            <th style="text-align: center;">Stok Adedi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($productData['variants'] as $variant): 
                                            $stockBadgeClass = ($variant['stockQuantity'] > 0) ? 'badge-success' : 'badge-danger';
                                            $stockText = htmlspecialchars($variant['stockQuantity']);
                                            $colorDisplay = htmlspecialchars($variant['color'] ?? '-');
                                            $sizeDisplay = htmlspecialchars($variant['size'] ?? '-');
                                            $skuDisplay = htmlspecialchars($variant['sku'] ?? '-');
                                        ?>
                                            <tr>
                                                <td><?php echo $colorDisplay; ?></td>
                                                <td><?php echo $sizeDisplay; ?></td>
                                                <td><?php echo $skuDisplay; ?></td>
                                                <td style="text-align: center;">
                                                    <span class="badge <?php echo $stockBadgeClass; ?>">
                                                        <?php echo $stockText; ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="image-col">
            <div class="image-card">
                
                <div class="image-gallery-container">
                    <img id="mainImage" src="<?php echo $mainImageSrc; ?>" 
                         alt="<?php echo htmlspecialchars($productData['name']); ?>" 
                         class="product-main-image">

                    <?php if (count($productData['images']) > 1): ?>
                    <div class="thumbnail-wrapper">
                        <?php foreach ($productData['images'] as $index => $image): 
                            $imagePath = htmlspecialchars($image['imageUrl']);
                            // Görsel yolu düzeltmesi (gerekirse)
                            if (strpos($imagePath, '/sinem') !== 0) {
                                $imagePath = '/sinem' . $imagePath; 
                            }
                        ?>
                            <img src="<?php echo $imagePath; ?>" 
                                 alt="Thumbnail <?php echo $index + 1; ?>" 
                                 class="thumbnail-image <?php echo ($index === 0) ? 'active' : ''; ?>"
                                 data-full-src="<?php echo $imagePath; ?>">
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="total-stock-info">
                    <i class="fas fa-cubes"></i> 
                    <span class="badge <?php echo ($totalStock > 0) ? 'badge-success' : 'badge-danger'; ?>">
                        <?php echo $totalStock; ?> ADET
                    </span> 
                    (Toplam Stok)
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // Sekme geçişleri için JavaScript (Aynı kalır)
    document.addEventListener('DOMContentLoaded', function() {
        const tabButtons = document.querySelectorAll('.tab-button');
        const tabPanes = document.querySelectorAll('.tab-pane');

        function switchTab(targetTabId) {
            tabButtons.forEach(btn => btn.classList.remove('active'));
            tabPanes.forEach(pane => pane.classList.remove('active'));
            
            const targetPane = document.getElementById(targetTabId);
            if (targetPane) {
                targetPane.classList.add('active');
            }
        }
        
        tabButtons.forEach(button => {
            button.addEventListener('click', function() {
                const targetTabId = this.getAttribute('data-tab');
                switchTab(targetTabId);
                this.classList.add('active');
            });
        });
        
        const defaultActiveButton = document.querySelector('.tab-button.active');
        if (defaultActiveButton) {
            const targetTabId = defaultActiveButton.getAttribute('data-tab');
            switchTab(targetTabId);
        } else if (tabButtons.length > 0) {
            tabButtons[0].click();
        }


        // === YENİ EKLEME: Görsel Galeri İşlevi ===
        const mainImage = document.getElementById('mainImage');
        const thumbnails = document.querySelectorAll('.thumbnail-image');

        thumbnails.forEach(thumbnail => {
            thumbnail.addEventListener('click', function() {
                const newSrc = this.getAttribute('data-full-src');
                
                // 1. Ana görseli değiştir
                mainImage.src = newSrc;
                
                // 2. Aktif küçük resmi güncelle
                thumbnails.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });
    });
</script>

</body>
</html>