<?php
session_start();
// Yönetici giriş yapmışsa, admin.php'ye yönlendir
if (isset($_SESSION['admin_loggedIn']) && $_SESSION['admin_loggedIn'] === true) {
    header("Location: admin.php"); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yönetici Giriş</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="../../css/adminUsers/adminUsers.css">  
</head>

<body>
    <div class="login-page-container">
        <div class="image-header">
            <div class="image-overlay"></div>
        </div>
        
        <div class="form-content-area">
            <h1 class="welcome-text">Yönetici Giriş</h1>

            <div class="form-box">
                <form id="adminForm" action="../../controller/adminUsers/adminUsersController.php?action=login" method="POST">
                    
                    <div class="input-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" placeholder="Yönetici e-posta adresinizi giriniz" required>
                    </div>

                    <div class="input-group password-group">
                        <label for="password">Şifre</label>
                        <input type="password" id="password" name="password" placeholder="Şifrenizi giriniz" required>
                        
                        <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    
                    <div class="links-and-remember">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Beni Hatırla</label>
                        </div>
                        
                        <a href="forgotPassword.php">Şifremi Unuttum</a> 
                    </div>

                    <button type="submit" id="submitButton" class="btn">Giriş Yap</button>

                </form>
            </div>
        </div>
    </div>
    
    <script src="../../js/adminUsers/adminUsers.js"></script>
</body>
</html>