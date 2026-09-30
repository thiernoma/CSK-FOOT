<?php
/**
 * Service Orange Money Payment
 * Intégration API Orange Money pour les paiements mobiles
 * Documentation: https://developer.orange.com/apis/om-webpay
 */

class OrangeMoneyPayment
{
    private $apiKey;
    private $merchantKey;
    private $merchantNumber;
    private $baseUrl = 'https://api.orange.com/orange-money-webpay/dev/v1'; // Changer en prod: /prod/v1
    private $callbackUrl;
    private $accessToken;

    public function __construct()
    {
        $this->apiKey = ORANGE_MONEY_API_KEY;
        $this->merchantKey = ORANGE_MONEY_MERCHANT_KEY;
        $this->merchantNumber = ORANGE_MONEY_MERCHANT_NUMBER;
        $this->callbackUrl = ORANGE_MONEY_CALLBACK_URL;
    }

    /**
     * Vérifier si Orange Money est activé
     */
    public static function isEnabled(): bool
    {
        return defined('ORANGE_MONEY_ENABLED') && ORANGE_MONEY_ENABLED === true
               && !empty(ORANGE_MONEY_API_KEY)
               && !empty(ORANGE_MONEY_MERCHANT_KEY);
    }

    /**
     * Obtenir le token d'accès OAuth
     */
    private function getAccessToken(): ?string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $url = 'https://api.orange.com/oauth/v3/token';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode($this->apiKey . ':' . $this->merchantKey),
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            $this->accessToken = $data['access_token'] ?? null;
            return $this->accessToken;
        }

        $this->logError('Failed to get Orange Money access token: ' . $response);
        return null;
    }

    /**
     * Créer une demande de paiement
     *
     * @param array $data [
     *   'amount' => montant en FCFA,
     *   'currency' => 'OUV' (FCFA pour zone UEMOA),
     *   'client_phone' => numéro du client (format: 221XXXXXXXXX),
     *   'reservation_id' => ID de la réservation,
     *   'description' => description du paiement
     * ]
     * @return array
     */
    public function createPayment(array $data): array
    {
        if (!self::isEnabled()) {
            return [
                'success' => false,
                'message' => 'Orange Money n\'est pas configuré'
            ];
        }

        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Impossible d\'obtenir le token d\'accès'
            ];
        }

        // Générer un ID de commande unique
        $orderId = 'RSV-' . $data['reservation_id'] . '-' . time();

        $payload = [
            'merchant_key' => $this->merchantKey,
            'currency' => $data['currency'] ?? 'OUV',
            'order_id' => $orderId,
            'amount' => (int) $data['amount'],
            'return_url' => APP_URL . '/site/paiement-success?provider=om&ref=' . $data['reservation_id'],
            'cancel_url' => APP_URL . '/site/paiement-error?provider=om&ref=' . $data['reservation_id'],
            'notif_url' => $this->callbackUrl,
            'lang' => 'fr',
            'reference' => 'Réservation #' . $data['reservation_id']
        ];

        $response = $this->makeRequest('/webpayment', 'POST', $payload, $accessToken);

        if (isset($response['payment_url']) || isset($response['pay_token'])) {
            // Sauvegarder la transaction
            $transactionId = $response['pay_token'] ?? $orderId;
            $this->saveTransaction([
                'provider' => 'orange_money',
                'transaction_id' => $transactionId,
                'order_id' => $orderId,
                'reservation_id' => $data['reservation_id'],
                'amount' => $data['amount'],
                'status' => 'pending',
                'payment_url' => $response['payment_url'] ?? null
            ]);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'order_id' => $orderId,
                'payment_url' => $response['payment_url'] ?? null,
                'pay_token' => $response['pay_token'] ?? null
            ];
        }

        return [
            'success' => false,
            'message' => $response['message'] ?? $response['description'] ?? 'Erreur lors de la création du paiement'
        ];
    }

    /**
     * Vérifier le statut d'un paiement
     */
    public function checkPaymentStatus(string $orderId, string $payToken = null): array
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'message' => 'Impossible d\'obtenir le token d\'accès'
            ];
        }

        $endpoint = '/transactionstatus';
        $payload = [
            'order_id' => $orderId,
            'amount' => 0, // L'API retourne le montant
            'pay_token' => $payToken
        ];

        $response = $this->makeRequest($endpoint, 'POST', $payload, $accessToken);

        if (isset($response['status'])) {
            $isPaid = in_array($response['status'], ['SUCCESS', 'SUCCESSFULL']);

            return [
                'success' => true,
                'status' => $response['status'],
                'is_paid' => $isPaid,
                'data' => $response
            ];
        }

        return [
            'success' => false,
            'message' => 'Impossible de vérifier le statut'
        ];
    }

    /**
     * Traiter le callback d'Orange Money
     */
    public function handleCallback(array $payload): array
    {
        $status = $payload['status'] ?? null;
        $orderId = $payload['order_id'] ?? null;
        $payToken = $payload['pay_token'] ?? null;
        $txnId = $payload['txnid'] ?? null;

        if (!$orderId) {
            return ['success' => false, 'message' => 'Order ID manquant'];
        }

        // Mettre à jour le statut dans la base de données
        $this->updateTransactionStatus($orderId, $status, $txnId);

        if (in_array($status, ['SUCCESS', 'SUCCESSFULL'])) {
            // Extraire l'ID de réservation depuis l'order_id (format: RSV-{id}-{timestamp})
            preg_match('/RSV-(\d+)-/', $orderId, $matches);
            $reservationId = $matches[1] ?? null;

            if ($reservationId) {
                $this->confirmReservation((int)$reservationId, $txnId ?? $orderId);
            }
        }

        return [
            'success' => true,
            'status' => $status,
            'order_id' => $orderId
        ];
    }

    /**
     * Effectuer une requête API
     */
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = [], string $token = null): array
    {
        $url = $this->baseUrl . $endpoint;

        $headers = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError('Orange Money API Error: ' . $error);
            return ['success' => false, 'message' => 'Erreur de connexion'];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logError('Orange Money API HTTP ' . $httpCode . ': ' . $response);
        }

        return $decoded ?? [];
    }

    /**
     * Sauvegarder une transaction
     */
    private function saveTransaction(array $data): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO paiements_mobile
                (provider, transaction_id, order_id, reservation_id, montant, statut, payment_url, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            return $stmt->execute([
                $data['provider'],
                $data['transaction_id'],
                $data['order_id'] ?? null,
                $data['reservation_id'],
                $data['amount'],
                $data['status'],
                $data['payment_url']
            ]);
        } catch (Exception $e) {
            $this->logError('Save transaction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour le statut d'une transaction
     */
    private function updateTransactionStatus(string $orderId, string $status, string $txnId = null): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                UPDATE paiements_mobile
                SET statut = ?,
                    provider_txn_id = ?,
                    updated_at = NOW()
                WHERE order_id = ? OR transaction_id = ?
            ");

            return $stmt->execute([$status, $txnId, $orderId, $orderId]);
        } catch (Exception $e) {
            $this->logError('Update transaction error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Confirmer une réservation après paiement
     */
    private function confirmReservation(int $reservationId, string $transactionId): bool
    {
        try {
            $db = Database::getInstance();

            // Mettre à jour la réservation
            $stmt = $db->prepare("
                UPDATE reservations
                SET statut = 'confirmee',
                    mode_paiement = 'om',
                    reference_paiement = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$transactionId, $reservationId]);

            // Envoyer SMS de confirmation
            $this->sendConfirmationSMS($reservationId);

            return true;
        } catch (Exception $e) {
            $this->logError('Confirm reservation error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Envoyer SMS de confirmation
     */
    private function sendConfirmationSMS(int $reservationId): void
    {
        // TODO: Implémenter l'envoi SMS via Orange SMS API
        $this->logInfo('SMS confirmation à envoyer pour réservation #' . $reservationId);
    }

    /**
     * Logger une erreur
     */
    private function logError(string $message): void
    {
        $logFile = LOGS_PATH . 'orange_money_payments.log';
        $logMessage = date('Y-m-d H:i:s') . ' [ERROR] ' . $message . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }

    /**
     * Logger une info
     */
    private function logInfo(string $message): void
    {
        $logFile = LOGS_PATH . 'orange_money_payments.log';
        $logMessage = date('Y-m-d H:i:s') . ' [INFO] ' . $message . PHP_EOL;
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
