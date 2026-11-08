<?php
require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

function respond(array $data, $code = 200) {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['status' => 'error', 'message' => 'Invalid request method.'], 405);
}

$rawData  = file_get_contents("php://input");
$cartData = json_decode($rawData, true);
$cart     = $cartData['cart'] ?? null;
$customer = $cartData['customer'] ?? [];
$method   = $cartData['payment_method'] ?? '';

if (!is_array($cart) || count($cart) === 0) {
    respond(['status' => 'error', 'message' => 'Your cart is empty.'], 400);
}

/* ---------- customer details ---------- */
$clean = function ($value) { return trim(str_replace(["\r", "\n"], ' ', (string)$value)); };
$customer = [
    'name'    => $clean($customer['name'] ?? ''),
    'address' => $clean($customer['address'] ?? ''),
    'phone'   => $clean($customer['phone'] ?? ''),
    'email'   => $clean($customer['email'] ?? ''),
];
if ($customer['name'] === '' || $customer['address'] === '' ||
    !preg_match('/^[0-9+\-\s()]{7,20}$/', $customer['phone']) ||
    !filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) {
    respond(['status' => 'error', 'message' => 'Please fill in your name, address, a valid phone number and a valid email.'], 400);
}

if (!in_array($method, ['sslcommerz', 'cash-on-delivery', 'bkash'], true)) {
    respond(['status' => 'error', 'message' => 'Please choose a payment method.'], 400);
}
$bkashTrx = trim($cartData['bkash_trx_id'] ?? '');
if ($method === 'bkash' && !preg_match('/^[A-Za-z0-9]{6,20}$/', $bkashTrx)) {
    respond(['status' => 'error', 'message' => 'Please enter a valid bKash Transaction ID.'], 400);
}

/* ---------- build the order from OUR price list, not the browser's ---------- */
$items = [];
$subtotal = 0;
$orderDetails = "";
foreach ($cart as $item) {
    $name = (string)($item['name'] ?? '');
    $qty  = (int)($item['quantity'] ?? 0);
    if (!array_key_exists($name, $PRODUCT_CATALOG) || $qty < 1 || $qty > 100) {
        respond(['status' => 'error', 'message' => "Sorry, \"$name\" is not available. Please remove it from your cart."], 400);
    }
    $price = $PRODUCT_CATALOG[$name];
    $subtotal += $price * $qty;
    $items[] = ['name' => $name, 'price' => $price, 'quantity' => $qty];
    $orderDetails .= $name . " x " . $qty . "\n";
}

$tax      = round($subtotal * TAX_RATE, 2);
$shipping = SHIPPING_FEE;
$total    = round($subtotal + $tax + $shipping, 2);

// FIX: rand(1000, 9999) could repeat; this is unique per second + random suffix
$orderID = 'ORD' . date('ymdHis') . random_int(100, 999);

$order = [
    'order_id'       => $orderID,
    'items'          => $items,
    'subtotal'       => $subtotal,
    'tax'            => $tax,
    'shipping'       => $shipping,
    'total'          => $total,
    'customer'       => $customer,
    'payment_method' => $method,
    'bkash_trx_id'   => $method === 'bkash' ? $bkashTrx : '',
    'payment_status' => $method === 'sslcommerz' ? 'pending' : ($method === 'bkash' ? 'awaiting_verification' : 'pay_on_delivery'),
    'status'         => $method === 'sslcommerz' ? 'awaiting_payment' : 'placed',
    'created_at'     => date('c'),
];

if (!save_order($order)) {
    respond(['status' => 'error', 'message' => 'Could not save your order. Please try again.'], 500);
}

/* ---------- online payment: send the customer to SSLCommerz ---------- */
if ($method === 'sslcommerz') {
    [$gatewayUrl, $error] = sslc_init_session($order);
    if ($gatewayUrl === null) {
        $order['payment_status'] = 'failed';   // do not leave a dead order looking "awaiting payment"
        save_order($order);
        respond(['status' => 'error', 'message' => 'Online payment is unavailable right now. ' . $error], 502);
    }
    respond(['status' => 'redirect', 'url' => $gatewayUrl, 'order_id' => $orderID]);
}

/* ---------- Cash on Delivery / bKash ---------- */
respond([
    'status'   => 'success',
    'order_id' => $orderID,
    'total'    => $total,
    'details'  => $orderDetails,
]);
