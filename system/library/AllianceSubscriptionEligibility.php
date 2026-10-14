<?php

declare(strict_types=1);

require_once __DIR__ . '/AllianceSubscriptionConfig.php';

class AllianceSubscriptionEligibility
{
    private const SESSION_INTENT_KEY = 'alliance_subscription_intent_product_id';

    /**
     * @param int $productId
     * @param Registry $registry
     */
    public static function resolveProductSubscriptionPlan(int $productId, Registry $registry): ?array
    {
        $db = $registry->get('db');

        $query = $db->query(
            "SELECT * FROM `" . DB_PREFIX . "alliance_subscription_plan` WHERE `product_id` = '"
            . (int)$productId . "' AND `is_active` = '1' AND `status` = '"
            . $db->escape(AllianceSubscriptionConfig::PLAN_STATUS_ACTIVE) . "' LIMIT 1"
        );

        return $query->num_rows ? $query->row : null;
    }

    /**
     * @param int $productId
     * @param Registry $registry
     */
    public static function markSubscriptionIntent(int $productId, Registry $registry): void
    {
        $registry->get('session')->data[self::SESSION_INTENT_KEY] = $productId;
    }

    /**
     * @param Registry $registry
     * @param int|null $onlyIfProductId
     */
    public static function clearSubscriptionIntent(Registry $registry, ?int $onlyIfProductId = null): void
    {
        $session = $registry->get('session');

        if ($onlyIfProductId === null || (int)($session->data[self::SESSION_INTENT_KEY] ?? 0) === $onlyIfProductId) {
            unset($session->data[self::SESSION_INTENT_KEY]);
        }
    }

    /**
     * @param int $productId
     * @param Registry $registry
     */
    public static function isSubscriptionIntentActive(int $productId, Registry $registry): bool
    {
        $session = $registry->get('session');

        return (int)($session->data[self::SESSION_INTENT_KEY] ?? 0) === $productId;
    }

    /**
     * @param array $cartProducts As returned by OpenCart's Cart::getProducts().
     * @param Registry $registry
     */
    public static function resolveCartSubscriptionPlan(array $cartProducts, Registry $registry): ?array
    {
        if (count($cartProducts) !== 1) {
            return null;
        }

        $item = reset($cartProducts);

        if ((int)($item['quantity'] ?? 0) !== 1) {
            return null;
        }

        $productId = (int)($item['product_id'] ?? 0);

        if (!self::isSubscriptionIntentActive($productId, $registry)) {
            return null;
        }

        return self::resolveProductSubscriptionPlan($productId, $registry);
    }

    /**
     * @param array $cartProducts
     * @param Registry $registry
     */
    public static function cartHasSubscriptionItem(array $cartProducts, Registry $registry): bool
    {
        $session = $registry->get('session');
        $flaggedProductId = (int)($session->data[self::SESSION_INTENT_KEY] ?? 0);

        if (!$flaggedProductId) {
            return false;
        }

        foreach ($cartProducts as $item) {
            if ((int)($item['product_id'] ?? 0) === $flaggedProductId) {
                return true;
            }
        }

        return false;
    }
}
