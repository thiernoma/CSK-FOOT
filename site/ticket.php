<?php
/**
 * Ticket de réservation — version PUBLIQUE (téléchargeable / imprimable).
 * Autorisé par la référence de paiement (client_reference) reçue sur la page de succès.
 * Le "téléchargement PDF" passe par la boîte d'impression du navigateur (Enregistrer en PDF).
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';

$ref = isset($_GET['ref']) ? sanitize($_GET['ref']) : '';

$reservation = null;
if ($ref !== '') {
    try {
        $db = Database::getInstance();
        // Retrouver la transaction via la référence (BOOK-xxxx ou RSV-id)
        $stmt = $db->prepare("SELECT reservation_id FROM paiements_mobile WHERE client_reference = ? AND reservation_id IS NOT NULL ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ref]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Repli : ref numérique = id de réservation
        $resId = $row['reservation_id'] ?? (ctype_digit($ref) ? (int)$ref : 0);
        if ($resId) {
            $reservation = Reservation::getById((int)$resId);
        }
    } catch (Exception $e) {
        $reservation = null;
    }
}

if (!$reservation) {
    http_response_code(404);
    ?>
    <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8">
    <title>Ticket introuvable - <?= APP_NAME ?></title>
    <style>body{font-family:system-ui,sans-serif;text-align:center;padding:60px 20px;color:#01305E}</style>
    </head><body>
        <h2>Ticket introuvable</h2>
        <p>Ce ticket n'est pas disponible. Vérifiez le lien ou contactez-nous.</p>
        <p><a href="<?= SITE_URL ?>">Retour à l'accueil</a></p>
    </body></html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket <?= e($reservation['numero_ticket']) ?> - <?= APP_NAME ?></title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Courier New', monospace; font-size:13px; line-height:1.4; background:#f0f2f5; }
        .receipt-container { max-width:80mm; margin:20px auto; background:#fff; padding:14px; box-shadow:0 4px 20px rgba(0,0,0,.08); border-radius:6px; }
        .header { text-align:center; padding-bottom:15px; border-bottom:2px dashed #333; }
        .company-name { font-size:15px; font-weight:bold; color:#1A3A6B; }
        .slogan { font-size:10px; color:#E8631A; font-style:italic; }
        .ticket-number { text-align:center; padding:15px 0; border-bottom:1px dashed #333; }
        .ticket-number .label { font-size:10px; color:#666; }
        .ticket-number .number { font-size:22px; font-weight:bold; color:#1A3A6B; letter-spacing:2px; }
        .section { padding:10px 0; border-bottom:1px dashed #ccc; }
        .section-title { font-size:10px; color:#999; text-transform:uppercase; margin-bottom:5px; }
        .row { display:flex; justify-content:space-between; margin:3px 0; }
        .row .label { color:#666; }
        .row .value { font-weight:bold; text-align:right; }
        .amount-section { background:#f8f8f8; padding:15px 10px; margin:10px -14px; text-align:center; }
        .amount-label { font-size:10px; color:#666; margin-bottom:5px; }
        .amount { font-size:24px; font-weight:bold; color:#1A3A6B; }
        .payment-status { text-align:center; padding:10px; margin:10px 0; border-radius:5px; font-weight:bold; }
        .status-paye { background:#d4edda; color:#155724; }
        .status-partiel { background:#fff3cd; color:#856404; }
        .status-en_attente { background:#f8d7da; color:#721c24; }
        .qr-code { text-align:center; margin:15px 0; }
        .footer { text-align:center; padding-top:15px; border-top:2px dashed #333; margin-top:10px; }
        .footer .datetime { font-size:10px; color:#666; margin-bottom:10px; }
        .footer .thank-you { font-weight:bold; margin-bottom:5px; }
        .footer .rules { font-size:9px; color:#999; margin-top:10px; }
        .actions { max-width:80mm; margin:0 auto 24px; display:flex; gap:10px; justify-content:center; }
        .btn { padding:12px 20px; border:none; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600;
               text-decoration:none; display:inline-flex; align-items:center; gap:8px; font-family:system-ui,sans-serif; }
        .btn-primary { background:#01305E; color:#fff; }
        .btn-secondary { background:#E8631A; color:#fff; }
        @media print {
            body { background:#fff; }
            .receipt-container { margin:0; padding:5mm; max-width:100%; box-shadow:none; border-radius:0; }
            .no-print { display:none !important; }
        }
    </style>
</head>
<body>
    <div class="actions no-print">
        <button onclick="window.print();" class="btn btn-primary">⬇ Télécharger / Imprimer</button>
        <a href="<?= SITE_URL ?>" class="btn btn-secondary">Accueil</a>
    </div>

    <div class="receipt-container">
        <div class="header">
            <div style="font-size:40px; color:#1A3A6B;">⚽</div>
            <div class="company-name"><?= APP_FULL_NAME ?></div>
            <div class="slogan"><?= APP_SLOGAN ?></div>
        </div>

        <div class="ticket-number">
            <div class="label">TICKET DE RÉSERVATION</div>
            <div class="number">N°<?= e($reservation['numero_ticket']) ?></div>
        </div>

        <div class="section">
            <div class="section-title">Détails de la réservation</div>
            <div class="row"><span class="label">Date</span><span class="value"><?= formatDate($reservation['date_reservation'], 'd-m-Y') ?></span></div>
            <div class="row"><span class="label">Heure Début</span><span class="value"><?= formatTimeFull($reservation['heure_debut']) ?></span></div>
            <div class="row"><span class="label">Heure Fin</span><span class="value"><?= formatTimeFull($reservation['heure_fin']) ?></span></div>
            <div class="row"><span class="label">Terrain</span><span class="value"><?= e($reservation['terrain_nom']) ?></span></div>
            <div class="row"><span class="label">Durée</span><span class="value"><?= formatDuration($reservation['duree_heures']) ?></span></div>
        </div>

        <div class="section">
            <div class="section-title">Client</div>
            <div class="row"><span class="label">Réserveur</span><span class="value"><?= e(strtoupper($reservation['client_nom'] ?? '')) ?></span></div>
            <div class="row"><span class="label">Téléphone</span><span class="value"><?= e($reservation['client_telephone'] ?? '') ?></span></div>
        </div>

        <div class="amount-section">
            <div class="amount-label">MONTANT TOTAL</div>
            <div class="amount"><?= formatMoney($reservation['montant']) ?></div>
        </div>

        <div class="payment-status status-<?= $reservation['statut_paiement'] ?>">
            <?php if ($reservation['statut_paiement'] === 'paye'): ?>
                ✓ PAYÉ
            <?php elseif ($reservation['statut_paiement'] === 'partiel'): ?>
                ACOMPTE: <?= formatMoney($reservation['montant_paye']) ?><br>
                <small>Reste: <?= formatMoney($reservation['montant'] - $reservation['montant_paye']) ?></small>
            <?php else: ?>
                ⚠ À PAYER
            <?php endif; ?>
        </div>

        <?php if (!empty($reservation['mode_paiement'])): ?>
        <div class="section">
            <div class="row"><span class="label">Mode de paiement</span><span class="value"><?= ucfirst($reservation['mode_paiement']) ?></span></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($reservation['qr_code_token'])): ?>
        <div class="qr-code">
            <img src="<?= Reservation::getQRCodeUrl($reservation, 120) ?>" alt="QR Code" style="max-width:120px;">
            <div style="font-size:9px; color:#666; margin-top:5px;">Scanner pour vérifier</div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <div class="datetime">Enregistré le <?= formatDate($reservation['created_at'], 'd-m-Y H:i:s') ?></div>
            <div class="thank-you">Merci de votre confiance !</div>
            <div class="rules">
                Veuillez vous présenter 10 minutes avant l'heure.<br>
                Présentez ce ticket (ou le QR code) à l'accueil.
            </div>
        </div>
    </div>
</body>
</html>
