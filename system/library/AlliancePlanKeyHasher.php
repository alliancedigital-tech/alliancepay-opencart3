<?php

declare(strict_types=1);

class AlliancePlanKeyHasher
{
    /**
     * @param string $merchantId
     * @param int $productId
     * @param int|null $templateId
     * @param string $categoryIndicator
     * @param array $rules
     */
    public static function compute(
        string $merchantId,
        int $productId,
        ?int $templateId,
        string $categoryIndicator,
        array $rules
    ): string {
        $normalizedRules = self::normalizeRules($rules);

        return hash('sha256', implode('|', [
            $merchantId,
            $productId,
            $templateId ?? 0,
            $categoryIndicator,
            json_encode($normalizedRules, JSON_UNESCAPED_UNICODE),
        ]));
    }

    /**
     * @param array $rules
     */
    private static function normalizeRules(array $rules): array
    {
        usort($rules, static function (array $a, array $b) {
            return ($a['paymentNumberFrom'] ?? 0) <=> ($b['paymentNumberFrom'] ?? 0);
        });

        return array_map(static function (array $rule) {
            ksort($rule);
            return $rule;
        }, $rules);
    }
}
