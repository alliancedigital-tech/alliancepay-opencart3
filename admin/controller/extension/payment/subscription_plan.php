<?php

declare(strict_types=1);

class ControllerExtensionPaymentSubscriptionPlan extends Controller
{
    private const ROUTE = 'extension/payment/subscription_plan';

    public function index(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->indexInner();
        } catch (\Throwable $e) {
            $this->logAndWarn($e);
            $this->response->redirect($this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true));
        }
    }

    private function indexInner(): void
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');
        $this->document->setTitle($this->language->get('heading_title'));

        $filterProductId = (int)($this->request->get['filter_product_id'] ?? 0);
        $filterStatus = (string)($this->request->get['filter_status'] ?? '');
        $filterSyncStatus = (string)($this->request->get['filter_sync_status'] ?? '');
        $page = max(1, (int)($this->request->get['page'] ?? 1));
        $limit = 20;

        $filterData = [
            'filter_product_id' => $filterProductId,
            'filter_status' => $filterStatus,
            'filter_sync_status' => $filterSyncStatus,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ];

        $data = $this->baseViewData();
        $data['plans'] = [];

        foreach ($this->model_extension_payment_subscription_plan->getPlans($filterData) as $plan) {
            $data['plans'][] = [
                'plan_id' => $plan['plan_id'],
                'product_name' => $plan['product_name'],
                'provider_plan_id' => $plan['provider_plan_id'],
                'status' => $plan['status'],
                'sync_status' => $plan['sync_status'],
                'is_active' => (int)$plan['is_active'],
                'active_subscriptions_count' => (int)$plan['active_subscriptions_count'],
                'is_archive_candidate' => $this->model_extension_payment_subscription_plan->isArchiveCandidate($plan),
                'edit' => $this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $plan['plan_id'], true),
            ];
        }

        $total = $this->model_extension_payment_subscription_plan->getTotalPlans($filterData);

        $this->load->library('pagination');
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&page={page}', true);
        $data['pagination'] = $pagination->render();

        $data['filter_product_id'] = $filterProductId;
        $data['filter_status'] = $filterStatus;
        $data['filter_sync_status'] = $filterSyncStatus;
        $data['add'] = $this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true);

        $this->response->setOutput($this->load->view(self::ROUTE . '_list', $data));
    }

    public function add(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->addInner();
        } catch (\Throwable $e) {
            $this->logAndWarn($e);
            $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
        }
    }

    private function addInner(): void
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->model('extension/payment/subscription_plan_template');
        $this->load->library('AllianceConfig');
        $this->load->library('AllianceSubscriptionConfig');
        $this->document->setTitle($this->language->get('heading_title'));

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->handleSave();
            return;
        }

        $data = $this->baseViewData();
        $data['action'] = $this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true);
        $data['plan'] = [
            'product_id' => '',
            'product_name' => '',
            'template_id' => '',
            'name' => '',
            'description' => '',
            'category_indicator' => '',
            'applies_from' => '',
            'applies_to' => '',
        ];
        $data['rules'] = [];
        $data['templates'] = $this->model_extension_payment_subscription_plan_template->getActiveTemplates();
        $data['currencies'] = array_keys(AllianceConfig::CURRENCY_CODE_MAP);

        $data['product_autocomplete_url'] = html_entity_decode(
            $this->url->link(self::ROUTE . '/productAutocomplete', 'user_token=' . $this->getUserToken(), true)
        );
        $data['read_only'] = false;

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
    }

    public function productAutocomplete()
    {
        $json = [];

        if ($this->user->hasPermission('access', self::ROUTE) && isset($this->request->get['filter_name'])) {
            $this->load->model('catalog/product');

            $results = $this->model_catalog_product->getProducts([
                'filter_name' => $this->request->get['filter_name'],
                'start' => 0,
                'limit' => 5,
            ]);

            foreach ($results as $result) {
                $json[] = [
                    'product_id' => $result['product_id'],
                    'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
                    'model' => $result['model'],

                    'price' => $result['price'],
                ];
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    private function handleSave(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->handleSaveInner();
        } catch (\Throwable $e) {
            (new AllianceLogger($this->registry))->log(
                AllianceLogger::ERROR,
                'Subscription plan save - uncaught ' . get_class($e) . ': ' . $e->getMessage()
                . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
            );

            $this->session->data['warning'] = $this->language->get('error_something_went_wrong') . ' ' . $e->getMessage();
            $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
        }
    }

    private function handleSaveInner(): void
    {
        $this->load->library('AllianceConfig');
        $this->load->library('AlliancePlanKeyHasher');
        $this->load->library('AllianceSubscriptionConfig');

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->session->data['warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $post = $this->request->post;
        $productId = (int)($post['product_id'] ?? 0);
        $templateId = !empty($post['template_id']) ? (int)$post['template_id'] : null;
        $categoryIndicator = (string)($post['category_indicator'] ?? '');
        $rules = $this->extractRulesFromPost($post);

        if (!$productId || !$categoryIndicator || !$rules) {
            $this->session->data['warning'] = $this->language->get('error_form_required');
            $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $merchantId = (string)$this->config->get('payment_alliance_merchant_id');
        $planKey = AlliancePlanKeyHasher::compute($merchantId, $productId, $templateId, $categoryIndicator, $rules);

        $existing = $this->model_extension_payment_subscription_plan->getPlanByPlanKey($planKey);

        if (!empty($existing)) {
            $this->session->data['success'] = $this->language->get('text_matching_plan_exists');
            $this->response->redirect(
                $this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $existing['plan_id'], true)
            );
            return;
        }

        $name = (string)($post['name'] ?? $this->model_extension_payment_subscription_plan->getProductName($productId));

        $description = trim((string)($post['description'] ?? ''));
        if ($description === '') {
            $description = $name;
        }
        $appliesFrom = !empty($post['applies_from']) ? (string)$post['applies_from'] : null;
        $appliesTo = !empty($post['applies_to']) ? (string)$post['applies_to'] : null;

        $planData = [
            'name' => $name,
            'description' => $description,
            'categorySubcategoryIndicator' => $categoryIndicator,
            'merchantId' => $merchantId,
            'rules' => $rules,
        ];

        if ($appliesFrom) {
            $planData['appliesDateTimeFrom'] = $appliesFrom;
        }

        if ($appliesTo) {
            $planData['appliesDateTimeTo'] = $appliesTo;
        }

        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $ecom = new AllianceEcom($this->registry);

        try {
            $createResponse = $ecom->createSubscriptionPlan($planData);
        } catch (\Throwable $e) {
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Subscription plan create failed: ' . $e->getMessage());
            $this->session->data['warning'] = $this->language->get('error_plan_create_failed') . ' ' . $e->getMessage();
            $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $providerPlanId = (string)($createResponse['planId'] ?? '');
        $versionId = (string)($createResponse['versionId'] ?? '');

        $baseRow = [
            'provider_plan_id' => $providerPlanId,
            'version_id' => $versionId,
            'product_id' => $productId,
            'template_id' => $templateId,
            'plan_key' => $planKey,
            'merchant_id' => $merchantId,
            'name' => $name,
            'description' => $description,
            'category_indicator' => $categoryIndicator,
            'rules' => $rules,
            'applies_from' => $appliesFrom,
            'applies_to' => $appliesTo,
        ];

        try {
            $ecom->setInfiniteSchedule($providerPlanId, $versionId);
        } catch (\Throwable $e) {

            (new AllianceLogger($this->registry))->log(
                AllianceLogger::ERROR,
                'Infinite schedule failed for provider plan ' . $providerPlanId . ': ' . $e->getMessage()
            );

            $baseRow['sync_error'] = $e->getMessage();
            $planId = $this->model_extension_payment_subscription_plan->insertErrorPlan($baseRow);

            $this->model_extension_payment_subscription_plan->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_CREATE_FAIL,
                'plan_id' => $planId,
                'product_id' => $productId,
                'user_id' => $this->user->getId(),
                'details' => ['provider_plan_id' => $providerPlanId, 'error' => $e->getMessage()],
            ]);

            $this->session->data['warning'] = sprintf(
                $this->language->get('error_infinite_schedule_failed'),
                $providerPlanId,
                $e->getMessage()
            );
            $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        try {
            $planId = $this->model_extension_payment_subscription_plan->insertActivePlan($baseRow);
        } catch (\Throwable $e) {

            if (stripos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate entry') !== false) {
                $existing = $this->model_extension_payment_subscription_plan->getPlanByPlanKey($planKey);
                $this->session->data['success'] = $this->language->get('text_matching_plan_exists');
                $this->response->redirect(
                    $this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . ($existing['plan_id'] ?? 0), true)
                );
                return;
            }

            throw $e;
        }

        $this->model_extension_payment_subscription_plan->insertAuditLog([
            'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_CREATE,
            'plan_id' => $planId,
            'product_id' => $productId,
            'user_id' => $this->user->getId(),
            'details' => ['provider_plan_id' => $providerPlanId],
        ]);

        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
    }

    public function edit(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->editInner();
        } catch (\Throwable $e) {
            $this->logAndWarn($e);
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
        }
    }

    private function editInner(): void
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');
        $this->document->setTitle($this->language->get('heading_title'));

        $planId = (int)($this->request->get['plan_id'] ?? 0);
        $plan = $this->model_extension_payment_subscription_plan->getPlan($planId);

        if (empty($plan)) {
            $this->session->data['warning'] = $this->language->get('error_not_found');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $data = $this->baseViewData();
        $data['plan'] = $plan;
        $data['plan']['product_name'] = $this->model_extension_payment_subscription_plan->getProductName((int)$plan['product_id']);
        $data['rules'] = json_decode((string)$plan['rules'], true) ?: [];
        $data['is_archive_candidate'] = $this->model_extension_payment_subscription_plan->isArchiveCandidate($plan);
        $data['reprice_url'] = $this->url->link('extension/payment/subscription_reprice', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true);
        $data['deactivate_url'] = $this->url->link(self::ROUTE . '/deactivateForNewCustomers', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true);
        $data['reactivate_url'] = $this->url->link(self::ROUTE . '/reactivateForNewCustomers', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true);
        $data['archive_url'] = $this->url->link(self::ROUTE . '/archiveAtProvider', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true);

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', array_merge($data, ['read_only' => true])));
    }

    public function deactivateForNewCustomers()
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');

        $planId = (int)($this->request->get['plan_id'] ?? 0);

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->session->data['warning'] = $this->language->get('error_permission');
        } else {
            $this->model_extension_payment_subscription_plan->deactivateForNewCustomers($planId);
            $this->model_extension_payment_subscription_plan->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_DEACTIVATE_LOCAL,
                'plan_id' => $planId,
                'user_id' => $this->user->getId(),
            ]);
            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
    }

    public function reactivateForNewCustomers()
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');

        $planId = (int)($this->request->get['plan_id'] ?? 0);

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->session->data['warning'] = $this->language->get('error_permission');
        } else {
            $this->model_extension_payment_subscription_plan->reactivateForNewCustomers($planId);
            $this->model_extension_payment_subscription_plan->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_REACTIVATE_LOCAL,
                'plan_id' => $planId,
                'user_id' => $this->user->getId(),
            ]);
            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
    }

    public function archiveAtProvider()
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $this->load->library('AllianceSubscriptionConfig');

        $planId = (int)($this->request->post['plan_id'] ?? $this->request->get['plan_id'] ?? 0);
        $plan = $this->model_extension_payment_subscription_plan->getPlan($planId);

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->session->data['warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        if (empty($plan) || !$this->model_extension_payment_subscription_plan->isArchiveCandidate($plan)) {
            $this->session->data['warning'] = $this->language->get('error_not_archive_candidate');
            $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        if (empty($this->request->post['confirm_archive'])) {
            $this->session->data['warning'] = $this->language->get('error_archive_confirmation_required');
            $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        try {
            $ecom = new AllianceEcom($this->registry);

            $ecom->updatePlanStatus($plan['provider_plan_id'], AllianceSubscriptionConfig::PLAN_STATUS_ARCHIVED);
            $ecom->deletePlanVersion($plan['provider_plan_id'], (string)$plan['version_id']);

            $this->model_extension_payment_subscription_plan->markArchived($planId);
            $this->model_extension_payment_subscription_plan->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_ARCHIVE_PROVIDER,
                'plan_id' => $planId,
                'user_id' => $this->user->getId(),
            ]);

            $this->session->data['success'] = $this->language->get('text_success');
        } catch (\Throwable $e) {
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Plan archive failed: ' . $e->getMessage());
            $this->session->data['warning'] = $this->language->get('error_archive_failed') . ' ' . $e->getMessage();
        }

        $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
    }

    /**
     * @param array $post
     */
    private function extractRulesFromPost(array $post): array
    {
        $rules = [];

        foreach ((array)($post['rule_payment_number_from'] ?? []) as $index => $from) {
            $currencyAlpha = (string)($post['rule_currency'][$index] ?? 'UAH');

            try {
                $currencyCode = AllianceConfig::getCurrencyCode($currencyAlpha);
            } catch (\InvalidArgumentException $e) {
                $currencyCode = AllianceConfig::CURRENCY_CODE_MAP['UAH'];
            }

            $rules[] = [
                'paymentNumberFrom' => (int)$from,
                'paymentNumberTo' => (int)($post['rule_payment_number_to'][$index] ?? $from),
                'intervalValue' => (int)($post['rule_interval_value'][$index] ?? 1),
                'intervalUnit' => (string)($post['rule_interval_unit'][$index] ?? 'MONTH'),
                'coinAmount' => (int)($post['rule_coin_amount'][$index] ?? 0),
                'currency' => $currencyCode,
            ];
        }

        return $rules;
    }

    private function baseViewData(): array
    {
        $data['heading_title'] = $this->language->get('heading_title');
        $data['user_token'] = $this->getUserToken();
        $data['back'] = $this->url->link('extension/payment/alliance_subscription', 'user_token=' . $this->getUserToken(), true);

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => $this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true),
            ],
        ];

        $data['success'] = $this->session->data['success'] ?? '';
        $data['warning'] = $this->session->data['warning'] ?? '';
        unset($this->session->data['success'], $this->session->data['warning']);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $data;
    }

    private function getUserToken(): string
    {
        return $this->session->data['user_token'];
    }

    private function logAndWarn(\Throwable $e): void
    {
        $this->load->language(self::ROUTE);

        (new AllianceLogger($this->registry))->log(
            AllianceLogger::ERROR,
            'Subscription plan - uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
        );

        $this->session->data['warning'] = $this->language->get('error_something_went_wrong') . ' ' . $e->getMessage();
    }
}
