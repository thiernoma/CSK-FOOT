<?php
/**
 * Page de Réservation - Site public
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'services/WavePayment.php';
require_once APP_PATH . 'services/OrangeMoneyPayment.php';

// Récupérer les terrains actifs
$terrains = Terrain::getAll('actif');

// Terrain présélectionné (si passé en paramètre)
$terrainId = isset($_GET['terrain']) ? (int)$_GET['terrain'] : null;

// Vérifier si les paiements mobiles sont activés
$waveEnabled = WavePayment::isEnabled();
$omEnabled = OrangeMoneyPayment::isEnabled();
$mobilePaymentEnabled = $waveEnabled || $omEnabled;

// Règles tarifaires pour le calcul dynamique côté JS (matinal / normal / weekend)
// heure_pointe_debut = heure de coupure : avant = matinal, à partir de = normal.
$tarifRules = [
    'jours_weekend'      => array_values(array_filter(array_map('intval', explode(',', getParam('jours_weekend', '6,7'))))),
    'heure_pointe_debut' => getParam('heure_pointe_debut', '16:00'),
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Réservez votre terrain au <?= APP_FULL_NAME ?>">

    <title>Réserver un terrain - <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>">

    <style>
        body {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            min-height: 100vh;
            padding: 10px;
        }

        .booking-container {
            max-width: 700px;
            margin: 0 auto;
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .booking-header {
            background: linear-gradient(135deg, #E8631A 0%, #d55a17 100%);
            color: white;
            padding: 15px 20px;
            text-align: center;
        }

        .booking-header h1 {
            font-size: 22px;
            margin-bottom: 5px;
        }

        .booking-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .booking-header .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .booking-header .back-link:hover {
            opacity: 1;
        }

        .booking-body {
            padding: 20px;
        }

        /* Steps indicator */
        .booking-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 20px;
            position: relative;
        }

        .booking-steps::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e0e0e0;
            z-index: 0;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .step-number {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e0e0e0;
            color: #666;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 5px;
            transition: all 0.3s;
        }

        .step.active .step-number {
            background: #E8631A;
            color: white;
            transform: scale(1.1);
        }

        .step.completed .step-number {
            background: #1a472a;
            color: white;
        }

        .step-label {
            font-size: 10px;
            color: #666;
            font-weight: 500;
            text-transform: uppercase;
        }

        .step.active .step-label {
            color: #E8631A;
            font-weight: 700;
        }

        /* Step content */
        .step-content {
            display: none;
        }

        .step-content.active {
            display: block;
        }

        .step-title {
            font-size: 16px;
            color: #1a472a;
            margin-bottom: 15px;
            font-weight: 600;
        }

        /* Terrain selection */
        .terrain-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .terrain-option:hover {
            border-color: #1a472a;
            background: #f8f9fa;
        }

        .terrain-option.selected {
            border-color: #E8631A;
            background: #fff8f5;
        }

        .terrain-option input {
            display: none;
        }

        .terrain-icon {
            width: 45px;
            height: 45px;
            background: #1a472a;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .terrain-info {
            flex: 1;
        }

        .terrain-info strong {
            display: block;
            font-size: 15px;
            color: #333;
        }

        .terrain-info small {
            color: #666;
            font-size: 12px;
        }

        .terrain-price {
            text-align: right;
        }

        .terrain-price strong {
            color: #E8631A;
            font-size: 16px;
        }

        .terrain-price small {
            color: #666;
            font-size: 11px;
        }

        /* Form elements */
        .form-group {
            margin-bottom: 12px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
            font-size: 14px;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-control:focus {
            outline: none;
            border-color: #1a472a;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        /* Time slots */
        .time-slots {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
            gap: 8px;
            margin-top: 8px;
        }

        .time-slot {
            padding: 8px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            font-size: 13px;
            transition: all 0.3s;
        }

        .time-slot:hover {
            border-color: #1a472a;
        }

        .time-slot.selected {
            background: #E8631A;
            color: white;
            border-color: #E8631A;
        }

        .time-slot.disabled {
            background: #ffebee;
            color: #c62828;
            cursor: not-allowed;
            text-decoration: line-through;
            border-color: #ffcdd2;
            position: relative;
        }

        .time-slot.disabled::after {
            content: 'Réservé';
            position: absolute;
            bottom: -18px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 9px;
            color: #c62828;
            white-space: nowrap;
        }

        .time-slot.available-after-transition {
            background: #e8f5e9;
            color: #2e7d32;
            border-color: #a5d6a7;
            font-weight: 700;
        }

        .time-slot.available-after-transition:hover {
            background: #c8e6c9;
            border-color: #66bb6a;
        }

        /* Summary */
        .booking-summary {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            border-bottom: 1px solid #e0e0e0;
            font-size: 14px;
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-row.total {
            font-size: 16px;
            font-weight: 700;
            color: #1a472a;
            padding-top: 10px;
            margin-top: 8px;
            border-top: 2px solid #1a472a;
            border-bottom: none;
        }

        .summary-row.total .value {
            color: #E8631A;
        }

        /* Buttons */
        .btn-nav {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: #E8631A;
            color: white;
        }

        .btn-primary:hover {
            background: #d55a17;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        .btn-full {
            width: 100%;
            justify-content: center;
        }

        /* Confirmation */
        .confirmation-box {
            text-align: center;
            padding: 25px 15px;
        }

        .confirmation-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #1a472a, #2d5a3d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
        }

        .confirmation-icon i {
            font-size: 35px;
            color: white;
        }

        .confirmation-title {
            font-size: 22px;
            color: #1a472a;
            margin-bottom: 10px;
        }

        .confirmation-text {
            color: #666;
            margin-bottom: 15px;
            font-size: 14px;
        }

        .ticket-number {
            background: #f8f9fa;
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 700;
            color: #E8631A;
            display: inline-block;
        }

        /* Payment Options */
        .payment-options {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .payment-label {
            display: block;
            font-weight: 600;
            color: #333;
            font-size: 14px;
            margin-bottom: 12px;
        }

        .payment-methods {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 12px;
        }

        .payment-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 15px 10px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            background: #fff;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }

        .payment-btn:not(.disabled):hover {
            border-color: #1a472a;
            background: #f8f9fa;
        }

        .payment-btn.selected {
            border-color: #E8631A;
            background: #fff8f5;
        }

        .payment-btn.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            background: #f5f5f5;
        }

        .payment-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .payment-icon img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 10px;
        }

        .payment-name {
            font-weight: 600;
            font-size: 13px;
            color: #333;
        }

        .payment-badge {
            position: absolute;
            top: -8px;
            right: -5px;
            background: #ff9800;
            color: white;
            font-size: 9px;
            padding: 3px 6px;
            border-radius: 10px;
            font-weight: 600;
            white-space: nowrap;
        }

        .payment-note {
            font-size: 12px;
            color: #888;
            margin: 0;
            display: flex;
            align-items: flex-start;
            gap: 6px;
            line-height: 1.4;
        }

        .payment-note i {
            color: #E8631A;
            margin-top: 2px;
        }

        .payment-note.success {
            color: #2d5a3d;
            background: #e8f5e9;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }

        .payment-note.success i {
            color: #2d5a3d;
        }

        @media (max-width: 600px) {
            body {
                padding: 5px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .booking-header {
                padding: 12px 15px;
            }

            .booking-header h1 {
                font-size: 18px;
            }

            .booking-body {
                padding: 15px;
            }

            .booking-steps {
                margin-bottom: 15px;
            }

            .btn-nav {
                flex-direction: column-reverse;
                margin-top: 15px;
            }

            .btn {
                width: 100%;
                justify-content: center;
                padding: 10px 20px;
            }

            .terrain-option {
                padding: 8px 10px;
            }

            .terrain-icon {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
        }

        /* Notification Modal */
        .notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .notification-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .notification-content {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .notification-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        .notification-icon.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .notification-icon.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .notification-icon.warning {
            background: linear-gradient(135deg, #ffc107, #ffb300);
            color: #333;
        }

        .notification-icon.info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .notification-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a472a;
            margin-bottom: 10px;
        }

        .notification-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .notification-btn {
            background: #E8631A;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .notification-btn:hover {
            background: #d55a17;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <div class="booking-container">
        <div class="booking-header" style="position: relative;">
            <a href="<?= siteUrl('site/') ?>" class="back-link">
                <i class="fas fa-arrow-left"></i> Retour
            </a>
            <h1><i class="fas fa-calendar-plus"></i> Réserver un terrain</h1>
            <p>Réservation simple et rapide</p>
        </div>

        <div class="booking-body">
            <!-- Steps indicator -->
            <div class="booking-steps">
                <div class="step active" data-step="1">
                    <div class="step-number">1</div>
                    <span class="step-label">Terrain</span>
                </div>
                <div class="step" data-step="2">
                    <div class="step-number">2</div>
                    <span class="step-label">Date</span>
                </div>
                <div class="step" data-step="3">
                    <div class="step-number">3</div>
                    <span class="step-label">Infos</span>
                </div>
                <div class="step" data-step="4">
                    <div class="step-number">4</div>
                    <span class="step-label">Confirmation</span>
                </div>
            </div>

            <!-- Step 1: Select terrain -->
            <div class="step-content active" id="step1">
                <h3 class="step-title">Choisissez un terrain</h3>

                <?php
                // Formate une heure "HH:MM" en style FR : "8h", "16h", "23h40"
                $fmtHeure = function ($t) {
                    $h = (int)substr($t, 0, 2);
                    $m = substr($t, 3, 2);
                    return $m === '00' ? $h . 'h' : $h . 'h' . $m;
                };
                $hOuverture = $fmtHeure(OPENING_TIME);
                $hCoupure   = $fmtHeure($tarifRules['heure_pointe_debut']);
                $hFermeture = $fmtHeure(CLOSING_TIME);
                ?>
                <?php foreach ($terrains as $terrain):
                    $prixMatinal = (float)$terrain['prix_heure'];
                    $prixNormal  = (float)($terrain['prix_heure_pointe'] ?: $prixMatinal);
                    $prixWeekend = (float)($terrain['prix_weekend'] ?: 0);
                ?>
                <label class="terrain-option <?= $terrainId == $terrain['id'] ? 'selected' : '' ?>"
                       data-prix-heure="<?= $prixMatinal ?>"
                       data-prix-pointe="<?= $prixNormal ?>"
                       data-prix-weekend="<?= $prixWeekend ?: $prixNormal ?>">
                    <input type="radio" name="terrain_id" value="<?= $terrain['id'] ?>"
                           data-prix="<?= $prixNormal ?>"
                           data-nom="<?= e($terrain['nom']) ?>"
                           <?= $terrainId == $terrain['id'] ? 'checked' : '' ?>>
                    <div class="terrain-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <div class="terrain-info" style="flex:1;">
                        <strong><?= e(terrainTypeLabel($terrain['type']) . ' ' . $terrain['nom']) ?></strong>
                        <div class="mt-1" style="font-size:0.82em; line-height:1.5;">
                            <?php if ($prixMatinal != $prixNormal): ?>
                                <div style="color:#28A745; font-weight:600;">Matin : de <?= $hOuverture ?> à <?= $hCoupure ?> · <?= formatMoney($prixMatinal) ?></div>
                                <div style="color:#E8631A; font-weight:600;">Soir : de <?= $hCoupure ?> à <?= $hFermeture ?> · <?= formatMoney($prixNormal) ?></div>
                            <?php else: ?>
                                <div style="color:#E8631A; font-weight:600;"><?= formatMoney($prixNormal) ?> / heure</div>
                            <?php endif; ?>
                            <?php if ($prixWeekend > 0 && $prixWeekend != $prixNormal): ?>
                                <div style="color:#B8860B; font-weight:600;">Week-end · <?= formatMoney($prixWeekend) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </label>
                <?php endforeach; ?>

                <div class="btn-nav">
                    <div></div>
                    <button type="button" class="btn btn-primary" onclick="goToStep(2)">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 2: Select date and time -->
            <div class="step-content" id="step2">
                <h3 class="step-title">Choisissez la date et l'heure</h3>

                <?php $fermeture = getPeriodeFermeture(); ?>
                <?php if ($fermeture): ?>
                <div style="background:#FFF4E5;border:1px solid #FFD8A8;color:#8A4B00;padding:12px 14px;
                            border-radius:8px;margin-bottom:16px;font-size:0.9em;font-weight:600;">
                    <i class="fas fa-calendar-times me-1"></i>
                    <?= e(messageFermeture()) ?>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label for="booking_date">Date de réservation</label>
                    <input type="date" class="form-control" id="booking_date" min="<?= date('Y-m-d') ?>" required>
                </div>

                <div class="form-group">
                    <label>Heure de début</label>
                    <div class="time-slots" id="timeSlots">
                        <!-- Généré par JS -->
                    </div>
                </div>

                <div class="form-group">
                    <label for="booking_duration">Durée</label>
                    <select class="form-control" id="booking_duration">
                        <option value="1" selected>1 heure</option>
                        <option value="2">2 heures</option>
                        <option value="3">3 heures</option>
                    </select>
                </div>

                <div class="btn-nav">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToStep(3)">
                        Suivant <i class="fas fa-arrow-right"></i>
                    </button>
                </div>
            </div>

            <!-- Step 3: Client info -->
            <div class="step-content" id="step3">
                <h3 class="step-title">Vos informations</h3>

                <div class="form-row">
                    <div class="form-group">
                        <label for="client_prenom">Prénom</label>
                        <input type="text" class="form-control" id="client_prenom" required>
                    </div>
                    <div class="form-group">
                        <label for="client_nom">Nom <span style="color:red;">*</span></label>
                        <input type="text" class="form-control" id="client_nom" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="client_telephone">Téléphone <span style="color:red;">*</span></label>
                    <input type="tel" class="form-control" id="client_telephone" required placeholder="77 XXX XX XX">
                </div>

                <div class="booking-summary" id="bookingSummary">
                    <!-- Rempli par JS -->
                </div>

                <!-- Options de paiement -->
                <div class="payment-options">
                    <label class="payment-label">Mode de paiement</label>

                    <div class="payment-methods">
                        <button type="button" class="payment-btn wave<?= !$waveEnabled ? ' disabled' : '' ?>" id="btnWave" <?= !$waveEnabled ? 'disabled' : '' ?> data-provider="wave">
                            <span class="payment-icon">
                                <img src="<?= asset('images/wave-logo.png') ?>" alt="Wave">
                            </span>
                            <span class="payment-name">Wave</span>
                            <?php if (!$waveEnabled): ?>
                            <span class="payment-badge">Bientôt</span>
                            <?php endif; ?>
                        </button>

                        <button type="button" class="payment-btn om<?= !$omEnabled ? ' disabled' : '' ?>" id="btnOrangeMoney" <?= !$omEnabled ? 'disabled' : '' ?> data-provider="om">
                            <span class="payment-icon">
                                <img src="<?= asset('images/om-logo.png') ?>" alt="Orange Money">
                            </span>
                            <span class="payment-name">Orange Money</span>
                            <?php if (!$omEnabled): ?>
                            <span class="payment-badge">Bientôt</span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <?php if (!$mobilePaymentEnabled): ?>
                    <p class="payment-note">
                        <i class="fas fa-info-circle"></i>
                        Le paiement mobile sera bientôt disponible. En attendant, vous pouvez réserver et via les mobile money ou payer sur place.
                    </p>
                    <?php else: ?>
                    <p class="payment-note success">
                        <i class="fas fa-shield-alt"></i>
                        Paiement sécurisé. Cliquez sur un mode de paiement pour continuer.
                    </p>
                    <?php endif; ?>
                </div>

                <div class="btn-nav">
                    <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
                        <i class="fas fa-arrow-left"></i> Retour
                    </button>
                    <?php /* Bouton "Réserver" retiré : la réservation se fait uniquement via le
                             paiement en ligne (Wave). La fonction submitBooking() est conservée
                             pour un éventuel retour de la réservation sans paiement.
                    <button type="button" class="btn btn-primary" onclick="submitBooking()">
                        <i class="fas fa-check"></i> Réserver
                    </button>
                    */ ?>
                </div>
            </div>

            <!-- Step 4: Confirmation -->
            <div class="step-content" id="step4">
                <div class="confirmation-box">
                    <div class="confirmation-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="confirmation-title">Réservation enregistrée !</h2>
                    <p class="confirmation-text">Votre réservation a été enregistrée avec succès.</p>
                    <div class="ticket-number" id="ticketNumber">N°2024-XXXXX</div>

                    <div class="payment-notice" style="margin-top:15px;padding:12px 15px;background:#fff8e6;border-radius:8px;border-left:3px solid #E8631A;">
                        <h4 style="color:#E8631A;margin-bottom:6px;font-size:14px;">
                            <i class="fas fa-exclamation-circle"></i> Paiement requis
                        </h4>
                        <p style="color:#666;margin-bottom:8px;font-size:13px;line-height:1.4;">
                            Pour confirmer et recevoir votre SMS, payez un <strong>acompte</strong> ou la <strong>totalité</strong>.
                        </p>
                        <p style="color:#666;font-size:12px;margin:0;">
                            <i class="fas fa-phone-alt" style="color:#E8631A;"></i>
                            Contactez-nous ou rendez-vous sur place.
                        </p>
                    </div>

                    <p style="margin-top:12px;color:#888;font-size:12px;">
                        Présentez ce numéro à votre arrivée (10 min avant l'heure).
                    </p>

                    <div class="btn-nav" style="justify-content:center;margin-top:15px;">
                        <a href="<?= siteUrl('site/') ?>" class="btn btn-primary btn-full">
                            <i class="fas fa-home"></i> Retour à l'accueil
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div class="notification-modal" id="notificationModal">
        <div class="notification-content">
            <div class="notification-icon" id="notificationIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="notification-title" id="notificationTitle">Succès</h3>
            <p class="notification-message" id="notificationMessage">Votre action a été effectuée avec succès.</p>
            <button class="notification-btn" onclick="closeNotification()">OK</button>
        </div>
    </div>

    <script>
        // Variables globales
        var currentStep = 1;
        var bookingData = {};

        // Bornes d'exploitation (op-time, en minutes depuis 00:00)
        var OPENING_MIN = <?= OPENING_HOUR_OP * 60 ?>;
        var CLOSING_MIN = <?= opTimeToMinutes(CLOSING_TIME_OP) ?>;

        // Liste des créneaux de départ — identiques à ceux du back-office :
        // getTimeSlots() = pas de 30 min, de l'ouverture jusqu'à la fermeture (CLOSING_TIME_OP).
        var TIME_SLOTS = <?= json_encode(array_map(function($s) {
            $w = opToWall($s);
            return ['op' => $s, 'wall' => $w['wall'], 'next_day' => $w['next_day']];
        }, getTimeSlots())) ?>;

        // Convertit minutes → "HH:MM" en op-time (peut dépasser 24h)
        function minutesToOp(min) {
            var h = Math.floor(min / 60);
            var m = min % 60;
            return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m;
        }
        // Convertit "HH:MM" op-time en minutes
        function opToMinutes(t) {
            var p = t.split(':');
            return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
        }
        // Affichage wall-clock "01:00 (lendemain)" si l'op-time >= 24h
        function opToDisplay(opTime) {
            var p = opTime.split(':');
            var h = parseInt(p[0], 10);
            var m = parseInt(p[1], 10);
            var wall = (h % 24 < 10 ? '0' : '') + (h % 24) + ':' + (m < 10 ? '0' : '') + m;
            return h >= 24 ? wall + ' (lendemain)' : wall;
        }

        // Règles tarifaires (depuis paramètres serveur)
        var TARIF_RULES = <?= json_encode($tarifRules) ?>;

        // Détermine le tarif applicable pour un terrain selon date + heure de début choisis.
        // prix_heure = matinal (avant coupure), prix_heure_pointe = normal (par défaut),
        // prix_weekend = week-end (prioritaire). Coupure = TARIF_RULES.heure_pointe_debut.
        function getEffectiveRate(card, dateStr, heureDebut) {
            var matinal = parseFloat(card.dataset.prixHeure)   || 0;
            var normal  = parseFloat(card.dataset.prixPointe)  || matinal;
            var weekend = parseFloat(card.dataset.prixWeekend) || normal;
            if (!dateStr) return { rate: normal, label: '' };
            var d = new Date(dateStr + 'T12:00:00');
            var iso = d.getDay() === 0 ? 7 : d.getDay();
            if (TARIF_RULES.jours_weekend.indexOf(iso) !== -1 && weekend > 0) {
                return { rate: weekend, label: 'weekend' };
            }
            if (heureDebut) {
                var startMin = opToMinutes(heureDebut);
                var coupure  = opToMinutes(TARIF_RULES.heure_pointe_debut);
                if (startMin < coupure) {
                    return { rate: matinal, label: 'matinal' };
                }
            }
            return { rate: normal, label: '' };
        }

        // Formatage monétaire FR
        function fmtFcfa(n) {
            return new Intl.NumberFormat('fr-FR').format(Math.round(n)) + ' FCFA';
        }

        // Met à jour le prix affiché sur chaque card terrain + le prix unitaire stocké dans bookingData
        function refreshTerrainPrices() {
            var date  = document.getElementById('booking_date').value;
            var heure = bookingData.heure_debut || null;

            document.querySelectorAll('.terrain-option').forEach(function(card) {
                var r = getEffectiveRate(card, date, heure);
                var display = card.querySelector('.terrain-price-display');
                var tag     = card.querySelector('.terrain-price-tag');
                if (display) display.textContent = fmtFcfa(r.rate);
                if (tag) {
                    if (r.label === 'weekend') {
                        tag.innerHTML = '<span class="badge" style="background:#FFC107;color:#000;">Tarif weekend</span>';
                    } else if (r.label === 'matinal') {
                        var coupure = TARIF_RULES.heure_pointe_debut.substring(0,5);
                        tag.innerHTML = '<span class="badge" style="background:#28A745;color:#fff;">Tarif matinal (avant ' + coupure + ')</span>';
                    } else {
                        tag.innerHTML = '';
                    }
                }
                // Synchroniser le data-prix de l'input (utilisé par les étapes suivantes)
                var input = card.querySelector('input[type=radio]');
                if (input) input.setAttribute('data-prix', r.rate);
            });

            // Si un terrain est déjà sélectionné, mettre à jour le tarif unitaire stocké
            var selected = document.querySelector('input[name="terrain_id"]:checked');
            if (selected) {
                bookingData.prix_heure = parseFloat(selected.getAttribute('data-prix')) || bookingData.prix_heure || 0;
            }
        }

        // Initialisation
        document.addEventListener('DOMContentLoaded', function() {
            // Générer les créneaux horaires
            generateTimeSlots();

            // Gestion de sélection des terrains
            var terrainOptions = document.querySelectorAll('.terrain-option');
            for (var i = 0; i < terrainOptions.length; i++) {
                terrainOptions[i].addEventListener('click', function() {
                    // Désélectionner tous
                    for (var j = 0; j < terrainOptions.length; j++) {
                        terrainOptions[j].classList.remove('selected');
                    }
                    // Sélectionner celui-ci
                    this.classList.add('selected');
                    this.querySelector('input').checked = true;
                });
            }

            // Quand la date change, vérifier disponibilité + rafraîchir les prix
            document.getElementById('booking_date').addEventListener('change', function() {
                refreshTerrainPrices();
                if (bookingData.terrain_id) {
                    checkAvailability(bookingData.terrain_id, this.value);
                }
            });

            // Initialiser les prix avec la date pré-remplie (si présente)
            refreshTerrainPrices();
        });

        function generateTimeSlots() {
            var container = document.getElementById('timeSlots');
            container.innerHTML = '';

            for (var i = 0; i < TIME_SLOTS.length; i++) {
                var info = TIME_SLOTS[i];
                var slot = document.createElement('div');
                slot.className = 'time-slot' + (info.next_day ? ' next-day' : '');
                slot.setAttribute('data-time', info.op);
                slot.textContent = info.wall;
                if (info.next_day) {
                    var lbl = document.createElement('small');
                    lbl.style.display = 'block';
                    lbl.style.fontSize = '0.7em';
                    lbl.textContent = 'lendemain';
                    slot.appendChild(lbl);
                }
                slot.onclick = function() {
                    var allSlots = document.querySelectorAll('.time-slot');
                    for (var j = 0; j < allSlots.length; j++) {
                        allSlots[j].classList.remove('selected');
                    }
                    this.classList.add('selected');
                };
                container.appendChild(slot);
            }
        }

        function goToStep(step) {
            // Validation avant de passer à l'étape suivante
            if (step > currentStep) {
                if (currentStep === 1) {
                    var terrain = document.querySelector('input[name="terrain_id"]:checked');
                    if (!terrain) {
                        showNotification('warning', 'Terrain requis', 'Veuillez sélectionner un terrain');
                        return;
                    }
                    bookingData.terrain_id = terrain.value;
                    bookingData.terrain_nom = terrain.getAttribute('data-nom');
                    bookingData.prix_heure = parseFloat(terrain.getAttribute('data-prix'));

                    // Réinitialiser les créneaux et vérifier disponibilité si une date est déjà sélectionnée
                    generateTimeSlots();
                    var dateInput = document.getElementById('booking_date');
                    if (dateInput.value) {
                        checkAvailability(bookingData.terrain_id, dateInput.value);
                    }
                }

                if (currentStep === 2) {
                    var date = document.getElementById('booking_date').value;
                    var selectedTime = document.querySelector('.time-slot.selected');

                    if (!date) {
                        showNotification('warning', 'Date requise', 'Veuillez sélectionner une date');
                        return;
                    }
                    if (!selectedTime) {
                        showNotification('warning', 'Créneau requis', 'Veuillez sélectionner un créneau horaire');
                        return;
                    }

                    // Vérifier si le créneau est disabled
                    if (selectedTime.classList.contains('disabled')) {
                        showNotification('error', 'Créneau indisponible', 'Ce créneau n\'est pas disponible. Veuillez en choisir un autre.');
                        return;
                    }

                    bookingData.date = date;
                    bookingData.heure_debut = selectedTime.getAttribute('data-time');
                    bookingData.duree = parseFloat(document.getElementById('booking_duration').value);

                    // Recalculer les prix avec l'heure choisie (pour appliquer le tarif "pointe" si applicable)
                    refreshTerrainPrices();

                    // Calculer l'heure de fin (op-time, peut dépasser 24h)
                    var finMinutes = opToMinutes(bookingData.heure_debut) + bookingData.duree * 60;
                    if (finMinutes > CLOSING_MIN) {
                        showNotification('warning', 'Durée trop longue',
                            'Cette durée dépasse l\'heure de fermeture (' + opToDisplay(minutesToOp(CLOSING_MIN)) + '). Choisissez un créneau plus tôt ou une durée plus courte.');
                        return;
                    }
                    bookingData.heure_fin = minutesToOp(finMinutes);

                    // Vérifier la disponibilité côté serveur avant de continuer
                    verifyAvailabilityAndProceed(step);
                    return; // On attend la réponse de l'API
                }
            }

            // Cacher toutes les étapes
            var stepContents = document.querySelectorAll('.step-content');
            for (var i = 0; i < stepContents.length; i++) {
                stepContents[i].classList.remove('active');
            }

            // Afficher l'étape demandée
            document.getElementById('step' + step).classList.add('active');

            // Mettre à jour les indicateurs
            var stepIndicators = document.querySelectorAll('.step');
            for (var i = 0; i < stepIndicators.length; i++) {
                stepIndicators[i].classList.remove('active', 'completed');
                if (i + 1 < step) {
                    stepIndicators[i].classList.add('completed');
                }
                if (i + 1 === step) {
                    stepIndicators[i].classList.add('active');
                }
            }

            currentStep = step;
        }

        function updateSummary() {
            var debutDisplay = opToDisplay(bookingData.heure_debut);
            var finDisplay = opToDisplay(bookingData.heure_fin);

            var dateObj = new Date(bookingData.date);
            var options = { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' };
            var dateFormatted = dateObj.toLocaleDateString('fr-FR', options);
            var montantFormatted = new Intl.NumberFormat('fr-FR').format(bookingData.montant);

            document.getElementById('bookingSummary').innerHTML =
                '<div class="summary-row"><span>Terrain</span><strong>' + bookingData.terrain_nom + '</strong></div>' +
                '<div class="summary-row"><span>Date</span><strong>' + dateFormatted + '</strong></div>' +
                '<div class="summary-row"><span>Horaire</span><strong>' + debutDisplay + ' - ' + finDisplay + '</strong></div>' +
                '<div class="summary-row"><span>Durée</span><strong>' + bookingData.duree + 'h</strong></div>' +
                '<div class="summary-row total"><span>Total à payer</span><span class="value">' + montantFormatted + ' FCFA</span></div>';
        }

        function checkAvailability(terrainId, date) {
            fetch('<?= APP_URL ?>/api/reservations.php?action=availability&terrain_id=' + terrainId + '&date=' + date)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    // Réinitialiser les créneaux
                    generateTimeSlots();

                    // Retirer un éventuel message de fermeture précédent
                    var oldNotice = document.getElementById('closureNotice');
                    if (oldNotice) oldNotice.remove();

                    // Période de fermeture exceptionnelle : aucun créneau réservable
                    if (data.ferme) {
                        var slotsFermes = document.querySelectorAll('.time-slot');
                        for (var f = 0; f < slotsFermes.length; f++) {
                            slotsFermes[f].classList.remove('selected');
                            slotsFermes[f].classList.add('disabled');
                        }
                        bookingData.heure_debut = null;

                        var container = document.getElementById('timeSlots');
                        if (container) {
                            var notice = document.createElement('div');
                            notice.id = 'closureNotice';
                            notice.style.cssText = 'background:#FDECEA;border:1px solid #F5C2C7;color:#842029;' +
                                'padding:12px 14px;border-radius:8px;margin-bottom:12px;font-size:0.9em;font-weight:600;';
                            notice.textContent = data.message_fermeture || 'Réservations indisponibles à cette date.';
                            container.parentNode.insertBefore(notice, container);
                        }
                        return;
                    }

                    if (data.creneaux_reserves && data.creneaux_reserves.length > 0) {
                        var tempsTransition = data.temps_transition || 10;
                        var container = document.getElementById('timeSlots');
                        var slotsToAdd = []; // Créneaux de transition à ajouter

                        for (var i = 0; i < data.creneaux_reserves.length; i++) {
                            var creneau = data.creneaux_reserves[i];
                            var debutParts = creneau.heure_debut.split(':');
                            var debutMinutes = parseInt(debutParts[0]) * 60 + parseInt(debutParts[1]);

                            var finParts = creneau.heure_fin.split(':');
                            var finReelMinutes = parseInt(finParts[0]) * 60 + parseInt(finParts[1]);

                            // Calculer l'heure de fin avec transition
                            var finAvecTransitionMinutes = finReelMinutes + tempsTransition;

                            // Parcourir tous les créneaux et marquer ceux qui chevauchent
                            var allSlots = document.querySelectorAll('.time-slot');
                            for (var j = 0; j < allSlots.length; j++) {
                                var slotTime = allSlots[j].getAttribute('data-time');
                                var slotParts = slotTime.split(':');
                                var slotMinutes = parseInt(slotParts[0]) * 60 + parseInt(slotParts[1]);

                                // Si le créneau est dans la plage réservée (de début jusqu'à fin + transition)
                                if (slotMinutes >= debutMinutes && slotMinutes < finAvecTransitionMinutes) {
                                    allSlots[j].classList.add('disabled');
                                    allSlots[j].onclick = null;
                                    allSlots[j].title = 'Créneau réservé';
                                }
                            }

                            // Ajouter un créneau de disponibilité après la transition
                            // (uniquement si dans les heures d'ouverture et pas pile sur l'heure)
                            var finAvecTransitionM = finAvecTransitionMinutes % 60;
                            if (finAvecTransitionMinutes < CLOSING_MIN && finAvecTransitionM > 0) {
                                var transitionSlotTime = minutesToOp(finAvecTransitionMinutes);
                                // Vérifier qu'il n'existe pas déjà
                                if (slotsToAdd.indexOf(transitionSlotTime) === -1) {
                                    slotsToAdd.push({
                                        time: transitionSlotTime,
                                        minutes: finAvecTransitionMinutes
                                    });
                                }
                            }
                        }

                        // Ajouter les créneaux de transition dans l'ordre
                        slotsToAdd.forEach(function(slotInfo) {
                            var existingSlot = document.querySelector('.time-slot[data-time="' + slotInfo.time + '"]');
                            if (!existingSlot) {
                                var slot = document.createElement('div');
                                slot.className = 'time-slot available-after-transition';
                                slot.setAttribute('data-time', slotInfo.time);
                                slot.textContent = opToDisplay(slotInfo.time);
                                slot.title = 'Disponible après préparation';
                                slot.onclick = function() {
                                    var allSlots = document.querySelectorAll('.time-slot');
                                    for (var j = 0; j < allSlots.length; j++) {
                                        allSlots[j].classList.remove('selected');
                                    }
                                    this.classList.add('selected');
                                };

                                // Trouver la bonne position pour insérer
                                var allSlots = container.querySelectorAll('.time-slot');
                                var inserted = false;
                                for (var k = 0; k < allSlots.length; k++) {
                                    var existingTime = allSlots[k].getAttribute('data-time');
                                    var existingParts = existingTime.split(':');
                                    var existingMinutes = parseInt(existingParts[0]) * 60 + parseInt(existingParts[1]);
                                    if (existingMinutes > slotInfo.minutes) {
                                        container.insertBefore(slot, allSlots[k]);
                                        inserted = true;
                                        break;
                                    }
                                }
                                if (!inserted) {
                                    container.appendChild(slot);
                                }
                            }
                        });
                    }
                })
                .catch(function(err) {
                    console.error('Erreur vérification disponibilité:', err);
                });
        }

        // Vérifier la disponibilité côté serveur avant de passer à l'étape 3
        function verifyAvailabilityAndProceed(targetStep) {
            var url = '<?= APP_URL ?>/api/public-booking.php?action=check_availability' +
                      '&terrain_id=' + bookingData.terrain_id +
                      '&date=' + bookingData.date +
                      '&heure_debut=' + bookingData.heure_debut +
                      '&heure_fin=' + bookingData.heure_fin;

            fetch(url)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.available) {
                        // Créneau disponible, calculer le montant et continuer
                        bookingData.montant = data.prix || (bookingData.prix_heure * bookingData.duree);
                        updateSummary();

                        // Passer à l'étape suivante
                        var stepContents = document.querySelectorAll('.step-content');
                        for (var i = 0; i < stepContents.length; i++) {
                            stepContents[i].classList.remove('active');
                        }
                        document.getElementById('step' + targetStep).classList.add('active');

                        var stepIndicators = document.querySelectorAll('.step');
                        for (var i = 0; i < stepIndicators.length; i++) {
                            stepIndicators[i].classList.remove('active', 'completed');
                            if (i + 1 < targetStep) {
                                stepIndicators[i].classList.add('completed');
                            }
                            if (i + 1 === targetStep) {
                                stepIndicators[i].classList.add('active');
                            }
                        }
                        currentStep = targetStep;
                    } else {
                        // Créneau non disponible
                        showNotification('error', 'Créneau indisponible', 'Désolé, ce créneau vient d\'être réservé. Veuillez en choisir un autre.');
                        // Recharger les créneaux disponibles
                        checkAvailability(bookingData.terrain_id, bookingData.date);
                        // Désélectionner le créneau
                        var selectedSlot = document.querySelector('.time-slot.selected');
                        if (selectedSlot) {
                            selectedSlot.classList.remove('selected');
                        }
                    }
                })
                .catch(function(err) {
                    console.error('Erreur vérification:', err);
                    showNotification('error', 'Erreur', 'Erreur lors de la vérification de disponibilité. Veuillez réessayer.');
                });
        }

        function submitBooking() {
            var tel = document.getElementById('client_telephone').value;
            var nom = document.getElementById('client_nom').value;

            if (!tel || !nom) {
                showNotification('warning', 'Champs requis', 'Veuillez remplir les champs obligatoires (nom et téléphone)');
                return;
            }

            // Heure de fin déjà calculée en op-time dans bookingData
            var heureFin = bookingData.heure_fin || minutesToOp(opToMinutes(bookingData.heure_debut) + bookingData.duree * 60);

            var formData = new FormData();
            formData.append('action', 'create_public');
            formData.append('terrain_id', bookingData.terrain_id);
            formData.append('date', bookingData.date);
            formData.append('heure_debut', bookingData.heure_debut);
            formData.append('heure_fin', heureFin);
            formData.append('client_prenom', document.getElementById('client_prenom').value);
            formData.append('client_nom', nom);
            formData.append('client_telephone', tel);

            fetch('<?= APP_URL ?>/api/public-booking.php', {
                method: 'POST',
                body: formData
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.success) {
                    document.getElementById('ticketNumber').textContent = 'N°' + data.numero_ticket;
                    goToStep(4);
                } else {
                    showNotification('error', 'Erreur', data.message || 'Une erreur est survenue');
                }
            })
            .catch(function(err) {
                console.error('Erreur:', err);
                showNotification('error', 'Erreur de connexion', 'Erreur de connexion. Veuillez réessayer.');
            });
        }

        // Initier un paiement mobile
        function initiatePayment(provider) {
            var tel = document.getElementById('client_telephone').value;
            var nom = document.getElementById('client_nom').value;

            if (!tel || !nom) {
                showNotification('warning', 'Champs requis', 'Veuillez remplir les champs obligatoires (nom et téléphone)');
                return;
            }

            // Heure de fin déjà calculée en op-time
            var heureFin = bookingData.heure_fin || minutesToOp(opToMinutes(bookingData.heure_debut) + bookingData.duree * 60);

            // La réservation n'est PAS créée ici : elle le sera automatiquement
            // après confirmation du paiement (webhook). On envoie juste le créneau.
            var paymentData = new FormData();
            paymentData.append('provider', provider);
            paymentData.append('terrain_id', bookingData.terrain_id);
            paymentData.append('date', bookingData.date);
            paymentData.append('heure_debut', bookingData.heure_debut);
            paymentData.append('heure_fin', heureFin);
            paymentData.append('client_prenom', document.getElementById('client_prenom').value);
            paymentData.append('client_nom', nom);
            paymentData.append('client_telephone', tel);
            paymentData.append('client_phone', tel);
            paymentData.append('payment_type', 'full');

            showNotification('info', 'Redirection', 'Redirection vers ' + (provider === 'wave' ? 'Wave' : 'Orange Money') + '...');

            fetch('<?= APP_URL ?>/api/payments/initiate.php', {
                method: 'POST',
                body: paymentData
            })
            .then(function(response) { return response.json(); })
            .then(function(result) {
                closeNotification();
                if (result.success && (result.payment_url || result.checkout_url)) {
                    // Rediriger vers la page de paiement Wave
                    window.location.href = result.payment_url || result.checkout_url;
                } else {
                    showNotification('error', 'Erreur', result.message || 'Erreur lors de l\'initiation du paiement');
                }
            })
            .catch(function(err) {
                console.error('Erreur:', err);
                showNotification('error', 'Erreur', err.message || 'Une erreur est survenue. Veuillez réessayer.');
            });
        }

        // Event listeners pour les boutons de paiement
        document.addEventListener('DOMContentLoaded', function() {
            var btnWave = document.getElementById('btnWave');
            var btnOM = document.getElementById('btnOrangeMoney');

            if (btnWave && !btnWave.disabled) {
                btnWave.addEventListener('click', function() {
                    initiatePayment('wave');
                });
            }

            if (btnOM && !btnOM.disabled) {
                btnOM.addEventListener('click', function() {
                    initiatePayment('om');
                });
            }
        });

        // Notification Modal Functions
        function showNotification(type, title, message) {
            var modal = document.getElementById('notificationModal');
            var icon = document.getElementById('notificationIcon');
            var titleEl = document.getElementById('notificationTitle');
            var messageEl = document.getElementById('notificationMessage');

            // Set icon based on type
            icon.className = 'notification-icon ' + type;
            switch(type) {
                case 'success':
                    icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    icon.innerHTML = '<i class="fas fa-times-circle"></i>';
                    break;
                case 'warning':
                    icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    icon.innerHTML = '<i class="fas fa-info-circle"></i>';
                    break;
            }

            titleEl.textContent = title;
            messageEl.textContent = message;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNotification() {
            var modal = document.getElementById('notificationModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Close modal on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNotification();
            }
        });

        // Close modal on background click
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotification();
            }
        });
    </script>
</body>
</html>
