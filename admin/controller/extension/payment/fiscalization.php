<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

require_once DIR_SYSTEM . 'library/AllianceFiscalizationService.php';

/**
 * Class ControllerExtensionPaymentFiscalization.
 *
 * Admin side of fiscalization: order block, manual actions and product form fields.
 */
class ControllerExtensionPaymentFiscalization extends Controller
{
    private const PERMISSION_ROUTE = 'extension/payment/fiscalization';

    /**
     * Event: admin/view/sale/order_info/after. Appends the "Fiscalization" panel to the order page.
     *
     * @param string $route
     * @param array $args
     * @param string $output
     * @return void
     */
    public function orderBlock(&$route, &$args, &$output)
    {
        if (!$this->isEnabled() || empty($this->request->get['order_id'])) {
            return;
        }

        $this->load->language('extension/payment/alliance');
        $this->load->library('AllianceConfig');
        $orderId = (int)$this->request->get['order_id'];

        $order = $this->db->query(
            "SELECT `payment_code` FROM `" . DB_PREFIX . "order` WHERE `order_id` = '" . $orderId . "'"
        )->row;
        if (empty($order) || $order['payment_code'] !== AllianceConfig::ALLIANCE_PAYMENT_CODE) {
            return;
        }

        $service = new AllianceFiscalizationService($this->registry);
        $records = $service->getRepository()->findByOrderId($orderId);
        $hasPurchase = false;
        $rows = [];
        foreach ($records as $record) {
            $hasPurchase = $hasPurchase || $record['operation_type'] === AllianceFiscalizationService::TYPE_PURCHASE;
            $retryable = in_array($record['status'], ['NEW', 'ERROR'], true);
            $rows[] = [
                'id' => (int)$record['id'],
                'type' => $this->language->get('fisc_type_' . $record['operation_type']),
                'status' => $this->language->get('fisc_status_' . $record['status']),
                'status_code' => $record['status'],
                'fiscal_number' => $record['fiscal_number'],
                'receipt_url' => $this->safeUrl((string)$record['receipt_url']),
                'date' => $record['fiscalization_date_time'],
                'error' => trim($record['error_code'] . ' ' . $record['error_description']),
                'action' => $retryable ? 'send' : 'refresh',
            ];
        }

        $refundRows = [];
        foreach ($service->findRefundsWithoutRecord($orderId) as $refund) {
            $refundRows[] = [
                'operation_id' => $refund['operation_id'],
                'amount' => number_format((int)$refund['coin_amount'] / 100, 2, '.', ''),
                'status' => $refund['status'],
            ];
        }

        $token = $this->session->data['user_token'];
        $data = [
            'rows' => $rows,
            'refund_rows' => $refundRows,
            'refund_url' => html_entity_decode($this->url->link(
                self::PERMISSION_ROUTE . '/fiscalizeRefund',
                'user_token=' . $token . '&order_id=' . $orderId,
                true
            ), ENT_QUOTES, 'UTF-8'),
            'fisc_btn_fiscalize_refund' => $this->language->get('fisc_btn_fiscalize_refund'),
            'fisc_refund_label' => $this->language->get('fisc_refund_label'),
            'can_modify' => $this->user->hasPermission('modify', self::PERMISSION_ROUTE),
            'show_purchase_button' => !$hasPurchase,
            'order_id' => $orderId,
            'purchase_url' => html_entity_decode($this->url->link(
                self::PERMISSION_ROUTE . '/fiscalizePurchase',
                'user_token=' . $token,
                true
            ), ENT_QUOTES, 'UTF-8'),
            'action_url' => html_entity_decode($this->url->link(
                self::PERMISSION_ROUTE . '/action',
                'user_token=' . $token,
                true
            ), ENT_QUOTES, 'UTF-8'),
            'fisc_block_title' => $this->language->get('fisc_block_title'),
            'fisc_col_type' => $this->language->get('fisc_col_type'),
            'fisc_col_status' => $this->language->get('fisc_col_status'),
            'fisc_col_number' => $this->language->get('fisc_col_number'),
            'fisc_col_receipt' => $this->language->get('fisc_col_receipt'),
            'fisc_col_date' => $this->language->get('fisc_col_date'),
            'fisc_col_error' => $this->language->get('fisc_col_error'),
            'fisc_btn_fiscalize' => $this->language->get('fisc_btn_fiscalize'),
            'fisc_btn_refresh' => $this->language->get('fisc_btn_refresh'),
            'fisc_receipt_link' => $this->language->get('fisc_receipt_link'),
            'fisc_empty' => $this->language->get('fisc_empty'),
        ];

        $html = $this->load->view('extension/payment/fiscalization_block', $data);
        $script = '<script>document.addEventListener("DOMContentLoaded", function () {'
            . 'var c = document.querySelector("#content > .container-fluid");'
            . 'if (!c) { return; }'
            . 'var t = document.createElement("div");'
            . 't.innerHTML = ' . json_encode($html, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) . ';'
            . 'Array.prototype.forEach.call(t.querySelectorAll("script"), function (old) {'
            . 'var s = document.createElement("script"); s.textContent = old.textContent;'
            . 'old.parentNode.replaceChild(s, old); });'
            . 'while (t.firstChild) { c.appendChild(t.firstChild); }'
            . '});</script>';

        $output = str_replace('</body>', $script . '</body>', $output);
    }

