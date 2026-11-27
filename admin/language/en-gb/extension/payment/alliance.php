<?php
$_["heading_title"] = "Alliance Payment";
$_['text_alliance'] = '<a target="_BLANK" href="https://alb.ua"><img src="view/image/payment/Alliance_logo.svg" alt="Alliance Bank" title="Alliance Bank" style="border: 1px solid #EEEEEE;" /></a>';
// Text
$_["text_payment"] = "Payment";
$_["text_success"] = "Success: You have modified Alliance payment module!";
$_["text_edit"] = "Edit Alliance Settings";
$_["text_changes"] = "There are unsaved changes.";
$_["text_general"] = "General";
$_["text_statuses"] = "Order Statuses";
$_["text_advanced"] = "Advanced";
$_["text_all_geo_zones"] = "All Geo Zones";
$_["text_yes"] = "Yes";
$_["text_no"] = "No";
$_["text_are_you_sure"] = "Are you sure?";

// Tab
$_["tab_settings"] = "Settings";
$_["tab_log"] = "Log";
$_["tab_support"] = "Support";

// Button
$_["button_disconnect"] = "Disconnect";
$_["button_send"] = "Send";
$_["button_continue"] = "Continue";

//Alliance
$_["merchant_request_id_key"] = "merchantRequestId:";
$_["merchant_id_key"] = "merchantId:";
$_["fail_url_key"] = "fail_url:";
$_["success_url_key"] = "success_url:";

// Entry
$_["entry_ipn_url"] = "IPN Url";
$_["entry_api_key"] = "API Code:";
$_["entry_api_key_1"] = "merchantRequestId test";
$_["alliance_merchant_request_id_api_key"] = "merchantRequestId";
$_["merchant_id_key"] = "merchantId:";
$_["notification_url_key"] = "notification_url:";
$_["entry_ipn_key"] = "IPN Key:";
$_["entry_geo_zone"] = "Geo Zone";
$_["entry_status"] = "Status";
$_["entry_sort_order"] = "Sort Order";
$_["entry_paid_status"] = "Paid Status";
$_["entry_pending_status"] = "Pending Status";
$_["entry_return_url"] = "Return URL";
$_["entry_debug"] = "Debug Logging";

// Help
//$_["help_api_key"] = "Get your API Code from your PTPShopy account";
//$_["help_ipn_key"] = "Get your IPN Key from your PTPShopy account";
//$_["help_paid_status"] = "A fully paid invoice";
//$_["help_pending_status"] =
//    "Buyer has checked out and we are awaiting funds from the buyer";
//$_["help_ipn_url"] =
//    'Copy this url to "IPN Url" field on <a target="_blank" href="https://merchant.ptpshopy.com/?from=opencart-3">merchant.ptpshopy.com</a>';
//$_["help_return_url"] =
//    "PTPShopy will provide a redirect link to the user for this URL";
//$_["help_debug"] =
//    "Enabling debug will write sensitive data to a log file. You should always disable unless instructed otherwise";

// Success
$_["success_clear"] = "Success: Alliance log has been cleared";

// Warning
$_["warning_permission"] =
    "Warning: You do not have permission to modify Alliance payment module.";

// Error
$_["error_api_key"] = "You must specify your API code";
$_["error_ipn_key"] = "You must specify your IPN key";
$_["error_ipn_url"] = "`IPN URL` needs to be a valid URL";
$_["error_return_url"] = "`Return URL` needs to be a valid URL";

// Log
$_["log_error_install"] = "Alliance payment extension was not installed correctly or the files are corrupt. Please reinstall the extension. If this message persists after a reinstall, contact support with this message.";

