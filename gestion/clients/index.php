<?php
/**
 * Liste des clients
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Client.php';

Auth::requireLogin();

$pageTitle = 'Clients';
$breadcrumb = [['label' => 'Clients']];

// Filtres
$filters = [
    'statut' => get('statut'),
    'type_client' => get('type'),
    'recherche' => get('q')
];

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 20;
$total = Client::count($filters);
$pagination = paginate($total, $perPage, $page);

// Récupérer les clients
$clients = Client::getAll($filters, $perPage, $pagination['offset']);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Gestion des clients</h4>
        <p class="text-muted mb-0"><?= $total ?> client(s) enregistré(s)</p>
    </div>
    <a href="<?= url('clients/nouveau.php') ?>" class="btn btn-accent">
        <i class="fas fa-user-plus me-2"></i>Nouveau client
    </a>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Recherche</label>
                <input type="text" class="form-control" name="q" placeholder="Nom, téléphone, email..."
                       value="<?= e($filters['recherche']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Type de client</label>
                <select class="form-select" name="type">
                    <option value="">Tous</option>
                    <option value="particulier" <?= $filters['type_client'] === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                    <option value="entreprise" <?= $filters['type_client'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                    <option value="association" <?= $filters['type_client'] === 'association' ? 'selected' : '' ?>>Association</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <option value="actif" <?= $filters['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="vip" <?= $filters['statut'] === 'vip' ? 'selected' : '' ?>>VIP</option>
                    <option value="inactif" <?= $filters['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-2"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des clients -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($clients)): ?>
            <div class="text-center py-5">
                <i class="fas fa-users fa-4x text-muted mb-3"></i>
                <h5>Aucun client trouvé</h5>
                <p class="text-muted">Modifiez vos critères de recherche ou ajoutez un nouveau client.</p>
                <a href="<?= url('clients/nouveau.php') ?>" class="btn btn-primary">
                    <i class="fas fa-user-plus me-2"></i>Nouveau client
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Client</th>
                            <th>Contact</th>
                            <th>Type</th>
                            <th>Réservations</th>
                            <th>CA Total</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-3" style="width: 40px; height: 40px; font-size: 14px;">
                                        <?= getInitials($client['nom'], $client['prenom'] ?? '') ?>
                                    </div>
                                    <div>
                                        <a href="<?= url('clients/voir.php?id=' . $client['id']) ?>" class="fw-semibold text-dark">
                                            <?= e(($client['prenom'] ?? '') . ' ' . $client['nom']) ?>
                                        </a>
                                        <?php if ($client['email']): ?>
                                            <br><small class="text-muted"><?= e($client['email']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="tel:<?= e($client['telephone']) ?>">
                                    <?= formatPhone($client['telephone']) ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= ucfirst($client['type_client']) ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold"><?= $client['nb_reservations'] ?></span>
                            </td>
                            <td>
                                <span class="fw-semibold text-success"><?= formatMoney($client['montant_total']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($client['statut']) ?>">
                                    <?= translateStatus($client['statut']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= url('clients/voir.php?id=' . $client['id']) ?>">
                                                <i class="fas fa-eye me-2"></i>Voir le profil
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('clients/modifier.php?id=' . $client['id']) ?>">
                                                <i class="fas fa-edit me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('reservations/nouveau.php?client=' . $client['id']) ?>">
                                                <i class="fas fa-calendar-plus me-2"></i>Nouvelle réservation
                                            </a>
                                        </li>
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
                <?= paginationHtml($pagination, url('clients/index.php')) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
