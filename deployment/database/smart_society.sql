-- ========================================================
-- Smart Society Database Production Dump
-- Generated for Hostinger MySQL Deployment
-- Date: 2026-08-24 12:03:01
-- ========================================================

SET FOREIGN_KEY_CHECKS=0;
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

-- --------------------------------------------------------
-- Table structure for `amenities`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `amenities`;
CREATE TABLE `amenities` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `max_booking_hours` smallint(5) unsigned NOT NULL DEFAULT 2,
  `opening_time` time DEFAULT NULL,
  `closing_time` time DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `amenities_name_unique` (`name`),
  KEY `amenities_is_active_index` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `amenities`

INSERT INTO `amenities` VALUES 
(1, 'Clubhouse', 'Community hall for events and celebrations', 1, 2, NULL, NULL, '2026-08-23 16:18:33', '2026-08-23 16:18:33');

-- --------------------------------------------------------
-- Table structure for `amenity_bookings`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `amenity_bookings`;
CREATE TABLE `amenity_bookings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `amenity_id` bigint(20) unsigned NOT NULL,
  `resident_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `booking_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'CONFIRMED',
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `amenity_bookings_flat_id_foreign` (`flat_id`),
  KEY `amenity_bookings_amenity_id_booking_date_status_index` (`amenity_id`,`booking_date`,`status`),
  KEY `amenity_bookings_resident_id_booking_date_index` (`resident_id`,`booking_date`),
  KEY `amenity_bookings_booking_date_index` (`booking_date`),
  KEY `amenity_bookings_status_index` (`status`),
  CONSTRAINT `amenity_bookings_amenity_id_foreign` FOREIGN KEY (`amenity_id`) REFERENCES `amenities` (`id`) ON DELETE CASCADE,
  CONSTRAINT `amenity_bookings_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `amenity_bookings_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `cache`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `cache_locks`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` bigint(20) NOT NULL,
  PRIMARY KEY (`key`),
  KEY `cache_locks_expiration_index` (`expiration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `complaint_status_histories`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `complaint_status_histories`;
CREATE TABLE `complaint_status_histories` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `complaint_id` bigint(20) unsigned NOT NULL,
  `from_status` varchar(30) DEFAULT NULL,
  `to_status` varchar(30) NOT NULL,
  `changed_by_user_id` bigint(20) unsigned NOT NULL,
  `note` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `complaint_status_histories_changed_by_user_id_foreign` (`changed_by_user_id`),
  KEY `complaint_status_histories_complaint_id_created_at_index` (`complaint_id`,`created_at`),
  CONSTRAINT `complaint_status_histories_changed_by_user_id_foreign` FOREIGN KEY (`changed_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `complaint_status_histories_complaint_id_foreign` FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `complaints`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `complaints`;
CREATE TABLE `complaints` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resident_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `assigned_staff_id` bigint(20) unsigned DEFAULT NULL,
  `category` varchar(100) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `priority` varchar(30) NOT NULL DEFAULT 'MEDIUM',
  `status` varchar(30) NOT NULL DEFAULT 'OPEN',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `complaints_assigned_staff_id_foreign` (`assigned_staff_id`),
  KEY `complaints_flat_id_status_index` (`flat_id`,`status`),
  KEY `complaints_resident_id_created_at_index` (`resident_id`,`created_at`),
  KEY `complaints_priority_index` (`priority`),
  KEY `complaints_status_index` (`status`),
  CONSTRAINT `complaints_assigned_staff_id_foreign` FOREIGN KEY (`assigned_staff_id`) REFERENCES `staff_members` (`id`) ON DELETE SET NULL,
  CONSTRAINT `complaints_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `complaints_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `conversation_participants`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `conversation_participants`;
CREATE TABLE `conversation_participants` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `user_id` bigint(20) unsigned NOT NULL,
  `last_read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conversation_participants_conversation_id_user_id_unique` (`conversation_id`,`user_id`),
  KEY `conversation_participants_user_id_updated_at_index` (`user_id`,`updated_at`),
  CONSTRAINT `conversation_participants_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `conversation_participants_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `conversations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `conversations`;
CREATE TABLE `conversations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(30) NOT NULL DEFAULT 'DIRECT',
  `subject` varchar(255) DEFAULT NULL,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `conversations_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `conversations_type_index` (`type`),
  CONSTRAINT `conversations_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `failed_jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` varchar(255) NOT NULL,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`),
  KEY `failed_jobs_connection_queue_failed_at_index` (`connection`,`queue`,`failed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `flats`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `flats`;
CREATE TABLE `flats` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `flat_number` varchar(30) NOT NULL,
  `building` varchar(60) NOT NULL,
  `floor` varchar(30) DEFAULT NULL,
  `owner_user_id` bigint(20) unsigned DEFAULT NULL,
  `occupancy_status` varchar(20) NOT NULL DEFAULT 'VACANT',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `flats_building_flat_number_unique` (`building`,`flat_number`),
  KEY `flats_owner_user_id_foreign` (`owner_user_id`),
  KEY `flats_occupancy_status_index` (`occupancy_status`),
  CONSTRAINT `flats_owner_user_id_foreign` FOREIGN KEY (`owner_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `flats`

INSERT INTO `flats` VALUES 
(1, 101, 'Tower A', 1, NULL, 'OCCUPIED', '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(2, 102, 'Tower A', 1, NULL, 'OCCUPIED', '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(3, 201, 'Tower A', 2, NULL, 'OCCUPIED', '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(4, 202, 'Tower A', 2, NULL, 'VACANT', '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(5, 301, 'Tower B', 3, NULL, 'OCCUPIED', '2026-08-23 16:18:33', '2026-08-23 16:18:33');

-- --------------------------------------------------------
-- Table structure for `job_batches`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `job_batches`;
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

-- --------------------------------------------------------
-- Table structure for `jobs`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` smallint(5) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `maintenance_bills`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `maintenance_bills`;
CREATE TABLE `maintenance_bills` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `flat_id` bigint(20) unsigned NOT NULL,
  `billing_month` date NOT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `due_date` date NOT NULL,
  `base_maintenance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `water_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `electricity_common_area_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `parking_charge` decimal(12,2) NOT NULL DEFAULT 0.00,
  `other_charges` decimal(12,2) NOT NULL DEFAULT 0.00,
  `late_fee` decimal(12,2) NOT NULL DEFAULT 0.00,
  `discount` decimal(12,2) NOT NULL DEFAULT 0.00,
  `amount` decimal(12,2) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'UNPAID',
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `maintenance_bills_flat_id_billing_month_unique` (`flat_id`,`billing_month`),
  KEY `maintenance_bills_due_date_index` (`due_date`),
  KEY `maintenance_bills_status_index` (`status`),
  KEY `maintenance_bills_billing_month_status_index` (`billing_month`,`status`),
  CONSTRAINT `maintenance_bills_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `maintenance_payments`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `maintenance_payments`;
CREATE TABLE `maintenance_payments` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_order_id` bigint(20) unsigned DEFAULT NULL,
  `maintenance_bill_id` bigint(20) unsigned NOT NULL,
  `resident_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `payment_method` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `paid_at` timestamp NULL DEFAULT NULL,
  `receipt_reference` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `maintenance_payments_reference_id_unique` (`reference_id`),
  KEY `maintenance_payments_resident_id_created_at_index` (`resident_id`,`created_at`),
  KEY `maintenance_payments_maintenance_bill_id_status_index` (`maintenance_bill_id`,`status`),
  KEY `maintenance_payments_status_index` (`status`),
  KEY `maintenance_payments_payment_order_id_foreign` (`payment_order_id`),
  CONSTRAINT `maintenance_payments_maintenance_bill_id_foreign` FOREIGN KEY (`maintenance_bill_id`) REFERENCES `maintenance_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `maintenance_payments_payment_order_id_foreign` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`) ON DELETE SET NULL,
  CONSTRAINT `maintenance_payments_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `messages`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `messages`;
CREATE TABLE `messages` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `conversation_id` bigint(20) unsigned NOT NULL,
  `sender_id` bigint(20) unsigned NOT NULL,
  `body` text NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `messages_sender_id_foreign` (`sender_id`),
  KEY `messages_conversation_id_created_at_index` (`conversation_id`,`created_at`),
  CONSTRAINT `messages_conversation_id_foreign` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  CONSTRAINT `messages_sender_id_foreign` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `migrations`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=25 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `migrations`

INSERT INTO `migrations` VALUES 
(1, '0001_01_01_000000_create_users_table', 1),
(2, '0001_01_01_000001_create_cache_table', 1),
(3, '0001_01_01_000002_create_jobs_table', 1),
(4, '2026_08_08_114022_create_personal_access_tokens_table', 1),
(5, '2026_08_08_120000_create_flats_and_residents_tables', 1),
(6, '2026_08_09_120000_create_visitors_table', 1),
(7, '2026_08_09_120001_create_notifications_table', 1),
(8, '2026_08_09_130000_create_maintenance_bills_and_payments_tables', 1),
(9, '2026_08_09_140000_create_staff_and_complaints_tables', 1),
(10, '2026_08_09_141000_create_notices_table', 1),
(11, '2026_08_09_142000_create_parking_slots_table', 1),
(12, '2026_08_09_143000_create_amenities_and_bookings_tables', 1),
(13, '2026_08_09_144000_create_messaging_tables', 1),
(14, '2026_08_11_190000_create_parcels_table', 2),
(15, '2026_08_11_191000_create_payment_orders_tables', 2),
(16, '2026_08_11_192000_add_reconciliation_to_payment_orders', 2),
(17, '2026_08_11_193000_create_payment_receipts_table', 2),
(18, '2026_08_12_090000_add_profiles_and_staff_registration_fields', 2),
(19, '2026_08_12_100000_extend_maintenance_bills_with_charge_breakdown', 2),
(20, '2026_08_12_101000_extend_notices_for_publication_lifecycle', 2),
(21, '2026_08_12_165000_activate_demo_security_user', 2),
(22, '2026_08_13_120000_make_visitor_fields_nullable', 2),
(23, '2026_08_19_152541_create_resident_transaction_ledger_table', 2),
(24, '2026_08_19_152916_create_resident_transactions_table', 2);

-- --------------------------------------------------------
-- Table structure for `notices`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notices`;
CREATE TABLE `notices` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `created_by_user_id` bigint(20) unsigned NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `category` varchar(30) NOT NULL DEFAULT 'GENERAL',
  `priority` varchar(30) NOT NULL DEFAULT 'NORMAL',
  `audience` varchar(30) NOT NULL DEFAULT 'ALL',
  `is_published` tinyint(1) NOT NULL DEFAULT 0,
  `published_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `attachment_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notices_created_by_user_id_foreign` (`created_by_user_id`),
  KEY `notices_audience_index` (`audience`),
  KEY `notices_is_published_index` (`is_published`),
  KEY `notices_published_at_index` (`published_at`),
  KEY `notices_expires_at_index` (`expires_at`),
  CONSTRAINT `notices_created_by_user_id_foreign` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `notifications`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id` char(36) NOT NULL,
  `type` varchar(255) NOT NULL,
  `notifiable_type` varchar(255) NOT NULL,
  `notifiable_id` bigint(20) unsigned NOT NULL,
  `data` text NOT NULL,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `notifications_notifiable_type_notifiable_id_index` (`notifiable_type`,`notifiable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `parcels`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `parcels`;
CREATE TABLE `parcels` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `flat_id` bigint(20) unsigned NOT NULL,
  `resident_id` bigint(20) unsigned NOT NULL,
  `received_by_user_id` bigint(20) unsigned NOT NULL,
  `collected_by_user_id` bigint(20) unsigned DEFAULT NULL,
  `courier_name` varchar(150) NOT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `parcel_type` varchar(30) NOT NULL DEFAULT 'OTHER',
  `status` varchar(30) NOT NULL DEFAULT 'AWAITING_PICKUP',
  `received_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `collected_at` timestamp NULL DEFAULT NULL,
  `notes` varchar(500) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `parcels_received_by_user_id_foreign` (`received_by_user_id`),
  KEY `parcels_collected_by_user_id_foreign` (`collected_by_user_id`),
  KEY `parcels_resident_id_status_index` (`resident_id`,`status`),
  KEY `parcels_flat_id_received_at_index` (`flat_id`,`received_at`),
  KEY `parcels_tracking_number_index` (`tracking_number`),
  KEY `parcels_status_index` (`status`),
  KEY `parcels_received_at_index` (`received_at`),
  CONSTRAINT `parcels_collected_by_user_id_foreign` FOREIGN KEY (`collected_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `parcels_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parcels_received_by_user_id_foreign` FOREIGN KEY (`received_by_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parcels_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `parking_slots`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `parking_slots`;
CREATE TABLE `parking_slots` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `slot_number` varchar(50) NOT NULL,
  `parking_type` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'AVAILABLE',
  `flat_id` bigint(20) unsigned DEFAULT NULL,
  `vehicle_number` varchar(30) DEFAULT NULL,
  `vehicle_type` varchar(30) DEFAULT NULL,
  `vehicle_description` varchar(150) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parking_slots_slot_number_unique` (`slot_number`),
  KEY `parking_slots_flat_id_status_index` (`flat_id`,`status`),
  KEY `parking_slots_parking_type_index` (`parking_type`),
  KEY `parking_slots_status_index` (`status`),
  CONSTRAINT `parking_slots_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `password_reset_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payment_order_items`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `payment_order_items`;
CREATE TABLE `payment_order_items` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_order_id` bigint(20) unsigned NOT NULL,
  `maintenance_bill_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_order_items_payment_order_id_maintenance_bill_id_unique` (`payment_order_id`,`maintenance_bill_id`),
  KEY `payment_order_items_maintenance_bill_id_foreign` (`maintenance_bill_id`),
  CONSTRAINT `payment_order_items_maintenance_bill_id_foreign` FOREIGN KEY (`maintenance_bill_id`) REFERENCES `maintenance_bills` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_order_items_payment_order_id_foreign` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payment_orders`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `payment_orders`;
CREATE TABLE `payment_orders` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resident_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `payment_method` varchar(30) NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `provider` varchar(50) NOT NULL,
  `provider_reference` varchar(100) NOT NULL,
  `gateway_payment_id` varchar(150) DEFAULT NULL,
  `verification_note` text DEFAULT NULL,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_orders_provider_reference_unique` (`provider_reference`),
  UNIQUE KEY `payment_orders_gateway_payment_id_unique` (`gateway_payment_id`),
  KEY `payment_orders_flat_id_foreign` (`flat_id`),
  KEY `payment_orders_resident_id_status_index` (`resident_id`,`status`),
  KEY `payment_orders_status_index` (`status`),
  CONSTRAINT `payment_orders_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `payment_orders_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `payment_receipts`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `payment_receipts`;
CREATE TABLE `payment_receipts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `payment_order_id` bigint(20) unsigned NOT NULL,
  `receipt_number` varchar(100) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'INR',
  `payment_method` varchar(30) NOT NULL,
  `payment_reference` varchar(150) NOT NULL,
  `issued_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_receipts_payment_order_id_unique` (`payment_order_id`),
  UNIQUE KEY `payment_receipts_receipt_number_unique` (`receipt_number`),
  KEY `payment_receipts_issued_at_index` (`issued_at`),
  CONSTRAINT `payment_receipts_payment_order_id_foreign` FOREIGN KEY (`payment_order_id`) REFERENCES `payment_orders` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `personal_access_tokens`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tokenable_type` varchar(255) NOT NULL,
  `tokenable_id` bigint(20) unsigned NOT NULL,
  `name` text NOT NULL,
  `token` varchar(64) NOT NULL,
  `abilities` text DEFAULT NULL,
  `last_used_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
  KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`,`tokenable_id`),
  KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `personal_access_tokens`

INSERT INTO `personal_access_tokens` VALUES 
(2, 'App\\Models\\User', 2, 'TestDevice', 'b619f711d5b9663e23238c73d7d77aa30a73920541ee9a23da946bc8ffc25bed', '[\"*\"]', '2026-08-23 16:23:41', NULL, '2026-08-23 16:23:41', '2026-08-23 16:23:41'),
(5, 'App\\Models\\User', 2, 'TestDevice', '2cff384cd303e50c023e458a8279d9a56413eee4e7b732e039b063921b6747bb', '[\"*\"]', '2026-08-23 16:24:04', NULL, '2026-08-23 16:24:03', '2026-08-23 16:24:04'),
(8, 'App\\Models\\User', 2, 'TestDevice', '1e1f1838aed0e131ebcb79f24a882c9c78ea8663948db09e77fffb16b1a0fd34', '[\"*\"]', '2026-08-23 16:25:12', NULL, '2026-08-23 16:25:11', '2026-08-23 16:25:12'),
(12, 'App\\Models\\User', 2, 'smart-society-mobile', 'c619a780a703047d8a3ae1d768067e1e8074a6f8082ce5aaaf0bef30ceca9772', '[\"*\"]', NULL, NULL, '2026-08-23 16:29:10', '2026-08-23 16:29:10'),
(15, 'App\\Models\\User', 2, 'smart-society-mobile', '94620f0ebd126417279aef4f198f9b9cc4738b7c13ed0bdbf19b00ebb6b5aac0', '[\"*\"]', '2026-08-23 16:29:46', NULL, '2026-08-23 16:29:46', '2026-08-23 16:29:46'),
(18, 'App\\Models\\User', 2, 'smart-society-mobile', '0a19d1ee0a5528eb9eafe6d43e9a1a3344b04df42b26c0080438d89f9e2947f1', '[\"*\"]', '2026-08-23 16:34:50', NULL, '2026-08-23 16:34:50', '2026-08-23 16:34:50'),
(21, 'App\\Models\\User', 2, 'smart-society-mobile', 'a6117d540dc0d290b82837da8dd9fd73a07a0735f4148ff9935983a82648f60f', '[\"*\"]', '2026-08-23 16:51:48', NULL, '2026-08-23 16:51:28', '2026-08-23 16:51:48'),
(22, 'App\\Models\\User', 2, 'smart-society-mobile', 'bfa296d4446329b952d482be7801a18b9511b3ea14a20d42b6a13ff6d4559177', '[\"*\"]', '2026-08-23 16:53:38', NULL, '2026-08-23 16:53:38', '2026-08-23 16:53:38'),
(23, 'App\\Models\\User', 2, 'smart-society-mobile', '412e06f8f825ab60f546e8361417f3a01d1a82604ba17c4d44bf1e0b90c87192', '[\"*\"]', '2026-08-23 16:54:01', NULL, '2026-08-23 16:54:01', '2026-08-23 16:54:01'),
(24, 'App\\Models\\User', 4, 'smart-society-mobile', '613dc1c4bcb980def5d4e94161ddcfd5f5a549b3387deb68c6aa5fc6d69c4eab', '[\"*\"]', '2026-08-24 12:02:44', NULL, '2026-08-24 12:02:41', '2026-08-24 12:02:44'),
(25, 'App\\Models\\User', 1, 'smart-society-mobile', '75226ca2ab50c7e2e311fc55ad3172b303bd3ec85d795d9594cb5a7ab0737820', '[\"*\"]', '2026-08-24 12:02:44', NULL, '2026-08-24 12:02:41', '2026-08-24 12:02:44'),
(26, 'App\\Models\\User', 2, 'smart-society-mobile', 'fb9e7f4f6723344fb6288e19177246353a367dc56b583a8917608c431cdff1c7', '[\"*\"]', '2026-08-24 12:02:42', NULL, '2026-08-24 12:02:42', '2026-08-24 12:02:42');

-- --------------------------------------------------------
-- Table structure for `resident_transaction_ledger`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `resident_transaction_ledger`;
CREATE TABLE `resident_transaction_ledger` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resident_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `transaction_type` enum('DEBIT','CREDIT') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `description` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `reference_type` varchar(100) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `running_balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resident_transaction_ledger_resident_id_transaction_date_index` (`resident_id`,`transaction_date`),
  KEY `resident_transaction_ledger_flat_id_transaction_date_index` (`flat_id`,`transaction_date`),
  KEY `resident_transaction_ledger_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `resident_transaction_ledger_transaction_type_index` (`transaction_type`),
  KEY `resident_transaction_ledger_category_index` (`category`),
  KEY `resident_transaction_ledger_transaction_date_index` (`transaction_date`),
  CONSTRAINT `resident_transaction_ledger_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resident_transaction_ledger_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `resident_transactions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `resident_transactions`;
CREATE TABLE `resident_transactions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `resident_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `transaction_type` enum('DEBIT','CREDIT') NOT NULL,
  `category` varchar(50) NOT NULL,
  `description` varchar(500) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `transaction_date` date NOT NULL,
  `reference_type` varchar(50) DEFAULT NULL,
  `reference_id` bigint(20) unsigned DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_method` varchar(30) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'COMPLETED',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `resident_transactions_resident_id_transaction_date_index` (`resident_id`,`transaction_date`),
  KEY `resident_transactions_flat_id_transaction_date_index` (`flat_id`,`transaction_date`),
  KEY `resident_transactions_reference_type_reference_id_index` (`reference_type`,`reference_id`),
  KEY `resident_transactions_transaction_type_index` (`transaction_type`),
  KEY `resident_transactions_category_index` (`category`),
  KEY `resident_transactions_transaction_date_index` (`transaction_date`),
  KEY `resident_transactions_status_index` (`status`),
  CONSTRAINT `resident_transactions_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `resident_transactions_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------
-- Table structure for `residents`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `residents`;
CREATE TABLE `residents` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `flat_id` bigint(20) unsigned DEFAULT NULL,
  `relation_to_owner` varchar(50) DEFAULT NULL,
  `is_primary_contact` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `residents_user_id_unique` (`user_id`),
  KEY `residents_flat_id_is_primary_contact_index` (`flat_id`,`is_primary_contact`),
  KEY `residents_is_primary_contact_index` (`is_primary_contact`),
  CONSTRAINT `residents_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE SET NULL,
  CONSTRAINT `residents_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `residents`

INSERT INTO `residents` VALUES 
(1, 3, 1, 'SELF', 1, '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(2, 4, 1, 'OWNER', 1, '2026-08-23 16:18:33', '2026-08-23 16:33:01'),
(3, 5, 1, 'TENANT', 0, '2026-08-23 16:54:02', '2026-08-23 16:54:02');

-- --------------------------------------------------------
-- Table structure for `sessions`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `sessions`;
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

-- --------------------------------------------------------
-- Table structure for `staff_members`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `staff_members`;
CREATE TABLE `staff_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `employee_id` varchar(50) DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `mobile` varchar(20) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `designation` varchar(100) NOT NULL,
  `shift` varchar(50) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'ACTIVE',
  `joining_date` date NOT NULL,
  `emergency_contact` varchar(25) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_members_user_id_unique` (`user_id`),
  UNIQUE KEY `staff_members_employee_id_unique` (`employee_id`),
  KEY `staff_members_status_index` (`status`),
  CONSTRAINT `staff_members_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `staff_members`

INSERT INTO `staff_members` VALUES 
(1, 1, 'SEC001', 'Test Security', +919999999999, 'security@smartsociety.local', 'Security Guard', 'ALL', 'ACTIVE', '2026-02-23', +919999999998, '2026-08-23 16:17:53', '2026-08-23 16:33:01');

-- --------------------------------------------------------
-- Table structure for `user_profiles`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `user_profiles`;
CREATE TABLE `user_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint(20) unsigned NOT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` varchar(30) DEFAULT NULL,
  `emergency_contact` varchar(25) DEFAULT NULL,
  `profile_photo_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_profiles_user_id_unique` (`user_id`),
  CONSTRAINT `user_profiles_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `user_profiles`

INSERT INTO `user_profiles` VALUES 
(1, 1, NULL, 'OTHER', +919999999998, NULL, '2026-08-23 16:17:53', '2026-08-23 16:17:53'),
(2, 2, NULL, 'OTHER', +919999999989, NULL, '2026-08-23 16:17:53', '2026-08-23 16:33:01'),
(3, 3, NULL, 'MALE', +919999999999, NULL, '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(4, 4, NULL, 'MALE', +919999999900, NULL, '2026-08-23 16:18:33', '2026-08-23 16:33:01');

-- --------------------------------------------------------
-- Table structure for `users`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'RESIDENT',
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`),
  UNIQUE KEY `users_phone_unique` (`phone`),
  KEY `users_role_index` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table `users`

INSERT INTO `users` VALUES 
(1, 'Test Security', 'security@smartsociety.local', +919999999999, 'SECURITY', '2026-08-23 16:17:53', '$2y$12$wDZTfviKfNbLXWkvoD9TluwO7KIEKJoSUvkhyRnf7gxBnYxQAkJii', NULL, '2026-08-23 16:17:53', '2026-08-23 16:33:01'),
(2, 'Test Administrator', 'admin@smartsociety.local', +919999999990, 'ADMIN', '2026-08-23 16:17:53', '$2y$12$71.3QHqy0ZpGsTUzcy1gPu/Q0rxpKVQACLXoE8Wz1eg19wlD3g2aS', NULL, '2026-08-23 16:17:53', '2026-08-23 16:33:01'),
(3, 'Swanand Pawar', 'pspawar13@gmail.com', +919876543210, 'RESIDENT', NULL, '$2y$12$RljDOH7yGCH6nMP4R4FQsOQW2qP1pWoyFKKaBJR8O/KLcdDNU8Yfe', NULL, '2026-08-23 16:18:33', '2026-08-23 16:18:33'),
(4, 'Test Resident', 'resident@smartsociety.local', +919999999901, 'RESIDENT', NULL, '$2y$12$iQj/Fu3vuj3vXyVNO0WPr.LWhoOh5OTTtaV4LJSkvQMZnD/joRoo.', NULL, '2026-08-23 16:18:33', '2026-08-23 16:33:01'),
(5, 'Aarav Sharma', 'aarav.sharma@example.com', +919811122233, 'RESIDENT', NULL, '$2y$12$/onoX/gdoENQU4MMpJciV.0mhbvOkePMyIl1LvVi0SJJBmVql6U1e', NULL, '2026-08-23 16:54:02', '2026-08-23 16:54:02');

-- --------------------------------------------------------
-- Table structure for `visitors`
-- --------------------------------------------------------

DROP TABLE IF EXISTS `visitors`;
CREATE TABLE `visitors` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `visitor_name` varchar(255) NOT NULL,
  `mobile_number` varchar(25) DEFAULT NULL,
  `purpose` varchar(255) DEFAULT NULL,
  `flat_id` bigint(20) unsigned NOT NULL,
  `resident_id` bigint(20) unsigned NOT NULL,
  `vehicle_number` varchar(30) DEFAULT NULL,
  `visitor_type` varchar(30) NOT NULL DEFAULT 'GUEST',
  `entry_status` varchar(30) NOT NULL DEFAULT 'WAITING',
  `approval_status` varchar(30) NOT NULL DEFAULT 'PENDING',
  `expected_at` timestamp NULL DEFAULT NULL,
  `entered_at` timestamp NULL DEFAULT NULL,
  `exited_at` timestamp NULL DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_note` varchar(500) DEFAULT NULL,
  `is_pre_approved` tinyint(1) NOT NULL DEFAULT 0,
  `created_by_security_id` bigint(20) unsigned DEFAULT NULL,
  `approved_by_resident_id` bigint(20) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `visitors_created_by_security_id_foreign` (`created_by_security_id`),
  KEY `visitors_approved_by_resident_id_foreign` (`approved_by_resident_id`),
  KEY `visitors_flat_id_approval_status_index` (`flat_id`,`approval_status`),
  KEY `visitors_resident_id_approval_status_index` (`resident_id`,`approval_status`),
  KEY `visitors_entry_status_created_at_index` (`entry_status`,`created_at`),
  KEY `visitors_mobile_number_index` (`mobile_number`),
  KEY `visitors_visitor_type_index` (`visitor_type`),
  KEY `visitors_entry_status_index` (`entry_status`),
  KEY `visitors_approval_status_index` (`approval_status`),
  KEY `visitors_is_pre_approved_index` (`is_pre_approved`),
  CONSTRAINT `visitors_approved_by_resident_id_foreign` FOREIGN KEY (`approved_by_resident_id`) REFERENCES `residents` (`id`) ON DELETE SET NULL,
  CONSTRAINT `visitors_created_by_security_id_foreign` FOREIGN KEY (`created_by_security_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `visitors_flat_id_foreign` FOREIGN KEY (`flat_id`) REFERENCES `flats` (`id`) ON DELETE CASCADE,
  CONSTRAINT `visitors_resident_id_foreign` FOREIGN KEY (`resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS=1;
COMMIT;
