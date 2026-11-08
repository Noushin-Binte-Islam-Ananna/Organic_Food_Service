<?php
// The payment was declined / failed on the SSLCommerz page.
require __DIR__ . '/config.php';

sslc_mark_unpaid($_POST ?: $_GET, 'failed');
header('Location: cart.html?status=failed', true, 303);
exit;
