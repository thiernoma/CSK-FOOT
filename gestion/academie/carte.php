<?php
/**
 * Génération de la carte membre
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MembreAcademie.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    die('Membre non spécifié.');
}

$membre = MembreAcademie::getById($id);
if (!$membre) {
    die('Membre introuvable.');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte membre - <?= e($membre['prenom'] . ' ' . $membre['nom']) ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Arial', sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .card-container {
            max-width: 86mm;
            margin: 0 auto;
        }

        .member-card {
            width: 86mm;
            height: 54mm;
            background: linear-gradient(135deg, #1A3A6B 0%, #2c5282 50%, #1A3A6B 100%);
            border-radius: 10px;
            padding: 4mm;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }

        .member-card::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: rgba(232, 99, 26, 0.3);
            border-radius: 50%;
        }

        .member-card::after {
            content: '';
            position: absolute;
            bottom: -30px;
            left: -30px;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 3mm;
        }

        .logo-section {
            display: flex;
            align-items: center;
            gap: 2mm;
        }

        .logo {
            width: 10mm;
            height: 10mm;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .academie-name {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 0.5px;
        }

        .academie-sub {
            font-size: 6px;
            opacity: 0.8;
        }

        .category-badge {
            background: #E8631A;
            color: white;
            padding: 1mm 3mm;
            border-radius: 3px;
            font-size: 10px;
            font-weight: bold;
        }

        .card-body {
            display: flex;
            gap: 3mm;
        }

        .photo-placeholder {
            width: 20mm;
            height: 25mm;
            background: white;
            border-radius: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1A3A6B;
            font-size: 20px;
            font-weight: bold;
            overflow: hidden;
        }

        .photo-placeholder img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .member-info {
            flex: 1;
        }

        .member-name {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 1mm;
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            font-size: 7px;
            margin-bottom: 0.5mm;
        }

        .info-label {
            width: 15mm;
            opacity: 0.7;
        }

        .info-value {
            font-weight: 500;
        }

        .card-footer {
            position: absolute;
            bottom: 3mm;
            left: 4mm;
            right: 4mm;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }

        .licence-number {
            font-size: 9px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .validity {
            font-size: 6px;
            opacity: 0.7;
        }

        .slogan {
            font-size: 6px;
            font-style: italic;
            color: #E8631A;
        }

        /* Back of card */
        .card-back {
            width: 86mm;
            height: 54mm;
            background: white;
            border-radius: 10px;
            padding: 4mm;
            margin-top: 10mm;
            color: #333;
            position: relative;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .back-header {
            text-align: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 2mm;
            margin-bottom: 2mm;
        }

        .back-header h3 {
            font-size: 8px;
            color: #1A3A6B;
        }

        .rules {
            font-size: 6px;
            line-height: 1.6;
        }

        .rules li {
            margin-bottom: 1mm;
        }

        .contact-info {
            position: absolute;
            bottom: 3mm;
            left: 4mm;
            right: 4mm;
            text-align: center;
            font-size: 6px;
            color: #666;
        }

        /* Print styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .actions {
                display: none !important;
            }

            .card-container {
                page-break-after: avoid;
            }
        }

        .actions {
            max-width: 86mm;
            margin: 20px auto 0;
            display: flex;
            gap: 10px;
        }

        .btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: #1A3A6B;
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }
    </style>
</head>
<body>
    <div class="card-container">
        <!-- Recto -->
        <div class="member-card">
            <div class="card-header">
                <div class="logo-section">
                    <div class="logo">⚽</div>
                    <div>
                        <div class="academie-name"><?= ACADEMIE_NAME ?></div>
                        <div class="academie-sub"><?= APP_FULL_NAME ?></div>
                    </div>
                </div>
                <div class="category-badge"><?= e($membre['categorie']) ?></div>
            </div>

            <div class="card-body">
                <div class="photo-placeholder">
                    <?php if ($membre['photo']): ?>
                        <img src="<?= uploads('membres/' . $membre['photo']) ?>" alt="">
                    <?php else: ?>
                        <?= getInitials($membre['nom'], $membre['prenom']) ?>
                    <?php endif; ?>
                </div>
                <div class="member-info">
                    <div class="member-name"><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></div>
                    <div class="info-row">
                        <span class="info-label">Né(e) le</span>
                        <span class="info-value"><?= formatDate($membre['date_naissance'], 'd/m/Y') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Position</span>
                        <span class="info-value"><?= $membre['position_preferee'] ? ucfirst($membre['position_preferee']) : '-' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Pied fort</span>
                        <span class="info-value"><?= ucfirst($membre['pied_fort']) ?></span>
                    </div>
                    <?php if ($membre['entraineur_nom']): ?>
                    <div class="info-row">
                        <span class="info-label">Entraîneur</span>
                        <span class="info-value"><?= e($membre['entraineur_prenom'] . ' ' . $membre['entraineur_nom']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-footer">
                <div>
                    <div class="licence-number"><?= e($membre['numero_licence']) ?></div>
                    <div class="validity">Valide jusqu'au 30/06/<?= date('Y') + 1 ?></div>
                </div>
                <div class="slogan"><?= APP_SLOGAN ?></div>
            </div>
        </div>

        <!-- Verso -->
        <div class="card-back">
            <div class="back-header">
                <h3>CARTE DE MEMBRE - RÈGLEMENT</h3>
            </div>
            <ul class="rules">
                <li>Cette carte est personnelle et incessible</li>
                <li>Le membre doit la présenter à chaque entraînement</li>
                <li>En cas de perte, prévenir immédiatement le secrétariat</li>
                <li>Le membre s'engage à respecter le règlement intérieur</li>
                <li>Les cotisations doivent être à jour pour participer aux entraînements</li>
                <li>Toute absence doit être signalée à l'avance</li>
            </ul>
            <div class="contact-info">
                <?= APP_FULL_NAME ?> - <?= ACADEMIE_NAME ?><br>
                Dakar, Sénégal | contact@akf-academie.sn
            </div>
        </div>
    </div>

    <div class="actions">
        <button onclick="window.print();" class="btn btn-primary">
            Imprimer
        </button>
        <a href="<?= url('academie/voir.php?id=' . $id) ?>" class="btn btn-secondary">
            Retour
        </a>
    </div>
</body>
</html>
