<?php
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"]);
    $contact = trim($_POST["contact"]);
    $email = trim($_POST["email"]);
    $message = trim($_POST["message"]);

    // Validate inputs
    if (empty($name) || empty($contact) || !filter_var($email, FILTER_VALIDATE_EMAIL) || empty($message)) {
        echo "Invalid input. Please go back and fill in the form correctly.";
        exit;
    }

    // Process the data (e.g., send an email or save to a database)
    $to = "admin@organicfoodstore.com"; // Replace with your email
    $subject = "New Contact Message from $name";
    $body = "Name: $name\nContact: $contact\nEmail: $email\n\nMessage:\n$message";
    $headers = "From: $email";

    if (mail($to, $subject, $body, $headers)) {
        echo "Thank you! Your message has been sent.";
    } else {
        echo "Sorry, there was an error sending your message. Please try again.";
    }
} else {
    echo "Invalid request method.";
}
?>
