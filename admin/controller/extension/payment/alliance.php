<?php

/**
 * Class ControllerExtensionPaymentAlliance.
 */
class ControllerExtensionPaymentAlliance extends Controller
{
    public $version = '1.1.1';
    private $events = [
        [
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => 'extension/payment/alliance/operationsEvent',
            'event_code' => 'alliance_operations',
            'sort_order' => 1,
        ],
        [
            'trigger' => 'catalog/model/checkout/order/addOrder/after',
            'action' => 'extension/payment/event/fix_session_order_id/afterAddOrder',
            'event_code' => 'alliance_fix_session_order_id',
        ],
        [
            'trigger' => 'admin/view/sale/order_info/after',
            'action'=> 'extension/payment/refund/injectRefundButton',
            'event_code' => 'alliance_add_refund_button_order_info',
            'sort_order' => 1,
        ],
        [
            'trigger' => 'admin/view/sale/order_info/before',
            'action'=> 'extension/module/order_refund_helper/addReturnedProducts',
            'event_code' => 'alliance_order_refund_data',
            'sort_order' => 1,
        ]
    ];

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

    public function upgrade() {
        $this->load->model('extension/payment/db');
        $this->model_extension_payment_db->updateAllianceOrderTable();
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('payment_alliance', ['payment_alliance_version' => '1.0.0']);
    }

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
        $buttonHtml = '';
        $order_id = (int)$this->request->get['order_id'];
        $order_info = $this->model_sale_order->getOrder($order_id);

        if ($order_info['payment_code'] === AllianceConfig::ALLIANCE_PAYMENT_CODE) {
            $buttonUrl = 'index.php?route=extension/payment/alliance/checkOperationStatus&user_token='
                . $this->getUserToken()
                . '&order_id='
                . $order_id;
            $buttonHtml = '
            <script>
                document.addEventListener("DOMContentLoaded", function() {
                    const container = document.querySelector(".page-header .container-fluid .pull-right");
                    if (container) {
                        const a = document.createElement("a");
                        a.className = "btn btn-primary";
                        a.style = "margin-right:5px;";
                        a.href = "'. $buttonUrl . '"
                        a.innerHTML = \'' . $this->language->get('button_check_status') . '\';
                        
                        container.prepend(a);
                    }
                });
            </script>';
        }

        $output = str_replace('</body>', $buttonHtml . '</body>', $output);
    }

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
                        $this->model_extension_payment_refund->checkIfOrderRefunded($order_id)
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
                        $this->model_extension_payment_refund->checkIfOrderRefunded($order_id)
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
                    $this->model_extension_payment_refund->checkIfOrderRefunded($order_id)
                )
            )
        );

        return true;
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
