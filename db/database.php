<?php
include_once "db/db.php";
// php db/database.php terminal de bu kodu çalıştırısan veritabanında işlemler olur.
function execute($numara, $query) {
    global $pdo;
    try {
        $pdo->exec($query);
        echo "✅ Sorgu #$numara çalıştı: $query\n";
    } catch (PDOException $e) {
        echo "❌ Hata #$numara: " . $e->getMessage() . "\n";
    }
}

execute(1, "CREATE TABLE IF NOT EXISTS adminUsers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(100) NULL,
    soyad VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    passwordResetToken VARCHAR(255) NULL,
    passwordResetExperies DATETIME NULL
)");

execute(2, "CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(100) NOT NULL,
    soyad VARCHAR(100) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    passwordHash VARCHAR(255) NOT NULL,
    passwordResetToken VARCHAR(255) NULL,
    passwordResetExperies DATETIME NULL
)");

execute(3, "CREATE TABLE IF NOT EXISTS product (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    categoryId INT NOT NULL,
    subCategoryId INT NULL,
    gender VARCHAR(50) NULL,
    size VARCHAR(255) NULL,
    color VARCHAR(50) NULL,
    material VARCHAR(100) NULL,
    price DECIMAL(10, 2) NOT NULL,
    newPrice DECIMAL(10, 2) NULL,
    imageUrl VARCHAR(255) NULL,
    isDeleted BOOLEAN DEFAULT 0, 
    description TEXT NULL
)");

execute(4, "CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    sortOrder INT NOT NULL DEFAULT 0
)");

execute(5, "CREATE TABLE IF NOT EXISTS settings (
    id INT PRIMARY KEY DEFAULT 1,
    marka VARCHAR(100) NULL,
    contactText TEXT NULL,
    aboutText TEXT NULL
)");

execute(6, "CREATE TABLE IF NOT EXISTS filters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
)");

execute(7, "CREATE TABLE IF NOT EXISTS subCategories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoryId INT NOT NULL, 
    name VARCHAR(100) NOT NULL,
    sortOrder INT NOT NULL DEFAULT 0,
    UNIQUE KEY category_sub_name (categoryId, name),
    FOREIGN KEY (categoryId) REFERENCES categories(id) ON DELETE CASCADE
)");

execute(8, "CREATE TABLE IF NOT EXISTS favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    userId INT NOT NULL,
    productId INT NOT NULL,
    UNIQUE KEY user_product_unique (userId, productId),
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE, 
    FOREIGN KEY (productId) REFERENCES product(id) ON DELETE CASCADE
)");

execute(9, "CREATE TABLE IF NOT EXISTS categoryFilters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    categoryId INT NOT NULL,
    subCategoryId INT NULL,
    filterId INT NOT NULL,
    FOREIGN KEY (categoryId) REFERENCES categories(id) ON DELETE CASCADE,
    FOREIGN KEY (subCategoryId) REFERENCES subCategories(id) ON DELETE CASCADE,
    FOREIGN KEY (filterId) REFERENCES filters(id) ON DELETE CASCADE
)");

execute(10, "CREATE TABLE IF NOT EXISTS productFilterValues (
    id INT AUTO_INCREMENT PRIMARY KEY,
    productId INT NOT NULL,
    filterId INT NOT NULL,
    value VARCHAR(100) NOT NULL,
    UNIQUE KEY product_filter_value_unique (productId, filterId, value),
    FOREIGN KEY (productId) REFERENCES product(id) ON DELETE CASCADE,
    FOREIGN KEY (filterId) REFERENCES filters(id) ON DELETE CASCADE
)");

execute(11, "CREATE TABLE admin_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    selector CHAR(32) UNIQUE NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    FOREIGN KEY (admin_id) REFERENCES adminusers(id) ON DELETE CASCADE
)");

execute(12, "CREATE TABLE user_auth_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    selector CHAR(32) UNIQUE NOT NULL,
    validator_hash CHAR(64) NOT NULL,
    expires DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)");

