<?php
/**
 * Page publique pour laisser un avis
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Avis.php';
require_once APP_PATH . 'models/Terrain.php';

$terrains = Terrain::getAll('actif');
$success = false;
$errors = [];

// Terrain présélectionné
$terrainId = get('terrain') ? (int)get('terrain') : null;

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'terrain_id' => (int)post('terrain_id'),
        'nom_client' => sanitize(post('nom')),
        'email_client' => sanitize(post('email')),
        'note' => (int)post('note'),
        'commentaire' => sanitize(post('commentaire')),
        'qualite_terrain' => post('qualite_terrain') ? (int)post('qualite_terrain') : null,
        'proprete' => post('proprete') ? (int)post('proprete') : null,
        'eclairage' => post('eclairage') ? (int)post('eclairage') : null,
        'accueil' => post('accueil') ? (int)post('accueil') : null,
        'rapport_qualite_prix' => post('rapport_qualite_prix') ? (int)post('rapport_qualite_prix') : null,
        'recommande' => post('recommande') ? 1 : 0,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'statut' => 'en_attente'
    ];

    // Validation
    if (empty($data['terrain_id'])) {
        $errors[] = 'Veuillez sélectionner un terrain.';
    }
    if (empty($data['nom_client'])) {
        $errors[] = 'Votre nom est obligatoire.';
    }
    if ($data['note'] < 1 || $data['note'] > 5) {
        $errors[] = 'Veuillez donner une note entre 1 et 5.';
    }

    if (empty($errors)) {
        try {
            Avis::create($data);
            $success = true;
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de l\'envoi de l\'avis.';
        }
    }
}

// Récupérer les avis approuvés pour affichage
$avisApprouves = Avis::getAll(['statut' => 'approuve', 'limit' => 10]);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donnez votre avis - <?= APP_NAME ?></title>

    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>">

    <style>
        body {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .avis-container {
            max-width: 800px;
            margin: 0 auto;
        }

        .avis-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            margin-bottom: 20px;
        }

        .avis-header {
            background: linear-gradient(135deg, #1a472a 0%, #2d5a3d 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .avis-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .avis-body {
            padding: 30px;
        }

        .rating-group {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
        }

        .rating-group input {
            display: none;
        }

        .rating-group label {
            cursor: pointer;
            font-size: 30px;
            color: #ddd;
            transition: color 0.2s;
            padding: 0 5px;
        }

        .rating-group label:hover,
        .rating-group label:hover ~ label,
        .rating-group input:checked ~ label {
            color: #ffc107;
        }

        .rating-small label {
            font-size: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }

        .form-control:focus {
            outline: none;
            border-color: #1a472a;
        }

        .btn-submit {
            background: #E8631A;
            color: white;
            border: none;
            padding: 15px 40px;
            border-radius: 8px;
            font-size: 18px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }

        .btn-submit:hover {
            background: #d55a17;
        }

        .success-box {
            text-align: center;
            padding: 40px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #28a745, #20c997);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .success-icon i {
            font-size: 40px;
            color: white;
        }

        .avis-item {
            border-bottom: 1px solid #eee;
            padding: 20px;
        }

        .avis-item:last-child {
            border-bottom: none;
        }

        .avis-stars {
            color: #ffc107;
        }

        .criteria-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        @media (max-width: 600px) {
            .criteria-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="avis-container">
        <!-- Formulaire d'avis -->
        <div class="avis-card">
            <div class="avis-header">
                <a href="<?= siteUrl('site/') ?>" style="color: white; text-decoration: none; position: absolute; left: 20px; top: 20px;">
                    <i class="fas fa-arrow-left"></i> Retour
                </a>
                <h1><i class="fas fa-star"></i> Donnez votre avis</h1>
                <p>Votre avis nous aide à nous améliorer</p>
            </div>

            <div class="avis-body">
                <?php if ($success): ?>
                <div class="success-box">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2>Merci pour votre avis !</h2>
                    <p>Votre avis sera publié après modération.</p>
                    <a href="<?= siteUrl('site/') ?>" class="btn-submit" style="display: inline-block; width: auto; text-decoration: none; margin-top: 20px;">
                        Retour à l'accueil
                    </a>
                </div>
                <?php else: ?>

                <?php if (!empty($errors)): ?>
                <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <ul style="margin: 0; padding-left: 20px;">
                        <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endif; ?>

                <form method="POST">
                    <!-- Terrain -->
                    <div class="form-group">
                        <label class="form-label">Quel terrain avez-vous utilisé ? <span style="color: red;">*</span></label>
                        <select name="terrain_id" class="form-control" required>
                            <option value="">-- Sélectionner un terrain --</option>
                            <?php foreach ($terrains as $terrain): ?>
                            <option value="<?= $terrain['id'] ?>" <?= $terrainId == $terrain['id'] ? 'selected' : '' ?>>
                                <?= e($terrain['nom']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Note globale -->
                    <div class="form-group">
                        <label class="form-label">Note globale <span style="color: red;">*</span></label>
                        <div class="rating-group">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="note" value="<?= $i ?>" id="note<?= $i ?>" <?= $i == 5 ? 'checked' : '' ?>>
                            <label for="note<?= $i ?>"><i class="fas fa-star"></i></label>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <!-- Critères détaillés -->
                    <div class="form-group">
                        <label class="form-label">Notez les critères (optionnel)</label>
                        <div class="criteria-grid">
                            <div>
                                <small>Qualité du terrain</small>
                                <div class="rating-group rating-small">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="qualite_terrain" value="<?= $i ?>" id="q<?= $i ?>">
                                    <label for="q<?= $i ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <small>Propreté</small>
                                <div class="rating-group rating-small">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="proprete" value="<?= $i ?>" id="p<?= $i ?>">
                                    <label for="p<?= $i ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <small>Éclairage</small>
                                <div class="rating-group rating-small">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="eclairage" value="<?= $i ?>" id="e<?= $i ?>">
                                    <label for="e<?= $i ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                            <div>
                                <small>Accueil</small>
                                <div class="rating-group rating-small">
                                    <?php for ($i = 5; $i >= 1; $i--): ?>
                                    <input type="radio" name="accueil" value="<?= $i ?>" id="a<?= $i ?>">
                                    <label for="a<?= $i ?>"><i class="fas fa-star"></i></label>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Commentaire -->
                    <div class="form-group">
                        <label class="form-label">Votre commentaire</label>
                        <textarea name="commentaire" class="form-control" rows="4"
                                  placeholder="Partagez votre expérience..."></textarea>
                    </div>

                    <!-- Recommandation -->
                    <div class="form-group">
                        <label style="display: flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="recommande" value="1" checked style="margin-right: 10px; width: 20px; height: 20px;">
                            <span>Je recommande ce terrain</span>
                        </label>
                    </div>

                    <!-- Informations personnelles -->
                    <div class="form-group">
                        <label class="form-label">Votre nom <span style="color: red;">*</span></label>
                        <input type="text" name="nom" class="form-control" required placeholder="Jean Dupont">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Votre email (optionnel)</label>
                        <input type="email" name="email" class="form-control" placeholder="jean@example.com">
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-paper-plane me-2"></i> Envoyer mon avis
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>

        <!-- Avis récents -->
        <?php if (!empty($avisApprouves)): ?>
        <div class="avis-card">
            <div class="avis-body">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-comments me-2"></i>Avis récents</h3>

                <?php foreach ($avisApprouves as $a): ?>
                <div class="avis-item">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <strong><?= e($a['client_nom_complet'] ?: $a['nom_client'] ?: 'Anonyme') ?></strong>
                        <span class="avis-stars">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?= $i <= $a['note'] ? '' : '-o text-muted' ?>"></i>
                            <?php endfor; ?>
                        </span>
                    </div>
                    <p style="color: #666; margin-bottom: 5px;">
                        <i class="fas fa-futbol me-1"></i><?= e($a['terrain_nom']) ?>
                    </p>
                    <?php if ($a['commentaire']): ?>
                    <p style="margin-bottom: 0;"><?= nl2br(e($a['commentaire'])) ?></p>
                    <?php endif; ?>

                    <?php if ($a['reponse_admin']): ?>
                    <div style="background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px;">
                        <small style="color: #1a472a;"><strong>Réponse de l'équipe:</strong></small>
                        <p style="margin: 5px 0 0;"><?= nl2br(e($a['reponse_admin'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
