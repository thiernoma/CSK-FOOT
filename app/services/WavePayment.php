<?php
/**
 * Service Wave Payment (Wave Business API — Checkout)
 * Documentation : https://docs.wave.com/business
 *
 * Flux :
 *   1. createPayment()  -> POST /v1/checkout/sessions  -> renvoie wave_launch_url (redirection client)
 *   2. Le client paie dans l'app Wave
 *   3. Wave redirige vers success_url / error_url (UX uniquement, ne fait PAS foi)
 *   4. Wave notifie le webhook (source de vérité) -> handleCallback() -> enregistre le paiement
 */

class WavePayment
{
    private $apiKey;
    private $apiSecret;
    private $webhookSecret;
    private $aggregatedMerchantId;
    private $baseUrl = 'https://api.wave.com/v1';
    private $callbackUrl;

    public function __construct()
    {
        // trim() : neutralise tout espace/retour à la ligne parasite issu d'un copier-coller
        // (un seul caractère en trop dans un secret casse la signature HMAC).
        $this->apiKey = trim(WAVE_API_KEY);
        $this->apiSecret = trim(defined('WAVE_API_SECRET') ? WAVE_API_SECRET : '');
        $this->webhookSecret = trim(defined('WAVE_WEBHOOK_SECRET') ? WAVE_WEBHOOK_SECRET : (defined('WAVE_SECRET_KEY') ? WAVE_SECRET_KEY : ''));
        $this->aggregatedMerchantId = trim(defined('WAVE_AGGREGATED_MERCHANT_ID') ? WAVE_AGGREGATED_MERCHANT_ID : '');
        $this->callbackUrl = WAVE_CALLBACK_URL;
    }

    /**
     * Wave est-il activé et correctement configuré ?
     */
    public static function isEnabled(): bool
    {
        return defined('WAVE_ENABLED') && WAVE_ENABLED === true
               && defined('WAVE_API_KEY') && !empty(WAVE_API_KEY);
    }

    /**
     * Créer une session de paiement (checkout session).
     *
     * @param array $data [
     *   'amount' => montant en FCFA (entier),
     *   'currency' => 'XOF',
     *   'reservation_id' => ID de la réservation,
     *   'client_phone' => (optionnel) numéro du client,
     *   'description' => (optionnel) libellé
     * ]
     */
    public function createPayment(array $data): array
    {
        if (!self::isEnabled()) {
            return ['success' => false, 'message' => 'Wave Payment n\'est pas configuré'];
        }

        $reservationId = isset($data['reservation_id']) ? (int)$data['reservation_id'] : 0;

        // Référence Wave : réservation existante (RSV-x) OU réservation en attente (BOOK-xxxx)
        $clientReference = $data['client_reference']
            ?? ($reservationId > 0 ? 'RSV-' . $reservationId : 'BOOK-' . bin2hex(random_bytes(8)));

        // Wave attend un montant sous forme de chaîne, sans décimales pour le XOF.
        $payload = [
            'amount'          => (string)(int)round($data['amount']),
            'currency'        => $data['currency'] ?? 'XOF',
            'client_reference'=> $clientReference,
            'success_url'     => APP_URL . '/site/paiement-success.php?provider=wave&ref=' . urlencode($clientReference),
            'error_url'       => APP_URL . '/site/paiement-error.php?provider=wave&ref=' . urlencode($clientReference),
        ];

        // Marchand agrégé (optionnel)
        if (!empty($this->aggregatedMerchantId)) {
            $payload['aggregated_merchant_id'] = $this->aggregatedMerchantId;
        }

        $response = $this->makeRequest('/checkout/sessions', 'POST', $payload);

        if (isset($response['id']) && isset($response['wave_launch_url'])) {
            $this->saveTransaction([
                'provider'         => 'wave',
                'transaction_id'   => $response['id'],
                'reservation_id'   => $reservationId > 0 ? $reservationId : null,
                'client_reference' => $clientReference,
                'booking_data'     => isset($data['booking_data']) ? json_encode($data['booking_data']) : null,
                'amount'           => (float)$data['amount'],
                'status'           => $response['payment_status'] ?? 'processing',
                'payment_url'      => $response['wave_launch_url'],
            ]);

            return [
                'success'        => true,
                'transaction_id' => $response['id'],
                'payment_url'    => $response['wave_launch_url'],
                'checkout_url'   => $response['wave_launch_url'],
            ];
        }

        $this->logError('createPayment a échoué: ' . json_encode($response));
        return [
            'success' => false,
            'message' => $response['message'] ?? ($response['error_message'] ?? 'Erreur lors de la création du paiement Wave'),
        ];
    }

