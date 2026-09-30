<?php
/**
 * Nouvelle dépense
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Depense.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Nouvelle Dépense';
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
        'mode_paiement' => post('mode_paiement') ? sanitize(post('mode_paiement')) : null,
        'reference_paiement' => sanitize(post('reference_paiement')),
        'fournisseur' => sanitize(post('fournisseur')),
        'recurrence' => sanitize(post('recurrence')),
        'notes' => sanitize(post('notes')),
        'statut' => 'payee', // Toujours payée par défaut
        'created_by' => Auth::user()['id']
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
                $data['piece_jointe'] = $filename;
            }
        }
    }

    if (empty($errors)) {
        try {
            $id = Depense::create($data);
            Session::flash('success', 'Dépense enregistrée avec succès.');
            redirect(url('depenses/index'));
        } catch (Exception $e) {
            $errors[] = 'Erreur lors de l\'enregistrement: ' . $e->getMessage();
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="container-fluid py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- En-tête -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1"><i class="fas fa-plus-circle me-2"></i><?= $pageTitle ?></h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item"><a href="<?= url('depenses/') ?>">Dépenses</a></li>
                            <li class="breadcrumb-item active">Nouvelle</li>
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
                                    <option value="<?= $cat['id'] ?>" <?= post('categorie_id') == $cat['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $terrain['id'] ?>" <?= post('terrain_id') == $terrain['id'] ? 'selected' : '' ?>>
                                        <?= e($terrain['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Libellé -->
                            <div class="col-12">
                                <label class="form-label">Libellé <span class="text-danger">*</span></label>
                                <input type="text" name="libelle" class="form-control"
                                       value="<?= e(post('libelle')) ?>" required
                                       placeholder="Ex: Facture électricité février 2024">
                            </div>

                            <!-- Montant et Date -->
                            <div class="col-md-4">
                                <label class="form-label">Montant (FCFA) <span class="text-danger">*</span></label>
                                <input type="number" name="montant" class="form-control"
                                       value="<?= e(post('montant')) ?>" required min="0" step="1">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date dépense <span class="text-danger">*</span></label>
                                <input type="date" name="date_depense" readonly="readonly" class="form-control"
                                       value="<?= e(post('date_depense') ?: date('Y-m-d')) ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date échéance</label>
                                <input type="date" name="date_echeance" class="form-control"
                                       value="<?= e(post('date_echeance')) ?>">
                            </div>

                            <!-- Récurrence -->
                            <div class="col-md-4">
                                <label class="form-label">Mode paiement</label>
                                <select name="mode_paiement" class="form-select">
                                    <option value="">-- Sélectionner --</option>
                                    <option value="especes" <?= post('mode_paiement') == 'especes' ? 'selected' : '' ?>>Espèces</option>
                                    <option value="virement" <?= post('mode_paiement') == 'virement' ? 'selected' : '' ?>>Virement</option>
                                    <option value="cheque" <?= post('mode_paiement') == 'cheque' ? 'selected' : '' ?>>Chèque</option>
                                    <option value="wave" <?= post('mode_paiement') == 'wave' ? 'selected' : '' ?>>Wave</option>
                                    <option value="om" <?= post('mode_paiement') == 'om' ? 'selected' : '' ?>>Orange Money</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Récurrence</label>
                                <select name="recurrence" class="form-select">
                                    <option value="unique" <?= post('recurrence') == 'unique' ? 'selected' : '' ?>>Unique</option>
                                    <option value="mensuel" <?= post('recurrence') == 'mensuel' ? 'selected' : '' ?>>Mensuel</option>
                                    <option value="trimestriel" <?= post('recurrence') == 'trimestriel' ? 'selected' : '' ?>>Trimestriel</option>
                                    <option value="annuel" <?= post('recurrence') == 'annuel' ? 'selected' : '' ?>>Annuel</option>
                                </select>
                            </div>

                            <!-- Fournisseur et Référence -->
                            <div class="col-md-6">
                                <label class="form-label">Fournisseur</label>
                                <input type="text" name="fournisseur" class="form-control"
                                       value="<?= e(post('fournisseur')) ?>" placeholder="Nom du fournisseur">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Référence paiement</label>
                                <input type="text" name="reference_paiement" class="form-control"
                                       value="<?= e(post('reference_paiement')) ?>" placeholder="N° facture, N° chèque...">
                            </div>

                            <!-- Description -->
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="2"><?= e(post('description')) ?></textarea>
                            </div>

                            <!-- Pièce jointe -->
                            <div class="col-md-6">
                                <label class="form-label">Pièce jointe (facture, reçu...)</label>
                                <input type="file" name="piece_jointe" class="form-control"
                                       accept=".pdf,.jpg,.jpeg,.png">
                                <small class="text-muted">PDF, JPG ou PNG (max 5 Mo)</small>
                            </div>

                            <!-- Notes -->
                            <div class="col-md-6">
                                <label class="form-label">Notes internes</label>
                                <textarea name="notes" class="form-control" rows="2"><?= e(post('notes')) ?></textarea>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= url('depenses/') ?>" class="btn btn-light">Annuler</a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
