<?php
// orders.php
// products.php'nin admin.php içinde çalıştığını varsayarak aynı dizin yapısını kullanıyoruz.
include_once "../../db/db.php"; 
// ordersController.php'yi dahil ettiğinizden emin olun.
include_once "../../controller/adminUsers/ordersController.php";

// Varsayılan dil ve durum eşleşmeleri (Artık kullanılmıyor, sadece $orderStatusMap kullanılıyor)
$orderStatuses = [
    'Pending' => 'Beklemede', 
    'Processing' => 'Hazırlanıyor', 
    'Shipped' => 'Kargolandı', 
    'Delivered' => 'Teslim Edildi', 
    'Cancelled' => 'İptal Edildi'
];

// Renk Sınıflarını içeren Durum Haritası
$orderStatusMap = [
    'Pending'    => ['text' => 'Beklemede',    'class' => 'status-warning'],  // Sarı
    'Processing' => ['text' => 'Hazırlanıyor', 'class' => 'status-primary'],  // Bordo
    'Shipped'    => ['text' => 'Kargolandı',   'class' => 'status-info'],     // Açık Mavi
    'Delivered'  => ['text' => 'Teslim Edildi','class' => 'status-success'],  // Yeşil
    'Completed'  => ['text' => 'Teslim Edildi','class' => 'status-success'],  // Yeşil
    'Cancelled'  => ['text' => 'İptal Edildi', 'class' => 'status-danger'],   // Kırmızı
];

// URL'den filtreleri al (Filtreleme kaldırıldığı için sadece temiz başlangıç)
$currentPage = $_GET['page'] ?? 'orders'; 

// Filtreler artık kullanılmadığı için boş bir dizi olarak tutulabilir.
$filters = []; 

global $pdo; 
// getOrders fonksiyonuna boş filtre gönderiyoruz.
$orders = getOrders($pdo, $filters); 

?>

<head>
    <link href="../../css/adminUsers/orders.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
</head>


<div class="order-list-table table-responsive">
    <table class="data-table table table-hover"> 
        <thead>
            <tr>
                <th>Sipariş Kodu</th>
                <th>Müşteri</th>
                <th>Tutar</th>
                <th>Tarih</th>
                <th>Durum</th>
                <th>Aksiyon</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($orders)): ?>
                <tr>
                    <td colspan="6" class="text-center">Kayıtlı sipariş bulunmamaktadır.</td> 
                </tr>
            <?php else: ?>
                <?php foreach ($orders as $order): ?>
            <tr>
                <td data-label="Sipariş Kodu"><?php echo htmlspecialchars($order['id']); ?></td>
                <td data-label="Müşteri">
                    <a href="?page=userDetails&id=<?php echo htmlspecialchars($order['userId']); ?>">
                        <?php echo htmlspecialchars($order['fullName']); ?>
                    </a>
                </td>
                
                <td data-label="Tutar"><?php echo number_format($order['totalAmount'], 2, ',', '.') . ' ₺'; ?></td>
                <td data-label="Tarih"><?php echo date('d.m.Y H:i', strtotime($order['orderDate'])); ?></td>
                
                <td data-label="Durum" class="status-cell">
                    <?php
                    // Mevcut siparişin durum anahtarını alıyoruz
                    $statusKey = $order['status'] ?? 'default';
                    
                    // Haritadan Türkçe metni ve özel CSS sınıfını çekiyoruz
                    $statusData = $orderStatusMap[$statusKey] ?? ['text' => $statusKey, 'class' => 'status-secondary'];
                    $turkishStatus = $statusData['text'];
                    $customStatusClass = $statusData['class'];  
                    ?>

                    <button type="button" class="status-button-final <?php echo htmlspecialchars($customStatusClass); ?>">
                        <?php echo htmlspecialchars($turkishStatus); ?>
                    </button>
                </td>
                        <td data-label="Aksiyon">
                    <a href="?page=orderDetail&id=<?php echo htmlspecialchars($order['id']); ?>" 
                       class="btn btn-sm btn-info" title="Sipariş Detayları">
                        <i class="fas fa-eye"></i> Detay
                    </a>
                </td>
                
            </tr>
        <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
