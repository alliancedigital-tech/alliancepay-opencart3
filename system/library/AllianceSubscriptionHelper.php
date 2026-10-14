<?php

declare(strict_types=1);

require_once __DIR__ . '/AllianceSubscriptionConfig.php';

class AllianceSubscriptionHelper
{
    /**
     * @param array $payload
     */
    public static function isInitialPayment(array $payload): bool
    {
        return empty($payload['recurringTransactionType']);
    }

    /**
     * @param array $payload
     */
    public static function extractCallbackAmount(array $payload): ?int
    {
        $candidates = [
            'operation.coinAmount',
            'coinAmount',
            'scheduledPaymentAmount',
            'amount',
        ];

        foreach ($candidates as $path) {
            $value = self::extractByPath($payload, $path);

            if ($value !== null && $value !== '') {
                self::log(
                    'extractCallbackAmount matched field "' . $path . '" = ' . (string)$value
                );

                return (int)$value;
            }
        }

        self::log(
            'extractCallbackAmount: no known amount field found in callback payload '
            . '(tried: ' . implode(', ', $candidates) . '). Amount reconciliation skipped for this delivery.'
        );

        return null;
    }

    /**
     * @param string $status
     */
    public static function resolveAtypicalStatus(string $status): string
    {
        $known = AllianceSubscriptionConfig::getAllScheduledStatuses();

        if (in_array($status, $known, true)) {
            return $status;
        }

        throw new \RuntimeException(
            'Unrecognized scheduled payment status "' . $status . '". Known statuses: '
            . implode(', ', $known) . '. Refusing to silently default/ignore this status.'
        );
    }

    /**
     * @param array $existingRow Previously persisted row data (may be empty for a brand-new row).
     * @param array $incomingPayload Raw webhook payload for this delivery.
     * @param array $scalarFieldMap Map of [payloadKey => rowColumn] for coalesced scalar fields.
     */
    public static function mergeWebhookPayload(
        array $existingRow,
        array $incomingPayload,
        array $scalarFieldMap
    ): array {
        $existingOperations = [];

        if (!empty($existingRow['raw_callback_data'])) {
            $decoded = is_array($existingRow['raw_callback_data'])
                ? $existingRow['raw_callback_data']
                : json_decode((string)$existingRow['raw_callback_data'], true);

            if (!empty($decoded['operations']) && is_array($decoded['operations'])) {
                $existingOperations = array_column($decoded['operations'], null, 'operationId');
            }
        }

        $isNewOperation = false;
        $operation = $incomingPayload['operation'] ?? null;

        if (!empty($operation['operationId'])) {
            $operationId = $operation['operationId'];

            if (!isset($existingOperations[$operationId])) {
                $existingOperations[$operationId] = $operation;
                $isNewOperation = true;
            }
        }

        $mergedRow = $existingRow;

        foreach ($scalarFieldMap as $payloadKey => $column) {
            $newValue = self::extractByPath($incomingPayload, $payloadKey);

            if ($newValue !== null && $newValue !== '') {
                $mergedRow[$column] = $newValue;
            }

        }

        return [
            'row' => $mergedRow,
            'operations' => array_values($existingOperations),
            'is_new_operation' => $isNewOperation,
        ];
    }

    /**
     * @param array $payload
     * @param string $path
     */
    private static function extractByPath(array $payload, string $path)
    {
        $segments = explode('.', $path);
        $value = $payload;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * @param string $message
     */
    private static function log(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        (new Log('alliance.log'))->write(
            "[{$timestamp}] [WARNING] [AllianceSubscriptionHelper] {$message}"
        );
    }
}
