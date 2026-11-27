<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class AllianceFiscalizationResolver.
 *
 * Picks taxCode / classificationCode / exciseLabels / measurementCode per field:
 * product value first, otherwise the module default.
 */
class AllianceFiscalizationResolver
{
    public const DEFAULT_MEASUREMENT_CODE = '0001';

    public const EXCISE_TAX_CODES = [2, 7, 8];

    private $config;

    private $repository;

    /**
     * @param object $config
     * @param AllianceFiscalizationRepository $repository
     */
    public function __construct(object $config, AllianceFiscalizationRepository $repository)
    {
        $this->config = $config;
        $this->repository = $repository;
    }

    /**
     * @param int $productId
     * @return array taxCode (int|null), classificationCode, exciseLabels (array), measurementCode
     */
    public function resolveForProduct(int $productId): array
    {
        $attributes = $productId > 0 ? $this->repository->getProductAttributes($productId) : [];

        return $this->resolve($attributes);
    }

    /**
     * Defaults only (used for service lines like shipping).
     *
     * @return array
     */
    public function resolveDefaults(): array
    {
        return $this->resolve([]);
    }

    /**
     * @param array $attributes product row (tax_code, classification_code, excise_labels, measurement_code)
     * @return array
     */
    public function resolve(array $attributes): array
    {
        $taxCode = $this->firstNonEmpty(
            $attributes['tax_code'] ?? null,
            $this->config->get('payment_alliance_fiscalization_tax_code')
        );
        $classification = $this->firstNonEmpty(
            $attributes['classification_code'] ?? null,
            $this->config->get('payment_alliance_fiscalization_classification_code')
        );
        $excise = self::parseList(
            $this->firstNonEmpty(
                $attributes['excise_labels'] ?? null,
                $this->config->get('payment_alliance_fiscalization_excise_labels')
            )
        );
        $measurement = $this->firstNonEmpty(
            $attributes['measurement_code'] ?? null,
            $this->config->get('payment_alliance_fiscalization_measurement_code'),
            self::DEFAULT_MEASUREMENT_CODE
        );

        return [
            'taxCode' => $taxCode === null ? null : (int)$taxCode,
            'classificationCode' => $classification,
            'exciseLabels' => $excise,
            'measurementCode' => $measurement,
        ];
    }

    /**
     * @param array $resolved
     * @return bool
     */
    public static function requiresClassification(array $resolved): bool
    {
        return in_array($resolved['taxCode'], self::EXCISE_TAX_CODES, true);
    }

    /**
     * @param mixed $value comma/newline separated string or array
     * @return array
     */
    public static function parseList($value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = preg_split('/[\s,;]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
        }

        $items = array_map('trim', array_map('strval', (array)$items));

        return array_values(array_filter($items, static function ($item) {
            return $item !== '';
        }));
    }

    /**
     * @param mixed ...$values
     * @return string|null
     */
    private function firstNonEmpty(...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return null;
    }
}
