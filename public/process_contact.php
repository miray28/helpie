<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Function to sanitize input data
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize form data
    $name = isset($_POST['name']) ? sanitize_input($_POST['name']) : '';
    $email = isset($_POST['email']) ? sanitize_input($_POST['email']) : '';
    $subject = isset($_POST['subject']) ? sanitize_input($_POST['subject']) : '';
    $message = isset($_POST['message']) ? sanitize_input($_POST['message']) : '';

    // Validate inputs
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $response['message'] = 'All fields are required';
    } elseif (!is_valid_email($email)) {
        $response['message'] = 'Invalid email format';
    } else {
        // Email configuration
        $to = "support@helpie.com"; // Replace with your email
        $email_subject = "Contact Form: $subject";
        
        // Compose email message
        $email_body = "You have received a new message from the contact form.\n\n" .
            "Name: $name\n" .
            "Email: $email\n" .
            "Subject: $subject\n\n" .
            "Message:\n$message";
        
        // Email headers
        $headers = "From: $email\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Attempt to send email
        try {
            if (mail($to, $email_subject, $email_body, $headers)) {
                $response['success'] = true;
                $response['message'] = 'Thank you for your message. We will get back to you soon!';
                
                // Optional: Log successful submissions
                $log_file = 'contact_log.txt';
                $log_entry = date('Y-m-d H:i:s') . " - Message received from: $name ($email)\n";
                file_put_contents($log_file, $log_entry, FILE_APPEND);
            } else {
                $response['message'] = 'Sorry, there was an error sending your message. Please try again later.';
            }
        } catch (Exception $e) {
            $response['message'] = 'An error occurred. Please try again later.';
            // Optional: Log errors
            error_log("Contact form error: " . $e->getMessage());
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);

// Optional: Log all submissions (successful or not)
$log_file = 'submission_log.txt';
$log_entry = date('Y-m-d H:i:s') . " - Submission attempt: " . 
             ($response['success'] ? 'SUCCESS' : 'FAILED') . 
             " - Message: " . $response['message'] . "\n";
file_put_contents($log_file, $log_entry, FILE_APPEND);
?>