    /**
     * Vérifier le statut d'une session auprès de Wave (source fiable).
     * @return array ['success', 'is_paid', 'checkout_status', 'payment_status', 'data']
     */
    public function checkPaymentStatus(string $sessionId): array
    {
        $response = $this->makeRequest('/checkout/sessions/' . rawurlencode($sessionId), 'GET');

        if (isset($response['payment_status'])) {
            return [
                'success'         => true,
                'is_paid'         => $response['payment_status'] === 'succeeded',
                'checkout_status' => $response['checkout_status'] ?? null,
                'payment_status'  => $response['payment_status'],
                'data'            => $response,
            ];
        }

        return ['success' => false, 'message' => 'Impossible de vérifier le statut', 'data' => $response];
    }

    /**
     * Traiter la notification webhook de Wave.
     *
     * @param string $rawBody         Corps HTTP brut (php://input) — NE PAS ré-encoder
     * @param string $signatureHeader Contenu du header "Wave-Signature"
     */
    public function handleCallback(string $rawBody, string $signatureHeader): array
    {
        // 1. Vérifier la signature sur le corps BRUT
        if (!$this->verifyWebhookSignature($rawBody, $signatureHeader)) {
            $this->logError('Signature webhook invalide. Header=' . $signatureHeader);
            return ['success' => false, 'message' => 'Signature invalide', 'http_code' => 401];
        }

        // Signature valide : à partir d'ici on accuse TOUJOURS réception (HTTP 200),
        // même pour un événement de test (test.test_event) ou un payload inattendu.
        // Un 4xx sur une requête signée ferait échouer le health-check de Wave.
        $event = json_decode($rawBody, true);
        if (!is_array($event)) {
            $this->logInfo('Webhook signé mais JSON illisible (ignoré): ' . substr($rawBody, 0, 200));
            return ['success' => true, 'status' => 'ignored', 'http_code' => 200];
        }

        $type = $event['type'] ?? '';
        $data = $event['data'] ?? [];
        $sessionId = $data['id'] ?? null;
        $paymentStatus = $data['payment_status'] ?? null;
        $clientRef = $data['client_reference'] ?? null;

        switch ($type) {
            case 'checkout.session.completed':
                if ($sessionId) {
                    $this->updateTransactionStatus($sessionId, $paymentStatus ?? 'processing');

                    if ($paymentStatus === 'succeeded') {
                        // Re-vérifier auprès de Wave pour éviter toute falsification
                        $check = $this->checkPaymentStatus($sessionId);
                        if (empty($check['is_paid'])) {
                            $this->logError('Webhook succeeded mais vérification API != payé pour ' . $sessionId);
                            break;
                        }
                        // Paye la réservation existante OU crée la réservation en attente
                        $this->recordReservationPayment($sessionId);
                    }
                }
                break;

            case 'checkout.session.payment_failed':
                if ($sessionId) {
                    $this->updateTransactionStatus($sessionId, 'failed');
                }
                break;

            case 'test.test_event':
                $this->logInfo('Événement de test Wave reçu et validé.');
                break;

            default:
                $this->logInfo('Événement Wave non géré: ' . $type);
        }

        return ['success' => true, 'status' => $paymentStatus ?? $type, 'transaction_id' => $sessionId, 'http_code' => 200];
    }

