<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceFiscalizationBuilder.
 *
 * Builds `products[]` for the fiscalization request from the OpenCart order.
 * Works with raw order tables so it can be used both in admin and catalog.
 */
class AllianceFiscalizationBuilder
{
    private const NON_SERVICE_TOTAL_CODES = ['sub_total', 'total', 'tax'];

    private $db;

    private $resolver;

    /**
     * @param Registry $registry
     * @param AllianceFiscalizationResolver $resolver
     */
    public function __construct(Registry $registry, AllianceFiscalizationResolver $resolver)
    {
        $this->db = $registry->get('db');
        $this->resolver = $resolver;
    }

    /**
     * @param int $orderId
     * @param float $conversionRate 0 → no conversion
     * @param int|null $targetCoinAmount expected sum in UAH coins (null → no reconciliation)
     * @param array|null $orderProductIds restrict to these order_product_id (null → all)
     * @param bool $includeServices include shipping/fee lines
     * @return array ['products' => array, 'total_coin_amount' => int]
     * @throws AllianceFiscalizationException
     */
    public function build(
        int $orderId,
        float $conversionRate = 0.0,
        ?int $targetCoinAmount = null,
        ?array $orderProductIds = null,
        bool $includeServices = true
    ): array {
        $rate = $conversionRate > 0 ? $conversionRate : 1.0;
        $lines = $this->collectLines($orderId, $rate);

        if (!$lines) {
            throw new AllianceFiscalizationException('no_products', 'У замовленні немає позицій для фіскалізації.');
        }

        $this->applyDiscounts($lines, $orderId, $rate);

        $lines = array_values(array_filter($lines, static function (array $line) use ($orderProductIds, $includeServices) {
            if ($line['type'] === 'service') {
                return $includeServices;
            }

            return $orderProductIds === null || in_array($line['order_product_id'], $orderProductIds, true);
        }));

        if (!$lines) {
            throw new AllianceFiscalizationException('no_products', 'У замовленні немає позицій для фіскалізації.');
        }

        $total = array_sum(array_column($lines, 'minor'));

        if ($targetCoinAmount !== null && $total !== $targetCoinAmount) {
            $diff = $targetCoinAmount - $total;
            if (abs($diff) > count($lines)) {
                throw new AllianceFiscalizationException(
                    'amount_mismatch',
                    sprintf(
                        'Сума позицій (%s) не збігається із сумою операції (%s).',
                        number_format($total / 100, 2, '.', ''),
                        number_format($targetCoinAmount / 100, 2, '.', '')
                    ),
                    [number_format($total / 100, 2, '.', ''), number_format($targetCoinAmount / 100, 2, '.', '')]
                );
            }
            $lines[count($lines) - 1]['minor'] += $diff;
            $total = $targetCoinAmount;
        }

        return [
            'products' => $this->toProducts($lines),
            'total_coin_amount' => $total,
        ];
    }

