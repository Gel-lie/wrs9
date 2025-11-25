<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message = $_POST['message'] ?? '';

    if (empty($message)) {
        echo json_encode(['reply' => 'Please enter a message.']);
        exit;
    }

    // Prepare data
    $data = json_encode(['message' => $message]);

    // --- CHANGE THIS LINE if your Flask is hosted elsewhere ---
    $python_api_url = 'http://127.0.0.1:5000/chat';

    // Initialize cURL
    $ch = curl_init($python_api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    // Execute cURL request
    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        echo json_encode(['reply' => 'Error: Unable to reach Python API.']);
    } else {
        echo $response;
    }

    curl_close($ch);
}
?>
