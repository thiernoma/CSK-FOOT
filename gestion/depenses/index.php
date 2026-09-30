<?php
/**
 * Liste des dépenses
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Depense.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Gestion des Dépenses';

// Filtres
$periode = get('periode', 'mois'); // jour, mois, annee, intervalle
$dateDebutInput = sanitize(get('date_debut', ''));
$dateFinInput   = sanitize(get('date_fin', ''));

$filters = [
    'categorie_id' => get('categorie') ?: null,
    'terrain_id' => get('terrain') ?: null,
    'statut' => get('statut') ?: null,
    'mois' => get('mois') ?: date('m'),
    'annee' => get('annee') ?: date('Y'),
    'periode' => $periode
];

// Bornes de l'intervalle (utilisées pour SQL et affichage)
$intervalleDebut = null;
$intervalleFin   = null;

if ($periode === 'jour') {
    // Filtrer uniquement aujourd'hui
    $filters['date_jour'] = date('Y-m-d');
} elseif ($periode === 'intervalle') {
    $intervalleDebut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
    $intervalleFin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
    if ($intervalleFin < $intervalleDebut) {
        [$intervalleDebut, $intervalleFin] = [$intervalleFin, $intervalleDebut];
    }
    $filters['date_debut'] = $intervalleDebut;
    $filters['date_fin']   = $intervalleFin;
}

// Récupérer les données
$depenses = Depense::getAll($filters);
$categories = Depense::getCategories();
$terrains = Terrain::getAll('actif');

// Stats / bilan : on s'aligne sur la période sélectionnée pour les KPIs
$statsPeriode = $periode === 'intervalle' ? 'intervalle' : 'mois';
$stats = Depense::getStats($statsPeriode, null, $intervalleDebut, $intervalleFin);
$statsAnnee = Depense::getStats('annee', $filters['annee']);
$parCategorie = Depense::getByCategorie($statsPeriode, null, $intervalleDebut, $intervalleFin);
$bilan = Depense::getBilanFinancier($statsPeriode, null, $intervalleDebut, $intervalleFin);

// Mois pour le filtre
$moisNoms = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-file-invoice-dollar me-2"></i><?= $pageTitle ?></h1>
            <p class="text-muted mb-0">Suivi des dépenses et charges du complexe</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= url('depenses/categories.php') ?>" class="btn btn-outline-secondary">
                <i class="fas fa-tags me-2"></i>Catégories
            </a>
            <?php endif; ?>
            <a href="<?= url('depenses/nouveau.php') ?>" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Nouvelle Dépense
            </a>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-danger bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-arrow-down text-danger fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Dépenses <?= $periode === 'intervalle' ? 'sur la période' : ($periode === 'jour' ? 'du jour' : 'du mois') ?></h6>
                            <h3 class="mb-0"><?= formatMoney($stats['total_payees'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-secondary bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-ban text-secondary fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Annulées</h6>
                            <h3 class="mb-0"><?= formatMoney($stats['total_annulees'] ?? 0) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-chart-line text-success fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Revenus <?= $periode === 'intervalle' ? 'sur la période' : ($periode === 'jour' ? 'du jour' : 'du mois') ?></h6>
                            <h3 class="mb-0"><?= formatMoney($bilan['total_revenus']) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-<?= $bilan['benefice'] >= 0 ? 'primary' : 'danger' ?> bg-opacity-10 rounded-circle p-3">
                                <i class="fas fa-balance-scale text-<?= $bilan['benefice'] >= 0 ? 'primary' : 'danger' ?> fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Bénéfice net</h6>
                            <h3 class="mb-0 text-<?= $bilan['benefice'] >= 0 ? 'success' : 'danger' ?>">
                                <?= formatMoney($bilan['benefice']) ?>
                            </h3>
                            <small class="text-muted">Marge: <?= $bilan['marge'] ?>%</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Liste des dépenses -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">
                                Liste des dépenses
                                <?php if ($periode === 'jour'): ?>
                                <span class="badge bg-primary ms-2">Aujourd'hui (<?= date('d/m/Y') ?>)</span>
                                <?php elseif ($periode === 'intervalle'): ?>
                                <span class="badge bg-info ms-2">Du <?= date('d/m/Y', strtotime($intervalleDebut)) ?> au <?= date('d/m/Y', strtotime($intervalleFin)) ?></span>
                                <?php else: ?>
                                <span class="badge bg-secondary ms-2"><?= $moisNoms[(int)$filters['mois']] ?> <?= $filters['annee'] ?></span>
                                <?php endif; ?>
                            </h5>
                        </div>
                        <div class="col-auto">
                            <!-- Filtres -->
                            <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
                                <!-- Filtre période rapide -->
                                <div class="btn-group btn-group-sm" role="group">
                                    <a href="?periode=jour" class="btn btn-<?= $periode === 'jour' ? 'primary' : 'outline-primary' ?>">
                                        <i class="fas fa-calendar-day me-1"></i>Aujourd'hui
                                    </a>
                                    <a href="?periode=mois&mois=<?= date('m') ?>&annee=<?= date('Y') ?>"
                                       class="btn btn-<?= $periode === 'mois' ? 'primary' : 'outline-primary' ?>">
                                        <i class="fas fa-calendar-week me-1"></i>Mois
                                    </a>
                                    <a href="?periode=intervalle&date_debut=<?= e($intervalleDebut ?? date('Y-m-01')) ?>&date_fin=<?= e($intervalleFin ?? date('Y-m-t')) ?>"
                                       class="btn btn-<?= $periode === 'intervalle' ? 'primary' : 'outline-primary' ?>">
                                        <i class="fas fa-calendar-range me-1"></i>Intervalle
                                    </a>
                                </div>

                                <?php if ($periode === 'intervalle'): ?>
                                <div class="input-group input-group-sm" style="width: auto;">
                                    <span class="input-group-text">Du</span>
                                    <input type="date" class="form-control form-control-sm" name="date_debut"
                                           value="<?= e($intervalleDebut) ?>" required>
                                    <span class="input-group-text">au</span>
                                    <input type="date" class="form-control form-control-sm" name="date_fin"
                                           value="<?= e($intervalleFin) ?>" required>
                                </div>
                                <?php elseif ($periode !== 'jour'): ?>
                                <select name="mois" class="form-select form-select-sm" style="width: auto;">
                                    <?php foreach ($moisNoms as $num => $nom): ?>
                                    <option value="<?= $num ?>" <?= $filters['mois'] == $num ? 'selected' : '' ?>>
                                        <?= $nom ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <select name="annee" class="form-select form-select-sm" style="width: auto;">
                                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                                    <option value="<?= $y ?>" <?= $filters['annee'] == $y ? 'selected' : '' ?>>
                                        <?= $y ?>
                                    </option>
                                    <?php endfor; ?>
                                </select>
                                <?php endif; ?>

                                <select name="categorie" class="form-select form-select-sm" style="width: auto;">
                                    <option value="">Toutes catégories</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>" <?= $filters['categorie_id'] == $cat['id'] ? 'selected' : '' ?>>
                                        <?= e($cat['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="periode" value="<?= e($periode) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-filter"></i>
                                </button>

                                <!-- Boutons Export -->
                                <?php
                                $exportParams = [
                                    'periode' => $periode,
                                    'mois' => $filters['mois'],
                                    'annee' => $filters['annee'],
                                    'categorie' => $filters['categorie_id']
                                ];
                                if ($periode === 'intervalle') {
                                    $exportParams['date_debut'] = $intervalleDebut;
                                    $exportParams['date_fin']   = $intervalleFin;
                                }
                                ?>
                                <div class="btn-group btn-group-sm ms-2">
                                    <a href="<?= url('depenses/export.php') ?>?<?= http_build_query(array_merge(['format' => 'excel'], $exportParams)) ?>"
                                       class="btn btn-outline-success" title="Export Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </a>
                                    <a href="<?= url('depenses/export.php') ?>?<?= http_build_query(array_merge(['format' => 'pdf'], $exportParams)) ?>"
                                       class="btn btn-outline-danger" title="Export PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($depenses)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-invoice text-muted fa-3x mb-3"></i>
                        <p class="text-muted">Aucune dépense pour cette période</p>
                    </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Libellé</th>
                                    <th>Catégorie</th>
                                    <th>Montant</th>
                                    <th>Statut</th>
                                    <th width="100">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($depenses as $depense): ?>
                                <tr>
                                    <td>
                                        <span class="text-muted"><?= date('d/m/Y', strtotime($depense['date_depense'])) ?></span>
                                    </td>
                                    <td>
                                        <strong><?= e($depense['libelle']) ?></strong>
                                        <?php if ($depense['fournisseur']): ?>
                                        <br><small class="text-muted"><?= e($depense['fournisseur']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $depense['couleur'] ?>;">
                                            <i class="fas <?= $depense['icone'] ?> me-1"></i>
                                            <?= e($depense['categorie_nom']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong class="text-danger"><?= formatMoney($depense['montant']) ?></strong>
                                    </td>
                                    <td>
                                        <?php
                                        $statutClass = match($depense['statut']) {
                                            'payee' => 'success',
                                            'annulee' => 'secondary',
                                            default => 'success'
                                        };
                                        $statutLabel = match($depense['statut']) {
                                            'payee' => 'Payée',
                                            'annulee' => 'Annulée',
                                            default => 'Payée'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statutClass ?>"><?= $statutLabel ?></span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-info"
                                                    onclick="showDetails(<?= htmlspecialchars(json_encode($depense), ENT_QUOTES, 'UTF-8') ?>)"
                                                    title="Voir détails">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <?php if ($depense['statut'] !== 'annulee'): ?>
                                            <button type="button" class="btn btn-outline-warning"
                                                    onclick="confirmAnnuler(<?= $depense['id'] ?>, '<?= e($depense['libelle']) ?>')"
                                                    title="Annuler">
                                                <i class="fas fa-ban"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
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

        <!-- Répartition par catégorie -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Répartition par catégorie</h5>
                </div>
                <div class="card-body">
                    <canvas id="chartCategories" height="200"></canvas>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0">Top catégories <?= $periode === 'intervalle' ? 'sur la période' : ($periode === 'jour' ? "aujourd'hui" : 'ce mois') ?></h5>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <?php foreach (array_slice($parCategorie, 0, 5) as $cat): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <i class="fas <?= $cat['icone'] ?> me-2" style="color: <?= $cat['couleur'] ?>;"></i>
                                <?= e($cat['nom']) ?>
                            </div>
                            <span class="badge bg-danger"><?= formatMoney($cat['total']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de détails de la dépense -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Détails de la dépense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Libellé</label>
                        <p class="fw-bold mb-2" id="detail-libelle"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Montant</label>
                        <p class="fw-bold text-danger mb-2" id="detail-montant"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Catégorie</label>
                        <p class="mb-2" id="detail-categorie"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Date</label>
                        <p class="mb-2" id="detail-date"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Fournisseur</label>
                        <p class="mb-2" id="detail-fournisseur"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Statut</label>
                        <p class="mb-2" id="detail-statut"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Mode de paiement</label>
                        <p class="mb-2" id="detail-mode"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Référence</label>
                        <p class="mb-2" id="detail-reference"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Terrain concerné</label>
                        <p class="mb-2" id="detail-terrain"></p>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Récurrence</label>
                        <p class="mb-2" id="detail-recurrence"></p>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Description</label>
                        <p class="mb-2" id="detail-description"></p>
                    </div>
                    <div class="col-12">
                        <label class="text-muted small">Notes</label>
                        <p class="mb-2" id="detail-notes"></p>
                    </div>
                    <div class="col-12" id="detail-pj-container" style="display:none;">
                        <label class="text-muted small">Pièce jointe</label>
                        <div class="border rounded p-3 bg-light" id="detail-pj">
                            <!-- Pièce jointe affichée ici -->
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de confirmation d'annulation -->
<div class="modal fade" id="annulerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-ban me-2 text-warning"></i>Confirmer l'annulation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Êtes-vous sûr de vouloir annuler cette dépense ?</p>
                <p class="mb-0"><strong id="annulerLibelle"></strong></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Non, fermer</button>
                <form id="annulerForm" method="POST" action="<?= url('depenses/annuler.php') ?>">
                    <input type="hidden" name="id" id="annulerId">
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-ban me-1"></i>Oui, annuler
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
function confirmAnnuler(id, libelle) {
    document.getElementById('annulerId').value = id;
    document.getElementById('annulerLibelle').textContent = libelle;
    new bootstrap.Modal(document.getElementById('annulerModal')).show();
}

function showDetails(depense) {
    const baseUrl = '<?= APP_URL ?>';

    // Remplir les détails
    document.getElementById('detail-libelle').textContent = depense.libelle || '-';
    document.getElementById('detail-montant').textContent = new Intl.NumberFormat('fr-FR').format(depense.montant) + ' FCFA';
    document.getElementById('detail-categorie').innerHTML = '<span class="badge" style="background-color:' + (depense.couleur || '#6c757d') + '">' +
        '<i class="fas ' + (depense.icone || 'fa-tag') + ' me-1"></i>' + (depense.categorie_nom || '-') + '</span>';
    document.getElementById('detail-date').textContent = depense.date_depense ? new Date(depense.date_depense).toLocaleDateString('fr-FR') : '-';
    document.getElementById('detail-fournisseur').textContent = depense.fournisseur || '-';

    // Statut avec badge
    const statutLabels = {payee: 'Payée', annulee: 'Annulée'};
    const statutClasses = {payee: 'success', annulee: 'secondary'};
    const statut = depense.statut || 'payee';
    document.getElementById('detail-statut').innerHTML = '<span class="badge bg-' + (statutClasses[statut] || 'success') + '">' +
        (statutLabels[statut] || 'Payée') + '</span>';

    // Mode de paiement
    const modeLabels = {especes: 'Espèces', wave: 'Wave', om: 'Orange Money', carte: 'Carte bancaire', virement: 'Virement', cheque: 'Chèque'};
    document.getElementById('detail-mode').textContent = depense.mode_paiement ? (modeLabels[depense.mode_paiement] || depense.mode_paiement) : '-';

    document.getElementById('detail-reference').textContent = depense.reference_paiement || '-';
    document.getElementById('detail-terrain').textContent = depense.terrain_nom || 'Dépense générale';

    // Récurrence
    const recurrenceLabels = {unique: 'Unique', mensuel: 'Mensuel', trimestriel: 'Trimestriel', annuel: 'Annuel'};
    document.getElementById('detail-recurrence').textContent = depense.recurrence ? (recurrenceLabels[depense.recurrence] || depense.recurrence) : 'Unique';

    document.getElementById('detail-description').textContent = depense.description || '-';
    document.getElementById('detail-notes').textContent = depense.notes || '-';

    // Pièce jointe
    const pjContainer = document.getElementById('detail-pj-container');
    const pjElement = document.getElementById('detail-pj');

    if (depense.piece_jointe) {
        pjContainer.style.display = 'block';
        const pjUrl = baseUrl + '/uploads/depenses/' + depense.piece_jointe;
        const ext = depense.piece_jointe.split('.').pop().toLowerCase();

        if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) {
            pjElement.innerHTML = '<a href="' + pjUrl + '" target="_blank"><img src="' + pjUrl + '" class="img-fluid rounded" style="max-height:300px;" alt="Pièce jointe"></a>';
        } else if (ext === 'pdf') {
            pjElement.innerHTML = '<a href="' + pjUrl + '" target="_blank" class="btn btn-outline-danger"><i class="fas fa-file-pdf me-2"></i>Voir le PDF</a>';
        } else {
            pjElement.innerHTML = '<a href="' + pjUrl + '" target="_blank" class="btn btn-outline-primary"><i class="fas fa-download me-2"></i>Télécharger (' + ext.toUpperCase() + ')</a>';
        }
    } else {
        pjContainer.style.display = 'none';
    }

    // Afficher le modal
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
}

// Graphique répartition
const categoriesData = <?= json_encode(array_map(function($c) {
    return ['nom' => $c['nom'], 'total' => $c['total'], 'couleur' => $c['couleur']];
}, array_filter($parCategorie, fn($c) => $c['total'] > 0))) ?>;

if (categoriesData.length > 0) {
    new Chart(document.getElementById('chartCategories'), {
        type: 'doughnut',
        data: {
            labels: categoriesData.map(c => c.nom),
            datasets: [{
                data: categoriesData.map(c => c.total),
                backgroundColor: categoriesData.map(c => c.couleur)
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
</script>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
