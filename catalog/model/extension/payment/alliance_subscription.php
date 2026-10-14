<?php

declare(strict_types=1);

class ModelExtensionPaymentAllianceSubscription extends Model
{
    /**
     * @param array $address
     */
    public function getMethod(array $address = [])
    {
        $this->load->language('extension/payment/alliance_subscription');
        $this->load->library('AllianceSubscriptionEligibility');

        if (!AllianceSubscriptionEligibility::resolveCartSubscriptionPlan($this->cart->getProducts(), $this->registry)) {
            return [];
        }

        $optionData['alliance_subscription'] = [
            'code' => AllianceSubscriptionConfig::SUBSCRIPTION_PAYMENT_CODE,
            'title' => $this->language->get('text_title'),
        ];

        return [
            'code' => AllianceSubscriptionConfig::SUBSCRIPTION_PAYMENT_CODE,
            'title' => $this->language->get('heading_title'),
            'option' => $optionData,
            'sort_order' => $this->config->get('payment_alliance_subscription_sort_order'),
        ];
    }

    /**
     * @param int $orderId
     */
    public function reserveMerchantRequestId(int $orderId): string
    {
        if (!isset($this->session->data['alliance_subscription_merchant_request_id'])) {
            $this->session->data['alliance_subscription_merchant_request_id'] = [];
        }

        if (empty($this->session->data['alliance_subscription_merchant_request_id'][$orderId])) {
            $this->session->data['alliance_subscription_merchant_request_id'][$orderId] = uniqid('sub_', true);
        }

        return $this->session->data['alliance_subscription_merchant_request_id'][$orderId];
    }

