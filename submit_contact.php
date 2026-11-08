<?php
header('Content-Type: text/plain; charset=UTF-8');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // FIX: "?? ''" avoids "undefined index" warnings; str_replace strips line breaks
    // so nobody can inject extra mail headers through the name/email fields.
    $name    = trim(str_replace(["\r", "\n"], ' ', $_POST["name"] ?? ''));
    $contact = trim(str_replace(["\r", "\n"], ' ', $_POST["contact"] ?? ''));
    $email   = trim(str_replace(["\r", "\n"], '',  $_POST["email"] ?? ''));
    $message = trim($_POST["message"] ?? '');

    // Validate inputs
    if (empty($name) || empty($contact) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
        http_response_code(400);
        echo "Invalid input. Please fill in the form correctly.";
        exit;
    }

    // Process the data (e.g., send an email or save to a database)
    $to = "admin@organicfoodstore.com"; // Replace with your email
    $subject = "New Contact Message from $name";
    $body = "Name: $name\nContact: $contact\nEmail: $email\n\nMessage:\n$message";
    $headers = "From: no-reply@organicfoodstore.com\r\nReply-To: $email"; // FIX: customer address goes in Reply-To

    if (@mail($to, $subject, $body, $headers)) {
        echo "Thank you! Your message has been sent.";
    } else {
        http_response_code(500);
        echo "Sorry, there was an error sending your message. Please try again.";
    }
} else {
    http_response_code(405);
    echo "Invalid request method.";
}
?>
