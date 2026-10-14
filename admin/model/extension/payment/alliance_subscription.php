<?php

declare(strict_types=1);

class ModelExtensionPaymentAllianceSubscription extends Model
{

    public function getDashboardCounts(): array
    {
        $activePlans = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `status` = 'ACTIVE'"
        );

        $activeSubscriptions = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription` WHERE `status` = 'ACTIVE'"
        );

        $errorPlans = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `status` = 'ERROR'"
        );

        $archiveCandidates = $this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "alliance_subscription_plan`
             WHERE `is_active` = '0' AND `active_subscriptions_count` = '0' AND `status` != 'ARCHIVED'"
        );

        return [
            'active_plans' => (int)($activePlans->row['total'] ?? 0),
            'active_subscriptions' => (int)($activeSubscriptions->row['total'] ?? 0),
            'error_plans' => (int)($errorPlans->row['total'] ?? 0),
            'archive_candidates' => (int)($archiveCandidates->row['total'] ?? 0),
        ];
    }
}
