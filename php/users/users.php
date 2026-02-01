<?php
session_start();
if (isset($_SESSION['user_loggedIn']) && $_SESSION['user_loggedIn'] === true) {
    header("Location: home.php"); 
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Giriş</title> 
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="../../css/users/users.css">     
</head>

<body>
    <div class="login-page-container"> <div class="image-header">
            <div class="image-overlay"></div> </div>
        
        <div class="form-content-area"> <h1 class="welcome-text">Kullanıcı Giriş</h1> <div class="form-box">
                <form id="userForm" action="../../controller/users/usersController.php?action=login" method="POST"> 
                    
                    <div class="input-group">
                        <label for="email">E-mail</label> <input type="email" id="email" name="email" placeholder="E-mailinizi giriniz" required> </div>

                    <div class="input-group password-group">
                        <label for="password">Şifre</label> <input type="password" id="password" name="password" placeholder="Şifrenizi giriniz" required> <span class="toggle-password" onclick="togglePasswordVisibility()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </span>
                    </div>
                    
                    <div class="links-and-remember">
                        <div class="remember-me">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Beni Hatırla</label>
                        </div>
                        
                        <a href="forgotPassword.php">Şifremi Unuttum</a> </div>

                    <button type="submit" id="submitButton" class="btn">Giriş</button> <div style="text-align: center; margin-top: 20px;">
                        <p>Hesabınız yok mu? <a href="register.php">Kayıt Ol</a></p> </div>

                </form>
            </div>
        </div>
    </div>
    
    <script src="../../js/users/users.js"></script>
</body>
</html>