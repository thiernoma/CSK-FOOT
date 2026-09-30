<?php
/**
 * Liste des terrains
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Gestion des terrains';
$breadcrumb = [['label' => 'Terrains']];

// Récupérer tous les terrains
$terrains = Terrain::getAll();

// Calculer les stats pour chaque terrain
foreach ($terrains as &$terrain) {
    $terrain['stats'] = Terrain::getStats($terrain['id'], 'mois');
    $terrain['occupation_jour'] = Terrain::getOccupancyRate($terrain['id'], date('Y-m-d'));
}
unset($terrain);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Gestion des terrains</h4>
        <p class="text-muted mb-0"><?= count($terrains) ?> terrain(s) enregistré(s)</p>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <a href="<?= url('terrains/nouveau.php') ?>" class="btn btn-accent">
        <i class="fas fa-plus me-2"></i>Ajouter un terrain
    </a>
    <?php endif; ?>
</div>

<!-- Liste des terrains -->
<div class="row g-4">
    <?php foreach ($terrains as $terrain): ?>
    <div class="col-xl-4 col-md-6">
        <div class="card h-100 shadow-hover">
            <!-- Image du terrain -->
            <div class="position-relative">
                <?php if ($terrain['photo']): ?>
                    <img src="<?= uploads('terrains/' . $terrain['photo']) ?>" class="card-img-top" alt="<?= e($terrain['nom']) ?>" style="height: 180px; object-fit: cover;">
                <?php else: ?>
                    <div class="card-img-top bg-primary d-flex align-items-center justify-content-center" style="height: 180px;">
                        <i class="fas fa-futbol fa-4x text-white opacity-50"></i>
                    </div>
                <?php endif; ?>

                <!-- Badge statut -->
                <span class="position-absolute top-0 end-0 m-3 badge <?= statusBadgeClass($terrain['statut']) ?>">
                    <?= translateStatus($terrain['statut']) ?>
                </span>

                <!-- Badge type -->
                <span class="position-absolute top-0 start-0 m-3 badge bg-dark">
                    <?= terrainTypeLabel($terrain['type']) ?>
                </span>
            </div>

            <div class="card-body">
                <h5 class="card-title mb-2"><?= e($terrain['nom']) ?></h5>

                <div class="d-flex gap-3 mb-3 text-muted small">
                    <?php if ($terrain['capacite']): ?>
                    <span><i class="fas fa-users me-1"></i><?= $terrain['capacite'] ?> joueurs</span>
                    <?php endif; ?>
                    <span><i class="fas fa-tag me-1"></i><?= formatMoney($terrain['prix_heure']) ?>/h</span>
                </div>

                <?php if ($terrain['description']): ?>
                <p class="card-text small text-muted mb-3">
                    <?= e(substr($terrain['description'], 0, 100)) ?><?= strlen($terrain['description']) > 100 ? '...' : '' ?>
                </p>
                <?php endif; ?>

                <!-- Stats du mois -->
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <div class="bg-light rounded p-2 text-center">
                            <div class="fw-bold text-primary"><?= $terrain['stats']['nb_reservations'] ?? 0 ?></div>
                            <small class="text-muted">Réservations/mois</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded p-2 text-center">
                            <div class="fw-bold text-success"><?= formatMoney($terrain['stats']['ca_total'] ?? 0) ?></div>
                            <small class="text-muted">CA du mois</small>
                        </div>
                    </div>
                </div>

                <!-- Barre d'occupation du jour -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between small mb-1">
                        <span>Occupation aujourd'hui</span>
                        <span class="fw-semibold"><?= number_format($terrain['occupation_jour'], 0) ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-<?= $terrain['occupation_jour'] > 80 ? 'danger' : ($terrain['occupation_jour'] > 50 ? 'warning' : 'success') ?>"
                             style="width: <?= $terrain['occupation_jour'] ?>%"></div>
                    </div>
                </div>
            </div>

            <div class="card-footer bg-transparent border-top-0 pt-0">
                <div class="d-flex gap-2">
                    <a href="<?= url('terrains/voir.php?id=' . $terrain['id']) ?>" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="fas fa-eye me-1"></i>Détails
                    </a>
                    <a href="<?= url('reservations/planning.php?terrain=' . $terrain['id']) ?>" class="btn btn-outline-primary btn-sm flex-fill">
                        <i class="fas fa-calendar me-1"></i>Planning
                    </a>
                    <?php if (Auth::isAdmin()): ?>
                    <a href="<?= url('terrains/modifier.php?id=' . $terrain['id']) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-edit"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (empty($terrains)): ?>
    <div class="col-12">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="fas fa-futbol fa-4x text-muted mb-3"></i>
                <h5>Aucun terrain enregistré</h5>
                <p class="text-muted">Commencez par ajouter votre premier terrain.</p>
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= url('terrains/nouveau.php') ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Ajouter un terrain
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
