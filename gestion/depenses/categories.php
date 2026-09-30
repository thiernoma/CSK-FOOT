<?php
/**
 * Gestion des catégories de dépenses (admin)
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/CategorieDepense.php';

Auth::requireLogin();

if (!Auth::isAdmin()) {
    Session::flash('danger', 'Accès refusé.');
    redirect(url('depenses/index.php'));
}

$pageTitle = 'Catégories de dépenses';
$breadcrumb = [
    ['label' => 'Dépenses', 'url' => url('depenses/index.php')],
    ['label' => 'Catégories']
];

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');

        if ($action === 'create') {
            $result = CategorieDepense::create([
                'nom' => sanitize(post('nom')),
                'description' => sanitize(post('description')),
                'icone' => sanitize(post('icone')),
                'couleur' => post('couleur'),
                'ordre' => (int)post('ordre'),
                'actif' => post('actif') ? 1 : 0
            ]);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'create', 'categories_depenses', $result['id']);
                Session::flash('success', $result['message']);
                redirect(url('depenses/categories.php'));
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'update') {
            $id = (int)post('id');
            $result = CategorieDepense::update($id, [
                'nom' => sanitize(post('nom')),
                'description' => sanitize(post('description')),
                'icone' => sanitize(post('icone')),
                'couleur' => post('couleur'),
                'ordre' => (int)post('ordre'),
                'actif' => post('actif') ? 1 : 0
            ]);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'update', 'categories_depenses', $id);
                Session::flash('success', $result['message']);
                redirect(url('depenses/categories.php'));
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'delete') {
            $id = (int)post('id');
            $result = CategorieDepense::delete($id);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'delete', 'categories_depenses', $id);
                Session::flash('success', $result['message']);
            } else {
                Session::flash('danger', $result['message']);
            }
            redirect(url('depenses/categories.php'));
        }
    }
}

$editId = (int)get('edit');
$editCat = $editId ? CategorieDepense::getById($editId) : null;
$categories = CategorieDepense::getAll();

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-tags me-2"></i>Catégories de dépenses</h4>
        <p class="text-muted mb-0">Configurez les types de dépenses disponibles</p>
    </div>
    <a href="<?= url('depenses/index.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Retour
    </a>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Liste -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header"><h6 class="mb-0">Liste des catégories</h6></div>
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-4 text-muted">Aucune catégorie</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Catégorie</th>
                                <th>Description</th>
                                <th>Statut</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $c): ?>
                            <tr>
                                <td><?= $c['ordre'] ?></td>
                                <td>
                                    <span class="badge me-2" style="background-color: <?= e($c['couleur']) ?>;">
                                        <?php if (!empty($c['icone'])): ?>
                                            <i class="fas <?= e($c['icone']) ?>"></i>
                                        <?php else: ?>
                                            &nbsp;
                                        <?php endif; ?>
                                    </span>
                                    <strong><?= e($c['nom']) ?></strong>
                                    <?php if ($c['systeme']): ?>
                                        <span class="badge bg-info ms-1" title="Catégorie système">SYS</span>
                                    <?php endif; ?>
                                </td>
                                <td><small class="text-muted"><?= e($c['description'] ?? '-') ?></small></td>
                                <td>
                                    <?php if ($c['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('depenses/categories.php?edit=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$c['systeme']): ?>
                                    <form method="POST" class="d-inline"
                                          data-confirm="Supprimer cette catégorie de dépense ?"
                                          data-confirm-title="Supprimer la catégorie"
                                          data-confirm-text="<i class='fas fa-trash me-1'></i> Supprimer"
                                          data-confirm-class="btn-danger"
                                          data-confirm-icon="fa-folder-minus"
                                          data-confirm-icon-class="text-danger">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Formulaire -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0">
                    <i class="fas fa-<?= $editCat ? 'edit' : 'plus' ?> me-2"></i>
                    <?= $editCat ? 'Modifier' : 'Nouvelle catégorie' ?>
                </h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="<?= $editCat ? 'update' : 'create' ?>">
                    <?php if ($editCat): ?>
                        <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">Nom <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nom"
                               value="<?= e($editCat['nom'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" rows="2"><?= e($editCat['description'] ?? '') ?></textarea>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Icône (FontAwesome)</label>
                            <input type="text" class="form-control" name="icone"
                                   value="<?= e($editCat['icone'] ?? 'fa-file-invoice') ?>" placeholder="fa-file-invoice">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Couleur</label>
                            <input type="color" class="form-control form-control-color w-100" name="couleur"
                                   value="<?= e($editCat['couleur'] ?? '#6c757d') ?>">
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label">Ordre</label>
                            <input type="number" class="form-control" name="ordre"
                                   value="<?= $editCat['ordre'] ?? 0 ?>" min="0">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="actif" name="actif" value="1"
                                       <?= ($editCat['actif'] ?? 1) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="actif">Actif</label>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i><?= $editCat ? 'Mettre à jour' : 'Créer' ?>
                        </button>
                        <?php if ($editCat): ?>
                        <a href="<?= url('depenses/categories.php') ?>" class="btn btn-outline-secondary">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
