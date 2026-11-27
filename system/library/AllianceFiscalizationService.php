<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

require_once __DIR__ . '/AllianceFiscalizationException.php';
require_once __DIR__ . '/AllianceFiscalizationRepository.php';
require_once __DIR__ . '/AllianceFiscalizationResolver.php';
require_once __DIR__ . '/AllianceFiscalizationBuilder.php';

/**
 * Class AllianceFiscalizationService.
 *
 * Orchestrates fiscalization of payments (PURCHASE) and refunds (REFUND).
 * Never throws to callers: all failures are stored in the fiscalization record and returned as a result array.
 */
class AllianceFiscalizationService
{
    public const TYPE_PURCHASE = 'PURCHASE';
    public const TYPE_REFUND = 'REFUND';

    private $registry;

    private $db;

    private $config;

    private $logger;

    private $ecom;

    private $repository;

    private $resolver;

    private $builder;

    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->registry = $registry;
        $this->db = $registry->get('db');
        $this->config = $registry->get('config');

        $loader = $registry->get('load');
        $loader->library('AllianceConfig');
        $loader->library('AllianceHelper');
        $loader->library('AllianceLogger');
        $loader->library('AllianceEcom');
        $loader->library('AllianceRequestIdentification');

        $this->logger = new AllianceLogger($registry);
        $this->ecom = new AllianceEcom($registry);
        $this->repository = new AllianceFiscalizationRepository($registry);
        $this->resolver = new AllianceFiscalizationResolver($this->config, $this->repository);
        $this->builder = new AllianceFiscalizationBuilder($registry, $this->resolver);
    }

    /**
     * @return AllianceFiscalizationRepository
     */
    public function getRepository(): AllianceFiscalizationRepository
    {
        return $this->repository;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (string)$this->config->get('payment_alliance_fiscalization_enabled') === '1';
    }

    /**
     * @return bool
     */
    public function isAutoEnabled(): bool
    {
        return $this->isEnabled() && (string)$this->config->get('payment_alliance_fiscalization_auto') === '1';
    }

    /**
     * Fiscalizes the successful payment of an order (manual button).
     *
     * @param int $orderId
     * @return array ['success' => bool, 'message' => string, 'status' => string]
     */
    public function fiscalizePurchase(int $orderId): array
    {
        return $this->guard(function () use ($orderId) {
            return $this->processPurchase($orderId);
        });
    }

    /**
     * Automatic fiscalization after a successful payment. Does nothing when auto mode is off.
     *
     * @param int $orderId
     * @return void
     */
    public function autoFiscalizePurchase(int $orderId): void
    {
        if (!$this->isAutoEnabled()) {
            return;
        }

        $this->guard(function () use ($orderId) {
            return $this->processPurchase($orderId);
        });
    }

    /**
     * Registers (and, in auto mode, sends) fiscalization of a successful refund.
     *
     * @param int $orderId
     * @param array $refundResult Ecom refund response (operationId, status, coinAmount)
     * @param array $orderProductIds order_product_id of refunded products (ignored for full refund)
     * @param bool $isFullRefund
     * @param bool $refundShipping
     * @return void
     */
    public function registerRefund(
        int $orderId,
        array $refundResult,
        array $orderProductIds,
        bool $isFullRefund,
        bool $refundShipping
    ): void {
        if (!$this->isEnabled()) {
            return;
        }

        $this->guard(function () use ($orderId, $refundResult, $orderProductIds, $isFullRefund, $refundShipping) {
            $operationId = (string)($refundResult['operationId'] ?? '');
            $refundStatus = strtoupper((string)($refundResult['status'] ?? ''));

            if ($operationId === '' || !in_array($refundStatus, [
                AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS,
                'PENDING',
            ], true)) {
                return ['success' => false, 'message' => '', 'status' => ''];
            }

            $record = $this->repository->findByOperationId($operationId);
            if ($record) {
                return $this->resultFromRecord($record, '');
            }

            $allianceOrder = $this->getAllianceOrder($orderId);
            try {
                $built = $this->builder->build(
                    $orderId,
                    (float)($allianceOrder['conversion_rate'] ?? 0),
                    null,
                    $isFullRefund ? null : array_map('intval', $orderProductIds),
                    $isFullRefund || $refundShipping
                );
            } catch (AllianceFiscalizationException $e) {
                $record = $this->saveLocalError($orderId, $operationId, self::TYPE_REFUND, $e->getMessage());

                return $this->resultFromRecord($record, $e->getMessage());
            }

            $record = $this->repository->save([
                'order_id' => $orderId,
                'store_id' => 0,
                'operation_id' => $operationId,
                'operation_type' => self::TYPE_REFUND,
                'merchant_request_id' => AllianceRequestIdentification::generateRequestId(),
                'status' => AllianceFiscalizationRepository::STATUS_NEW,
                'receipt_comment' => 'REFUND_FOR_ORDER_' . $orderId,
                'request_payload' => json_encode(
                    $this->buildBody($orderId, $operationId, self::TYPE_REFUND, $built['products']),
                    JSON_UNESCAPED_UNICODE
                ),
            ]);

            if (!$this->isAutoEnabled() || $refundStatus !== AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS) {
                return $this->resultFromRecord($record, '');
            }

            return $this->sendRecord($record);
        });
    }

    /**
     * @param int $orderId
     * @param array $operation
     * @return void
     */
    public function onRefundOperation(int $orderId, array $operation): void
    {
        $operationId = (string)($operation['operationId'] ?? $operation['operation_id'] ?? '');
        $status = strtoupper((string)($operation['status'] ?? ''));
        $this->logger->info(sprintf(
            'Fiscalization refund callback: order %d, operation %s, status %s, enabled=%s, auto=%s',
            $orderId,
            $operationId,
            $status,
            $this->isEnabled() ? 'yes' : 'no',
            $this->isAutoEnabled() ? 'yes' : 'no'
        ));

        if (!$this->isEnabled() || $operationId === ''
            || $status !== AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS
        ) {
            return;
        }

        $this->guard(function () use ($orderId, $operationId, $operation) {
            $record = $this->repository->findByOperationId($operationId);
            if (!$record) {
                $record = $this->createRefundRecordFromHistory(
                    $orderId,
                    $operationId,
                    (int)($operation['coinAmount'] ?? 0)
                );
            }

            $retryable = in_array(
                $record['status'],
                [AllianceFiscalizationRepository::STATUS_NEW, AllianceFiscalizationRepository::STATUS_ERROR],
                true
            );
            if (!$this->isAutoEnabled() || !$retryable) {
                return $this->resultFromRecord($record, '');
            }

            return $this->sendRecord($record);
        });
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @return array
     */
    public function fiscalizeRefund(int $orderId, string $operationId): array
    {
        return $this->guard(function () use ($orderId, $operationId) {
            if (!$this->isEnabled()) {
                return $this->fail($this->msg('disabled', 'Фіскалізація вимкнена в налаштуваннях модуля.'));
            }

            $refund = $this->findRefundOperation($orderId, $operationId);
            if (!$refund) {
                return $this->fail($this->msg('refund_not_found', 'Повернення не знайдено.'));
            }

            $record = $this->repository->findByOperationId($operationId);
            if (!$record) {
                $record = $this->createRefundRecordFromHistory($orderId, $operationId, (int)$refund['coin_amount']);
            }

            if (!in_array(
                $record['status'],
                [AllianceFiscalizationRepository::STATUS_NEW, AllianceFiscalizationRepository::STATUS_ERROR],
                true
            )) {
                return $this->fail($this->msg(
                    'already',
                    'Фіскалізація для цієї операції вже виконується або завершена (статус %s).',
                    [$record['status']]
                ));
            }

            return $this->sendRecord($record);
        });
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function findRefundsWithoutRecord(int $orderId): array
    {
        $rows = $this->db->query(
            "SELECT `operation_id`, `coin_amount`, `status` FROM `" . DB_PREFIX
            . "alliance_integration_order_refund` WHERE `order_id` = '" . $orderId . "'"
        )->rows;

        return array_values(array_filter($rows, function (array $row) {
            return (string)$row['operation_id'] !== ''
                && !$this->repository->findByOperationId((string)$row['operation_id']);
        }));
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @return array
     */
    private function findRefundOperation(int $orderId, string $operationId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_integration_order_refund` WHERE `order_id` = '" . $orderId
            . "' AND `operation_id` = '" . $this->db->escape($operationId) . "'"
        )->row;
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @param int $coinAmount
     * @return array
     */
    private function createRefundRecordFromHistory(int $orderId, string $operationId, int $coinAmount): array
    {
        $refund = $this->findRefundOperation($orderId, $operationId);
        $coinAmount = $coinAmount > 0 ? $coinAmount : (int)($refund['coin_amount'] ?? 0);
        $original = (int)($refund['original_coin_amount'] ?? 0);
        $isFull = $original > 0 && $coinAmount >= $original;

        $orderProductIds = null;
        if (!$isFull) {
            $rows = $this->db->query(
                "SELECT op.`order_product_id` FROM `" . DB_PREFIX . "return` r JOIN `" . DB_PREFIX
                . "order_product` op ON op.`order_id` = r.`order_id` AND op.`product_id` = r.`product_id` "
                . "WHERE r.`order_id` = '" . $orderId . "'"
            )->rows;
            $orderProductIds = array_map('intval', array_column($rows, 'order_product_id'));
        }

        $allianceOrder = $this->getAllianceOrder($orderId);
        try {
            $built = $this->builder->build(
                $orderId,
                (float)($allianceOrder['conversion_rate'] ?? 0),
                $coinAmount > 0 ? $coinAmount : null,
                $orderProductIds,
                $isFull
            );
        } catch (AllianceFiscalizationException $e) {
            return $this->saveLocalError(
                $orderId,
                $operationId,
                self::TYPE_REFUND,
                $this->msg($e->getMessageKey(), $e->getMessage(), $e->getMessageArgs())
            );
        }

        return $this->repository->save([
            'order_id' => $orderId,
            'store_id' => 0,
            'operation_id' => $operationId,
            'operation_type' => self::TYPE_REFUND,
            'merchant_request_id' => AllianceRequestIdentification::generateRequestId(),
            'status' => AllianceFiscalizationRepository::STATUS_NEW,
            'receipt_comment' => 'REFUND_FOR_ORDER_' . $orderId,
            'request_payload' => json_encode(
                $this->buildBody($orderId, $operationId, self::TYPE_REFUND, $built['products']),
                JSON_UNESCAPED_UNICODE
            ),
        ]);
    }

    /**
     * Sends a stored NEW/ERROR record again, or refreshes a PENDING/SUCCESS/FAIL one (idempotent on Ecom side).
     *
     * @param int $recordId
     * @param bool $refresh true → "Оновити статус"
     * @return array
     */
    public function resend(int $recordId, bool $refresh = false): array
    {
        return $this->guard(function () use ($recordId, $refresh) {
            $record = $this->repository->findById($recordId);
            if (!$record) {
                return $this->fail('Запис фіскалізації не знайдено.');
            }

            if (!$this->isEnabled()) {
                return $this->fail($this->msg('disabled', 'Фіскалізація вимкнена в налаштуваннях модуля.'));
            }

            $retryable = in_array(
                $record['status'],
                [AllianceFiscalizationRepository::STATUS_NEW, AllianceFiscalizationRepository::STATUS_ERROR],
                true
            );

            if (!$retryable && !$refresh) {
                return $this->fail($this->msg(
                    'already',
                    'Фіскалізація для цієї операції вже виконується або завершена (статус %s).',
                    [$record['status']]
                ));
            }

            if ($record['operation_type'] === self::TYPE_PURCHASE && $retryable) {
                return $this->processPurchase((int)$record['order_id']);
            }

            return $this->sendRecord($record);
        });
    }

    /**
     * Applies a verified callback payload.
     *
     * @param array $payload
     * @return bool false when no matching record exists
     */
    public function handleCallback(array $payload): bool
    {
        $record = [];
        if (!empty($payload['operationId'])) {
            $record = $this->repository->findByOperationId((string)$payload['operationId']);
        }
        if (!$record && !empty($payload['merchantRequestId'])) {
            $record = $this->repository->findByMerchantRequestId((string)$payload['merchantRequestId']);
        }

        if (!$record) {
            $this->logger->warning(
                'Fiscalization callback: record not found for operationId='
                . (string)($payload['operationId'] ?? '') . ', merchantRequestId='
                . (string)($payload['merchantRequestId'] ?? '')
            );

            return false;
        }

        $this->repository->begin();
        try {
            $locked = $this->repository->findByOperationId((string)$record['operation_id'], true);
            $this->applyPayload($locked ?: $record, $payload, 'callback_payload');
            $this->repository->commit();
        } catch (\Throwable $e) {
            $this->repository->rollback();

            throw $e;
        }

        return true;
    }

    /**
     * @param int $orderId
     * @return array
     * @throws Throwable
     */
    private function processPurchase(int $orderId): array
    {
        if (!$this->isEnabled()) {
            return $this->fail($this->msg('disabled', 'Фіскалізація вимкнена в налаштуваннях модуля.'));
        }

        $allianceOrder = $this->getAllianceOrder($orderId);
        $operation = $this->findSuccessfulPurchase($allianceOrder);
        if (!$operation) {
            return $this->fail($this->msg(
                'no_operation',
                'Не знайдено успішної платіжної операції для замовлення.'
            ));
        }

        $operationId = (string)$operation['operationId'];
        $record = $this->repository->findByOperationId($operationId);
        if ($record && !in_array(
            $record['status'],
            [AllianceFiscalizationRepository::STATUS_NEW, AllianceFiscalizationRepository::STATUS_ERROR],
            true
        )) {
            return $this->fail($this->msg(
                'already',
                'Фіскалізація для цієї операції вже виконується або завершена (статус %s).',
                [$record['status']]
            ));
        }

        try {
            $built = $this->builder->build(
                $orderId,
                (float)($allianceOrder['conversion_rate'] ?? 0),
                isset($operation['coinAmount']) ? (int)$operation['coinAmount'] : null
            );
        } catch (AllianceFiscalizationException $e) {
            $message = $this->msg($e->getMessageKey(), $e->getMessage(), $e->getMessageArgs());
            $this->saveLocalError($orderId, $operationId, self::TYPE_PURCHASE, $message);

            return $this->fail($message);
        }

        $record = $this->repository->save([
            'order_id' => $orderId,
            'store_id' => 0,
            'operation_id' => $operationId,
            'operation_type' => self::TYPE_PURCHASE,
            'merchant_request_id' => AllianceRequestIdentification::generateRequestId(),
            'status' => AllianceFiscalizationRepository::STATUS_NEW,
            'receipt_comment' => 'PAYMENT_FOR_ORDER_' . $orderId,
            'error_code' => null,
            'error_description' => null,
            'request_payload' => json_encode(
                $this->buildBody($orderId, $operationId, self::TYPE_PURCHASE, $built['products']),
                JSON_UNESCAPED_UNICODE
            ),
        ]);

        return $this->sendRecord($record);
    }

    /**
     * Sends the stored request body of a record (a fresh merchantRequestId is generated for NEW/ERROR records).
     *
     * @param array $record
     * @return array
     * @throws Throwable
     */
    private function sendRecord(array $record): array
    {
        $body = json_decode((string)$record['request_payload'], true);
        if (!is_array($body) || empty($body['products'])) {
            return $this->fail($this->msg('no_products', 'У замовленні немає позицій для фіскалізації.'));
        }

        if (empty($body['merchantId'])) {
            return $this->fail($this->msg('no_merchant', 'Не вказано Merchant ID в налаштуваннях модуля.'));
        }

        if (empty($body['notificationUrl'])) {
            return $this->fail($this->msg('no_notification_url', 'Не вдалося сформувати notificationUrl.'));
        }

        $isRefresh = !in_array(
            $record['status'],
            [AllianceFiscalizationRepository::STATUS_NEW, AllianceFiscalizationRepository::STATUS_ERROR],
            true
        );
        if (!$isRefresh) {
            $body['merchantRequestId'] = AllianceRequestIdentification::generateRequestId();
        }

        $this->logger->info(
            'Fiscalization request: ' . json_encode(AllianceLogger::maskSensitive($body), JSON_UNESCAPED_UNICODE)
        );

        try {
            $result = $this->ecom->createFiscalization($body);
        } catch (\Throwable $e) {
            $this->logger->error('Fiscalization request failed: ' . $e->getMessage());
            $message = $this->msg('technical', 'Помилка відправки запиту фіскалізації. Деталі в alliance.log.');
            $this->repository->save([
                'operation_id' => $record['operation_id'],
                'merchant_request_id' => $body['merchantRequestId'],
                'request_payload' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'status' => AllianceFiscalizationRepository::STATUS_ERROR,
                'error_description' => $message . ' ' . mb_substr($e->getMessage(), 0, 500),
            ]);

            return $this->fail($message, AllianceFiscalizationRepository::STATUS_ERROR);
        }

        $this->logger->info(
            'Fiscalization response: ' . json_encode(
                AllianceLogger::maskSensitive((array)$result['data']),
                JSON_UNESCAPED_UNICODE
            )
        );

        if ($result['http_status'] === 400) {
            $message = $this->describeBusinessError((array)$result['data']);
            $this->repository->save([
                'operation_id' => $record['operation_id'],
                'merchant_request_id' => $body['merchantRequestId'],
                'request_payload' => json_encode($body, JSON_UNESCAPED_UNICODE),
                'response_payload' => json_encode($result['data'], JSON_UNESCAPED_UNICODE),
                'status' => AllianceFiscalizationRepository::STATUS_ERROR,
                'error_code' => mb_substr((string)($result['data']['msgCode'] ?? '400'), 0, 32),
                'error_description' => $message,
            ]);

            return $this->fail($message, AllianceFiscalizationRepository::STATUS_ERROR);
        }

        $record['merchant_request_id'] = $body['merchantRequestId'];
        $record['request_payload'] = json_encode($body, JSON_UNESCAPED_UNICODE);
        $record = $this->repository->save([
            'operation_id' => $record['operation_id'],
            'merchant_request_id' => $record['merchant_request_id'],
            'request_payload' => $record['request_payload'],
        ]);

        $record = $this->applyPayload($record, (array)$result['data'], 'response_payload');

        return $this->resultFromRecord($record, '');
    }

    /**
     * Merges an Ecom payload (sync response or callback) into the record, protecting final statuses.
     *
     * @param array $record
     * @param array $payload
     * @param string $payloadColumn response_payload|callback_payload
     * @return array updated record
     */
    private function applyPayload(array $record, array $payload, string $payloadColumn): array
    {
        $update = [
            'operation_id' => $record['operation_id'],
            $payloadColumn => json_encode($payload, JSON_UNESCAPED_UNICODE),
        ];

        $newStatus = strtoupper((string)($payload['status'] ?? ''));
        $oldStatus = (string)$record['status'];
        $modification = $this->parseDateTime($payload['fiscalizationModificationDateTime']
            ?? $payload['modificationDateTime'] ?? null);

        $isStale = $modification !== null
            && !empty($record['fiscalization_modification_date_time'])
            && strtotime($modification) < strtotime((string)$record['fiscalization_modification_date_time']);

        $isFinal = in_array(
            $oldStatus,
            [AllianceFiscalizationRepository::STATUS_SUCCESS, AllianceFiscalizationRepository::STATUS_FAIL],
            true
        );
        $isDowngrade = $oldStatus === AllianceFiscalizationRepository::STATUS_SUCCESS
            ? $newStatus !== AllianceFiscalizationRepository::STATUS_SUCCESS
            : ($isFinal && $newStatus === AllianceFiscalizationRepository::STATUS_PENDING);

        $known = in_array($newStatus, [
            AllianceFiscalizationRepository::STATUS_PENDING,
            AllianceFiscalizationRepository::STATUS_SUCCESS,
            AllianceFiscalizationRepository::STATUS_FAIL,
        ], true);

        if ($known && !$isStale && !$isDowngrade) {
            $update['status'] = $newStatus;
            $update['bucket_id'] = $payload['bucketId'] ?? $record['bucket_id'];
            $update['fiscal_number'] = $payload['fiscalNumber'] ?? $record['fiscal_number'];
            $update['receipt_url'] = $payload['receiptUrl'] ?? $record['receipt_url'];
            $update['fiscalization_date_time'] = $this->parseDateTime($payload['fiscalizationDateTime'] ?? null)
                ?? $record['fiscalization_date_time'];
            $update['fiscalization_modification_date_time'] = $modification
                ?? $record['fiscalization_modification_date_time'];
            $update['error_code'] = isset($payload['errorCode']) ? mb_substr((string)$payload['errorCode'], 0, 32) : null;
            $update['error_description'] = $payload['errorDescription'] ?? null;
        } else {
            $this->logger->info(sprintf(
                'Fiscalization payload ignored for operation %s (status %s → %s, stale=%s)',
                (string)$record['operation_id'],
                $oldStatus,
                $newStatus,
                $isStale ? 'yes' : 'no'
            ));
        }

        $saved = $this->repository->save($update);

        if (isset($update['status']) && $update['status'] !== $oldStatus) {
            $this->addOrderNote((int)$saved['order_id'], $saved);
        }

        return $saved;
    }

    /**
     * @param int $orderId
     * @param array $record
     * @return void
     */
    private function addOrderNote(int $orderId, array $record): void
    {
        if ($record['status'] === AllianceFiscalizationRepository::STATUS_SUCCESS) {
            $note = $this->msg('note_success', 'Чек фіскалізовано, ФН %s', [(string)$record['fiscal_number']]);
        } elseif ($record['status'] === AllianceFiscalizationRepository::STATUS_FAIL) {
            $note = $this->msg(
                'note_fail',
                'Помилка фіскалізації: %s %s',
                [(string)$record['error_code'], (string)$record['error_description']]
            );
        } else {
            return;
        }

        $order = $this->db->query(
            "SELECT `order_status_id` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $orderId . "'"
        )->row;
        if (!$order) {
            return;
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "order_history` SET `order_id` = '" . $orderId . "', `order_status_id` = '"
            . (int)$order['order_status_id'] . "', `notify` = '0', `comment` = '"
            . $this->db->escape($note) . "', `date_added` = NOW()"
        );
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @param string $type
     * @param string $message
     * @return array
     */
    private function saveLocalError(int $orderId, string $operationId, string $type, string $message): array
    {
        $existing = $this->repository->findByOperationId($operationId);

        return $this->repository->save([
            'order_id' => $orderId,
            'store_id' => 0,
            'operation_id' => $operationId,
            'operation_type' => $type,
            'merchant_request_id' => $existing['merchant_request_id'] ?? AllianceRequestIdentification::generateRequestId(),
            'status' => AllianceFiscalizationRepository::STATUS_ERROR,
            'error_description' => $message,
        ]);
    }

    /**
     * @param int $orderId
     * @param string $operationId
     * @param string $type
     * @param array $products
     * @return array
     */
    private function buildBody(int $orderId, string $operationId, string $type, array $products): array
    {
        return [
            'merchantRequestId' => AllianceRequestIdentification::generateRequestId(),
            'merchantId' => (string)$this->config->get('payment_alliance_merchant_id'),
            'operationId' => $operationId,
            'receiptComment' => ($type === self::TYPE_REFUND ? 'REFUND_FOR_ORDER_' : 'PAYMENT_FOR_ORDER_') . $orderId,
            'notificationUrl' => $this->getNotificationUrl(),
            'products' => $products,
        ];
    }

    /**
     * @return string
     */
    private function getNotificationUrl(): string
    {
        $base = defined('HTTP_CATALOG') ? (HTTP_CATALOG ?: '') : '';
        if ($base === '') {
            $base = (string)($this->config->get('config_url') ?: (defined('HTTP_SERVER') ? HTTP_SERVER : ''));
        }
        if ($base === '') {
            return '';
        }

        return rtrim($base, '/') . '/index.php?route=' . AllianceEcom::NOTIFICATION_URL_ROUTE_FISCALIZATION;
    }

    /**
     * @param int $orderId
     * @return array
     */
    private function getAllianceOrder(int $orderId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_checkout_integration_order` WHERE `order_id` = '"
            . $orderId . "'"
        )->row;
    }

    /**
     * @param array $allianceOrder
     * @return array operation (operationId, coinAmount, ...) or empty
     */
    private function findSuccessfulPurchase(array $allianceOrder): array
    {
        if (empty($allianceOrder['callback_data'])) {
            return [];
        }

        $callback = json_decode((string)$allianceOrder['callback_data'], true);
        if (!is_array($callback)) {
            return [];
        }

        $operation = AllianceHelper::getOperationPurchaseData($callback);
        if (empty($operation['operationId'])
            || ($operation['status'] ?? '') !== AllianceConfig::ALLIANCE_ORDER_SUCCESS_STATUS
        ) {
            return [];
        }

        return $operation;
    }

    /**
     * @param array $data
     * @return string
     */
    private function describeBusinessError(array $data): string
    {
        $code = (string)($data['msgCode'] ?? '');
        $attrs = $data['msgAttrs'] ?? '';
        $attrs = is_array($attrs) ? implode(', ', array_map('strval', $attrs)) : (string)$attrs;

        switch ($code) {
            case 'missing_required_field':
                return $this->msg('err_missing_required_field', 'Відсутнє обов\'язкове поле: %s', [$attrs]);
            case 'invalid_format':
                return $this->msg('err_invalid_format', 'Некоректний формат поля: %s', [$attrs]);
            case 'payment_not_found':
                return $this->msg('err_payment_not_found', 'Операцію не знайдено в Alliance Ecom.');
            case 'original_receipt_not_found':
                return $this->msg(
                    'err_original_receipt_not_found',
                    'Для повернення не знайдено оригінальний чек. Спочатку виконайте фіскалізацію покупки.'
                );
        }

        return $this->msg(
            'err_unknown',
            'Помилка фіскалізації: %s %s',
            [$code, (string)($data['msgText'] ?? '')]
        );
    }

    /**
     * ISO 8601 with zone → site (PHP default) timezone, Y-m-d H:i:s.
     *
     * @param mixed $value
     * @return string|null
     */
    private function parseDateTime($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            $date = new \DateTime((string)$value);
            $date->setTimezone(new \DateTimeZone(date_default_timezone_get()));

            return $date->format('Y-m-d H:i:s');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @param string $key
     * @param string $default
     * @param array $args
     * @return string
     */
    private function msg(string $key, string $default, array $args = []): string
    {
        $language = $this->registry->get('language');
        $text = $default;
        if ($language) {
            $translated = $language->get('fiscalization_msg_' . $key);
            if ($translated !== 'fiscalization_msg_' . $key) {
                $text = $translated;
            }
        }

        if ($args) {
            $formatted = @vsprintf($text, $args);

            return $formatted === false ? $text : $formatted;
        }

        return $text;
    }

    /**
     * @param string $message
     * @param string $status
     * @return array
     */
    private function fail(string $message, string $status = ''): array
    {
        return ['success' => false, 'message' => $message, 'status' => $status];
    }

    /**
     * @param array $record
     * @param string $errorMessage
     * @return array
     */
    private function resultFromRecord(array $record, string $errorMessage): array
    {
        $status = (string)($record['status'] ?? '');
        $isError = in_array($status, [
            AllianceFiscalizationRepository::STATUS_ERROR,
            AllianceFiscalizationRepository::STATUS_FAIL,
        ], true);

        if ($isError) {
            return $this->fail(
                $errorMessage ?: (string)($record['error_description'] ?? ''),
                $status
            );
        }

        return [
            'success' => true,
            'message' => $this->msg('sent', 'Запит фіскалізації відправлено (статус: %s).', [$status]),
            'status' => $status,
        ];
    }

    /**
     * Runs an action and converts any throwable into a failed result (fiscalization must not break flows).
     *
     * @param callable $action
     * @return array
     */
    private function guard(callable $action): array
    {
        try {
            return $action();
        } catch (AllianceFiscalizationException $e) {
            return $this->fail($this->msg($e->getMessageKey(), $e->getMessage(), $e->getMessageArgs()));
        } catch (\Throwable $e) {
            $this->logger->error('Fiscalization error: ' . $e->getMessage());

            return $this->fail($this->msg('technical', 'Помилка відправки запиту фіскалізації. Деталі в alliance.log.'));
        }
    }
}
