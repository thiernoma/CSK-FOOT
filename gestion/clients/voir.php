<?php
/**
 * Profil client
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Client.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Client non spécifié.');
    redirect(url('clients/index.php'));
}

$client = Client::getById($id);
if (!$client) {
    Session::flash('danger', 'Client introuvable.');
    redirect(url('clients/index.php'));
}

$pageTitle = ($client['prenom'] ?? '') . ' ' . $client['nom'];
$breadcrumb = [
    ['label' => 'Clients', 'url' => url('clients/index.php')],
    ['label' => $pageTitle]
];

// Statistiques
$stats = Client::getStats($id);

// Dernières réservations
$reservations = Client::getReservations($id, 10);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <div class="user-avatar me-3" style="width: 60px; height: 60px; font-size: 24px;">
            <?= getInitials($client['nom'], $client['prenom'] ?? '') ?>
        </div>
        <div>
            <h4 class="mb-1"><?= e(($client['prenom'] ?? '') . ' ' . $client['nom']) ?></h4>
            <span class="badge <?= statusBadgeClass($client['statut']) ?> me-2"><?= translateStatus($client['statut']) ?></span>
            <span class="badge bg-light text-dark"><?= ucfirst($client['type_client']) ?></span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('reservations/nouveau.php?client=' . $id) ?>" class="btn btn-accent">
            <i class="fas fa-calendar-plus me-2"></i>Nouvelle réservation
        </a>
        <a href="<?= url('clients/modifier.php?id=' . $id) ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne gauche: Infos et stats -->
    <div class="col-xl-4">
        <!-- Informations -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Informations</h5>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" width="40%"><i class="fas fa-phone me-2"></i>Téléphone</td>
                        <td>
                            <a href="tel:<?= e($client['telephone']) ?>">
                                <?= formatPhone($client['telephone']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php if ($client['telephone_alt']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-phone me-2"></i>Tél. secondaire</td>
                        <td><?= formatPhone($client['telephone_alt']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($client['email']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-envelope me-2"></i>Email</td>
                        <td>
                            <a href="mailto:<?= e($client['email']) ?>">
                                <?= e($client['email']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($client['adresse']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-map-marker-alt me-2"></i>Adresse</td>
                        <td><?= nl2br(e($client['adresse'])) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-calendar me-2"></i>Client depuis</td>
                        <td><?= formatDate($client['created_at'], 'd/m/Y') ?></td>
                    </tr>
                </table>

                <?php if ($client['notes']): ?>
                <hr>
                <h6 class="text-muted mb-2">Notes</h6>
                <p class="mb-0 small"><?= nl2br(e($client['notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Statistiques</h5>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="bg-primary-light rounded p-3">
                            <div class="h3 mb-0 text-primary"><?= $stats['nb_reservations'] ?? 0 ?></div>
                            <small class="text-muted">Réservations</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-success text-white rounded p-3">
                            <div class="h5 mb-0"><?= formatMoney($stats['montant_total'] ?? 0) ?></div>
                            <small>CA Total</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded p-3">
                            <div class="h5 mb-0"><?= formatMoney($stats['panier_moyen'] ?? 0) ?></div>
                            <small class="text-muted">Panier moyen</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-light rounded p-3">
                            <div class="h6 mb-0">
                                <?= $stats['derniere_visite'] ? formatDate($stats['derniere_visite'], 'd/m/Y') : '-' ?>
                            </div>
                            <small class="text-muted">Dernière visite</small>
                        </div>
                    </div>
                </div>

                <?php if ($client['points_fidelite'] > 0): ?>
                <hr>
                <div class="d-flex justify-content-between align-items-center">
                    <span><i class="fas fa-star text-warning me-2"></i>Points fidélité</span>
                    <strong class="text-warning"><?= $client['points_fidelite'] ?> pts</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Colonne droite: Réservations -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Historique des réservations</h5>
                <a href="<?= url('reservations/index.php?client=' . $id) ?>" class="btn btn-sm btn-outline-primary">
                    Voir tout
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($reservations)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">Aucune réservation pour ce client</p>
                        <a href="<?= url('reservations/nouveau.php?client=' . $id) ?>" class="btn btn-primary mt-3">
                            <i class="fas fa-plus me-2"></i>Créer une réservation
                        </a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>N° Ticket</th>
                                    <th>Date</th>
                                    <th>Terrain</th>
                                    <th>Horaire</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $r): ?>
                                <tr>
                                    <td>
                                        <a href="<?= url('reservations/voir.php?id=' . $r['id']) ?>" class="fw-semibold">
                                            <?= e($r['numero_ticket']) ?>
                                        </a>
                                    </td>
                                    <td><?= formatDate($r['date_reservation']) ?></td>
                                    <td>
                                        <span class="badge bg-light text-dark"><?= e($r['terrain_nom']) ?></span>
                                    </td>
                                    <td><?= formatTime($r['heure_debut']) ?> - <?= formatTime($r['heure_fin']) ?></td>
                                    <td class="fw-semibold"><?= formatMoney($r['montant']) ?></td>
                                    <td>
                                        <span class="badge <?= statusBadgeClass($r['statut_reservation']) ?>">
                                            <?= translateStatus($r['statut_reservation']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="<?= url('reservations/voir.php?id=' . $r['id']) ?>" class="btn btn-sm btn-outline-primary">
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
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
