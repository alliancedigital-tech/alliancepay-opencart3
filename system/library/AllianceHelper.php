<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceHelper.
 */
class AllianceHelper
{
    /**
     * @param array $orderCallBackData
     * @return array
     */
    public static function getOperationPurchaseData(array $orderCallBackData): array
    {
        $purchaseOperation = [];

        if (!empty($orderCallBackData['operation'])
            && !empty($orderCallBackData['operation']['type'])
            && in_array($orderCallBackData['operation']['type'], AllianceConfig::PURCHASE_OPERATIONS)
        ) {
            $purchaseOperation = $orderCallBackData['operation'];
        } elseif (!empty($orderCallBackData['operations'])) {
            foreach ($orderCallBackData['operations'] as $operation) {
                if (!empty($operation['type'])
                    && in_array($operation['type'], AllianceConfig::PURCHASE_OPERATIONS)
                ) {
                    $purchaseOperation = $operation;
                }
            }
        }

        return $purchaseOperation;
    }

    /**
     * @param array $orderCallBackData
     * @return array
     */
    public static function getPreAuthOperationData(array $orderCallBackData): array
    {
        $preauthOperation = [];

        if (!empty($orderCallBackData['operation'])
            && !empty($orderCallBackData['operation']['type'])
            && $orderCallBackData['operation']['type'] === AllianceConfig::OPERATION_TYPE_PREAUTH
        ) {
            $preauthOperation = $orderCallBackData['operation'];
        }

        return $preauthOperation;
    }

    /**
     * @param array $purchaseOperation
     * @return string
     */
    public static function getPurchaseOperationStatus(array $purchaseOperation): string
    {
        if (!empty($purchaseOperation)
            && !empty($purchaseOperation['status'])
            && in_array($purchaseOperation['type'], AllianceConfig::PURCHASE_OPERATIONS)
        ) {
            return $purchaseOperation['status'];
        }

        return '';
    }

    /**
     * @param array $purchaseOperation
     * @return string
     */
    public static function getPurchaseOperationId(array $purchaseOperation): string
    {
        $operationId = '';

        if (!empty($purchaseOperation['operationId'])
            && !empty($purchaseOperation['type'])
            && in_array($purchaseOperation['type'], AllianceConfig::PURCHASE_OPERATIONS)
        ) {
            $operationId = $purchaseOperation['operationId'];
        }

        return $operationId;
    }
}
