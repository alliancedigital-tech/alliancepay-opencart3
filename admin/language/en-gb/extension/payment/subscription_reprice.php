<?php
$_['heading_title'] = 'Mass Repricing';

$_['text_home'] = 'Dashboard';
$_['text_success'] = 'Success: The plan has been repriced for all active subscribers.';
$_['text_permission_denied'] = 'You do not have permission to perform mass repricing. This is a deliberately restricted action affecting the billing of every active subscriber on a plan at once - ask a store administrator to grant "modify" on this screen to your user group via Users > User Groups.';
$_['text_affected_count_label'] = 'Active subscriptions that will be affected';
$_['text_lead_time_notice'] = 'The new price can only take effect at least %s days from now, to give customers time to be notified.';
$_['text_current_rules'] = 'Current Rules';
$_['text_new_rules'] = 'New Rules';
$_['text_amount'] = 'Amount';
$_['text_interval_unit'] = 'Interval Unit';
$_['text_confirm_submit'] = 'Apply this price change to all active subscribers of the plan? This cannot be undone.';

$_['entry_payment_from'] = 'Payment #From';
$_['entry_payment_to'] = 'Payment #To';
$_['entry_interval_value'] = 'Interval Value';
$_['entry_applies_from'] = 'Applies From';
$_['entry_reason'] = 'Reason (internal note)';
$_['entry_notify_confirmed'] = 'I confirm affected customers have been or will be notified of this price change';

$_['button_back'] = 'Back';
$_['button_add_rule'] = 'Add Rule';

$_['error_plan_not_found'] = 'Plan not found.';
$_['error_lead_time'] = 'The new price must apply at least %s days from now.';
$_['error_notify_confirmation_required'] = 'You must confirm that affected customers have been or will be notified.';
$_['error_rules_required'] = 'Please provide at least one rule.';
$_['error_reprice_failed'] = 'Failed to update the plan at the provider:';
$_['error_infinite_schedule_failed'] = 'The plan was updated at the provider but the mandatory infinite-schedule step failed: %s. This requires manual follow-up - do not retry blindly, contact support.';
