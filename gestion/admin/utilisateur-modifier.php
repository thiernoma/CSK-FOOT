<?php
/**
 * Modifier un utilisateur
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/User.php';

Auth::requireLogin();
Auth::requireAdmin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Utilisateur non spécifié.');
    redirect(url('admin/utilisateurs.php'));
}

$user = User::getById($id);
if (!$user) {
    Session::flash('danger', 'Utilisateur introuvable.');
    redirect(url('admin/utilisateurs.php'));
}

$pageTitle = 'Modifier ' . $user['nom'];
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Utilisateurs', 'url' => url('admin/utilisateurs.php')],
    ['label' => 'Modifier']
];

$roles = User::getRoles();
$errors = [];
$data = $user;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'nom' => sanitize(post('nom')),
            'email' => sanitize(post('email')),
            'role_id' => (int)post('role_id'),
            'telephone' => sanitize(post('telephone')),
            'statut' => sanitize(post('statut')),
            'password' => post('password') // optionnel
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire.';
        }
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
        }

        // Ne pas changer le rôle du super admin
        if ($user['role_id'] == 1 && $data['role_id'] != 1) {
            $errors[] = 'Impossible de modifier le rôle du super administrateur.';
        }

        if (empty($errors)) {
            try {
                User::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'users', $id);
                Session::flash('success', 'Utilisateur mis à jour avec succès !');
                redirect(url('admin/utilisateurs.php'));
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
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-user-edit me-2"></i>Modifier l'utilisateur</h5>
                <span class="badge <?= statusBadgeClass($user['statut']) ?>"><?= translateStatus($user['statut']) ?></span>
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
                        <div class="col-md-6">
                            <label class="form-label">Nom complet <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom"
                                   value="<?= e($data['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= e($data['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="password" minlength="8">
                            <small class="text-muted">Laisser vide pour ne pas changer</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone"
                                   value="<?= e($data['telephone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Rôle <span class="text-danger">*</span></label>
                            <select class="form-select" name="role_id" required
                                    <?= $user['role_id'] == 1 ? 'disabled' : '' ?>>
                                <?php foreach ($roles as $role): ?>
                                    <option value="<?= $role['id'] ?>" <?= $data['role_id'] == $role['id'] ? 'selected' : '' ?>>
                                        <?= e($role['nom']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($user['role_id'] == 1): ?>
                                <input type="hidden" name="role_id" value="1">
                                <small class="text-warning">Le rôle du super admin ne peut pas être modifié</small>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut"
                                    <?= $user['id'] == Auth::id() ? 'disabled' : '' ?>>
                                <option value="actif" <?= $data['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="inactif" <?= $data['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                            </select>
                            <?php if ($user['id'] == Auth::id()): ?>
                                <input type="hidden" name="statut" value="<?= $data['statut'] ?>">
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Info -->
                    <div class="bg-light rounded p-3 mt-4">
                        <h6 class="mb-3">Informations</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted">Créé le:</small><br>
                                <?= formatDate($user['created_at'], 'd/m/Y H:i') ?>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted">Dernière connexion:</small><br>
                                <?= $user['derniere_connexion'] ? formatDate($user['derniere_connexion'], 'd/m/Y H:i') : 'Jamais' ?>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('admin/utilisateurs.php') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
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
