<?php
// SSLCommerz sends the customer's browser here (as a POST) after a successful payment.
require __DIR__ . '/config.php';

[$paid, $order] = sslc_confirm_payment($_POST ?: $_GET);

if ($paid) {
    header('Location: cart.html?status=success&order=' . urlencode($order['order_id']), true, 303);
} else {
    header('Location: cart.html?status=failed', true, 303);
}
exit;
