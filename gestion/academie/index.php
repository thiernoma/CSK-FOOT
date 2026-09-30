<?php
/**
 * Liste des membres de l'académie
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MembreAcademie.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();

$pageTitle = 'Académie - Membres';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Membres']
];

// Filtres
$filters = [
    'categorie' => sanitize(get('categorie')),
    'statut' => sanitize(get('statut', 'actif')),
    'entraineur_id' => (int)get('entraineur'),
    'search' => sanitize(get('search')),
    'cotisation_status' => sanitize(get('cotisation'))
];

$page = max(1, (int)get('page', 1));
$result = MembreAcademie::getAll($filters, $page, 20);

// Statistiques
$stats = MembreAcademie::getGlobalStats();

// Liste des entraîneurs pour le filtre
$entraineurs = Entraineur::getAll();

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Membres de l'Académie</h4>
        <p class="text-muted mb-0"><?= $result['total'] ?> membre(s) inscrit(s)</p>
    </div>
    <a href="<?= url('academie/nouveau.php') ?>" class="btn btn-accent">
        <i class="fas fa-plus me-2"></i>Nouveau membre
    </a>
</div>

<!-- Statistiques rapides -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary-light text-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['total_actifs'] ?? 0 ?></div>
                <div class="kpi-label">Membres actifs</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-success text-white">
                <i class="fas fa-user-plus"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['nouveaux_30j'] ?? 0 ?></div>
                <div class="kpi-label">Nouveaux (30j)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning text-white">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $stats['membres_en_retard'] ?? 0 ?></div>
                <div class="kpi-label">En retard</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-info text-white">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($stats['ca_mois'] ?? 0) ?></div>
                <div class="kpi-label">Cotisations du mois</div>
            </div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label small">Rechercher</label>
                <input type="text" class="form-control" name="search"
                       value="<?= e($filters['search']) ?>" placeholder="Nom, prénom, téléphone...">
            </div>
            <div class="col-md-2">
                <label class="form-label small">Catégorie</label>
                <select class="form-select" name="categorie">
                    <option value="">Toutes</option>
                    <?php foreach (CATEGORIES_AGE as $cat => $range): ?>
                        <option value="<?= $cat ?>" <?= $filters['categorie'] === $cat ? 'selected' : '' ?>>
                            <?= $cat ?> (<?= $range['min'] ?>-<?= $range['max'] ?> ans)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <option value="actif" <?= $filters['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                    <option value="suspendu" <?= $filters['statut'] === 'suspendu' ? 'selected' : '' ?>>Suspendu</option>
                    <option value="inactif" <?= $filters['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Cotisation</label>
                <select class="form-select" name="cotisation">
                    <option value="">Tous</option>
                    <option value="a_jour" <?= $filters['cotisation_status'] === 'a_jour' ? 'selected' : '' ?>>À jour</option>
                    <option value="retard" <?= $filters['cotisation_status'] === 'retard' ? 'selected' : '' ?>>En retard</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Entraîneur</label>
                <select class="form-select" name="entraineur">
                    <option value="">Tous</option>
                    <?php foreach ($entraineurs as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filters['entraineur_id'] == $e['id'] ? 'selected' : '' ?>>
                            <?= e($e['prenom'] . ' ' . $e['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des membres -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($result['data'])): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-users fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">Aucun membre trouvé</p>
                <a href="<?= url('academie/nouveau.php') ?>" class="btn btn-primary mt-3">
                    <i class="fas fa-plus me-2"></i>Ajouter un membre
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Membre</th>
                            <th>N° Licence</th>
                            <th>Catégorie</th>
                            <th>Parent / Contact</th>
                            <th>Entraîneur</th>
                            <th>Cotisation</th>
                            <th>Statut</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($result['data'] as $membre): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2" style="width: 40px; height: 40px;">
                                        <?= getInitials($membre['nom'], $membre['prenom']) ?>
                                    </div>
                                    <div>
                                        <a href="<?= url('academie/voir.php?id=' . $membre['id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                            <?= e($membre['prenom'] . ' ' . $membre['nom']) ?>
                                        </a>
                                        <br>
                                        <small class="text-muted">
                                            <?= MembreAcademie::calculateAge($membre['date_naissance']) ?> ans
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <code><?= e($membre['numero_licence']) ?></code>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?= $membre['categorie'] ?></span>
                            </td>
                            <td>
                                <?php if ($membre['nom_parent']): ?>
                                    <small class="text-muted"><?= e($membre['nom_parent']) ?></small><br>
                                <?php endif; ?>
                                <a href="tel:<?= e($membre['telephone_parent']) ?>">
                                    <?= formatPhone($membre['telephone_parent']) ?>
                                </a>
                            </td>
                            <td>
                                <?php if ($membre['entraineur_nom']): ?>
                                    <?= e($membre['entraineur_prenom'] . ' ' . $membre['entraineur_nom']) ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($membre['cotisations_retard'] > 0): ?>
                                    <span class="badge bg-danger">
                                        <?= $membre['cotisations_retard'] ?> en retard
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success">À jour</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= statusBadgeClass($membre['statut']) ?>">
                                    <?= translateStatus($membre['statut']) ?>
                                </span>
                            </td>
                            <td>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item" href="<?= url('academie/voir.php?id=' . $membre['id']) ?>">
                                                <i class="fas fa-eye me-2"></i>Voir le profil
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('academie/modifier.php?id=' . $membre['id']) ?>">
                                                <i class="fas fa-edit me-2"></i>Modifier
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('academie/cotisations.php?membre=' . $membre['id']) ?>">
                                                <i class="fas fa-money-bill me-2"></i>Cotisations
                                            </a>
                                        </li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li>
                                            <a class="dropdown-item" href="<?= url('academie/carte.php?id=' . $membre['id']) ?>">
                                                <i class="fas fa-id-card me-2"></i>Carte membre
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
            <?php if ($result['pages'] > 1): ?>
            <div class="card-footer">
                <?= pagination($result['current_page'], $result['pages'], url('academie/index') . '?' . http_build_query(array_filter($filters))) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
