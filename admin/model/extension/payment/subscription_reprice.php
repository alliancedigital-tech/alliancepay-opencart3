<?php

declare(strict_types=1);

class ModelExtensionPaymentSubscriptionReprice extends Model
{
    /**
     * @param array $data plan_id, old_version_id, new_version_id, old_rules, new_rules,
     */
    public function insertVersionHistory(array $data): void
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan_version_history` SET "
            . "`plan_id` = '" . (int)$data['plan_id'] . "', "
            . "`old_version_id` = " . (!empty($data['old_version_id']) ? "'" . $this->db->escape((string)$data['old_version_id']) . "'" : "NULL") . ", "
            . "`new_version_id` = " . (!empty($data['new_version_id']) ? "'" . $this->db->escape((string)$data['new_version_id']) . "'" : "NULL") . ", "
            . "`old_rules` = " . (!empty($data['old_rules']) ? "'" . $this->db->escape(json_encode($data['old_rules'])) . "'" : "NULL") . ", "
            . "`new_rules` = '" . $this->db->escape(json_encode($data['new_rules'])) . "', "
            . "`applies_from` = '" . $this->db->escape((string)$data['applies_from']) . "', "
            . "`reason` = " . (!empty($data['reason']) ? "'" . $this->db->escape((string)$data['reason']) . "'" : "NULL") . ", "
            . "`notify_confirmed` = '" . (int)($data['notify_confirmed'] ?? 0) . "', "
            . "`affected_subscriptions_count` = '" . (int)($data['affected_subscriptions_count'] ?? 0) . "', "
            . "`created_by` = '" . (int)$data['created_by'] . "', "
            . "`created_at` = NOW()"
        );
    }

    /**
     * @param int $planId
     */
    public function getVersionHistory(int $planId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_version_history`
             WHERE `plan_id` = '" . (int)$planId . "' ORDER BY `created_at` DESC"
        );

        return $query->rows;
    }
}
