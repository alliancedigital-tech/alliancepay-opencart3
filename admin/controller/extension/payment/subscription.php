<?php

declare(strict_types=1);

class ControllerExtensionPaymentSubscription extends Controller
{

    public function index()
    {
        $this->load->language('extension/payment/subscription');
        $this->load->model('extension/payment/subscription');
        $this->document->setTitle($this->language->get('heading_title'));

        $filter = [
            'filter_customer' => (string)($this->request->get['filter_customer'] ?? ''),
            'filter_plan_id' => (int)($this->request->get['filter_plan_id'] ?? 0),
            'filter_status' => (string)($this->request->get['filter_status'] ?? ''),
            'filter_provider_order_id' => (string)($this->request->get['filter_provider_order_id'] ?? ''),
        ];

        $page = max(1, (int)($this->request->get['page'] ?? 1));
        $limit = 20;

        $listFilter = $filter;
        $listFilter['start'] = ($page - 1) * $limit;
        $listFilter['limit'] = $limit;

        $total = $this->model_extension_payment_subscription->getTotalSubscriptions($filter);
        $subscriptions = $this->model_extension_payment_subscription->getSubscriptions($listFilter);

        $data = $filter;
        $data['heading_title'] = $this->language->get('heading_title');
        $data['user_token'] = $this->getUserToken();
        $data['subscriptions'] = [];

        foreach ($subscriptions as $subscription) {
            $data['subscriptions'][] = [
                'subscription_id' => $subscription['subscription_id'],
                'order_id' => $subscription['order_id'],
                'customer_name' => trim(($subscription['firstname'] ?? '') . ' ' . ($subscription['lastname'] ?? '')),
                'customer_email' => $subscription['email'] ?? '',
                'plan_name' => $subscription['plan_name'] ?? '',
                'product_name' => $subscription['product_name'] ?? '',
                'status' => $subscription['status'],
                'provider_order_id' => $subscription['provider_order_id'],
                'created_at' => $subscription['created_at'],
                'diagnostics' => $this->url->link(
                    'extension/payment/subscription/diagnostics',
                    'user_token=' . $this->getUserToken() . '&subscription_id=' . (int)$subscription['subscription_id'],
                    true
                ),
            ];
        }

        $pages = (int)ceil($total / $limit);
        $data['pagination'] = [];
        for ($i = 1; $i <= $pages; $i++) {
            $data['pagination'][] = [
                'page' => $i,
                'active' => $i === $page,
                'href' => $this->url->link(
                    'extension/payment/subscription',
                    'user_token=' . $this->getUserToken() . '&page=' . $i
                        . '&filter_customer=' . urlencode($filter['filter_customer'])
                        . '&filter_plan_id=' . (int)$filter['filter_plan_id']
                        . '&filter_status=' . urlencode($filter['filter_status'])
                        . '&filter_provider_order_id=' . urlencode($filter['filter_provider_order_id']),
                    true
                ),
            ];
        }

        $data['back'] = $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true);
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/subscription', 'user_token=' . $this->getUserToken(), true),
            ],
        ];

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/subscription_list', $data));
    }

    public function diagnostics()
    {
        $this->load->language('extension/payment/subscription');
        $this->load->model('extension/payment/subscription');
        $this->load->library('AllianceEcom');
        $this->document->setTitle($this->language->get('heading_title_diagnostics'));

        $subscriptionId = (int)($this->request->get['subscription_id'] ?? 0);
        $subscription = $this->model_extension_payment_subscription->getSubscription($subscriptionId);

        $data = [];
        $data['heading_title'] = $this->language->get('heading_title_diagnostics');
        $data['user_token'] = $this->getUserToken();
        $data['back'] = $this->url->link('extension/payment/subscription', 'user_token=' . $this->getUserToken(), true);
        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link('extension/payment/subscription', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title_diagnostics'),
                'href' => '',
            ],
        ];

        $data['subscription'] = $subscription;
        $data['rules_snapshot_pretty'] = !empty($subscription['rules_snapshot'])
            ? json_encode(json_decode($subscription['rules_snapshot'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            : '';

        $scheduledPayments = $subscription
            ? $this->model_extension_payment_subscription->getScheduledPayments($subscriptionId)
            : [];

        $data['scheduled_payments'] = [];
        foreach ($scheduledPayments as $scheduledPayment) {
            $data['scheduled_payments'][] = $scheduledPayment + [
                'run_now_url' => $this->url->link(
                    'extension/payment/subscription/runNow',
                    'user_token=' . $this->getUserToken(),
                    true
                ),
            ];
        }

        $data['live_status_error'] = '';
        $data['live_status'] = [];

        if (!empty($subscription['provider_order_id'])) {
            try {
                $ecom = new AllianceEcom($this->registry);
                $data['live_status'] = $ecom->checkSubscriptionOperationStatus($subscription['provider_order_id']);
            } catch (\Throwable $e) {
                $data['live_status_error'] = $this->language->get('text_live_status_unavailable') . ' ' . $e->getMessage();
            }
        }

        $data['cancel_url'] = $subscription ? html_entity_decode($this->url->link(
            'extension/payment/subscription/cancel',
            'user_token=' . $this->getUserToken() . '&subscription_id=' . $subscriptionId,
            true
        )) : '';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/payment/subscription_diagnostics', $data));
    }

    public function cancel()
    {
        $this->load->language('extension/payment/subscription');
        $this->load->model('extension/payment/subscription');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/payment/subscription')) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $subscriptionId = (int)($this->request->get['subscription_id'] ?? $this->request->post['subscription_id'] ?? 0);
        $subscription = $this->model_extension_payment_subscription->getSubscription($subscriptionId);

        if (empty($subscription)) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_subscription_not_found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        try {
            $this->load->library('AllianceEcom');
            $ecom = new AllianceEcom($this->registry);
            $ecom->cancelSubscriptionOrder(
                $subscription['provider_order_id'],
                (string)$this->config->get('payment_alliance_merchant_id')
            );

            $this->model_extension_payment_subscription->updateSubscriptionStatus(
                $subscriptionId,
                AllianceSubscriptionConfig::SUBSCRIPTION_STATUS_CANCELED
            );
            $this->model_extension_payment_subscription->decrementPlanActiveCount((int)$subscription['plan_id']);
            $this->model_extension_payment_subscription->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_SUBSCRIPTION_CANCEL,
                'plan_id' => $subscription['plan_id'],
                'subscription_id' => $subscriptionId,
                'user_id' => $this->user->getId(),
                'details' => ['provider_order_id' => $subscription['provider_order_id']],
            ]);

            $json['success'] = true;
            $json['message'] = $this->language->get('text_cancel_success');
        } catch (\Throwable $e) {
            $this->load->library('AllianceLogger');
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Subscription cancel error: ' . $e->getMessage());
            $json['success'] = false;
            $json['message'] = $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function runNow()
    {
        $this->load->language('extension/payment/subscription');
        $this->load->model('extension/payment/subscription');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/payment/subscription')) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_permission');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $scheduledPaymentRowId = (int)($this->request->post['scheduled_payment_row_id'] ?? 0);
        $scheduledPayment = $this->model_extension_payment_subscription->getScheduledPayment($scheduledPaymentRowId);

        if (empty($scheduledPayment)) {
            $json['success'] = false;
            $json['message'] = $this->language->get('error_scheduled_payment_not_found');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($json));
            return;
        }

        $subscription = $this->model_extension_payment_subscription->getSubscription((int)$scheduledPayment['subscription_id']);

        try {
            $this->load->library('AllianceEcom');
            $ecom = new AllianceEcom($this->registry);
            $ecom->triggerRecurringPayment(
                $scheduledPayment['provider_order_id'],
                (string)$scheduledPayment['scheduled_payment_id'],
                (string)$this->config->get('payment_alliance_merchant_id')
            );

            $this->model_extension_payment_subscription->markScheduledPaymentProcessingWithRetry($scheduledPaymentRowId);
            $this->model_extension_payment_subscription->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_MANUAL_RETRY,
                'plan_id' => $subscription['plan_id'] ?? null,
                'subscription_id' => $scheduledPayment['subscription_id'],
                'user_id' => $this->user->getId(),
                'details' => ['scheduled_payment_row_id' => $scheduledPaymentRowId],
            ]);

            $json['success'] = true;
            $json['message'] = $this->language->get('text_run_now_success');
        } catch (\Throwable $e) {
            $this->load->library('AllianceLogger');
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Manual retry error: ' . $e->getMessage());
            $json['success'] = false;
            $json['message'] = $this->language->get('error_something_went_wrong');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function getUserToken(): string
    {
        return $this->session->data['user_token'];
    }
}
