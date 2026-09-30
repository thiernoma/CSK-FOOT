<?php
/**
 * Gestion des catégories d'encaissement (admin)
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/CategorieEncaissement.php';

Auth::requireLogin();

if (!Auth::isAdmin()) {
    Session::flash('danger', 'Accès refusé.');
    redirect(url('paiements/index.php'));
}

$pageTitle = 'Catégories d\'encaissement';
$breadcrumb = [
    ['label' => 'Paiements', 'url' => url('paiements/index.php')],
    ['label' => 'Catégories']
];

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');

        if ($action === 'create') {
            $result = CategorieEncaissement::create([
                'code' => post('code'),
                'libelle' => post('libelle'),
                'icone' => post('icone'),
                'couleur' => post('couleur'),
                'ordre' => (int)post('ordre'),
                'actif' => post('actif') ? 1 : 0
            ]);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'create', 'categories_encaissement', $result['id']);
                Session::flash('success', $result['message']);
                redirect(url('paiements/categories.php'));
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'update') {
            $id = (int)post('id');
            $result = CategorieEncaissement::update($id, [
                'code' => post('code'),
                'libelle' => post('libelle'),
                'icone' => post('icone'),
                'couleur' => post('couleur'),
                'ordre' => (int)post('ordre'),
                'actif' => post('actif') ? 1 : 0
            ]);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'update', 'categories_encaissement', $id);
                Session::flash('success', $result['message']);
                redirect(url('paiements/categories.php'));
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'delete') {
            $id = (int)post('id');
            $result = CategorieEncaissement::delete($id);
            if ($result['success']) {
                Auth::logAction(Auth::id(), 'delete', 'categories_encaissement', $id);
                Session::flash('success', $result['message']);
            } else {
                Session::flash('danger', $result['message']);
            }
            redirect(url('paiements/categories.php'));
        }
    }
}

$editId = (int)get('edit');
$editCat = $editId ? CategorieEncaissement::getById($editId) : null;
$categories = CategorieEncaissement::getAll();

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-tags me-2"></i>Catégories d'encaissement</h4>
        <p class="text-muted mb-0">Configurez les types d'encaissement utilisables par les caissiers</p>
    </div>
    <a href="<?= url('paiements/index.php') ?>" class="btn btn-outline-secondary">
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
                                <th>Libellé</th>
                                <th>Code</th>
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
                                    <strong><?= e($c['libelle']) ?></strong>
                                    <?php if ($c['systeme']): ?>
                                        <span class="badge bg-info ms-1" title="Catégorie système">SYS</span>
                                    <?php endif; ?>
                                </td>
                                <td><code><?= e($c['code']) ?></code></td>
                                <td>
                                    <?php if ($c['actif']): ?>
                                        <span class="badge bg-success">Actif</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactif</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <a href="<?= url('paiements/categories.php?edit=' . $c['id']) ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if (!$c['systeme']): ?>
                                    <form method="POST" class="d-inline"
                                          data-confirm="Supprimer cette catégorie d'encaissement ?"
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
                        <label class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="libelle"
                               value="<?= e($editCat['libelle'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Code <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code"
                               value="<?= e($editCat['code'] ?? '') ?>"
                               <?= $editCat && $editCat['systeme'] ? 'readonly' : '' ?>
                               pattern="[a-z0-9_]+" required>
                        <small class="text-muted">Identifiant unique (a-z, 0-9, _)</small>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label">Icône (FontAwesome)</label>
                            <input type="text" class="form-control" name="icone"
                                   value="<?= e($editCat['icone'] ?? '') ?>" placeholder="fa-coins">
                        </div>
                        <div class="col-5">
                            <label class="form-label">Couleur</label>
                            <input type="color" class="form-control form-control-color w-100" name="couleur"
                                   value="<?= e($editCat['couleur'] ?? '#01305E') ?>">
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
                        <a href="<?= url('paiements/categories.php') ?>" class="btn btn-outline-secondary">Annuler</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
