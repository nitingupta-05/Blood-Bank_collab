SET NAMES utf8mb4;

DROP TABLE IF EXISTS `blood_units`;
CREATE TABLE `blood_units` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `barcode`           VARCHAR(64)  NOT NULL,
  `blood_group`       ENUM('O+','O-','A+','A-','B+','B-','AB+','AB-') NOT NULL,
  `collection_date`   DATE         NOT NULL,
  `expiry_date`       DATE         NOT NULL,
  `donor_id`          INT UNSIGNED DEFAULT NULL,
  `storage_location`  VARCHAR(100) DEFAULT NULL,
  `status`            ENUM('available','reserved','used','expired','discarded') NOT NULL DEFAULT 'available',
  `created_by`        INT UNSIGNED DEFAULT NULL,
  `created_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`        TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_units_barcode` (`barcode`),
  KEY `idx_units_blood_group` (`blood_group`),
  KEY `idx_units_status` (`status`),
  KEY `idx_units_expiry` (`expiry_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `donors`;
CREATE TABLE `donors` (
  `id`                     INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id`                INT UNSIGNED NOT NULL,
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
  KEY `idx_donors_eligibility` (`eligibility_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS `storage_areas`;
CREATE TABLE `storage_areas` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`                  VARCHAR(100) NOT NULL,
  `capacity`              INT UNSIGNED NOT NULL DEFAULT 0,
  `current_occupancy`     INT UNSIGNED NOT NULL DEFAULT 0,
  `current_temperature`   DECIMAL(5,2) DEFAULT NULL,
  `last_temperature_check` DATETIME    DEFAULT NULL,
  `created_at`            TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  KEY `idx_notif_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  UNIQUE KEY `uq_notif_settings_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
