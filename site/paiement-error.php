<?php
/**
 * Retour de paiement Wave — ÉCHEC / ANNULATION (UX).
 */

require_once __DIR__ . '/../includes/init.php';

$reservationId = isset($_GET['ref']) ? (int)$_GET['ref'] : 0;
$numeroTicket = null;

try {
    $db = Database::getInstance();
    $r = $db->prepare("SELECT numero_ticket FROM reservations WHERE id = ? LIMIT 1");
    $r->execute([$reservationId]);
    $resa = $r->fetch(PDO::FETCH_ASSOC);
    if ($resa) $numeroTicket = $resa['numero_ticket'];
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paiement non abouti - <?= APP_NAME ?></title>
    <style>
        :root { --navy:#01305E; --orange:#E8631A; --red:#DC3545; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Segoe UI',system-ui,sans-serif; background:#F4F6FA; color:#01305E;
               min-height:100vh; display:flex; align-items:center; justify-content:center; padding:20px; }
        .card { background:#fff; border-radius:16px; box-shadow:0 10px 40px rgba(1,48,94,.12);
                max-width:440px; width:100%; padding:40px 32px; text-align:center; }
        .icon { width:88px; height:88px; border-radius:50%; margin:0 auto 24px; display:flex;
                align-items:center; justify-content:center; font-size:44px; color:#fff; background:var(--red); }
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
</head>
<body>
    <div class="card">
        <div class="icon">&#10008;</div>
        <h1>Paiement non abouti</h1>
        <p>Votre paiement Wave n'a pas pu être finalisé ou a été annulé. Aucune somme n'a été débitée.</p>

        <?php if ($numeroTicket): ?>
        <div class="infos">
            <div><span>Réservation</span><span><?= htmlspecialchars($numeroTicket) ?></span></div>
            <div><span>Statut</span><span>Non payé</span></div>
        </div>
        <?php endif; ?>

        <a class="btn orange" href="<?= SITE_URL ?>/site/reserver.php">Réessayer</a>
        <a class="btn" href="<?= SITE_URL ?>">Accueil</a>

        <p class="muted">Besoin d'aide ? Contactez-nous au <?= CONTACT_PHONE_1 ?> ou payez sur place.</p>
    </div>
</body>
</html>
