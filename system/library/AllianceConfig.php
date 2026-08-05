<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceConfig.
 */
class AllianceConfig
{
    public const ALLIANCE_PAYMENT_CODE = 'alliance';
    public const OPERATION_TYPE_PURCHASE = 'PURCHASE';
    public const OPERATION_TYPE_REFUND = 'REFUND';
    public const OPERATION_TYPE_A2A = 'ACCOUNT_2_ACCOUNT';
    public const OPERATION_TYPE_PREAUTH = 'PREAUTH';
    public const OPERATION_TYPE_COMPLETION = 'COMPLETION';

    public const PURCHASE_OPERATIONS = [
        self::OPERATION_TYPE_PURCHASE,
        self::OPERATION_TYPE_A2A,
        self::OPERATION_TYPE_COMPLETION,
    ];

    public const ALLIANCE_ORDER_SUCCESS_STATUS = 'SUCCESS';
    public const ALLIANCE_ORDER_FAIL_STATUS = 'FAIL';
    public const ALLIANCE_ORDER_PENDING_STATUS = 'PENDING';
    public const ALLIANCE_ORDER_REQUIRED_3DS_STATUS = 'REQUIRED_3DS';
}
