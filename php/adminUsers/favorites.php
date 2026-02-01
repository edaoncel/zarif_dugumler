<?php
// favoritesController.php'yi dahil edin ve verileri çekin
include_once "../../controller/adminUsers/favoritesController.php";
if (!isset($_SESSION['admin_loggedIn']) || $_SESSION['admin_loggedIn'] !== true) {
 header("Location: adminUsers.php"); 
 exit;
}
// favoritesController.php içinde $favoritesData doldurulmuş olmalı
global $favoritesData; 
?>
<!DOCTYPE html>
<html lang="tr">
<head>
 <meta charset="UTF-8">
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <title>Yönetici Paneli - Favori Yönetimi</title>
 <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
 <link rel="stylesheet" href="../../css/adminUsers/favorites.css"> 
</head>
<body>
 <div class="dashboard-container">

  <div class="favorites-table-container">
   <table class="data-table">
    <thead>
     <tr>
      <th>ID</th>
      <th>Kullanıcı</th>
      <th>Ürün Resmi</th>
      <th>Ürün Adı</th>
      <th>İşlemler</th>      </tr>
    </thead>
    <tbody id="favoritesTableBody">
     <?php if (!empty($favoritesData)): ?>
  <?php foreach ($favoritesData as $favorite): ?>
    <tr data-favorite-id="<?= htmlspecialchars($favorite['favorite_id']) ?>">
      <td><?= htmlspecialchars($favorite['favorite_id']) ?></td>
            <td><?= htmlspecialchars($favorite['ad'] . ' ' . $favorite['soyad']) ?></td>
      <td><img src="<?= htmlspecialchars($favorite['imageUrl']) ?>" alt="Ürün Resmi" class="product-thumb"></td>
      <td><?= htmlspecialchars($favorite['product_name']) ?></td>
      <td>
    <a href="#" class="btn-danger" onclick="removeFavorite(<?= $favorite['favorite_id'] ?>)"><i class="fas fa-trash-alt"></i> 
      </td>
    </tr>
  <?php endforeach; ?>
     <?php else: ?>
      <tr>
       <td colspan="5" style="text-align: center;">Henüz favorilenen bir ürün bulunmamaktadır.</td>       </tr>
     <?php endif; ?>
    </tbody>
   </table>
  </div>
 </div>
 
 <script src="../../js/adminUsers/favorites.js"></script>
</body>
</html>