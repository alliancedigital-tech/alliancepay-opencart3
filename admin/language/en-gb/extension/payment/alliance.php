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
$_["alliance_service_code_id_api_key"] = "serviceCode:";
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
$_['text_alliance_settings']    = 'Alliance payment module settings';
$_['text_help_settings']        = 'Be sure to fill in all settings and check if everything is correct. Otherwise, the payment may not work correctly.';
$_['text_help_jwt']             = 'Enter jwt keys. You need to select parameters "x", "y", "d" from the generated key file to decrypt the responses. If the keys are not filled or incorrect, the plugin will not work. If you have any questions, contact JSC "BANK ALLIANCE".';
$_['text_payment_info']         = 'Payment Information';
$_['text_message_type']         = 'Message Type:';
$_['text_refund_status']        = 'Refund Status:';
$_['text_refund_successful']    = 'refund successful';
$_['text_order_status']         = 'Order Status:';
$_['text_amount']               = 'Amount:';
$_['text_ecom_order_id']        = 'Ecom Order ID:';
$_['text_status_url']           = 'Status URL:';
$_['text_merchant_id']          = 'Merchant ID:';
$_['text_hpp_order_id']         = 'HPP Order ID:';
$_['text_hpp_pay_type']         = 'HPP Pay Type:';
$_['text_expired_order_date']   = 'Expired Order Date:';
$_['text_created_date']         = 'Created Date:';

// Entries
$_['entry_api_url']             = 'Alliance API URL';
$_['entry_param_x']             = 'Parameter "x"';
$_['entry_param_y']             = 'Parameter "y"';
$_['entry_param_d']             = 'Parameter "d"';
$_['entry_fail_url']            = 'Page to redirect the user in case of payment error';
$_['entry_success_url']         = 'Page to redirect the user in case of successful payment';
$_['entry_status']              = 'Payment module status';
$_['entry_pending_status']      = '"Payment in progress" status';
$_['entry_error_status']        = '"Payment error" status';
$_['entry_paid_status']         = '"Paid" status';
$_['entry_refunded_status']     = '"Payment refunded" status';
$_['entry_refund_fail_status']  = '"Refund error" status';

// Helps
$_['help_service_code']         = 'Get serviceCode from JSC "BANK ALLIANCE"';
$_['help_merchant_id']          = 'Get merchantId from JSC "BANK ALLIANCE"';
$_['help_fail_url']             = 'This page is standard according to your store settings, if there is a page you want to redirect the buyer to, please specify it. You must write the full path. Example - https://example.com/fail';
$_['help_success_url']          = 'This page is standard according to your store settings, if there is a page you want to redirect the buyer to, please specify it. You must write the full path. Example - https://example.com/success';
$_['help_status']               = 'Select \'Enabled\' to activate the module';
$_['help_statuses']             = 'Statuses must be configured correctly, as they will be used during the payment process.';
$_['help_pending_status']       = 'Select the status that corresponds to this status in your store. Example status - "Pending"';
$_['help_error_status']         = 'Select the status that corresponds to this status in your store. Example status - "Failed"';
$_['help_paid_status']          = 'Select the status that corresponds to this status in your store. Example status - "Complete"';
$_['help_refunded_status']      = 'Select the status that corresponds to this status in your store. Example status - "Refunded"';
$_['help_refund_fail_status']   = 'Select the status that corresponds to this status in your store. Example status - "Failed"';

$_['text_statuses']                 = 'Order Statuses';
$_['text_refund_history_comment']   = 'Refund through Alliance Payment';
$_['text_refund_success_message']   = 'Refund successful';
$_['text_auto_return_comment']      = 'Automatic return via AlliancePay system';
$_['error_something_went_wrong']    = 'Something went wrong :(';

$_['text_partial_refund_title'] = 'Create refund for selected products';
$_['button_partial_refund']     = '<i class="fa fa-reply"></i> Partial refund';
$_['button_full_refund']        = '<i class="fa fa-undo"></i> Full refund';
$_['text_refund_complete_js']   = 'Refund complete!';
$_['text_refund_failed_js']     = 'Refund failed!';
$_['button_check_status']       = '<i class="fa fa-info"></i> Check order status';
$_['heading_title_order_data']  = 'Order data retrieval';
$_['text_technical_error']      = 'Technical error occurred';
$_['text_shipping_match']       = 'shipping'; // Слово для пошуку в базі

$_['text_select_for_return']         = 'Select for return';
$_['text_already_returned']          = 'Already returned';
$_['text_order_fully_refunded']      = 'The order amount is fully refunded.';
$_['text_refund_shipping_cost']      = 'Refund shipping cost?';
$_['text_please_select_products']    = 'Please select products!';
$_['text_confirm_return_requests']   = 'Create return requests for selected products?';

