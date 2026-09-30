<?php
/**
 * Page de vérification de réservation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';

$reservation = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticket = sanitize(post('ticket'));
    $telephone = preg_replace('/\s+/', '', sanitize(post('telephone')));

    if ($ticket && $telephone) {
        $reservation = Database::fetchOne(
            "SELECT r.*, t.nom as terrain_nom, t.photo as terrain_photo,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom, c.telephone
             FROM reservations r
             JOIN terrains t ON r.terrain_id = t.id
             JOIN clients c ON r.client_id = c.id
             WHERE r.numero_ticket = :ticket
             AND c.telephone = :telephone",
            ['ticket' => $ticket, 'telephone' => $telephone]
        );

        if (!$reservation) {
            $error = 'Réservation non trouvée. Vérifiez votre numéro de ticket et votre téléphone.';
        }
    } else {
        $error = 'Veuillez remplir tous les champs.';
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ma réservation - <?= APP_FULL_NAME ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>">

    <style>
        .verify-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            padding: 40px 20px;
        }

        .verify-card {
            background: white;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            max-width: 500px;
            width: 100%;
            overflow: hidden;
        }

        .verify-header {
            background: var(--primary);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .verify-header i {
            font-size: 48px;
            margin-bottom: 15px;
            color: var(--accent);
        }

        .verify-header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .verify-header p {
            opacity: 0.8;
            font-size: 14px;
        }

        .verify-body {
            padding: 30px;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .reservation-details {
            background: var(--light);
            border-radius: 12px;
            padding: 25px;
        }

        .reservation-header {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px dashed #ddd;
        }

        .terrain-icon {
            width: 70px;
            height: 70px;
            background: var(--primary);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .terrain-icon i {
            font-size: 32px;
            color: white;
        }

        .terrain-info h3 {
            font-size: 20px;
            color: var(--primary);
            margin-bottom: 5px;
        }

        .ticket-badge {
            background: var(--accent);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            display: inline-block;
            letter-spacing: 1px;
        }

        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .detail-row:last-child {
            border-bottom: none;
        }

        .detail-label {
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .detail-label i {
            width: 20px;
            color: var(--accent);
        }

        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-confirmee {
            background: #d4edda;
            color: #155724;
        }

        .status-en_attente {
            background: #fff3cd;
            color: #856404;
        }

        .status-annulee {
            background: #f8d7da;
            color: #721c24;
        }

        .status-paye {
            background: #d4edda;
            color: #155724;
        }

        .status-partiel {
            background: #fff3cd;
            color: #856404;
        }

        .amount-box {
            background: var(--primary);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            margin-top: 20px;
        }

        .amount-label {
            font-size: 14px;
            opacity: 0.8;
        }

        .amount-value {
            font-size: 32px;
            font-weight: 800;
            color: var(--accent);
        }

        .back-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--primary);
            font-weight: 600;
        }

        .back-link:hover {
            color: var(--accent);
        }

        .print-btn {
            margin-top: 20px;
            width: 100%;
        }

        @media print {
            .verify-container {
                background: white;
                padding: 0;
            }
            .verify-card {
                box-shadow: none;
            }
            form, .back-link, .print-btn {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="verify-container">
        <div class="verify-card">
            <div class="verify-header">
                <i class="fas fa-ticket-alt"></i>
                <h1>Ma réservation</h1>
                <p>Consultez les détails de votre réservation</p>
            </div>

            <div class="verify-body">
                <?php if (!$reservation): ?>
                    <?php if ($error): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle" style="margin-right:10px;"></i>
                            <?= e($error) ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="form-group">
                            <label>Numéro de ticket</label>
                            <input type="text" class="form-control" name="ticket"
                                   placeholder="Ex: 2024-000123" required
                                   value="<?= e(post('ticket') ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label>Votre téléphone</label>
                            <input type="tel" class="form-control" name="telephone"
                                   placeholder="77 XXX XX XX" required
                                   value="<?= e(post('telephone') ?? '') ?>">
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%;">
                            <i class="fas fa-search"></i> Rechercher
                        </button>
                    </form>

                <?php else: ?>
                    <div class="reservation-details">
                        <div class="reservation-header">
                            <div class="terrain-icon">
                                <i class="fas fa-futbol"></i>
                            </div>
                            <div class="terrain-info">
                                <h3><?= e($reservation['terrain_nom']) ?></h3>
                                <span class="ticket-badge">N°<?= e($reservation['numero_ticket']) ?></span>
                            </div>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-user"></i> Client</span>
                            <span class="detail-value"><?= e($reservation['client_nom']) ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-calendar"></i> Date</span>
                            <span class="detail-value"><?= formatDate($reservation['date_reservation'], 'l d F Y') ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-clock"></i> Horaire</span>
                            <span class="detail-value">
                                <?= formatTime($reservation['heure_debut']) ?> - <?= formatTime($reservation['heure_fin']) ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-hourglass-half"></i> Durée</span>
                            <span class="detail-value"><?= formatDuration($reservation['duree_heures']) ?></span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-info-circle"></i> Statut</span>
                            <span class="status-badge status-<?= $reservation['statut_reservation'] ?>">
                                <?= translateStatus($reservation['statut_reservation']) ?>
                            </span>
                        </div>

                        <div class="detail-row">
                            <span class="detail-label"><i class="fas fa-credit-card"></i> Paiement</span>
                            <span class="status-badge status-<?= $reservation['statut_paiement'] ?>">
                                <?= translateStatus($reservation['statut_paiement']) ?>
                            </span>
                        </div>

                        <div class="amount-box">
                            <div class="amount-label">Montant total</div>
                            <div class="amount-value"><?= formatMoney($reservation['montant']) ?></div>
                            <?php if ($reservation['montant_paye'] > 0 && $reservation['montant_paye'] < $reservation['montant']): ?>
                                <div style="margin-top:10px;font-size:14px;opacity:0.9;">
                                    Payé: <?= formatMoney($reservation['montant_paye']) ?><br>
                                    Reste: <?= formatMoney($reservation['montant'] - $reservation['montant_paye']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button onclick="window.print();" class="btn btn-secondary print-btn">
                        <i class="fas fa-print"></i> Imprimer
                    </button>

                    <a href="<?= siteUrl('site/ma-reservation.php') ?>" class="back-link">
                        <i class="fas fa-arrow-left"></i> Vérifier une autre réservation
                    </a>
                <?php endif; ?>

                <a href="<?= siteUrl('site/index.php') ?>" class="back-link" style="margin-top:30px;">
                    <i class="fas fa-home"></i> Retour à l'accueil
                </a>
            </div>
        </div>
    </div>
</body>
</html>
