document.addEventListener('DOMContentLoaded', function() {
    // Sayfa yüklendiğinde bir şey yapmamız gerekmiyorsa boş bırakabiliriz,
    // ancak genellikle buraya uyarı/bildirim yöneticisi kodları eklenir.
});

/**
 * Favori kaydını silmek için AJAX isteği gönderir.
 * @param {number} favoriteId Silinecek favori kaydının ID'si.
 */
function removeFavorite(favoriteId) {
    if (!confirm('Bu favori kaydını silmek istediğinizden emin misiniz?')) {
        return; // Kullanıcı işlemi iptal etti
    }

    const formData = new FormData();
    formData.append('action', 'delete');
    formData.append('favorite_id', favoriteId);

    fetch('../../controller/adminUsers/favoritesController.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            // Sunucudan 4xx veya 5xx hatası gelirse
            throw new Error(`HTTP Hata kodu: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.status === 'success') {
            alert(data.message); // Başarı mesajı göster
            // Başarılı olursa tablo satırını DOM'dan kaldır
            const rowToRemove = document.querySelector(`#favoritesTableBody tr[data-favorite-id='${favoriteId}']`);
            if (rowToRemove) {
                rowToRemove.remove();
            }
        } else {
            alert('Hata: ' + data.message); // Hata mesajı göster
        }
    })
    .catch(error => {
        console.error('Silme işleminde bir hata oluştu:', error);
        alert('İşlem sırasında beklenmeyen bir hata oluştu.');
    });
}