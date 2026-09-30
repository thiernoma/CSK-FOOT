<?php
/**
 * Dupliquer un terrain
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireRole('super_admin', 'directeur');

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Terrain non spécifié.');
    redirect(url('terrains/index.php'));
}

$terrainSource = Terrain::getById($id);
if (!$terrainSource) {
    Session::flash('danger', 'Terrain introuvable.');
    redirect(url('terrains/index.php'));
}

$pageTitle = 'Dupliquer ' . $terrainSource['nom'];
$breadcrumb = [
    ['label' => 'Terrains', 'url' => url('terrains/index.php')],
    ['label' => $terrainSource['nom'], 'url' => url('terrains/voir.php?id=' . $id)],
    ['label' => 'Dupliquer']
];

$errors = [];

// Pré-remplir avec les données du terrain source
$data = $terrainSource;
$data['nom'] = $terrainSource['nom'] . ' (copie)';

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'nom' => sanitize(post('nom')),
            'type' => sanitize(post('type')),
            'capacite' => post('capacite') ? (int)post('capacite') : null,
            'nb_joueurs_min' => post('nb_joueurs_min') ? (int)post('nb_joueurs_min') : null,
            'nb_joueurs_max' => post('nb_joueurs_max') ? (int)post('nb_joueurs_max') : null,
            'parent_id' => post('parent_id') ? (int)post('parent_id') : null,
            'prix_heure' => (float)post('prix_heure'),
            'prix_heure_pointe' => post('prix_heure_pointe') ? (float)post('prix_heure_pointe') : null,
            'prix_weekend' => post('prix_weekend') ? (float)post('prix_weekend') : null,
            'description' => sanitize(post('description')),
            'equipements' => sanitize(post('equipements')),
            'statut' => sanitize(post('statut')),
            'ordre_affichage' => (int)post('ordre_affichage'),
            'latitude' => post('latitude') ? (float)post('latitude') : null,
            'longitude' => post('longitude') ? (float)post('longitude') : null,
            'adresse' => sanitize(post('adresse')),
            'video_url' => sanitize(post('video_url')),
            'video_type' => sanitize(post('video_type'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom du terrain est obligatoire.';
        }

        if ($data['prix_heure'] <= 0) {
            $errors[] = 'Le tarif horaire doit être supérieur à 0.';
        }

        // Vérifier que le nom n'existe pas déjà
        $existingTerrain = Database::fetchOne(
            "SELECT id FROM terrains WHERE nom = :nom",
            ['nom' => $data['nom']]
        );
        if ($existingTerrain) {
            $errors[] = 'Un terrain avec ce nom existe déjà.';
        }

        // Upload photo si fournie
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadFile($_FILES['photo'], 'terrains', 'terrain');
            if ($upload['valid']) {
                $data['photo'] = $upload['filename'];
            } else {
                $errors[] = $upload['message'];
            }
        }

        if (empty($errors)) {
            try {
                $newId = Terrain::create($data);
                Auth::logAction(Auth::id(), 'create', 'terrains', $newId, 'Dupliqué depuis terrain #' . $id);

                Session::flash('success', 'Terrain dupliqué avec succès !');
                redirect(url('terrains/voir.php?id=' . $newId));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la création du terrain.';
                if (DEV_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

// Récupérer tous les terrains pour le select parent
$allTerrains = Terrain::getAll('actif');

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-copy me-2"></i>Dupliquer le terrain</h5>
                <span class="badge bg-info">Copie de <?= e($terrainSource['nom']) ?></span>
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

                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    Vous allez créer une copie du terrain <strong><?= e($terrainSource['nom']) ?></strong>.
                    Modifiez les informations ci-dessous selon vos besoins.
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <!-- Nom -->
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom du terrain <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?= e($data['nom']) ?>" required>
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

                        <!-- Terrain Parent -->
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label">Terrain parent</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Aucun (terrain indépendant) --</option>
                                <?php foreach ($allTerrains as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= ($data['parent_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['nom']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">Si ce terrain fait partie d'un terrain plus grand</div>
                        </div>

                        <!-- Capacité -->
                        <div class="col-md-6">
                            <label for="capacite" class="form-label">Capacité (joueurs max)</label>
                            <input type="number" class="form-control" id="capacite" name="capacite"
                                   value="<?= e($data['capacite'] ?? '') ?>" min="2" max="30">
                        </div>

                        <!-- Nombre de joueurs -->
                        <div class="col-md-3">
                            <label for="nb_joueurs_min" class="form-label">Joueurs min</label>
                            <input type="number" class="form-control" id="nb_joueurs_min" name="nb_joueurs_min"
                                   value="<?= e($data['nb_joueurs_min'] ?? '') ?>" min="1" max="22">
                        </div>

                        <div class="col-md-3">
                            <label for="nb_joueurs_max" class="form-label">Joueurs max</label>
                            <input type="number" class="form-control" id="nb_joueurs_max" name="nb_joueurs_max"
                                   value="<?= e($data['nb_joueurs_max'] ?? '') ?>" min="1" max="22">
                        </div>

                        <!-- Prix horaire -->
                        <div class="col-md-4">
                            <label for="prix_heure" class="form-label">Tarif matinal (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure" name="prix_heure"
                                       value="<?= e($data['prix_heure']) ?>" required min="0" step="500">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                        </div>

                        <!-- Statut -->
                        <div class="col-md-4">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif" <?= ($data['statut'] ?? 'actif') === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="maintenance" <?= ($data['statut'] ?? '') === 'maintenance' ? 'selected' : '' ?>>En maintenance</option>
                                <option value="ferme" <?= ($data['statut'] ?? '') === 'ferme' ? 'selected' : '' ?>>Fermé</option>
                            </select>
                        </div>

                        <!-- Ordre d'affichage -->
                        <div class="col-md-4">
                            <label for="ordre_affichage" class="form-label">Ordre d'affichage</label>
                            <input type="number" class="form-control" id="ordre_affichage" name="ordre_affichage"
                                   value="<?= e($data['ordre_affichage'] ?? 0) ?>" min="0">
                        </div>

                        <!-- Tarif normal (prix par défaut) -->
                        <div class="col-md-4">
                            <label for="prix_heure_pointe" class="form-label">Tarif normal</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure_pointe" name="prix_heure_pointe"
                                       value="<?= e($data['prix_heure_pointe'] ?? '') ?>" min="0" step="500">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                        </div>

                        <!-- Prix week-end -->
                        <div class="col-md-4">
                            <label for="prix_weekend" class="form-label">Tarif week-end</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_weekend" name="prix_weekend"
                                       value="<?= e($data['prix_weekend'] ?? '') ?>" min="0" step="500">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= e($data['description'] ?? '') ?></textarea>
                        </div>

                        <!-- Équipements -->
                        <div class="col-12">
                            <label for="equipements" class="form-label">Équipements disponibles</label>
                            <textarea class="form-control" id="equipements" name="equipements" rows="2"><?= e($data['equipements'] ?? '') ?></textarea>
                        </div>

                        <!-- Photo -->
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Photo du terrain</label>
                            <input type="file" class="form-control" id="photo" name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">La photo du terrain source n'est pas copiée automatiquement</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('terrains/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-info">
                            <i class="fas fa-copy me-2"></i>Créer la copie
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
