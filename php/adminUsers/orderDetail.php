<?php
// E:\xampp\htdocs\sinem\php\adminUsers\orderDetail.php
include_once "../../db/db.php"; 
include_once "../../controller/adminUsers/ordersController.php"; 

// Eğer ordersController.php düzgün dahil edilemezse veya fonksiyonlar yoksa diye kontrol
if (!function_exists('getOrderDetails')) {
    // Üretim ortamında bu blok olmamalı, hata döndürmeli.
    // Şimdilik sadece uyarı amaçlı bırakıyorum.
    error_log("Hata: getOrderDetails fonksiyonu bulunamadı. ordersController.php dahil edilmeli.");
    exit; 
}
if (!function_exists('getOrderAddress')) { 
    // Bu fonksiyon da ordersController.php içinde olmalı.
    error_log("Hata: getOrderAddress fonksiyonu bulunamadı.");
    exit; 
}
// =========================================================================

global $pdo;
$orderId = $_GET['id'] ?? null;

if (!$orderId || !is_numeric($orderId)) {
    echo "<div class='alert alert-danger p-4 shadow-sm mx-3 mt-3'>Geçersiz veya eksik Sipariş ID'si.</div>";
    exit;
}

// Sipariş ve Adres detaylarını controller'dan çek
$order = getOrderDetails($pdo, $orderId); 
$shippingAddress = getOrderAddress($pdo, $orderId); 
$orderItems = $order['items'] ?? []; 

if (!$order) {
    echo "<div style='padding: 20px; border: 1px solid red; margin: 15px;'>Sipariş ID: <strong>#". htmlspecialchars($orderId) ."</strong> sistemde bulunamadı.</div>";
    exit;
}

$orderStatusMap = [
    'Pending' => ['text' => 'Beklemede', 'css' => 'warning'], 
    'Processing' => ['text' => 'Hazırlanıyor', 'css' => 'info'],
    'Shipped' => ['text' => 'Kargolandı', 'css' => 'primary'],
    'Delivered' => ['text' => 'Teslim Edildi', 'css' => 'success'], 
    'Cancelled' => ['text' => 'İptal Edildi', 'css' => 'danger'], 
];

$currentStatusKey = $order['status'];
$currentStatus = $orderStatusMap[$currentStatusKey] ?? ['text' => $currentStatusKey, 'css' => 'secondary']; 
$currentStatusText = $currentStatus['text'];
$statusCssClass = $currentStatus['css']; 

// Kargo firması listesi
$shippingCompanies = ['Yurtiçi Kargo', 'MNG Kargo', 'Aras Kargo', 'PTT Kargo', 'Sürat Kargo'];
$selectedCompany = $order['shippingCompany'] ?? '';


// *************************************************************************
// KARGO VE TOPLAM HESAPLAMA MANTIĞI
// *************************************************************************

$totalShippingFee = 0.00; 
$productSubtotal = 0.00; 

foreach ($orderItems as $item) {
    // 'price' anahtarı getOrderDetails fonksiyonunda orderitems.price olarak ayarlandı
    $productSubtotal += ($item['price'] * $item['quantity']);
    
    // 'shippingFee' anahtarı getOrderDetails fonksiyonunda product.shippingFee olarak ayarlandı
    $itemShippingFee = $item['shippingFee'] ?? 0.00; 
    $totalShippingFee += $itemShippingFee;
}

$shippingFee = $totalShippingFee; 

// GENEL TOPLAM: Ürün Ara Toplamı + Toplam Kargo Ücreti
$generalTotal = $productSubtotal + $shippingFee;

// *************************************************************************

?>
<link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">

<link href="../../css/adminUsers/orderDetail.css" rel="stylesheet">
<div class="container-fluid">


