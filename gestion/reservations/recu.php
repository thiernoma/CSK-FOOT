<?php
/**
 * Génération du reçu de réservation (HTML pour impression)
 * Fidèle au ticket N°2026-000584
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    die('Réservation non spécifiée.');
}

$reservation = Reservation::getById($id);
if (!$reservation) {
    die('Réservation introuvable.');
}

// Format d'impression
$format = get('format', 'thermal'); // thermal (80mm) ou a4
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reçu <?= e($reservation['numero_ticket']) ?> - <?= APP_NAME ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
            background: #f5f5f5;
        }

        .receipt-container {
            max-width: <?= $format === 'thermal' ? '80mm' : '210mm' ?>;
            margin: 20px auto;
            background: white;
            padding: <?= $format === 'thermal' ? '10px' : '30px' ?>;
            <?php if ($format === 'a4'): ?>
            border: 1px solid #ddd;
            <?php endif; ?>
        }

        .header {
            text-align: center;
            padding-bottom: 15px;
            border-bottom: 2px dashed #333;
        }

        .logo {
            max-width: <?= $format === 'thermal' ? '60px' : '100px' ?>;
            margin-bottom: 10px;
        }

        .company-name {
            font-size: <?= $format === 'thermal' ? '14px' : '18px' ?>;
            font-weight: bold;
            color: #1A3A6B;
        }

        .slogan {
            font-size: <?= $format === 'thermal' ? '10px' : '12px' ?>;
            color: #E8631A;
            font-style: italic;
        }

        .ticket-number {
            text-align: center;
            padding: 15px 0;
            border-bottom: 1px dashed #333;
        }

        .ticket-number .label {
            font-size: 10px;
            color: #666;
        }

        .ticket-number .number {
            font-size: <?= $format === 'thermal' ? '20px' : '28px' ?>;
            font-weight: bold;
            color: #1A3A6B;
            letter-spacing: 2px;
        }

        .section {
            padding: 10px 0;
            border-bottom: 1px dashed #ccc;
        }

        .section-title {
            font-size: 10px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
        }

        .row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .row .label {
            color: #666;
        }

        .row .value {
            font-weight: bold;
            text-align: right;
        }

        .amount-section {
            background: #f8f8f8;
            padding: 15px 10px;
            margin: 10px -10px;
            text-align: center;
        }

        .amount-label {
            font-size: 10px;
            color: #666;
            margin-bottom: 5px;
        }

        .amount {
            font-size: <?= $format === 'thermal' ? '22px' : '32px' ?>;
            font-weight: bold;
            color: #1A3A6B;
        }

        .payment-status {
            text-align: center;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-weight: bold;
        }

        .status-paye {
            background: #d4edda;
            color: #155724;
        }

        .status-partiel {
            background: #fff3cd;
            color: #856404;
        }

        .status-en_attente {
            background: #f8d7da;
            color: #721c24;
        }

        .footer {
            text-align: center;
            padding-top: 15px;
            border-top: 2px dashed #333;
            margin-top: 10px;
        }

        .footer .datetime {
            font-size: 10px;
            color: #666;
            margin-bottom: 10px;
        }

        .footer .thank-you {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .footer .rules {
            font-size: 9px;
            color: #999;
            margin-top: 10px;
        }

        .qr-code {
            text-align: center;
            margin: 15px 0;
        }

        .qr-code img {
            max-width: 80px;
        }

        /* Impression */
        @media print {
            body {
                background: white;
            }

            .receipt-container {
                margin: 0;
                padding: 5mm;
                max-width: 100%;
                border: none;
            }

            .no-print {
                display: none !important;
            }
        }

        /* Boutons d'action (non imprimés) */
        .actions {
            max-width: <?= $format === 'thermal' ? '80mm' : '210mm' ?>;
            margin: 0 auto 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: #1A3A6B;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Actions -->
    <div class="actions no-print">
        <button onclick="window.print();" class="btn btn-primary">
            <svg width="16" height="16" fill="currentColor" viewBox="0 0 16 16">
                <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1z"/>
                <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2H5zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4V3zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2H5zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1z"/>
            </svg>
            Imprimer
        </button>
        <a href="<?= url('reservations/voir.php?id=' . $id) ?>" class="btn btn-secondary">
            Retour
        </a>
        <a href="<?= url('reservations/recu.php?id=' . $id . '&format=' . ($format === 'thermal' ? 'a4' : 'thermal')) ?>" class="btn btn-secondary">
            Format <?= $format === 'thermal' ? 'A4' : '80mm' ?>
        </a>
    </div>

    <!-- Reçu -->
    <div class="receipt-container">
        <!-- En-tête -->
        <div class="header">
            <div style="font-size: 40px; color: #1A3A6B; margin-bottom: 5px;">⚽</div>
            <div class="company-name"><?= APP_FULL_NAME ?></div>
            <div class="company-name" style="font-size: <?= $format === 'thermal' ? '12px' : '14px' ?>;">
                <?= ACADEMIE_NAME ?>
            </div>
            <div class="slogan"><?= APP_SLOGAN ?></div>
        </div>

        <!-- Numéro de ticket -->
        <div class="ticket-number">
            <div class="label">REÇU DE RÉSERVATION</div>
            <div class="number">N°<?= e($reservation['numero_ticket']) ?></div>
        </div>

        <!-- Informations réservation -->
        <div class="section">
            <div class="section-title">Détails de la réservation</div>
            <div class="row">
                <span class="label">Date</span>
                <span class="value"><?= formatDate($reservation['date_reservation'], 'd-m-Y') ?></span>
            </div>
            <div class="row">
                <span class="label">Heure Début</span>
                <span class="value"><?= formatTimeFull($reservation['heure_debut']) ?></span>
            </div>
            <div class="row">
                <span class="label">Heure Fin</span>
                <span class="value"><?= formatTimeFull($reservation['heure_fin']) ?></span>
            </div>
            <div class="row">
                <span class="label">Terrain</span>
                <span class="value"><?= e($reservation['terrain_nom']) ?></span>
            </div>
            <div class="row">
                <span class="label">Durée</span>
                <span class="value"><?= formatDuration($reservation['duree_heures']) ?></span>
            </div>
        </div>

        <!-- Client -->
        <div class="section">
            <div class="section-title">Client</div>
            <div class="row">
                <span class="label">Réserveur</span>
                <span class="value"><?= e(strtoupper($reservation['client_nom'])) ?></span>
            </div>
            <div class="row">
                <span class="label">Téléphone</span>
                <span class="value"><?= e($reservation['client_telephone']) ?></span>
            </div>
        </div>

        <!-- Montant -->
        <div class="amount-section">
            <div class="amount-label">MONTANT TOTAL</div>
            <div class="amount"><?= formatMoney($reservation['montant']) ?></div>
        </div>

        <!-- Statut paiement -->
        <div class="payment-status status-<?= $reservation['statut_paiement'] ?>">
            <?php if ($reservation['statut_paiement'] === 'paye'): ?>
                ✓ PAYÉ
            <?php elseif ($reservation['statut_paiement'] === 'partiel'): ?>
                ACOMPTE: <?= formatMoney($reservation['montant_paye']) ?>
                <br>
                <small>Reste: <?= formatMoney($reservation['montant'] - $reservation['montant_paye']) ?></small>
            <?php else: ?>
                ⚠ À PAYER
            <?php endif; ?>
        </div>

        <?php if ($reservation['mode_paiement']): ?>
        <div class="section">
            <div class="row">
                <span class="label">Mode de paiement</span>
                <span class="value"><?= ucfirst($reservation['mode_paiement']) ?></span>
            </div>
        </div>
        <?php endif; ?>

        <!-- QR Code pour vérification -->
        <?php if (!empty($reservation['qr_code_token'])): ?>
        <div class="qr-code">
            <img src="<?= Reservation::getQRCodeUrl($reservation, $format === 'thermal' ? 80 : 120) ?>"
                 alt="QR Code"
                 style="max-width: <?= $format === 'thermal' ? '80px' : '120px' ?>;">
            <div style="font-size: 9px; color: #666; margin-top: 5px;">
                Scanner pour vérifier
            </div>
        </div>
        <?php endif; ?>

        <!-- Pied de page -->
        <div class="footer">
            <div class="datetime">
                Enregistré le <?= formatDate($reservation['created_at'], 'd-m-Y H:i:s') ?>
            </div>
            <div class="thank-you">Merci de votre confiance !</div>
            <div class="rules">
                Ce ticket est non remboursable.<br>
                Veuillez vous présenter 10 minutes avant l'heure.<br>
                Tout retard ne sera pas récupéré.
            </div>
        </div>
    </div>

    <script>
        // Auto-print si demandé
        <?php if (get('print')): ?>
        window.onload = function() {
            window.print();
        };
        <?php endif; ?>
    </script>
</body>
</html>
