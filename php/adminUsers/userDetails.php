<?php
include_once "../../controller/adminUsers/userDetailsController.php"; 


global $pdo;

$userId = $_GET['id'] ?? null;

if (!$userId || !is_numeric($userId)) {
    header("Location: ?page=dashboard"); 
    exit;
}

// 1. Verileri Çek
$user = getUserDetails($pdo, $userId); 
$userAddresses = getUserAddresses($pdo, $userId); 
$userFavorites = getUserFavoritesList($pdo, $userId); 
$userOrders = getUserOrdersList($pdo, $userId); 
$userOrderItems = getUserOrderItems($pdo, $userId); 

if (!$user || isset($user['error'])) {
    echo '<link href="../../css/adminUsers/userDetails.css" rel="stylesheet">'; 
    echo "<div class='alert alert-danger p-4 shadow-sm mx-3 mt-3'>Kullanıcı ID: **#". htmlspecialchars($userId) ."** sistemde bulunamadı.</div>";
    exit;
}

// 2. Özet Bilgileri ve Durumları Hesaplama

// TOPLAM SİPARİŞ ADEDİ
$totalOrders = count($userOrders);

// TOPLAM SİPARİŞ EDİLEN ÜRÜN KALEMİ SAYISI (KRİTİK DÜZELTME!)
$totalOrderItemsCount = 0;
foreach ($userOrderItems as $orderGroup) {
    $totalOrderItemsCount += count($orderGroup['items']);
}

// TOPLAM HARCAMA (Dinamik hesaplama)
$totalSpent = 0;
if (!empty($userOrders)) {
    foreach ($userOrders as $order) {
        $totalSpent += calculateOrderTotal($pdo, $order['id']);
    }
}

// KULLANICI DURUM BUTONU HESAPLAMALARI
$is_active = ($user['status'] == 'active');

// MEVCUT GÖRÜNÜM ayarları
$currentStatusTurkish = $is_active ? 'AKTİF MÜŞTERİ' : 'PASİF MÜŞTERİ';
$currentClass = $is_active ? 'success' : 'danger';
$currentIcon = $is_active ? 'fa-toggle-on' : 'fa-toggle-off'; // Aktif: ON, Pasif: OFF

// SONRAKİ İŞLEM ayarları
$nextStatus = $is_active ? 'passive' : 'active'; // Tıklanınca yeni durum
$nextActionText = $is_active ? 'PASİF YAP' : 'AKTİF YAP'; // Tıklanınca gösterilecek yazı (aksiyon)

// Diğer Bilgiler
$registrationDate = isset($user['registrationDate']) ? date('d.m.Y H:i', strtotime($user['registrationDate'])) : 'Bilinmiyor';
$hasPhoto = !empty($user['userPhoto']);
$userPhotoUrl = $hasPhoto ? '../../img/users/' . htmlspecialchars($user['userPhoto']) : '';

// Sipariş Durumu Çeviri Haritası
$orderStatusMap = [
    'Pending' => ['text' => 'Beklemede', 'class' => 'Pending'],
    'Processing' => ['text' => 'Hazırlanıyor', 'class' => 'Processing'],
    'Completed' => ['text' => 'Tamamlandı', 'class' => 'Completed'],
    'Cancelled' => ['text' => 'İptal Edildi', 'class' => 'Cancelled'],
    'Shipped' => ['text' => 'Kargolandı', 'class' => 'Shipped'],
    'Delivered' => ['text' => 'Teslim Edildi', 'class' => 'Delivered'],
];
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
<link href="../../css/adminUsers/userDetails.css" rel="stylesheet">
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">

