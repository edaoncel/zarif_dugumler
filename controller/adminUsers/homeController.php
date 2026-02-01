<?php
// Oturum başlatma
if (session_status() == PHP_SESSION_NONE) {
session_start();
}

// Veritabanı bağlantısı
include_once "../../db/db.php"; 

// --- GÜVENLİK KONTROLÜ ---
if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
if (isset($_GET['action']) && $_GET['action'] == 'dashboardData') {
 http_response_code(403); 
 echo json_encode(['error' => 'Yetkisiz Erişim']);
} else {
 header("Location: adminUsers.php"); 
}
exit;
}


function getDashboardData() {
global $pdo;
$data = [];

// --- 1. Temel Sayısal İstatistikler (Uyumsuzluk Düzeltildi) ---
try {
 $data['totalUsers'] = $pdo->query('SELECT COUNT(id) FROM users')->fetchColumn();
 $data['totalProducts'] = $pdo->query('SELECT COUNT(id) FROM product WHERE isDeleted = 0')->fetchColumn();
 $data['totalCategories'] = $pdo->query('SELECT COUNT(id) FROM categories')->fetchColumn();
 $data['totalFavorites'] = $pdo->query('SELECT COUNT(id) FROM favorites')->fetchColumn();
 
 // DÜZELTME: Toplam Gelir için hem 'Completed' hem de 'Delivered' kabul edildi.
 // Bu, eski verilerinizi de hesaba katar.
 $data['totalRevenue'] = $pdo->query("SELECT IFNULL(SUM(totalAmount), 0) FROM orders WHERE status IN ('Completed', 'Delivered')")->fetchColumn();
 
 // SİPARİŞ DURUM METRİKLERİ
 // Bekleyen Siparişler (Pending)
 $data['pendingOrders'] = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'Pending'")->fetchColumn();
  
 // Hazırlanıyor (Processing)
 $data['processingOrders'] = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'Processing'")->fetchColumn();

 // Kargolandı (Shipped)
 $data['shippedOrders'] = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'Shipped'")->fetchColumn();

 // Tamamlanan/Teslim Edilen Siparişler (Completed/Delivered)
 $data['completedOrders'] = $pdo->query("SELECT COUNT(id) FROM orders WHERE status IN ('Completed', 'Delivered')")->fetchColumn();
 
 // İptal Edilen Siparişler (Cancelled)
 $data['cancelledOrders'] = $pdo->query("SELECT COUNT(id) FROM orders WHERE status = 'Cancelled'")->fetchColumn();

} catch (PDOException $e) {
 error_log("Dashboard veri çekme hatası (Sayılar): " . $e->getMessage());
 // Hata durumunda varsayılan değerler
 $data['totalUsers'] = $data['totalProducts'] = $data['totalCategories'] = $data['totalFavorites'] = $data['totalRevenue'] = $data['pendingOrders'] = 0;
 $data['processingOrders'] = $data['shippedOrders'] = $data['completedOrders'] = $data['cancelledOrders'] = 0;
}

// --- 2. Tüm Ürünlerin Kategorilere Göre Dağılımı (PASTA GRAFİK) ---
try {
 $stmt = $pdo->prepare("
 SELECT c.name AS categoryName, COUNT(p.id) AS productCount
 FROM product p
 JOIN categories c ON p.categoryId = c.id
 WHERE p.isDeleted = 0
 GROUP BY c.name
 ORDER BY productCount DESC
 ");
 $stmt->execute();
 $categoryProductData = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $data['categoryProductChart'] = [
 'labels' => array_column($categoryProductData, 'categoryName'),
 'data' => array_column($categoryProductData, 'productCount')
 ];
} catch (PDOException $e) {
 error_log("Dashboard veri çekme hatası (Pasta Grafik): " . $e->getMessage());
 $data['categoryProductChart'] = ['labels' => [], 'data' => []];
}

try {
 $stmt = $pdo->prepare("
 SELECT c.name AS categoryName, COUNT(p.id) AS productCount
 FROM product p
 JOIN categories c ON p.categoryId = c.id
 WHERE p.id IN (
  SELECT id FROM product WHERE isDeleted = 0 ORDER BY id DESC LIMIT 5
 )
 GROUP BY c.name
 ORDER BY productCount DESC
 ");
 $stmt->execute();
 $recentCategoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
 
 $data['recentProductsChart'] = [
 'labels' => array_column($recentCategoryData, 'categoryName'),
 'data' => array_column($recentCategoryData, 'productCount')
 ];
 
} catch (PDOException $e) {
 error_log("Dashboard veri çekme hatası (Son 5 Ürün Grafik): " . $e->getMessage());
 $data['recentProductsChart'] = ['labels' => [], 'data' => []];
}

// --- 4. Aylık Satış Geliri (ÇİZGİ GRAFİK) ---
try {
 $stmt = $pdo->prepare("
 SELECT 
  DATE_FORMAT(orderDate, '%Y-%m') AS month,
  SUM(totalAmount) AS totalSales
 FROM orders
 WHERE status IN ('Completed', 'Delivered') -- DÜZELTME: Hem 'Completed' hem 'Delivered' kabul edildi
 AND orderDate >= DATE_SUB(NOW(), INTERVAL 6 MONTH) -- Son 6 ayı al
 GROUP BY month
 ORDER BY month ASC
 ");
 $stmt->execute();
 $monthlySalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

 $data['monthlySalesChart'] = [
 'labels' => array_column($monthlySalesData, 'month'),
 'data' => array_column($monthlySalesData, 'totalSales')
 ];

} catch (PDOException $e) {
 error_log("Dashboard veri çekme hatası (Aylık Satış): " . $e->getMessage());
 $data['monthlySalesChart'] = ['labels' => [], 'data' => []];
}

try {
$stmt = $pdo->prepare("
SELECT 
 p.name, 
 p.price, 
 p.newPrice, 
 pi.imageUrl  -- productimages'tan ÇEKİLİYOR
FROM product p
LEFT JOIN (                   -- Ana resmi almak için alt sorgu
 SELECT productId, imageUrl 
 FROM productimages 
 GROUP BY productId 
 ORDER BY id ASC
) pi ON pi.productId = p.id
WHERE p.isDeleted = 0
ORDER BY p.id DESC
LIMIT 5 
");
$stmt->execute();
$data['latestProducts'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
error_log("Dashboard veri çekme hatası (Son Ürünler): " . $e->getMessage());
$data['latestProducts'] = [];
}

return $data;
}

// --- AJAX İŞLEYİCİ ---
if (isset($_GET['action']) && $_GET['action'] == 'dashboardData') {
header('Content-Type: application/json');
echo json_encode(getDashboardData()); 
exit;
}
?>