// adminManagement.js dosya içeriği

// ======================================================================
// 1. Şifre Göster/Gizle İşlevi
// HTML'deki onclick="" olaylarından doğrudan çağrılabilir.
// ======================================================================
function togglePasswordVisibility(passwordId, iconId) {
    const passwordInput = document.getElementById(passwordId);
    const toggleIcon = document.getElementById(iconId);

    if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleIcon.classList.remove('fa-eye');
        toggleIcon.classList.add('fa-eye-slash'); // Göz kapalı ikonu
    } else {
        passwordInput.type = 'password';
        toggleIcon.classList.remove('fa-eye-slash');
        toggleIcon.classList.add('fa-eye'); // Göz açık ikonu
    }
}

// ======================================================================
// 2. Silme İşlemi Onayı ve POST ile Gönderimi
// ======================================================================
function confirmDelete(id, name) {
    if (confirm("UYARI: " + name + " adlı yöneticiyi kalıcı olarak silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!")) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '../../controller/adminUsers/adminManagementController.php';

        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete_admin';

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
// Form doğrulama ve mesaj kapatma işlemleri burada yapılır.
// ======================================================================
document.addEventListener('DOMContentLoaded', function() {
    
    // YÖNETİCİ EKLEME FORMUNU DOĞRULAMA (ADD ADMIN FORM)
    const addForm = document.getElementById('addAdminForm');

    if (addForm) {
        addForm.addEventListener('submit', function(e) {
            
            const ad = document.getElementById('ad').value.trim();
            const soyad = document.getElementById('soyad').value.trim();
            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;
            const passwordConfirm = document.getElementById('password_confirm').value;

            let hasError = false;
            let errorMessage = '';

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
            let hasResetError = false;
            let resetErrorMessage = '';

            if (newPass.length < 6) {
                resetErrorMessage += 'Yeni şifre en az 6 karakter olmalıdır.\n';
                hasResetError = true;
            } 
            if (newPass !== newPassConfirm) {
                resetErrorMessage += 'Yeni şifreler eşleşmiyor.\n';
                hasResetError = true;
            }
            
            if (hasResetError) {
                e.preventDefault();
                alert('Hata:\n' + resetErrorMessage);
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