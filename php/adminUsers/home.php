<?php
// PHP include_once ve oturum kontrolü yukarı taşındı.
include_once "../../controller/adminUsers/homeController.php";
if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
 header("Location: adminUsers.php"); 
 exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Yönetici Paneli - Anasayfa</title>
 <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
 <link rel="stylesheet" href="../../css/adminUsers/home.css">
</head>
<body>
 <div class="dashboard-container">
  
  <div class="stats-cards-grid">
      
              <div class="stat-card card-users">
    <i class="fas fa-users card-icon"></i>
    <div class="card-info">
     <p class="card-label">Toplam Kullanıcı</p>
     <h2 class="card-value" id="totalUsers"></h2>
    </div>
   </div>

   <div class="stat-card card-products">
    <i class="fas fa-box card-icon"></i>
    <div class="card-info">
     <p class="card-label">Aktif Ürün Sayısı</p>
     <h2 class="card-value" id="totalProducts"></h2>
    </div>
   </div>

   <div class="stat-card card-categories">
    <i class="fas fa-th-large card-icon"></i>
    <div class="card-info">
     <p class="card-label">Toplam Kategori</p>
     <h2 class="card-value" id="totalCategories"></h2>
    </div>
   </div>
        
   <div class="stat-card card-favorites">
    <i class="fas fa-heart card-icon"></i>
    <div class="card-info">
     <p class="card-label">Toplam Favori</p>
     <h2 class="card-value" id="totalFavorites"></h2>
    </div>
   </div>

           <div class="stat-card card-revenue">
    <i class="fas fa-money-bill-wave card-icon"></i>     <div class="card-info">
     <p class="card-label">Toplam Gelir (TL)</p>
     <h2 class="card-value" id="totalRevenue"></h2>
    </div>
   </div>

           
   <div class="stat-card card-orders-pending">
    <i class="fas fa-file-invoice card-icon"></i>     <div class="card-info">
     <p class="card-label">Bekleyen Sipariş</p>
     <h2 class="card-value" id="pendingOrders"></h2>
    </div>
   </div>
      
  <div class="stat-card card-orders-processing">
      <i class="fas fa-cogs card-icon"></i>   <div class="card-info">
    <p class="card-label">Hazırlanan Sipariş</p>
    <h2 class="card-value" id="processingOrders">0</h2>   </div>
  </div>

  <div class="stat-card card-orders-shipped"> 
      <i class="fas fa-shipping-fast card-icon"></i>   <div class="card-info">
    <p class="card-label">Kargodaki Sipariş</p>
    <h2 class="card-value" id="shippedOrders">0</h2>   </div>
  </div>
        
   <div class="stat-card card-orders-completed"> 
    <i class="fas fa-box-open card-icon"></i> 
    <div class="card-info">
     <p class="card-label">Teslim Edilen Sipariş</p>
     <h2 class="card-value" id="completedOrders"></h2>     </div>
   </div>
   
   <div class="stat-card card-orders-cancelled">
    <i class="fas fa-times-circle card-icon"></i> 
    <div class="card-info">
     <p class="card-label">İptal Edilen Sipariş</p>
     <h2 class="card-value" id="cancelledOrders"></h2>
    </div>
   </div>
  </div>
  
      
<div class="charts-row-grid">
  <div class="chart-box monthly-sales-chart">
    <h3 class="box-title">Son 6 Aylık Satış Geliri</h3>
    <canvas id="monthlySalesChart"></canvas>
  </div>

  <div class="chart-box product-distribution-chart">
    <h3 class="box-title">Tüm Ürünlerin Kategori Dağılımı</h3>
    <canvas id="categoryProductChart"></canvas>
  </div>
  
  <div class="chart-box latest-products-list"> 
    <h3 class="box-title">Son Eklenen 5 Ürün</h3>
    <ul id="latestProductsList" class="product-list">
      <li class="loading-item">Veriler </li>
    </ul>
  </div>

  
 </div>
 
 <script src="../../js/adminUsers/home.js"></script>
</body>
</html>