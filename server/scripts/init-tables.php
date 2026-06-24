<?php

/**
 * Database Table Initialization Script
 * Creates all necessary tables if they don't exist
 */

require_once __DIR__ . '/../config/db.php';

echo "🔧 Starting database table initialization...\n\n";

$database = new Database();
$conn = $database->getConnection();

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error . "\n");
}

echo "✅ Database connection established\n\n";

$tables = [
    'users' => "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `hash` VARCHAR(21) UNIQUE NOT NULL,
        `first_name` VARCHAR(50) NOT NULL,
        `last_name` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(255) UNIQUE NOT NULL,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_email` (`email`),
        INDEX `idx_hash` (`hash`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'stores' => "CREATE TABLE IF NOT EXISTS `stores` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `store_id` VARCHAR(21) UNIQUE NOT NULL,
        `user_id` INT NOT NULL,
        `name` VARCHAR(100) NOT NULL,
        `description` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_store_id` (`store_id`),
        INDEX `idx_user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'brands' => "CREATE TABLE IF NOT EXISTS `brands` (
        `brand_id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) UNIQUE NOT NULL,
        `description` TEXT,
        `logo_url` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_name` (`name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'categories_nested' => "CREATE TABLE IF NOT EXISTS `categories_nested` (
        `category_id` VARCHAR(36) PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `slug` VARCHAR(120) UNIQUE NOT NULL,
        `lft` INT NOT NULL,
        `rgt` INT NOT NULL,
        `level` INT NOT NULL DEFAULT 0,
        `parent_id` VARCHAR(36) DEFAULT NULL,
        `icon` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_lft` (`lft`),
        INDEX `idx_rgt` (`rgt`),
        INDEX `idx_parent` (`parent_id`),
        INDEX `idx_slug` (`slug`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'subcategories' => "CREATE TABLE IF NOT EXISTS `subcategories` (
        `subcategory_id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_groups' => "CREATE TABLE IF NOT EXISTS `product_groups` (
        `group_id` VARCHAR(36) PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_group_attributes' => "CREATE TABLE IF NOT EXISTS `product_group_attributes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_id` VARCHAR(36) NOT NULL,
        `attribute_name` VARCHAR(100) NOT NULL,
        FOREIGN KEY (`group_id`) REFERENCES `product_groups`(`group_id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_group_attribute` (`group_id`, `attribute_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'products' => "CREATE TABLE IF NOT EXISTS `products` (
        `id` VARCHAR(36) PRIMARY KEY,
        `psku` VARCHAR(25) UNIQUE NOT NULL,
        `user_id` INT,
        `store_id` VARCHAR(21),
        `category_id` VARCHAR(36),
        `subcategory_id` INT,
        `brand_id` INT,
        `group_id` VARCHAR(36),
        `name` VARCHAR(255) NOT NULL,
        `price` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(4) DEFAULT 'USD',
        `stock` INT DEFAULT 0,
        `is_returnable` BOOLEAN DEFAULT FALSE,
        `is_public` BOOLEAN DEFAULT FALSE,
        `final_price` DECIMAL(10, 2),
        `product_overview` TEXT,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_psku` (`psku`),
        INDEX `idx_category` (`category_id`),
        INDEX `idx_brand` (`brand_id`),
        INDEX `idx_group` (`group_id`),
        INDEX `idx_name` (`name`),
        INDEX `idx_public` (`is_public`),
        FOREIGN KEY (`category_id`) REFERENCES `categories_nested`(`category_id`) ON DELETE SET NULL,
        FOREIGN KEY (`brand_id`) REFERENCES `brands`(`brand_id`) ON DELETE SET NULL,
        FOREIGN KEY (`group_id`) REFERENCES `product_groups`(`group_id`) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_images' => "CREATE TABLE IF NOT EXISTS `product_images` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(36) NOT NULL,
        `image_url` VARCHAR(512) NOT NULL,
        `is_primary` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_specifications' => "CREATE TABLE IF NOT EXISTS `product_specifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(36) NOT NULL,
        `spec_name` VARCHAR(100) NOT NULL,
        `spec_value` TEXT NOT NULL,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'product_attribute_values' => "CREATE TABLE IF NOT EXISTS `product_attribute_values` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(36) NOT NULL,
        `attribute_name` VARCHAR(100) NOT NULL,
        `attribute_value` VARCHAR(255) NOT NULL,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_product` (`product_id`),
        INDEX `idx_attribute` (`attribute_name`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'discounts' => "CREATE TABLE IF NOT EXISTS `discounts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(36) NOT NULL,
        `type` ENUM('percentage', 'fixed') NOT NULL,
        `value` DECIMAL(10, 2) NOT NULL,
        `starts_at` TIMESTAMP NULL,
        `ends_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'coupons' => "CREATE TABLE IF NOT EXISTS `coupons` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `code` VARCHAR(50) UNIQUE NOT NULL,
        `type` ENUM('percentage', 'fixed') NOT NULL,
        `value` DECIMAL(10, 2) NOT NULL,
        `min_order_amount` DECIMAL(10, 2) DEFAULT NULL,
        `max_uses` INT DEFAULT NULL,
        `used_count` INT DEFAULT 0,
        `starts_at` TIMESTAMP NULL,
        `ends_at` TIMESTAMP NULL,
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_code` (`code`),
        INDEX `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'carts' => "CREATE TABLE IF NOT EXISTS `carts` (
        `id` VARCHAR(21) PRIMARY KEY,
        `user_id` INT DEFAULT NULL,
        `guest_cart_id` VARCHAR(50) DEFAULT NULL,
        `is_guest_cart` BOOLEAN DEFAULT FALSE,
        `expires_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_guest` (`guest_cart_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'cart_items' => "CREATE TABLE IF NOT EXISTS `cart_items` (
        `id` VARCHAR(21) PRIMARY KEY,
        `cart_id` VARCHAR(21) NOT NULL,
        `product_id` VARCHAR(36) NOT NULL,
        `quantity` INT NOT NULL DEFAULT 1,
        `price` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(4) DEFAULT 'USD',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`cart_id`) REFERENCES `carts`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_cart` (`cart_id`),
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wishlists' => "CREATE TABLE IF NOT EXISTS `wishlists` (
        `id` VARCHAR(21) PRIMARY KEY,
        `user_id` INT NOT NULL,
        `name` VARCHAR(255) NOT NULL,
        `is_private` BOOLEAN DEFAULT FALSE,
        `is_default` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'wishlist_items' => "CREATE TABLE IF NOT EXISTS `wishlist_items` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `wishlist_id` VARCHAR(21) NOT NULL,
        `product_id` VARCHAR(36) NOT NULL,
        `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`wishlist_id`) REFERENCES `wishlists`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_wishlist_product` (`wishlist_id`, `product_id`),
        INDEX `idx_wishlist` (`wishlist_id`),
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'orders' => "CREATE TABLE IF NOT EXISTS `orders` (
        `id` VARCHAR(21) PRIMARY KEY,
        `user_id` INT NOT NULL,
        `total_amount` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(4) DEFAULT 'USD',
        `status` ENUM('placed', 'processing', 'confirmed', 'dispatched', 'delivered', 'cancelled') DEFAULT 'placed',
        `payment_status` ENUM('unpaid', 'paid', 'refunded') DEFAULT 'unpaid',
        `shipping_address` TEXT NOT NULL,
        `payment_method` VARCHAR(50) NOT NULL,
        `stripe_session_id` VARCHAR(255) DEFAULT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_status` (`status`),
        INDEX `idx_payment_status` (`payment_status`),
        UNIQUE INDEX `idx_stripe_session` (`stripe_session_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'order_items' => "CREATE TABLE IF NOT EXISTS `order_items` (
        `id` VARCHAR(21) PRIMARY KEY,
        `order_id` VARCHAR(21) NOT NULL,
        `product_id` VARCHAR(36) NOT NULL,
        `quantity` INT NOT NULL,
        `price` DECIMAL(10, 2) NOT NULL,
        `currency` VARCHAR(4) DEFAULT 'USD',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_order` (`order_id`),
        INDEX `idx_product` (`product_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'order_tracking' => "CREATE TABLE IF NOT EXISTS `order_tracking` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `order_id` INT NOT NULL,
        `tracking_number` VARCHAR(50) UNIQUE NOT NULL,
        `status` VARCHAR(50) DEFAULT 'processing',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_order` (`order_id`),
        INDEX `idx_tracking` (`tracking_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    // Reviews table
    'reviews' => "CREATE TABLE IF NOT EXISTS `reviews` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `product_id` VARCHAR(36) NOT NULL,
        `user_id` INT NOT NULL,
        `rating` INT NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
        `title` VARCHAR(255),
        `comment` TEXT,
        `is_verified_purchase` BOOLEAN DEFAULT FALSE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        INDEX `idx_product` (`product_id`),
        INDEX `idx_user` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'review_helpful_votes' => "CREATE TABLE IF NOT EXISTS `review_helpful_votes` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `review_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `is_helpful` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`review_id`) REFERENCES `reviews`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        UNIQUE KEY `unique_review_user` (`review_id`, `user_id`),
        INDEX `idx_review` (`review_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'browsing_history' => "CREATE TABLE IF NOT EXISTS `browsing_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `product_id` VARCHAR(36) NOT NULL,
        `viewed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
        INDEX `idx_user` (`user_id`),
        INDEX `idx_product` (`product_id`),
        INDEX `idx_viewed` (`viewed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'banners' => "CREATE TABLE IF NOT EXISTS `banners` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `image_url` VARCHAR(512) NOT NULL,
        `link_url` VARCHAR(512),
        `is_active` BOOLEAN DEFAULT TRUE,
        `display_order` INT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_active` (`is_active`),
        INDEX `idx_order` (`display_order`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'partners' => "CREATE TABLE IF NOT EXISTS `partners` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `logo_url` VARCHAR(255),
        `website_url` VARCHAR(255),
        `description` TEXT,
        `is_active` BOOLEAN DEFAULT TRUE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_active` (`is_active`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

    'tracking_details' => "CREATE TABLE IF NOT EXISTS `tracking_details` (
        `id` VARCHAR(36) PRIMARY KEY,
        `order_id` VARCHAR(21) NOT NULL,
        `tracking_number` VARCHAR(100) NOT NULL,
        `carrier` VARCHAR(100),
        `status` VARCHAR(50),
        `estimated_delivery` TIMESTAMP NULL,
        `current_location` VARCHAR(255),
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX `idx_order` (`order_id`),
        INDEX `idx_tracking` (`tracking_number`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
];

$successCount = 0;
$failureCount = 0;
$errors = [];

foreach ($tables as $tableName => $query) {
    echo "Creating table: $tableName... ";

    if ($conn->query($query) === TRUE) {
        echo "✅ SUCCESS\n";
        $successCount++;
    } else {
        echo "❌ FAILED\n";
        $error = "Error creating table $tableName: " . $conn->error;
        echo "   $error\n";
        $errors[] = $error;
        $failureCount++;
    }
}

// Only close connection if running standalone (not included)
if (basename($_SERVER['SCRIPT_FILENAME']) === 'init-tables.php') {
    $conn->close();
}

echo "\n" . str_repeat("=", 60) . "\n";
echo "📊 Summary:\n";
echo "   Total tables: " . count($tables) . "\n";
echo "   ✅ Successful: $successCount\n";
echo "   ❌ Failed: $failureCount\n";

if ($failureCount > 0) {
    echo "\n❌ Errors encountered:\n";
    foreach ($errors as $error) {
        echo "   - $error\n";
    }
} else {
    echo "\n🎉 All tables created successfully!\n";
}

