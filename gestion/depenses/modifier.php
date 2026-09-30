<?php
/**
 * Modifier une dépense
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Depense.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Dépense non spécifiée.');
    redirect(url('depenses/index'));
}

$depense = Depense::getById($id);
if (!$depense) {
    Session::flash('danger', 'Dépense introuvable.');
    redirect(url('depenses/index'));
}

$pageTitle = 'Modifier la dépense';
$errors = [];

// Récupérer les données pour les selects
$categories = Depense::getCategories();
$terrains = Terrain::getAll('actif');

// Traitement du formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'categorie_id' => (int)post('categorie_id'),
        'terrain_id' => post('terrain_id') ? (int)post('terrain_id') : null,
        'libelle' => sanitize(post('libelle')),
        'description' => sanitize(post('description')),
        'montant' => (float)post('montant'),
        'date_depense' => sanitize(post('date_depense')),
        'date_echeance' => post('date_echeance') ? sanitize(post('date_echeance')) : null,
        'statut' => sanitize(post('statut')),
        'mode_paiement' => post('mode_paiement') ? sanitize(post('mode_paiement')) : null,
        'reference_paiement' => sanitize(post('reference_paiement')),
        'fournisseur' => sanitize(post('fournisseur')),
        'recurrence' => sanitize(post('recurrence')),
        'notes' => sanitize(post('notes'))
    ];

    // Validation
    if (empty($data['categorie_id'])) {
        $errors[] = 'La catégorie est obligatoire.';
    }
    if (empty($data['libelle'])) {
        $errors[] = 'Le libellé est obligatoire.';
    }
    if ($data['montant'] <= 0) {
        $errors[] = 'Le montant doit être supérieur à 0.';
    }
    if (empty($data['date_depense'])) {
        $errors[] = 'La date est obligatoire.';
    }

    // Upload pièce jointe
    if (!empty($_FILES['piece_jointe']['name'])) {
        $uploadDir = PUBLIC_PATH . 'uploads/depenses/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $ext = strtolower(pathinfo($_FILES['piece_jointe']['name'], PATHINFO_EXTENSION));
        $allowedExt = ['pdf', 'jpg', 'jpeg', 'png'];

        if (!in_array($ext, $allowedExt)) {
            $errors[] = 'Format de fichier non autorisé (PDF, JPG, PNG uniquement).';
        } elseif ($_FILES['piece_jointe']['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Le fichier est trop volumineux (max 5 Mo).';
        } else {
            $filename = 'dep_' . date('YmdHis') . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($_FILES['piece_jointe']['tmp_name'], $uploadDir . $filename)) {
                // Supprimer l'ancienne pièce jointe si elle existe
                if ($depense['piece_jointe'] && file_exists($uploadDir . $depense['piece_jointe'])) {
                    @unlink($uploadDir . $depense['piece_jointe']);
                }
                $data['piece_jointe'] = $filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            Depense::update($id, $data);
            Session::flash('success', 'Dépense modifiée avec succès.');
            redirect(url('depenses/index'));
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de la mise à jour: ' . $e->getMessage();
        }
    }
} else {
    // Pré-remplir avec les données existantes
    $data = $depense;
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-edit me-2"></i><?= $pageTitle ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= url('depenses/') ?>">Dépenses</a></li>
                            <li class="breadcrumb-item active">Modifier</li>
                        </ol>
                    </nav>
                </div>
                <a href="<?= url('depenses/') ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <form method="POST" enctype="multipart/form-data">
                        <div class="row g-3">
                            <!-- Catégorie -->
                            <div class="col-md-6">
                                <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                                <select name="categorie_id" class="form-select" required>
                                    <option value="">-- Sélectionner --</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= ($data['categorie_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Terrain (optionnel) -->
                            <div class="col-md-6">
                                <label class="form-label">Terrain concerné</label>
                                <select name="terrain_id" class="form-select">
                                    <option value="">-- Dépense générale --</option>
                                    <?php foreach ($terrains as $terrain): ?>
                                    <option value="<?= $terrain['id'] ?>" <?= ($data['terrain_id'] ?? '') == $terrain['id'] ? 'selected' : '' ?>>
                                        <?= e($terrain['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Libellé -->
                            <div class="col-12">
                                <label class="form-label">Libellé <span class="text-danger">*</span></label>
                                <input type="text" name="libelle" class="form-control"
                                       value="<?= e($data['libelle'] ?? '') ?>" required
                                       placeholder="Ex: Facture électricité février 2024">
                            </div>

                            <!-- Montant et Date -->
                            <div class="col-md-4">
                                <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" name="montant" class="form-control"
                                       value="<?= e($data['montant'] ?? '') ?>" required min="0" step="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date dépense <span class="text-danger">*</span></label>
                                <input type="date" name="date_depense" class="form-control"
                                       value="<?= e($data['date_depense'] ?? date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date échéance</label>
                                <input type="date" name="date_echeance" class="form-control"
                                       value="<?= e($data['date_echeance'] ?? '') ?>">
                            </div>

                            <!-- Statut et Récurrence -->
                            <div class="col-md-4">
                                <label class="form-label">Statut</label>
                                <select name="statut" class="form-select">
                                    <option value="payee" <?= ($data['statut'] ?? '') == 'payee' ? 'selected' : '' ?>>Payée</option>
                                    
                                    <option value="annulee" <?= ($data['statut'] ?? '') == 'annulee' ? 'selected' : '' ?>>Annulée</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Mode paiement</label>
                                <select name="mode_paiement" class="form-select">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="especes" <?= ($data['mode_paiement'] ?? '') == 'especes' ? 'selected' : '' ?>>Espèces</option>
                                    <option value="virement" <?= ($data['mode_paiement'] ?? '') == 'virement' ? 'selected' : '' ?>>Virement</option>
                                    <option value="cheque" <?= ($data['mode_paiement'] ?? '') == 'cheque' ? 'selected' : '' ?>>Chèque</option>
                                    <option value="wave" <?= ($data['mode_paiement'] ?? '') == 'wave' ? 'selected' : '' ?>>Wave</option>
                                    <option value="om" <?= ($data['mode_paiement'] ?? '') == 'om' ? 'selected' : '' ?>>Orange Money</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Récurrence</label>
                                <select name="recurrence" class="form-select">
                                    <option value="unique" <?= ($data['recurrence'] ?? '') == 'unique' ? 'selected' : '' ?>>Unique</option>
                                    <option value="mensuel" <?= ($data['recurrence'] ?? '') == 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                                    <option value="trimestriel" <?= ($data['recurrence'] ?? '') == 'trimestriel' ? 'selected' : '' ?>>Trimestriel</option>
                                    <option value="annuel" <?= ($data['recurrence'] ?? '') == 'annuel' ? 'selected' : '' ?>>Annuel</option>
                                </select>
                            </div>

                            <!-- Fournisseur et Référence -->
                            <div class="col-md-6">
                                <label class="form-label">Fournisseur</label>
                                <input type="text" name="fournisseur" class="form-control"
                                       value="<?= e($data['fournisseur'] ?? '') ?>" placeholder="Nom du fournisseur">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Référence paiement</label>
                                <input type="text" name="reference_paiement" class="form-control"
                                       value="<?= e($data['reference_paiement'] ?? '') ?>" placeholder="N° facture, N° chèque...">
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"><?= e($data['description'] ?? '') ?></textarea>
                            </div>

                            <!-- Pièce jointe actuelle -->
                            <?php if (!empty($depense['piece_jointe'])): ?>
                            <div class="col-md-6">
                                <label class="form-label">Pièce jointe actuelle</label>
                                <div class="d-flex align-items-center gap-2">
                                    <a href="<?= uploads('depenses/' . $depense['piece_jointe']) ?>"
                                       target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-file me-1"></i>
                                        Voir le fichier
                                    </a>
                                    <small class="text-muted"><?= e($depense['piece_jointe']) ?></small>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Nouvelle pièce jointe -->
                            <div class="col-md-6">
                                <label class="form-label">
                                    <?= !empty($depense['piece_jointe']) ? 'Remplacer la pièce jointe' : 'Pièce jointe (facture, reçu...)' ?>
                                </label>
                                <input type="file" name="piece_jointe" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG ou PNG (max 5 Mo)</small>
                            </div>

                            <!-- Notes -->
                            <div class="col-12">
                                <label class="form-label">Notes internes</label>
                                <textarea name="notes" class="form-control" rows="2"><?= e($data['notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-between">
                            <div>
                                <small class="text-muted">
                                    Créée le <?= date('d/m/Y H:i', strtotime($depense['created_at'])) ?>
                                    <?php if (!empty($depense['created_by_nom'])): ?>
                                    par <?= e($depense['created_by_nom']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= url('depenses/') ?>" class="btn btn-light">Annuler</a>
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
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
