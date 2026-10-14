<?php

declare(strict_types=1);

class ModelExtensionPaymentSubscriptionPlan extends Model
{
    /**
     * @param string $planKey
     */
    public function getPlanByPlanKey(string $planKey): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `plan_key` = '"
            . $this->db->escape($planKey) . "'"
        );

        return $query->row;
    }

    /**
     * @param string $providerPlanId
     */
    public function getPlanByProviderId(string $providerPlanId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `provider_plan_id` = '"
            . $this->db->escape($providerPlanId) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $planId
     */
    public function getPlan(int $planId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `plan_id` = '" . (int)$planId . "'"
        );

        return $query->row;
    }

    /**
     * @param int $productId
     */
    public function getActivePlanByProduct(int $productId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `product_id` = '"
            . (int)$productId . "' AND `is_active` = '1' AND `status` = 'ACTIVE' LIMIT 1"
        );

        return $query->row;
    }

    /**
     * @param array $data
     */
    public function insertActivePlan(array $data): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`provider_plan_id` = '" . $this->db->escape($data['provider_plan_id']) . "', "
            . "`version_id` = '" . $this->db->escape((string)$data['version_id']) . "', "
            . "`product_id` = '" . (int)$data['product_id'] . "', "
            . "`template_id` = " . (isset($data['template_id']) && $data['template_id'] ? "'" . (int)$data['template_id'] . "'" : "NULL") . ", "
            . "`plan_key` = '" . $this->db->escape($data['plan_key']) . "', "
            . "`merchant_id` = '" . $this->db->escape($data['merchant_id']) . "', "
            . "`name` = '" . $this->db->escape($data['name']) . "', "
            . "`description` = '" . $this->db->escape((string)($data['description'] ?? '')) . "', "
            . "`category_indicator` = '" . $this->db->escape($data['category_indicator']) . "', "
            . "`rules` = '" . $this->db->escape(json_encode($data['rules'])) . "', "
            . "`applies_from` = " . (!empty($data['applies_from']) ? "'" . $this->db->escape($data['applies_from']) . "'" : "NULL") . ", "
            . "`applies_to` = " . (!empty($data['applies_to']) ? "'" . $this->db->escape($data['applies_to']) . "'" : "NULL") . ", "
            . "`status` = 'ACTIVE', "
            . "`is_active` = '1', "
            . "`active_subscriptions_count` = '0', "
            . "`sync_status` = 'SYNCED', "
            . "`sync_error` = NULL, "
            . "`infinite_schedule_confirmed` = '1', "
            . "`created_at` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param array $data
     */
    public function insertErrorPlan(array $data): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`provider_plan_id` = '" . $this->db->escape($data['provider_plan_id']) . "', "
            . "`version_id` = " . (!empty($data['version_id']) ? "'" . $this->db->escape((string)$data['version_id']) . "'" : "NULL") . ", "
            . "`product_id` = '" . (int)$data['product_id'] . "', "
            . "`template_id` = " . (isset($data['template_id']) && $data['template_id'] ? "'" . (int)$data['template_id'] . "'" : "NULL") . ", "
            . "`plan_key` = '" . $this->db->escape($data['plan_key']) . "', "
            . "`merchant_id` = '" . $this->db->escape($data['merchant_id']) . "', "
            . "`name` = '" . $this->db->escape($data['name']) . "', "
            . "`description` = '" . $this->db->escape((string)($data['description'] ?? '')) . "', "
            . "`category_indicator` = '" . $this->db->escape($data['category_indicator']) . "', "
            . "`rules` = '" . $this->db->escape(json_encode($data['rules'])) . "', "
            . "`status` = 'ERROR', "
            . "`is_active` = '0', "
            . "`active_subscriptions_count` = '0', "
            . "`sync_status` = 'ERROR', "
            . "`sync_error` = '" . $this->db->escape((string)$data['sync_error']) . "', "
            . "`infinite_schedule_confirmed` = '0', "
            . "`created_at` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param int $planId
     */
    public function deactivateForNewCustomers(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET `is_active` = '0', `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     */
    public function reactivateForNewCustomers(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET `is_active` = '1', `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     */
    public function markArchived(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET `status` = 'ARCHIVED', `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     * @param array $data
     */
    public function applyReprice(int $planId, array $data): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`version_id` = '" . $this->db->escape((string)$data['version_id']) . "', "
            . "`rules` = '" . $this->db->escape(json_encode($data['rules'])) . "', "
            . "`applies_from` = '" . $this->db->escape($data['applies_from']) . "', "
            . "`infinite_schedule_confirmed` = '1', "
            . "`sync_status` = 'SYNCED', "
            . "`sync_error` = NULL, "
            . "`updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     * @param string $syncError
     */
    public function markRepriceFailed(int $planId, string $syncError): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`status` = 'ERROR', `sync_status` = 'ERROR', "
            . "`sync_error` = '" . $this->db->escape($syncError) . "', `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     */
    public function incrementActiveCount(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`active_subscriptions_count` = `active_subscriptions_count` + 1, `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param int $planId
     */
    public function decrementActiveCount(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`active_subscriptions_count` = GREATEST(`active_subscriptions_count` - 1, 0), `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param array $data
     */
    public function insertAuditLog(array $data): void
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_audit_log` SET "
            . "`action` = '" . $this->db->escape((string)$data['action']) . "', "
            . "`plan_id` = " . (!empty($data['plan_id']) ? "'" . (int)$data['plan_id'] . "'" : "NULL") . ", "
            . "`subscription_id` = " . (!empty($data['subscription_id']) ? "'" . (int)$data['subscription_id'] . "'" : "NULL") . ", "
            . "`product_id` = " . (!empty($data['product_id']) ? "'" . (int)$data['product_id'] . "'" : "NULL") . ", "
            . "`user_id` = " . (!empty($data['user_id']) ? "'" . (int)$data['user_id'] . "'" : "NULL") . ", "
            . "`details` = " . (isset($data['details']) ? "'" . $this->db->escape(json_encode($data['details'])) . "'" : "NULL") . ", "
            . "`created_at` = NOW()"
        );
    }

    /**
     * @param array $data
     */
    public function getPlans(array $data = []): array
    {
        $sql = "SELECT p.*, pd.name AS product_name FROM `" . DB_PREFIX . "alliance_subscription_plan` p "
            . "LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '"
            . (int)$this->config->get('config_language_id') . "') WHERE 1=1";

        if (!empty($data['filter_product_id'])) {
            $sql .= " AND p.product_id = '" . (int)$data['filter_product_id'] . "'";
        }

        if (!empty($data['filter_status'])) {
            $sql .= " AND p.status = '" . $this->db->escape((string)$data['filter_status']) . "'";
        }

        if (isset($data['filter_sync_status']) && $data['filter_sync_status'] !== '') {
            $sql .= " AND p.sync_status = '" . $this->db->escape((string)$data['filter_sync_status']) . "'";
        }

        $sql .= " ORDER BY p.created_at DESC";

        if (isset($data['start']) || isset($data['limit'])) {
            $start = max((int)($data['start'] ?? 0), 0);
            $limit = max((int)($data['limit'] ?? 20), 1);
            $sql .= " LIMIT " . $start . "," . $limit;
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param array $data
     */
    public function getTotalPlans(array $data = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan` p WHERE 1=1";

        if (!empty($data['filter_product_id'])) {
            $sql .= " AND p.product_id = '" . (int)$data['filter_product_id'] . "'";
        }

        if (!empty($data['filter_status'])) {
            $sql .= " AND p.status = '" . $this->db->escape((string)$data['filter_status']) . "'";
        }

        if (isset($data['filter_sync_status']) && $data['filter_sync_status'] !== '') {
            $sql .= " AND p.sync_status = '" . $this->db->escape((string)$data['filter_sync_status']) . "'";
        }

        $query = $this->db->query($sql);

        return (int)($query->row['total'] ?? 0);
    }

    /**
     * @param int $planId
     */
    public function getActiveSubscriptionCountForPlan(int $planId): int
    {
        $query = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription` WHERE `plan_id` = '"
            . (int)$planId . "' AND `status` = 'ACTIVE'"
        );

        return (int)($query->row['total'] ?? 0);
    }

    /**
     * @param int $productId
     */
    public function getProductName(int $productId): string
    {
        $query = $this->db->query(
            "SELECT `name` FROM `" . DB_PREFIX . "product_description` WHERE `product_id` = '"
            . (int)$productId . "' AND `language_id` = '" . (int)$this->config->get('config_language_id') . "'"
        );

        return (string)($query->row['name'] ?? '');
    }

    /**
     * @param array $plan
     */
    public function isArchiveCandidate(array $plan): bool
    {
        return (int)($plan['is_active'] ?? 1) === 0
            && (int)($plan['active_subscriptions_count'] ?? 1) === 0
            && ($plan['status'] ?? '') !== AllianceSubscriptionConfig::PLAN_STATUS_ARCHIVED;
    }
}
