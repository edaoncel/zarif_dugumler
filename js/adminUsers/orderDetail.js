// orderDetail.js - SİPARİŞ DURUMU GÜNCELLEME İŞLEMLERİ

document.addEventListener('DOMContentLoaded', function() {
    const statusSelect = document.getElementById('statusSelect');
    const updateStatusBtn = document.getElementById('updateStatusBtn');
    const currentStatusElement = document.getElementById('currentStatusText');
    const statusCard = document.querySelector('.status-card'); // Ana kartı bul

    if (!statusSelect || !updateStatusBtn || !currentStatusElement || !statusCard) {
        console.error("Gerekli DOM elemanları bulunamadı (statusSelect, updateStatusBtn, currentStatusText, veya statusCard).");
        return;
    }

    // Sayfa yüklendiğinde varsayılan durumu tut
    let initialStatus = statusSelect.value;

    // Butonun başlangıçtaki HTML içeriği (Başarılı/Hata durumunda kullanılacak)
    const initialButtonHtml = '<i class="fas fa-check"></i> Kaydet';
    updateStatusBtn.innerHTML = initialButtonHtml; 
    
    // Durumları CSS sınıflarına eşleyen yardımcı fonksiyon
    const getCssClass = (statusKey) => {
        // Örn: 'Processing' -> 'info', 'Cancelled' -> 'danger'
        const statusMap = {
            'Pending': 'warning', 
            'Processing': 'info',
            'Shipped': 'primary',
            'Delivered': 'success', 
            'Cancelled': 'danger', 
        };
        return statusMap[statusKey] || 'secondary';
    };

    // Select değiştiğinde butonu aktif et
    statusSelect.addEventListener('change', function() {
        if (this.value !== initialStatus) {
            updateStatusBtn.disabled = false;
        } else {
            updateStatusBtn.disabled = true;
        }
    });

    // Güncelle butonuna tıklandığında AJAX isteği gönder
    updateStatusBtn.addEventListener('click', function() {
        const orderId = this.dataset.orderId;
        const newStatusKey = statusSelect.value;
        const newStatusText = statusSelect.options[statusSelect.selectedIndex].text;
        const newCssClass = getCssClass(newStatusKey);

        if (!confirm(`Sipariş #${orderId} durumunu '${newStatusText}' olarak güncellemek istediğinizden emin misiniz?`)) {
            // Kullanıcı iptal ettiyse select'i ve butonu eski haline getir
            statusSelect.value = initialStatus;
            updateStatusBtn.disabled = true;
            return;
        }

        // Görsel geri bildirim (Yükleniyor)
        updateStatusBtn.disabled = true;
        updateStatusBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Güncelleniyor...';
        
        // AJAX isteği
        fetch('../../controller/adminUsers/ordersController.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=update_status&order_id=${orderId}&new_status=${newStatusKey}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Başarılıysa DOM ve değişkenleri güncelle
                initialStatus = newStatusKey;
                
                // 1. Durum Yazısını Güncelle
                currentStatusElement.textContent = newStatusText;
                
                // 2. CSS Sınıflarını Güncelle
                
                // a) Status Badge (Rozet) Sınıfı
                currentStatusElement.className = ''; 
                currentStatusElement.classList.add('status-badge', 'text-white', `bg-${newCssClass}`); 
                
                // b) Ana Kart Kenarlığını Güncelle (status-card)
                statusCard.classList.remove('status-warning', 'status-info', 'status-primary', 'status-success', 'status-danger');
                statusCard.classList.add(`status-${newCssClass}`);

                alert('Sipariş durumu başarıyla güncellendi.');
                
            } else {
                alert('Hata: Durum güncellenemedi. ' + (data.message || 'Bilinmeyen Hata.'));
                // Hata durumunda select'i eski haline getir
                statusSelect.value = initialStatus;
            }
            
            // Buton durumunu sıfırla
            updateStatusBtn.innerHTML = initialButtonHtml;
            updateStatusBtn.disabled = true; 
        })
        .catch(error => {
            console.error('Fetch Hatası:', error);
            alert('Sunucuya bağlanırken ağ hatası oluştu.');
            
            // Hata durumunda select'i eski haline getir
            statusSelect.value = initialStatus;
            
            // Buton durumunu sıfırla
            updateStatusBtn.innerHTML = initialButtonHtml;
            updateStatusBtn.disabled = true; 
        });
    });
});

// orderDetail.js

document.addEventListener('DOMContentLoaded', function() {
    // ... (Mevcut Durum Güncelleme kodları burada kalacak) ...
    
    // ==========================================================
    // YENİ KISIM: SİPARİŞ DETAY (NOTLAR, KARGO BİLGİLERİ) KAYDETME
    // ==========================================================
    
    const saveDetailsBtn = document.getElementById('saveDetailsBtn');
    const invoiceNoteTextarea = document.getElementById('invoiceNoteTextarea');
    const shippingNoteTextarea = document.getElementById('shippingNoteTextarea');
    const trackingNumberInput = document.getElementById('trackingNumberInput');
    const shippingCompanySelect = document.getElementById('shippingCompanySelect');

    if (!saveDetailsBtn || !invoiceNoteTextarea || !shippingNoteTextarea || !trackingNumberInput || !shippingCompanySelect) {
        console.error("Gerekli detay DOM elemanları bulunamadı (Notlar/Kargo).");
        // return; // Hata olsa bile durum güncellemenin çalışması için yorumda bırakıyoruz.
    }
    
    // Butonun başlangıç HTML içeriği
    const initialDetailsButtonHtml = '<i class="fas fa-check-circle mr-2"></i> AÇIKLAMA/NOT KAYDET';

    if (saveDetailsBtn) {
        saveDetailsBtn.addEventListener('click', function() {
            const orderId = this.dataset.orderId;
            
            // Verileri Topla
            const detailsData = {
                action: 'update_details',
                order_id: orderId,
                shipping_company: shippingCompanySelect.value,
                tracking_number: trackingNumberInput.value,
                invoice_note: invoiceNoteTextarea.value,
                shipping_note: shippingNoteTextarea.value
            };
            
            if (!confirm(`Sipariş #${orderId}'in tüm detaylarını kaydetmek istediğinizden emin misiniz?`)) {
                return;
            }

            // Görsel geri bildirim (Yükleniyor)
            this.disabled = true;
            this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Kaydediliyor...';
            
            // URLSearchParams kullanarak veriyi POST formatına dönüştür
            const formBody = Object.keys(detailsData).map(key => 
                encodeURIComponent(key) + '=' + encodeURIComponent(detailsData[key])
            ).join('&');

            // AJAX isteği
            fetch('../../controller/adminUsers/ordersController.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formBody
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Detaylar başarıyla güncellendi!');
                    // Başarılıysa, input/textarea'nın yeni değerleri, ilk değer olarak kabul edilir.
                    // Gerekirse sayfayı yenilemeden yeni değeri alabiliriz.
                } else {
                    alert('Hata: Detaylar güncellenemedi. ' + (data.message || 'Bilinmeyen Hata.'));
                }
                
                // Buton durumunu sıfırla
                this.innerHTML = initialDetailsButtonHtml;
                this.disabled = false;
            })
            .catch(error => {
                console.error('Fetch Hatası:', error);
                alert('Sunucuya bağlanırken ağ hatası oluştu.');
                
                // Buton durumunu sıfırla
                this.innerHTML = initialDetailsButtonHtml;
                this.disabled = false;
            });
        });
    }
});