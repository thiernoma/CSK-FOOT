<?php
/**
 * Orange Money Payment Callback API
 * Endpoint pour recevoir les notifications d'Orange Money
 */

// Headers pour API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Charger l'initialisation
require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'services/OrangeMoneyPayment.php';

// Accepter uniquement POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Logger la requête entrante
$logFile = LOGS_PATH . 'om_callbacks.log';
$rawInput = file_get_contents('php://input');
$logEntry = date('Y-m-d H:i:s') . " - Callback received\n";
$logEntry .= "Headers: " . json_encode(getallheaders()) . "\n";
$logEntry .= "Body: " . $rawInput . "\n";
$logEntry .= "GET params: " . json_encode($_GET) . "\n";
$logEntry .= "POST params: " . json_encode($_POST) . "\n";
$logEntry .= "---\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Orange Money peut envoyer les données en POST form ou JSON
    $payload = [];

    if (!empty($_POST)) {
        $payload = $_POST;
    } else {
        $payload = json_decode($rawInput, true) ?? [];
    }

    // Aussi vérifier les paramètres GET (certaines implémentations les utilisent)
    if (empty($payload) && !empty($_GET)) {
        $payload = $_GET;
    }

    if (empty($payload)) {
        http_response_code(400);
        echo json_encode(['error' => 'No payload received']);
        exit;
    }

    // Traiter le callback
    $om = new OrangeMoneyPayment();
    $result = $om->handleCallback($payload);

    if ($result['success']) {
        // Logger le succès
        $logEntry = date('Y-m-d H:i:s') . " - Callback processed successfully\n";
        $logEntry .= "Order ID: " . ($result['order_id'] ?? 'N/A') . "\n";
        $logEntry .= "Status: " . ($result['status'] ?? 'N/A') . "\n";
        $logEntry .= "---\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Callback processed'
        ]);
    } else {
        // Logger l'échec
        $logEntry = date('Y-m-d H:i:s') . " - Callback processing failed\n";
        $logEntry .= "Error: " . ($result['message'] ?? 'Unknown') . "\n";
        $logEntry .= "---\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $result['message'] ?? 'Processing failed'
        ]);
    }

} catch (Exception $e) {
    // Logger l'exception
    $logEntry = date('Y-m-d H:i:s') . " - Exception: " . $e->getMessage() . "\n";
    $logEntry .= "---\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error'
    ]);
}
