<?php
/**
 * config.php - shared settings + helper functions for checkout / SSLCommerz.
 *
 * HOW TO GET SANDBOX (TEST) CREDENTIALS
 *   1. Register at https://developer.sslcommerz.com/registration/
 *   2. They email you a Store ID and Store Password for the sandbox.
 *   3. Paste them below. Keep SSLC_SANDBOX = true while testing.
 *   4. For real payments: use your LIVE Store ID/Password and set SSLC_SANDBOX = false.
 *
 * Never upload your real store password to a public GitHub repo!
 */

define('SSLC_SANDBOX',        true);
define('SSLC_STORE_ID',       'your_store_id_here');
define('SSLC_STORE_PASSWORD', 'your_store_password_here');

// Used to build the success/fail/cancel/IPN links SSLCommerz calls back.
// Leave empty to auto-detect (fine for localhost). On a real server set it, e.g.
// 'https://yourdomain.com/Organic_Food_Service'
define('BASE_URL', '');

// On XAMPP/Windows, cURL often fails with "SSL certificate problem" (error 60) because
// no CA bundle is installed. For SANDBOX testing only we skip verification; it is ON for live.
define('SSLC_VERIFY_SSL', !SSLC_SANDBOX);

// Keep these in sync with cart.js
define('SHIPPING_FEE', 60);
define('TAX_RATE', 0.05);

// Server-side price list. The browser's prices are NOT trusted (anyone can edit
// localStorage and pay 1 Tk for everything). Names must match shop.html / product.html.
$PRODUCT_CATALOG = [
    'Organic Apples'        => 300,
    'Organic Broccoli'      => 99,
    'Organic Ghee'          => 599,
    'Organic Chia Seeds'    => 455,
    'Musterd oil'           => 320,
    'Organic Peanut Butter' => 499,
    'Fresh Chana'           => 750,
    'Organic Coffee'        => 429,
    'Organic Pink Salt'     => 890,
    'Turmeric Powder'       => 65,
    'Organic Mixed Nuts'    => 499,
    'Organic Dates'         => 1200,
    'Fresh Strawberry'      => 450,
    'Fresh Carrot'          => 70,
    'Fresh Tomato'          => 110,
    // product.html
    'Organic Orange'        => 299,
    'Organic Honey'         => 789,
    'Organic Cucumber'      => 70,
];

