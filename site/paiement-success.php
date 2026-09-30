<?php
/**
 * Retour de paiement Wave — SUCCÈS (UX).
 * La confirmation qui fait foi vient du webhook (api/payments/wave-callback.php).
 * Cette page re-vérifie le statut auprès de Wave pour informer le client.
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'services/WavePayment.php';

$ref = isset($_GET['ref']) ? sanitize($_GET['ref']) : '';

$statut = 'pending';   // pending | paye | inconnu
$montant = null;
$numeroTicket = null;

try {
    $db = Database::getInstance();

    // Retrouver la transaction via la référence (RSV-x ou BOOK-xxxx), sinon via reservation_id numérique
    if ($ref !== '') {
        $stmt = $db->prepare("SELECT * FROM paiements_mobile WHERE client_reference = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$ref]);
        $txn = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$txn && ctype_digit($ref)) {
            $stmt = $db->prepare("SELECT * FROM paiements_mobile WHERE reservation_id = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([(int)$ref]);
            $txn = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $txn = null;
    }

    if ($txn) {
        $montant = $txn['montant'];

        // Vérification en direct auprès de Wave
        if (WavePayment::isEnabled()) {
            $wave = new WavePayment();
            $check = $wave->checkPaymentStatus($txn['transaction_id']);
            if (!empty($check['is_paid'])) {
                $statut = 'paye';
            }
        }
        if ($statut !== 'paye' && $txn['statut'] === 'succeeded') {
            $statut = 'paye';
        }

        // Numéro de ticket si la réservation a déjà été créée par le webhook
        if (!empty($txn['reservation_id'])) {
            $r = $db->prepare("SELECT numero_ticket, montant_paye FROM reservations WHERE id = ? LIMIT 1");
            $r->execute([(int)$txn['reservation_id']]);
            $resa = $r->fetch(PDO::FETCH_ASSOC);
            if ($resa) {
                $numeroTicket = $resa['numero_ticket'];
                if ($statut === 'paye') $montant = $resa['montant_paye'];
            }
        }
    }
} catch (Exception $e) {
    $statut = 'inconnu';
}

$estPaye = ($statut === 'paye');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement <?= $estPaye ? 'confirmé' : 'en cours' ?> - <?= APP_NAME ?></title>
    <style>
        :root { --navy:#01305E; --orange:#E8631A; --green:#28A745; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:#F4F6FA; color:#01305E;
               min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(1,48,94,.12);
                max-width:440px; width:100%; padding:40px 32px; text-align:center; }
        .icon { width:88px; height:88px; border-radius:50%; margin:0 auto 24px; display:flex;
                align-items:center; justify-content:center; font-size:44px; color:#fff; }
        .icon.ok { background:var(--green); }
        .icon.wait { background:var(--orange); }
        h1 { font-size:22px; margin-bottom:12px; }
        p { color:#5a6b82; line-height:1.6; margin-bottom:8px; }
        .infos { background:#F4F6FA; border-radius:12px; padding:16px; margin:24px 0; text-align:left; }
        .infos div { display:flex; justify-content:space-between; padding:6px 0; }
        .infos span:last-child { font-weight:700; }
        .btn { display:inline-block; margin-top:16px; background:var(--navy); color:#fff; text-decoration:none;
               padding:13px 28px; border-radius:10px; font-weight:600; }
        .btn.orange { background:var(--orange); }
        .muted { font-size:13px; color:#8a97a8; margin-top:20px; }
    </style>
    <?php // Continuer à rafraîchir tant que le paiement n'est pas confirmé OU que le ticket n'est pas encore généré
    if (!$estPaye || !$numeroTicket): ?><meta http-equiv="refresh" content="6"><?php endif; ?>
</head>
<body>
    <div class="card">
        <?php if ($estPaye): ?>
            <div class="icon ok">&#10004;</div>
            <h1>Paiement confirmé !</h1>
            <p>Votre réservation est confirmée. Merci d'avoir choisi <?= APP_FULL_NAME ?>.</p>
        <?php else: ?>
            <div class="icon wait">&#8987;</div>
            <h1>Paiement en cours de confirmation</h1>
            <p>Nous validons votre paiement Wave. Cette page se met à jour automatiquement…</p>
        <?php endif; ?>

        <div class="infos">
            <?php if ($numeroTicket): ?>
            <div><span>Réservation</span><span><?= htmlspecialchars($numeroTicket) ?></span></div>
            <?php endif; ?>
            <?php if ($montant !== null): ?>
            <div><span><?= $estPaye ? 'Montant payé' : 'Montant' ?></span><span><?= formatMoney($montant) ?></span></div>
            <?php endif; ?>
            <div><span>Mode</span><span>Wave</span></div>
        </div>

        <?php if ($numeroTicket): ?>
        <a class="btn" href="<?= SITE_URL ?>/site/ticket.php?ref=<?= urlencode($ref) ?>" target="_blank" rel="noopener">Télécharger le ticket</a>
        <?php endif; ?>
        <a class="btn orange" href="<?= SITE_URL ?>">Accueil</a>

        <p class="muted">Un reçu vous sera communiqué. En cas de doute, contactez-nous au <?= CONTACT_PHONE_1 ?>.</p>
    </div>
</body>
</html>
