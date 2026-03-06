<?php

class ModelExtensionPaymentDb extends Model
{
	public function initDB()
	{
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_checkout_integration_order`
		(
			`order_id` INT(11) NOT NULL,
			`merchant_request_id` VARCHAR(255) NOT NULL,
			`hpp_order_id` VARCHAR(255) NOT NULL,
			`merchant_id` VARCHAR(255) NOT NULL,
			`coin_amount` INT(11) NOT NULL,
			`hpp_pay_type` VARCHAR(50) NOT NULL,
			`order_status` VARCHAR(50) NOT NULL,
			`payment_methods` TEXT NOT NULL,
			`create_date` DATETIME NOT NULL,
			`updated_at` DATETIME,
            `operation_id` VARCHAR(255),
            `ecom_order_id` VARCHAR(255),
            `is_callback_returned` BOOLEAN DEFAULT FALSE,
            `callback_data` LONGTEXT,
			`expired_order_date` DATETIME NOT NULL,
			PRIMARY KEY (`order_id`),
			KEY `merchant_request_id` (`merchant_request_id`),
			KEY `hpp_order_id` (`hpp_order_id`),
			KEY `merchant_id` (`merchant_id`),
			KEY `order_id` (`order_id`)
		) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");


        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "alliance_integration_order_refund`
        (
            `refund_id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `type` VARCHAR(255) NOT NULL,
            `rrn` VARCHAR(255) NOT NULL,
            `purpose` VARCHAR(255) NOT NULL,
            `comment` VARCHAR(255) NOT NULL,
            `coin_amount` INT(11) NOT NULL,
            `merchant_id` VARCHAR(255) NOT NULL,
            `operation_id` VARCHAR(255) NOT NULL,
            `ecom_operation_id` VARCHAR(255) NOT NULL,
            `merchant_name` VARCHAR(255) NULL,
            `approval_code` VARCHAR(255) NOT NULL,
            `status` VARCHAR(255) NOT NULL,
            `transaction_type` INT(11) NOT NULL,
            `merchant_request_id` VARCHAR(255) NOT NULL,
            `transaction_currency` VARCHAR(255) NOT NULL,
            `merchant_commission` INT(11) NULL,
            `create_date_time` DATETIME NOT NULL,
            `modification_date_time` DATETIME NOT NULL,
            `action_code` VARCHAR(255) NOT NULL,
            `response_code` VARCHAR(255) NOT NULL,
            `description` VARCHAR(255) NOT NULL,
            `processing_merchant_id` VARCHAR(255) NOT NULL,
            `processing_terminal_id` VARCHAR(255) NOT NULL,
            `transaction_response_info` TEXT NOT NULL,
            `bank_code` VARCHAR(255) NOT NULL,
            `payment_system` VARCHAR(255) NOT NULL,
            `product_type` VARCHAR(255) NOT NULL,
            `notification_url` VARCHAR(255) NOT NULL,
            `payment_service_type` VARCHAR(255) NOT NULL,
            `notification_encryption` VARCHAR(255) NOT NULL,
            `original_operation_id` VARCHAR(255) NOT NULL,
            `original_coin_amount` INT(11) NOT NULL,
            `original_ecom_operation_id` VARCHAR(255) NOT NULL,
            `rrn_original` VARCHAR(255) NOT NULL,
            PRIMARY KEY (`refund_id`),
            KEY `refund_id` (`refund_id`),
            KEY `merchant_request_id` (`merchant_request_id`),
            KEY `merchant_id` (`merchant_id`),
            KEY `order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
    }

    public function updateAllianceOrderTable()
    {
        $columnsToAdd = "'updated_at', 'ecom_order_id', 'is_callback_returned', 'callback_data'";

        $columnsDefinition = [
            'updated_at' => ' DATETIME NOT NULL AFTER `create_date`',
            'operation_id' => ' VARCHAR(255) AFTER `payment_methods`',
            'ecom_order_id' => ' VARCHAR(255) AFTER `payment_methods`',
            'is_callback_returned' => ' BOOLEAN DEFAULT FALSE AFTER `payment_methods`',
            'callback_data' => ' LONGTEXT AFTER `payment_methods`',
        ];

        $orderIdIsPrimary = $this->db->query(
            "SELECT COLUMN_KEY FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '"
            . DB_DATABASE
            . "' AND TABLE_NAME = '"
            . DB_PREFIX
            . "alliance_checkout_integration_order' AND COLUMN_NAME='order_id'");

        if ($orderIdIsPrimary == 'PRI') {
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX
                . "alliance_integration_order_refund` DROP PRIMARY KEY");
            $this->db->query(
                "ALTER TABLE `" . DB_PREFIX
                . "alliance_integration_order_refund` ADD `refund_id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY");
        }

        $fields = $this->db->query(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = '"
            . DB_DATABASE
            . "' AND TABLE_NAME = '"
            . DB_PREFIX
            . "alliance_checkout_integration_order' AND COLUMN_NAME IN (" . $columnsToAdd . ")");

        if (!$fields->num_rows) {
            foreach ($columnsDefinition as $column => $definition) {
                $this->db->query(
                    "ALTER TABLE `" . DB_PREFIX
                    . "alliance_checkout_integration_order` ADD `". $column . "`" . $definition);
            }
        }
    }
}
