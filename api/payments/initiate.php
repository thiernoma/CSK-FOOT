<?php
/**
 * API pour initier un paiement mobile
 * Endpoint: POST /api/payments/initiate.php
 *
 * Deux modes :
 *  - reservation_id fourni  -> paiement d'une réservation EXISTANTE
 *  - sinon (données de créneau) -> réservation EN ATTENTE : rien n'est créé tant que
 *    le paiement n'est pas confirmé (le webhook créera la réservation).
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'services/WavePayment.php';
require_once APP_PATH . 'services/OrangeMoneyPayment.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Client.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

/** Petit helper de sortie JSON */
function payFail(string $message): void {
    echo json_encode(['success' => false, 'message' => $message]);
    exit;
}

try {
    $provider = $_POST['provider'] ?? null;
    $reservationId = (int)($_POST['reservation_id'] ?? 0);
    $paymentType = $_POST['payment_type'] ?? 'full';
    $clientPhone = $_POST['client_phone'] ?? '';

    if (!$provider || !in_array($provider, ['wave', 'om'])) {
        payFail('Provider invalide. Utilisez "wave" ou "om".');
    }

    $paymentData = null;

    if ($reservationId > 0) {
        // =============================================================
        // CAS A — Paiement d'une réservation EXISTANTE
        // =============================================================
        $amount = (float)($_POST['amount'] ?? 0);

        $db = Database::getInstance();
        $stmt = $db->prepare("
            SELECT r.*, t.nom AS terrain_nom, c.telephone AS client_telephone
            FROM reservations r
            LEFT JOIN terrains t ON t.id = r.terrain_id
            LEFT JOIN clients c ON c.id = r.client_id
            WHERE r.id = ? AND r.statut_reservation != 'annulee'
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$reservation) {
            payFail('Réservation non trouvée ou annulée.');
        }

        $montantNet = (float)$reservation['montant'] - (float)($reservation['remise'] ?? 0);
        $resteAPayer = $montantNet - (float)$reservation['montant_paye'];

        if ($resteAPayer <= 0) {
            payFail('Cette réservation est déjà entièrement payée.');
        }
        if ($amount <= 0 || $amount > $resteAPayer) {
            $amount = $resteAPayer; // par défaut : solde restant
        }
        if ($paymentType === 'acompte') {
            $acomptePercent = defined('ACOMPTE_MINIMUM_PERCENT') ? ACOMPTE_MINIMUM_PERCENT : 30;
            $minimumAmount = ($montantNet * $acomptePercent) / 100;
            if ($amount < $minimumAmount) {
                payFail("L'acompte minimum est de " . number_format($minimumAmount, 0, ',', ' ') . " FCFA (" . $acomptePercent . "%).");
            }
        }

        $paymentData = [
            'amount' => $amount,
            'currency' => 'XOF',
            'reservation_id' => $reservationId,
            'client_phone' => $clientPhone ?: ($reservation['client_telephone'] ?? ''),
            'description' => 'Réservation terrain - ' . ($reservation['terrain_nom'] ?? 'CSK'),
        ];

    } else {
        // =============================================================
        // CAS B — Réservation EN ATTENTE (créée uniquement après paiement)
        // =============================================================
        $terrainId       = (int)($_POST['terrain_id'] ?? 0);
        $date            = sanitize($_POST['date'] ?? '');
        $heureDebut      = sanitize($_POST['heure_debut'] ?? '');
        $heureFin        = sanitize($_POST['heure_fin'] ?? '');
        $clientPrenom    = sanitize($_POST['client_prenom'] ?? '');
        $clientNom       = sanitize($_POST['client_nom'] ?? '');
        $clientTelephone = preg_replace('/\s+/', '', $_POST['client_telephone'] ?? $clientPhone);
        $clientEmail     = sanitize($_POST['client_email'] ?? '');

        $errors = [];
        if (!$terrainId) $errors[] = 'Terrain non spécifié.';
        if (!$date || !strtotime($date)) {
            $errors[] = 'Date invalide.';
        } elseif ($date < date('Y-m-d')) {
            $errors[] = 'La date ne peut pas être dans le passé.';
        }
        if (!$heureDebut || !$heureFin) {
            $errors[] = 'Horaires non spécifiés.';
        } else {
            $heureDebut = wallToOp($heureDebut);
            $heureFin   = wallToOp($heureFin);
            if ($heureDebut >= $heureFin) $errors[] = "L'heure de fin doit être après l'heure de début.";
        }
        if (empty($clientNom)) $errors[] = 'Le nom est obligatoire.';
        if (empty($clientTelephone) || !preg_match('/^(77|78|76|70|75)\d{7}$/', $clientTelephone)) {
            $errors[] = 'Numéro de téléphone sénégalais invalide.';
        }
        if ($clientEmail && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }

        $terrain = $terrainId ? Terrain::getById($terrainId) : null;
        if (!$terrain) {
            $errors[] = 'Terrain introuvable.';
        } elseif ($terrain['statut'] !== 'actif') {
            $errors[] = 'Ce terrain n\'est pas disponible.';
        }

        if (empty($errors) && !Terrain::checkAvailability($terrainId, $date, $heureDebut, $heureFin)) {
            $errors[] = 'Ce créneau est déjà réservé.';
        }

        if (!empty($errors)) {
            payFail(implode(' ', $errors));
        }

        // Client (créé/retrouvé — sans réservation)
        $client = Client::getByPhone($clientTelephone);
        if (!$client) {
            $clientId = Client::create([
                'nom' => $clientNom,
                'prenom' => $clientPrenom,
                'telephone' => $clientTelephone,
                'email' => $clientEmail,
                'type_client' => 'particulier',
                'statut' => 'actif',
            ]);
        } else {
            $clientId = $client['id'];
        }

        // Montant calculé côté serveur (on ne fait pas confiance au client)
        $montant = Terrain::calculatePrice($terrainId, $date, $heureDebut, $heureFin);
        $dureeHeures = opDurationHours($heureDebut, $heureFin);
        if ($montant <= 0) {
            payFail('Montant invalide pour ce créneau.');
        }

        // Données de la réservation à créer APRÈS paiement (webhook)
        $bookingData = [
            'client_id' => (int)$clientId,
            'terrain_id' => $terrainId,
            'date_reservation' => $date,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin,
            'duree_heures' => $dureeHeures,
            'montant' => $montant,
            'source' => 'site_web',
            'notes' => 'Réservation en ligne',
        ];

        $paymentData = [
            'amount' => $montant,
            'currency' => 'XOF',
            'booking_data' => $bookingData,
            'client_phone' => $clientTelephone,
            'description' => 'Réservation ' . ($terrain['nom'] ?? 'terrain'),
        ];
    }

    // =============================================================
    // Dispatch fournisseur
    // =============================================================
    if ($provider === 'wave') {
        if (!WavePayment::isEnabled()) {
            payFail('Wave Payment n\'est pas encore disponible.');
        }
        $wave = new WavePayment();
        $result = $wave->createPayment($paymentData);
    } else { // om
        if (!OrangeMoneyPayment::isEnabled()) {
            payFail('Orange Money n\'est pas encore disponible.');
        }
        $om = new OrangeMoneyPayment();
        $result = $om->createPayment($paymentData);
    }

    echo json_encode($result);

} catch (Exception $e) {
    $logEntry = date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . "\n";
    @file_put_contents(LOGS_PATH . 'payment_errors.log', $logEntry, FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'initiation du paiement',
    ]);
}
