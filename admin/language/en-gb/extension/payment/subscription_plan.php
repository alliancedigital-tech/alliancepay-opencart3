<?php
$_['heading_title'] = 'Subscription Plans';

$_['text_home'] = 'Dashboard';
$_['text_success'] = 'Success: You have modified subscription plans!';
$_['text_filter'] = 'Filter';
$_['text_product_id'] = 'Product ID';
$_['text_all_statuses'] = 'All statuses';
$_['text_all_sync_statuses'] = 'All sync statuses';
$_['text_no_results'] = 'No plans found.';
$_['text_archive_candidate'] = 'Archive candidate';
$_['text_yes'] = 'Yes';
$_['text_no'] = 'No';
$_['text_rules'] = 'Rules';
$_['text_version_id'] = 'Version ID';
$_['text_amount'] = 'Amount';
$_['text_interval_unit'] = 'Interval Unit';
$_['text_no_template'] = '-- No template --';
$_['text_product_search_placeholder'] = 'Start typing a product name...';
$_['text_matching_plan_exists'] = 'A matching plan already exists for this product/template/rules combination - showing the existing plan.';
$_['text_sync_error_notice'] = 'This plan could not be fully synchronized with the provider:';
$_['text_confirm_deactivate'] = 'Deactivate this plan for new customers? Existing subscribers are not affected.';
$_['text_confirm_archive'] = 'Archive this plan at the provider? This cannot be undone.';

$_['column_product'] = 'Product';
$_['column_provider_plan_id'] = 'Provider Plan ID';
$_['column_status'] = 'Status';
$_['column_sync_status'] = 'Sync Status';
$_['column_new_customers'] = 'Active for New Customers';
$_['column_active_subscriptions'] = 'Active Subscriptions';
$_['column_action'] = 'Action';

$_['entry_product'] = 'Product';
$_['entry_template'] = 'Plan Template';
$_['entry_name'] = 'Name';
$_['entry_description'] = 'Description';
$_['help_description'] = 'Optional - if left blank, the plan name is sent as the description (the bank requires a non-blank value).';
$_['entry_category'] = 'Category Indicator';
$_['entry_applies_from'] = 'Applies From';
$_['entry_applies_to'] = 'Applies To';
$_['entry_payment_from'] = 'Payment #From';
$_['entry_payment_to'] = 'Payment #To';
$_['entry_interval_value'] = 'Interval Value';

$_['help_template'] = 'Selecting a template pre-fills the rule structure (stage counts/intervals) - you still enter the amount and currency per stage.';
$_['help_rules'] = 'One row per billing stage. A trial is a rule with amount 0 over the first payment number(s).';

$_['button_add'] = 'Add New';
$_['button_edit'] = 'Edit';
$_['button_back'] = 'Back';
$_['button_filter'] = 'Filter';
$_['button_add_rule'] = 'Add Rule';
$_['button_deactivate'] = 'Deactivate for New Customers';
$_['button_reactivate'] = 'Reactivate for New Customers';
$_['button_reprice'] = 'Mass Reprice';
$_['button_archive'] = 'Archive at Provider';

$_['error_permission'] = 'Warning: You do not have permission to modify subscription plans.';
$_['error_not_found'] = 'Plan not found.';
$_['error_form_required'] = 'Please select a product, category indicator and at least one rule.';
$_['error_plan_create_failed'] = 'Failed to create the plan at the provider:';
$_['error_infinite_schedule_failed'] = 'Plan was created at the provider (id: %s) but the mandatory infinite-schedule step failed: %s. This requires manual follow-up - do not retry blindly, contact support.';
$_['error_not_archive_candidate'] = 'This plan is not eligible for archiving (must be deactivated for new customers and have zero active subscriptions).';
$_['error_archive_confirmation_required'] = 'You must confirm the archive action.';
$_['error_archive_failed'] = 'Failed to archive the plan at the provider:';
$_['error_something_went_wrong'] = 'Something went wrong while saving the plan. The error has been logged to alliance.log.';
