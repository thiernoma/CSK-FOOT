<?php
/**
 * Liste des paiements
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/CategorieEncaissement.php';

Auth::requireLogin();

$categoriesFiltre = CategorieEncaissement::getAll();

$pageTitle = 'Paiements';
$breadcrumb = [['label' => 'Paiements']];

// Filtres
$dateDebut = get('date_debut', date('Y-m-01'));
$dateFin = get('date_fin', date('Y-m-d'));
$modePaiement = get('mode');

// Récupérer les paiements
$sql = "SELECT p.*, r.numero_ticket, r.date_reservation, r.montant as montant_reservation,
               CONCAT(c.prenom, ' ', c.nom) as client_nom, t.nom as terrain_nom,
               u.nom as recu_par_nom,
               cat.libelle as categorie_libelle, cat.icone as categorie_icone, cat.couleur as categorie_couleur,
               sc.id as session_id, sc.statut as session_statut, sc.heure_ouverture as session_heure
        FROM paiements p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN clients c ON r.client_id = c.id
        LEFT JOIN terrains t ON r.terrain_id = t.id
        LEFT JOIN users u ON p.recu_par = u.id
        LEFT JOIN categories_encaissement cat ON p.categorie_id = cat.id
        LEFT JOIN clotures_caisse sc ON p.session_caisse_id = sc.id
        WHERE DATE(p.created_at) BETWEEN :date_debut AND :date_fin";

$params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

if ($modePaiement) {
    $sql .= " AND p.mode_paiement = :mode";
    $params['mode'] = $modePaiement;
}

$categorieFilter = (int)get('categorie');
if ($categorieFilter) {
    $sql .= " AND p.categorie_id = :cat";
    $params['cat'] = $categorieFilter;
}

$sql .= " ORDER BY p.created_at DESC";

$paiements = Database::fetchAll($sql, $params);

// Totaux globaux (sur la période, sans filtres mode/catégorie pour avoir la vraie photo)
$totaux = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_paiements,
        SUM(CASE WHEN type_paiement = 'paiement' THEN montant ELSE 0 END) as total_encaisse,
        SUM(CASE WHEN type_paiement = 'remboursement' THEN montant ELSE 0 END) as total_rembourse,
        SUM(CASE WHEN mode_paiement = 'especes' AND type_paiement = 'paiement' THEN montant ELSE 0 END) as total_especes,
        SUM(CASE WHEN mode_paiement = 'wave' AND type_paiement = 'paiement' THEN montant ELSE 0 END) as total_wave,
        SUM(CASE WHEN mode_paiement = 'om' AND type_paiement = 'paiement' THEN montant ELSE 0 END) as total_om,
        SUM(CASE WHEN mode_paiement = 'carte' AND type_paiement = 'paiement' THEN montant ELSE 0 END) as total_carte
     FROM paiements
     WHERE DATE(created_at) BETWEEN :date_debut AND :date_fin",
    ['date_debut' => $dateDebut, 'date_fin' => $dateFin]
);
$totalNet = (float)($totaux['total_encaisse'] ?? 0) - (float)($totaux['total_rembourse'] ?? 0);

// Répartition par catégorie d'encaissement (toutes catégories — ce que l'utilisateur veut voir)
$parCategorie = Database::fetchAll(
    "SELECT
        COALESCE(c.libelle, 'Sans catégorie') as libelle,
        COALESCE(c.icone, 'fa-coins') as icone,
        COALESCE(c.couleur, '#6c757d') as couleur,
        SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                 WHEN p.type_paiement = 'remboursement' THEN -p.montant END) as total,
        COUNT(*) as nb
     FROM paiements p
     LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
     WHERE DATE(p.created_at) BETWEEN :date_debut AND :date_fin
     GROUP BY p.categorie_id
     HAVING total <> 0
     ORDER BY total DESC",
    ['date_debut' => $dateDebut, 'date_fin' => $dateFin]
);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="mb-1">Historique des paiements</h4>
        <p class="text-muted mb-0">Du <?= formatDate($dateDebut) ?> au <?= formatDate($dateFin) ?></p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= url('paiements/export.php?' . http_build_query([
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin,
                'mode' => $modePaiement,
                'categorie' => $categorieFilter
            ])) ?>" class="btn btn-outline-success">
            <i class="fas fa-file-csv me-2"></i>Exporter CSV
        </a>
        <a href="<?= url('paiements/nouveau.php') ?>" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>Nouvel encaissement
        </a>
        <a href="<?= url('paiements/ma-caisse.php') ?>" class="btn btn-outline-primary">
            <i class="fas fa-cash-register me-2"></i>Ma caisse
        </a>
        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('paiements/caisse.php') ?>" class="btn btn-primary">
            <i class="fas fa-th-large me-2"></i>Vue d'ensemble
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres en haut de page -->
<div class="card mb-4">
    <div class="card-body py-3">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small mb-1">Date début</label>
                <input type="date" class="form-control form-control-sm" name="date_debut" value="<?= e($dateDebut) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Date fin</label>
                <input type="date" class="form-control form-control-sm" name="date_fin" value="<?= e($dateFin) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Catégorie</label>
                <select class="form-select form-select-sm" name="categorie">
                    <option value="">Toutes</option>
                    <?php foreach ($categoriesFiltre as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= $categorieFilter == $c['id'] ? 'selected' : '' ?>><?= e($c['libelle']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Mode</label>
                <select class="form-select form-select-sm" name="mode">
                    <option value="">Tous</option>
                    <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $modePaiement === $key ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fas fa-search me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs globaux -->
<div class="row g-3 mb-3">
    <div class="col-md-4">
        <div class="card bg-success text-white">
            <div class="card-body text-center py-3">
                <div class="h4 mb-0"><?= formatMoney($totaux['total_encaisse'] ?? 0) ?></div>
                <small>Total encaissé (<?= $totaux['nb_paiements'] ?? 0 ?> opérations)</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-danger text-white">
            <div class="card-body text-center py-3">
                <div class="h4 mb-0">- <?= formatMoney($totaux['total_rembourse'] ?? 0) ?></div>
                <small>Remboursements</small>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-primary text-white">
            <div class="card-body text-center py-3">
                <div class="h4 mb-0"><?= formatMoney($totalNet) ?></div>
                <small>Net (encaissé − remboursé)</small>
            </div>
        </div>
    </div>
</div>

<!-- KPIs par mode de paiement -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-money-bill text-success fa-lg mb-1"></i>
                <div class="h5 mb-0"><?= formatMoney($totaux['total_especes'] ?? 0) ?></div>
                <small class="text-muted">Espèces</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-mobile-alt text-info fa-lg mb-1"></i>
                <div class="h5 mb-0"><?= formatMoney($totaux['total_wave'] ?? 0) ?></div>
                <small class="text-muted">Wave</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-mobile-alt text-warning fa-lg mb-1"></i>
                <div class="h5 mb-0"><?= formatMoney($totaux['total_om'] ?? 0) ?></div>
                <small class="text-muted">Orange Money</small>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card">
            <div class="card-body text-center">
                <i class="fas fa-credit-card text-primary fa-lg mb-1"></i>
                <div class="h5 mb-0"><?= formatMoney($totaux['total_carte'] ?? 0) ?></div>
                <small class="text-muted">Carte</small>
            </div>
        </div>
    </div>
</div>

<!-- Répartition par catégorie d'encaissement -->
<?php if (!empty($parCategorie)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Répartition par catégorie</h6>
        <small class="text-muted"><?= count($parCategorie) ?> catégorie(s)</small>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Catégorie</th>
                        <th class="text-center">Nb opérations</th>
                        <th class="text-end">Net</th>
                        <th style="width: 30%;">Part</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $totalCat = array_sum(array_column($parCategorie, 'total'));
                    foreach ($parCategorie as $cat):
                        $pct = $totalCat > 0 ? round(($cat['total'] / $totalCat) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td>
                            <span class="badge me-2" style="background-color: <?= e($cat['couleur']) ?>;">
                                <i class="fas <?= e($cat['icone']) ?>"></i>
                            </span>
                            <?= e($cat['libelle']) ?>
                        </td>
                        <td class="text-center"><?= $cat['nb'] ?></td>
                        <td class="text-end fw-semibold <?= $cat['total'] < 0 ? 'text-danger' : 'text-success' ?>">
                            <?= formatMoney($cat['total']) ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <div class="progress flex-grow-1" style="height: 8px;">
                                    <div class="progress-bar" role="progressbar"
                                         style="width: <?= max(0, $pct) ?>%; background-color: <?= e($cat['couleur']) ?>;"></div>
                                </div>
                                <small class="text-muted" style="min-width: 50px; text-align: right;"><?= $pct ?>%</small>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Liste des paiements -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($paiements)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-receipt fa-4x mb-3 opacity-50"></i>
                <p class="mb-0">Aucun paiement pour cette période</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date/Heure</th>
                            <th>Catégorie</th>
                            <th>Détail</th>
                            <th>Mode</th>
                            <th>Référence</th>
                            <th class="text-end">Montant</th>
                            <th>Reçu par</th>
                            <th>Caisse</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiements as $p): $isRefund = $p['type_paiement'] === 'remboursement'; ?>
                        <tr class="<?= $isRefund ? 'table-warning' : '' ?>">
                            <td>
                                <?= formatDate($p['created_at'], 'd/m/Y') ?>
                                <br>
                                <small class="text-muted"><?= formatDate($p['created_at'], 'H:i') ?></small>
                            </td>
                            <td>
                                <?php if (!empty($p['categorie_icone'])): ?>
                                    <i class="fas <?= e($p['categorie_icone']) ?> me-1" style="color: <?= e($p['categorie_couleur'] ?? '#666') ?>;"></i>
                                <?php endif; ?>
                                <small><?= e($p['categorie_libelle'] ?? 'Sans catégorie') ?></small>
                                <?php if ($isRefund): ?>
                                    <br><span class="badge bg-danger">Remboursement</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($p['numero_ticket'])): ?>
                                    <a href="<?= url('reservations/voir.php?id=' . $p['reservation_id']) ?>" class="fw-semibold">
                                        <?= e($p['numero_ticket']) ?>
                                    </a>
                                    <br>
                                    <small><?= e($p['client_nom']) ?></small>
                                    <?php if (!empty($p['terrain_nom'])): ?>
                                        <span class="badge bg-light text-dark ms-1"><?= e($p['terrain_nom']) ?></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <small><?= e($p['libelle'] ?? '-') ?></small>
                                <?php endif; ?>
                                <?php if ($isRefund && !empty($p['motif'])): ?>
                                    <br><small class="text-muted fst-italic"><?= e($p['motif']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $modeIcon = match($p['mode_paiement']) {
                                    'especes' => 'fa-money-bill text-success',
                                    'wave' => 'fa-mobile-alt text-info',
                                    'om' => 'fa-mobile-alt text-warning',
                                    'carte' => 'fa-credit-card text-primary',
                                    default => 'fa-money-check'
                                };
                                ?>
                                <i class="fas <?= $modeIcon ?> me-1"></i>
                                <?= ucfirst($p['mode_paiement']) ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= e($p['reference'] ?? '-') ?></small>
                            </td>
                            <td class="text-end">
                                <span class="fw-bold <?= $isRefund ? 'text-danger' : 'text-success' ?>">
                                    <?= $isRefund ? '-' : '+' ?>
                                    <?= formatMoney($p['montant']) ?>
                                </span>
                            </td>
                            <td>
                                <small><?= e(recuParLabel($p['recu_par_nom'] ?? null, $p['mode_paiement'] ?? '')) ?></small>
                            </td>
                            <td>
                                <?php if ($p['session_id']): ?>
                                    <?php
                                    $badgeClass = match($p['session_statut'] ?? '') {
                                        'ouverte'  => 'bg-success',
                                        'cloturee' => 'bg-warning text-dark',
                                        'validee'  => 'bg-primary',
                                        default    => 'bg-secondary'
                                    };
                                    ?>
                                    <span class="badge <?= $badgeClass ?>" title="Session #<?= $p['session_id'] ?> ouverte le <?= formatDate($p['session_heure'], 'd/m H:i') ?>">
                                        #<?= $p['session_id'] ?>
                                    </span>
                                <?php else: ?>
                                    <small class="text-muted">—</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr class="table-light">
                            <td colspan="5" class="text-end fw-bold">Net</td>
                            <td class="text-end fw-bold text-success">
                                <?= formatMoney(($totaux['total_encaisse'] ?? 0) - ($totaux['total_rembourse'] ?? 0)) ?>
                            </td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
