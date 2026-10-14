<?php

declare(strict_types=1);

class AllianceSubscriptionConfig
{
    public const SUBSCRIPTION_PAYMENT_CODE = 'alliance_subscription';

    public const SUBSCRIPTION_STATUS_PENDING = 'PENDING';
    public const SUBSCRIPTION_STATUS_ACTIVE = 'ACTIVE';
    public const SUBSCRIPTION_STATUS_CANCELED = 'CANCELED';
    public const SUBSCRIPTION_STATUS_ERROR = 'ERROR';

    public const PLAN_STATUS_ACTIVE = 'ACTIVE';
    public const PLAN_STATUS_PAUSED = 'PAUSED';
    public const PLAN_STATUS_CANCELED = 'CANCELED';
    public const PLAN_STATUS_ERROR = 'ERROR';
    public const PLAN_STATUS_ARCHIVED = 'ARCHIVED';

    public const TEMPLATE_STATUS_ACTIVE = 'active';
    public const TEMPLATE_STATUS_ARCHIVED = 'archived';

    public const SCHEDULED_STATUS_WAITING = 'WAITING';
    public const SCHEDULED_STATUS_PROCESSING = 'PROCESSING';
    public const SCHEDULED_STATUS_SUCCESS = 'SUCCESS';
    public const SCHEDULED_STATUS_FAIL = 'FAIL';
    public const SCHEDULED_STATUS_RETRY = 'RETRY';
    public const SCHEDULED_STATUS_SKIPPED_PAUSED = 'SKIPPED_PAUSED';
    public const SCHEDULED_STATUS_INVALIDATED = 'INVALIDATED';

    public const PLAN_SYNC_STATUS_SYNCED = 'SYNCED';
    public const PLAN_SYNC_STATUS_ERROR = 'ERROR';

    public const MASS_REPRICE_MIN_LEAD_DAYS = 30;

    public const AUDIT_ACTION_PLAN_CREATE = 'PLAN_CREATE';
    public const AUDIT_ACTION_PLAN_CREATE_FAIL = 'PLAN_CREATE_FAIL';
    public const AUDIT_ACTION_PLAN_REPRICE = 'PLAN_REPRICE';
    public const AUDIT_ACTION_PLAN_REPRICE_FAIL = 'PLAN_REPRICE_FAIL';
    public const AUDIT_ACTION_PLAN_DEACTIVATE_LOCAL = 'PLAN_DEACTIVATE_LOCAL';
    public const AUDIT_ACTION_PLAN_REACTIVATE_LOCAL = 'PLAN_REACTIVATE_LOCAL';
    public const AUDIT_ACTION_PLAN_ARCHIVE_PROVIDER = 'PLAN_ARCHIVE_PROVIDER';
    public const AUDIT_ACTION_SUBSCRIPTION_CANCEL = 'SUBSCRIPTION_CANCEL';
    public const AUDIT_ACTION_MANUAL_RETRY = 'MANUAL_RETRY';
    public const AUDIT_ACTION_TEMPLATE_CREATE = 'TEMPLATE_CREATE';
    public const AUDIT_ACTION_TEMPLATE_ARCHIVE = 'TEMPLATE_ARCHIVE';

    public static function getAllScheduledStatuses(): array
    {
        return [
            self::SCHEDULED_STATUS_WAITING,
            self::SCHEDULED_STATUS_PROCESSING,
            self::SCHEDULED_STATUS_SUCCESS,
            self::SCHEDULED_STATUS_FAIL,
            self::SCHEDULED_STATUS_RETRY,
            self::SCHEDULED_STATUS_SKIPPED_PAUSED,
            self::SCHEDULED_STATUS_INVALIDATED,
        ];
    }
}
