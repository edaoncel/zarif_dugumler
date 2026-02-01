// E:\xampp\htdocs\sinem\js\adminUsers\userDetails.js
// Bu kodun çalışması için sayfanıza jQuery, Bootstrap JS ve Font Awesome ikon kütüphanelerinin (fas) dahil edilmiş olması gerekir.

$(document).ready(function() {
    // Bootstrap tablarını etkinleştir (Admin/Kullanıcı detay sayfasındaki sekmeler)
    $('#userTabs a').on('click', function (e) {
        e.preventDefault();
        $(this).tab('show');
    });

    // Kullanıcı Durumunu Değiştirme Butonu İşlevselliği
    $('.status-toggle-btn').on('click', function() {
        const $button = $(this);
        const userId = $button.data('user-id');
        // Mevcut durumu data niteliğinden al
        let currentStatus = $button.data('current-status');
        // Yeni durumu belirle (toggle işlemi)
        let newStatus = (currentStatus === 'active') ? 'passive' : 'active';
        
        // Yeni durum için Türkçe metinler ve CSS/ikon sınıfları
        const newStatusTurkish = (newStatus === 'active') ? 'AKTİF MÜŞTERİ' : 'PASİF MÜŞTERİ';
        const newClass = (newStatus === 'active') ? 'success' : 'danger';
        const newIcon = (newStatus === 'active') ? 'fa-toggle-on' : 'fa-toggle-off';
        const nextActionText = (newStatus === 'active') ? 'PASİF YAP' : 'AKTİF YAP';
        
        // Kullanıcı onayı al
        const confirmMessage = `Kullanıcının durumunu ${newStatusTurkish} olarak değiştirmek istediğinizden emin misiniz?`;
        
        if (!confirm(confirmMessage)) {
            return; 
        }

        // Butonu devre dışı bırak ve yükleniyor göster
        $button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Lütfen Bekleyin...');

        // AJAX isteği: Controller'daki AJAX işleyicisine istek gönderilir
        $.ajax({
            // Path düzeltildi: E:\xampp\htdocs\sinem\js\adminUsers\ -> E:\xampp\htdocs\sinem\controller\users\
            url: '../../controller/users/usersController.php', 
            type: 'POST',
            data: { 
                action: 'toggle_status', // PHP Controller'da bu aksiyonu işleyecek blok
                user_id: userId, 
                new_status: newStatus 
            },
            dataType: 'json', 
            success: function(response) {
                if (response.success) {
                    
                    // 1. BUTONU ANINDA GÜNCELLE
                    // Eski renk sınıfını kaldır, yenisini ekle
                    $button.removeClass('btn-success btn-danger').addClass('btn-' + newClass);
                    // Butonun içindeki metni ve ikonu güncelle
                    $button.html(`
                        <i class="fas ${newIcon} mr-2"></i>
                        <span id="currentStatusText">${newStatusTurkish}</span>
                        <span class="small font-weight-light ml-2 text-white-50">
                            (${nextActionText})
                        </span>
                    `);
                    
                    // Butonun data-* niteliklerini güncelle (Sonraki tıklama için durumu hazırla)
                    $button.data('current-status', newStatus);
                    
                    // 2. SAĞDAKİ ÖZET TABLOSUNU ANINDA GÜNCELLE (Eğer sayfada bir özet durumu gösteren badge varsa)
                    const $statusBadge = $('.user-info-table').find('td .badge');
                    
                    $statusBadge.removeClass('badge-success badge-danger').addClass('badge-' + newClass);
                    $statusBadge.text(newStatusTurkish);

                    // Başarı mesajı göster (Kullanıcıya bildirim vermek için)
                    // İstenirse Toastr veya başka bir kütüphane kullanılabilir.
                    // alert('Kullanıcı durumu başarıyla güncellendi: ' + newStatusTurkish); 
                    
                } else {
                    // Controller'dan dönen hata mesajını göster
                    alert('Hata: ' + response.message);
                }
            },
            error: function() {
                // Sunucuya ulaşılamazsa veya 500/400 gibi hatalar alınırsa
                alert('Sunucuya bağlanırken bir hata oluştu veya yetkiniz yok. Lütfen tekrar deneyin.');
            },
            complete: function() {
                // İşlem (başarılı veya hatalı) bitince butonu tekrar etkinleştir
                $button.prop('disabled', false);
            }
        });
    });
});