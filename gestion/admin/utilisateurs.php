<?php
/**
 * Gestion des utilisateurs
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/User.php';

Auth::requireLogin();
Auth::requireAdmin();

$pageTitle = 'Gestion des utilisateurs';
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Utilisateurs']
];

// Filtres
$filters = [
    'search' => sanitize(get('search')),
    'role_id' => (int)get('role'),
    'statut' => sanitize(get('statut'))
];

$page = max(1, (int)get('page', 1));
$result = User::getAll($filters, $page, 20);

// Rôles pour le filtre
$roles = User::getRoles();

// Stats
$stats = User::getStats();

// Traitement des actions
$errors = [];
$success = null;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');

        if ($action === 'create') {
            $data = [
                'nom' => sanitize(post('nom')),
                'email' => sanitize(post('email')),
                'password' => post('password'),
                'role_id' => (int)post('role_id'),
                'telephone' => sanitize(post('telephone')),
                'statut' => 'actif'
            ];

            // Validation
            if (empty($data['nom'])) {
                $errors[] = 'Le nom est obligatoire.';
            }
            if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }
            if (empty($data['password']) || strlen($data['password']) < 8) {
                $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
            }
            if (empty($data['role_id'])) {
                $errors[] = 'Veuillez sélectionner un rôle.';
            }

            if (empty($errors)) {
                try {
                    $userId = User::create($data);
                    Auth::logAction(Auth::id(), 'create', 'users', $userId);
                    Session::flash('success', 'Utilisateur créé avec succès !');
                    redirect(url('admin/utilisateurs.php'));
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if ($action === 'toggle_status') {
            $userId = (int)post('user_id');
            if ($userId && $userId !== Auth::id()) {
                User::toggleStatus($userId);
                Auth::logAction(Auth::id(), 'update', 'users', $userId);
                Session::flash('success', 'Statut modifié avec succès.');
                redirect(url('admin/utilisateurs.php'));
            }
        }

        if ($action === 'delete') {
            $userId = (int)post('user_id');
            if ($userId && $userId !== Auth::id()) {
                try {
                    User::delete($userId);
                    Auth::logAction(Auth::id(), 'delete', 'users', $userId);
                    Session::flash('success', 'Utilisateur supprimé.');
                    redirect(url('admin/utilisateurs.php'));
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }

        if ($action === 'reset_password') {
            $userId = (int)post('user_id');
            $newPassword = post('new_password');

            if ($userId && strlen($newPassword) >= 8) {
                User::changePassword($userId, $newPassword);
                Auth::logAction(Auth::id(), 'update', 'users', $userId);
                Session::flash('success', 'Mot de passe réinitialisé.');
                redirect(url('admin/utilisateurs.php'));
            } else {
                $errors[] = 'Mot de passe invalide (minimum 8 caractères).';
            }
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Utilisateurs</h4>
        <p class="text-muted mb-0"><?= $result['total'] ?> utilisateur(s)</p>
    </div>
    <button type="button" class="btn btn-accent" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-2"></i>Nouvel utilisateur
    </button>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary text-white">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['total'] ?? 0 ?></div>
                <div class="kpi-label">Total</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-success text-white">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['actifs'] ?? 0 ?></div>
                <div class="kpi-label">Actifs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning text-white">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['connectes_24h'] ?? 0 ?></div>
                <div class="kpi-label">Connectés (24h)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger text-white">
                <i class="fas fa-user-slash"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['inactifs'] ?? 0 ?></div>
                <div class="kpi-label">Inactifs</div>
            </div>
        </div>
    </div>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $error): ?>
            <li><?= e($error) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" class="form-control form-control-sm" name="search"
                       value="<?= e($filters['search']) ?>" placeholder="Rechercher...">
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="role">
                    <option value="">Tous les rôles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= $role['id'] ?>" <?= $filters['role_id'] == $role['id'] ? 'selected' : '' ?>>
                            <?= e($role['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <select class="form-select form-select-sm" name="statut">
                    <option value="">Tous statuts</option>
                    <option value="actif" <?= $filters['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="inactif" <?= $filters['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fas fa-filter"></i> Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($result['data'])): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">Aucun utilisateur trouvé</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Utilisateur</th>
                            <th>Email</th>
                            <th>Rôle</th>
                            <th>Téléphone</th>
                            <th>Dernière connexion</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['data'] as $user): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2" style="width:40px;height:40px;">
                                        <?= strtoupper(substr($user['nom'], 0, 2)) ?>
                                    </div>
                                    <div>
                                        <strong><?= e($user['nom']) ?></strong>
                                        <?php if ($user['id'] === Auth::id()): ?>
                                            <span class="badge bg-info ms-1">Vous</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= e($user['email']) ?></td>
                            <td>
                                <span class="badge bg-<?= $user['role_id'] == 1 ? 'danger' : ($user['role_id'] == 2 ? 'primary' : 'secondary') ?>">
                                    <?= e($user['role_nom']) ?>
                                </span>
                            </td>
                            <td><?= $user['telephone'] ? formatPhone($user['telephone']) : '-' ?></td>
                            <td>
                                <?php if ($user['derniere_connexion']): ?>
                                    <?= formatDate($user['derniere_connexion'], 'd/m/Y H:i') ?>
                                <?php else: ?>
                                    <span class="text-muted">Jamais</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($user['statut']) ?>">
                                    <?= translateStatus($user['statut']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['id'] !== Auth::id()): ?>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= url('admin/utilisateur-modifier.php?id=' . $user['id']) ?>">
                                                <i class="fas fa-edit me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#passwordModal"
                                                    data-user-id="<?= $user['id'] ?>" data-user-name="<?= e($user['nom']) ?>">
                                                <i class="fas fa-key me-2"></i>Réinitialiser MDP
                                            </button>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <form method="POST" style="display:inline;">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="dropdown-item">
                                                    <?php if ($user['statut'] === 'actif'): ?>
                                                        <i class="fas fa-user-slash me-2"></i>Désactiver
                                                    <?php else: ?>
                                                        <i class="fas fa-user-check me-2"></i>Activer
                                                    <?php endif; ?>
                                                </button>
                                            </form>
                                        </li>
                                        <?php if ($user['role_id'] != 1): ?>
                                        <li>
                                            <form method="POST"
                                                  data-confirm="Êtes-vous sûr de vouloir supprimer cet utilisateur ? Cette action est irréversible."
                                                  data-confirm-title="Supprimer l'utilisateur"
                                                  data-confirm-text="<i class='fas fa-trash me-1'></i> Supprimer"
                                                  data-confirm-class="btn-danger"
                                                  data-confirm-icon="fa-user-times"
                                                  data-confirm-icon-class="text-danger">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="dropdown-item text-danger">
                                                    <i class="fas fa-trash me-2"></i>Supprimer
                                                </button>
                                            </form>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($result['pages'] > 1): ?>
            <div class="card-footer">
                <?= pagination($result['current_page'], $result['pages'], url('admin/utilisateurs') . '?' . http_build_query(array_filter($filters))) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Créer -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="create">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nouvel utilisateur</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="nom" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" name="email" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="password" required minlength="8">
                        <small class="text-muted">Minimum 8 caractères</small>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Rôle <span class="text-danger">*</span></label>
                        <select class="form-select" name="role_id" required>
                            <option value="">Sélectionner...</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?= $role['id'] ?>"><?= e($role['nom']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" name="telephone" placeholder="77 XXX XX XX">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-accent">
                        <i class="fas fa-check me-2"></i>Créer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Reset Password -->
<div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="pwd_user_id">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-key me-2"></i>Réinitialiser le mot de passe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Réinitialiser le mot de passe de <strong id="pwd_user_name"></strong></p>
                    <div class="mb-3">
                        <label class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" name="new_password" required minlength="8">
                        <small class="text-muted">Minimum 8 caractères</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-key me-2"></i>Réinitialiser
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = "
document.getElementById('passwordModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('pwd_user_id').value = button.dataset.userId;
    document.getElementById('pwd_user_name').textContent = button.dataset.userName;
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
