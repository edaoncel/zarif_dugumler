document.addEventListener('DOMContentLoaded', function() {
    const newImagesInput = document.getElementById('new_product_images');
    const currentImagesList = document.getElementById('current-images-list');
    const productEditForm = document.getElementById('productEditForm');

    /**
     * Renk adını alıp bir slug oluşturur (Türkçe karakter ve boşlukları temizler).
     * @param {string} text 
     * @returns {string} Slug
     */
    function createSlug(text) {
        if (!text) return '';
        // Türkçe karakterleri çevir
        let slug = text.toLowerCase().trim();
        slug = slug.replace(/ı/g, 'i')
                   .replace(/ğ/g, 'g')
                   .replace(/ü/g, 'u')
                   .replace(/ş/g, 's')
                   .replace(/ö/g, 'o')
                   .replace(/ç/g, 'c');
        // Boşlukları tire ile değiştir ve alfanumerik olmayanları kaldır
        slug = slug.replace(/\s+/g, '-').replace(/[^a-z0-9-]/g, '');
        return slug;
    }

    // Renk grubu HTML yapısını oluşturan fonksiyon
    function createColorGroupContainer(colorSlug, colorName, hexCode, imageUrl, imageId) {
        
        // Slug'ı sanitize et (küçük harf, trim)
        const sanitizedSlug = createSlug(colorName);

        // Aynı sluga ait mevcut bir grup var mı kontrol et (eskiler + yeni eklenenler)
        let groupContainer = document.querySelector(`.color-group-container[data-color-slug="${sanitizedSlug}"]`);
        
        // Mevcut bir grup yoksa yeni bir grup oluştur
        if (!groupContainer) {
            
            groupContainer = document.createElement('div');
            groupContainer.className = 'color-group-container card mb-4 new-image-group';
            groupContainer.setAttribute('data-color-slug', sanitizedSlug);
            
            // Renk grubunun kontrol paneli (Yeni inputları buraya ekledik)
            groupContainer.innerHTML = `
                <div class="card-body color-group-controls" style="padding: 15px;">
                    <div style="display: flex; gap: 20px; align-items: flex-start;">
                        
                        <div class="input-control-item" style="flex-basis: 25%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Renk Adı:</label>
                            <input type="text" class="group-color-name-input form-control form-control-sm" 
                                value="${colorName}" 
                                placeholder="Örn: Kırmızı" required>
                        </div>
                        
                        <div class="input-control-item" style="flex-basis: 15%; display: flex; flex-direction: column;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Hex Kodu:</label>
                            <input type="color" class="group-color-hex-input form-control form-control-sm form-control-color" 
                                value="${hexCode}" 
                                title="Renk Seçici" style="height: 35px;">
                        </div>
                        
                        <div class="input-control-item" style="flex-basis: 30%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Toplu Stok (Yeni Varyantlar İçin):</label>
                            <div style="display: flex; gap: 5px;">
                                <input type="number" min="0" class="group-new-stock-input form-control form-control-sm"
                                    placeholder="Stok Adedi" value="1" required> 
                            </div>
                        </div>

                        <div class="input-control-item" style="flex-basis: 30%;">
                            <label class="form-label mb-1" style="font-size: 0.8em; font-weight: 600;">Bedenler (Virgülle Ayırın):</label>
                            <input type="text" 
                                class="group-sizes-input form-control form-control-sm" 
                                placeholder="Örn: S, M, L, 36, 38"
                                value="" required>
                            <small class="text-danger mt-1" style="font-size: 0.7em; display: block;">Bu, bu renge ait yeni varyantların oluşturulmasını sağlar.</small>
                        </div>
                    </div>
                </div>
                <div class="images-in-group-list image-sortable-list card-footer" style="display: flex; flex-wrap: wrap; gap: 10px; padding: 15px; border-top: 1px solid #ddd; background-color: #f7f7f7;">
                </div>
            `;
            // Yeni grubu mevcut listeye ekle
            currentImagesList.appendChild(groupContainer);
            
            // Eğer görsel yok mesajı varsa kaldır
            const noImageMessage = document.getElementById('no-image-message');
            if (noImageMessage) {
                noImageMessage.style.display = 'none';
            }
        }

        // Görsel öğesini oluştur
        const imageItem = document.createElement('div');
        imageItem.className = 'image-item shadow-sm new-image-item';
        imageItem.setAttribute('data-id', imageId); // Geçici ID
        imageItem.style.cssText = 'width: 100px; height: 100px; overflow: hidden; position: relative; border: 1px solid #ccc; border-radius: 4px; background-color: #fff;';

        imageItem.innerHTML = `
            <img src="${imageUrl}" style="width: 100%; height: 100%; object-fit: cover; display: block;">
            
            <span class="delete-new-image-btn" data-id="${imageId}" title="Yeni Görseli Sil"
                style="position: absolute; top: -8px; right: -8px; cursor: pointer; color: white; background: #dc3545; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 2px solid white; box-shadow: 0 0 5px rgba(0,0,0,0.5);">
                <i class="fas fa-times" style="font-size: 0.8em;"></i>
            </span>
            
            <input type="hidden" class="image-id-input" value="${imageId}">
            <input type="hidden" class="image-sortOrder-input" value="0">
            <input type="hidden" class="image-is-new-input" name="new_image_data[${imageId}][is_new]" value="1">
            <input type="hidden" class="image-color-slug-input" name="new_image_data[${imageId}][color_slug]" value="${sanitizedSlug}">
        `;

        // Görseli gruba ekle
        const imageGroupList = groupContainer.querySelector('.images-in-group-list');
        imageGroupList.appendChild(imageItem);
        
        // Renk/Slug inputlarındaki değişiklikleri izleme (Sadece yeni oluşturulan gruplar için 1 kere eklenmeli)
        if (groupContainer.classList.contains('new-image-group') && !groupContainer.dataset.listenersAdded) {
            const colorNameInput = groupContainer.querySelector('.group-color-name-input');
            
            colorNameInput.addEventListener('input', function() {
                const newColorName = this.value;
                const newSlug = createSlug(newColorName);

                // Ana grubun slug attribute'ünü güncelle
                groupContainer.setAttribute('data-color-slug', newSlug);
                
                // Bu gruptaki tüm yeni görsellerin hidden color_slug inputunu güncelle
                groupContainer.querySelectorAll('.image-color-slug-input').forEach(input => {
                    input.value = newSlug;
                });
            });
            groupContainer.dataset.listenersAdded = 'true'; // Tekrar dinleyici eklemeyi engelle
        }


        // Yeni görsel silme butonu listener'ı
        imageItem.querySelector('.delete-new-image-btn').addEventListener('click', function() {
            // Görseli DOM'dan kaldır
            imageItem.remove();
            
            // Eğer grup içinde başka görsel kalmadıysa, grubu da kaldır
            if (imageGroupList.children.length === 0) {
                groupContainer.remove();
                
                // Eğer hiç görsel kalmadıysa 'görsel yok' mesajını göster
                if (currentImagesList.children.length === 0) {
                    const noImageMessage = document.getElementById('no-image-message');
                    if (noImageMessage) {
                        noImageMessage.style.display = 'block';
                    }
                }
            }
        });
    }

    // Yeni dosyalar yüklendiğinde
    newImagesInput.addEventListener('change', function(event) {
        const files = event.target.files;
        
        // Varsayılan başlangıç değerleri
        const defaultColorName = 'Yeni Renk';
        const defaultHexCode = '#cccccc';
        
        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            const tempImageId = `new-img-${Date.now()}-${i}`; // Geçici ID

            const reader = new FileReader();
            reader.onload = function(e) {
                createColorGroupContainer(defaultColorName, defaultColorName, defaultHexCode, e.target.result, tempImageId);
            };
            reader.readAsDataURL(file);
        }
        // Input'un içindeki FileList'i sıfırlamazsak, aynı isimli dosyayı tekrar seçtiğimizde 'change' event'ı tetiklenmez.
        this.value = ''; 
    });

    // Form gönderildiğinde: Tüm varyant gruplarının (eskiler + yeniler) güncel verilerini topla
    productEditForm.addEventListener('submit', function() {
        
        // ... (Eski silinen görselleri toplama kısmı burada devam edebilir)
        
        // 2. Tüm renk gruplarından (eski + yeni) verileri topla
        let allVariantGroupsData = {};

        document.querySelectorAll('.color-group-container').forEach(group => {
            const colorNameInput = group.querySelector('.group-color-name-input');
            const colorHexCodeInput = group.querySelector('.group-color-hex-input');
            const stockInput = group.querySelector('.group-new-stock-input');
            const sizesInput = group.querySelector('.group-sizes-input');

            // Kontrol: Inputların var olduğundan emin olun (eski ve yeni gruplarda olabilir)
            if (!colorNameInput || !colorHexCodeInput || !stockInput || !sizesInput) {
                // Eğer bir grup eksikse (örneğin sadece görseli kalmış eski bir grup) atla
                return;
            }

            const colorName = colorNameInput.value;
            const colorHexCode = colorHexCodeInput.value;
            const stock = stockInput.value;
            const sizesString = sizesInput.value;

            // Renk adı boşsa uyarı ver ve gönderimi durdur
            if (!colorName.trim()) {
                alert("Lütfen tüm yeni renk grupları için renk adı girin.");
                event.preventDefault(); // Form gönderimini durdur
                colorNameInput.focus();
                return;
            }
            
            // Yeni slug'ı oluştur (Eğer inputtan geliyorsa)
            const colorSlug = createSlug(colorName);

            // Temizleme ve ayrıştırma
            const sizesArray = sizesString.split(',').map(s => s.trim()).filter(s => s.length > 0);
            
            // Anahtarı standartlaştırılmış slug olan nesneyi oluştur
            allVariantGroupsData[colorSlug] = {
                color: colorName,
                colorSlug: colorSlug,
                colorHexCode: colorHexCode,
                stock: stock,
                sizes: sizesArray
            };
        });
        
        // Toplanan veriyi gizli bir JSON input'una ekle
        let hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'all_variant_groups_data';
        hiddenInput.value = JSON.stringify(allVariantGroupsData);
        productEditForm.appendChild(hiddenInput);

        // Not: Controller tarafında, bu 'all_variant_groups_data' değişkenini alarak
        // stok matrisinizi güncellemeniz, yeni varyantları oluşturmanız ve
        // yeni görselleri (new_product_images[] ve new_image_data[]) 
        // bu verideki renk slug'larına göre kaydetmeniz gerekecektir.
    });
});