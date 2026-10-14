<?php

declare(strict_types=1);

class ModelExtensionPaymentSubscriptionPlanTemplate extends Model
{
    /**
     * @param array $data
     */
    public function addTemplate(array $data): int
    {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription_plan_template` SET "
            . "`code` = '" . $this->db->escape($data['code']) . "', "
            . "`name` = '" . $this->db->escape($data['name']) . "', "
            . "`category_indicator` = '" . $this->db->escape($data['category_indicator']) . "', "
            . "`rules_template` = '" . $this->db->escape(json_encode($data['rules'])) . "', "
            . "`status` = '" . $this->db->escape(AllianceSubscriptionConfig::TEMPLATE_STATUS_ACTIVE) . "', "
            . "`created_at` = NOW()"
        );

        return (int)$this->db->getLastId();
    }

    /**
     * @param int $templateId
     * @param array $data
     */
    public function editTemplate(int $templateId, array $data): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan_template` SET "
            . "`name` = '" . $this->db->escape($data['name']) . "', "
            . "`category_indicator` = '" . $this->db->escape($data['category_indicator']) . "', "
            . "`rules_template` = '" . $this->db->escape(json_encode($data['rules'])) . "', "
            . "`updated_at` = NOW() "
            . "WHERE `template_id` = '" . (int)$templateId . "'"
        );
    }

    /**
     * @param int $templateId
     */
    public function archiveTemplate(int $templateId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan_template` SET "
            . "`status` = '" . $this->db->escape(AllianceSubscriptionConfig::TEMPLATE_STATUS_ARCHIVED) . "', "
            . "`updated_at` = NOW() "
            . "WHERE `template_id` = '" . (int)$templateId . "'"
        );
    }

    /**
     * @param int $templateId
     */
    public function getTemplate(int $templateId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_template` WHERE `template_id` = '"
            . (int)$templateId . "'"
        );

        return $query->row;
    }

    /**
     * @param string $code
     */
    public function getTemplateByCode(string $code): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_template` WHERE `code` = '"
            . $this->db->escape($code) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $templateId
     */
    public function isTemplateInUse(int $templateId): bool
    {
        $query = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `template_id` = '"
            . (int)$templateId . "'"
        );

        return (int)($query->row['total'] ?? 0) > 0;
    }

    /**
     * @param array $data
     */
    public function getActiveTemplates(): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_template` WHERE `status` = '"
            . $this->db->escape(AllianceSubscriptionConfig::TEMPLATE_STATUS_ACTIVE) . "' ORDER BY `name` ASC"
        );

        return $query->rows;
    }

    /**
     * @param array $data
     */
    public function getTemplates(array $data = []): array
    {
        $sql = "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan_template` WHERE 1=1";

        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '" . $this->db->escape((string)$data['filter_name']) . "%'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND `status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
        }

        $sql .= " ORDER BY `created_at` DESC";

        if (isset($data['start']) || isset($data['limit'])) {
            $start = isset($data['start']) ? (int)$data['start'] : 0;
            $limit = isset($data['limit']) ? (int)$data['limit'] : 20;
            $start = max($start, 0);
            $limit = max($limit, 1);
            $sql .= " LIMIT " . $start . "," . $limit;
        }

        $query = $this->db->query($sql);

        return $query->rows;
    }

    /**
     * @param array $data
     */
    public function getTotalTemplates(array $data = []): int
    {
        $sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan_template` WHERE 1=1";

        if (!empty($data['filter_name'])) {
            $sql .= " AND `name` LIKE '" . $this->db->escape((string)$data['filter_name']) . "%'";
        }

        if (isset($data['filter_status']) && $data['filter_status'] !== '') {
            $sql .= " AND `status` = '" . $this->db->escape((string)$data['filter_status']) . "'";
        }

        $query = $this->db->query($sql);

        return (int)($query->row['total'] ?? 0);
    }
}
