<?php
/**
 * Modifier un entraîneur
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();
Auth::requireAdmin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Entraîneur non spécifié.');
    redirect(url('entraineurs/index.php'));
}

$entraineur = Entraineur::getById($id);
if (!$entraineur) {
    Session::flash('danger', 'Entraîneur introuvable.');
    redirect(url('entraineurs/index.php'));
}

$pageTitle = 'Modifier ' . $entraineur['prenom'] . ' ' . $entraineur['nom'];
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Entraîneurs', 'url' => url('entraineurs/index.php')],
    ['label' => $entraineur['prenom'] . ' ' . $entraineur['nom'], 'url' => url('entraineurs/voir.php?id=' . $id)],
    ['label' => 'Modifier']
];

$errors = [];
$data = [
    'nom' => $entraineur['nom'],
    'prenom' => $entraineur['prenom'],
    'telephone' => $entraineur['telephone'],
    'email' => $entraineur['email'] ?? '',
    'specialite' => $entraineur['specialite'] ?? '',
    'categories' => $entraineur['categories'] ?? '',
    'diplomes' => $entraineur['diplomes'] ?? '',
    'date_embauche' => $entraineur['date_embauche'],
    'salaire' => $entraineur['salaire'] ?? '',
    'statut' => $entraineur['statut']
];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'nom' => sanitize(post('nom')),
            'prenom' => sanitize(post('prenom')),
            'telephone' => sanitize(post('telephone')),
            'email' => sanitize(post('email')),
            'specialite' => sanitize(post('specialite')),
            'categories' => sanitize(post('categories')),
            'diplomes' => sanitize(post('diplomes')),
            'date_embauche' => $entraineur['date_embauche'], // Ne pas modifier
            'salaire' => (float)post('salaire') ?: null,
            'statut' => sanitize(post('statut'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire.';
        }

        if (empty($data['prenom'])) {
            $errors[] = 'Le prénom est obligatoire.';
        }

        if (empty($data['telephone'])) {
            $errors[] = 'Le téléphone est obligatoire.';
        }

        if ($data['email'] && !isValidEmail($data['email'])) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }

        // Upload de photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['photo'], 'entraineurs', ['jpg', 'jpeg', 'png']);
            if ($uploadResult['success']) {
                $data['photo'] = $uploadResult['filename'];
                // Supprimer l'ancienne photo si elle existe
                if ($entraineur['photo']) {
                    $oldPhotoPath = UPLOADS_PATH . 'entraineurs/' . $entraineur['photo'];
                    if (file_exists($oldPhotoPath)) {
                        unlink($oldPhotoPath);
                    }
                }
            } else {
                $errors[] = $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            try {
                Entraineur::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'entraineurs', $id);

                Session::flash('success', 'Entraîneur modifié avec succès !');
                redirect(url('entraineurs/voir.php?id=' . $id));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de l\'enregistrement.';
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
                <h5><i class="fas fa-user-edit me-2"></i>Modifier l'entraîneur</h5>
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
                        <!-- Identité -->
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="prenom" name="prenom"
                                   value="<?= e($data['prenom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?= e($data['nom']) ?>" required>
                        </div>

                        <!-- Contact -->
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                   value="<?= e($data['telephone']) ?>" required placeholder="77 XXX XX XX">
                        </div>
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e($data['email']) ?>">
                        </div>

                        <!-- Spécialité -->
                        <div class="col-md-6">
                            <label for="specialite" class="form-label">Spécialité</label>
                            <select class="form-select" id="specialite" name="specialite">
                                <option value="">Sélectionner...</option>
                                <option value="Technique" <?= $data['specialite'] === 'Technique' ? 'selected' : '' ?>>Technique</option>
                                <option value="Physique" <?= $data['specialite'] === 'Physique' ? 'selected' : '' ?>>Préparation physique</option>
                                <option value="Gardiens" <?= $data['specialite'] === 'Gardiens' ? 'selected' : '' ?>>Entraîneur des gardiens</option>
                                <option value="Tactique" <?= $data['specialite'] === 'Tactique' ? 'selected' : '' ?>>Tactique</option>
                                <option value="Formation" <?= $data['specialite'] === 'Formation' ? 'selected' : '' ?>>Formation jeunes</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="categories" class="form-label">Catégories encadrées</label>
                            <input type="text" class="form-control" id="categories" name="categories"
                                   value="<?= e($data['categories']) ?>" placeholder="Ex: U13, U15, U17">
                        </div>

                        <!-- Diplômes -->
                        <div class="col-12">
                            <label for="diplomes" class="form-label">Diplômes / Certifications</label>
                            <input type="text" class="form-control" id="diplomes" name="diplomes"
                                   value="<?= e($data['diplomes']) ?>" placeholder="Ex: CAF A, UEFA B...">
                        </div>

                        <!-- Photo -->
                        <div class="col-md-6">
                            <label for="photo" class="form-label">Photo</label>
                            <?php if ($entraineur['photo']): ?>
                                <div class="mb-2">
                                    <img src="<?= uploads('entraineurs/' . $entraineur['photo']) ?>"
                                         class="rounded" style="width: 80px; height: 80px; object-fit: cover;">
                                    <small class="d-block text-muted">Photo actuelle</small>
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                            <small class="text-muted">Laisser vide pour conserver la photo actuelle</small>
                        </div>

                        <!-- Salaire et Statut -->
                        <div class="col-md-3">
                            <label for="salaire" class="form-label">Salaire (FCFA)</label>
                            <input type="number" class="form-control" id="salaire" name="salaire"
                                   value="<?= $data['salaire'] ?>" min="0" step="1000">
                        </div>
                        <div class="col-md-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif" <?= $data['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="inactif" <?= $data['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('entraineurs/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-accent">
                            <i class="fas fa-check me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