<div class="row">
    
    <div class="col-lg-8 col-md-12 order-lg-1 order-1">
    
    <div class="card shadow status-card status-<?php echo $statusCssClass; ?> mb-4">
    <div class="card-body py-3 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 font-weight-bold text-dark">Sipariş Durumu</h5>
        </div>
        <div>
            <span class="status-badge bg-<?php echo $statusCssClass; ?> text-white font-weight-bold" id="currentStatusText">
            <?php echo $currentStatusText; ?>
            </span>
            <p class="text-muted small mb-0 text-right mt-1">Tarih: <strong><?php echo date('d.m.Y H:i', strtotime($order['orderDate'])); ?></strong></p>
        </div>
    </div>
    
    <div class="card-footer bg-white border-0 py-2 d-flex align-items-center justify-content-end">
    <label for="statusSelect" class="mr-2 mb-0 font-weight-bold small">Durum Güncelle:</label>
    <select id="statusSelect" class="form-control form-control-sm mr-2" style="max-width: 150px;">
        <?php foreach ($orderStatusMap as $key => $data): ?>
        <option value="<?php echo $key; ?>" 
            <?php echo ($currentStatusKey == $key) ? 'selected' : ''; ?>>
            <?php echo $data['text']; ?>
        </option>
        <?php endforeach; ?>
    </select>
    <button id="updateStatusBtn" class="btn btn-success btn-sm px-3" data-order-id="<?php echo htmlspecialchars($order['orderId']); ?>" disabled>
        <i class="fas fa-check"></i> Kaydet
    </button>
    </div>
    </div>

    <div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-box-open mr-2 text-primary"></i> Sipariş Edilen Ürünler (<?php echo count($orderItems); ?>)</h6>
        </div>
    <div class="card-body p-0">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
        <thead class="bg-light">
            <tr>
            <th scope="col" style="width: 150px;">GÖRSEL</th>
            <th scope="col">ÜRÜN ADI / BİLGİ</th>
            <th scope="col" style="width: 80px;">ADET</th>
            <th scope="col" style="width: 100px;" class="text-right">BİRİM FİYAT</th>
            <th scope="col" style="width: 120px;" class="text-right">TOPLAM</th>
            </tr>
        </thead>
        <tbody>
        <?php if (!empty($orderItems)): ?>
            <?php foreach ($orderItems as $item): ?>
            <tr>
            
            <td>
            <?php 
                // 1. Ürün ID'sini, adını ve URL'sini ordersController'dan gelen DOĞRU anahtarlarla ($item) al
                $productId = $item['productId'] ?? null;
                $productName = $item['productName'] ?? 'Ürün'; 
                // !!! RESİM URL'Sİ BURADA GÜNCELLENDİ !!!
                $productImageUrl = $item['imageUrl'] ?? '';     
                
                // Resim yolu '/sinem/img/product/' gibi bir yolla başlar.
                $imagePath = htmlspecialchars($productImageUrl); 
                
                // 3. Bağlantı URL'sini oluştur
                $detailUrl = 'admin.php?page=productDetails&product_id=' . htmlspecialchars($productId);
            ?>

            <a href="<?php echo $detailUrl; ?>" title="<?php echo htmlspecialchars($productName); ?> Detay">
                <img 
                    src="<?php echo $imagePath; ?>" 
                    alt="<?php echo htmlspecialchars($productName); ?> Resmi" 
                    class="item-thumb mr-3" 
                    style="width: 100%; height: auto;" 
                    onerror="this.onerror=null;this.src='../../img/placeholder.png';"
                >
            </a>
        </td>


            <td>
                <div class="font-weight-bold text-dark"><?php echo htmlspecialchars($item['productName'] ?? 'Ürün Adı Yok'); ?></div>
                <div class="small text-muted">Renk: <?php echo htmlspecialchars($item['color'] ?? 'N/A'); ?> | Beden: <?php echo htmlspecialchars($item['size'] ?? 'N/A'); ?></div>
            </td>
            <td><?php echo htmlspecialchars($item['quantity']); ?></td>
            <td class="text-right"><?php echo number_format($item['price'], 2, ',', '.') . ' ₺'; ?></td>
            <td class="text-right font-weight-bold text-danger"><?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.') . ' ₺'; ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="5" class="text-center text-muted">Sipariş edilen ürün bulunamadı.</td></tr>
        <?php endif; ?>
        </tbody>
        </table>
    </div>
    </div>
    
        <div class="card-footer bg-white border-top-0 pt-0 pb-3">
        <div class="row justify-content-end">
            <div class="col-md-6 col-12">
            <table class="table table-sm table-borderless text-right mb-0">
                <tbody>
                <tr>
                    <td class="font-weight-bold text-dark border-0 pt-1 pb-0" style="width: 60%;">Ürün Ara Toplamı (KDV Dahil)</td>
                    <td class="border-0 pt-1 pb-0"><?php echo number_format($productSubtotal, 2, ',', '.') . ' ₺'; ?></td>
                </tr>
                <tr>
                    <td class="font-weight-bold text-dark pt-1 pb-0">Kargo Ücreti</td>
                    <td class="pt-1 pb-0">
                    <?php if ($shippingFee > 0): ?>
                        <span class="text-info"><?php echo number_format($shippingFee, 2, ',', '.') . ' ₺'; ?></span>
                    <?php else: ?>
                        <span class="text-success font-weight-bold">Ücretsiz</span>
                    <?php endif; ?>
                    </td>
                </tr>
                <tr class="table-primary">
                    <td class="font-weight-bold text-dark pt-2 pb-2">GENEL TOPLAM</td>
                    <td class="font-weight-bold text-danger pt-2 pb-2">
                    <?php echo number_format($generalTotal, 2, ',', '.') . ' ₺'; ?>
                    </td>
                </tr>
                </tbody>
            </table>
            </div>
        </div>
        </div>
        </div>

    <div class="row">
    <div class="col-md-6 mb-4">
    <div class="card shadow h-100">
        <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-file-invoice mr-2 text-info"></i> Fatura Özel Açıklama/Not</h6>
        </div>
        <div class="card-body">
        <textarea class="form-control" rows="4" placeholder="Fatura ile ilgili notlar..." id="invoiceNoteTextarea"><?php echo htmlspecialchars($order['invoiceNote'] ?? ''); ?></textarea>
        </div>
    </div>
    </div>
    <div class="col-md-6 mb-4">
    <div class="card shadow h-100">
        <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-shipping-fast mr-2 text-info"></i> Kargo Fişi Özel Açıklama/Not</h6>
        </div>
        <div class="card-body">
        <textarea class="form-control" rows="4" placeholder="Kargo fişinde/etiketinde görünecek notlar..." id="shippingNoteTextarea"><?php echo htmlspecialchars($order['shippingNote'] ?? ''); ?></textarea>
        </div>
    </div>
    </div>
    </div>
    <div class="mb-4">
    <button class="btn btn-success px-4" id="saveDetailsBtn" data-order-id="<?php echo htmlspecialchars($order['orderId']); ?>"><i class="fas fa-check-circle mr-2"></i> AÇIKLAMA/NOT KAYDET</button>
    </div>
    </div>
    
    
    <div class="col-lg-4 col-md-12 order-lg-2 order-2">
    
    <div class="card shadow mb-4">
    <div class="card-header bg-primary py-3">
    <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-user card-title-icon mr-2"></i> Müşteri Bilgileri</h6>
    </div>
    <div class="card-body px-4 pt-3 pb-4">
    <div class="info-box-item">
        <div class="info-label">Müşteri Adı Soyadı</div>
        <div class="info-value"><a href="?page=userDetails&id=<?php echo htmlspecialchars($order['userId']); ?>" class="text-dark font-weight-bold"><?php echo htmlspecialchars($order['fullName']); ?></a></div>
    </div>
    <div class="info-box-item">
        <div class="info-label">Telefon Numarası</div>
        <div class="info-value text-break"><?php echo htmlspecialchars($shippingAddress['phone'] ?? 'N/A'); ?></div>
        </div>
    <div class="info-box-item border-bottom-0">
        <div class="info-label">E-posta</div>
        <div class="info-value text-break"><?php echo htmlspecialchars($order['email']); ?></div>
    </div>
    </div>
    </div>
    
    <div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
    <h6 class="m-0 font-weight-bold text-dark"><i class="fas fa-map-marked-alt mr-2 text-info"></i> Sipariş Adresi</h6>
    </div>
    <div class="card-body px-4 pt-3 pb-4">
    <?php if ($shippingAddress): ?>
        <div class="address-line">
        <span class="address-text">Alıcı: <strong><?php echo htmlspecialchars($shippingAddress['fullname']); ?></strong></span>
        </div>
        <div class="address-line small text-muted">
        <?php echo nl2br(htmlspecialchars($shippingAddress['addressDetail'])); ?><br>
        <strong class="text-dark"><?php echo htmlspecialchars($shippingAddress['district']); ?> / <?php echo htmlspecialchars($shippingAddress['city']); ?></strong>
        </div>
    <?php else: ?>
        <div class="alert alert-warning small mb-0">Adres bilgisi yok.</div>
    <?php endif; ?>
    </div>
    </div>
    
    <div class="card shadow mb-4">
    <div class="card-header bg-danger py-3">
    <h6 class="m-0 font-weight-bold text-white"><i class="fas fa-money-check-alt card-title-icon mr-2"></i> Finansal & Kargo Bilgileri</h6>
    </div>
    <div class="card-body px-4 pt-3 pb-4">
    <div class="info-box-item">
        <div class="info-label">Ödeme Yöntemi</div>
        <div class="info-value text-capitalize"><?php echo htmlspecialchars($order['paymentMethod'] ?? 'Bilinmiyor'); ?></div>
    </div>
        
        <hr class="mt-3 mb-3">
        
            <div class="info-box-item border-bottom-0 mb-3">
        <div class="info-label">Kargo Takip No</div>
        <div class="info-value">
            <input type="text" 
            class="form-control form-control-sm" 
            id="trackingNumberInput" 
            value="<?php echo htmlspecialchars($order['trackingNumber'] ?? ''); ?>"
            placeholder="Takip numarasını girin"
            >
            </div>
    </div>
    
    <label for="shippingCompanySelect" class="info-label d-block mb-1">Kargo Firması Seç</label>
        <select class="form-control form-control-sm mb-2" id="shippingCompanySelect">
        <option value="" disabled selected>Firma Seçiniz</option>
        <?php foreach ($shippingCompanies as $company): ?>
            <option value="<?php echo htmlspecialchars($company); ?>" 
            <?php echo ($selectedCompany == $company) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($company); ?>
            </option>
        <?php endforeach; ?>
    </select>
    </div>
    </div>
    
    </div>
</div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../../js/adminUsers/orderDetail.js"></script>