<div class="container-fluid">

    <div class="user-header-area">
        <div class="user-header-content">

            <?php if ($hasPhoto): ?>
                <img src="<?php echo $userPhotoUrl; ?>" alt="Profil Fotoğrafı" class="user-profile-photo">
            <?php else: ?>
                <div class="user-profile-icon">
                    <i class="fas fa-user"></i>
                </div>
            <?php endif; ?>
            
            <div class="user-details-text">
                <h3><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h3>
                <p class="text-muted mb-0"><i class="fas fa-envelope mr-1"></i> <?php echo htmlspecialchars($user['email']); ?></p>
            </div>

            <button class="btn btn-<?php echo $currentClass; ?> status-toggle-btn ml-auto" 
                    type="button"
                    data-user-id="<?php echo htmlspecialchars($user['id']); ?>" 
                    data-current-status="<?php echo htmlspecialchars($user['status']); ?>"
                    data-next-status="<?php echo $nextStatus; ?>"
                    style="min-width: 180px;">
                
                <i class="fas <?php echo $currentIcon; ?> mr-2"></i>
                
                <span id="currentStatusText">
                    <?php echo $currentStatusTurkish; ?>
                </span>
                
                <span class="small font-weight-light ml-2 text-white-50">
                    (<?php echo $nextActionText; ?>)
                </span>
                
            </button>
            </div>
    </div>
    <div class="row">

        <div class="col-lg-8 mb-4">
            <div class="card shadow">
                <div class="card-header p-0">
                    <ul class="nav nav-tabs" id="userTabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="orders-detail-tab" data-toggle="tab" href="#orders-detail" role="tab" aria-controls="orders-detail" aria-selected="true"><i class="fas fa-list-alt mr-1"></i> Sipariş Detayları (<?php echo $totalOrders; ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="orders-products-tab" data-toggle="tab" href="#orders-products" role="tab" aria-controls="orders-products" aria-selected="false"><i class="fas fa-box-open mr-1"></i> Sipariş Edilen Ürünler (<?php echo $totalOrderItemsCount; ?>)</a> </li>
                        <li class="nav-item">
                            <a class="nav-link" id="favorites-tab" data-toggle="tab" href="#favorites" role="tab" aria-controls="favorites" aria-selected="false"><i class="fas fa-heart mr-1"></i> Favori Ürünler (<?php echo count($userFavorites); ?>)</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="addresses-tab" data-toggle="tab" href="#addresses" role="tab" aria-controls="addresses" aria-selected="false"><i class="fas fa-map-marker-alt mr-1"></i> Adresler (<?php echo count($userAddresses); ?>)</a>
                        </li>
                    </ul>
                </div>
                
                <div class="tab-content" id="userTabsContent">

                    <div class="tab-pane fade show active" id="orders-detail" role="tabpanel" aria-labelledby="orders-detail-tab">
                        <?php if (!empty($userOrders)): ?>
                        <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0 small">
                                <thead class="thead-light">
                                    <tr>
                                        <th>Sipariş No</th>
                                        <th>Tarih</th>
                                        <th>Tutar</th>
                                        <th>Durum</th>
                                        <th>Kargo Takip</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($userOrders as $order): 
                                        $statusData = $orderStatusMap[$order['status']] ?? ['text' => $order['status'], 'class' => 'secondary'];
                                        $orderDate = date('d.m.Y H:i', strtotime($order['orderDate']));
                                        $calculatedTotal = calculateOrderTotal($pdo, $order['id']); 
                                    ?>
                                    <tr>
                                        <td class="font-weight-bold text-nowrap">
                                            <a href="?page=orderDetail&id=<?php echo htmlspecialchars($order['id']); ?>" 
                                                style="color: var(--color-primary); text-decoration: none;">
                                                #<?php echo htmlspecialchars($order['id']); ?>
                                            </a>
                                        </td>
                                        <td class="text-nowrap"><?php echo $orderDate; ?></td>
                                        <td><strong><?php echo number_format($calculatedTotal, 2, ',', '.') . ' ₺'; ?></strong></td> 
                                        <td><span class="order-status-badge status-<?php echo $statusData['class']; ?>"><?php echo $statusData['text']; ?></span></td>
                                        <td>
                                            <?php if (!empty($order['trackingNumber'])): ?>
                                            <span class="text-success"><i class="fas fa-shipping-fast mr-1"></i> <?php echo htmlspecialchars($order['trackingNumber']); ?></span>
                                            <?php else: ?>
                                            <span class="text-secondary">Yok</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php else: ?>
                            <p class="text-muted text-center p-3 mb-0"><i class="fas fa-info-circle mr-1"></i> Bu müşteriye ait kayıtlı sipariş bulunmamaktadır.</p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="orders-products" role="tabpanel" aria-labelledby="orders-products-tab">
                        <?php if (!empty($userOrderItems)): ?>
                        <div class="item-card-grid">
                            <?php foreach ($userOrderItems as $orderGroup): ?>
                                <?php foreach ($orderGroup['items'] as $item): 
                                    $statusData = $orderStatusMap[$orderGroup['orderStatus']] ?? ['text' => $orderGroup['orderStatus'], 'class' => 'secondary'];
                                    $turkishStatus = $statusData['text'];
                                    $orderStatusClass = $statusData['class'];
                                    $totalItemPrice = $item['totalItemPrice'] ?? ($item['quantity'] * $item['itemPrice']);
                                    $shippingFee = $item['productShippingFee'] ?? 0.00;
                                ?>
                                    <div class="order-item-card">
                                        <?php $fileName = basename($item['productImageUrl']); ?>
                                        <img src="../../img/product/<?php echo htmlspecialchars($fileName); ?>" 
                                            alt="<?php echo htmlspecialchars($item['productName']); ?>" 
                                            class="item-card-img mr-3">
                                        
                                        <div class="item-card-details">
                                            <strong title="<?php echo htmlspecialchars($item['productName']); ?>"><?php echo htmlspecialchars($item['productName']); ?></strong>
                                            <p class="small mb-1">Sipariş ID: #<?php echo htmlspecialchars($orderGroup['orderId']); ?></p>
                                            
                                            <p class="small mb-1">
                                                Adet: x<?php echo htmlspecialchars($item['quantity']); ?> 
                                                | Birim Fiyat: <?php echo number_format($item['itemPrice'], 2, ',', '.') . ' ₺'; ?>
                                            </p>
                                            
                                            <?php if (!empty($item['variantColor']) || !empty($item['variantSize'])): ?>
                                            <p class="small mb-1 text-muted">
                                                <?php 
                                                    $variantDetails = [];
                                                    if (!empty($item['variantColor'])) {
                                                        $variantDetails[] = 'Renk: ' . htmlspecialchars($item['variantColor']);
                                                    }
                                                    if (!empty($item['variantSize'])) {
                                                        $variantDetails[] = 'Beden: ' . htmlspecialchars($item['variantSize']);
                                                    }
                                                    echo '<i class="fas fa-palette mr-1"></i>' . implode(' | ', $variantDetails);
                                                ?>
                                            </p>
                                            <?php endif; ?>

                                            <p class="small mb-1 font-weight-bold text-success">
                                                <i class="fas fa-hand-holding-usd mr-1"></i> Toplam: <?php echo number_format($totalItemPrice, 2, ',', '.') . ' ₺'; ?>
                                            </p>
                                            <p class="small mb-1 text-info">
                                                <i class="fas fa-truck-moving mr-1"></i> Kargo Ücreti: <?php echo number_format($shippingFee, 2, ',', '.') . ' ₺'; ?>
                                            </p>
                                            
                                            <span class="order-status-badge status-<?php echo $orderStatusClass; ?> mt-1 d-inline-block"><?php echo $turkishStatus; ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="text-muted text-center p-3 mb-0"><i class="fas fa-info-circle mr-1"></i> Bu müşteriye ait kayıtlı sipariş ürünü bulunmamaktadır.</p>
                        <?php endif; ?>
                    </div>

                    <div class="tab-pane fade" id="favorites" role="tabpanel" aria-labelledby="favorites-tab">
                        <?php if (!empty($userFavorites)): ?>
                        <div class="item-card-grid">
                            <?php foreach ($userFavorites as $fav): ?>
                                <div class="favorite-item-card">
                                    <?php $favFileName = basename($fav['imageUrl']); ?>
                                    <img src="../../img/product/<?php echo htmlspecialchars($favFileName); ?>" 
                                        alt="<?php echo htmlspecialchars($fav['name']); ?>"
                                        class="item-card-img">
                                    <div class="item-card-details">
                                        <strong title="<?php echo htmlspecialchars($fav['name']); ?>"><?php echo htmlspecialchars($fav['name']); ?></strong>
                                        <p class="small mb-0">Ürün ID: #<?php echo htmlspecialchars($fav['productId'] ?? 'N/A'); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="text-muted text-center p-3 mb-0"><i class="fas fa-bell-slash mr-1"></i> Bu müşterinin favori ürünü bulunmamaktadır.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="tab-pane fade" id="addresses" role="tabpanel" aria-labelledby="addresses-tab">
                        <?php if (!empty($userAddresses)): ?>
                        <div class="address-list-group">
                            <?php foreach ($userAddresses as $address): ?>
                                <div class="list-group-item <?php echo $address['isDefault'] ? 'default-address' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="address-title"><?php echo htmlspecialchars($address['title']); ?></span>
                                        <?php if ($address['isDefault']): ?>
                                        <span class="address-badge badge" style="background-color: var(--color-primary); color:#fff;"><i class="fas fa-star mr-1"></i> Varsayılan</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="small text-muted mb-0">
                                        <?php echo htmlspecialchars($address['addressDetail']); ?> <br>
                                        <span class="text-dark font-weight-bold"><?php echo htmlspecialchars($address['district']); ?> / <?php echo htmlspecialchars($address['city']); ?></span> <?php echo htmlspecialchars($address['zipCode']); ?>
                                    </p>
                                    <div class="small mt-2 border-top pt-2">
                                        <i class="fas fa-user-tag text-secondary mr-1"></i> Alıcı: <?php echo htmlspecialchars($address['fullname']); ?>
                                        <span class="ml-3"><i class="fas fa-phone text-secondary mr-1"></i> Tel: <?php echo htmlspecialchars($address['phone']); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                            <p class="text-muted text-center p-3 mb-0"><i class="fas fa-exclamation-circle mr-1"></i> Bu müşteriye ait kayıtlı adres bulunmamaktadır.</p>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
        </div>
        <div class="col-lg-4 mb-4">

            <div class="card shadow mb-4 card-general-info">
                <div class="card-header py-3 bg-white">
                    <h6 class="m-0 font-weight-bold" style="color: var(--color-primary);"><i class="fas fa-info-circle mr-1"></i> Genel Bilgiler & Özet</h6>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-bordered user-info-table mb-0">
                        <tbody>
                            <tr>
                                <th>Ad Soyad</th>
                                <td><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></td>
                            </tr>
                            <tr>
                                <th>E-posta</th>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                            </tr>
                            <tr>
                                <th>Telefon</th>
                                <td><?php echo htmlspecialchars($userAddresses[0]['phone'] ?? 'Belirtilmemiş'); ?></td>
                            </tr>
                            <tr><td colspan="2" class="p-1" style="background-color: #f1f4f9;"></td></tr>
                            <tr>
                                <th>Toplam Sipariş</th>
                                <td><span class="font-weight-bold" style="color: var(--color-primary);"><?php echo $totalOrders; ?> Adet</span></td>
                            </tr>
                            <tr>
                                <th>Toplam Harcama</th>
                                <td><strong style="color: var(--color-danger);"><?php echo number_format($totalSpent, 2, ',', '.') . ' ₺'; ?></strong></td>
                            </tr>
                            <tr>
                                <th>Durum</th>
                                <td><span class="badge badge-<?php echo $currentClass; ?> order-status-badge"><?php echo $currentStatusTurkish; ?></span></td>
                            </tr>
                            <tr>
                                <th>Kayıt Tarihi</th>
                                <td><?php echo $registrationDate; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.4/dist/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script src="../../js/adminUsers/userDetails.js"></script>