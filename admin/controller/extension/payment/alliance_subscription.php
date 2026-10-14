<?php

declare(strict_types=1);

class ControllerExtensionPaymentAllianceSubscription extends Controller
{
    public $version = '1.1.2';

    private $fullPermissionRoutes = [
        'extension/payment/alliance_subscription',
        'extension/payment/subscription_plan_template',
        'extension/payment/subscription_plan',
        'extension/payment/subscription',
    ];

    private const REPRICE_ROUTE = 'extension/payment/subscription_reprice';

    private $events = [
        [
            'trigger' => 'admin/view/sale/order_info/after',
            'action' => 'extension/payment/alliance_subscription/operationsEvent',
            'event_code' => 'alliance_subscription_operations',
            'sort_order' => 3,
        ],
        [
            'trigger' => 'catalog/controller/checkout/cart/add/before',
            'action' => 'extension/payment/alliance_subscription/cartGuardEvent',
            'event_code' => 'alliance_subscription_cart_guard',
            'sort_order' => 0,
        ],
        [
            'trigger' => 'catalog/view/product/product/after',
            'action' => 'extension/payment/alliance_subscription/injectSubscribeBlockEvent',
            'event_code' => 'alliance_subscription_product_block',
            'sort_order' => 1,
        ],
        [
            'trigger' => 'catalog/view/account/account/after',
            'action' => 'extension/payment/alliance_subscription/injectAccountMenuLinkEvent',
            'event_code' => 'alliance_subscription_account_link',
            'sort_order' => 1,
        ],
    ];

    private $retiredEventCodes = [
        'alliance_subscription_menu',
    ];

    public function index()
    {
        $this->load->language('extension/payment/alliance_subscription');
        $this->load->model('setting/setting');
        $this->document->setTitle($this->language->get('heading_title'));

        $installedVersion = $this->config->get('payment_alliance_subscription_version');

        if (!$installedVersion || version_compare($installedVersion, $this->version, '<')) {
            $this->upgrade();
        }

        if ($this->request->server['REQUEST_METHOD'] == 'POST'
            && ($this->request->post['action'] ?? '') === 'save'
        ) {
            if (!$this->user->hasPermission('modify', 'extension/payment/alliance_subscription')) {
                $this->session->data['warning'] = $this->language->get('error_permission');
                $this->response->redirect(
                    $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true)
                );
                return;
            }

            $post = $this->request->post;
            $post['payment_alliance_subscription_version'] = $this->version;

            $this->model_setting_setting->editSetting('payment_alliance_subscription', $post);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true)
            );
            return;
        }

        $data = $this->buildViewData();

        $this->response->setOutput($this->load->view('extension/payment/alliance_subscription', $data));
    }

    private function buildViewData(): array
    {
        $this->load->model('extension/payment/alliance_subscription');

        $data['heading_title'] = $this->language->get('heading_title');
        $data['user_token'] = $this->getUserToken();
        $data['action'] = $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->getUserToken() . '&type=payment', true);

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true),
            ],
        ];

        $data['links'] = [
            'plan_template' => $this->url->link('extension/payment/subscription_plan_template', 'user_token=' . $this->getUserToken(), true),
            'plan' => $this->url->link('extension/payment/subscription_plan', 'user_token=' . $this->getUserToken(), true),
            'subscription' => $this->url->link('extension/payment/subscription', 'user_token=' . $this->getUserToken(), true),
        ];

        $data['counts'] = $this->model_extension_payment_alliance_subscription->getDashboardCounts();

        $data['payment_alliance_subscription_status'] = $this->config->get('payment_alliance_subscription_status');
        $data['payment_alliance_subscription_notification_encryption'] = $this->config->get('payment_alliance_subscription_notification_encryption');

        $data['success'] = $this->session->data['success'] ?? '';
        $data['warning'] = $this->session->data['warning'] ?? '';
        unset($this->session->data['success'], $this->session->data['warning']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $data;
    }

    public function install()
    {
        $this->load->language('extension/payment/alliance_subscription');
        $this->load->model('setting/setting');
        $this->load->model('extension/payment/subscription_db');
        $this->load->model('user/user_group');

        $defaultSettings = [
            'payment_alliance_subscription_status' => '0',
            'payment_alliance_subscription_notification_encryption' => '',
            'payment_alliance_subscription_version' => $this->version,
        ];

        foreach ($this->fullPermissionRoutes as $route) {
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', $route);
            $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', $route);
        }

        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', self::REPRICE_ROUTE);

        $this->model_setting_setting->editSetting('payment_alliance_subscription', $defaultSettings);
        $this->model_extension_payment_subscription_db->initDB();
        $this->installEvents();
    }

    public function uninstall()
    {
        $this->load->language('extension/report');
        $this->load->model('setting/extension');
        $this->load->model('user/user_group');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/payment/alliance_subscription')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            foreach ($this->fullPermissionRoutes as $route) {
                $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', $route);
                $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', $route);
            }

            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', self::REPRICE_ROUTE);
            $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', self::REPRICE_ROUTE);

            $this->model_setting_extension->uninstall('payment', $this->request->get['code'] ?? 'alliance_subscription');

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function upgrade()
    {
        $this->load->model('extension/payment/subscription_db');
        $this->model_extension_payment_subscription_db->upgradeSubscriptionTables();
        $this->load->model('setting/setting');
        $this->model_setting_setting->editSetting('payment_alliance_subscription', [
            'payment_alliance_subscription_version' => $this->version,
        ]);

        $this->installEvents();

        $this->load->model('setting/event');
        foreach ($this->retiredEventCodes as $eventCode) {
            $this->model_setting_event->deleteEventByCode($eventCode);
        }
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

            foreach ($defaults as $key => $value) {
                if (!isset($event[$key])) {
                    $event[$key] = $value;
                }
            }

            $this->model_setting_event->addEvent(
                $event['event_code'],
                $event['trigger'],
                $event['action'],
                $event['status'],
                $event['sort_order']
            );
        }
    }

    /**
     * @param string $route
     * @param array $args
     * @param string $output
     */
    public function operationsEvent(&$route, &$args, &$output)
    {
        $this->load->language('extension/payment/alliance_subscription');
        $this->load->model('extension/payment/subscription');

        $orderId = (int)($this->request->get['order_id'] ?? 0);

        if (!$orderId) {
            return;
        }

        $subscription = $this->model_extension_payment_subscription->getSubscriptionByOrderId($orderId);

        if (empty($subscription)) {
            return;
        }

        $viewUrl = html_entity_decode($this->url->link(
            'extension/payment/subscription/diagnostics',
            'user_token=' . $this->getUserToken() . '&subscription_id=' . (int)$subscription['subscription_id'],
            true
        ));

        $buttonHtml = '
        <script>
            document.addEventListener("DOMContentLoaded", function() {
                const container = document.querySelector(".page-header .container-fluid .pull-right");
                if (container) {
                    const a = document.createElement("a");
                    a.className = "btn btn-info";
                    a.style = "margin-right:5px;";
                    a.href = "' . $viewUrl . '";
                    a.innerHTML = \'' . $this->language->get('button_view_subscription') . '\';
                    container.prepend(a);
                }
            });
        </script>';

        $output = str_replace('</body>', $buttonHtml . '</body>', $output);
    }

    private function getUserToken(): string
    {
        return $this->session->data['user_token'];
    }
}
