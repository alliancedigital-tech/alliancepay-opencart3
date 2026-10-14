<?php

declare(strict_types=1);

class ControllerExtensionPaymentSubscriptionReprice extends Controller
{
    private const ROUTE = 'extension/payment/subscription_reprice';

    public function index()
    {
        $this->load->language(self::ROUTE);
        $this->document->setTitle($this->language->get('heading_title'));

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->renderPermissionDenied();
            return;
        }

        $this->load->model('extension/payment/subscription_plan');
        $this->load->model(self::ROUTE);

        $planId = (int)($this->request->get['plan_id'] ?? 0);
        $plan = $this->model_extension_payment_subscription_plan->getPlan($planId);

        if (empty($plan)) {
            $this->session->data['warning'] = $this->language->get('error_plan_not_found');
            $this->response->redirect($this->url->link('extension/payment/subscription_plan', 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $this->load->library('AllianceConfig');
        $this->load->library('AllianceSubscriptionConfig');

        $data = $this->baseViewData();
        $data['permission_denied'] = false;
        $data['plan'] = $plan;
        $data['rules'] = json_decode((string)$plan['rules'], true) ?: [];
        $data['active_subscription_count'] = $this->model_extension_payment_subscription_plan->getActiveSubscriptionCountForPlan($planId);
        $data['min_lead_days'] = AllianceSubscriptionConfig::MASS_REPRICE_MIN_LEAD_DAYS;
        $data['currencies'] = array_keys(AllianceConfig::CURRENCY_CODE_MAP);
        $data['action'] = $this->url->link(self::ROUTE . '/save', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true);

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
    }

    public function save()
    {
        $this->load->language(self::ROUTE);
        $this->load->model('extension/payment/subscription_plan');
        $this->load->model(self::ROUTE);
        $this->load->library('AllianceConfig');
        $this->load->library('AllianceEcom');
        $this->load->library('AllianceLogger');
        $this->load->library('AllianceSubscriptionConfig');

        $planId = (int)($this->request->post['plan_id'] ?? $this->request->get['plan_id'] ?? 0);

        if (!$this->user->hasPermission('modify', self::ROUTE)) {
            $this->renderPermissionDenied();
            return;
        }

        $plan = $this->model_extension_payment_subscription_plan->getPlan($planId);

        if (empty($plan)) {
            $this->session->data['warning'] = $this->language->get('error_plan_not_found');
            $this->response->redirect($this->url->link('extension/payment/subscription_plan', 'user_token=' . $this->getUserToken(), true));
            return;
        }

        $post = $this->request->post;
        $appliesFrom = (string)($post['applies_from'] ?? '');
        $reason = (string)($post['reason'] ?? '');
        $notifyConfirmed = !empty($post['notify_confirmed']);
        $newRules = $this->extractRulesFromPost($post);

        $minLeadSeconds = AllianceSubscriptionConfig::MASS_REPRICE_MIN_LEAD_DAYS * 86400;
        $appliesFromTimestamp = $appliesFrom !== '' ? strtotime($appliesFrom) : false;

        if ($appliesFromTimestamp === false || $appliesFromTimestamp < (time() + $minLeadSeconds)) {
            $this->session->data['warning'] = sprintf(
                $this->language->get('error_lead_time'),
                AllianceSubscriptionConfig::MASS_REPRICE_MIN_LEAD_DAYS
            );
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        if (!$notifyConfirmed) {
            $this->session->data['warning'] = $this->language->get('error_notify_confirmation_required');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        if (!$newRules) {
            $this->session->data['warning'] = $this->language->get('error_rules_required');
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        $affectedCount = $this->model_extension_payment_subscription_plan->getActiveSubscriptionCountForPlan($planId);
        $appliesFromNormalized = date('Y-m-d H:i:s', $appliesFromTimestamp);

        $oldVersionId = (string)$plan['version_id'];
        $oldRules = json_decode((string)$plan['rules'], true) ?: [];

        $ecom = new AllianceEcom($this->registry);

        try {

            $updateResponse = $ecom->updateSubscriptionPlan([
                'planId' => $plan['provider_plan_id'],
                'name' => $plan['name'],
                'description' => ($plan['description'] ?: $plan['name']),
                'rules' => $newRules,
                'appliesDateTimeFrom' => $appliesFromNormalized,
            ]);
        } catch (\Throwable $e) {
            (new AllianceLogger($this->registry))->log(AllianceLogger::ERROR, 'Plan reprice (update) failed: ' . $e->getMessage());
            $this->session->data['warning'] = $this->language->get('error_reprice_failed') . ' ' . $e->getMessage();
            $this->response->redirect($this->url->link(self::ROUTE, 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true));
            return;
        }

        $newVersionId = (string)($updateResponse['versionId'] ?? '');

        try {
            $ecom->setInfiniteSchedule($plan['provider_plan_id'], $newVersionId);
        } catch (\Throwable $e) {

            (new AllianceLogger($this->registry))->log(
                AllianceLogger::ERROR,
                'Infinite schedule failed after reprice for provider plan ' . $plan['provider_plan_id'] . ': ' . $e->getMessage()
            );

            $this->model_extension_payment_subscription_plan->markRepriceFailed($planId, $e->getMessage());
            $this->model_extension_payment_subscription_plan->insertAuditLog([
                'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_REPRICE_FAIL,
                'plan_id' => $planId,
                'user_id' => $this->user->getId(),
                'details' => ['new_version_id' => $newVersionId, 'error' => $e->getMessage()],
            ]);

            $this->session->data['warning'] = sprintf($this->language->get('error_infinite_schedule_failed'), $e->getMessage());
            $this->response->redirect(
                $this->url->link('extension/payment/subscription_plan/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true)
            );
            return;
        }

        $this->model_extension_payment_subscription_reprice->insertVersionHistory([
            'plan_id' => $planId,
            'old_version_id' => $oldVersionId,
            'new_version_id' => $newVersionId,
            'old_rules' => $oldRules,
            'new_rules' => $newRules,
            'applies_from' => $appliesFromNormalized,
            'reason' => $reason,
            'notify_confirmed' => 1,
            'affected_subscriptions_count' => $affectedCount,
            'created_by' => $this->user->getId(),
        ]);

        $this->model_extension_payment_subscription_plan->applyReprice($planId, [
            'version_id' => $newVersionId,
            'rules' => $newRules,
            'applies_from' => $appliesFromNormalized,
        ]);

        $this->model_extension_payment_subscription_plan->insertAuditLog([
            'action' => AllianceSubscriptionConfig::AUDIT_ACTION_PLAN_REPRICE,
            'plan_id' => $planId,
            'user_id' => $this->user->getId(),
            'details' => ['new_version_id' => $newVersionId, 'affected_subscriptions_count' => $affectedCount],
        ]);

        $this->session->data['success'] = $this->language->get('text_success');
        $this->response->redirect(
            $this->url->link('extension/payment/subscription_plan/edit', 'user_token=' . $this->getUserToken() . '&plan_id=' . $planId, true)
        );
    }

    private function renderPermissionDenied(): void
    {
        $data = $this->baseViewData();
        $data['permission_denied'] = true;

        $this->response->setOutput($this->load->view(self::ROUTE . '_form', $data));
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
                'coinAmount' => (int)($post['rule_coin_amount'][$index] ?? 0),
                'currency' => AllianceConfig::getCurrencyCode((string)($post['rule_currency'][$index] ?? 'UAH')),
            ];
        }

        return $rules;
    }

    private function baseViewData(): array
    {
        $data['heading_title'] = $this->language->get('heading_title');
        $data['user_token'] = $this->getUserToken();
        $data['back'] = $this->url->link('extension/payment/subscription_plan', 'user_token=' . $this->getUserToken(), true);

        $data['breadcrumbs'] = [
            [
                'text' => $this->language->get('text_home'),
                'href' => $this->url->link('common/dashboard', 'user_token=' . $this->getUserToken(), true),
            ],
            [
                'text' => $this->language->get('heading_title'),
                'href' => '',
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
}
