<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class ControllerExtensionPaymentAllianceFiscalization.
 *
 * Public callback endpoint for fiscalization results (JWS only).
 */
class ControllerExtensionPaymentAllianceFiscalization extends Controller
{
    /**
     * @return void
     */
    public function callback(): void
    {
        $this->response->addHeader('Cache-Control: no-store, no-cache, must-revalidate');
        $this->response->addHeader('Content-Type: application/json');

        $this->load->library('AllianceLogger');
        $logger = new AllianceLogger($this->registry);

        $body = trim((string)file_get_contents('php://input'));
        $isToken = $body !== '' && strpos($body, ' ') === false && substr_count($body, '.') === 2;

        if (!$isToken) {
            $logger->error('Fiscalization callback rejected: body is not a JWS');
            $this->reject();

            return;
        }

        try {
            require_once DIR_SYSTEM . 'library/AllianceFiscalizationService.php';
            $this->load->library('AllianceEcom');
            $payload = (new AllianceEcom($this->registry))->verifyJwsToken($body);
        } catch (\Throwable $e) {
            $logger->error('Fiscalization callback JWS verification failed: ' . $e->getMessage());
            $this->reject();

            return;
        }

        if (!is_array($payload)) {
            $this->reject();

            return;
        }

        $logger->info(
            'Fiscalization callback: ' . json_encode(AllianceLogger::maskSensitive($payload), JSON_UNESCAPED_UNICODE)
        );

        try {
            (new AllianceFiscalizationService($this->registry))->handleCallback($payload);
        } catch (\Throwable $e) {
            $logger->error('Fiscalization callback processing failed: ' . $e->getMessage());
            $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 500 Internal Server Error');
            $this->response->setOutput(json_encode(['status' => 'error']));

            return;
        }

        $this->response->setOutput(json_encode(['status' => 'ok']));
    }

    /**
     * @return void
     */
    private function reject(): void
    {
        $this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 400 Bad Request');
        $this->response->setOutput(json_encode(['status' => 'error']));
    }
}
