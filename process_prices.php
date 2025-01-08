<?php
// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the raw POST data
    $rawData = file_get_contents('php://input');

    // Decode the JSON data
    $data = json_decode($rawData, true);

    // Validate the data
    if (isset($data['filename']) && isset($data['content'])) {
        $filename = $data['filename'];
        $content = $data['content'];
        $secret_token = $data['secret_token'];

        if ($secret_token !== 'secret_token') {
            http_response_code(403);
            echo 'Access denied.\n';
            exit;
        }

        // Save the content to the file
        if (file_put_contents($filename, json_encode($content))) {
            echo 'File saved successfully: ' . $filename . "\n";
        } else {
            http_response_code(500);
            echo 'Error saving the file.\n';
        }
    } else {
        http_response_code(400);
        echo 'Invalid data.\n';
    }
} else {
    http_response_code(405);
    echo 'Invalid request method.\n';
}
