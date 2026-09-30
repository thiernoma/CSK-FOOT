<?php
/**
 * Rapport financier détaillé
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Cotisation.php';

Auth::requireLogin();

$pageTitle = 'Rapport Financier';
$breadcrumb = [
    ['label' => 'Rapports', 'url' => url('rapports/index.php')],
    ['label' => 'Rapport Financier']
];

// Période sélectionnée
$typePeriode = sanitize(get('type_periode', 'mois')); // 'mois' | 'annee' | 'intervalle'
$mois = (int)get('mois') ?: date('n');
$annee = (int)get('annee') ?: date('Y');
$dateDebutInput = sanitize(get('date_debut', ''));
$dateFinInput   = sanitize(get('date_fin', ''));

if ($typePeriode === 'annee') {
    $dateDebut = "$annee-01-01";
    $dateFin = "$annee-12-31";
    $periodeLabel = "Année $annee";
} elseif ($typePeriode === 'intervalle') {
    $dateDebut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
    $dateFin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
    if ($dateFin < $dateDebut) [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
    $periodeLabel = 'Du ' . formatDate($dateDebut) . ' au ' . formatDate($dateFin);
} else {
    $dateDebut = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
    $dateFin = date('Y-m-t', strtotime($dateDebut));
    $periodeLabel = getMonthName($mois) . " $annee";
}

// ==================== RECETTES ====================

// Recettes des réservations
$recettesReservations = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_reservations,
        SUM(montant) as montant_total,
        SUM(montant_paye) as montant_encaisse,
        SUM(montant - montant_paye) as montant_restant
     FROM reservations
     WHERE date_reservation BETWEEN :debut AND :fin
     AND statut_reservation != 'annulee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes par terrain — base caisse : argent réellement encaissé sur la période
// (table paiements, rattaché au terrain via la réservation). Cohérent avec le
// total RECETTES et le résumé. Les cotisations (reservation_id NULL) sont exclues.
$recettesParTerrain = Database::fetchAll(
    "SELECT t.nom as terrain,
            COUNT(DISTINCT p.reservation_id) as nb_reservations,
            COALESCE(SUM(p.montant), 0) as montant_encaisse
     FROM terrains t
     LEFT JOIN reservations r ON r.terrain_id = t.id
     LEFT JOIN paiements p ON p.reservation_id = r.id
        AND p.type_paiement = 'paiement'
        AND DATE(p.created_at) BETWEEN :debut AND :fin
     GROUP BY t.id
     ORDER BY montant_encaisse DESC, t.nom ASC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes par mode de paiement
$recettesParMode = Database::fetchAll(
    "SELECT mode_paiement,
            COUNT(*) as nb_paiements,
            SUM(montant) as total
     FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
     AND type_paiement = 'paiement'
     GROUP BY mode_paiement
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes des cotisations académie
$recettesCotisations = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_cotisations,
        SUM(montant) as montant_total,
        SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant_encaisse
     FROM cotisations
     WHERE (annee = :annee AND mois = :mois) OR date_paiement BETWEEN :debut AND :fin",
    ['annee' => $annee, 'mois' => $mois, 'debut' => $dateDebut, 'fin' => $dateFin]
);

// Total des recettes (paiements réels)
$totalRecettes = Database::fetchOne(
    "SELECT SUM(montant) as total FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
     AND type_paiement = 'paiement'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes par catégorie d'encaissement (toutes catégories, hors cotisation_academie pour éviter
// le double-comptage car les cotisations sont sommées séparément depuis leur table)
$recettesParCategorie = Database::fetchAll(
    "SELECT
        COALESCE(c.libelle, 'Sans catégorie') as libelle,
        COALESCE(c.icone, 'fa-coins') as icone,
        COALESCE(c.couleur, '#6c757d') as couleur,
        SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                 WHEN p.type_paiement = 'remboursement' THEN -p.montant END) as total,
        COUNT(*) as nb
     FROM paiements p
     LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
     WHERE DATE(p.created_at) BETWEEN :debut AND :fin
       AND (c.code IS NULL OR c.code != 'cotisation_academie')
     GROUP BY p.categorie_id
     HAVING total <> 0
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Cotisations payées sur la période (ajoutées comme ligne virtuelle au breakdown)
$totalCotisationsPeriode = (float)(Database::fetchOne(
    "SELECT COALESCE(SUM(montant), 0) as total
     FROM cotisations
     WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
)['total'] ?? 0);
$nbCotisationsPeriode = (int)(Database::fetchOne(
    "SELECT COUNT(*) as nb FROM cotisations
     WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
)['nb'] ?? 0);
if ($totalCotisationsPeriode > 0) {
    $recettesParCategorie[] = [
        'libelle' => 'Cotisations académie',
        'icone' => 'fa-graduation-cap',
        'couleur' => '#E8631A',
        'total' => $totalCotisationsPeriode,
        'nb' => $nbCotisationsPeriode
    ];
    usort($recettesParCategorie, fn($a, $b) => $b['total'] <=> $a['total']);
}

// ==================== DÉPENSES ====================

// Total des dépenses
$totalDepenses = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_depenses,
        SUM(montant) as montant_total
     FROM depenses
     WHERE date_depense BETWEEN :debut AND :fin
     AND statut = 'payee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Dépenses par catégorie
$depensesParCategorie = Database::fetchAll(
    "SELECT c.nom as categorie, c.icone,
            COUNT(d.id) as nb_depenses,
            SUM(d.montant) as montant_total
     FROM categories_depenses c
     LEFT JOIN depenses d ON c.id = d.categorie_id
        AND d.date_depense BETWEEN :debut AND :fin
        AND d.statut = 'payee'
     GROUP BY c.id
     HAVING montant_total > 0
     ORDER BY montant_total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Liste des dépenses détaillées
$listeDepenses = Database::fetchAll(
    "SELECT d.*, c.nom as categorie_nom, t.nom as terrain_nom
     FROM depenses d
     JOIN categories_depenses c ON d.categorie_id = c.id
     LEFT JOIN terrains t ON d.terrain_id = t.id
     WHERE d.date_depense BETWEEN :debut AND :fin
     AND d.statut = 'payee'
     ORDER BY d.date_depense DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// ==================== BILAN ====================

$bilanMensuel = ($totalRecettes['total'] ?? 0) - ($totalDepenses['montant_total'] ?? 0);

// Évolution quotidienne recettes vs dépenses
// Récupérer les recettes par jour
$recettesParJour = Database::fetchAll(
    "SELECT DATE(created_at) as date, SUM(montant) as total
     FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin AND type_paiement = 'paiement'
     GROUP BY DATE(created_at)",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Récupérer les dépenses par jour
$depensesParJour = Database::fetchAll(
    "SELECT date_depense as date, SUM(montant) as total
     FROM depenses
     WHERE date_depense BETWEEN :debut AND :fin AND statut = 'payee'
     GROUP BY date_depense",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Construire le tableau d'évolution quotidienne
$evolutionQuotidienne = [];
$recettesIndex = array_column($recettesParJour, 'total', 'date');
$depensesIndex = array_column($depensesParJour, 'total', 'date');

$currentDate = new DateTime($dateDebut);
$endDate = new DateTime($dateFin);

while ($currentDate <= $endDate) {
    $dateStr = $currentDate->format('Y-m-d');
    $evolutionQuotidienne[] = [
        'date' => $dateStr,
        'recettes' => $recettesIndex[$dateStr] ?? 0,
        'depenses' => $depensesIndex[$dateStr] ?? 0
    ];
    $currentDate->modify('+1 day');
}

// Comparaison avec le mois précédent
$moisPrec = $mois == 1 ? 12 : $mois - 1;
$anneePrec = $mois == 1 ? $annee - 1 : $annee;
$dateDebutPrec = "$anneePrec-" . str_pad($moisPrec, 2, '0', STR_PAD_LEFT) . "-01";
$dateFinPrec = date('Y-m-t', strtotime($dateDebutPrec));

$recettesMoisPrec = Database::fetchOne(
    "SELECT SUM(montant) as total FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin AND type_paiement = 'paiement'",
    ['debut' => $dateDebutPrec, 'fin' => $dateFinPrec]
);

$depensesMoisPrec = Database::fetchOne(
    "SELECT SUM(montant) as total FROM depenses
     WHERE date_depense BETWEEN :debut AND :fin AND statut = 'payee'",
    ['debut' => $dateDebutPrec, 'fin' => $dateFinPrec]
);

// Calcul des variations
$variationRecettes = ($recettesMoisPrec['total'] ?? 0) > 0
    ? round((($totalRecettes['total'] ?? 0) - ($recettesMoisPrec['total'] ?? 0)) / ($recettesMoisPrec['total'] ?? 1) * 100, 1)
    : 0;

$variationDepenses = ($depensesMoisPrec['total'] ?? 0) > 0
    ? round((($totalDepenses['montant_total'] ?? 0) - ($depensesMoisPrec['total'] ?? 0)) / ($depensesMoisPrec['total'] ?? 1) * 100, 1)
    : 0;

include VIEWS_PATH . 'layouts/header.php';
?>

<style>
@media print {
    .no-print { display: none !important; }
    .card { break-inside: avoid; }
}
.financial-card {
    border-left: 4px solid;
    transition: transform 0.2s;
}
.financial-card:hover {
    transform: translateY(-2px);
}
.financial-card.recettes { border-left-color: #28A745; }
.financial-card.depenses { border-left-color: #DC3545; }
.financial-card.bilan { border-left-color: #1A3A6B; }
.trend-up { color: #28A745; }
.trend-down { color: #DC3545; }
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Rapport Financier</h4>
        <p class="text-muted mb-0"><?= $periodeLabel ?></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimer
        </button>
        <a href="<?= url('rapports/export-financier.php?' . http_build_query([
                'type_periode' => $typePeriode,
                'mois' => $mois,
                'annee' => $annee,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ])) ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>Exporter Excel
        </a>
    </div>
</div>

<!-- Filtres période -->
<div class="card mb-4 no-print">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center" id="filterForm">
            <div class="col-auto">
                <label class="form-label mb-0 me-2">Période :</label>
            </div>
            <div class="col-auto">
                <select class="form-select form-select-sm" name="type_periode" id="typePeriode" onchange="toggleFilters()">
                    <option value="mois"       <?= $typePeriode === 'mois' ? 'selected' : '' ?>>Mensuel</option>
                    <option value="annee"      <?= $typePeriode === 'annee' ? 'selected' : '' ?>>Annuel</option>
                    <option value="intervalle" <?= $typePeriode === 'intervalle' ? 'selected' : '' ?>>Personnalisé</option>
                </select>
            </div>

            <div class="col-auto" id="moisFilter" style="display: <?= $typePeriode === 'mois' ? 'block' : 'none' ?>">
                <select class="form-select form-select-sm" name="mois">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $mois == $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-auto" id="anneeFilter" style="display: <?= in_array($typePeriode, ['mois','annee']) ? 'block' : 'none' ?>">
                <select class="form-select form-select-sm" name="annee">
                    <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                        <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-auto" id="intervalleFilter" style="display: <?= $typePeriode === 'intervalle' ? 'block' : 'none' ?>">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Du</span>
                    <input type="date" class="form-control form-control-sm" name="date_debut"
                           value="<?= e($dateDebut) ?>">
                    <span class="input-group-text">au</span>
                    <input type="date" class="form-control form-control-sm" name="date_fin"
                           value="<?= e($dateFin) ?>">
                </div>
            </div>

            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-search me-1"></i>Afficher
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleFilters() {
    var type = document.getElementById('typePeriode').value;
    document.getElementById('moisFilter').style.display       = (type === 'mois')       ? 'block' : 'none';
    document.getElementById('anneeFilter').style.display      = (type === 'mois' || type === 'annee') ? 'block' : 'none';
    document.getElementById('intervalleFilter').style.display = (type === 'intervalle') ? 'block' : 'none';
}
</script>

<!-- KPIs principaux -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card financial-card recettes h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-success mb-2">
                            <i class="fas fa-arrow-up me-2"></i>RECETTES
                        </h6>
                        <h2 class="mb-0"><?= formatMoney($totalRecettes['total'] ?? 0) ?></h2>
                    </div>
                    <div class="text-end">
                        <?php if ($variationRecettes != 0): ?>
                        <span class="badge <?= $variationRecettes > 0 ? 'bg-success' : 'bg-danger' ?>">
                            <i class="fas fa-arrow-<?= $variationRecettes > 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($variationRecettes) ?>%
                        </span>
                        <br><small class="text-muted">vs mois précédent</small>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h5 mb-0"><?= $recettesReservations['nb_reservations'] ?? 0 ?></div>
                        <small class="text-muted">Réservations</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 mb-0"><?= $recettesCotisations['nb_cotisations'] ?? 0 ?></div>
                        <small class="text-muted">Cotisations</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card financial-card depenses h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h6 class="text-danger mb-2">
                            <i class="fas fa-arrow-down me-2"></i>DÉPENSES
                        </h6>
                        <h2 class="mb-0"><?= formatMoney($totalDepenses['montant_total'] ?? 0) ?></h2>
                    </div>
                    <div class="text-end">
                        <?php if ($variationDepenses != 0): ?>
                        <span class="badge <?= $variationDepenses < 0 ? 'bg-success' : 'bg-danger' ?>">
                            <i class="fas fa-arrow-<?= $variationDepenses > 0 ? 'up' : 'down' ?>"></i>
                            <?= abs($variationDepenses) ?>%
                        </span>
                        <br><small class="text-muted">vs mois précédent</small>
                        <?php endif; ?>
                    </div>
                </div>
                <hr>
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h5 mb-0"><?= $totalDepenses['nb_depenses'] ?? 0 ?></div>
                        <small class="text-muted">Transactions</small>
                    </div>
                    <div class="col-6">
                        <div class="h5 mb-0"><?= count($depensesParCategorie) ?></div>
                        <small class="text-muted">Catégories</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card financial-card bilan h-100">
            <div class="card-body">
                <h6 class="text-primary mb-2">
                    <i class="fas fa-balance-scale me-2"></i>BILAN
                </h6>
                <h2 class="mb-0 <?= $bilanMensuel >= 0 ? 'text-success' : 'text-danger' ?>">
                    <?= $bilanMensuel >= 0 ? '+' : '' ?><?= formatMoney($bilanMensuel) ?>
                </h2>
                <hr>
                <div class="d-flex justify-content-between">
                    <span>Marge</span>
                    <strong class="<?= $bilanMensuel >= 0 ? 'text-success' : 'text-danger' ?>">
                        <?php
                        $marge = ($totalRecettes['total'] ?? 0) > 0
                            ? round($bilanMensuel / ($totalRecettes['total'] ?? 1) * 100, 1)
                            : 0;
                        ?>
                        <?= $marge ?>%
                    </strong>
                </div>
                <div class="progress mt-2" style="height: 8px;">
                    <?php if ($bilanMensuel >= 0): ?>
                    <div class="progress-bar bg-success" style="width: <?= min(100, abs($marge)) ?>%"></div>
                    <?php else: ?>
                    <div class="progress-bar bg-danger" style="width: <?= min(100, abs($marge)) ?>%"></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Graphique évolution -->
<div class="row g-4 mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-area me-2"></i>Évolution quotidienne Recettes vs Dépenses</h6>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" height="80"></canvas>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Recettes par terrain -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-futbol me-2"></i>Recettes par terrain</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Terrain</th>
                                <th class="text-center">Réservations</th>
                                <th class="text-end">Encaissé</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recettesParTerrain as $t): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($t['terrain']) ?></td>
                                <td class="text-center"><?= $t['nb_reservations'] ?? 0 ?></td>
                                <td class="text-end text-success fw-semibold"><?= formatMoney($t['montant_encaisse'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Total</th>
                                <th class="text-center"><?= array_sum(array_column($recettesParTerrain, 'nb_reservations')) ?></th>
                                <th class="text-end text-success"><?= formatMoney(array_sum(array_column($recettesParTerrain, 'montant_encaisse'))) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recettes par mode de paiement -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Recettes par mode de paiement</h6>
            </div>
            <div class="card-body">
                <canvas id="paiementChart" height="200"></canvas>
                <hr>
                <div class="table-responsive">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <?php foreach ($recettesParMode as $mode): ?>
                            <tr>
                                <td>
                                    <?php
                                    $icones = [
                                        'especes' => 'fa-money-bill-wave',
                                        'wave' => 'fa-mobile-alt',
                                        'om' => 'fa-mobile-alt',
                                        'carte' => 'fa-credit-card',
                                        'virement' => 'fa-university',
                                        'cheque' => 'fa-money-check'
                                    ];
                                    ?>
                                    <i class="fas <?= $icones[$mode['mode_paiement']] ?? 'fa-money-bill' ?> me-2"></i>
                                    <?= ucfirst($mode['mode_paiement']) ?>
                                </td>
                                <td class="text-center"><?= $mode['nb_paiements'] ?> paiements</td>
                                <td class="text-end fw-semibold"><?= formatMoney($mode['total']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Recettes par catégorie d'encaissement -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Recettes par catégorie d'encaissement</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recettesParCategorie)): ?>
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">Aucune recette sur la période</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th class="text-center">Opérations</th>
                                <th class="text-end">Montant net</th>
                                <th class="text-end" style="width: 30%;">Part</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalCatRec = array_sum(array_column($recettesParCategorie, 'total'));
                            foreach ($recettesParCategorie as $cat):
                                $pct = $totalCatRec > 0 ? round(($cat['total'] / $totalCatRec) * 100, 1) : 0;
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
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="2">Total</th>
                                <th class="text-end text-success"><?= formatMoney($totalCatRec) ?></th>
                                <th>100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Dépenses par catégorie -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Dépenses par catégorie</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($depensesParCategorie)): ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-check-circle fa-2x mb-2"></i>
                    <p class="mb-0">Aucune dépense ce mois</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th class="text-center">Nb</th>
                                <th class="text-end">Montant</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalDep = $totalDepenses['montant_total'] ?? 1;
                            foreach ($depensesParCategorie as $cat):
                                $pct = round(($cat['montant_total'] / $totalDep) * 100, 1);
                            ?>
                            <tr>
                                <td>
                                    <i class="fas <?= $cat['icone'] ?? 'fa-tag' ?> me-2 text-muted"></i>
                                    <?= e($cat['categorie']) ?>
                                </td>
                                <td class="text-center"><?= $cat['nb_depenses'] ?></td>
                                <td class="text-end fw-semibold text-danger"><?= formatMoney($cat['montant_total']) ?></td>
                                <td class="text-end">
                                    <div class="d-flex align-items-center justify-content-end">
                                        <div class="progress flex-grow-1 me-2" style="height: 6px; max-width: 60px;">
                                            <div class="progress-bar bg-danger" style="width: <?= $pct ?>%"></div>
                                        </div>
                                        <small><?= $pct ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th>Total</th>
                                <th class="text-center"><?= $totalDepenses['nb_depenses'] ?? 0 ?></th>
                                <th class="text-end text-danger"><?= formatMoney($totalDepenses['montant_total'] ?? 0) ?></th>
                                <th class="text-end">100%</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Liste détaillée des dépenses -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-list me-2"></i>Détail des dépenses</h6>
                <span class="badge bg-white text-danger"><?= count($listeDepenses) ?></span>
            </div>
            <div class="card-body p-0" style="max-height: 400px; overflow-y: auto;">
                <?php if (empty($listeDepenses)): ?>
                <div class="text-center py-4 text-muted">
                    <p class="mb-0">Aucune dépense enregistrée</p>
                </div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($listeDepenses as $dep): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= e($dep['description']) ?></strong>
                                <br>
                                <small class="text-muted">
                                    <i class="fas fa-tag me-1"></i><?= e($dep['categorie_nom']) ?>
                                    <?php if ($dep['terrain_nom']): ?>
                                        <i class="fas fa-futbol ms-2 me-1"></i><?= e($dep['terrain_nom']) ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="text-end">
                                <strong class="text-danger"><?= formatMoney($dep['montant']) ?></strong>
                                <br>
                                <small class="text-muted"><?= formatDate($dep['date_depense'], 'd/m/Y') ?></small>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Résumé imprimable -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Résumé financier - <?= $periodeLabel ?></h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-success"><i class="fas fa-plus-circle me-2"></i>RECETTES</h6>
                <table class="table table-sm">
                    <?php
                    // Décomposition du total encaissé (base caisse = table paiements sur la période)
                    // pour que les lignes additionnent exactement le Total Recettes affiché.
                    // Les cotisations payées génèrent aussi une ligne dans paiements, donc le reste
                    // (réservations + autres encaissements) = total - cotisations de la période.
                    $recettesEncaisseesReservations = ($totalRecettes['total'] ?? 0) - $totalCotisationsPeriode;
                    ?>
                    <tr>
                        <td>Réservations encaissées</td>
                        <td class="text-end"><?= formatMoney($recettesEncaisseesReservations) ?></td>
                    </tr>
                    <tr>
                        <td>Cotisations académie</td>
                        <td class="text-end"><?= formatMoney($totalCotisationsPeriode) ?></td>
                    </tr>
                    <tr class="table-success">
                        <th>Total Recettes</th>
                        <th class="text-end"><?= formatMoney($totalRecettes['total'] ?? 0) ?></th>
                    </tr>
                </table>
            </div>
            <div class="col-md-6">
                <h6 class="text-danger"><i class="fas fa-minus-circle me-2"></i>DÉPENSES</h6>
                <table class="table table-sm">
                    <?php foreach (array_slice($depensesParCategorie, 0, 5) as $cat): ?>
                    <tr>
                        <td><?= e($cat['categorie']) ?></td>
                        <td class="text-end"><?= formatMoney($cat['montant_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="table-danger">
                        <th>Total Dépenses</th>
                        <th class="text-end"><?= formatMoney($totalDepenses['montant_total'] ?? 0) ?></th>
                    </tr>
                </table>
            </div>
        </div>
        <hr>
        <div class="row">
            <div class="col-12">
                <table class="table table-bordered">
                    <tr class="<?= $bilanMensuel >= 0 ? 'table-success' : 'table-danger' ?>">
                        <th class="text-center" style="font-size: 1.2em;">
                            RÉSULTAT NET DU MOIS
                        </th>
                        <th class="text-center" style="font-size: 1.5em;">
                            <?= $bilanMensuel >= 0 ? '+' : '' ?><?= formatMoney($bilanMensuel) ?>
                        </th>
                    </tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
// Préparer les données pour les graphiques
$evolutionLabels = array_map(fn($e) => formatDate($e['date'], 'd'), $evolutionQuotidienne);
$evolutionRecettes = array_map(fn($e) => $e['recettes'], $evolutionQuotidienne);
$evolutionDepenses = array_map(fn($e) => $e['depenses'], $evolutionQuotidienne);

$paiementLabels = array_map(fn($p) => ucfirst($p['mode_paiement']), $recettesParMode);
$paiementData = array_map(fn($p) => $p['total'], $recettesParMode);

$inlineJs = "
// Graphique évolution
new Chart(document.getElementById('evolutionChart'), {
    type: 'line',
    data: {
        labels: " . json_encode($evolutionLabels) . ",
        datasets: [
            {
                label: 'Recettes',
                data: " . json_encode($evolutionRecettes) . ",
                borderColor: '#28A745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Dépenses',
                data: " . json_encode($evolutionDepenses) . ",
                borderColor: '#DC3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        interaction: {
            intersect: false,
            mode: 'index'
        },
        plugins: {
            legend: { position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' F';
                    }
                }
            }
        }
    }
});

// Graphique modes de paiement
new Chart(document.getElementById('paiementChart'), {
    type: 'doughnut',
    data: {
        labels: " . json_encode($paiementLabels) . ",
        datasets: [{
            data: " . json_encode($paiementData) . ",
            backgroundColor: ['#28A745', '#FF6B00', '#00B2FF', '#6c757d', '#1A3A6B', '#ffc107']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'right' }
        }
    }
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