// General Text
$_['text_alliance_settings'] = 'Alliance payment module settings';
$_['text_help_settings'] = 'Be sure to fill in all settings and check if everything is correct. Otherwise, the payment may not work correctly.';
$_['text_help_jwt'] = 'Enter your JWS signing credentials: the EC P-256 private key (PEM, PKCS#8) and the Key ID (kid) issued by JSC "BANK ALLIANCE". Every request to the bank is signed with these. If they are not filled or incorrect, the plugin will not work. If you have any questions, contact JSC "BANK ALLIANCE".';
$_['text_payment_info'] = 'Payment Information';
$_['text_message_type'] = 'Message Type:';
$_['text_refund_status'] = 'Refund Status:';
$_['text_refund_successful'] = 'refund successful';
$_['text_order_status'] = 'Order Status:';
$_['text_amount'] = 'Amount:';
$_['text_ecom_order_id'] = 'Ecom Order ID:';
$_['text_status_url'] = 'Status URL:';
$_['text_merchant_id'] = 'Merchant ID:';
$_['text_hpp_order_id'] = 'HPP Order ID:';
$_['text_hpp_pay_type'] = 'HPP Pay Type:';
$_['text_expired_order_date'] = 'Expired Order Date:';
$_['text_created_date'] = 'Created Date:';

// Entries
$_['entry_api_url'] = 'Alliance API URL';
$_['entry_jws_private_key'] = 'JWS Private Key';
$_['entry_jws_key_id'] = 'JWS Key ID';
$_['entry_hpp_type'] = 'Hpp Type';
$_['entry_hpp_payment_methods'] = 'Payment Methods';
$_['entry_fail_url'] = 'Page to redirect the user in case of payment error';
$_['entry_success_url'] = 'Page to redirect the user in case of successful payment';
$_['entry_status'] = 'Payment module status';
$_['entry_pending_status'] = '"Payment in progress" status';
$_['entry_error_status'] = '"Payment error" status';
$_['entry_paid_status'] = '"Paid" status';
$_['entry_refunded_status'] = '"Payment refunded" status';
$_['entry_refund_fail_status'] = '"Refund error" status';

// Helps
$_['help_jws_private_key'] = 'EC (P-256) private key in PEM format (PKCS#8) used to sign JWS requests (ES256). Keep secret.';
$_['help_jws_key_id'] = 'Key ID (kid) registered with JSC "BANK ALLIANCE", identifies your signing key.';
$_['help_merchant_id'] = 'Get merchantId from JSC "BANK ALLIANCE"';
$_['help_fail_url'] = 'This page is standard according to your store settings, if there is a page you want to redirect the buyer to, please specify it. You must write the full path. Example - https://example.com/fail';
$_['help_success_url'] = 'This page is standard according to your store settings, if there is a page you want to redirect the buyer to, please specify it. You must write the full path. Example - https://example.com/success';
$_['help_status'] = 'Select \'Enabled\' to activate the module';
$_['help_statuses'] = 'Statuses must be configured correctly, as they will be used during the payment process.';
$_['help_pending_status'] = 'Select the status that corresponds to this status in your store. Example status - "Pending"';
$_['help_error_status'] = 'Select the status that corresponds to this status in your store. Example status - "Failed"';
$_['help_paid_status'] = 'Select the status that corresponds to this status in your store. Example status - "Complete"';
$_['help_refunded_status'] = 'Select the status that corresponds to this status in your store. Example status - "Refunded"';
$_['help_refund_fail_status'] = 'Select the status that corresponds to this status in your store. Example status - "Failed"';
$_['help_hpp_type'] = 'Choose the HPP payment type from the two available PURCHASE or A2A.';
$_['help_hpp_payment_methods'] = 'Payment methods offered on the Hosted Payment Page, for both one-time and subscription checkout.';

$_['text_statuses'] = 'Order Statuses';
$_['text_refund_history_comment'] = 'Refund through Alliance Payment';
$_['text_refund_success_message'] = 'Refund successful';
$_['text_auto_return_comment'] = 'Automatic return via AlliancePay system';
$_['error_something_went_wrong'] = 'Something went wrong :(';

