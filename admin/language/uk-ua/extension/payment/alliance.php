<?php
/**
 * Copyright © 2026 Alliance Dgtl. https://alb.ua/uk
 */

// General Text
$_['text_alliance_settings'] = 'Налаштування модуля оплати alliance';
$_['text_help_settings'] = 'Обов\'язково пропишіть всі налаштування і провірте чи все правильно прописано та налаштовано. В іншому випадку, оплата може працювати не коректно.';
$_['text_help_jwt'] = 'Пропишіть дані для підпису JWS: приватний EC-ключ P-256 (PEM, PKCS#8) та Key ID (kid), видані АТ "БАНК АЛЬЯНС". Ними підписується кожен запит до банку. Якщо ключі не будуть прописані або будуть не вірні, тоді плагін не буде працювати. В разі винекнення питань, зверніться до АТ "БАНК АЛЬЯНС".';
$_['text_payment_info'] = 'Інформація про платіж';
$_['text_message_type'] = 'Тип повідомлення:';
$_['text_refund_status'] = 'Статус повернення:';
$_['text_refund_successful'] = 'повернення коштів здійснено';
$_['text_order_status'] = 'Статус замовлення:';
$_['text_amount'] = 'Сума:';
$_['text_ecom_order_id'] = 'ID замовлення Ecom:';
$_['text_status_url'] = 'URL статусу:';
$_['text_merchant_id'] = 'ID мерчанта:';
$_['text_hpp_order_id'] = 'ID замовлення HPP:';
$_['text_hpp_pay_type'] = 'Тип оплати HPP:';
$_['text_expired_order_date'] = 'Термін дії замовлення:';
$_['text_created_date'] = 'Дата створення:';

// Entries
$_['entry_api_url'] = 'URL-адреса API Alliance';
$_['entry_jws_private_key'] = 'JWS Private Key';
$_['entry_jws_key_id'] = 'JWS Key ID';
$_['entry_hpp_type'] = 'Тип HPP сторінки';
$_['entry_hpp_payment_methods'] = 'Способи оплати';
$_['entry_fail_url'] = 'Сторінка на яку перенаправиться користувач в разі помилки оплати';
$_['entry_success_url'] = 'Сторінка на яку перенаправиться користувач в разі успішної оплати';
$_['entry_status'] = 'Статус модуля оплати';
$_['entry_pending_status'] = 'Статус "в процесі оплати"';
$_['entry_error_status'] = 'Статус "помилка оплати"';
$_['entry_paid_status'] = 'Статус "оплачено"';
$_['entry_refunded_status'] = 'Статус "оплату повернено"';
$_['entry_refund_fail_status'] = 'Статус "помилка повернення оплати"';

// Helps
$_['help_jws_private_key'] = 'Приватний EC-ключ (P-256) у форматі PEM (PKCS#8) для підпису JWS-запитів (ES256). Зберігайте в таємниці.';
$_['help_jws_key_id'] = 'Key ID (kid), зареєстрований в АТ "БАНК АЛЬЯНС", ідентифікує ваш ключ підпису.';
$_['help_merchant_id'] = 'Отримайте merchantId в АТ "БАНК АЛЬЯНС"';
$_['help_fail_url'] = 'Ця сторінка стандартна за налаштуваннями вашого магазину, якщо є сторінка на яку ви хочете перенаправляти покупця будь ласка вкажіть її. Обов\'язково потрібно прописати повний шлях. Приклад - https://example.com/fail';
$_['help_success_url'] = 'Ця сторінка стандартна за налаштуваннями вашого магазину, якщо є сторінка на яку ви хочете перенаправляти покупця будь ласка вкажіть її. Обов\'язково потрібно прописати повний шлях. Приклад - https://example.com/success';
$_['help_status'] = 'Виберіть \'Enabled\', щоб активувати модуль';
$_['help_statuses'] = 'Статуси обов\'язково потрібно налаштувати і правильно. Так як вони будуть використувавати в процесі оплати.';
$_['help_pending_status'] = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Pending"';
$_['help_error_status'] = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Failed"';
$_['help_paid_status'] = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Complete"';
$_['help_refunded_status'] = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Refunded"';
$_['help_refund_fail_status'] = 'Виберіть тут статус, який відповідає даному статусу в вашому магазині. Наприклад статус - "Failed"';
$_['help_hpp_type'] = 'Виберіть тип HPP платежу з двох доступних PURCHASE або A2A.';
$_['help_hpp_payment_methods'] = 'Способи оплати, доступні на сторінці оформлення (Hosted Payment Page), як для одноразових платежів, так і для підписок.';

