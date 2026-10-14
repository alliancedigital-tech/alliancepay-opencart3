<?php

declare(strict_types=1);

class ModelExtensionPaymentSubscriptionDb extends Model
{

    public function initDB()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan_template`
        (
            `template_id` INT(11) NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(64) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `category_indicator` VARCHAR(64) NOT NULL,
            `rules_template` LONGTEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'active',
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`template_id`),
            UNIQUE KEY `uq_template_code` (`code`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan`
        (
            `plan_id` INT(11) NOT NULL AUTO_INCREMENT,
            `provider_plan_id` VARCHAR(255) NOT NULL,
            `version_id` VARCHAR(255) DEFAULT NULL,
            `product_id` INT(11) NOT NULL,
            `template_id` INT(11) DEFAULT NULL,
            `plan_key` CHAR(64) NOT NULL,
            `merchant_id` VARCHAR(255) NOT NULL,
            `name` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) DEFAULT NULL,
            `category_indicator` VARCHAR(64) NOT NULL,
            `rules` LONGTEXT NOT NULL,
            `applies_from` DATETIME DEFAULT NULL,
            `applies_to` DATETIME DEFAULT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'ACTIVE',
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `active_subscriptions_count` INT(11) NOT NULL DEFAULT 0,
            `sync_status` VARCHAR(20) NOT NULL DEFAULT 'SYNCED',
            `sync_error` TEXT DEFAULT NULL,
            `infinite_schedule_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`plan_id`),
            UNIQUE KEY `uq_provider_plan_id` (`provider_plan_id`),
            UNIQUE KEY `uq_plan_key` (`plan_key`),
            KEY `product_id` (`product_id`),
            KEY `template_id` (`template_id`),
            KEY `is_active_status` (`is_active`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription`
        (
            `subscription_id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `plan_id` INT(11) NOT NULL,
            `customer_id` INT(11) NOT NULL,
            `provider_order_id` VARCHAR(255) NOT NULL,
            `merchant_request_id` VARCHAR(255) NOT NULL,
            `rules_snapshot` LONGTEXT NOT NULL,
            `status` VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            `amount_review_flag` TINYINT(1) NOT NULL DEFAULT 0,
            `callback_data` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`subscription_id`),
            UNIQUE KEY `uq_order_id` (`order_id`),
            UNIQUE KEY `uq_provider_order_id` (`provider_order_id`),
            KEY `plan_id` (`plan_id`),
            KEY `customer_id` (`customer_id`),
            KEY `merchant_request_id` (`merchant_request_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_scheduled_payment`
        (
            `scheduled_payment_row_id` INT(11) NOT NULL AUTO_INCREMENT,
            `subscription_id` INT(11) NOT NULL,
            `provider_order_id` VARCHAR(255) NOT NULL,
            `scheduled_payment_id` VARCHAR(255) DEFAULT NULL,
            `payment_number` INT(11) DEFAULT NULL,
            `status` VARCHAR(30) NOT NULL DEFAULT 'WAITING',
            `scheduled_date` DATETIME DEFAULT NULL,
            `executed_at` DATETIME DEFAULT NULL,
            `operation_id` VARCHAR(255) DEFAULT NULL,
            `expected_coin_amount` INT(11) DEFAULT NULL,
            `actual_coin_amount` INT(11) DEFAULT NULL,
            `amount_mismatch_flag` TINYINT(1) NOT NULL DEFAULT 0,
            `retry_count` INT(11) NOT NULL DEFAULT 0,
            `raw_callback_data` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            `updated_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`scheduled_payment_row_id`),
            UNIQUE KEY `uq_subscription_scheduled_payment` (`subscription_id`, `scheduled_payment_id`),
            KEY `provider_order_id` (`provider_order_id`),
            KEY `operation_id` (`operation_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_plan_version_history`
        (
            `history_id` INT(11) NOT NULL AUTO_INCREMENT,
            `plan_id` INT(11) NOT NULL,
            `old_version_id` VARCHAR(255) DEFAULT NULL,
            `new_version_id` VARCHAR(255) DEFAULT NULL,
            `old_rules` LONGTEXT DEFAULT NULL,
            `new_rules` LONGTEXT NOT NULL,
            `applies_from` DATETIME NOT NULL,
            `reason` VARCHAR(255) DEFAULT NULL,
            `notify_confirmed` TINYINT(1) NOT NULL DEFAULT 0,
            `affected_subscriptions_count` INT(11) NOT NULL DEFAULT 0,
            `created_by` INT(11) NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`history_id`),
            KEY `plan_id` (`plan_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_subscription_audit_log`
        (
            `audit_id` INT(11) NOT NULL AUTO_INCREMENT,
            `action` VARCHAR(64) NOT NULL,
            `plan_id` INT(11) DEFAULT NULL,
            `subscription_id` INT(11) DEFAULT NULL,
            `product_id` INT(11) DEFAULT NULL,
            `user_id` INT(11) DEFAULT NULL,
            `details` LONGTEXT DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`audit_id`),
            KEY `plan_id` (`plan_id`),
            KEY `subscription_id` (`subscription_id`),
            KEY `action` (`action`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    public function upgradeSubscriptionTables()
    {

    }
}