execute(13, "ALTER TABLE adminUsers
ADD COLUMN adminPhoto VARCHAR(255) NULL;
");

execute(14, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (1, 'Kadın', 1)");
execute(15, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (2, 'Çocuk & Bebek', 2)");
execute(16, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (3, 'Ayakkabı', 3)");
execute(17, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (4, 'Ev Tekstili', 4)");
execute(18, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (5, 'Çanta', 5)");
execute(19, "INSERT IGNORE INTO categories (id, name, sortOrder) VALUES (6, 'Şapka & Aksesuar', 6)");

execute(20, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (1, 'Elbise')");
execute(21, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (1, 'Pantolon & Şort')");
execute(22, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (1, 'Etek')");
execute(23, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (1, 'Tulum')");
execute(24, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (1, 'Ceket & Kaban')");

execute(25, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Elbise')");
execute(26, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Pantolon & Şort')");
execute(27, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Etek')");
execute(28, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Tulum')");
execute(29, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Ceket & Kaban')");
execute(29, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (2, 'Battaniye')");

execute(30, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (4, 'Uyku Setleri')");
execute(31, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (4, 'Çeyiz Takımları')");
execute(32, "INSERT IGNORE INTO subCategories (categoryId, name) VALUES (4, 'Perde')");

execute(33, "CREATE TABLE IF NOT EXISTS filterOptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filterId INT NOT NULL,
    valueName VARCHAR(100) NOT NULL,
    sortOrder INT NOT NULL DEFAULT 0,
    UNIQUE KEY filter_value_unique (filterId, valueName),
    FOREIGN KEY (filterId) REFERENCES filters(id) ON DELETE CASCADE
)");

execute(34, "CREATE TABLE IF NOT EXISTS orders ( 
    id INT AUTO_INCREMENT PRIMARY KEY, 
    userId INT NOT NULL, 
    totalAmount DECIMAL(10, 2) NOT NULL, 
    status VARCHAR(50) NOT NULL DEFAULT 'Pending', 
    orderDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (userId) REFERENCES users(id) ON DELETE RESTRICT
)"); 

execute(35, "CREATE TABLE IF NOT EXISTS orderitems ( 
    id INT AUTO_INCREMENT PRIMARY KEY, 
    orderId INT NOT NULL, 
    productId INT NOT NULL, 
    quantity INT NOT NULL, 
    price DECIMAL(10, 2) NOT NULL, 
    FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (productId) REFERENCES product(id) ON DELETE RESTRICT
)");

execute(36, "ALTER TABLE users ADD COLUMN status ENUM('active', 'passive') NOT NULL DEFAULT 'active';");
execute(37, "ALTER TABLE users ADD COLUMN userPhoto VARCHAR(255) NULL;");

execute(38, "ALTER TABLE favorites DROP FOREIGN KEY favorites_ibfk_1;"); 
execute(39, "ALTER TABLE user_auth_tokens DROP FOREIGN KEY user_auth_tokens_ibfk_1;");
execute(40, "ALTER TABLE orders DROP FOREIGN KEY orders_ibfk_1;"); 

execute(41, "ALTER TABLE users MODIFY id INT(11) UNSIGNED NOT NULL AUTO_INCREMENT;");

execute(42, "ALTER TABLE favorites MODIFY userId INT(11) UNSIGNED NOT NULL;");
execute(43, "ALTER TABLE user_auth_tokens MODIFY user_id INT(11) UNSIGNED NOT NULL;");
execute(44, "ALTER TABLE orders MODIFY userId INT(11) UNSIGNED NOT NULL;");

execute(45, "CREATE TABLE IF NOT EXISTS address (
    id INT(11) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    userId INT(11) UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL COMMENT 'Ev, İş, vb.',
    fullname VARCHAR(150) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    city VARCHAR(100) NOT NULL,
    district VARCHAR(100) NOT NULL,
    addressDetail TEXT NOT NULL COMMENT 'Cadde, sokak, no, daire',
    zipCode VARCHAR(10) NULL,
    isDefault TINYINT(1) DEFAULT 0 COMMENT 'Varsayılan adres mi?',
    createdAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updatedAt TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    CONSTRAINT fk_address_user
        FOREIGN KEY (userId) 
        REFERENCES users(id) 
        ON DELETE CASCADE 
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

execute(46, "ALTER TABLE favorites ADD CONSTRAINT favorites_ibfk_1 FOREIGN KEY (userId) REFERENCES users(id) ON DELETE CASCADE ON UPDATE CASCADE;");
execute(47, "ALTER TABLE user_auth_tokens ADD CONSTRAINT user_auth_tokens_ibfk_1 FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE;");
execute(48, "ALTER TABLE orders ADD CONSTRAINT orders_ibfk_1 FOREIGN KEY (userId) REFERENCES users(id) ON DELETE RESTRICT;");

execute(49, "ALTER TABLE orders
    ADD COLUMN addressId INT(11) UNSIGNED NULL COMMENT 'Teslimat adresi referansı',
    ADD CONSTRAINT fk_order_address
        FOREIGN KEY (addressId) 
        REFERENCES address(id) 
        ON DELETE SET NULL 
        ON UPDATE CASCADE;
");

execute(50, "ALTER TABLE orders
    ADD COLUMN paymentMethod VARCHAR(50) NOT NULL DEFAULT 'CreditCard' COMMENT 'Ödeme Yöntemi (CreditCard, Transfer, COD, vb.)',
    ADD COLUMN shippingCompany VARCHAR(100) NULL COMMENT 'Kargo Firması Adı',
    ADD COLUMN trackingNumber VARCHAR(100) NULL COMMENT 'Kargo Takip Numarası',
    ADD COLUMN invoiceNote TEXT NULL COMMENT 'Fatura için özel not (Yönetici Tarafından)',
    ADD COLUMN shippingNote TEXT NULL COMMENT 'Kargo fişi için özel not (Yönetici Tarafından)'
");

execute(51, "CREATE TABLE IF NOT EXISTS payments ( 
    id INT AUTO_INCREMENT PRIMARY KEY, 
    orderId INT NOT NULL, 
    pspTransactionId VARCHAR(255) NULL COMMENT 'Ödeme Sağlayıcısının İşlem IDsi', 
    paymentToken VARCHAR(255) NULL COMMENT 'Tekrarlayan ödeme tokeni', 
    amount DECIMAL(10, 2) NOT NULL, 
    paymentStatus VARCHAR(50) NOT NULL, 
    paymentDate TIMESTAMP DEFAULT CURRENT_TIMESTAMP, 
    FOREIGN KEY (orderId) REFERENCES orders(id) ON DELETE RESTRICT
)");

execute(52, "ALTER TABLE product
ADD COLUMN shippingFee DECIMAL(10, 2) NOT NULL DEFAULT 0.00 
COMMENT 'Bu ürün için varsayılan kargo ücreti.'
");

execute(52, "INSERT INTO adminUsers (ad, soyad, email, passwordHash) 
VALUES ('Eda', 'Öncel', 'edaoncel15@gmail.com', '$2y$10$s.oNQfl07rYbKBINXnInq.H4B79se3xfDdDVNKscPCOotfu9ESEhi')
ON DUPLICATE KEY UPDATE 
    ad = VALUES(ad), 
    soyad = VALUES(soyad),
    passwordHash = VALUES(passwordHash);
");

execute(52, "INSERT INTO users (ad, soyad, email, passwordHash) 
VALUES ('Eda', 'Öncel', 'edaoncel15@gmail.com', '$2y$10$s.oNQfl07rYbKBINXnInq.H4B79se3xfDdDVNKscPCOotfu9ESEhi')
ON DUPLICATE KEY UPDATE 
    ad = VALUES(ad), 
    soyad = VALUES(soyad),
    passwordHash = VALUES(passwordHash);
");

execute(53, "ALTER TABLE product
ADD COLUMN mainSku VARCHAR(255) NULL
COMMENT 'Aynı ürüne ait varyantları gruplamak için kullanılır. NULL ise ana üründür.';
");

execute(54, "ALTER TABLE product MODIFY COLUMN mainSku VARCHAR(255) NOT NULL;");

execute(55, "ALTER TABLE product MODIFY COLUMN color VARCHAR(50) NOT NULL, MODIFY COLUMN description TEXT NOT NULL;");

execute(54, "
CREATE TABLE IF NOT EXISTS productVariants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    productId INT NOT NULL,
    color VARCHAR(50) NOT NULL COMMENT 'Varyant Rengi',
    size VARCHAR(255) NOT NULL COMMENT 'Varyant Bedeni/Numarası',
    stockQuantity INT NOT NULL DEFAULT 0, -- Bu varyantın stok adedi
    sku VARCHAR(100) UNIQUE NULL COMMENT 'Stok Tutma Birimi kodu (isteğe bağlı)',
    UNIQUE KEY product_color_size (productId, color, size), 
    FOREIGN KEY (productId) REFERENCES product(id) ON DELETE CASCADE
);
");

execute(56, "
CREATE TABLE IF NOT EXISTS productImages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  productId INT NOT NULL,
  imageUrl VARCHAR(255) NOT NULL,
  sortOrder INT NOT NULL DEFAULT 0 COMMENT 'Görselin ürün sayfasındaki sıralaması',
  FOREIGN KEY (productId) REFERENCES product(id) ON DELETE CASCADE
);
");

execute(57, "ALTER TABLE product DROP COLUMN size;");

execute(58, "ALTER TABLE product DROP COLUMN color;");

execute(59, "ALTER TABLE product DROP COLUMN imageUrl;"); 

execute(60, "ALTER TABLE product MODIFY COLUMN mainSku VARCHAR(255) NULL COMMENT 'Ürünün ana SKU kodu.';");

execute(61, "ALTER TABLE product MODIFY COLUMN description TEXT NULL;");

execute(62, "ALTER TABLE orderitems 
    ADD COLUMN variantId INT NULL COMMENT 'Sipariş edilen ürünün varyant IDsi',
    ADD CONSTRAINT fk_orderitem_variant
        FOREIGN KEY (variantId) 
        REFERENCES productVariants(id) 
        ON DELETE RESTRICT 
        ON UPDATE CASCADE;
");

execute(63, "ALTER TABLE productVariants
    ADD COLUMN colorHexCode VARCHAR(7) NULL;
");

execute(64, "ALTER TABLE productVariants 
ADD COLUMN colorSlug VARCHAR(100) NULL AFTER color;
");

execute(65, "
CREATE TABLE IF NOT EXISTS productImages (
 id INT AUTO_INCREMENT PRIMARY KEY,
 productId INT NOT NULL,
 imageUrl VARCHAR(255) NOT NULL,
 sortOrder INT NOT NULL DEFAULT 0 COMMENT 'Görselin ürün sayfasındaki sıralaması',
 FOREIGN KEY (productId) REFERENCES product(id) ON DELETE CASCADE
);
");

execute(66, "ALTER TABLE productImages ADD COLUMN colorSlug VARCHAR(100) NULL COMMENT 'Görselin ait olduğu renk varyantı slugı';");