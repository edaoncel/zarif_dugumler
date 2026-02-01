document.addEventListener('DOMContentLoaded', () => {
    const toggleButton = document.querySelector('.hamburger-menu-toggle');
    const categoryNav = document.querySelector('.category-nav'); // Nav kapsayıcısı
    const categoryNavList = document.getElementById('category-list'); // UL etiketi

    // =================================================================
    // 1. ANA MENÜ (HAMBURGER) AÇMA/KAPAMA İŞLEVİ
    // =================================================================
    if (toggleButton && categoryNav && categoryNavList) {
        
        // Sayfa yüklendiğinde mobil görünümde max-height'ı 0 yap (Menüyü kapat)
        if (window.innerWidth < 768) {
            categoryNav.style.maxHeight = '0';
        }

        toggleButton.addEventListener('click', () => {
            if (window.innerWidth < 768) { // Mobil kontrolü
                const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
                
                toggleButton.setAttribute('aria-expanded', !isExpanded);

                if (!isExpanded) {
                    // Menüyü aç: categoryNavList'in (UL) gerçek yüksekliğini nav'a uygula
                    categoryNav.style.maxHeight = categoryNavList.scrollHeight + 'px'; 
                } else {
                    // Menüyü kapat
                    categoryNav.style.maxHeight = '0';
                }
                
                // Ek olarak: Ana menü kapanırken açık olan alt menüleri de kapat
                document.querySelectorAll('.category-item.active-menu').forEach(openItem => {
                    openItem.classList.remove('active-menu');
                    const openSubmenu = openItem.querySelector('.submenu');
                    if(openSubmenu) {
                        openSubmenu.style.maxHeight = '0';
                    }
                });
            }
        });
    }
    
    // =================================================================
    // 2. ALT MENÜ AÇMA/KAPAMA İŞLEVİ (Hiyerarşi)
    // =================================================================
    document.querySelectorAll('.has-submenu > a').forEach(item => {
        item.addEventListener('click', function(e) {
            if (window.innerWidth < 768) {
                e.preventDefault(); 
                const listItem = this.closest('.category-item');
                const submenu = listItem.querySelector('.submenu');
                const navContainer = categoryNav; // Ana menü kapsayıcısı

                // Alt menü durumunu değiştir
                if (listItem.classList.contains('active-menu')) {
                    listItem.classList.remove('active-menu');
                    submenu.style.maxHeight = '0';
                } else {
                    // Diğer açık alt menüleri kapat
                    document.querySelectorAll('.category-item.active-menu').forEach(openItem => {
                        openItem.classList.remove('active-menu');
                        const openSubmenu = openItem.querySelector('.submenu');
                        if(openSubmenu) {
                            openSubmenu.style.maxHeight = '0';
                        }
                    });
                    // Mevcut alt menüyü aç
                    listItem.classList.add('active-menu');
                    submenu.style.maxHeight = submenu.scrollHeight + 'px';
                }
                
                // Alt menü açılıp kapandıktan sonra Ana Menünün yüksekliğini YENİLE
                if (navContainer && navContainer.style.maxHeight !== '0px') {
                    // Sadece ana menü açıksa (maxHeight 0 değilse) yüksekliği güncelle
                    navContainer.style.maxHeight = categoryNavList.scrollHeight + 'px';
                }
            }
        });
    });

    // =================================================================
    // 3. ÜRÜN GÖRSEL KAYDIRMA (SLIDER) VE RENK SEÇİMİ İŞLEVİ
    // =================================================================
    document.querySelectorAll('.product-card').forEach(card => {
        const slider = card.querySelector('.image-slider');
        const prevBtn = card.querySelector('.prev-btn');
        const nextBtn = card.querySelector('.next-btn');
        const slides = card.querySelectorAll('.slide-item');
        
        if (!slider || slides.length === 0) return; // Slider yoksa veya resim yoksa çık

        let currentIndex = 0;
        
        /**
         * Kaydırıcıyı günceller ve aktif görseli gösterir.
         * @param {number} index - Gösterilecek görselin indeksi.
         */
        function updateSlider(index) {
            currentIndex = index;
            // Kaydırma (transform) miktarını ayarla
            const offset = -currentIndex * 100; 
            slider.style.transform = `translateX(${offset}%)`;

            // Ek: transform kullanıldığında 'active' class'ı gerekli olmayabilir. 
            // Ancak, CSS'te bu class'ı kullanıyorsanız (örn: ilk resmi göstermek için), 
            // class yönetimini de bu fonksiyonda yapmalısınız.
            slides.forEach((slide, i) => {
                slide.classList.toggle('active', i === currentIndex);
            });
        }
        
        // İlk resmi aktif olarak işaretle (CSS'te transform yoksa ve sadece active class'ı ile gösteriliyorsa)
        if (slides.length > 0) {
             updateSlider(0);
        }

        // Önceki butonu
        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault(); 
                currentIndex = (currentIndex === 0) ? slides.length - 1 : currentIndex - 1;
                updateSlider(currentIndex);
            });
        }

        // Sonraki butonu
        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault(); 
                currentIndex = (currentIndex === slides.length - 1) ? 0 : currentIndex + 1;
                updateSlider(currentIndex);
            });
        }
        
        // ==========================================================
        // RENK SEÇİMİ VE GÖRSEL DEĞİŞTİRME İŞLEVİ (YENİ)
        // ==========================================================
        const colorCircles = card.querySelectorAll('.color-circle');

        colorCircles.forEach(circle => {
            circle.addEventListener('click', (e) => {
                e.preventDefault(); 

                const targetColorSlug = circle.getAttribute('data-color-slug');

                if (targetColorSlug) {
                    // Renk slug'ı ile eşleşen görseli bul
                    // querySelector, eşleşen ilk elementi döndürür.
                    const targetSlide = card.querySelector(`.slide-item[data-color-slug="${targetColorSlug}"]`);
                    
                    if (targetSlide) {
                        // Eşleşen görselin indeksini bul
                        let targetIndex = Array.from(slides).indexOf(targetSlide);
                        
                        if (targetIndex !== -1) {
                            // Kaydırıcıyı o indekse taşı
                            updateSlider(targetIndex);
                        }
                    } else {
                        console.warn(`[Ürün ${card.dataset.productId}]: Renk slug'ı (${targetColorSlug}) için görsel bulunamadı.`);
                    }
                }
            });
        });
    });
});