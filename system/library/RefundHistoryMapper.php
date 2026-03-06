<?php

declare(strict_types=1);


/**
 * Class RefundHistory.
 */
class RefundHistoryMapper
{
    public const REFUND_DATA_HISTORY_FIELDS_MAPPING = [
        'type' => 'type',
        'rrn' => 'rrn',
        'purpose' => 'purpose',
        'comment' => 'comment',
        'coinAmount' => 'coin_amount',
        'merchantId' => 'merchant_id',
        'operationId' => 'operation_id',
        'ecomOperationId' => 'ecom_operation_id',
        'merchantName' => 'merchant_name',
        'approvalCode' => 'approval_code',
        'status' => 'status',
        'transactionType' => 'transaction_type',
        'merchantRequestId' => 'merchant_request_id',
        'transactionCurrency' => 'transaction_currency',
        'merchantCommission' => 'merchant_commission',
        'createDateTime' => 'create_date_time',
        'modificationDateTime' => 'modification_date_time',
        'actionCode' => 'action_code',
        'responseCode' => 'response_code',
        'description' => 'description',
        'processingMerchantId' => 'processing_merchant_id',
        'processingTerminalId' => 'processing_terminal_id',
        'transactionResponseInfo' => 'transaction_response_info',
        'bankCode' => 'bank_code',
        'paymentSystem' => 'payment_system',
        'productType' => 'product_type',
        'notificationUrl' => 'notification_url',
        'paymentServiceType' => 'payment_service_type',
        'notificationEncryption' => 'notification_encryption',
        'originalOperationId' => 'original_operation_id',
        'originalCoinAmount' => 'original_coin_amount',
        'originalEcomOperationId' => 'original_ecom_operation_id',
        'rrnOriginal' => 'rrn_original',
    ];

    public const JSON_ENCODED_FIELDS_LIST = [
        'transactionResponseInfo'
    ];
}
