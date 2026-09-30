<?php
/**
 * Wave Payment Callback API
 * Endpoint pour recevoir les notifications de Wave
 */

// Headers pour API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Charger l'initialisation
require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'services/WavePayment.php';

// Accepter uniquement POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Logger la requête entrante
$logFile = LOGS_PATH . 'wave_callbacks.log';
$rawInput = file_get_contents('php://input');
$logEntry = date('Y-m-d H:i:s') . " - Callback received\n";
$logEntry .= "Headers: " . json_encode(getallheaders()) . "\n";
$logEntry .= "Body: " . $rawInput . "\n";
$logEntry .= "---\n";
file_put_contents($logFile, $logEntry, FILE_APPEND);

try {
    // Récupérer le header de signature Wave (insensible à la casse selon le serveur)
    $signature = $_SERVER['HTTP_WAVE_SIGNATURE']
        ?? ($_SERVER['HTTP_X_WAVE_SIGNATURE'] ?? '');
    if ($signature === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strtolower($k) === 'wave-signature') { $signature = $v; break; }
        }
    }

    // Traiter le callback à partir du corps BRUT (indispensable pour la signature)
    $wave = new WavePayment();
    $result = $wave->handleCallback($rawInput, $signature);

    // Code HTTP proposé par le service (401 signature, 400 payload, 200 sinon)
    $httpCode = $result['http_code'] ?? ($result['success'] ? 200 : 400);
    http_response_code($httpCode);

    if ($result['success']) {
        // Logger le succès
        $logEntry = date('Y-m-d H:i:s') . " - Callback processed successfully\n";
        $logEntry .= "Transaction ID: " . ($result['transaction_id'] ?? 'N/A') . "\n";
        $logEntry .= "Status: " . ($result['status'] ?? 'N/A') . "\n";
        $logEntry .= "---\n";
        file_put_contents($logFile, $logEntry, FILE_APPEND);

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