    /**
     * @param int $orderId
     * @param float $rate
     * @return array
     */
    private function collectLines(int $orderId, float $rate): array
    {
        $lines = [];
        $productTax = 0.0;

        $products = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_product` WHERE `order_id` = '" . $orderId
            . "' ORDER BY `order_product_id` ASC"
        )->rows;

        foreach ($products as $product) {
            $quantity = max(1, (int)$product['quantity']);
            $productTax += (float)$product['tax'] * $quantity;
            $lines[] = [
                'type' => 'product',
                'order_product_id' => (int)$product['order_product_id'],
                'product_id' => (int)$product['product_id'],
                'id' => (string)$product['product_id'],
                'name' => (string)$product['name'],
                'count' => $quantity,
                'minor' => AllianceHelper::toCoinAmount(
                    ((float)$product['total'] + (float)$product['tax'] * $quantity) * $rate
                ),
            ];
        }

        $totals = $this->db->query(
            "SELECT * FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . $orderId
            . "' ORDER BY `sort_order` ASC"
        )->rows;

        $taxTotal = 0.0;
        $serviceLines = [];
        foreach ($totals as $total) {
            if ($total['code'] === 'tax') {
                $taxTotal += (float)$total['value'];
                continue;
            }
            if (in_array($total['code'], self::NON_SERVICE_TOTAL_CODES, true) || (float)$total['value'] <= 0) {
                continue;
            }
            $serviceLines[] = [
                'type' => 'service',
                'service_code' => (string)$total['code'],
                'id' => (string)$total['code'],
                'name' => (string)$total['title'],
                'count' => 1,
                'value' => (float)$total['value'],
            ];
        }

        $extraTax = round($taxTotal - $productTax, 4);
        if ($extraTax > 0.005 && $serviceLines) {
            $target = count($serviceLines) - 1;
            foreach ($serviceLines as $index => $service) {
                if ($service['service_code'] === 'shipping') {
                    $target = $index;
                    break;
                }
            }
            $serviceLines[$target]['value'] += $extraTax;
        } elseif ($extraTax > 0.005 && $lines) {
            $lines[count($lines) - 1]['minor'] += AllianceHelper::toCoinAmount($extraTax * $rate);
        }

        foreach ($serviceLines as $service) {
            $service['minor'] = AllianceHelper::toCoinAmount($service['value'] * $rate);
            unset($service['value']);
            $lines[] = $service;
        }

        return $lines;
    }

    /**
     * Distributes negative order totals (coupon, reward, voucher...) over product lines proportionally;
     * the rounding remainder goes to the last product line.
     *
     * @param array $lines
     * @param int $orderId
     * @param float $rate
     * @return void
     */
    private function applyDiscounts(array &$lines, int $orderId, float $rate): void
    {
        $discountRow = $this->db->query(
            "SELECT SUM(`value`) AS discount FROM `" . DB_PREFIX . "order_total` WHERE `order_id` = '" . $orderId
            . "' AND `value` < 0"
        )->row;

        $discount = AllianceHelper::toCoinAmount(abs((float)($discountRow['discount'] ?? 0)) * $rate);
        if ($discount <= 0) {
            return;
        }

        $productIndexes = array_keys(array_filter($lines, static function (array $line) {
            return $line['type'] === 'product';
        }));
        if (!$productIndexes) {
            $productIndexes = array_keys($lines);
        }

        $base = 0;
        foreach ($productIndexes as $index) {
            $base += $lines[$index]['minor'];
        }
        if ($base <= 0) {
            return;
        }

        $discount = min($discount, $base);
        $distributed = 0;
        foreach ($productIndexes as $index) {
            $share = (int)floor($discount * $lines[$index]['minor'] / $base);
            $lines[$index]['minor'] -= $share;
            $distributed += $share;
        }

        $lines[end($productIndexes)]['minor'] -= ($discount - $distributed);
    }

    /**
     * @param array $lines
     * @return array
     * @throws AllianceFiscalizationException
     */
    private function toProducts(array $lines): array
    {
        $products = [];
        $last = count($lines) - 1;
        $residual = 0;

        foreach ($lines as $index => $line) {
            $count = (int)$line['count'];
            $unit = (int)round($line['minor'] / $count);
            $residual += $line['minor'] - $unit * $count;

            $attributes = $this->buildAttributes($line);

            if ($index === $last && $residual !== 0) {
                if ($count > 1) {
                    $products[] = $this->makeProduct($line, $count - 1, $unit, $attributes);
                    $products[] = $this->makeProduct($line, 1, $unit + $residual, $attributes);
                } else {
                    $products[] = $this->makeProduct($line, 1, $unit + $residual, $attributes);
                }
                continue;
            }

            $products[] = $this->makeProduct($line, $count, $unit, $attributes);
        }

        return $products;
    }

    /**
     * @param array $line
     * @param int $count
     * @param int $unitMinor
     * @param array $attributes
     * @return array
     */
    private function makeProduct(array $line, int $count, int $unitMinor, array $attributes): array
    {
        $product = [
            'id' => $line['id'],
            'name' => $line['name'],
            'count' => round((float)$count, 3),
            'measurementCode' => $attributes['measurementCode'],
            'unitPrice' => round($unitMinor / 100, 2),
        ];

        if ($attributes['taxCode'] !== null) {
            $product['taxCode'] = $attributes['taxCode'];
        }
        if (!empty($attributes['classificationCode'])) {
            $product['classificationCode'] = $attributes['classificationCode'];
        }
        if (!empty($attributes['exciseLabels'])) {
            $product['exciseLabels'] = $attributes['exciseLabels'];
        }

        return $product;
    }

    /**
     * @param array $line
     * @return array
     * @throws AllianceFiscalizationException
     */
    private function buildAttributes(array $line): array
    {
        if ($line['type'] === 'service') {
            $resolved = $this->resolver->resolveDefaults();
            if (AllianceFiscalizationResolver::requiresClassification($resolved)) {
                $resolved['taxCode'] = null;
                $resolved['classificationCode'] = null;
                $resolved['exciseLabels'] = [];
            }

            return $resolved;
        }

        $resolved = $this->resolver->resolveForProduct((int)$line['product_id']);

        if (empty($resolved['measurementCode'])) {
            throw new AllianceFiscalizationException(
                'missing_measurement',
                sprintf('Для позиції "%s" не задано код одиниці виміру.', $line['name']),
                [$line['name']]
            );
        }

        if (AllianceFiscalizationResolver::requiresClassification($resolved)
            && empty($resolved['classificationCode'])
        ) {
            throw new AllianceFiscalizationException(
                'tax_class_required',
                sprintf(
                    'Для позиції "%s" з кодом податку %s обов\'язковий УКТЗЕД (classificationCode).',
                    $line['name'],
                    (string)$resolved['taxCode']
                ),
                [$line['name'], (string)$resolved['taxCode']]
            );
        }

        return $resolved;
    }
}