    /**
     * AJAX (POST): manual fiscalization of the order payment.
     *
     * @return void
     */
    public function fiscalizePurchase()
    {
        if (!$this->checkModifyPermission()) {
            return;
        }

        $this->load->language('extension/payment/alliance');
        $orderId = (int)($this->request->post['order_id'] ?? 0);
        $result = $orderId > 0
            ? (new AllianceFiscalizationService($this->registry))->fiscalizePurchase($orderId)
            : ['success' => false, 'message' => $this->language->get('fisc_error_generic')];

        $this->jsonOutput($result);
    }

    /**
     * @return void
     */
    public function fiscalizeRefund()
    {
        if (!$this->checkModifyPermission()) {
            return;
        }

        $this->load->language('extension/payment/alliance');
        $orderId = (int)($this->request->get['order_id'] ?? 0);
        $operationId = (string)($this->request->post['operation_id'] ?? '');
        $result = $orderId > 0 && $operationId !== ''
            ? (new AllianceFiscalizationService($this->registry))->fiscalizeRefund($orderId, $operationId)
            : ['success' => false, 'message' => $this->language->get('fisc_error_generic')];

        $this->jsonOutput($result);
    }

    /**
     * AJAX: send (NEW/ERROR records) or refresh status (other records).
     *
     * @return void
     */
    public function action()
    {
        if (!$this->checkModifyPermission()) {
            return;
        }

        $this->load->language('extension/payment/alliance');
        $recordId = (int)($this->request->post['record_id'] ?? 0);
        $refresh = ($this->request->post['mode'] ?? '') === 'refresh';
        $result = $recordId > 0
            ? (new AllianceFiscalizationService($this->registry))->resend($recordId, $refresh)
            : ['success' => false, 'message' => $this->language->get('fisc_error_generic')];

        $this->jsonOutput($result);
    }

    /**
     * Event: admin/view/catalog/product_form/after. Adds the "Fiscalization" tab.
     *
     * @param string $route
     * @param array $args
     * @param string $output
     * @return void
     */
    public function productFormTab(&$route, &$args, &$output)
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->load->language('extension/payment/alliance');
        $productId = (int)($this->request->get['product_id'] ?? 0);
        $repository = new AllianceFiscalizationRepository($this->registry);
        $attributes = $this->request->server['REQUEST_METHOD'] === 'POST'
            ? ($this->request->post['alliancepay_fiscalization'] ?? [])
            : ($productId ? $repository->getProductAttributes($productId) : []);

