<?php
/**
 * Modifier un terrain
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

$terrain = Terrain::getById($id);
if (!$terrain) {
    Session::flash('danger', 'Terrain introuvable.');
    redirect(url('terrains/index.php'));
}

$pageTitle = 'Modifier ' . $terrain['nom'];
$breadcrumb = [
    ['label' => 'Terrains', 'url' => url('terrains/index.php')],
    ['label' => $terrain['nom'], 'url' => url('terrains/voir.php?id=' . $id)],
    ['label' => 'Modifier']
];

$errors = [];
$data = $terrain;

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

        // Upload nouvelle photo
        if (!empty($_FILES['photo']['name'])) {
            $upload = uploadFile($_FILES['photo'], 'terrains', 'terrain');
            if ($upload['valid']) {
                // Supprimer l'ancienne photo
                if ($terrain['photo']) {
                    @unlink(UPLOADS_PATH . 'terrains/' . $terrain['photo']);
                }
                $data['photo'] = $upload['filename'];
            } else {
                $errors[] = $upload['message'];
            }
        }

        if (empty($errors)) {
            try {
                Terrain::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'terrains', $id);

                Session::flash('success', 'Terrain mis à jour avec succès !');
                redirect(url('terrains/voir.php?id=' . $id));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la mise à jour.';
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-edit me-2"></i>Modifier le terrain</h5>
                <span class="badge <?= statusBadgeClass($terrain['statut']) ?>"><?= translateStatus($terrain['statut']) ?></span>
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
                                   value="<?= e($data['nom']) ?>" required>
                        </div>

                        <!-- Type -->
                        <div class="col-md-6">
                            <label for="type" class="form-label">Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="type" name="type" required>
                                <option value="grand" <?= $data['type'] === 'grand' ? 'selected' : '' ?>>Grand terrain (11v11)</option>
                                <option value="mini" <?= $data['type'] === 'mini' ? 'selected' : '' ?>>Mini terrain (8v8)</option>
                                <option value="petit_6v6" <?= $data['type'] === 'petit_6v6' ? 'selected' : '' ?>>Petit terrain (6v6)</option>
                                <option value="petit" <?= $data['type'] === 'petit' ? 'selected' : '' ?>>Petit terrain (5v5)</option>
                            </select>
                        </div>

                        <!-- Terrain Parent -->
                        <div class="col-md-6">
                            <label for="parent_id" class="form-label">Terrain parent</label>
                            <select class="form-select" id="parent_id" name="parent_id">
                                <option value="">-- Aucun (terrain indépendant) --</option>
                                <?php
                                $allTerrains = Terrain::getAll('actif');
                                foreach ($allTerrains as $t):
                                    if ($t['id'] != $id): // Ne pas s'afficher soi-même
                                ?>
                                <option value="<?= $t['id'] ?>" <?= ($data['parent_id'] ?? '') == $t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['nom']) ?>
                                </option>
                                <?php endif; endforeach; ?>
                            </select>
                            <div class="form-text">Si ce terrain fait partie d'un terrain plus grand</div>
                        </div>

                        <!-- Capacité -->
                        <div class="col-md-6">
                            <label for="capacite" class="form-label">Capacité (joueurs max)</label>
                            <input type="number" class="form-control" id="capacite" name="capacite"
                                   value="<?= e($data['capacite']) ?>" min="2" max="30">
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

                        <!-- Tarif matinal (réduction avant l'heure de coupure) -->
                        <div class="col-md-4">
                            <label for="prix_heure" class="form-label">Tarif matinal (FCFA) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure" name="prix_heure"
                                       value="<?= e($data['prix_heure']) ?>" required min="0" step="500">
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
                        <div class="col-md-4">
                            <label for="prix_heure_pointe" class="form-label">
                                Tarif normal
                                <i class="fas fa-info-circle text-muted" data-bs-toggle="tooltip"
                                   title="Prix standard appliqué à partir de l'heure de coupure. Configurable dans Paramètres → Tarification."></i>
                            </label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_heure_pointe" name="prix_heure_pointe"
                                       value="<?= e($data['prix_heure_pointe']) ?>" min="0" step="500">
                                <span class="input-group-text">FCFA/h</span>
                            </div>
                            <small class="text-muted">
                                Appliqué à partir de <?= e(getParam('heure_pointe_debut', '16:00')) ?>. Si vide, le tarif matinal s'applique toute la journée.
                                <a href="<?= url('admin/parametres.php') ?>#tarification">configurer</a>
                            </small>
                        </div>

                        <!-- Prix week-end -->
                        <div class="col-md-4">
                            <label for="prix_weekend" class="form-label">Tarif week-end</label>
                            <div class="input-group">
                                <input type="number" class="form-control" id="prix_weekend" name="prix_weekend"
                                       value="<?= e($data['prix_weekend']) ?>" min="0" step="500">
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

                        <!-- Ordre d'affichage -->
                        <div class="col-md-4">
                            <label for="ordre_affichage" class="form-label">Ordre d'affichage</label>
                            <input type="number" class="form-control" id="ordre_affichage" name="ordre_affichage"
                                   value="<?= e($data['ordre_affichage'] ?? 0) ?>" min="0">
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= e($data['description']) ?></textarea>
                        </div>

                        <!-- Équipements -->
                        <div class="col-12">
                            <label for="equipements" class="form-label">Équipements disponibles</label>
                            <textarea class="form-control" id="equipements" name="equipements" rows="2"><?= e($data['equipements']) ?></textarea>
                        </div>

                        <!-- Photo actuelle -->
                        <?php if ($terrain['photo']): ?>
                        <div class="col-md-6">
                            <label class="form-label">Photo actuelle</label>
                            <div class="border rounded p-2">
                                <img src="<?= uploads('terrains/' . $terrain['photo']) ?>" alt="Photo" class="img-fluid rounded" style="max-height: 150px;">
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Nouvelle photo -->
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Nouvelle photo</label>
                            <input type="file" class="form-control" id="photo" name="photo"
                                   accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">Laissez vide pour conserver la photo actuelle</div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Section GPS et Vidéo -->
                    <h6 class="text-primary mb-3"><i class="fas fa-map-marker-alt me-2"></i>Localisation & Médias</h6>

                    <div class="row g-3">
                        <!-- Adresse -->
                        <div class="col-12">
                            <label for="adresse" class="form-label">Adresse complète</label>
                            <input type="text" class="form-control" id="adresse" name="adresse"
                                   value="<?= e($data['adresse'] ?? '') ?>" placeholder="Ex: Route de Ouakam, Dakar, Sénégal">
                        </div>

                        <!-- Coordonnées GPS -->
                        <div class="col-md-4">
                            <label for="latitude" class="form-label">Latitude</label>
                            <input type="number" class="form-control" id="latitude" name="latitude"
                                   value="<?= e($data['latitude'] ?? '') ?>" step="0.00000001" placeholder="Ex: 14.6937">
                        </div>

                        <div class="col-md-4">
                            <label for="longitude" class="form-label">Longitude</label>
                            <input type="number" class="form-control" id="longitude" name="longitude"
                                   value="<?= e($data['longitude'] ?? '') ?>" step="0.00000001" placeholder="Ex: -17.4441">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <button type="button" class="btn btn-outline-primary w-100" onclick="getCurrentLocation()">
                                <i class="fas fa-crosshairs me-2"></i>Ma position
                            </button>
                        </div>

                        <!-- URL vidéo -->
                        <div class="col-md-8">
                            <label for="video_url" class="form-label">URL Vidéo (YouTube)</label>
                            <input type="url" class="form-control" id="video_url" name="video_url"
                                   value="<?= e($data['video_url'] ?? '') ?>" placeholder="https://www.youtube.com/watch?v=...">
                            <div class="form-text">Collez l'URL d'une vidéo YouTube présentant ce terrain</div>
                        </div>

                        <div class="col-md-4">
                            <label for="video_type" class="form-label">Type de vidéo</label>
                            <select class="form-select" id="video_type" name="video_type">
                                <option value="youtube" <?= ($data['video_type'] ?? 'youtube') === 'youtube' ? 'selected' : '' ?>>YouTube</option>
                                <option value="vimeo" <?= ($data['video_type'] ?? '') === 'vimeo' ? 'selected' : '' ?>>Vimeo</option>
                                <option value="upload" <?= ($data['video_type'] ?? '') === 'upload' ? 'selected' : '' ?>>Fichier uploadé</option>
                            </select>
                        </div>

                        <!-- Aperçu carte si coordonnées -->
                        <?php if (!empty($data['latitude']) && !empty($data['longitude'])): ?>
                        <div class="col-12">
                            <label class="form-label">Aperçu sur la carte</label>
                            <div class="border rounded overflow-hidden">
                                <iframe
                                    src="https://www.google.com/maps/embed/v1/place?key=AIzaSyBFw0Qbyq9zTFTd-tUY6dZWTgaQzuU17R8&q=<?= $data['latitude'] ?>,<?= $data['longitude'] ?>&zoom=16"
                                    width="100%"
                                    height="200"
                                    style="border:0;"
                                    allowfullscreen=""
                                    loading="lazy">
                                </iframe>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('terrains/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(position) {
            document.getElementById('latitude').value = position.coords.latitude.toFixed(8);
            document.getElementById('longitude').value = position.coords.longitude.toFixed(8);
        }, function(error) {
            alertModal('Impossible d\'obtenir votre position : ' + error.message, { title: 'Géolocalisation', type: 'warning' });
        });
    } else {
        alertModal('La géolocalisation n\'est pas supportée par ce navigateur.', { title: 'Géolocalisation', type: 'warning' });
    }
}
</script>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