$_['text_partial_refund_title'] = 'Create refund for selected products';
$_['button_partial_refund'] = '<i class="fa fa-reply"></i> Partial refund';
$_['button_full_refund'] = '<i class="fa fa-undo"></i> Full refund';
$_['text_refund_complete_js'] = 'Refund complete!';
$_['text_refund_failed_js'] = 'Refund failed!';
$_['button_check_status'] = '<i class="fa fa-info"></i> Check order status';
$_['heading_title_order_data'] = 'Order data retrieval';
$_['text_technical_error'] = 'Technical error occurred';
$_['text_shipping_match'] = 'shipping'; // Слово для пошуку в базі

$_['text_select_for_return'] = 'Select for return';
$_['text_already_returned'] = 'Already returned';
$_['text_order_fully_refunded'] = 'The order amount is fully refunded.';
$_['text_refund_shipping_cost'] = 'Refund shipping cost?';
$_['text_please_select_products'] = 'Please select products!';
$_['text_confirm_return_requests'] = 'Create return requests for selected products?';

$_['text_alliance_operation_purchase_not_found'] = 'Purchase operation not found.';

$_['entry_preauth_exp_date']  = 'Pre-Auth Expiration Period';
$_['help_preauth_exp_date']   = 'Set the pre-authorization hold period (min 2h, max 28 days). Only applies when PREAUTH type is selected.';
$_['entry_preauth_status']    = 'Status after preauthorization (PREAUTH)';
$_['help_preauth_status']     = 'Status assigned after PREAUTH order creation, awaiting admin confirmation.';
$_['entry_completion_status']  = 'Status after successful capture (PREAUTH)';
$_['help_completion_status']   = 'Order will be moved to this status after a successful PREAUTH capture via the "Confirm Payment" button.';
$_['error_completion_amount']  = 'Completion rejected: order amount differs by more than 20% from the original authorized amount.';
$_['button_confirm_payment']   = '<i class="fa fa-check"></i> Confirm Payment';
$_['text_preauth_success']  = 'Preauthorization success.';
$_['text_completion_success']  = 'Payment successfully captured.';
$_['error_not_preauth_order']  = 'This order is not a PREAUTH order.';
$_['error_a2a_uah_only']       = 'A2A payment type is supported only for UAH currency. Current store currency: %s. Please select PURCHASE or PREAUTH.';

// Fiscalization
$_['text_fiscalization'] = 'Fiscalization';
$_['text_fiscalization_help'] = 'Fiscalization of successful payments and refunds via Alliance Kasa. Requests must be signed with an EC P-256 key (ES256).';
$_['entry_fiscalization_enabled'] = 'Enable fiscalization';
$_['help_fiscalization_enabled'] = 'When disabled, no fiscalization requests are sent and order buttons are hidden.';
$_['entry_fiscalization_auto'] = 'Automatic fiscalization';
$_['help_fiscalization_auto'] = 'When enabled, a receipt is created automatically after a successful payment or refund. When disabled, only the manual "Fiscalize" button works (for both payments and refunds).';
$_['entry_fiscalization_tax_code'] = 'Tax group code (default)';
$_['help_fiscalization_tax_code'] = 'Integer, optional. Used when the product has no value of its own.';
$_['entry_fiscalization_classification_code'] = 'UKTZED code (default)';
$_['help_fiscalization_classification_code'] = 'Digits only (usually 10). Required when the tax group code is 2, 7 or 8.';
$_['entry_fiscalization_excise_labels'] = 'Excise stamps (default)';
$_['help_fiscalization_excise_labels'] = 'Comma-separated or one per line. Empty values are ignored.';
$_['entry_fiscalization_measurement_code'] = 'Measurement unit code (default)';
$_['help_fiscalization_measurement_code'] = 'Required. Default is 0001 (piece).';
$_['error_fiscalization_tax_code'] = 'Tax group code must be an integer.';
$_['error_fiscalization_classification_code'] = 'UKTZED code must contain digits only.';
$_['error_fiscalization_classification_required'] = 'UKTZED code is required when the tax group code is 2, 7 or 8.';
$_['error_fiscalization_measurement_code'] = 'Measurement unit code is required.';