$_['text_statuses'] = 'Статуси замовлень';
$_['text_refund_history_comment'] = 'Повернення коштів через Alliance Payment';
$_['text_refund_success_message'] = 'Повернення коштів здійснено';
$_['text_auto_return_comment'] = 'Автоматичне повернення через систему AlliancePay';
$_['error_something_went_wrong'] = 'Щось пішло не так :(';

$_['text_partial_refund_title'] = 'Створити повернення для обраних товарів';
$_['button_partial_refund'] = '<i class="fa fa-reply"></i> Часткове повернення';
$_['button_full_refund'] = '<i class="fa fa-undo"></i> Повне повернення коштів';
$_['text_refund_complete_js'] = 'Повернення завершено!';
$_['text_refund_failed_js'] = 'Помилка повернення!';
$_['button_check_status'] = '<i class="fa fa-info"></i> Перевірити статус замовлення';
$_['heading_title_order_data'] = 'Отримання даних по замовленню';
$_['text_technical_error'] = 'Сталась технічна помилка';
$_['text_shipping_match'] = 'доставка'; // Слово для пошуку в базі

$_['text_select_for_return'] = 'Обрати для повернення';
$_['text_already_returned'] = 'Вже повернуто';
$_['text_order_fully_refunded'] = 'Сума замовлення повністю повернута.';
$_['text_refund_shipping_cost'] = 'Повернути вартість доставки?';
$_['text_please_select_products'] = 'Будь ласка, оберіть товари!';
$_['text_confirm_return_requests'] = 'Створити запити на повернення для обраних товарів?';

$_['text_alliance_operation_purchase_not_found'] = 'Не знайдено операції купівлі.';

$_['entry_preauth_exp_date']  = 'Термін дії преавторизації';
$_['help_preauth_exp_date']   = 'Встановіть термін утримання преавторизації (мін. 2 год, макс. 28 днів). Застосовується лише якщо вибрано тип PREAUTH.';
$_['entry_preauth_status']    = 'Статус "Очікує підтвердження оплати" (PREAUTH)';
$_['help_preauth_status']     = 'Статус, який присвоюється після створення PREAUTH замовлення, до підтвердження адміністратором.';
$_['entry_completion_status']  = 'Статус після успішного підтвердження (PREAUTH)';
$_['help_completion_status']   = 'Замовлення буде переведено в цей статус після успішного підтвердження PREAUTH через кнопку "Підтвердити оплату".';
$_['error_completion_amount']  = 'Списання відхилено: сума замовлення відрізняється більш ніж на 20% від початково авторизованої суми.';
$_['button_confirm_payment']   = '<i class="fa fa-check"></i> Підтвердити оплату';
$_['text_preauth_success']  = 'Преавторизація успішна';
$_['text_completion_success']  = 'Оплату успішно підтверджено.';
$_['error_not_preauth_order']  = 'Це замовлення не є PREAUTH замовленням.';
$_['error_a2a_uah_only']       = 'Тип оплати A2A підтримується тільки для гривні (UAH). Поточна валюта магазину: %s. Будь ласка, оберіть PURCHASE або PREAUTH.';

// Fiscalization
$_['text_fiscalization'] = 'Фіскалізація';
$_['text_fiscalization_help'] = 'Фіскалізація успішних платежів та повернень через Alliance Kasa. Для підпису запитів потрібен EC-ключ P-256 (ES256).';
$_['entry_fiscalization_enabled'] = 'Увімкнути фіскалізацію';
$_['help_fiscalization_enabled'] = 'Якщо вимкнено — запити фіскалізації не відправляються, кнопки в замовленні не відображаються.';
$_['entry_fiscalization_auto'] = 'Автоматична фіскалізація';
$_['help_fiscalization_auto'] = 'Якщо увімкнено — чек створюється автоматично після успішного платежу або повернення. Якщо вимкнено — лише вручну кнопкою «Фіскалізувати» (і для платежів, і для повернень).';
$_['entry_fiscalization_tax_code'] = 'Код податкової групи (за замовчуванням)';
$_['help_fiscalization_tax_code'] = 'Ціле число, необов\'язкове. Використовується, якщо у товарі не заповнено власне значення.';
$_['entry_fiscalization_classification_code'] = 'УКТЗЕД (за замовчуванням)';
$_['help_fiscalization_classification_code'] = 'Лише цифри (зазвичай 10). Обов\'язкове, якщо код податкової групи 2, 7 або 8.';
$_['entry_fiscalization_excise_labels'] = 'Акцизні марки (за замовчуванням)';
$_['help_fiscalization_excise_labels'] = 'Через кому або по одній в рядку. Порожні значення ігноруються.';
$_['entry_fiscalization_measurement_code'] = 'Код одиниці виміру (за замовчуванням)';
$_['help_fiscalization_measurement_code'] = 'Обов\'язкове. За замовчуванням 0001 (штука).';
$_['error_fiscalization_tax_code'] = 'Код податкової групи має бути цілим числом.';
$_['error_fiscalization_classification_code'] = 'УКТЗЕД має містити лише цифри.';
$_['error_fiscalization_classification_required'] = 'УКТЗЕД обов\'язковий, якщо код податкової групи 2, 7 або 8.';
$_['error_fiscalization_measurement_code'] = 'Код одиниці виміру обов\'язковий.';

