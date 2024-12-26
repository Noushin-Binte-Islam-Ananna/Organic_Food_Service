<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawData = file_get_contents("php://input");
    $cartData = json_decode($rawData, true);
    $cart = $cartData['cart'];

    $totalAmount = 0;
    $orderDetails = "";

    foreach ($cart as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
        $orderDetails .= $item['name'] . " x " . $item['quantity'] . "\n";
    }

    $tax = $totalAmount * 0.05;
    $grandTotal = $totalAmount + $tax;

    // Simulate storing the order in the database
    $orderID = rand(1000, 9999);
    $response = [
        'status' => 'success',
        'order_id' => $orderID,
        'total' => $grandTotal,
        'details' => $orderDetails
    ];

    // Return response to frontend
    header('Content-Type: application/json');
    echo json_encode($response);
}
?>
