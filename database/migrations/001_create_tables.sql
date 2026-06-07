-- ===========================================================================
-- Blood Bank Management System — Clean Unified Migration
-- MySQL 5.7+ / utf8mb4
-- ===========================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`                    VARCHAR(190) NOT NULL,
  `password_hash`            VARCHAR(255) NOT NULL,
  `role`                     ENUM('super_admin','blood_bank_admin','staff','donor','patient','hospital') NOT NULL,
  `first_name`               VARCHAR(100) DEFAULT NULL,
  `last_name`                VARCHAR(100) DEFAULT NULL,
  `phone`                    VARCHAR(30)  DEFAULT NULL,
  `address`                  TEXT         DEFAULT NULL,
  `city`                     VARCHAR(100) DEFAULT NULL,
  `latitude`                 DECIMAL(10,8) DEFAULT NULL,
  `longitude`                DECIMAL(11,8) DEFAULT NULL,
  `is_active`                TINYINT(1)   NOT NULL DEFAULT 1,
  `failed_login_attempts`    INT UNSIGNED NOT NULL DEFAULT 0,
  `lockout_until`            DATETIME     DEFAULT NULL,
  `password_reset_token`     VARCHAR(64)  DEFAULT NULL,
  `password_reset_expires`   DATETIME     DEFAULT NULL,
  `last_login_at`            DATETIME     DEFAULT NULL,
  `created_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`               TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_city` (`city`),
  KEY `idx_users_reset_token` (`password_reset_token`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- blood_banks
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `blood_banks`;
CREATE TABLE `blood_banks` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`            VARCHAR(255) NOT NULL,
  `address`         TEXT         NOT NULL,
  `city`            VARCHAR(100) NOT NULL,
  `contact_person`  VARCHAR(100) DEFAULT NULL,
  `phone`           VARCHAR(30)  DEFAULT NULL,
  `email`           VARCHAR(190) DEFAULT NULL,
  `latitude`        DECIMAL(10,8) DEFAULT NULL,
  `longitude`       DECIMAL(11,8) DEFAULT NULL,
  `capacity`        INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_banks_city` (`city`),
  KEY `idx_banks_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- blood_units
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `blood_units`;
CREATE TABLE `blood_units` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode`           VARCHAR(64)  NOT NULL,
  `blood_group`       ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  `collection_date`   DATE         NOT NULL,
  `expiry_date`       DATE         NOT NULL,
  `donor_id`          INT UNSIGNED DEFAULT NULL,
  `blood_bank_id`     INT UNSIGNED NOT NULL,
  `storage_location`  VARCHAR(100) DEFAULT NULL,
  `status`            ENUM('available','reserved','used','expired','discarded') NOT NULL DEFAULT 'available',
  `created_by`        INT UNSIGNED DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_barcode` (`barcode`),
  KEY `idx_units_blood_group` (`blood_group`),
  KEY `idx_units_status` (`status`),
  KEY `idx_units_expiry` (`expiry_date`),
  KEY `idx_units_bank` (`blood_bank_id`),
  CONSTRAINT `fk_units_donor`  FOREIGN KEY (`donor_id`)      REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_units_bank`   FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_units_creator` FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `chk_units_dates` CHECK (`expiry_date` > `collection_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- donors
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `donors`;
CREATE TABLE `donors` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                INT UNSIGNED NOT NULL,
  `preferred_blood_bank_id` INT UNSIGNED DEFAULT NULL,
  `blood_group`            ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  `age`                    TINYINT UNSIGNED DEFAULT NULL,
  `weight`                 DECIMAL(5,2) DEFAULT NULL,
  `gender`                 ENUM('male','female','other') DEFAULT NULL,
  `medical_history`        TEXT         DEFAULT NULL,
  `last_donation_date`     DATE         DEFAULT NULL,
  `next_eligible_date`     DATE         DEFAULT NULL,
  `eligibility_status`     ENUM('eligible','ineligible') NOT NULL DEFAULT 'eligible',
  `total_donations`        INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`             TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_donors_user` (`user_id`),
  KEY `idx_donors_blood_group` (`blood_group`),
  KEY `idx_donors_eligibility` (`eligibility_status`),
  CONSTRAINT `fk_donors_user`  FOREIGN KEY (`user_id`)                REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_donors_bank`  FOREIGN KEY (`preferred_blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- emergency_requests
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `emergency_requests`;
CREATE TABLE `emergency_requests` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blood_group`     ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  `quantity`        INT UNSIGNED NOT NULL,
  `urgency_level`   ENUM('critical','moderate','low') NOT NULL DEFAULT 'moderate',
  `hospital_id`     INT UNSIGNED NOT NULL,
  `location`        VARCHAR(255) NOT NULL,
  `patient_name`    VARCHAR(255) NOT NULL,
  `patient_details` TEXT         DEFAULT NULL,
  `contact_phone`   VARCHAR(30)  NOT NULL,
  `latitude`        DECIMAL(10,8) DEFAULT NULL,
  `longitude`       DECIMAL(11,8) DEFAULT NULL,
  `status`          ENUM('active','fulfilled','cancelled','expired') NOT NULL DEFAULT 'active',
  `expires_at`      DATETIME     DEFAULT NULL,
  `fulfilled_at`    DATETIME     DEFAULT NULL,
  `created_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_emerg_status` (`status`),
  KEY `idx_emerg_blood_group` (`blood_group`),
  KEY `idx_emerg_urgency` (`urgency_level`),
  KEY `idx_emerg_hospital` (`hospital_id`),
  KEY `idx_emerg_expires` (`expires_at`),
  KEY `idx_emerg_created` (`created_at`),
  CONSTRAINT `fk_emerg_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- blood_bookings
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `blood_bookings`;
CREATE TABLE `blood_bookings` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `hospital_id`      INT UNSIGNED NOT NULL,
  `blood_group`      ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  `quantity`         INT UNSIGNED NOT NULL,
  `required_date`    DATE         NOT NULL,
  `patient_name`     VARCHAR(255) DEFAULT NULL,
  `status`           ENUM('pending','approved','rejected','completed','cancelled') NOT NULL DEFAULT 'pending',
  `approved_by`      INT UNSIGNED DEFAULT NULL,
  `approval_date`    DATETIME     DEFAULT NULL,
  `notes`            TEXT         DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_bookings_status` (`status`),
  KEY `idx_bookings_hospital` (`hospital_id`),
  KEY `idx_bookings_required_date` (`required_date`),
  CONSTRAINT `fk_bookings_hospital` FOREIGN KEY (`hospital_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bookings_approver` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- storage_areas
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `storage_areas`;
CREATE TABLE `storage_areas` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `blood_bank_id`         INT UNSIGNED NOT NULL,
  `name`                  VARCHAR(100) NOT NULL,
  `capacity`              INT UNSIGNED NOT NULL DEFAULT 0,
  `current_occupancy`     INT UNSIGNED NOT NULL DEFAULT 0,
  `current_temperature`   DECIMAL(5,2) DEFAULT NULL,
  `last_temperature_check` DATETIME    DEFAULT NULL,
  `created_at`            TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_storage_bank` (`blood_bank_id`),
  CONSTRAINT `fk_storage_bank` FOREIGN KEY (`blood_bank_id`) REFERENCES `blood_banks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- temperature_logs
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `temperature_logs`;
CREATE TABLE `temperature_logs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `storage_area_id`  INT UNSIGNED NOT NULL,
  `temperature`      DECIMAL(5,2) NOT NULL,
  `recorded_at`      TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_temp_storage` (`storage_area_id`),
  KEY `idx_temp_recorded` (`recorded_at`),
  CONSTRAINT `fk_temp_storage` FOREIGN KEY (`storage_area_id`) REFERENCES `storage_areas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `notifications`;
CREATE TABLE `notifications` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`         INT UNSIGNED NOT NULL,
  `type`            ENUM('low_stock','expiry','emergency','booking','temperature','eligibility','system') NOT NULL,
  `message`         TEXT NOT NULL,
  `channel`         ENUM('sms','email','in_app') NOT NULL DEFAULT 'in_app',
  `delivery_status` ENUM('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
  `related_type`    VARCHAR(50) DEFAULT NULL,
  `related_id`      INT UNSIGNED DEFAULT NULL,
  `created_at`      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_user` (`user_id`),
  KEY `idx_notif_status` (`delivery_status`),
  KEY `idx_notif_created` (`created_at`),
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- notification_settings
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `notification_settings`;
CREATE TABLE `notification_settings` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`             INT UNSIGNED NOT NULL,
  `email_notifications` TINYINT(1)   NOT NULL DEFAULT 1,
  `sms_notifications`   TINYINT(1)   NOT NULL DEFAULT 0,
  `in_app_notifications` TINYINT(1)  NOT NULL DEFAULT 1,
  `created_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notif_settings_user` (`user_id`),
  CONSTRAINT `fk_notif_set_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- audit_logs
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_logs`;
CREATE TABLE `audit_logs` (
  `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`          INT UNSIGNED DEFAULT NULL,
  `action_type`      VARCHAR(100) NOT NULL,
  `affected_record`  VARCHAR(255) DEFAULT NULL,
  `details`          TEXT         DEFAULT NULL,
  `ip_address`       VARCHAR(45)  DEFAULT NULL,
  `created_at`       TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_user` (`user_id`),
  KEY `idx_audit_action` (`action_type`),
  KEY `idx_audit_created` (`created_at`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_attempts  (rate limiting + lockout)
-- ---------------------------------------------------------------------------
DROP TABLE IF EXISTS `login_attempts`;
CREATE TABLE `login_attempts` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`       VARCHAR(190) NOT NULL,
  `ip_address`  VARCHAR(45)  NOT NULL,
  `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `successful`  TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_login_email_time` (`email`, `attempted_at`),
  KEY `idx_login_ip_time`    (`ip_address`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
