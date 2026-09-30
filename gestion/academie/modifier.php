<?php
/**
 * Modifier un membre de l'académie
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MembreAcademie.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Membre non spécifié.');
    redirect(url('academie/index.php'));
}

$membre = MembreAcademie::getById($id);
if (!$membre) {
    Session::flash('danger', 'Membre introuvable.');
    redirect(url('academie/index.php'));
}

$pageTitle = 'Modifier ' . $membre['prenom'] . ' ' . $membre['nom'];
$breadcrumb = [
    ['label' => 'Académie', 'url' => url('academie/index.php')],
    ['label' => $membre['prenom'] . ' ' . $membre['nom'], 'url' => url('academie/voir.php?id=' . $id)],
    ['label' => 'Modifier']
];

$errors = [];
$data = $membre;

// Liste des entraîneurs
$entraineurs = Entraineur::getAll('actif');

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'nom' => sanitize(post('nom')),
            'prenom' => sanitize(post('prenom')),
            'date_naissance' => sanitize(post('date_naissance')),
            'categorie' => sanitize(post('categorie')),
            'nom_parent' => sanitize(post('nom_parent')),
            'telephone_parent' => sanitize(post('telephone_parent')),
            'telephone_parent_alt' => sanitize(post('telephone_parent_alt')),
            'email_parent' => sanitize(post('email_parent')),
            'adresse' => sanitize(post('adresse')),
            'ecole' => sanitize(post('ecole')),
            'niveau_scolaire' => sanitize(post('niveau_scolaire')),
            'position_preferee' => sanitize(post('position_preferee')),
            'pied_fort' => sanitize(post('pied_fort')),
            'entraineur_principal_id' => (int)post('entraineur_principal_id') ?: null,
            'cotisation_mensuelle' => (float)post('cotisation_mensuelle'),
            'notes_medicales' => sanitize(post('notes_medicales')),
            'personne_urgence' => sanitize(post('personne_urgence')),
            'telephone_urgence' => sanitize(post('telephone_urgence')),
            'statut' => sanitize(post('statut'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire.';
        }

        if (empty($data['prenom'])) {
            $errors[] = 'Le prénom est obligatoire.';
        }

        if (empty($data['date_naissance'])) {
            $errors[] = 'La date de naissance est obligatoire.';
        }

        if (empty($data['telephone_parent'])) {
            $errors[] = 'Le téléphone du parent est obligatoire.';
        }

        if ($data['email_parent'] && !isValidEmail($data['email_parent'])) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }

        // Upload de photo
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $uploadResult = uploadFile($_FILES['photo'], 'membres', ['jpg', 'jpeg', 'png']);
            if ($uploadResult['success']) {
                $data['photo'] = $uploadResult['filename'];
            } else {
                $errors[] = $uploadResult['error'];
            }
        }

        if (empty($errors)) {
            try {
                MembreAcademie::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'membres_academie', $id);

                Session::flash('success', 'Membre mis à jour avec succès !');
                redirect(url('academie/voir.php?id=' . $id));
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
    <div class="col-xl-10">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-user-edit me-2"></i>Modifier le membre</h5>
                <span class="badge <?= statusBadgeClass($membre['statut']) ?>"><?= translateStatus($membre['statut']) ?></span>
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

                    <!-- Section Identité -->
                    <div class="border rounded p-3 mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-child me-2"></i>Identité du joueur</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="prenom" class="form-label">Prénom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="prenom" name="prenom"
                                       value="<?= e($data['prenom']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nom" name="nom"
                                       value="<?= e($data['nom']) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label for="date_naissance" class="form-label">Date de naissance <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="date_naissance" name="date_naissance"
                                       value="<?= e($data['date_naissance']) ?>" required>
                            </div>
                            <div class="col-md-3">
                                <label for="categorie" class="form-label">Catégorie</label>
                                <select class="form-select" id="categorie" name="categorie">
                                    <?php foreach (CATEGORIES_AGE as $cat => $range): ?>
                                        <option value="<?= $cat ?>" <?= $data['categorie'] === $cat ? 'selected' : '' ?>>
                                            <?= $cat ?> (<?= $range['min'] ?>-<?= $range['max'] ?> ans)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="position_preferee" class="form-label">Position</label>
                                <select class="form-select" id="position_preferee" name="position_preferee">
                                    <option value="">Non définie</option>
                                    <option value="gardien" <?= $data['position_preferee'] === 'gardien' ? 'selected' : '' ?>>Gardien</option>
                                    <option value="defenseur" <?= $data['position_preferee'] === 'defenseur' ? 'selected' : '' ?>>Défenseur</option>
                                    <option value="milieu" <?= $data['position_preferee'] === 'milieu' ? 'selected' : '' ?>>Milieu</option>
                                    <option value="attaquant" <?= $data['position_preferee'] === 'attaquant' ? 'selected' : '' ?>>Attaquant</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="pied_fort" class="form-label">Pied fort</label>
                                <select class="form-select" id="pied_fort" name="pied_fort">
                                    <option value="droit" <?= $data['pied_fort'] === 'droit' ? 'selected' : '' ?>>Droit</option>
                                    <option value="gauche" <?= $data['pied_fort'] === 'gauche' ? 'selected' : '' ?>>Gauche</option>
                                    <option value="ambidextre" <?= $data['pied_fort'] === 'ambidextre' ? 'selected' : '' ?>>Ambidextre</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="statut" class="form-label">Statut</label>
                                <select class="form-select" id="statut" name="statut">
                                    <option value="actif" <?= $data['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                    <option value="suspendu" <?= $data['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                                    <option value="inactif" <?= $data['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="photo" class="form-label">Photo</label>
                                <input type="file" class="form-control" id="photo" name="photo" accept="image/*">
                                <?php if ($data['photo']): ?>
                                    <small class="text-muted">Photo actuelle: <?= e($data['photo']) ?></small>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label for="entraineur_principal_id" class="form-label">Entraîneur assigné</label>
                                <select class="form-select" id="entraineur_principal_id" name="entraineur_principal_id">
                                    <option value="">Non assigné</option>
                                    <?php foreach ($entraineurs as $e): ?>
                                        <option value="<?= $e['id'] ?>" <?= $data['entraineur_principal_id'] == $e['id'] ? 'selected' : '' ?>>
                                            <?= e($e['prenom'] . ' ' . $e['nom']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Section Parent/Tuteur -->
                    <div class="border rounded p-3 mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-user-tie me-2"></i>Parent / Tuteur</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nom_parent" class="form-label">Nom du parent</label>
                                <input type="text" class="form-control" id="nom_parent" name="nom_parent"
                                       value="<?= e($data['nom_parent']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="telephone_parent" class="form-label">Téléphone <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control" id="telephone_parent" name="telephone_parent"
                                       value="<?= e($data['telephone_parent']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telephone_parent_alt" class="form-label">Téléphone secondaire</label>
                                <input type="tel" class="form-control" id="telephone_parent_alt" name="telephone_parent_alt"
                                       value="<?= e($data['telephone_parent_alt']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="email_parent" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email_parent" name="email_parent"
                                       value="<?= e($data['email_parent']) ?>">
                            </div>
                            <div class="col-12">
                                <label for="adresse" class="form-label">Adresse</label>
                                <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= e($data['adresse']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Section Scolarité -->
                    <div class="border rounded p-3 mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-graduation-cap me-2"></i>Scolarité</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="ecole" class="form-label">Établissement</label>
                                <input type="text" class="form-control" id="ecole" name="ecole"
                                       value="<?= e($data['ecole']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="niveau_scolaire" class="form-label">Niveau / Classe</label>
                                <input type="text" class="form-control" id="niveau_scolaire" name="niveau_scolaire"
                                       value="<?= e($data['niveau_scolaire']) ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Section Cotisation -->
                    <div class="border rounded p-3 mb-4">
                        <h6 class="text-primary mb-3"><i class="fas fa-money-bill me-2"></i>Cotisation</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cotisation_mensuelle" class="form-label">Cotisation mensuelle (FCFA)</label>
                                <input type="number" class="form-control" id="cotisation_mensuelle" name="cotisation_mensuelle"
                                       value="<?= $data['cotisation_mensuelle'] ?>" min="0" step="500">
                            </div>
                            <div class="col-md-6">
                                <div class="bg-light rounded p-3">
                                    <small class="text-muted">Informations inscription</small>
                                    <div>Date: <?= formatDate($data['date_inscription'], 'd/m/Y') ?></div>
                                    <div>Frais: <?= formatMoney($data['montant_inscription']) ?></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Section Urgence -->
                    <div class="border rounded p-3 mb-4">
                        <h6 class="text-danger mb-3"><i class="fas fa-first-aid me-2"></i>Urgence & Médical</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="personne_urgence" class="form-label">Personne à contacter</label>
                                <input type="text" class="form-control" id="personne_urgence" name="personne_urgence"
                                       value="<?= e($data['personne_urgence']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="telephone_urgence" class="form-label">Téléphone d'urgence</label>
                                <input type="tel" class="form-control" id="telephone_urgence" name="telephone_urgence"
                                       value="<?= e($data['telephone_urgence']) ?>">
                            </div>
                            <div class="col-12">
                                <label for="notes_medicales" class="form-label">Notes médicales</label>
                                <textarea class="form-control" id="notes_medicales" name="notes_medicales"
                                          rows="2"><?= e($data['notes_medicales']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <hr>

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('academie/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
