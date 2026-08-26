<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

declare(strict_types=1);

/**
 * Class ControllerExtensionPaymentAlliance.
 */
class ControllerExtensionPaymentAlliance extends Controller
{
    public $version = '1.6.0';
    private $events = [
        [
            'trigger'    => 'admin/view/sale/order_info/after',
            'action'     => 'extension/payment/alliance/operationsEvent',
            'event_code' => 'alliance_operations',
            'sort_order' => 1,
        ],
        [
            'trigger'    => 'catalog/model/checkout/order/addOrder/after',
            'action'     => 'extension/payment/event/fix_session_order_id/afterAddOrder',
            'event_code' => 'alliance_fix_session_order_id',
            'sort_order' => 0,
        ],
        [
            'trigger'    => 'admin/view/sale/order_info/after',
            'action'     => 'extension/payment/refund/injectRefundButton',
            'event_code' => 'alliance_add_refund_button_order_info',
            'sort_order' => 2,
        ],
        [
            'trigger'    => 'admin/view/sale/order_info/before',
            'action'     => 'extension/module/order_refund_helper/addReturnedProducts',
            'event_code' => 'alliance_order_refund_data',
            'sort_order' => 1,
        ],
    ];

    /**
     * @return void
     */
    public function index()
    {
        $this->load->language('extension/payment/alliance');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/data');
        $this->document->setTitle($this->language->get('heading_title'));

        $installed_version = $this->config->get('payment_alliance_version');

        if (!$installed_version
            || version_compare($installed_version, $this->version, '<')
        ) {
            $this->upgrade();
        }

        if (
            $this->request->server['REQUEST_METHOD'] == 'POST' &&
            $this->request->post['action'] === 'save'
        ) {
            $post = $this->request->post;

            $siteCurrency = $this->config->get('config_currency');
            if (($post['payment_alliance_hpp_type'] ?? '') === 'A2A' && $siteCurrency !== 'UAH') {
                $this->session->data['warning'] = sprintf(
                    $this->language->get('error_a2a_uah_only'),
                    $siteCurrency
                );
                $this->response->redirect(
                    $this->url->link('extension/payment/alliance', 'user_token=' . $this->getUserToken(), true)
                );
                return;
            }

            $catalog_url = $this->config->get('config_url') ?: HTTP_CATALOG;
            $defaults = [
                'payment_alliance_success_url' => $catalog_url . 'index.php?route=checkout/success',
                'payment_alliance_fail_url' => $catalog_url . 'index.php?route=checkout/failure',
                'payment_alliance_version' => $this->version,
            ];

            foreach ($defaults as $key => $value) {
                if (empty($post[$key])) {
                    $post[$key] = $value;
                }
            }

            $this->model_setting_setting->editSetting('payment_alliance', $post);

            $this->session->data['success'] = $this->language->get(
                'text_success'
            );
            $this->response->redirect(
                $this->url->link(
                    'extension/payment/alliance',
                    'user_token=' . $this->getUserToken(),
                    true
                )
            );
        }

        $data = $this->model_extension_payment_data->fillSettingData($this->session, $this->config, $this->request);

        $this->response->setOutput($this->load->view('extension/payment/alliance', $data));
    }

    /**
     * @return void
     */
    public function install()
    {
        $this->load->language('extension/payment/alliance');
        $this->load->model('localisation/order_status');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/alliance');
        $this->load->model('extension/payment/db');
        $this->load->model('user/user_group');

        $default_pending = 1;
        $default_paid = 7;
        $default_error = 10;
        $catalog_url = $this->config->get('config_url') ?? HTTP_CATALOG;

        $default_settings = [
            'payment_alliance_paid_status' => $default_paid,
            'payment_alliance_pending_status' => $default_pending,
            'payment_alliance_error_status' => $default_error,
            'payment_alliance_return_url' => null,
            'payment_alliance_success_url' => $catalog_url . 'index.php?route=checkout/success',
            'payment_alliance_fail_url' => $catalog_url . 'index.php?route=checkout/failure',
            'payment_alliance_debug' => '0',
            'payment_alliance_version' => '0.0.2',
        ];

        $permissions = [
            'access' => [
                'extension/payment/refund',
                'extension/payment/alliance'
            ],
            'modify' => [
                'extension/payment/refund',
                'extension/payment/alliance'
            ]
        ];

        foreach ($permissions as $type => $routes) {
            foreach ($routes as $route) {
                $this->model_user_user_group->addPermission(
                    $this->user->getGroupId(),
                    $type,
                    $route
                );
            }
        }

        $this->model_setting_setting->editSetting('payment_alliance', $default_settings);
        $this->model_extension_payment_db->initDB();
        $this->installEvents();
    }