    /**
     * @param array $providerResponse
     * @param int $orderId
     * @param int $planId
     * @param array $rulesSnapshot
     * @param string $merchantRequestId
     * @param int $customerId
     */
    public function saveSubscription(
        array $providerResponse,
        int $orderId,
        int $planId,
        array $rulesSnapshot,
        string $merchantRequestId,
        int $customerId
    ): void {
        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_subscription` SET "
            . "`order_id` = '" . (int)$orderId . "', "
            . "`plan_id` = '" . (int)$planId . "', "
            . "`customer_id` = '" . (int)$customerId . "', "
            . "`provider_order_id` = '" . $this->db->escape((string)$providerResponse['hppOrderId']) . "', "
            . "`merchant_request_id` = '" . $this->db->escape($merchantRequestId) . "', "
            . "`rules_snapshot` = '" . $this->db->escape(json_encode($rulesSnapshot)) . "', "
            . "`status` = 'PENDING', "
            . "`created_at` = NOW()"
        );

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`active_subscriptions_count` = `active_subscriptions_count` + 1, `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );

        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory($orderId, (int)$this->config->get('payment_alliance_pending_status'));
    }

    /**
     * @param int $subscriptionId
     */
    public function getSubscription(int $subscriptionId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription` WHERE `subscription_id` = '" . (int)$subscriptionId . "'"
        );

        return $query->row;
    }

    /**
     * @param string $providerOrderId
     */
    public function getSubscriptionByProviderOrderId(string $providerOrderId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription` WHERE `provider_order_id` = '"
            . $this->db->escape($providerOrderId) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $customerId
     */
    public function getCustomerSubscriptions(int $customerId): array
    {
        $query = $this->db->query(
            "SELECT s.*, p.name AS plan_name, pd.name AS product_name FROM `" . DB_PREFIX . "alliance_subscription` s "
            . "LEFT JOIN `" . DB_PREFIX . "alliance_subscription_plan` p ON (p.plan_id = s.plan_id) "
            . "LEFT JOIN `" . DB_PREFIX . "product_description` pd ON (pd.product_id = p.product_id AND pd.language_id = '"
            . (int)$this->config->get('config_language_id') . "') "
            . "WHERE s.customer_id = '" . (int)$customerId . "' ORDER BY s.created_at DESC"
        );

        return $query->rows;
    }

    /**
     * @param int $customerId
     * @param int $subscriptionId
     */
    public function cancelOwnSubscription(int $customerId, int $subscriptionId): array
    {
        $this->load->language('extension/payment/alliance_subscription');

        $subscription = $this->getSubscription($subscriptionId);

        if (empty($subscription) || (int)$subscription['customer_id'] !== $customerId) {
            return ['success' => false, 'message' => $this->language->get('error_subscription_not_found')];
        }

        try {
            $this->load->library('AllianceEcom');
            $ecom = new AllianceEcom($this->registry);
            $ecom->cancelSubscriptionOrder(
                $subscription['provider_order_id'],
                (string)$this->config->get('payment_alliance_merchant_id')
            );

            $this->updateSubscriptionStatus($subscriptionId, AllianceSubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED);
            $this->decrementPlanActiveCount((int)$subscription['plan_id']);

            return ['success' => true, 'message' => $this->language->get('text_cancel_success')];
        } catch (\Throwable $e) {
            $this->load->library('AllianceLogger');
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Customer subscription cancel error: ' . $e->getMessage());

            return ['success' => false, 'message' => $this->language->get('error_something_went_wrong')];
        }
    }

    /**
     * @param int $subscriptionId
     * @param string $status
     */
    public function updateSubscriptionStatus(int $subscriptionId, string $status): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET `status` = '" . $this->db->escape($status)
            . "', `updated_at` = NOW() WHERE `subscription_id` = '" . (int)$subscriptionId . "'"
        );
    }

    /**
     * @param int $planId
     */
    private function decrementPlanActiveCount(int $planId): void
    {
        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription_plan` SET "
            . "`active_subscriptions_count` = GREATEST(`active_subscriptions_count` - 1, 0), `updated_at` = NOW() "
            . "WHERE `plan_id` = '" . (int)$planId . "'"
        );
    }

    /**
     * @param array $payload
     */
    public function processCallback(array $payload): void
    {
        $this->load->library('AllianceLogger');
        $this->load->library('AllianceSubscriptionHelper');
        $logger = new AllianceLogger($this->registry);

        $hppOrderId = (string)($payload['hppOrderId'] ?? '');

        if ($hppOrderId === '') {
            $logger->log(AllianceLogger::WARNING, 'Subscription callback missing hppOrderId: ' . json_encode($payload));
            return;
        }

        $subscription = $this->getSubscriptionByProviderOrderId($hppOrderId);

        if (empty($subscription)) {
            $logger->log(
                AllianceLogger::WARNING,
                'Subscription callback for unknown hppOrderId ' . $hppOrderId . ': ' . json_encode($payload)
            );
            return;
        }

        $isInitial = AllianceSubscriptionHelper::isInitialPayment($payload);

        if (!empty($payload['scheduledPayments']) && is_array($payload['scheduledPayments'])) {
            foreach ($payload['scheduledPayments'] as $entry) {
                $this->upsertScheduledPaymentFromScheduleEntry((int)$subscription['subscription_id'], $hppOrderId, $entry);
            }
        }

        $row = $this->resolveTargetScheduledPaymentRow(
            (int)$subscription['subscription_id'],
            $hppOrderId,
            $payload,
            $isInitial
        );

        $scalarFieldMap = [
            'scheduledDate' => 'scheduled_date',
            'operation.executedAt' => 'executed_at',
        ];

        $merged = AllianceSubscriptionHelper::mergeWebhookPayload($row, $payload, $scalarFieldMap);
        $mergedRow = $merged['row'];
        $isNewOperation = $merged['is_new_operation'];

        $rawStatus = $payload['operation']['status'] ?? $payload['orderStatus'] ?? null;

        if ($rawStatus !== null) {
            try {
                $mergedRow['status'] = AllianceSubscriptionHelper::resolveAtypicalStatus((string)$rawStatus);
            } catch (\Throwable $e) {
                $logger->log(AllianceLogger::ERROR, 'Subscription callback status error: ' . $e->getMessage());
            }
        }

        if ($isNewOperation) {
            $this->applyNewOperationEffects($subscription, $mergedRow, $payload, $isInitial, $logger);
        }

        $this->persistScheduledPaymentRow($mergedRow, (int)$subscription['subscription_id'], $hppOrderId, $merged['operations']);

        $this->db->query(
            "UPDATE `" . DB_PREFIX . "alliance_subscription` SET `callback_data` = '"
            . $this->db->escape(json_encode($payload)) . "', `updated_at` = NOW() WHERE `subscription_id` = '"
            . (int)$subscription['subscription_id'] . "'"
        );
    }

    /**
     * @param int $subscriptionId
     * @param string $hppOrderId
     * @param array $entry
     */
    private function upsertScheduledPaymentFromScheduleEntry(int $subscriptionId, string $hppOrderId, array $entry): void
    {
        $scheduledPaymentId = $entry['scheduledPaymentId'] ?? null;

        $existing = $scheduledPaymentId !== null
            ? $this->findScheduledPaymentRow($subscriptionId, (string)$scheduledPaymentId)
            : [];

        if (!empty($existing)) {
            return;
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_scheduled_payment` SET "
            . "`subscription_id` = '" . (int)$subscriptionId . "', "
            . "`provider_order_id` = '" . $this->db->escape($hppOrderId) . "', "
            . "`scheduled_payment_id` = " . ($scheduledPaymentId !== null ? "'" . $this->db->escape((string)$scheduledPaymentId) . "'" : "NULL") . ", "
            . "`payment_number` = " . (isset($entry['paymentNumber']) ? (int)$entry['paymentNumber'] : "NULL") . ", "
            . "`status` = '" . $this->db->escape((string)($entry['status'] ?? AllianceSubscriptionConfig::SCHEDULED_STATUS_WAITING)) . "', "
            . "`scheduled_date` = " . (!empty($entry['scheduledDate']) ? "'" . $this->db->escape((string)$entry['scheduledDate']) . "'" : "NULL") . ", "
            . "`created_at` = NOW()"
        );
    }

    /**
     * @param int $subscriptionId
     * @param string $scheduledPaymentId
     */
    private function findScheduledPaymentRow(int $subscriptionId, string $scheduledPaymentId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment` WHERE `subscription_id` = '"
            . (int)$subscriptionId . "' AND `scheduled_payment_id` = '" . $this->db->escape($scheduledPaymentId) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionId
     * @param string $operationId
     */
    private function findScheduledPaymentRowByOperationId(int $subscriptionId, string $operationId): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment` WHERE `subscription_id` = '"
            . (int)$subscriptionId . "' AND `operation_id` = '" . $this->db->escape($operationId) . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionId
     * @param int $paymentNumber
     */
    private function findScheduledPaymentRowByPaymentNumber(int $subscriptionId, int $paymentNumber): array
    {
        $query = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_scheduled_payment` WHERE `subscription_id` = '"
            . (int)$subscriptionId . "' AND `payment_number` = '" . (int)$paymentNumber . "'"
        );

        return $query->row;
    }

    /**
     * @param int $subscriptionId
     * @param string $hppOrderId
     * @param array $payload
     * @param bool $isInitial
     */
    private function resolveTargetScheduledPaymentRow(int $subscriptionId, string $hppOrderId, array $payload, bool $isInitial): array
    {
        if (!empty($payload['scheduledPaymentId'])) {
            $row = $this->findScheduledPaymentRow($subscriptionId, (string)$payload['scheduledPaymentId']);
            if (!empty($row)) {
                return $row;
            }
        }

        $operationId = $payload['operation']['operationId'] ?? $payload['ecomOperationId'] ?? null;

        if ($operationId !== null) {
            $row = $this->findScheduledPaymentRowByOperationId($subscriptionId, (string)$operationId);
            if (!empty($row)) {
                return $row;
            }
        }

        if ($isInitial) {
            $row = $this->findScheduledPaymentRowByPaymentNumber($subscriptionId, 1);
            if (!empty($row)) {
                return $row;
            }

            return [
                'subscription_id' => $subscriptionId,
                'provider_order_id' => $hppOrderId,
                'scheduled_payment_id' => $payload['scheduledPaymentId'] ?? null,
                'payment_number' => 1,
            ];
        }

        return [
            'subscription_id' => $subscriptionId,
            'provider_order_id' => $hppOrderId,
            'scheduled_payment_id' => $payload['scheduledPaymentId'] ?? null,
            'payment_number' => null,
        ];
    }

    /**
     * @param array $subscription
     * @param array $row
     * @param array $payload
     * @param bool $isInitial
     * @param AllianceLogger $logger
     */
    private function applyNewOperationEffects(array $subscription, array &$row, array $payload, bool $isInitial, AllianceLogger $logger): void
    {
        $amount = AllianceSubscriptionHelper::extractCallbackAmount($payload);

        if ($amount !== null) {
            $row['actual_coin_amount'] = $amount;

            $expected = $this->resolveExpectedAmount($subscription, (int)($row['payment_number'] ?? 0));

            if ($expected !== null && $expected !== $amount) {
                $row['amount_mismatch_flag'] = 1;
                $logger->log(
                    AllianceLogger::WARNING,
                    'Subscription #' . $subscription['subscription_id'] . ' amount mismatch: expected '
                    . $expected . ', got ' . $amount . ' - flagged for admin review, charge NOT blocked.'
                );
            }
        }

        $status = $row['status'] ?? null;

        if ($isInitial) {
            $this->reflectInitialPaymentOnOrder($subscription, $status);
        }

        if (in_array($status, [AllianceSubscriptionConfig::SCHEDULED_STATUS_INVALIDATED], true)) {
            $this->updateSubscriptionStatus((int)$subscription['subscription_id'], AllianceSubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED);
            $this->decrementPlanActiveCount((int)$subscription['plan_id']);
        } elseif ($isInitial && $status === AllianceSubscriptionConfig::SCHEDULED_STATUS_SUCCESS) {
            $this->updateSubscriptionStatus((int)$subscription['subscription_id'], AllianceSubscriptionConfig::SUBSCRIPTION_STATUS_ACTIVE);
        }
    }

    /**
     * @param array $subscription
     * @param int $paymentNumber
     */
    private function resolveExpectedAmount(array $subscription, int $paymentNumber): ?int
    {
        if ($paymentNumber <= 0 || empty($subscription['rules_snapshot'])) {
            return null;
        }

        $rules = json_decode((string)$subscription['rules_snapshot'], true);

        if (!is_array($rules)) {
            return null;
        }

        foreach ($rules as $rule) {
            $from = (int)($rule['paymentNumberFrom'] ?? 0);
            $to = (int)($rule['paymentNumberTo'] ?? 0);

            if ($paymentNumber >= $from && $paymentNumber <= $to && isset($rule['coinAmount'])) {
                return (int)$rule['coinAmount'];
            }
        }

        return null;
    }

    /**
     * @param array $subscription
     * @param string|null $status
     */
    private function reflectInitialPaymentOnOrder(array $subscription, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        $this->load->model('checkout/order');
        $orderId = (int)$subscription['order_id'];

        if ($status === AllianceSubscriptionConfig::SCHEDULED_STATUS_SUCCESS) {
            $this->model_checkout_order->addOrderHistory($orderId, (int)$this->config->get('payment_alliance_paid_status'));
        } elseif ($status === AllianceSubscriptionConfig::SCHEDULED_STATUS_WAITING
            || $status === AllianceSubscriptionConfig::SCHEDULED_STATUS_PROCESSING
        ) {
            $this->model_checkout_order->addOrderHistory($orderId, (int)$this->config->get('payment_alliance_pending_status'));
        } elseif ($status === AllianceSubscriptionConfig::SCHEDULED_STATUS_FAIL) {
            $this->model_checkout_order->addOrderHistory($orderId, (int)$this->config->get('payment_alliance_error_status'));
        }
    }

    /**
     * @param array $row
     * @param int $subscriptionId
     * @param string $hppOrderId
     * @param array $operations
     */
    private function persistScheduledPaymentRow(array $row, int $subscriptionId, string $hppOrderId, array $operations): void
    {
        $rowWithOperations = $row;
        $rowWithOperations['operations'] = $operations;
        $rawCallbackData = json_encode($rowWithOperations['raw_callback_data'] ?? $rowWithOperations);

        if (!empty($row['scheduled_payment_row_id'])) {
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "alliance_scheduled_payment` SET "
                . "`status` = '" . $this->db->escape((string)($row['status'] ?? 'WAITING')) . "', "
                . "`scheduled_date` = " . (!empty($row['scheduled_date']) ? "'" . $this->db->escape((string)$row['scheduled_date']) . "'" : "NULL") . ", "
                . "`executed_at` = " . (!empty($row['executed_at']) ? "'" . $this->db->escape((string)$row['executed_at']) . "'" : "NULL") . ", "
                . "`operation_id` = " . (!empty($row['operation_id']) ? "'" . $this->db->escape((string)$row['operation_id']) . "'" : "NULL") . ", "
                . "`actual_coin_amount` = " . (isset($row['actual_coin_amount']) ? (int)$row['actual_coin_amount'] : "NULL") . ", "
                . "`amount_mismatch_flag` = '" . (!empty($row['amount_mismatch_flag']) ? 1 : 0) . "', "
                . "`raw_callback_data` = '" . $this->db->escape(json_encode(['operations' => $operations])) . "', "
                . "`updated_at` = NOW() "
                . "WHERE `scheduled_payment_row_id` = '" . (int)$row['scheduled_payment_row_id'] . "'"
            );

            return;
        }

        $scheduledPaymentId = $row['scheduled_payment_id'] ?? null;

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliance_scheduled_payment` SET "
            . "`subscription_id` = '" . (int)$subscriptionId . "', "
            . "`provider_order_id` = '" . $this->db->escape($hppOrderId) . "', "
            . "`scheduled_payment_id` = " . ($scheduledPaymentId !== null ? "'" . $this->db->escape((string)$scheduledPaymentId) . "'" : "NULL") . ", "
            . "`payment_number` = " . (isset($row['payment_number']) && $row['payment_number'] !== null ? (int)$row['payment_number'] : "NULL") . ", "
            . "`status` = '" . $this->db->escape((string)($row['status'] ?? 'WAITING')) . "', "
            . "`actual_coin_amount` = " . (isset($row['actual_coin_amount']) ? (int)$row['actual_coin_amount'] : "NULL") . ", "
            . "`amount_mismatch_flag` = '" . (!empty($row['amount_mismatch_flag']) ? 1 : 0) . "', "
            . "`raw_callback_data` = '" . $this->db->escape(json_encode(['operations' => $operations])) . "', "
            . "`created_at` = NOW()"
        );
    }
}
