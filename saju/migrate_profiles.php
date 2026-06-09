<?php
/**
 * DB 마이그레이션: saju_profiles 테이블 생성
 */
$pdo = new PDO('mysql:host=localhost;dbname=saju_db;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
]);

$pdo->exec("
CREATE TABLE IF NOT EXISTS `saju_profiles` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `profile_name` VARCHAR(50) NOT NULL COMMENT '프로필 이름 (예: 본인, 아버지, 어머니)',
    `birth_year` INT NOT NULL,
    `birth_month` INT NOT NULL,
    `birth_day` INT NOT NULL,
    `birth_hour` INT DEFAULT NULL COMMENT '태어난 시 (0-23)',
    `gender` ENUM('male', 'female') NOT NULL,
    `calendar_type` ENUM('solar', 'lunar') DEFAULT 'solar',
    `is_default` TINYINT(1) DEFAULT 0 COMMENT '기본 프로필 여부',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_user` (`user_id`),
    INDEX `idx_default` (`user_id`, `is_default`),
    FOREIGN KEY (`user_id`) REFERENCES `saju_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

echo "saju_profiles table created successfully.\n";

// Verify
$cols = $pdo->query("SHOW COLUMNS FROM saju_profiles")->fetchAll(PDO::FETCH_COLUMN);
echo "Columns: " . implode(', ', $cols) . "\n";