        $data = [
            'tax_code' => $attributes['tax_code'] ?? '',
            'classification_code' => $attributes['classification_code'] ?? '',
            'excise_labels' => $attributes['excise_labels'] ?? '',
            'measurement_code' => $attributes['measurement_code'] ?? '',
            'fisc_product_tax_code' => $this->language->get('fisc_product_tax_code'),
            'fisc_product_classification_code' => $this->language->get('fisc_product_classification_code'),
            'fisc_product_excise_labels' => $this->language->get('fisc_product_excise_labels'),
            'fisc_product_measurement_code' => $this->language->get('fisc_product_measurement_code'),
            'fisc_product_help' => $this->language->get('fisc_product_help'),
            'help_fiscalization_tax_code' => $this->language->get('help_fiscalization_tax_code'),
            'help_fiscalization_classification_code' => $this->language->get('help_fiscalization_classification_code'),
            'help_fiscalization_excise_labels' => $this->language->get('help_fiscalization_excise_labels'),
            'help_fiscalization_measurement_code' => $this->language->get('help_fiscalization_measurement_code'),
        ];

        $pane = $this->load->view('extension/payment/fiscalization_product_tab', $data);
        $tabLink = '<li><a href="#tab-alliancepay-fiscalization" data-toggle="tab">'
            . htmlspecialchars($this->language->get('fisc_tab_title'), ENT_QUOTES, 'UTF-8') . '</a></li>';

        $navPos = strpos($output, 'nav nav-tabs');
        $navEnd = $navPos === false ? false : strpos($output, '</ul>', $navPos);
        $contentPos = strpos($output, '<div class="tab-content">');
        if ($navEnd === false || $contentPos === false) {
            return;
        }

        $paneOffset = $contentPos + strlen('<div class="tab-content">');
        $output = substr($output, 0, $paneOffset) . $pane . substr($output, $paneOffset);
        $output = substr($output, 0, $navEnd) . $tabLink . substr($output, $navEnd);
    }

    /**
     * Event: admin/model/catalog/product/addProduct/after ($output = new product_id).
     *
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function productAdded(&$route, &$args, &$output)
    {
        $this->saveProductAttributes((int)$output);
    }

    /**
     * Event: admin/model/catalog/product/editProduct/after ($args[0] = product_id).
     *
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function productEdited(&$route, &$args, &$output)
    {
        $this->saveProductAttributes((int)($args[0] ?? 0));
    }

    /**
     * Event: admin/model/catalog/product/deleteProduct/after ($args[0] = product_id).
     *
     * @param string $route
     * @param array $args
     * @param mixed $output
     * @return void
     */
    public function productDeleted(&$route, &$args, &$output)
    {
        $productId = (int)($args[0] ?? 0);
        if ($productId > 0) {
            (new AllianceFiscalizationRepository($this->registry))->deleteProductAttributes($productId);
        }
    }

    /**
     * @param int $productId
     * @return void
     */
    private function saveProductAttributes(int $productId): void
    {
        if ($productId <= 0 || !isset($this->request->post['alliancepay_fiscalization'])) {
            return;
        }

        $input = (array)$this->request->post['alliancepay_fiscalization'];
        $taxCode = trim((string)($input['tax_code'] ?? ''));
        $classification = trim((string)($input['classification_code'] ?? ''));

        $attributes = [
            'tax_code' => preg_match('/^\d+$/', $taxCode) ? $taxCode : '',
            'classification_code' => preg_match('/^\d+$/', $classification) ? $classification : '',
            'excise_labels' => implode(',', AllianceFiscalizationResolver::parseList($input['excise_labels'] ?? '')),
            'measurement_code' => trim((string)($input['measurement_code'] ?? '')),
        ];

        (new AllianceFiscalizationRepository($this->registry))->saveProductAttributes($productId, $attributes);
    }

    /**
     * @return bool
     */
    private function isEnabled(): bool
    {
        return (string)$this->config->get('payment_alliance_fiscalization_enabled') === '1';
    }

    /**
     * @return bool
     */
    private function checkModifyPermission(): bool
    {
        if ($this->user->hasPermission('modify', self::PERMISSION_ROUTE)) {
            return true;
        }

        $this->load->language('extension/payment/alliance');
        $this->jsonOutput(['success' => false, 'message' => $this->language->get('fisc_error_permission')]);

        return false;
    }

    /**
     * Only http(s) receipt links are rendered as hrefs.
     *
     * @param string $url
     * @return string
     */
    private function safeUrl(string $url): string
    {
        return preg_match('#^https?://#i', $url) ? $url : '';
    }

    /**
     * @param array $data
     * @return void
     */
    private function jsonOutput(array $data): void
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
