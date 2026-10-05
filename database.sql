-- Warehouse Stock: schema generated from Laravel migrations; demo stock is recorded as IN.
-- Import into an EMPTY database. Does not drop existing data.
-- No user/password is seeded: run php artisan warehouse:admin after import.
-- Compatible: MySQL >=8.0.16 / MariaDB >=10.6, InnoDB.
SET NAMES utf8mb4;

CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `username` varchar(80) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_username_unique` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `warehouse_mutexes` (
  `id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `warehouse_mutexes` (`id`) VALUES ('1');

CREATE TABLE `categories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_name_unique` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('1','Elektronik','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('2','Peralatan','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('3','Kabel','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('4','Plastik','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('5','ATK','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `categories` (`id`,`name`,`active`,`created_at`,`updated_at`) VALUES ('6','Sparepart','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `rooms` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `code` varchar(30) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rooms_name_unique` (`name`),
  UNIQUE KEY `rooms_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rooms` (`id`,`name`,`code`,`active`,`created_at`,`updated_at`) VALUES ('1','Ruangan 1','R1','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `blocks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `room_id` bigint(20) unsigned NOT NULL,
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `blocks_room_id_code_unique` (`room_id`,`code`),
  CONSTRAINT `blocks_room_id_foreign` FOREIGN KEY (`room_id`) REFERENCES `rooms` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `blocks` (`id`,`room_id`,`code`,`name`,`active`,`created_at`,`updated_at`) VALUES ('1','1','A','Blok A','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `blocks` (`id`,`room_id`,`code`,`name`,`active`,`created_at`,`updated_at`) VALUES ('2','1','B','Blok B','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `racks` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `block_id` bigint(20) unsigned NOT NULL,
  `rack_number` smallint(5) unsigned NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `racks_block_id_rack_number_unique` (`block_id`,`rack_number`),
  CONSTRAINT `racks_block_id_foreign` FOREIGN KEY (`block_id`) REFERENCES `blocks` (`id`),
  CONSTRAINT `chk_rack_number` CHECK (`rack_number` between 1 and 999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `racks` (`id`,`block_id`,`rack_number`,`name`,`active`,`created_at`,`updated_at`) VALUES ('1','1','1','Rak 01','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `racks` (`id`,`block_id`,`rack_number`,`name`,`active`,`created_at`,`updated_at`) VALUES ('2','1','2','Rak 02','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `racks` (`id`,`block_id`,`rack_number`,`name`,`active`,`created_at`,`updated_at`) VALUES ('3','2','1','Rak 01','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `rack_positions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `rack_id` bigint(20) unsigned NOT NULL,
  `position_number` smallint(5) unsigned NOT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rack_positions_rack_id_position_number_unique` (`rack_id`,`position_number`),
  CONSTRAINT `rack_positions_rack_id_foreign` FOREIGN KEY (`rack_id`) REFERENCES `racks` (`id`),
  CONSTRAINT `chk_position_number` CHECK (`position_number` between 1 and 999)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('1','1','1','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('2','1','2','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('3','1','3','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('4','2','1','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('5','2','2','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `rack_positions` (`id`,`rack_id`,`position_number`,`active`,`created_at`,`updated_at`) VALUES ('6','3','1','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `products` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `product_code` varchar(60) NOT NULL,
  `name` varchar(150) NOT NULL,
  `specification` varchar(150) DEFAULT NULL,
  `category_id` bigint(20) unsigned NOT NULL,
  `unit` varchar(20) NOT NULL,
  `stock` int(10) unsigned NOT NULL DEFAULT 0,
  `rack_position_id` bigint(20) unsigned NOT NULL,
  `qr_token` char(36) NOT NULL,
  `moving_type` enum('FAST','SLOW') DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `active_position_id` bigint(20) unsigned GENERATED ALWAYS AS (case when `active` = 1 then `rack_position_id` else NULL end) STORED,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `products_product_code_unique` (`product_code`),
  UNIQUE KEY `products_qr_token_unique` (`qr_token`),
  UNIQUE KEY `products_active_position_id_unique` (`active_position_id`),
  KEY `products_category_id_foreign` (`category_id`),
  KEY `products_rack_position_id_foreign` (`rack_position_id`),
  KEY `products_name_index` (`name`),
  KEY `products_moving_type_index` (`moving_type`),
  KEY `products_active_index` (`active`),
  CONSTRAINT `products_category_id_foreign` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  CONSTRAINT `products_rack_position_id_foreign` FOREIGN KEY (`rack_position_id`) REFERENCES `rack_positions` (`id`),
  CONSTRAINT `chk_stock_range` CHECK (`stock` between 0 and 2147483647)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `products` (`id`,`product_code`,`name`,`specification`,`category_id`,`unit`,`stock`,`rack_position_id`,`qr_token`,`moving_type`,`image`,`description`,`active`,`created_at`,`updated_at`) VALUES ('1','A0001','Ember','1 KG','4','PCS','25','1','51db379e-dcc7-48ca-a86f-4f4827b2f079','FAST',NULL,'Contoh data gudang','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `products` (`id`,`product_code`,`name`,`specification`,`category_id`,`unit`,`stock`,`rack_position_id`,`qr_token`,`moving_type`,`image`,`description`,`active`,`created_at`,`updated_at`) VALUES ('2','A0002','Ember','1.2 KG','4','PCS','2','2','1bdff79d-4dbe-4af9-86fa-13f55ac4334c','FAST',NULL,'Contoh data gudang','1','2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `products` (`id`,`product_code`,`name`,`specification`,`category_id`,`unit`,`stock`,`rack_position_id`,`qr_token`,`moving_type`,`image`,`description`,`active`,`created_at`,`updated_at`) VALUES ('3','A0003','Ember','2 KG','4','PCS','0','3','2ea2db4f-37f2-4813-8440-5606b332ee19','SLOW',NULL,'Contoh data gudang','1','2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `transaction_sequences` (
  `sequence_date` date NOT NULL,
  `type` varchar(3) NOT NULL,
  `last_number` bigint(20) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`sequence_date`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `transaction_sequences` (`sequence_date`,`type`,`last_number`) VALUES ('2026-10-02','IN','2');

CREATE TABLE `stock_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `transaction_number` varchar(40) NOT NULL,
  `product_id` bigint(20) unsigned NOT NULL,
  `rack_position_id` bigint(20) unsigned NOT NULL,
  `location_snapshot` varchar(100) NOT NULL,
  `room_snapshot` varchar(100) NOT NULL,
  `product_code_snapshot` varchar(60) NOT NULL,
  `name_snapshot` varchar(150) NOT NULL,
  `specification_snapshot` varchar(150) DEFAULT NULL,
  `unit_snapshot` varchar(20) NOT NULL,
  `transaction_type` enum('IN','OUT','ADJUSTMENT') NOT NULL,
  `quantity` int(11) DEFAULT NULL,
  `stock_before` int(10) unsigned DEFAULT NULL,
  `stock_after` int(10) unsigned DEFAULT NULL,
  `status` enum('PENDING','APPROVED','CANCELLED') NOT NULL DEFAULT 'PENDING',
  `pending_product_id` bigint(20) unsigned GENERATED ALWAYS AS (case when `status` = 'PENDING' then `product_id` else NULL end) STORED,
  `scanned_at` datetime DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `cancelled_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) unsigned DEFAULT NULL,
  `processed_by` bigint(20) unsigned DEFAULT NULL,
  `request_key` char(36) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `stock_transactions_transaction_number_unique` (`transaction_number`),
  UNIQUE KEY `stock_transactions_pending_product_id_unique` (`pending_product_id`),
  UNIQUE KEY `stock_transactions_request_key_unique` (`request_key`),
  KEY `stock_transactions_rack_position_id_foreign` (`rack_position_id`),
  KEY `stock_transactions_created_by_foreign` (`created_by`),
  KEY `stock_transactions_processed_by_foreign` (`processed_by`),
  KEY `stock_transactions_product_id_transaction_type_status_index` (`product_id`,`transaction_type`,`status`),
  KEY `stock_transactions_transaction_type_index` (`transaction_type`),
  KEY `stock_transactions_status_index` (`status`),
  KEY `stock_transactions_scanned_at_index` (`scanned_at`),
  KEY `stock_transactions_approved_at_index` (`approved_at`),
  CONSTRAINT `stock_transactions_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_transactions_processed_by_foreign` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`),
  CONSTRAINT `stock_transactions_product_id_foreign` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  CONSTRAINT `stock_transactions_rack_position_id_foreign` FOREIGN KEY (`rack_position_id`) REFERENCES `rack_positions` (`id`),
  CONSTRAINT `chk_approved_values` CHECK (`status` <> 'APPROVED' or `quantity` is not null and `stock_before` is not null and `stock_after` is not null and `approved_at` is not null and `stock_after` between 0 and 2147483647 and (`transaction_type` = 'IN' and `quantity` > 0 and `stock_after` = `stock_before` + `quantity` or `transaction_type` = 'OUT' and `quantity` > 0 and `stock_before` = `stock_after` + `quantity` or `transaction_type` = 'ADJUSTMENT' and `stock_after` = `stock_before` + `quantity`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `stock_transactions` (`id`,`transaction_number`,`product_id`,`rack_position_id`,`location_snapshot`,`room_snapshot`,`product_code_snapshot`,`name_snapshot`,`specification_snapshot`,`unit_snapshot`,`transaction_type`,`quantity`,`stock_before`,`stock_after`,`status`,`scanned_at`,`approved_at`,`cancelled_at`,`notes`,`created_by`,`processed_by`,`request_key`,`created_at`,`updated_at`) VALUES ('1','IN-20261002-0001','1','1','A.01.01','Ruangan 1','A0001','Ember','1 KG','PCS','IN','25','0','25','APPROVED','2026-10-02 13:24:46','2026-10-02 13:24:46',NULL,'Stok demo melalui transaksi IN (bukan input stok awal).',NULL,NULL,NULL,'2026-10-02 13:24:46','2026-10-02 13:24:46');
INSERT INTO `stock_transactions` (`id`,`transaction_number`,`product_id`,`rack_position_id`,`location_snapshot`,`room_snapshot`,`product_code_snapshot`,`name_snapshot`,`specification_snapshot`,`unit_snapshot`,`transaction_type`,`quantity`,`stock_before`,`stock_after`,`status`,`scanned_at`,`approved_at`,`cancelled_at`,`notes`,`created_by`,`processed_by`,`request_key`,`created_at`,`updated_at`) VALUES ('2','IN-20261002-0002','2','2','A.01.02','Ruangan 1','A0002','Ember','1.2 KG','PCS','IN','2','0','2','APPROVED','2026-10-02 13:24:46','2026-10-02 13:24:46',NULL,'Stok demo melalui transaksi IN (bukan input stok awal).',NULL,NULL,NULL,'2026-10-02 13:24:46','2026-10-02 13:24:46');

CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES ('1','0001_01_01_000000_create_users_table','1');
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES ('2','0001_01_01_000001_create_cache_table','1');
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES ('3','0001_01_01_000002_create_jobs_table','1');
INSERT INTO `migrations` (`id`,`migration`,`batch`) VALUES ('4','2026_10_02_000001_create_warehouse_tables','1');

