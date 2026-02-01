document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('forgotPasswordForm');
    const submitButton = document.getElementById('submitButton');
    const messageArea = document.getElementById('messageArea');

    function displayMessage(message, type) {
        messageArea.textContent = message;
        messageArea.className = (type === 'success') ? 'message success' : 'message error';
    }
    form.addEventListener('submit', function(e) {
        e.preventDefault(); 
        
        displayMessage('', '');
        

        const formData = new FormData(form);
        const actionUrl = form.action;

        submitButton.disabled = true;
        submitButton.textContent = 'İşleniyor...'; 
        
        fetch(actionUrl, {
            method: 'POST',
            body: formData 
        })
        .then(response => {
            submitButton.disabled = false;
            submitButton.textContent = 'Sıfırlama Kodu Gönder';
            
            if (!response.ok) {
                return response.json().then(errorData => { 
                    throw new Error(errorData.hata || 'Bilinmeyen bir sunucu hatası oluştu.');
                });
            }
            return response.json();
        })
        .then(data => {
            displayMessage(data.mesaj, 'success');
            form.reset(); 
            
            if (data.redirect_url) {
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 3000); 
            }

        })
        .catch(error => {
            displayMessage(error.message, 'error');
            console.error('İşlem Hatası:', error);
        });
    });
});