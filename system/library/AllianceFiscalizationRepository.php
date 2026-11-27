<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceFiscalizationRepository.
 *
 * Single access point to fiscalization tables (shared by admin and catalog).
 */
class AllianceFiscalizationRepository
{
    public const STATUS_NEW = 'NEW';
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_FAIL = 'FAIL';
    public const STATUS_ERROR = 'ERROR';

    private const COLUMNS = [
        'order_id',
        'store_id',
        'operation_id',
        'operation_type',
        'merchant_request_id',
        'bucket_id',
        'status',
        'receipt_comment',
        'fiscal_number',
        'receipt_url',
        'fiscalization_date_time',
        'fiscalization_modification_date_time',
        'error_code',
        'error_description',
        'request_payload',
        'response_payload',
        'callback_payload',
    ];

    private $db;

    /**
     * @param Registry $registry
     */
    public function __construct(Registry $registry)
    {
        $this->db = $registry->get('db');
    }

    /**
     * @param string $operationId
     * @return array
     */
    public function findByOperationId(string $operationId, bool $forUpdate = false): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliancepay_fiscalization` WHERE `operation_id` = '"
            . $this->db->escape($operationId) . "'" . ($forUpdate ? ' FOR UPDATE' : '')
        )->row;
    }

    /**
     * @return void
     */
    public function begin(): void
    {
        $this->db->query('START TRANSACTION');
    }

    /**
     * @return void
     */
    public function commit(): void
    {
        $this->db->query('COMMIT');
    }

    /**
     * @return void
     */
    public function rollback(): void
    {
        $this->db->query('ROLLBACK');
    }

    /**
     * @param string $merchantRequestId
     * @return array
     */
    public function findByMerchantRequestId(string $merchantRequestId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliancepay_fiscalization` WHERE `merchant_request_id` = '"
            . $this->db->escape($merchantRequestId) . "'"
        )->row;
    }

    /**
     * @param int $id
     * @return array
     */
    public function findById(int $id): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliancepay_fiscalization` WHERE `id` = '" . $id . "'"
        )->row;
    }

    /**
     * @param int $orderId
     * @return array
     */
    public function findByOrderId(int $orderId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliancepay_fiscalization` WHERE `order_id` = '" . $orderId
            . "' ORDER BY `id` ASC"
        )->rows;
    }

    /**
     * Upsert by operation_id. Returns the stored row.
     *
     * @param array $data column => value (null is stored as NULL)
     * @return array
     */
    public function save(array $data): array
    {
        if (empty($data['operation_id'])) {
            throw new \InvalidArgumentException('Fiscalization record requires operation_id.');
        }

        $now = date('Y-m-d H:i:s');
        $set = [];
        foreach (self::COLUMNS as $column) {
            if (!array_key_exists($column, $data)) {
                continue;
            }
            $set[] = "`" . $column . "` = " . $this->quote($data[$column]);
        }

        $existing = $this->findByOperationId((string)$data['operation_id']);

        if ($existing) {
            $set[] = "`updated_at` = '" . $now . "'";
            $this->db->query(
                "UPDATE `" . DB_PREFIX . "alliancepay_fiscalization` SET " . implode(', ', $set)
                . " WHERE `id` = '" . (int)$existing['id'] . "'"
            );
        } else {
            $set[] = "`created_at` = '" . $now . "'";
            $set[] = "`updated_at` = '" . $now . "'";
            $this->db->query(
                "INSERT INTO `" . DB_PREFIX . "alliancepay_fiscalization` SET " . implode(', ', $set)
            );
        }

        return $this->findByOperationId((string)$data['operation_id']);
    }

    /**
     * @param int $productId
     * @return array
     */
    public function getProductAttributes(int $productId): array
    {
        return $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliancepay_fiscalization_product` WHERE `product_id` = '"
            . $productId . "'"
        )->row;
    }

    /**
     * @param int $productId
     * @param array $attributes tax_code, classification_code, excise_labels, measurement_code
     * @return void
     */
    public function saveProductAttributes(int $productId, array $attributes): void
    {
        $this->deleteProductAttributes($productId);

        $values = [
            'tax_code' => $attributes['tax_code'] ?? '',
            'classification_code' => $attributes['classification_code'] ?? '',
            'excise_labels' => $attributes['excise_labels'] ?? '',
            'measurement_code' => $attributes['measurement_code'] ?? '',
        ];

        if (implode('', $values) === '') {
            return;
        }

        $this->db->query(
            "INSERT INTO `" . DB_PREFIX . "alliancepay_fiscalization_product` SET `product_id` = '" . $productId
            . "', `tax_code` = " . ($values['tax_code'] === '' ? 'NULL' : (int)$values['tax_code'])
            . ", `classification_code` = " . $this->quote($values['classification_code'] ?: null)
            . ", `excise_labels` = " . $this->quote($values['excise_labels'] ?: null)
            . ", `measurement_code` = " . $this->quote($values['measurement_code'] ?: null)
        );
    }

    /**
     * @param int $productId
     * @return void
     */
    public function deleteProductAttributes(int $productId): void
    {
        $this->db->query(
            "DELETE FROM `" . DB_PREFIX . "alliancepay_fiscalization_product` WHERE `product_id` = '"
            . $productId . "'"
        );
    }

    /**
     * @param mixed $value
     * @return string
     */
    private function quote($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        return "'" . $this->db->escape((string)$value) . "'";
    }
}
