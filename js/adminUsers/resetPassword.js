function togglePasswordVisibility(passwordId, iconId) {
    const passwordInput = document.getElementById(passwordId);
    const toggleIcon = document.getElementById(iconId);

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash');
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye');
    }
}

document.addEventListener('DOMContentLoaded', function() {

    const codeEntryDiv = document.getElementById('codeEntryDiv');
    const passwordFormDiv = document.getElementById('passwordFormDiv');
    const verifyCodeForm = document.getElementById('verifyCodeForm');
    const messageArea = document.getElementById('messageArea');


    function displayMessage(message, type) {
        messageArea.textContent = message;
        messageArea.className = (type === 'success') ? 'message success' : 'message error';
    }


    // =========================================================
    // AŞAMA 1: KOD DOĞRULAMA İŞLEMİ
    // =========================================================
    if (verifyCodeForm) {
        verifyCodeForm.addEventListener('submit', function(e) {
            e.preventDefault();
            displayMessage('', '');

            const form = e.target;
            const formData = new FormData(form);
            const submitButton = document.getElementById('verifyCodeButton');
            
            submitButton.disabled = true;
            submitButton.textContent = 'Doğrulanıyor...';
            
            fetch(form.action, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                submitButton.disabled = false;
                submitButton.textContent = 'Kodu Doğrula';
                
                if (!response.ok) {
                    return response.json().then(errorData => {
                        throw new Error(errorData.hata || 'Bilinmeyen bir hata oluştu.');
                    });
                }
                return response.json();
            })
            .then(data => {
                displayMessage(data.mesaj, 'success');
                
                if (data.next_step === 'password_form') {
                    showNewPasswordForm(formData.get('code'));
                }
            })
            .catch(error => {
                displayMessage(error.message, 'error');
                console.error('Doğrulama Hatası:', error);
            });
        });
    }


    // =========================================================
    // AŞAMA 2: YENİ ŞİFRE FORMUNU GÖSTERME
    // =========================================================
    function showNewPasswordForm(code) {
        codeEntryDiv.style.display = 'none';
        passwordFormDiv.style.display = 'block';

        passwordFormDiv.innerHTML = `
            <h3>Yeni Şifrenizi Belirleyin</h3>
            <form id="resetPasswordForm" action="../../controller/adminUsers/passwordResetController.php?action=reset" method="POST">
                <input type="hidden" name="code" value="${code}">

                <div class="input-group password-group">
                    <label for="new_password">Yeni Şifre</label>
                    <input type="password" id="new_password" name="new_password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('new_password', 'newPasswordIcon')">
                        <i class="fas fa-eye" id="newPasswordIcon"></i>
                    </span>
                </div>

                <div class="input-group password-group">
                    <label for="confirm_password">Şifre Tekrar</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <span class="toggle-password" onclick="togglePasswordVisibility('confirm_password', 'confirmPasswordIcon')">
                        <i class="fas fa-eye" id="confirmPasswordIcon"></i>
                    </span>
                </div>

                <button type="submit" id="submitResetButton" class="btn">Şifreyi Değiştir</button>
            </form>
        `;

        document.getElementById('resetPasswordForm').addEventListener('submit', handlePasswordReset);
    }


    // =========================================================
    // AŞAMA 3: ŞİFRE SIFIRLAMA İŞLEMİNİ YÖNETME
    // =========================================================
    function handlePasswordReset(e) {
        e.preventDefault();
        displayMessage('', '');

        const form = e.target;
        const formData = new FormData(form);
        const submitButton = document.getElementById('submitResetButton');
        
        const newPassword = formData.get('new_password');
        const confirmPassword = formData.get('confirm_password');
        
        if (newPassword !== confirmPassword) {
            displayMessage('Girdiğiniz şifreler eşleşmiyor.', 'error');
            return;
        }
        
        submitButton.disabled = true;
        submitButton.textContent = 'Güncelleniyor...';

        fetch(form.action, {
            method: 'POST',
            body: formData
        })
        .then(response => {
            submitButton.disabled = false;
            submitButton.textContent = 'Şifreyi Değiştir';

            if (!response.ok) {
                return response.json().then(errorData => {
                    throw new Error(errorData.hata || 'Şifre sıfırlama başarısız oldu.');
                });
            }
            return response.json();
        })
        .then(data => {
            displayMessage(data.mesaj, 'success');
            
            if (data.redirect_url) {
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 1500);
            }
        })
        .catch(error => {
            displayMessage(error.message, 'error');
            console.error('Şifre Sıfırlama Hatası:', error);
        });
    }

});