// Fiscalization: order block / product tab / service messages
$_['fisc_block_title'] = 'Fiscalization';
$_['fisc_col_type'] = 'Operation';
$_['fisc_col_status'] = 'Status';
$_['fisc_col_number'] = 'Fiscal number';
$_['fisc_col_receipt'] = 'Receipt';
$_['fisc_col_date'] = 'Fiscalization date';
$_['fisc_col_error'] = 'Error';
$_['fisc_btn_fiscalize'] = 'Fiscalize';
$_['fisc_btn_refresh'] = 'Refresh status';
$_['fisc_btn_fiscalize_refund'] = 'Fiscalize refund';
$_['fisc_refund_label'] = 'Refund of';
$_['fisc_receipt_link'] = 'Open receipt';
$_['fisc_empty'] = 'Not fiscalized yet.';
$_['fisc_tab_title'] = 'Fiscalization';
$_['fisc_product_tax_code'] = 'Tax group code';
$_['fisc_product_classification_code'] = 'UKTZED code';
$_['fisc_product_excise_labels'] = 'Excise stamps';
$_['fisc_product_measurement_code'] = 'Measurement unit code';
$_['fisc_product_help'] = 'Empty fields use the defaults from the AlliancePay module settings.';
$_['fisc_type_PURCHASE'] = 'Payment';
$_['fisc_type_REFUND'] = 'Refund';
$_['fisc_status_NEW'] = 'Waiting to be sent';
$_['fisc_status_PENDING'] = 'Pending';
$_['fisc_status_SUCCESS'] = 'Success';
$_['fisc_status_FAIL'] = 'Failed';
$_['fisc_status_ERROR'] = 'Sending error';
$_['fisc_error_permission'] = 'You do not have permission to perform this action.';
$_['fisc_error_generic'] = 'Action failed. See alliance.log for details.';
$_['fiscalization_msg_disabled'] = 'Fiscalization is disabled in the module settings.';
$_['fiscalization_msg_no_operation'] = 'No successful payment operation found for this order.';
$_['fiscalization_msg_already'] = 'Fiscalization for this operation is already in progress or completed (status %s).';
$_['fiscalization_msg_no_products'] = 'The order has no items to fiscalize.';
$_['fiscalization_msg_amount_mismatch'] = 'Items total (%s) does not match the operation amount (%s).';
$_['fiscalization_msg_missing_measurement'] = 'Measurement unit code is not set for item "%s".';
$_['fiscalization_msg_tax_class_required'] = 'Item "%s" with tax code %s requires a UKTZED code (classificationCode).';
$_['fiscalization_msg_no_merchant'] = 'Merchant ID is not configured in the module settings.';
$_['fiscalization_msg_no_notification_url'] = 'Could not build notificationUrl.';
$_['fiscalization_msg_technical'] = 'Fiscalization request failed. See alliance.log for details.';
$_['fiscalization_msg_err_missing_required_field'] = 'Missing required field: %s';
$_['fiscalization_msg_err_invalid_format'] = 'Invalid field format: %s';
$_['fiscalization_msg_err_payment_not_found'] = 'Operation not found in Alliance Ecom.';
$_['fiscalization_msg_err_original_receipt_not_found'] = 'Original receipt not found for this refund. Fiscalize the purchase first.';
$_['fiscalization_msg_err_unknown'] = 'Fiscalization error: %s %s';
$_['fiscalization_msg_sent'] = 'Fiscalization request sent (status: %s).';
$_['fiscalization_msg_note_success'] = 'Receipt fiscalized, fiscal number %s';
$_['fiscalization_msg_note_fail'] = 'Fiscalization error: %s %s';