    /**
     * @return void
     */
    public function uninstall()
    {
        $this->load->language('extension/report');
        $this->load->model('extension/payment/alliance');
        $this->load->model('setting/extension');
        $this->load->model('user/user_group');

        $permissions = [
            'access' => [
                'extension/payment/refund',
                'extension/payment/alliance'
            ],
            'modify' => [
                'extension/payment/refund',
                'extension/payment/alliance'
            ]
        ];

        foreach ($permissions as $type => $routes) {
            foreach ($routes as $route) {
                $this->model_user_user_group->removePermission(
                    $this->user->getGroupId(),
                    $type,
                    $route
                );
            }
        }

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/report')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->model_setting_extension->uninstall('report', $this->request->get['code']);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @return void
     */
    public function upgrade() {
        $this->load->model('extension/payment/db');
        $this->model_extension_payment_db->updateAllianceOrderTable();
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('payment_alliance', ['payment_alliance_version' => '1.0.0']);
    }

    /**
     * @return void
     */
    private function installEvents()
    {
        $this->load->model('setting/event');

        $defaults = [
            'status' => 1,
            'sort_order' => 0,
            'description' => '',
        ];

        foreach ($this->events as $event) {
            $this->model_setting_event->deleteEventByCode($event['event_code']);
            $event['code'] = $event['event_code'];
            foreach ($defaults as $key => $value) {
                if (!isset($event[$key])) {
                    $event[$key] = $value;
                }
            }

            $this->model_setting_event->addEvent(
                $event['event_code'],
                $event['trigger'],
                $event['action'],
                $event['status'] ?? 1,
                $event['sort_order'] ?? 0
            );
        }
    }

    /**
     * @param $route
     * @param $args
     * @param $output
     * @return void
     */
    public function operationsEvent(&$route, &$args, &$output)
    {
        $this->load->library('AllianceConfig');
        $this->load->language('extension/payment/alliance');
        $this->load->model('extension/payment/alliance');
        $buttonHtml = '';
        $order_id = (int)$this->request->get['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);

        if ($order_info['payment_code'] === AllianceConfig::ALLIANCE_PAYMENT_CODE) {
            $alliance_order = $this->model_extension_payment_alliance->getAllianceOrder($order_id);

            $buttonUrl = 'index.php?route=extension/payment/alliance/checkOperationStatus&user_token='
                . $this->getUserToken()
                . '&order_id='
                . $order_id;

            $completionUrl = 'index.php?route=extension/payment/alliance/executePreAuthCompletion&user_token='
                . $this->getUserToken()
                . '&order_id='
                . $order_id;

            $isPreauth = (!empty($alliance_order) && $alliance_order['hpp_pay_type'] === 'PREAUTH') ? 'true' : 'false';

            $buttonHtml = '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const container = document.querySelector(".page-header .container-fluid .pull-right");
                    if (container) {
                        const a = document.createElement("a");
                        a.className = "btn btn-primary";
                        a.style = "margin-right:5px;";
                        a.href = "' . $buttonUrl . '";
                        a.innerHTML = \'' . $this->language->get('button_check_status') . '\';
                        container.prepend(a);

                        if (' . $isPreauth . ') {
                            const btn = document.createElement("button");
                            btn.type = "button";
                            btn.className = "btn btn-warning";
                            btn.style = "margin-right:5px;";
                            btn.innerHTML = \'' . $this->language->get('button_confirm_payment') . '\';
                            btn.onclick = function() {
                                btn.disabled = true;
                                fetch("' . $completionUrl . '")
                                    .then(function(r) { 
                                        return r.json(); 
                                    })
                                    .then(function(data) {
                                        if (data.success) {
                                            location.reload();
                                        } else {
                                            alert(data.message);
                                            btn.disabled = false;
                                        }
                                    })
                                    .catch(function() {
                                        btn.disabled = false;
                                    });
                            };
                            container.prepend(btn);
                        }
                    }
                });
            </script>';
        }

        $output = str_replace('</body>', $buttonHtml . '</body>', $output);
    }

