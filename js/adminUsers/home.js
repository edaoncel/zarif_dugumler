document.addEventListener('DOMContentLoaded', function() {
 const API_URL = '../../controller/adminUsers/homeController.php?action=dashboardData';

 function generateRandomColors(count) {
  const colors = [
   '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
   '#FF9F40', '#6A5ACD', '#7FFF00', '#DC143C', '#00FFFF',
      '#FF5733', '#C70039' // Yeni renkler eklendi
  ];
  const colorPalette = [];
  for (let i = 0; i < count; i++) {
   colorPalette.push(colors[i % colors.length]);
  }
  return colorPalette;
 }

 async function fetchDashboardData() {
  try {
   const response = await fetch(API_URL);
   if (!response.ok) {
    throw new Error(`HTTP Hata! Durum: ${response.status}`);
   }
   const data = await response.json();
   
   // 1. Sayısal Kartları Güncelleme
   updateStatCards(data);

   // 2. Grafikleri Çizme
   drawSalesChart(data.monthlySalesChart); // ÇİZGİ GRAFİK
   drawPieChart(data.categoryProductChart); // PASTA GRAFİK (Tüm Ürünler)

   // 3. Son Eklenen Ürünler Listesini Güncelleme
   updateLatestProductsList(data.latestProducts);

  } catch (error) {
   console.error("Dashboard verileri çekilirken hata oluştu:", error);
   
   // Hata durumunda güncellenecek tüm kart ID'leri listesi (YENİLER EKLENDİ)
   const cardIds = [
    'totalUsers', 'totalProducts', 'totalCategories', 'totalFavorites', 'totalRevenue', 
    'pendingOrders', 'processingOrders', 'shippedOrders', 'completedOrders', 'cancelledOrders'
   ];
   
   cardIds.forEach(id => {
    const el = document.getElementById(id);
    if(el) el.textContent = 'Hata!'; 
   });
   
   const listEl = document.getElementById('latestProductsList');
   if (listEl) listEl.innerHTML = '<li class="error-message">Veriler yüklenemedi. Sunucu veya Yetki Hatası.</li>';
  }
 }

// home.js içinde GÜNCELLENMİŞ updateStatCards fonksiyonu

function updateStatCards(data) {
  // Mevcut kartlar
  const totalUsers = document.getElementById('totalUsers');
  if (totalUsers) totalUsers.textContent = data.totalUsers || 0;
  
  const totalProducts = document.getElementById('totalProducts');
  if (totalProducts) totalProducts.textContent = data.totalProducts || 0;
  
  const totalCategories = document.getElementById('totalCategories');
  if (totalCategories) totalCategories.textContent = data.totalCategories || 0;
  
  const totalFavorites = document.getElementById('totalFavorites');
  if (totalFavorites) totalFavorites.textContent = data.totalFavorites || 0;

  const totalRevenue = document.getElementById('totalRevenue');
  // Gelir için formatlama gerektiğinden, '0.00' olarak kontrol ederiz
  if (totalRevenue) totalRevenue.textContent = (data.totalRevenue ? parseFloat(data.totalRevenue).toFixed(2) : '0.00');
  
  const pendingOrders = document.getElementById('pendingOrders');
  if (pendingOrders) pendingOrders.textContent = data.pendingOrders || 0;
  
  // YENİ KARTLAR: Hazırlanıyor - Veri yoksa 0 gösterir
  const processingOrders = document.getElementById('processingOrders');
  if (processingOrders) processingOrders.textContent = data.processingOrders || 0;

  // YENİ KARTLAR: Kargolandı - Veri yoksa 0 gösterir
  const shippedOrders = document.getElementById('shippedOrders');
  if (shippedOrders) shippedOrders.textContent = data.shippedOrders || 0;
    
  // Mevcut sipariş durumu kartları
  const completedOrders = document.getElementById('completedOrders');
  if (completedOrders) completedOrders.textContent = data.completedOrders || 0;
  
  const cancelledOrders = document.getElementById('cancelledOrders');
  if (cancelledOrders) cancelledOrders.textContent = data.cancelledOrders || 0;
}
 
 // Çizgi Grafiği (Aylık Satış)
 function drawSalesChart(chartData) {
  const ctx = document.getElementById('monthlySalesChart').getContext('2d');
  if (!chartData.labels || chartData.labels.length === 0) {
   ctx.font = "16px Arial"; ctx.fillStyle = "#888"; ctx.textAlign = "center";
   ctx.fillText("Gösterilecek satış verisi bulunmamaktadır.", ctx.canvas.width / 2, ctx.canvas.height / 2);
   return;
  }

  new Chart(ctx, {
   type: 'line',
   data: {
    labels: chartData.labels,
    datasets: [{
     label: 'Aylık Satış Geliri (TL)',
     data: chartData.data,
     backgroundColor: 'rgba(40, 167, 69, 0.5)', // Yeşil
     borderColor: '#28a745', 
     borderWidth: 2,
     tension: 0.3, // Yumuşak çizgi
     fill: true
    }]
   },
   options: {
    responsive: true,
    maintainAspectRatio: true,
    scales: {
     y: { beginAtZero: true },
     x: { grid: { display: false } }
    },
    plugins: {
     legend: { display: false },
     tooltip: { callbacks: { label: (c) => ` ${c.dataset.label}: ${parseFloat(c.raw).toFixed(2)} TL` } }
    }
   }
  });
 }

 // Pasta grafiği (Tüm Ürünler)
 function drawPieChart(chartData) {
  const ctx = document.getElementById('categoryProductChart').getContext('2d');
  if (!chartData.labels || chartData.labels.length === 0) {
   ctx.font = "16px Arial"; ctx.fillStyle = "#888"; ctx.textAlign = "center";
   ctx.fillText("Gösterilecek ürün verisi bulunmamaktadır.", ctx.canvas.width / 2, ctx.canvas.height / 2);
   return;
  }
  const backgroundColors = generateRandomColors(chartData.labels.length);
  new Chart(ctx, {
   type: 'pie',
   data: {
    labels: chartData.labels,
    datasets: [{ data: chartData.data, backgroundColor: backgroundColors, hoverOffset: 10 }]
   },
   options: {
    responsive: true, maintainAspectRatio: true,
    plugins: { legend: { position: 'right', labels: { padding: 15 } } }
   }
  });
 }
 
 // Son 10 Ürün Listesi
 function updateLatestProductsList(products) {
  const listElement = document.getElementById('latestProductsList');
  if (!listElement) return;

  listElement.innerHTML = ''; 

  if (!products || products.length === 0) {
   listElement.innerHTML = '<li class="loading-item">Henüz eklenmiş bir ürün bulunmamaktadır.</li>';
   return;
  }

  products.forEach(product => {
   const listItem = document.createElement('li');
   listItem.classList.add('product-item');

   const price = parseFloat(product.price);
   const newPrice = product.newPrice ? parseFloat(product.newPrice) : null;
   
   let priceHTML = (newPrice !== null && newPrice < price) ? 
    `<span class="old-price">${price.toFixed(2)} TL</span> <span class="new-price">${newPrice.toFixed(2)} TL</span>` : 
    `<span class="new-price">${price.toFixed(2)} TL</span>`;

   const imageUrl = product.imageUrl || 'https://via.placeholder.com/50x50?text=NO+IMG';

   listItem.innerHTML = `
    <img src="${imageUrl}" alt="${product.name}" class="product-image" onerror="this.onerror=null;this.src='https://via.placeholder.com/50x50?text=NO+IMG';">
    <div class="product-details">
     <p class="product-name">${product.name}</p>
     <p class="product-prices">${priceHTML}</p>
    </div>
   `;
   listElement.appendChild(listItem);
  });
 }

 fetchDashboardData();
});