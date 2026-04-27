<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

class AllianceCountryCodeProvider
{
    public function __construct()
    {
        $composerAutoload = DIR_SYSTEM . 'library/alliancepay/vendor/autoload.php';

        if (file_exists($composerAutoload)) {
            require_once $composerAutoload;
        } else {
            $rootAutoload = DIR_SYSTEM . '../vendor/autoload.php';
            if (file_exists($rootAutoload)) {
                require_once $rootAutoload;
            } else {
                throw new Exception('AlliancePay: Autoloader not found.');
            }
        }
        $this->countryCode = new \League\ISO3166\ISO3166();
    }

    /**
     * @param string $alpha2
     * @return string
     */
    public function getCountryNumericCodeByAlpha2(string $alpha2): string
    {
        $countryData = $this->countryCode->alpha2($alpha2);

        return $countryData['numeric'] ?? '';
    }
}
