// ======================================================================
// 1. Şifre Göster/Gizle İşlevi (Global Kapsamda)
// ======================================================================
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

// ======================================================================
// 2. Silme İşlemi Onayı ve POST ile Gönderimi (Global Kapsamda)
// Bu fonksiyon HTML'deki onclick olayından çağrılır ve bu JS dosyası 
// düzgün yüklenirse hata ÇÖZÜLÜR.
// ======================================================================
function confirmDelete(id, name) {
    if (confirm("UYARI: " + name + " adlı kullanıcıyı kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!")) {
        // Silme işlemi için dinamik bir POST formu oluşturuluyor
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../../controller/adminUsers/usersManagementController.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_users';

        const idInput = document.createElement('input');
        idInput.type = 'hidden';
        idInput.name = 'id';
        idInput.value = id;

        form.appendChild(actionInput);
        form.appendChild(idInput);
        document.body.appendChild(form);
        form.submit();
    }
}

// ======================================================================
// 3. DOMContentLoaded: Belge Yüklendikten Sonra Çalışacak Ana Blok
// ======================================================================
document.addEventListener('DOMContentLoaded', function() {
    
    // YÖNETİCİ EKLEME FORMUNU DOĞRULAMA
    const addForm = document.getElementById('addUsersForm');
    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            
            const ad = document.getElementById('ad').value.trim();
            const soyad = document.getElementById('soyad').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            let hasError = false;
            let errorMessage = '';

            // Kontroller
            if (ad === '' || soyad === '' || email === '' || password === '' || passwordConfirm === '') {
                errorMessage += 'Lütfen tüm zorunlu alanları doldurun.\n';
                hasError = true;
            }
            if (password.length < 6) {
                errorMessage += 'Şifre en az 6 karakter olmalıdır.\n';
                hasError = true;
            }
            if (password !== passwordConfirm) {
                errorMessage += 'Şifreler eşleşmiyor.\n';
                hasError = true;
            }
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                errorMessage += 'Geçerli bir e-posta adresi giriniz.\n';
                hasError = true;
            }

            if (hasError) {
                e.preventDefault(); 
                alert('Hata:\n' + errorMessage);
            }
        });
    }

    // ŞİFRE SIFIRLAMA FORMUNU DOĞRULAMA (RESET PASSWORD FORM)
    const resetForm = document.getElementById('resetPassForm');
    if (resetForm) {
        resetForm.addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const newPassConfirm = document.getElementById('new_password_confirm').value;

            if (newPass.length < 6) {
                e.preventDefault();
                alert('Hata: Yeni şifre en az 6 karakter olmalıdır.');
            } else if (newPass !== newPassConfirm) {
                e.preventDefault();
                alert('Hata: Yeni şifreler eşleşmiyor.');
            } else if (!confirm('UYARI: Kullanıcının şifresini gerçekten sıfırlamak istiyor musunuz?')) {
                e.preventDefault();
            }
        });
    }

    // MESAJLARI (ALERTS) 3 SANİYE SONRA KAPATMA
    const alerts = document.querySelectorAll('.alert-danger, .alert-success');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            alert.style.transition = 'opacity 0.5s ease-out';
            setTimeout(function() {
                alert.remove();
            }, 500); 
        }, 3000); 
    });
});