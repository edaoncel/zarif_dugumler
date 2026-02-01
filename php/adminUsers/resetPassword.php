<?php session_start(); ?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Yenileme</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="../../css/adminUsers/adminUsers.css">
</head>
<body>
    
    <div class="login-page-container">
        
        <div class="image-header">
            <div class="image-overlay"></div>
        </div>
        
        <div class="form-content-area">
            
            <div id="messageArea" class="message" style="display:none;"></div>

            <div id="codeEntryDiv">
                <h1 class="welcome-text">Şifre Yenileme</h1>
                <p style="text-align: center; color: #555;">Lütfen e-postanıza gelen 6 haneli kodu giriniz.</p>
                <div class="form-box">
                    <form id="verifyCodeForm" action="../../controller/adminUsers/passwordResetController.php?action=verifycode" method="POST">
                        <div class="input-group">
                            <label for="code">6 Haneli Kod</label>
                            <input type="text" id="code" name="code" required maxlength="6" pattern="\d{6}">
                        </div>
                        <button type="submit" id="verifyCodeButton" class="btn">Kodu Doğrula</button>
                    </form>
                </div>
            </div>
            
            <div id="passwordFormDiv" style="display:none;">
                </div>
            
        </div>
    </div>
    
    <script src="../../js/adminUsers/resetPassword.js"></script>
</body>
</html>