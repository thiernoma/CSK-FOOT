<?php
/**
 * Mon profil
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';

Auth::requireLogin();

$pageTitle = 'Mon profil';
$breadcrumb = [
    ['label' => 'Mon profil']
];

$userId = Auth::id();
$user = Database::fetchOne(
    "SELECT u.*, r.nom as role_nom
     FROM users u
     LEFT JOIN roles r ON u.role_id = r.id
     WHERE u.id = :id",
    ['id' => $userId]
);

if (!$user) {
    Session::flash('danger', 'Utilisateur introuvable.');
    redirect(url('dashboard.php'));
}

$errors = [];
$success = '';
$activeTab = get('tab', 'infos');

// Traitement des formulaires
if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');

        // Mise à jour des informations personnelles
        if ($action === 'update_infos') {
            $nom = sanitize(post('nom'));
            $prenom = sanitize(post('prenom'));
            $email = sanitize(post('email'));
            $telephone = sanitize(post('telephone'));

            // Validation
            if (empty($nom)) {
                $errors[] = 'Le nom est obligatoire.';
            }
            if (empty($email)) {
                $errors[] = 'L\'email est obligatoire.';
            } elseif (!isValidEmail($email)) {
                $errors[] = 'L\'email n\'est pas valide.';
            } else {
                // Vérifier que l'email n'est pas déjà utilisé par un autre utilisateur
                $existingUser = Database::fetchOne(
                    "SELECT id FROM users WHERE email = :email AND id != :id",
                    ['email' => $email, 'id' => $userId]
                );
                if ($existingUser) {
                    $errors[] = 'Cet email est déjà utilisé par un autre compte.';
                }
            }

            if (empty($errors)) {
                Database::update('users', [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'email' => $email,
                    'telephone' => $telephone
                ], 'id = :id', ['id' => $userId]);

                // Mettre à jour la session
                Session::set('user_nom', $nom);
                Session::set('user_prenom', $prenom);
                Session::set('user_email', $email);

                Auth::logAction($userId, 'update', 'users', $userId);
                Session::flash('success', 'Vos informations ont été mises à jour.');
                redirect(url('profil.php?tab=infos'));
            }
            $activeTab = 'infos';
        }

        // Changement de mot de passe
        elseif ($action === 'change_password') {
            $currentPassword = post('current_password');
            $newPassword = post('new_password');
            $confirmPassword = post('confirm_password');

            // Validation
            if (empty($currentPassword)) {
                $errors[] = 'Le mot de passe actuel est obligatoire.';
            } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                $errors[] = 'Le mot de passe actuel est incorrect.';
            }

            if (empty($newPassword)) {
                $errors[] = 'Le nouveau mot de passe est obligatoire.';
            } elseif (strlen($newPassword) < 6) {
                $errors[] = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (empty($errors)) {
                Database::update('users', [
                    'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT)
                ], 'id = :id', ['id' => $userId]);

                Auth::logAction($userId, 'update', 'users', $userId);
                Session::flash('success', 'Votre mot de passe a été modifié.');
                redirect(url('profil.php?tab=securite'));
            }
            $activeTab = 'securite';
        }

        // Upload de photo
        elseif ($action === 'upload_photo') {
            if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = uploadFile($_FILES['photo'], 'avatars', ['jpg', 'jpeg', 'png', 'gif'], 2 * 1024 * 1024);

                if ($uploadResult['success']) {
                    // Supprimer l'ancienne photo si elle existe
                    if ($user['photo'] && file_exists(UPLOAD_PATH . 'avatars/' . $user['photo'])) {
                        unlink(UPLOAD_PATH . 'avatars/' . $user['photo']);
                    }

                    Database::update('users', [
                        'photo' => $uploadResult['filename']
                    ], 'id = :id', ['id' => $userId]);

                    Session::flash('success', 'Photo de profil mise à jour.');
                    redirect(url('profil.php?tab=infos'));
                } else {
                    $errors[] = $uploadResult['error'];
                }
            } else {
                $errors[] = 'Veuillez sélectionner une image.';
            }
            $activeTab = 'infos';
        }

        // Supprimer la photo
        elseif ($action === 'delete_photo') {
            if ($user['photo'] && file_exists(UPLOAD_PATH . 'avatars/' . $user['photo'])) {
                unlink(UPLOAD_PATH . 'avatars/' . $user['photo']);
            }

            Database::update('users', ['photo' => null], 'id = :id', ['id' => $userId]);
            Session::flash('success', 'Photo de profil supprimée.');
            redirect(url('profil.php?tab=infos'));
        }
    }

    // Recharger les données utilisateur après modification
    $user = Database::fetchOne(
        "SELECT u.*, r.nom as role_nom
         FROM users u
         LEFT JOIN roles r ON u.role_id = r.id
         WHERE u.id = :id",
        ['id' => $userId]
    );
}

// Statistiques de l'utilisateur
$stats = [
    'reservations_creees' => Database::fetchOne(
        "SELECT COUNT(*) as total FROM reservations WHERE cree_par = :id",
        ['id' => $userId]
    )['total'] ?? 0,
    'paiements_recus' => Database::fetchOne(
        "SELECT COUNT(*) as total FROM paiements WHERE recu_par = :id",
        ['id' => $userId]
    )['total'] ?? 0,
    'derniere_connexion' => $user['derniere_connexion'] ?? null,
    'compte_cree' => $user['created_at'] ?? null
];

// Activité récente
$activiteRecente = Database::fetchAll(
    "SELECT * FROM logs_audit
     WHERE user_id = :id
     ORDER BY created_at DESC
     LIMIT 10",
    ['id' => $userId]
);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row">
    <!-- Colonne gauche: Photo et infos rapides -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <!-- Photo de profil -->
                <div class="position-relative d-inline-block mb-3">
                    <?php if ($user['photo']): ?>
                        <img src="<?= uploads('avatars/' . $user['photo']) ?>"
                             alt="Photo de profil"
                             class="rounded-circle"
                             style="width: 120px; height: 120px; object-fit: cover;">
                    <?php else: ?>
                        <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto"
                             style="width: 120px; height: 120px; font-size: 48px; color: white;">
                            <?= getInitials($user['nom'], $user['prenom']) ?>
                        </div>
                    <?php endif; ?>

                    <button type="button" class="btn btn-sm btn-light position-absolute bottom-0 end-0 rounded-circle"
                            data-bs-toggle="modal" data-bs-target="#photoModal"
                            style="width: 36px; height: 36px;">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>

                <h4 class="mb-1"><?= e($user['prenom'] . ' ' . $user['nom']) ?></h4>
                <p class="text-muted mb-2"><?= e($user['role_nom'] ?? 'Utilisateur') ?></p>

                <span class="badge <?= $user['statut'] === 'actif' ? 'bg-success' : 'bg-secondary' ?>">
                    <?= ucfirst($user['statut']) ?>
                </span>

                <hr>

                <div class="text-start">
                    <p class="mb-2">
                        <i class="fas fa-envelope text-muted me-2"></i>
                        <?= e($user['email']) ?>
                    </p>
                    <?php if ($user['telephone']): ?>
                    <p class="mb-2">
                        <i class="fas fa-phone text-muted me-2"></i>
                        <?= formatPhone($user['telephone']) ?>
                    </p>
                    <?php endif; ?>
                    <p class="mb-2">
                        <i class="fas fa-calendar text-muted me-2"></i>
                        Membre depuis <?= formatDate($user['created_at'], 'd/m/Y') ?>
                    </p>
                    <?php if ($user['derniere_connexion']): ?>
                    <p class="mb-0">
                        <i class="fas fa-clock text-muted me-2"></i>
                        Dernière connexion: <?= formatDate($user['derniere_connexion'], 'd/m/Y H:i') ?>
                    </p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Mes statistiques</h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="h4 text-primary mb-0"><?= number_format($stats['reservations_creees']) ?></div>
                        <small class="text-muted">Réservations créées</small>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="h4 text-success mb-0"><?= number_format($stats['paiements_recus']) ?></div>
                        <small class="text-muted">Paiements reçus</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonne droite: Onglets -->
    <div class="col-lg-8">
        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'infos' ? 'active' : '' ?>" href="?tab=infos">
                            <i class="fas fa-user me-2"></i>Informations
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'securite' ? 'active' : '' ?>" href="?tab=securite">
                            <i class="fas fa-lock me-2"></i>Sécurité
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $activeTab === 'activite' ? 'active' : '' ?>" href="?tab=activite">
                            <i class="fas fa-history me-2"></i>Activité
                        </a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <!-- Onglet Informations -->
                <?php if ($activeTab === 'infos'): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_infos">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prénom</label>
                            <input type="text" class="form-control" name="prenom"
                                   value="<?= e($user['prenom']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom"
                                   value="<?= e($user['nom']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" class="form-control" name="email"
                                   value="<?= e($user['email']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="telephone"
                                   value="<?= e($user['telephone']) ?>" placeholder="77 XXX XX XX">
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- Onglet Sécurité -->
                <?php if ($activeTab === 'securite'): ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Mot de passe actuel <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="current_password"
                                       id="currentPassword" required>
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePasswordVisibility('currentPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nouveau mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="new_password"
                                       id="newPassword" required minlength="6">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePasswordVisibility('newPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 6 caractères</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmer le mot de passe <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="confirm_password"
                                       id="confirmPassword" required minlength="6">
                                <button type="button" class="btn btn-outline-secondary"
                                        onclick="togglePasswordVisibility('confirmPassword')">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info mt-4">
                        <i class="fas fa-info-circle me-2"></i>
                        Pour des raisons de sécurité, vous serez déconnecté après avoir changé votre mot de passe.
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Changer le mot de passe
                        </button>
                    </div>
                </form>
                <?php endif; ?>

                <!-- Onglet Activité -->
                <?php if ($activeTab === 'activite'): ?>
                <h6 class="mb-3">Activité récente</h6>
                <?php if (empty($activiteRecente)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
                        <p>Aucune activité récente</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($activiteRecente as $log): ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <span class="badge <?= match($log['action']) {
                                        'create' => 'bg-success',
                                        'update' => 'bg-primary',
                                        'delete' => 'bg-danger',
                                        'login' => 'bg-info',
                                        'logout' => 'bg-secondary',
                                        default => 'bg-light text-dark'
                                    } ?> me-2">
                                        <?= match($log['action']) {
                                            'create' => 'Création',
                                            'update' => 'Modification',
                                            'delete' => 'Suppression',
                                            'login' => 'Connexion',
                                            'logout' => 'Déconnexion',
                                            'payment' => 'Paiement',
                                            default => ucfirst($log['action'])
                                        } ?>
                                    </span>
                                    <?php if ($log['table_cible']): ?>
                                        <span class="text-muted"><?= e($log['table_cible']) ?></span>
                                        <?php if ($log['id_cible']): ?>
                                            <span class="badge bg-light text-dark">#<?= $log['id_cible'] ?></span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= formatDate($log['created_at'], 'd/m/Y H:i') ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal Photo -->
<div class="modal fade" id="photoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-camera me-2"></i>Photo de profil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="upload_photo">
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <?php if ($user['photo']): ?>
                            <img src="<?= uploads('avatars/' . $user['photo']) ?>"
                                 alt="Photo actuelle"
                                 class="rounded-circle mb-3"
                                 style="width: 150px; height: 150px; object-fit: cover;">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary d-flex align-items-center justify-content-center mx-auto mb-3"
                                 style="width: 150px; height: 150px; font-size: 60px; color: white;">
                                <?= getInitials($user['nom'], $user['prenom']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Choisir une nouvelle photo</label>
                        <input type="file" class="form-control" name="photo" accept="image/*" required>
                        <div class="form-text">JPG, PNG ou GIF. Max 2 Mo.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <?php if ($user['photo']): ?>
                    <form method="POST" class="d-inline"
                          data-confirm="Supprimer la photo de profil ?"
                          data-confirm-title="Supprimer la photo"
                          data-confirm-text="<i class='fas fa-trash me-1'></i> Supprimer"
                          data-confirm-class="btn-danger"
                          data-confirm-icon="fa-image"
                          data-confirm-icon-class="text-danger">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete_photo">
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </button>
                    </form>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Télécharger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = "
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const icon = event.currentTarget.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// Validation mot de passe confirmation
document.querySelector('input[name=\"confirm_password\"]')?.addEventListener('input', function() {
    const newPass = document.querySelector('input[name=\"new_password\"]').value;
    if (this.value !== newPass) {
        this.setCustomValidity('Les mots de passe ne correspondent pas');
    } else {
        this.setCustomValidity('');
    }
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
