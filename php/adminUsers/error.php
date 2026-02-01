<?php
http_response_code(404);
?>

<style>
.page_404 { 
    padding: 40px 0; 
    background: #fff; 
    font-family: 'Arial', sans-serif;
}

.four_zero_four_bg {
    background-image: url(https://cdn.dribbble.com/users/285475/screenshots/2083086/dribbble_1.gif);
    height: 400px;
    background-position: center;
    background-repeat: no-repeat
}

.four_zero_four_bg h1 {
    font-size: 80px;
    text-align: center;
    color: #f8f8f8;
    opacity: 0.1;
}

.contant_box_404 { 
    margin-top: -50px;
    text-align: center;
}

.contant_box_404 h3 {
    font-size: 28px;
    margin-bottom: 15px;
}

.contant_box_404 p {
    font-size: 16px;
    color: #6c757d;
    margin-bottom: 25px;
}
         
.link_404 {          
    color: #fff!important;
    padding: 10px 20px;
    background: #0b4426;
    margin: 20px 0;
    display: inline-block;
    text-decoration: none;
    border-radius: 5px;
}

.link_404:hover {
    background: #2e8b26;
}

.four-oh-four-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: calc(100vh - 100px);
        text-align: center;
        padding: 20px;
    }

    .four-oh-four-content {
        background-color: #ffffff;
        padding: 40px 30px;
        border-radius: 15px;
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        max-width: 450px;
        width: 100%;
        transition: transform 0.3s ease-in-out;
    }
    
    .four-oh-four-code {
        font-size: 8rem;
        font-weight: 700;
        color: #ff6b6b;
        line-height: 1;
        margin-bottom: 5px;
    }

    .four-oh-four-icon {
        color: #ff6b6b;
        font-size: 3rem;
        margin-bottom: 20px;
    }

    .four-oh-four-content h1 {
        font-size: 1.8rem;
        color: #495057;
        margin-bottom: 15px;
    }

    .four-oh-four-content p {
        font-size: 1rem;
        margin-bottom: 25px;
        line-height: 1.6;
        color: #6c757d;
    }


@media (max-width: 768px) {
    .four_zero_four_bg {
        height: 250px;
        background-size: cover;
    }
    .four_zero_four_bg h1 {
        font-size: 60px;
    }
}

</style>


<section class="page_404">
            <div class="col-sm-12 text-center">
                <div class="four_zero_four_bg">
                    <div class="four-oh-four-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                <div class="contant_box_404">
                    <h3 class="h2"><div class="four-oh-four-code">404</div>Sayfa bulunamadı!</h3>
                    <a href="?page=home" class="link_404">Anasayfaya Git</a>
                </div>
            </div>
</section>