<?php
/**
 * Liste des réservations
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Réservations';
$breadcrumb = [['label' => 'Réservations']];

// Filtres
$filters = [
    'date' => get('date'),
    'terrain_id' => get('terrain'),
    'statut_reservation' => get('statut'),
    'statut_paiement' => get('paiement'),
    'recherche' => get('q')
];

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 20;
$total = Reservation::count($filters);
$pagination = paginate($total, $perPage, $page);

// Récupérer les réservations
$reservations = Reservation::getAll($filters, $perPage, $pagination['offset']);

// Liste des terrains pour le filtre
$terrains = Terrain::getAll('actif');

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Réservations</h4>
        <p class="text-muted mb-0"><?= $total ?> réservation(s) trouvée(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('reservations/planning.php') ?>" class="btn btn-outline-primary">
            <i class="fas fa-calendar-week me-2"></i>Planning
        </a>
        <a href="<?= url('reservations/nouveau.php') ?>" class="btn btn-accent">
            <i class="fas fa-plus me-2"></i>Nouvelle réservation
        </a>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Date</label>
                <input type="date" class="form-control" name="date" value="<?= e($filters['date']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Terrain</label>
                <select class="form-select" name="terrain">
                    <option value="">Tous</option>
                    <?php foreach ($terrains as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $filters['terrain_id'] == $t['id'] ? 'selected' : '' ?>>
                            <?= e($t['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <option value="confirmee" <?= $filters['statut_reservation'] === 'confirmee' ? 'selected' : '' ?>>Confirmée</option>
                    <option value="en_cours" <?= $filters['statut_reservation'] === 'en_cours' ? 'selected' : '' ?>>En cours</option>
                    <option value="terminee" <?= $filters['statut_reservation'] === 'terminee' ? 'selected' : '' ?>>Terminée</option>
                    <option value="annulee" <?= $filters['statut_reservation'] === 'annulee' ? 'selected' : '' ?>>Annulée</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Paiement</label>
                <select class="form-select" name="paiement">
                    <option value="">Tous</option>
                    <option value="paye" <?= $filters['statut_paiement'] === 'paye' ? 'selected' : '' ?>>Payé</option>
                    <option value="partiel" <?= $filters['statut_paiement'] === 'partiel' ? 'selected' : '' ?>>Partiel</option>
                    <option value="en_attente" <?= $filters['statut_paiement'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="q" placeholder="N° ticket, client, téléphone..."
                       value="<?= e($filters['recherche']) ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des réservations -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($reservations)): ?>
            <div class="text-center py-5">
                <i class="fas fa-calendar-times fa-4x text-muted mb-3"></i>
                <h5>Aucune réservation trouvée</h5>
                <p class="text-muted">Modifiez vos critères de recherche ou créez une nouvelle réservation.</p>
                <a href="<?= url('reservations/nouveau.php') ?>" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Nouvelle réservation
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>N° Ticket</th>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Terrain</th>
                            <th>Client</th>
                            <th>Montant</th>
                            <th>Paiement</th>
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
                            <td>
                                <div><?= formatDate($r['date_reservation']) ?></div>
                                <small class="text-muted"><?= getDayName($r['date_reservation']) ?></small>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= formatTimeFull($r['heure_debut']) ?></span>
                                <span class="text-muted">-</span>
                                <span><?= formatTimeFull($r['heure_fin']) ?></span>
                                <br>
                                <small class="text-muted"><?= formatDuration($r['duree_heures']) ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= e($r['terrain_nom']) ?></span>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= e($r['client_nom']) ?></div>
                                <small class="text-muted"><?= formatPhone($r['client_telephone']) ?></small>
                            </td>
                            <td>
                                <div class="fw-semibold"><?= formatMoney($r['montant']) ?></div>
                                <?php if ($r['montant_paye'] > 0 && $r['montant_paye'] < $r['montant']): ?>
                                    <small class="text-success">Payé: <?= formatMoney($r['montant_paye']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($r['statut_paiement']) ?>">
                                    <?= translateStatus($r['statut_paiement']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($r['statut_reservation']) ?>">
                                    <?= translateStatus($r['statut_reservation']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= url('reservations/voir.php?id=' . $r['id']) ?>">
                                                <i class="fas fa-eye me-2"></i>Détails
                                            </a>
                                        </li>
                                        <?php if ($r['statut_reservation'] !== 'annulee' && $r['statut_reservation'] !== 'terminee'): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('reservations/modifier.php?id=' . $r['id']) ?>">
                                                <i class="fas fa-edit me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <?php if ($r['statut_paiement'] !== 'paye'): ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('reservations/paiement.php?id=' . $r['id']) ?>">
                                                <i class="fas fa-money-bill me-2"></i>Encaisser
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('reservations/recu.php?id=' . $r['id']) ?>" target="_blank">
                                                <i class="fas fa-print me-2"></i>Imprimer reçu
                                            </a>
                                        </li>
                                        <?php if ($r['statut_reservation'] === 'confirmee'): ?>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item text-danger" href="<?= url('reservations/annuler.php?id=' . $r['id']) ?>">
                                                <i class="fas fa-times me-2"></i>Annuler
                                            </a>
                                        </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($pagination['total_pages'] > 1): ?>
            <div class="card-footer">
                <?= paginationHtml($pagination, url('reservations/index.php')) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