/* ------------------------------------------------------------------ */
/* URLs                                                                */
/* ------------------------------------------------------------------ */
function app_url($file) {
    if (BASE_URL !== '') {
        return rtrim(BASE_URL, '/') . '/' . $file;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'])), '/');
    return ($https ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $dir . '/' . $file;
}

function sslc_host() {
    return SSLC_SANDBOX ? 'https://sandbox.sslcommerz.com' : 'https://securepay.sslcommerz.com';
}

/* ------------------------------------------------------------------ */
/* Order storage (simple JSON files; swap for MySQL when you are ready) */
/* ------------------------------------------------------------------ */
function orders_dir() {
    $dir = __DIR__ . '/orders';
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
        // block direct download of order files from the browser
        @file_put_contents($dir . '/.htaccess',
            "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" .
            "<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n");
    }
    return $dir;
}

function order_path($order_id) {
    return orders_dir() . '/' . preg_replace('/[^A-Za-z0-9_-]/', '', $order_id) . '.json';
}

function save_order(array $order) {
    $order['updated_at'] = date('c');
    return file_put_contents(order_path($order['order_id']),
        json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
}

function load_order($order_id) {
    $path = order_path($order_id);
    return is_file($path) ? json_decode(file_get_contents($path), true) : null;
}

/* ------------------------------------------------------------------ */
/* SSLCommerz                                                          */
/* ------------------------------------------------------------------ */
function sslc_http($url, array $post = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => SSLC_VERIFY_SSL,
        CURLOPT_SSL_VERIFYHOST => SSLC_VERIFY_SSL ? 2 : 0,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }
    $body  = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return [$body, $error];
}

/** Ask SSLCommerz to start a payment session. Returns [GatewayPageURL|null, errorMessage|null] */
function sslc_init_session(array $order) {
    $post = [
        'store_id'         => SSLC_STORE_ID,
        'store_passwd'     => SSLC_STORE_PASSWORD,
        'total_amount'     => number_format($order['total'], 2, '.', ''),
        'currency'         => 'BDT',
        'tran_id'          => $order['order_id'],
        'success_url'      => app_url('ssl_success.php'),
        'fail_url'         => app_url('ssl_fail.php'),
        'cancel_url'       => app_url('ssl_cancel.php'),
        'ipn_url'          => app_url('ssl_ipn.php'),
        'emi_option'       => 0,
        'cus_name'         => $order['customer']['name'],
        'cus_email'        => $order['customer']['email'],
        'cus_add1'         => $order['customer']['address'],
        'cus_city'         => 'Dhaka',   // we do not collect a city; SSLCommerz requires one
        'cus_country'      => 'Bangladesh',
        'cus_phone'        => $order['customer']['phone'],
        'shipping_method'  => 'NO',
        'num_of_item'      => count($order['items']),
        'product_name'     => 'Organic Food Order',
        'product_category' => 'Food',
        'product_profile'  => 'general',
    ];

    [$body, $curlError] = sslc_http(sslc_host() . '/gwprocess/v4/api.php', $post);
    if ($body === false || $body === null) {
        return [null, 'Could not connect to the payment gateway: ' . $curlError];
    }
    $data = json_decode($body, true);
    if (!is_array($data) || ($data['status'] ?? '') !== 'SUCCESS' || empty($data['GatewayPageURL'])) {
        return [null, $data['failedreason'] ?? 'The payment gateway rejected the request. Check your Store ID / Password.'];
    }
    return [$data['GatewayPageURL'], null];
}

/**
 * Called from ssl_success.php (browser) and ssl_ipn.php (SSLCommerz server).
 * Never trust the POST by itself: ask SSLCommerz's validation API and compare
 * the order id, currency and amount with OUR saved order.
 * Returns [bool paid, array|null order].
 */
function sslc_confirm_payment(array $post) {
    $tran_id = $post['tran_id'] ?? '';
    $val_id  = $post['val_id'] ?? '';
    $order   = $tran_id !== '' ? load_order($tran_id) : null;

    if (!$order || $val_id === '') {
        return [false, $order];
    }
    if (($order['payment_status'] ?? '') === 'paid') {   // IPN + redirect both arrive: be idempotent
        return [true, $order];
    }

    $url = sslc_host() . '/validator/api/validationserverAPI.php?' . http_build_query([
        'val_id'       => $val_id,
        'store_id'     => SSLC_STORE_ID,
        'store_passwd' => SSLC_STORE_PASSWORD,
        'format'       => 'json',
    ]);
    [$body] = sslc_http($url);
    $v = $body ? json_decode($body, true) : null;

    $statusOk   = is_array($v) && in_array($v['status'] ?? '', ['VALID', 'VALIDATED'], true);
    $tranOk     = $statusOk && ($v['tran_id'] ?? '') === $order['order_id'];
    $currencyOk = $statusOk && ($v['currency'] ?? 'BDT') === 'BDT';
    $paidAmount = $statusOk ? (float)($v['currency_amount'] ?? $v['amount'] ?? 0) : 0;
    $amountOk   = abs($paidAmount - (float)$order['total']) < 0.01;

    if ($statusOk && $tranOk && $currencyOk && $amountOk) {
        $order['payment_status'] = 'paid';
        $order['status']         = 'confirmed';
        $order['val_id']         = $val_id;
        $order['bank_tran_id']   = $v['bank_tran_id'] ?? '';
        $order['card_type']      = $v['card_type'] ?? '';
        save_order($order);
        return [true, $order];
    }

    $order['payment_status'] = 'validation_failed';
    save_order($order);
    return [false, $order];
}

/** Mark an unpaid order as failed/cancelled (never touches an order that is already paid). */
function sslc_mark_unpaid(array $post, $status) {
    $order = load_order($post['tran_id'] ?? '');
    if ($order && ($order['payment_status'] ?? '') !== 'paid') {
        $order['payment_status'] = $status;
        save_order($order);
    }
}
