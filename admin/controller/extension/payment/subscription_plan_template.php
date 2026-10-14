<?php

declare(strict_types=1);

class ControllerExtensionPaymentSubscriptionPlanTemplate extends Controller
{
    private const ROUTE = 'extension/payment/subscription_plan_template';

    public function index()
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));

        $filterName = (string)($this->request->get['filter_name'] ?? '');
        $filterStatus = (string)($this->request->get['filter_status'] ?? '');
        $page = max(1, (int)($this->request->get['page'] ?? 1));
        $limit = 20;

        $filterData = [
            'filter_name' => $filterName,
            'filter_status' => $filterStatus,
            'start' => ($page - 1) * $limit,
            'limit' => $limit,
        ];

        $data = $this->baseViewData();
        $data['templates'] = [];

        foreach ($this->model_extension_payment_subscription_plan_template->getTemplates($filterData) as $template) {
            $data['templates'][] = [
                'template_id' => $template['template_id'],
                'code' => $template['code'],
                'name' => $template['name'],
                'category_indicator' => $template['category_indicator'],
                'status' => $template['status'],
                'edit' => $this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&template_id=' . $template['template_id'], true),
                'archive' => $this->url->link(self::ROUTE . '/archive', 'user_token=' . $this->getUserToken() . '&template_id=' . $template['template_id'], true),
            ];
        }

        $total = $this->model_extension_payment_subscription_plan_template->getTotalTemplates($filterData);

        $this->load->library('pagination');
        $pagination = new Pagination();
        $pagination->total = $total;
        $pagination->page = $page;
        $pagination->limit = $limit;
        $pagination->url = $this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&page={page}', true);
        $data['pagination'] = $pagination->render();

        $data['filter_name'] = $filterName;
        $data['filter_status'] = $filterStatus;
        $data['add'] = $this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true);
        $data['can_modify'] = $this->user->hasPermission('modify', self::ROUTE);

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
        $this->load->library('AllianceSubscriptionConfig');
        $this->document->setTitle($this->language->get('heading_title'));

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            if (!$this->user->hasPermission('modify', self::ROUTE)) {
                $this->session->data['warning'] = $this->language->get('error_permission');
                $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
                return;
            }

            $errors = $this->validateForm($this->request->post);

            if ($errors) {
                $this->session->data['warning'] = implode(' ', $errors);
                $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
                return;
            }

            if ($this->model_extension_payment_subscription_plan_template->getTemplateByCode($this->request->post['code'])) {
                $this->session->data['warning'] = $this->language->get('error_code_exists');
                $this->response->redirect($this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true));
                return;
            }

            $this->model_extension_payment_subscription_plan_template->addTemplate([
                'code' => $this->request->post['code'],
                'name' => $this->request->post['name'],
                'category_indicator' => $this->request->post['category_indicator'],
                'rules' => $this->extractRulesFromPost($this->request->post),
            ]);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $data = $this->baseViewData();
        $data['action'] = $this->url->link(self::ROUTE . '/add', 'user_token=' . $this->getUserToken(), true);
        $data['template'] = [
            'code' => '',
            'name' => '',
            'category_indicator' => '',
        ];
        $data['rules'] = [];
        $data['code_readonly'] = false;

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
    }

    public function edit(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->editInner();
        } catch (\Throwable $e) {
            $this->logAndWarn($e);
            $this->response->redirect($this->url->link(
                self::ROUTE . '/edit',
                'user_token=' . $this->getUserToken() . '&template_id=' . (int)($this->request->get['template_id'] ?? 0),
                true
            ));
        }
    }

    private function editInner(): void
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');
        $this->document->setTitle($this->language->get('heading_title'));

        $templateId = (int)($this->request->get['template_id'] ?? 0);
        $template = $this->model_extension_payment_subscription_plan_template->getTemplate($templateId);

        if (empty($template)) {
            $this->session->data['warning'] = $this->language->get('error_not_found');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $inUse = $this->model_extension_payment_subscription_plan_template->isTemplateInUse($templateId);

        if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            if (!$this->user->hasPermission('modify', self::ROUTE)) {
                $this->session->data['warning'] = $this->language->get('error_permission');
                $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
                return;
            }

            if ($inUse) {
                $this->session->data['warning'] = $this->language->get('error_template_in_use');
                $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&template_id=' . $templateId, true));
                return;
            }

            $errors = $this->validateForm($this->request->post, true);

            if ($errors) {
                $this->session->data['warning'] = implode(' ', $errors);
                $this->response->redirect($this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&template_id=' . $templateId, true));
                return;
            }

            $this->model_extension_payment_subscription_plan_template->editTemplate($templateId, [
                'name' => $this->request->post['name'],
                'category_indicator' => $this->request->post['category_indicator'],
                'rules' => $this->extractRulesFromPost($this->request->post),
            ]);

            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $data = $this->baseViewData();
        $data['action'] = $this->url->link(self::ROUTE . '/edit', 'user_token=' . $this->getUserToken() . '&template_id=' . $templateId, true);
        $data['template'] = $template;
        $data['rules'] = json_decode((string)$template['rules_template'], true) ?: [];
        $data['code_readonly'] = true;
        $data['in_use'] = $inUse;

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
    }

    public function archive(): void
    {
        $this->load->library('AllianceLogger');

        try {
            $this->archiveInner();
        } catch (\Throwable $e) {
            $this->logAndWarn($e);
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
        }
    }

    private function archiveInner(): void
    {
        $this->load->language(self::ROUTE);
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceSubscriptionConfig');

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->session->data['warning'] = $this->language->get('error_permission');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $templateId = (int)($this->request->get['template_id'] ?? 0);

        if ($templateId) {
            $this->model_extension_payment_subscription_plan_template->archiveTemplate($templateId);
            $this->session->data['success'] = $this->language->get('text_success');
        }

        $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken(), true));
    }

    /**
     * @param array $post
     */
    private function extractRulesFromPost(array $post): array
    {
        $rules = [];

        foreach ((array)($post['rule_payment_number_from'] ?? []) as $index => $from) {
            $rules[] = [
                'paymentNumberFrom' => (int)$from,
                'paymentNumberTo' => (int)($post['rule_payment_number_to'][$index] ?? $from),
                'intervalValue' => (int)($post['rule_interval_value'][$index] ?? 1),
                'intervalUnit' => (string)($post['rule_interval_unit'][$index] ?? 'MONTH'),
            ];
        }

        return $rules;
    }

    /**
     * @param array $post
     * @param bool $isEdit
     */
    private function validateForm(array $post, bool $isEdit = false): array
    {
        $errors = [];

        if (!$isEdit && empty($post['code'])) {
            $errors[] = $this->language->get('error_code_required');
        }

        if (empty($post['name'])) {
            $errors[] = $this->language->get('error_name_required');
        }

        if (empty($post['category_indicator'])) {
            $errors[] = $this->language->get('error_category_required');
        }

        if (empty($post['rule_payment_number_from'])) {
            $errors[] = $this->language->get('error_rules_required');
        }

        return $errors;
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
            'Subscription plan template - uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString()
        );

        $this->session->data['warning'] = $this->language->get('error_something_went_wrong') . ' ' . $e->getMessage();
    }
}
