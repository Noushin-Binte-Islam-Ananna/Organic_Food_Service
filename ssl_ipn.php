<?php
// Instant Payment Notification: SSLCommerz's SERVER calls this directly (no browser involved),
// so the order still gets marked paid even if the customer closes the tab after paying.
// NOTE: it can only reach you on a public URL (not http://localhost) - use ngrok when testing.
require __DIR__ . '/config.php';

[$paid] = sslc_confirm_payment($_POST);
http_response_code(200);
echo $paid ? 'OK' : 'IGNORED';
