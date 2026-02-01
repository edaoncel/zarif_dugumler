<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Şifremi Unuttum</title>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    
    <link rel="stylesheet" href="../../css/users/users.css">
</head>
<body>
    
    <div class="login-page-container">
        
        <div class="image-header">
            <div class="image-overlay"></div>
        </div>
        
        <div class="form-content-area">
            <h1 class="welcome-text">Kullanıcı Şifrenizi Mi Unuttunuz?</h1>

            <div class="form-box">
                
                <p id="infoText" style="color: #666; margin-bottom: 30px; line-height: 1.4; font-size: 0.95em;">
                    Lütfen hesabınızla ilişkili e-posta adresinizi girin. Size şifrenizi sıfırlamanız için bir e-posta göndereceğiz.
                </p>
                <p id="messageArea" class="message error"></p>

                <form id="forgotPasswordForm" action="../../controller/users/passwordResetController.php?action=request" method="POST">
                    
                    <div class="input-group">
                        <label for="email">E-posta Adresiniz</label>
                        <input type="email" id="email" name="email" placeholder="e-posta@adresiniz.com" required>
                    </div>

                    <button type="submit" id="submitButton" class="btn">Sıfırlama Kodu Gönder</button>
                    
                    <div style="text-align: center; margin-top: 25px;">
                        <a href="users.php">Giriş Sayfasına Geri Dön</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
    
    <script src="../../js/users/forgotPassword.js"></script>
</body>
</html>