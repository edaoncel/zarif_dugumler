document.addEventListener('DOMContentLoaded', function() {
    
    const loginForm = document.getElementById('adminForm');
    const registerForm = document.getElementById('adminRegisterForm');
    
    const currentForm = loginForm || registerForm; 
    
    if (!currentForm) {
        console.error("Hata: Sayfada 'adminForm' veya 'adminRegisterForm' ID'li bir form bulunamadı.");
        return; 
    }

    const passwordField = document.getElementById('password');
    const toggleIcon = document.getElementById('toggleIcon');
    const submitButton = document.getElementById('submitButton');
    const formBox = document.querySelector('.form-box'); 
    
    const action = new URL(currentForm.action).searchParams.get('action');
    const isLogin = (action === 'login'); 

    // =========================================================
    // 1. Şifre Göster/Gizle İşlevi
    // =========================================================
    window.togglePasswordVisibility = function() {
        if (!passwordField) return; 
        
        if (passwordField.type === 'password') {
            passwordField.type = 'text';
            if (toggleIcon) {
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            }
        } else {
            passwordField.type = 'password';
            if (toggleIcon) {
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    };
    
    // =========================================================
    // 2. Form Gönderimi (AJAX/Fetch) İşlevi
    // =========================================================
    currentForm.addEventListener('submit', function(e) { 
        e.preventDefault(); 
        
        if (!submitButton) {
            displayMessage('Form gönderme butonu bulunamadı.', 'error');
            return;
        }

        clearMessages(); 

        const formData = new FormData(currentForm);
        const actionUrl = currentForm.action; 

        submitButton.disabled = true;
        const buttonText = isLogin ? 'Giriş Yapılıyor...' : 'Kaydediliyor...';
        submitButton.textContent = buttonText;
        
        fetch(actionUrl, {
            method: 'POST',
            body: formData 
        })
        .then(async response => {
            submitButton.disabled = false;
            submitButton.textContent = isLogin ? 'Giriş Yap' : 'Kayıt Ol'; 
            
            let data;
            try {
                data = await response.json(); 
            } catch (e) {
                console.error('JSON Parse Hatası:', e);
                throw new Error('Sunucu Yanıtı Hatası: Lütfen PHP Controller dosyasını kontrol edin.');
            }

            if (!response.ok) {
                throw new Error(data.hata || 'Bilinmeyen bir sunucu hatası oluştu.');
            }

            return data;
        })
        .then(data => {
            displayMessage(data.mesaj, 'success');
            
            if (data.redirect_url) {
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 1500); 
            }
            
            if (!isLogin && data.basari) {
                currentForm.reset();
            }
        })
        .catch(error => {
            displayMessage(error.message, 'error');
            console.error('İşlem Hatası:', error);
        });
    });

    // =========================================================
    // 3. Mesaj Gösterme ve Temizleme İşlevleri
    // =========================================================
    function clearMessages() {
        const existingMessages = document.querySelectorAll('.message');
        existingMessages.forEach(msg => msg.remove());
    }

    function displayMessage(message, type) {
        if (!formBox) return;

        clearMessages(); 
        const msgDiv = document.createElement('div');
        msgDiv.classList.add('message', type);
        msgDiv.textContent = message;
        formBox.insertBefore(msgDiv, currentForm); 
    }
});