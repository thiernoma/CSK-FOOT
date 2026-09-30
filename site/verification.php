<?php
/**
 * Page de vérification de réservation via QR Code
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';

$token = get('token');
$reservation = null;
$error = null;

if (empty($token)) {
    $error = 'Token de vérification manquant.';
} else {
    $reservation = Reservation::getByQRToken($token);
    if (!$reservation) {
        $error = 'Réservation introuvable ou token invalide.';
    }
}

// Action check-in/check-out (pour le personnel uniquement via lien spécial)
if ($reservation && get('action') === 'checkin' && get('key') === md5($token . 'csk_staff')) {
    Reservation::checkIn($reservation['id']);
    $reservation = Reservation::getByQRToken($token);
}

if ($reservation && get('action') === 'checkout' && get('key') === md5($token . 'csk_staff')) {
    Reservation::checkOut($reservation['id']);
    $reservation = Reservation::getByQRToken($token);
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de Réservation - <?= APP_NAME ?></title>

    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .verification-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }

        .card-header .logo {
            font-size: 50px;
            margin-bottom: 10px;
        }

        .card-header h1 {
            font-size: 20px;
            margin-bottom: 5px;
        }

        .card-body {
            padding: 25px;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 10px 20px;
            border-radius: 50px;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-valid {
            background: #d4edda;
            color: #155724;
        }

        .status-invalid {
            background: #f8d7da;
            color: #721c24;
        }

        .status-terminee {
            background: #e2e3e5;
            color: #383d41;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
        }

        .info-section {
            margin-bottom: 20px;
        }

        .info-section h3 {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #eee;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #666;
        }

        .info-value {
            font-weight: 600;
            color: #333;
        }

        .ticket-number {
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        .ticket-number .label {
            font-size: 12px;
            color: #666;
        }

        .ticket-number .number {
            font-size: 24px;
            font-weight: 700;
            color: #1a472a;
            letter-spacing: 2px;
        }

        .payment-status {
            text-align: center;
            padding: 15px;
            border-radius: 10px;
            font-weight: 600;
        }

        .payment-paye {
            background: #d4edda;
            color: #155724;
        }

        .payment-partiel {
            background: #fff3cd;
            color: #856404;
        }

        .payment-en_attente {
            background: #f8d7da;
            color: #721c24;
        }

        .error-box {
            text-align: center;
            padding: 40px;
        }

        .error-box .icon {
            font-size: 60px;
            color: #dc3545;
            margin-bottom: 20px;
        }

        .checkin-info {
            background: #e7f5ff;
            border: 1px solid #74c0fc;
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
            text-align: center;
        }

        .checkin-info i {
            color: #1971c2;
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: #1a472a;
            text-decoration: none;
            font-weight: 500;
        }

        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="verification-card">
        <div class="card-header">
            <div class="logo">⚽</div>
            <h1><?= APP_NAME ?></h1>
            <p>Vérification de Réservation</p>
        </div>

        <div class="card-body">
            <?php if ($error): ?>
            <div class="error-box">
                <div class="icon"><i class="fas fa-times-circle"></i></div>
                <h2>Vérification échouée</h2>
                <p><?= e($error) ?></p>
            </div>
            <?php else: ?>

            <!-- Statut -->
            <div style="text-align: center;">
                <?php
                $statusClass = match($reservation['statut_reservation']) {
                    'confirmee', 'en_cours' => 'status-valid',
                    'terminee' => 'status-terminee',
                    'annulee' => 'status-annulee',
                    default => 'status-invalid'
                };
                $statusIcon = match($reservation['statut_reservation']) {
                    'confirmee', 'en_cours' => 'fa-check-circle',
                    'terminee' => 'fa-flag-checkered',
                    'annulee' => 'fa-ban',
                    default => 'fa-question-circle'
                };
                $statusLabel = match($reservation['statut_reservation']) {
                    'confirmee' => 'Réservation Valide',
                    'en_cours' => 'En Cours',
                    'terminee' => 'Terminée',
                    'annulee' => 'Annulée',
                    default => $reservation['statut_reservation']
                };
                ?>
                <div class="status-badge <?= $statusClass ?>">
                    <i class="fas <?= $statusIcon ?> me-2"></i>
                    <?= $statusLabel ?>
                </div>
            </div>

            <!-- Numéro de ticket -->
            <div class="ticket-number">
                <div class="label">N° TICKET</div>
                <div class="number"><?= e($reservation['numero_ticket']) ?></div>
            </div>

            <!-- Détails réservation -->
            <div class="info-section">
                <h3><i class="fas fa-calendar-alt me-2"></i>Détails</h3>
                <div class="info-row">
                    <span class="info-label">Date</span>
                    <span class="info-value"><?= formatDateFr($reservation['date_reservation']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Horaire</span>
                    <span class="info-value"><?= formatTimeFull($reservation['heure_debut']) ?> - <?= formatTimeFull($reservation['heure_fin']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Terrain</span>
                    <span class="info-value"><?= e($reservation['terrain_nom']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Durée</span>
                    <span class="info-value"><?= formatDuration($reservation['duree_heures']) ?></span>
                </div>
            </div>

            <!-- Client -->
            <div class="info-section">
                <h3><i class="fas fa-user me-2"></i>Client</h3>
                <div class="info-row">
                    <span class="info-label">Nom</span>
                    <span class="info-value"><?= e($reservation['client_nom']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Téléphone</span>
                    <span class="info-value"><?= e($reservation['client_telephone']) ?></span>
                </div>
            </div>

            <!-- Paiement -->
            <div class="payment-status payment-<?= $reservation['statut_paiement'] ?>">
                <?php if ($reservation['statut_paiement'] === 'paye'): ?>
                    <i class="fas fa-check-circle me-2"></i> Payé - <?= formatMoney($reservation['montant']) ?>
                <?php elseif ($reservation['statut_paiement'] === 'partiel'): ?>
                    <i class="fas fa-clock me-2"></i> Acompte: <?= formatMoney($reservation['montant_paye']) ?>
                    <br><small>Reste: <?= formatMoney($reservation['montant'] - $reservation['montant_paye']) ?></small>
                <?php else: ?>
                    <i class="fas fa-exclamation-triangle me-2"></i> À payer: <?= formatMoney($reservation['montant']) ?>
                <?php endif; ?>
            </div>

            <!-- Check-in/Check-out info -->
            <?php if ($reservation['check_in_at'] || $reservation['check_out_at']): ?>
            <div class="checkin-info">
                <?php if ($reservation['check_in_at']): ?>
                <div><i class="fas fa-sign-in-alt me-2"></i>Check-in: <?= formatDate($reservation['check_in_at'], 'H:i') ?></div>
                <?php endif; ?>
                <?php if ($reservation['check_out_at']): ?>
                <div><i class="fas fa-sign-out-alt me-2"></i>Check-out: <?= formatDate($reservation['check_out_at'], 'H:i') ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php endif; ?>

            <a href="<?= siteUrl('site/') ?>" class="back-link">
                <i class="fas fa-arrow-left me-2"></i>Retour au site
            </a>
        </div>
    </div>
</body>
</html>
