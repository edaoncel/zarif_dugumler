// admin.js

// Yeni animasyonlu butonun kontrol fonksiyonu. 
// HTML'den `onclick="menuBtnFunction(this)"` ile çağrılır.
function menuBtnFunction(menuBtn) {
    const sidebar = document.getElementById('sidebar');
    
    // 1. Butona 'active' sınıfını ekle/kaldır (Animasyonu başlatır)
    menuBtn.classList.toggle("active");
    
    // 2. Sidebar'a 'menu-open' sınıfını ekle/kaldır (Tüm menüyü açar/kapatır)
    if (sidebar) {
        sidebar.classList.toggle('menu-open');
    }
}


document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const hamburgerBtn = document.getElementById('hamburgerBtn'); // Yeni menü butonu div'i
    const contentArea = document.querySelector('.content-area');

    // --- 1. MOBİL MENÜ İÇERİĞE TIKLANINCA KAPATMA MANTIĞI ---
    if (contentArea && sidebar && hamburgerBtn) {
        contentArea.addEventListener('click', function(e) {
            // Sadece mobil ve menü açıkken kapat
            if (window.innerWidth <= 768 && sidebar.classList.contains('menu-open')) {
                sidebar.classList.remove('menu-open'); 
                hamburgerBtn.classList.remove('active'); // Animasyonu kapat
            }
        });
    }


    // --- 2. PROFİL FOTOĞRAFI YÖNETİMİ MANTIĞI ---
    // Bu kısım temizlik gerektirmez, olduğu gibi korundu.
    
    const photoUploadInput = document.getElementById('photo-upload-input');
    const photoUploadForm = document.getElementById('photo-upload-form');
    const deletePhotoLink = document.getElementById('delete-photo-link');
    const photoDeleteForm = document.getElementById('photo-delete-form');
    
    // Fotoğraf yükleme: Dosya seçildiğinde formu otomatik gönder
    if (photoUploadInput && photoUploadForm) {
        photoUploadInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                photoUploadForm.submit();
            }
        });
    }

    // Fotoğraf silme: Bağlantıya tıklandığında onayla ve formu gönder
    if (deletePhotoLink && photoDeleteForm) {
        deletePhotoLink.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm("Profil fotoğrafınızı silmek istediğinizden emin misiniz?")) {
                photoDeleteForm.submit();
            }
        });
    }

    // --- 3. Mesajları Otomatik Kapatma MANTIĞI ---
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            alert.style.opacity = '0';
            setTimeout(() => alert.style.display = 'none', 500); // CSS geçişi sonrası gizle
        });
    }, 4000); // 4 saniye sonra başlat

});

// Global olarak tanımlanmalı ki HTML'den erişilebilsin.
window.menuBtnFunction = menuBtnFunction;