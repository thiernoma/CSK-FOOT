<?php
/**
 * Liste des entraîneurs
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();

$pageTitle = 'Entraîneurs';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Entraîneurs']
];

$entraineurs = Entraineur::getAll();

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Entraîneurs</h4>
        <p class="text-muted mb-0"><?= count($entraineurs) ?> entraîneur(s)</p>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <a href="<?= url('entraineurs/nouveau.php') ?>" class="btn btn-accent">
        <i class="fas fa-plus me-2"></i>Nouvel entraîneur
    </a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <?php if (empty($entraineurs)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5 text-muted">
                    <i class="fas fa-whistle fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">Aucun entraîneur enregistré</p>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= url('entraineurs/nouveau.php') ?>" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Ajouter un entraîneur
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($entraineurs as $e): ?>
        <div class="col-xl-4 col-md-6">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex align-items-start">
                        <?php if ($e['photo']): ?>
                            <img src="<?= uploads('entraineurs/' . $e['photo']) ?>"
                                 class="rounded-circle me-3" style="width: 60px; height: 60px; object-fit: cover;">
                        <?php else: ?>
                            <div class="user-avatar me-3" style="width: 60px; height: 60px; font-size: 24px;">
                                <?= getInitials($e['nom'], $e['prenom']) ?>
                            </div>
                        <?php endif; ?>
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <a href="<?= url('entraineurs/voir.php?id=' . $e['id']) ?>" class="text-dark text-decoration-none">
                                    <?= e($e['prenom'] . ' ' . $e['nom']) ?>
                                </a>
                            </h5>
                            <span class="badge <?= statusBadgeClass($e['statut']) ?>"><?= translateStatus($e['statut']) ?></span>
                            <?php if ($e['specialite']): ?>
                                <span class="badge bg-light text-dark"><?= e($e['specialite']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <hr>

                    <?php
                    // Décoder les catégories JSON
                    $categories = [];
                    if ($e['categories']) {
                        $decoded = json_decode($e['categories'], true);
                        if (is_array($decoded)) {
                            $categories = $decoded;
                        } else {
                            // Si ce n'est pas du JSON, c'est une chaîne simple
                            $categories = array_map('trim', explode(',', $e['categories']));
                        }
                    }
                    $categoriesStr = !empty($categories) ? implode(', ', $categories) : '-';
                    ?>
                    <div class="row g-2 text-center mb-3">
                        <div class="col-6">
                            <div class="bg-primary-light rounded p-2">
                                <div class="h5 mb-0 text-primary"><?= $e['seances_planifiees'] ?? 0 ?></div>
                                <small class="text-muted">Séances</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="bg-light rounded p-2">
                                <div class="h6 mb-0"><?= e($categoriesStr) ?></div>
                                <small class="text-muted">Catégories</small>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <p class="mb-1">
                            <i class="fas fa-phone text-muted me-2"></i>
                            <a href="tel:<?= e($e['telephone']) ?>"><?= formatPhone($e['telephone']) ?></a>
                        </p>
                        <?php if ($e['email']): ?>
                        <p class="mb-1">
                            <i class="fas fa-envelope text-muted me-2"></i>
                            <a href="mailto:<?= e($e['email']) ?>"><?= e($e['email']) ?></a>
                        </p>
                        <?php endif; ?>
                    </div>

                    <?php if ($e['diplomes']): ?>
                    <p class="small text-muted mb-0">
                        <i class="fas fa-certificate me-1"></i><?= e($e['diplomes']) ?>
                    </p>
                    <?php endif; ?>
                </div>
                <div class="card-footer bg-light">
                    <div class="d-flex gap-2">
                        <a href="<?= url('entraineurs/voir.php?id=' . $e['id']) ?>" class="btn btn-sm btn-outline-primary flex-grow-1">
                            <i class="fas fa-eye me-1"></i>Profil
                        </a>
                        <a href="<?= url('entraineurs/planning.php?id=' . $e['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-calendar"></i>
                        </a>
                        <?php if (Auth::isAdmin()): ?>
                        <a href="<?= url('entraineurs/modifier.php?id=' . $e['id']) ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-edit"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