    /**
     * @return bool
     * @throws Throwable
     */
    public function checkOperationStatus()
    {
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/alliance');
        $this->load->language('extension/payment/alliance');
        $this->document->setTitle($this->language->get('heading_title_order_data'));
        $this->load->library('AllianceEcom');
        $this->load->model('extension/payment/refund');

        $referer = $this->request->server['HTTP_REFERER'] ?? $this->url->link('common/dashboard');
        $order_id = $this->request->get['order_id'] ?? $this->request->get('order_id');
        $data['heading_title'] = $this->language->get('heading_title_order_data');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $data['back'] = $referer;

        $alliance_order = $this->model_extension_payment_alliance->getAllianceOrder($order_id);
        $order_callback = $this->model_extension_payment_alliance->getAllianceOrderCallback($order_id);

        if (!empty($alliance_order) && !empty($order_callback)) {
            $this->response->setOutput(
                $this->load->view(
                    'extension/payment/operation_info',
                    $this->model_extension_payment_alliance->fillOperationStatusData(
                        $data,
                        $order_callback,
                        false,
                        $this->model_extension_payment_refund->checkIfOrderRefunded($order_id),
                        $alliance_order
                    )
                )
            );

            return true;
        }

        try {
            $ecom = new AllianceEcom($this->registry);
            $jwe_token = $ecom->authorizeByVirtualDevice($this->config->get('payment_alliance_service_code_id'));
            $decrypt_auth = $ecom->decryptResponse($jwe_token);

            $e_com_response = $ecom->checkOperationStatus($decrypt_auth, $alliance_order['hpp_order_id']);
        } catch (\Exception $exception) {
            $json['msgType'] = $this->language->get('text_technical_error');
            $this->response->setOutput(
                $this->load->view('extension/payment/operation_info',
                    $this->model_extension_payment_alliance->fillOperationStatusData($data, $json, true))
            );

            return false;
        }

        if (
            isset($e_com_response['msgType'])
            &&
            (strpos($e_com_response['msgType'], 'ERROR') || strpos($e_com_response['msgType'], 'error'))
        ) {
            $this->response->setOutput(
                $this->load->view(
                    'extension/payment/operation_info',
                    $this->model_extension_payment_alliance->fillOperationStatusData(
                        $data,
                        $e_com_response,
                        true,
                        $this->model_extension_payment_refund->checkIfOrderRefunded($order_id),
                        $alliance_order
                    )
                )
            );

            return false;
        }

        $this->response->setOutput(
            $this->load->view(
                'extension/payment/operation_info',
                $this->model_extension_payment_alliance->fillOperationStatusData(
                    $data,
                    $e_com_response,
                    false,
                    $this->model_extension_payment_refund->checkIfOrderRefunded($order_id),
                    $alliance_order
                )
            )
        );

        return true;
    }

    /**
     * @return void
     */
    public function executePreAuthCompletion()
    {
        $this->load->model('extension/payment/alliance');
        $this->load->model('sale/order');
        $this->load->language('extension/payment/alliance');
        $this->load->library('AllianceLogger');

        $json = [];
        $order_id = (int)($this->request->get['order_id'] ?? 0);

        if (!$order_id) {
            $json['message'] = $this->language->get('error_something_went_wrong');
            $json['success'] = false;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $alliance_order = $this->model_extension_payment_alliance->getAllianceOrder($order_id);

        if (empty($alliance_order) || ($alliance_order['hpp_pay_type'] ?? '') !== 'PREAUTH') {
            $json['message'] = $this->language->get('error_not_preauth_order');
            $json['success'] = false;
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $order = $this->model_sale_order->getOrder($order_id);

        $conversionRate = (float)($alliance_order['conversion_rate'] ?? 0);

        $current_minor_units = (int)round($order['total'] * 100);
        $original_amount = (int)$alliance_order['original_authorized_amount'];

        if ($original_amount > 0) {
            $lower = (int)($original_amount * 0.80);
            $upper = (int)($original_amount * 1.20);

            if ($current_minor_units < $lower || $current_minor_units > $upper) {
                $logger = new AllianceLogger($this->registry);
                $logger->log(
                    AllianceLogger::ERROR,
                    sprintf(
                        'PREAUTH completion rejected for order #%d: current amount %d minor units'
                        . ' is outside ±20%% of original %d minor units (conversionRate=%.6f)',
                        $order_id,
                        $current_minor_units,
                        $original_amount,
                        $conversionRate
                    )
                );
                $json['message'] = $this->language->get('error_completion_amount');
                $json['success'] = false;
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));
                return;
            }
        }

        if ($conversionRate > 0) {
            $coin_amount_uah = (int)round($order['total'] * $conversionRate * 100);
        } else {
            $coin_amount_uah = (int)round($order['total'] * 100);
        }

        try {
            $this->load->library('AllianceEcom');
            $ecom = new AllianceEcom($this->registry);
            $jwe_token = $ecom->authorizeByVirtualDevice(
                $this->config->get('payment_alliance_service_code_id')
            );
            $decrypt_auth = $ecom->decryptResponse($jwe_token);
            $alliance_order['coin_amount'] = $coin_amount_uah;
            $completionResult = $ecom->executeCompletion(
                $decrypt_auth,
                $alliance_order,
                $this->url
            );

            if (!$completionResult) {
                throw new Exception('Completion failed');
            }

            $json['message'] = $this->language->get('text_completion_success');
            $json['success'] = true;
        } catch (\Throwable $e) {
            $logger = new AllianceLogger($this->registry);
            $logger->log(AllianceLogger::ERROR, 'PREAUTH completion error: ' . $e->getMessage());
            $json['message'] = $this->language->get('error_something_went_wrong');
            $json['success'] = false;
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    /**
     * @return string
     */
    private function getUserToken()
    {
        $session = $this->session;

        return $session->data['user_token'];
    }
}
