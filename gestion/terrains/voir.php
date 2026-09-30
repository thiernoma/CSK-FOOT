<?php
/**
 * Détails d'un terrain
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Terrain non spécifié.');
    redirect(url('terrains/index.php'));
}

$terrain = Terrain::getById($id);
if (!$terrain) {
    Session::flash('danger', 'Terrain introuvable.');
    redirect(url('terrains/index.php'));
}

$pageTitle = $terrain['nom'];
$breadcrumb = [
    ['label' => 'Terrains', 'url' => url('terrains/index.php')],
    ['label' => $terrain['nom']]
];

// Statistiques
$statsJour = Terrain::getStats($id, 'jour');
$statsMois = Terrain::getStats($id, 'mois');
$statsAnnee = Terrain::getStats($id, 'annee');

// Réservations du jour
$reservationsJour = Database::fetchAll(
    "SELECT r.*, CONCAT(c.prenom, ' ', c.nom) as client_nom, c.telephone
     FROM reservations r
     JOIN clients c ON r.client_id = c.id
     WHERE r.terrain_id = :terrain_id
     AND r.date_reservation = CURDATE()
     ORDER BY r.heure_debut",
    ['terrain_id' => $id]
);

// Occupation du jour
$occupationJour = Terrain::getOccupancyRate($id, date('Y-m-d'));

// Dernières réservations
$dernieresReservations = Database::fetchAll(
    "SELECT r.*, CONCAT(c.prenom, ' ', c.nom) as client_nom
     FROM reservations r
     JOIN clients c ON r.client_id = c.id
     WHERE r.terrain_id = :terrain_id
     ORDER BY r.created_at DESC
     LIMIT 10",
    ['terrain_id' => $id]
);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= e($terrain['nom']) ?></h4>
        <span class="badge <?= statusBadgeClass($terrain['statut']) ?> me-2"><?= translateStatus($terrain['statut']) ?></span>
        <span class="badge bg-secondary"><?= terrainTypeLabel($terrain['type']) ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('reservations/nouveau.php?terrain=' . $id) ?>" class="btn btn-accent">
            <i class="fas fa-plus me-2"></i>Réserver
        </a>
        <a href="<?= url('reservations/planning.php?terrain=' . $id) ?>" class="btn btn-primary">
            <i class="fas fa-calendar-week me-2"></i>Planning
        </a>
        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('terrains/modifier.php?id=' . $id) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
        <a href="<?= url('terrains/dupliquer.php?id=' . $id) ?>" class="btn btn-outline-info">
            <i class="fas fa-copy me-2"></i>Dupliquer
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne gauche: Infos et Stats -->
    <div class="col-xl-4">
        <!-- Photo et infos -->
        <div class="card mb-4">
            <?php if ($terrain['photo']): ?>
                <img src="<?= uploads('terrains/' . $terrain['photo']) ?>" class="card-img-top" alt="<?= e($terrain['nom']) ?>" style="height: 200px; object-fit: cover;">
            <?php else: ?>
                <div class="card-img-top bg-primary d-flex align-items-center justify-content-center" style="height: 200px;">
                    <i class="fas fa-futbol fa-5x text-white opacity-50"></i>
                </div>
            <?php endif; ?>
            <div class="card-body">
                <h5 class="card-title"><?= e($terrain['nom']) ?></h5>

                <ul class="list-unstyled mb-0">
                    <?php if ($terrain['prix_heure_pointe']): ?>
                    <li class="mb-2">
                        <i class="fas fa-tag text-primary me-2"></i>
                        <strong>Tarif normal:</strong> <?= formatMoney($terrain['prix_heure_pointe']) ?>/h
                    </li>
                    <?php endif; ?>
                    <li class="mb-2">
                        <i class="fas fa-sun text-success me-2"></i>
                        <strong>Tarif matinal:</strong> <?= formatMoney($terrain['prix_heure']) ?>/h
                    </li>
                    <?php if ($terrain['prix_weekend']): ?>
                    <li class="mb-2">
                        <i class="fas fa-calendar-week text-info me-2"></i>
                        <strong>Week-end:</strong> <?= formatMoney($terrain['prix_weekend']) ?>/h
                    </li>
                    <?php endif; ?>
                    <?php if ($terrain['capacite']): ?>
                    <li class="mb-2">
                        <i class="fas fa-users text-success me-2"></i>
                        <strong>Capacité:</strong> <?= $terrain['capacite'] ?> joueurs max
                    </li>
                    <?php endif; ?>
                </ul>

                <?php if ($terrain['description']): ?>
                <hr>
                <p class="text-muted small mb-0"><?= nl2br(e($terrain['description'])) ?></p>
                <?php endif; ?>

                <?php if ($terrain['equipements']): ?>
                <hr>
                <h6 class="small text-uppercase text-muted mb-2">Équipements</h6>
                <p class="small mb-0"><?= nl2br(e($terrain['equipements'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques</h6>
            </div>
            <div class="card-body">
                <!-- Occupation du jour -->
                <div class="mb-4">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Occupation aujourd'hui</span>
                        <strong><?= number_format($occupationJour, 1) ?>%</strong>
                    </div>
                    <div class="progress" style="height: 10px;">
                        <div class="progress-bar bg-<?= $occupationJour > 80 ? 'danger' : ($occupationJour > 50 ? 'warning' : 'success') ?>"
                             style="width: <?= $occupationJour ?>%"></div>
                    </div>
                </div>

                <!-- Stats du jour -->
                <div class="row g-3 text-center mb-3">
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="h5 mb-0 text-primary"><?= $statsJour['nb_reservations'] ?? 0 ?></div>
                            <small class="text-muted">Aujourd'hui</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="h5 mb-0 text-success"><?= $statsMois['nb_reservations'] ?? 0 ?></div>
                            <small class="text-muted">Ce mois</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="border rounded p-2">
                            <div class="h5 mb-0 text-info"><?= $statsAnnee['nb_reservations'] ?? 0 ?></div>
                            <small class="text-muted">Cette année</small>
                        </div>
                    </div>
                </div>

                <!-- CA -->
                <div class="bg-light rounded p-3">
                    <h6 class="text-muted mb-2">Chiffre d'affaires</h6>
                    <div class="d-flex justify-content-between">
                        <span>Ce mois:</span>
                        <strong class="text-success"><?= formatMoney($statsMois['ca_total'] ?? 0) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Cette année:</span>
                        <strong><?= formatMoney($statsAnnee['ca_total'] ?? 0) ?></strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonne droite: Réservations -->
    <div class="col-xl-8">
        <!-- Réservations du jour -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-calendar-day me-2"></i>Réservations du jour</h6>
                <span class="badge bg-primary"><?= count($reservationsJour) ?> réservation(s)</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reservationsJour)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">Aucune réservation aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Horaire</th>
                                    <th>Client</th>
                                    <th>Durée</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservationsJour as $resa): ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold"><?= formatTime($resa['heure_debut']) ?></span>
                                        <span class="text-muted">-</span>
                                        <span><?= formatTime($resa['heure_fin']) ?></span>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= e($resa['client_nom']) ?></div>
                                        <small class="text-muted"><?= formatPhone($resa['telephone']) ?></small>
                                    </td>
                                    <td><?= formatDuration($resa['duree_heures']) ?></td>
                                    <td class="fw-semibold"><?= formatMoney($resa['montant']) ?></td>
                                    <td>
                                        <span class="badge <?= statusBadgeClass($resa['statut_paiement']) ?>">
                                            <?= translateStatus($resa['statut_paiement']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= url('reservations/voir.php?id=' . $resa['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dernières réservations -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-history me-2"></i>Dernières réservations</h6>
                <a href="<?= url('reservations/index.php?terrain=' . $id) ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($dernieresReservations)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune réservation pour ce terrain</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>N° Ticket</th>
                                    <th>Date</th>
                                    <th>Horaire</th>
                                    <th>Client</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dernieresReservations as $resa): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('reservations/voir.php?id=' . $resa['id']) ?>" class="fw-semibold">
                                            <?= e($resa['numero_ticket']) ?>
                                        </a>
                                    </td>
                                    <td><?= formatDate($resa['date_reservation']) ?></td>
                                    <td><?= formatTime($resa['heure_debut']) ?> - <?= formatTime($resa['heure_fin']) ?></td>
                                    <td><?= e($resa['client_nom']) ?></td>
                                    <td class="fw-semibold"><?= formatMoney($resa['montant']) ?></td>
                                    <td>
                                        <span class="badge <?= statusBadgeClass($resa['statut_reservation']) ?>">
                                            <?= translateStatus($resa['statut_reservation']) ?>
                                        </span>
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
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