// Fiscalization: order block / product tab / service messages
$_['fisc_block_title'] = 'Фіскалізація';
$_['fisc_col_type'] = 'Операція';
$_['fisc_col_status'] = 'Статус';
$_['fisc_col_number'] = 'Фіскальний номер';
$_['fisc_col_receipt'] = 'Чек';
$_['fisc_col_date'] = 'Дата фіскалізації';
$_['fisc_col_error'] = 'Помилка';
$_['fisc_btn_fiscalize'] = 'Фіскалізувати';
$_['fisc_btn_refresh'] = 'Оновити статус';
$_['fisc_btn_fiscalize_refund'] = 'Фіскалізувати повернення';
$_['fisc_refund_label'] = 'Повернення на суму';
$_['fisc_receipt_link'] = 'Відкрити чек';
$_['fisc_empty'] = 'Фіскалізацію ще не виконано.';
$_['fisc_tab_title'] = 'Фіскалізація';
$_['fisc_product_tax_code'] = 'Код податкової групи';
$_['fisc_product_classification_code'] = 'УКТЗЕД';
$_['fisc_product_excise_labels'] = 'Акцизні марки';
$_['fisc_product_measurement_code'] = 'Код одиниці виміру';
$_['fisc_product_help'] = 'Порожні поля беруть значення за замовчуванням з налаштувань модуля AlliancePay.';
$_['fisc_type_PURCHASE'] = 'Оплата';
$_['fisc_type_REFUND'] = 'Повернення';
$_['fisc_status_NEW'] = 'Очікує відправки';
$_['fisc_status_PENDING'] = 'В обробці';
$_['fisc_status_SUCCESS'] = 'Успішно';
$_['fisc_status_FAIL'] = 'Помилка';
$_['fisc_status_ERROR'] = 'Помилка відправки';
$_['fisc_error_permission'] = 'У вас немає прав для виконання цієї дії.';
$_['fisc_error_generic'] = 'Не вдалося виконати дію. Деталі в alliance.log.';
$_['fiscalization_msg_disabled'] = 'Фіскалізація вимкнена в налаштуваннях модуля.';
$_['fiscalization_msg_no_operation'] = 'Не знайдено успішної платіжної операції для замовлення.';
$_['fiscalization_msg_already'] = 'Фіскалізація для цієї операції вже виконується або завершена (статус %s).';
$_['fiscalization_msg_no_products'] = 'У замовленні немає позицій для фіскалізації.';
$_['fiscalization_msg_amount_mismatch'] = 'Сума позицій (%s) не збігається із сумою операції (%s).';
$_['fiscalization_msg_missing_measurement'] = 'Для позиції "%s" не задано код одиниці виміру.';
$_['fiscalization_msg_tax_class_required'] = 'Для позиції "%s" з кодом податку %s обов\'язковий УКТЗЕД (classificationCode).';
$_['fiscalization_msg_no_merchant'] = 'Не вказано Merchant ID в налаштуваннях модуля.';
$_['fiscalization_msg_no_notification_url'] = 'Не вдалося сформувати notificationUrl.';
$_['fiscalization_msg_technical'] = 'Помилка відправки запиту фіскалізації. Деталі в alliance.log.';
$_['fiscalization_msg_err_missing_required_field'] = 'Відсутнє обов\'язкове поле: %s';
$_['fiscalization_msg_err_invalid_format'] = 'Некоректний формат поля: %s';
$_['fiscalization_msg_err_payment_not_found'] = 'Операцію не знайдено в Alliance Ecom.';
$_['fiscalization_msg_err_original_receipt_not_found'] = 'Для повернення не знайдено оригінальний чек. Спочатку виконайте фіскалізацію покупки.';
$_['fiscalization_msg_err_unknown'] = 'Помилка фіскалізації: %s %s';
$_['fiscalization_msg_sent'] = 'Запит фіскалізації відправлено (статус: %s).';
$_['fiscalization_msg_note_success'] = 'Чек фіскалізовано, ФН %s';
$_['fiscalization_msg_note_fail'] = 'Помилка фіскалізації: %s %s';
