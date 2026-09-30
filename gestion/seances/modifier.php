<?php
/**
 * Modifier une séance
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Seance.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();
Auth::requirePermission('academie');

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Séance non spécifiée.');
    redirect(url('seances/index.php'));
}

$seance = Seance::getById($id);
if (!$seance) {
    Session::flash('danger', 'Séance introuvable.');
    redirect(url('seances/index.php'));
}

$pageTitle = 'Modifier la séance';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Séances', 'url' => url('seances/index.php')],
    ['label' => 'Modifier']
];

$categories = Seance::getCategories();
$types = Seance::getTypes();
$entraineurs = Entraineur::getAll(['statut' => 'actif']);
$terrains = Database::fetchAll("SELECT id, nom FROM terrains WHERE statut = 'actif' ORDER BY nom");

$errors = [];
$data = $seance;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'date_seance' => post('date_seance'),
            'heure_debut' => post('heure_debut'),
            'heure_fin' => post('heure_fin'),
            'categorie' => post('categorie'),
            'type_seance' => post('type_seance'),
            'terrain_id' => post('terrain_id') ?: null,
            'entraineur_id' => post('entraineur_id') ?: null,
            'description' => sanitize(post('description')),
            'statut' => post('statut')
        ];

        // Validation
        if (empty($data['date_seance'])) {
            $errors[] = 'La date est obligatoire.';
        }
        if (empty($data['heure_debut']) || empty($data['heure_fin'])) {
            $errors[] = 'Les horaires sont obligatoires.';
        }
        if ($data['heure_fin'] <= $data['heure_debut']) {
            $errors[] = 'L\'heure de fin doit être après l\'heure de début.';
        }

        if (empty($errors)) {
            try {
                Seance::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'seances', $id);
                Session::flash('success', 'Séance mise à jour !');
                redirect(url('seances/index.php'));
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
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
                <h5><i class="fas fa-edit me-2"></i>Modifier la séance</h5>
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

                <form method="POST">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="date_seance"
                                   value="<?= e($data['date_seance']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure début <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="heure_debut"
                                   value="<?= substr($data['heure_debut'], 0, 5) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure fin <span class="text-danger">*</span></label>
                            <input type="time" class="form-control" name="heure_fin"
                                   value="<?= substr($data['heure_fin'], 0, 5) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" name="categorie" required>
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $data['categorie'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type de séance</label>
                            <select class="form-select" name="type_seance">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $data['type_seance'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Terrain</label>
                            <select class="form-select" name="terrain_id">
                                <option value="">-- Aucun --</option>
                                <?php foreach ($terrains as $t): ?>
                                    <option value="<?= $t['id'] ?>" <?= $data['terrain_id'] == $t['id'] ? 'selected' : '' ?>>
                                        <?= e($t['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Entraîneur</label>
                            <select class="form-select" name="entraineur_id">
                                <option value="">-- Aucun --</option>
                                <?php foreach ($entraineurs as $e): ?>
                                    <option value="<?= $e['id'] ?>" <?= $data['entraineur_id'] == $e['id'] ? 'selected' : '' ?>>
                                        <?= e($e['nom'] . ' ' . $e['prenom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut">
                                <option value="planifiee" <?= $data['statut'] === 'planifiee' ? 'selected' : '' ?>>Planifiée</option>
                                <option value="en_cours" <?= $data['statut'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                                <option value="terminee" <?= $data['statut'] === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                                <option value="annulee" <?= $data['statut'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                            </select>
                        </div>

                        <div class="col-12">
                            <label class="form-label">Description / Notes</label>
                            <textarea class="form-control" name="description" rows="3"><?= e($data['description']) ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('seances/index.php') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                        <div class="d-flex gap-2">
                            <a href="<?= url('seances/presences.php?id=' . $id) ?>" class="btn btn-info">
                                <i class="fas fa-user-check me-2"></i>Présences
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
