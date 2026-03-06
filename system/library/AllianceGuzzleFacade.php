<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

use GuzzleHttp\Client;

/**
 * Class AllianceGuzzleFacade.
 */
class AllianceGuzzleFacade
{
    const METHOD_POST = 'POST';
    private $client;
    private $is_old_version;

    /**
     * @throws Exception
     */
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
    }

    /**
     * @param string $base_url
     * @return void
     */
    public function init(string $base_url)
    {
        $this->is_old_version = !method_exists(Client::class, 'request');

        if ($this->is_old_version) {
            $this->client = new Client([
                'base_url' => $base_url
            ]);
        } else {
            $this->client = new Client([
                'base_uri' => $base_url
            ]);
        }
    }

    /**
     * @param $endpoint
     * @param array $options
     * @return mixed
     */
    public function request($endpoint, array $options = []) {
        if ($this->is_old_version) {
            return $this->client->post($endpoint, $options);
        } else {
            return $this->client->request(self::METHOD_POST, $endpoint, $options);
        }
    }
}
