<?php

declare(strict_types=1);

class ModelExtensionPaymentSubscription extends Model
{
    /**
     * @param array $data Optional filter_customer, filter_plan_id, filter_status,
     */
    public function getSubscriptions(array $data = []): array
    {
        $languageId = (int)$this->config->get('config_language_id');

        $sql = "SELECT s.*, p.name AS plan_name, p.product_id AS plan_product_id,
                    pd.name AS product_name, c.firstname, c.lastname, c.email
                FROM `" . DB_PREFIX . "alliance_subscription` s
                LEFT JOIN `" . DB_PREFIX . "alliance_subscription_plan` p ON (s.plan_id = p.plan_id)
                LEFT JOIN `" . DB_PREFIX . "product_description` pd
                    ON (p.product_id = pd.product_id AND pd.language_id = '" . $languageId . "')
                LEFT JOIN `" . DB_PREFIX . "customer` c ON (s.customer_id = c.customer_id)"
            . $this->buildWhere($data);

        $sql .= " ORDER BY s.created_at DESC";

        if (isset($data['start']) || isset($data['limit'])) {
            $start = max(0, (int)($data['start'] ?? 0));
            $limit = max(1, (int)($data['limit'] ?? 20));
            $sql .= " LIMIT " . $start . ", " . $limit;
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param array $data
     */
    public function getTotalSubscriptions(array $data = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription` s
                LEFT JOIN `" . DB_PREFIX . "alliance_subscription_plan` p ON (s.plan_id = p.plan_id)
                LEFT JOIN `" . DB_PREFIX . "customer` c ON (s.customer_id = c.customer_id)"
            . $this->buildWhere($data);

        $query = $this->db->query($sql);

        return (int)($query->row['total'] ?? 0);
    }

    /**
     * @param array $data
     */
    private function buildWhere(array $data): string
    {
        $where = [];

        if (!empty($data['filter_customer'])) {
            $customer = $this->db->escape((string)$data['filter_customer']);
            $where[] = "(c.firstname LIKE '%" . $customer . "%' OR c.lastname LIKE '%" . $customer . "%'"
                . " OR c.email LIKE '%" . $customer . "%')";
        }

        if (!empty($data['filter_plan_id'])) {
            $where[] = "s.plan_id = '" . (int)$data['filter_plan_id'] . "'";
        }

        if (!empty($data['filter_status'])) {
            $where[] = "s.status = '" . $this->db->escape((string)$data['filter_status']) . "'";
        }

        if (!empty($data['filter_provider_order_id'])) {
            $where[] = "s.provider_order_id = '" . $this->db->escape((string)$data['filter_provider_order_id']) . "'";
        }

        return $where ? ' WHERE ' . implode(' AND ', $where) : '';
    }

    /**
     * @param int $subscriptionId
     */
    public function getSubscription(int $subscriptionId): array
    {
        $query = $this->db->query(
            "SELECT s.*, p.name AS plan_name, p.rules AS plan_rules, p.product_id AS plan_product_id
             FROM `" . DB_PREFIX . "alliance_subscription` s
             LEFT JOIN `" . DB_PREFIX . "alliance_subscription_plan` p ON (s.plan_id = p.plan_id)
             WHERE s.subscription_id = '" . (int)$subscriptionId . "'"
        );

        return $query->row;
    }

    /**
     * @param int $orderId
     */
    public function getSubscriptionByOrderId(int $orderId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription` WHERE `order_id` = '" . (int)$orderId . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionId
     */
    public function getScheduledPayments(int $subscriptionId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`
             WHERE `subscription_id` = '" . (int)$subscriptionId . "'
             ORDER BY `payment_number` ASC, `scheduled_payment_row_id` ASC"
        );

        return $query->rows;
    }

    /**
     * @param int $scheduledPaymentRowId
     */
    public function getScheduledPayment(int $scheduledPaymentRowId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment`
             WHERE `scheduled_payment_row_id` = '" . (int)$scheduledPaymentRowId . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionId
     * @param string $status
     */
    public function updateSubscriptionStatus(int $subscriptionId, string $status): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET
                `status` = '" . $this->db->escape($status) . "',
                `updated_at` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'
             WHERE `subscription_id` = '" . (int)$subscriptionId . "'"
        );
    }

    /**
     * @param int $scheduledPaymentRowId
     */
    public function markScheduledPaymentProcessingWithRetry(int $scheduledPaymentRowId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_scheduled_payment` SET
                `status` = '" . AllianceSubscriptionConfig::SCHEDULED_STATUS_PROCESSING . "',
                `retry_count` = `retry_count` + 1,
                `updated_at` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'
             WHERE `scheduled_payment_row_id` = '" . (int)$scheduledPaymentRowId . "'"
        );
    }

    /**
     * @param int $planId
     */
    public function decrementPlanActiveCount(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan`
             SET `active_subscriptions_count` = GREATEST(`active_subscriptions_count` - 1, 0)
             WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param array $data action, plan_id?, subscription_id?, product_id?, user_id?, details?
     */
    public function insertAuditLog(array $data): void
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_audit_log` SET
                `action` = '" . $this->db->escape((string)$data['action']) . "',
                `plan_id` = " . (isset($data['plan_id']) ? "'" . (int)$data['plan_id'] . "'" : "NULL") . ",
                `subscription_id` = " . (isset($data['subscription_id']) ? "'" . (int)$data['subscription_id'] . "'" : "NULL") . ",
                `product_id` = " . (isset($data['product_id']) ? "'" . (int)$data['product_id'] . "'" : "NULL") . ",
                `user_id` = " . (isset($data['user_id']) ? "'" . (int)$data['user_id'] . "'" : "NULL") . ",
                `details` = '" . $this->db->escape(json_encode($data['details'] ?? [], JSON_UNESCAPED_UNICODE)) . "',
                `created_at` = '" . $this->db->escape(date('Y-m-d H:i:s')) . "'"
        );
    }
}