    /**
     * Vérifier la signature d'un webhook Wave.
     *
     * Header : Wave-Signature: t=<unix_ts>,v1=<hmac_hex>[,v1=<hmac_hex>...]
     * Signature = HMAC-SHA256( timestamp + corps_brut ) avec le secret de signature (WAVE_WEBHOOK_SECRET).
     */
    private function verifyWebhookSignature(string $rawBody, string $signatureHeader): bool
    {
        if (empty($this->webhookSecret) || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $kv = array_pad(explode('=', trim($part), 2), 2, null);
            if ($kv[0] === 't') {
                $timestamp = $kv[1];
            } elseif ($kv[0] === 'v1' && $kv[1] !== null) {
                $signatures[] = $kv[1];
            }
        }

        if (!$timestamp || empty($signatures)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . $rawBody, $this->webhookSecret);
        foreach ($signatures as $sig) {
            if (hash_equals($expected, $sig)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extraire l'ID de réservation depuis le client_reference ('RSV-123').
     */
    private function reservationIdFromReference($clientRef): int
    {
        if (!$clientRef) return 0;
        if (preg_match('/(\d+)/', (string)$clientRef, $m)) {
            return (int)$m[1];
        }
        return 0;
    }

    /**
     * Enregistrer le paiement encaissé sur la réservation (caisse + rapports),
     * de façon idempotente (Wave peut ré-émettre le webhook).
     */
    private function recordReservationPayment(string $sessionId): void
    {
        require_once APP_PATH . 'models/Client.php';
        require_once APP_PATH . 'models/Terrain.php';
        require_once APP_PATH . 'models/Reservation.php';

        try {
            $db = Database::getInstance();

            $stmt = $db->prepare("SELECT * FROM paiements_mobile WHERE transaction_id = ? LIMIT 1");
            $stmt->execute([$sessionId]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$txn) {
                $this->logError('Transaction mobile introuvable: ' . $sessionId);
                return;
            }

            $montant = (float)$txn['montant'];
            if ($montant <= 0) {
                $this->logError('Montant introuvable pour la transaction ' . $sessionId);
                return;
            }

            // ---- CAS A : transaction de RÉSERVATION EN ATTENTE (identifiée par booking_data) ----
            if (!empty($txn['booking_data'])) {
                // Déjà créée (webhook rejoué) -> idempotence
                if (!empty($txn['reservation_id'])) {
                    return;
                }

                $booking = json_decode($txn['booking_data'], true);
                if (!is_array($booking)) {
                    $this->logError('booking_data illisible pour ' . $sessionId);
                    return;
                }

                // Créer la réservation avec paiement immédiat (recu_par null -> "Wave Business" à l'affichage)
                $booking['montant']           = $montant;
                $booking['mode_paiement']     = 'wave';
                $booking['paiement_immediat'] = $montant;
                $booking['cree_par']          = null;
                $booking['notes']             = $booking['notes'] ?? 'Réservation en ligne (Wave)';

                $res = Reservation::create($booking);

                if (!empty($res['success'])) {
                    $db->prepare("UPDATE paiements_mobile SET reservation_id = ?, statut = 'succeeded', updated_at = NOW() WHERE transaction_id = ?")
                       ->execute([$res['id'], $sessionId]);
                    $this->logInfo("Réservation créée après paiement Wave: #{$res['id']} ({$res['numero_ticket']}), session $sessionId, montant $montant");
                } else {
                    // Le créneau a pu être pris entre-temps : argent encaissé mais réservation impossible.
                    $db->prepare("UPDATE paiements_mobile SET statut = 'conflict', updated_at = NOW() WHERE transaction_id = ?")->execute([$sessionId]);
                    $this->logError("ÉCHEC création réservation post-paiement (créneau indisponible ?) session $sessionId — REMBOURSEMENT À PRÉVOIR: " . ($res['message'] ?? ''));
                }
                return;
            }

            // ---- CAS B : paiement d'une RÉSERVATION EXISTANTE ----
            if (!empty($txn['reservation_id'])) {
                if (!empty($txn['paiement_id'])) {
                    return; // déjà encaissé (idempotence)
                }
                $result = Reservation::addPayment((int)$txn['reservation_id'], [
                    'montant'       => $montant,
                    'mode_paiement' => 'wave',
                    'reference'     => $sessionId,
                    'recu_par'      => null,
                    'notes'         => 'Paiement Wave en ligne',
                ]);
                if (!empty($result['success']) && !empty($result['paiement_id'])) {
                    $db->prepare("UPDATE paiements_mobile SET paiement_id = ?, statut = 'succeeded', updated_at = NOW() WHERE transaction_id = ?")
                       ->execute([$result['paiement_id'], $sessionId]);
                    $this->logInfo("Paiement Wave enregistré: réservation #{$txn['reservation_id']}, session $sessionId, montant $montant");
                } else {
                    $db->prepare("UPDATE paiements_mobile SET statut = 'succeeded', updated_at = NOW() WHERE transaction_id = ?")->execute([$sessionId]);
                    $this->logInfo("Paiement Wave non ré-enregistré (déjà soldée ?) réservation #{$txn['reservation_id']}: " . ($result['message'] ?? ''));
                }
                return;
            }

            $this->logError('Transaction sans reservation_id ni booking_data: ' . $sessionId);
        } catch (Exception $e) {
            $this->logError('recordReservationPayment: ' . $e->getMessage());
        }
    }

    /**
     * Requête HTTP vers l'API Wave.
     */
    private function makeRequest(string $endpoint, string $method = 'GET', array $data = []): array
    {
        $url = $this->baseUrl . $endpoint;

        // Corps de la requête (chaîne vide pour GET) — DOIT être identique à ce qui est signé
        $body = ($method === 'POST') ? json_encode($data) : '';

        $headers = [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        // Signature des requêtes SORTANTES (imposée par le compte Wave) :
        // Wave-Signature: t=<ts>,v1=HMAC-SHA256( timestamp + corps ) avec le secret AKS.
        if (!empty($this->apiSecret)) {
            $timestamp = (string)time();
            $signature = hash_hmac('sha256', $timestamp . $body, $this->apiSecret);
            $headers[] = 'Wave-Signature: t=' . $timestamp . ',v1=' . $signature;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        } elseif ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->logError('Wave API cURL: ' . $error);
            return ['success' => false, 'message' => 'Erreur de connexion à Wave'];
        }

        $decoded = json_decode($response, true);

        if ($httpCode >= 400) {
            $this->logError('Wave API HTTP ' . $httpCode . ': ' . $response);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Sauvegarder une transaction mobile (idempotent sur transaction_id).
     */
    private function saveTransaction(array $data): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                INSERT INTO paiements_mobile
                    (provider, transaction_id, reservation_id, client_reference, booking_data, montant, statut, payment_url, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    montant = VALUES(montant),
                    statut = VALUES(statut),
                    payment_url = VALUES(payment_url),
                    updated_at = NOW()
            ");
            return $stmt->execute([
                $data['provider'],
                $data['transaction_id'],
                $data['reservation_id'],
                $data['client_reference'] ?? null,
                $data['booking_data'] ?? null,
                $data['amount'],
                $data['status'],
                $data['payment_url'],
            ]);
        } catch (Exception $e) {
            $this->logError('saveTransaction: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Mettre à jour le statut d'une transaction mobile.
     */
    private function updateTransactionStatus(string $transactionId, string $status): bool
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("UPDATE paiements_mobile SET statut = ?, updated_at = NOW() WHERE transaction_id = ?");
            return $stmt->execute([$status, $transactionId]);
        } catch (Exception $e) {
            $this->logError('updateTransactionStatus: ' . $e->getMessage());
            return false;
        }
    }

    private function logError(string $message): void
    {
        @file_put_contents(LOGS_PATH . 'wave_payments.log', date('Y-m-d H:i:s') . ' [ERROR] ' . $message . PHP_EOL, FILE_APPEND);
    }

    private function logInfo(string $message): void
    {
        @file_put_contents(LOGS_PATH . 'wave_payments.log', date('Y-m-d H:i:s') . ' [INFO] ' . $message . PHP_EOL, FILE_APPEND);
    }
}
