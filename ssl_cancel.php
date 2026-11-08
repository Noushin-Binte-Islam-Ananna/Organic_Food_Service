<?php
// The customer pressed "Cancel" on the SSLCommerz page.
require __DIR__ . '/config.php';

sslc_mark_unpaid($_POST ?: $_GET, 'cancelled');
header('Location: cart.html?status=cancelled', true, 303);
exit;
