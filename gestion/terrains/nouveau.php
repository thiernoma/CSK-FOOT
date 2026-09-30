<?php
/**
 * Ajouter un nouveau terrain
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireRole('super_admin', 'directeur');

$pageTitle = 'Nouveau terrain';
$breadcrumb = [
    ['label' => 'Terrains', 'url' => url('terrains/index.php')],
    ['label' => 'Nouveau']
];

$errors = [];
$data = [
    'nom' => '',
    'type' => 'mini',
    'capacite' => '',
    'prix_heure' => '',
    'prix_heure_pointe' => '',
    'prix_weekend' => '',
    'description' => '',
    'equipements' => '',
    'statut' => 'actif'
];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        // Récupérer les données
        $data = [
            'nom' => sanitize(post('nom')),
            'type' => sanitize(post('type')),
            'capacite' => post('capacite') ? (int)post('capacite') : null,
            'prix_heure' => (float)post('prix_heure'),
            'prix_heure_pointe' => post('prix_heure_pointe') ? (float)post('prix_heure_pointe') : null,
            'prix_weekend' => post('prix_weekend') ? (float)post('prix_weekend') : null,
            'description' => sanitize(post('description')),
            'equipements' => sanitize(post('equipements')),
            'statut' => sanitize(post('statut'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom du terrain est obligatoire.';
        }

        if ($data['prix_heure'] <= 0) {
            $errors[] = 'Le tarif horaire doit être supérieur à 0.';
        }

        // Upload photo
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadFile($_FILES['photo'], 'terrains', 'terrain');
            if ($upload['valid']) {
                $data['photo'] = $upload['filename'];
            } else {
                $errors[] = $upload['message'];
            }
        }

        // Créer le terrain
        if (empty($errors)) {
            try {
                $terrainId = Terrain::create($data);
                Auth::logAction(Auth::id(), 'create', 'terrains', $terrainId);

                Session::flash('success', 'Terrain créé avec succès !');
                redirect(url('terrains/index.php'));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la création du terrain.';
                if (DEV_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-futbol me-2"></i>Nouveau terrain</h5>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <!-- Nom -->
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom du terrain <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?= e($data['nom']) ?>" required placeholder="Ex: Mini Terrain">
                        </div>

                        <!-- Type -->
                        <div class="col-md-6">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="grand" <?= $data['type'] === 'grand' ? 'selected' : '' ?>>Grand terrain (11v11)</option>
                                <option value="mini" <?= $data['type'] === 'mini' ? 'selected' : '' ?>>Mini terrain (8v8)</option>
                                <option value="mini" <?= $data['type'] === 'mini' ? 'selected' : '' ?>>Mini terrain (7v7)</option>
                                <option value="petit_6v6" <?= $data['type'] === 'petit_6v6' ? 'selected' : '' ?>>Petit terrain (6v6)</option>
                                <option value="petit" <?= $data['type'] === 'petit' ? 'selected' : '' ?>>Petit terrain (5v5)</option>
                            </select>
                        </div>

                        <!-- Capacité -->
                        <div class="col-md-4">
                            <label for="capacite" class="form-label">Capacité (joueurs max)</label>
                            <input type="number" class="form-control" id="capacite" name="capacite"
                                   value="<?= e($data['capacite']) ?>" min="2" max="30" placeholder="Ex: 10">
                        </div>

                        <!-- Tarif matinal (réduction avant l'heure de coupure) -->
                        <div class="col-md-4">
                            <label for="prix_heure" class="form-label">Tarif matinal (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure" name="prix_heure"
                                       value="<?= e($data['prix_heure']) ?>" required min="0" step="500" placeholder="20000">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                            <small class="text-muted">Prix réduit du matin (avant <?= e(getParam('heure_pointe_debut', '16:00')) ?>)</small>
                        </div>

                        <!-- Statut -->
                        <div class="col-md-4">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif" <?= $data['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="maintenance" <?= $data['statut'] === 'maintenance' ? 'selected' : '' ?>>En maintenance</option>
                                <option value="ferme" <?= $data['statut'] === 'ferme' ? 'selected' : '' ?>>Fermé</option>
                            </select>
                        </div>

                        <!-- Tarif normal (prix par défaut) -->
                        <div class="col-md-6">
                            <label for="prix_heure_pointe" class="form-label">
                                Tarif normal
                                <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip"
                                   title="Prix standard appliqué à partir de l'heure de coupure. Configurable dans Paramètres → Tarification."></i>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure_pointe" name="prix_heure_pointe"
                                       value="<?= e($data['prix_heure_pointe']) ?>" min="0" step="500" placeholder="Optionnel">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                            <small class="text-muted">
                                Prix standard appliqué à partir de <?= e(getParam('heure_pointe_debut', '16:00')) ?>. Si vide, le tarif matinal s'applique toute la journée.
                                <a href="<?= url('admin/parametres.php') ?>">configurer</a>
                            </small>
                        </div>

                        <!-- Prix week-end -->
                        <div class="col-md-6">
                            <label for="prix_weekend" class="form-label">Tarif week-end</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_weekend" name="prix_weekend"
                                       value="<?= e($data['prix_weekend']) ?>" min="0" step="500" placeholder="Optionnel">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                            <small class="text-muted">
                                <?php
                                $jw = explode(',', getParam('jours_weekend', '6,7'));
                                $lbl = [1=>'Lun',2=>'Mar',3=>'Mer',4=>'Jeu',5=>'Ven',6=>'Sam',7=>'Dim'];
                                $names = array_map(fn($d) => $lbl[(int)$d] ?? '', $jw);
                                ?>
                                Appliqué : <?= e(implode(', ', array_filter($names))) ?>
                            </small>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Description du terrain, caractéristiques..."><?= e($data['description']) ?></textarea>
                        </div>

                        <!-- Équipements -->
                        <div class="col-12">
                            <label for="equipements" class="form-label">Équipements disponibles</label>
                            <textarea class="form-control" id="equipements" name="equipements" rows="2"
                                      placeholder="Vestiaires, éclairage, douches, parking..."><?= e($data['equipements']) ?></textarea>
                        </div>

                        <!-- Photo -->
                        <div class="col-12">
                            <label for="photo" class="form-label">Photo du terrain</label>
                            <input type="file" class="form-control" id="photo" name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Formats acceptés: JPG, PNG, WebP. Taille max: 5 Mo</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('terrains/index.php') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer le terrain
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
