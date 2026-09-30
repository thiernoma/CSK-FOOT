<?php
/**
 * Gestion des cotisations
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Cotisation.php';
require_once APP_PATH . 'models/MembreAcademie.php';

Auth::requireLogin();

$membreId = (int)get('membre');
$membre = null;

if ($membreId) {
    $membre = MembreAcademie::getById($membreId);
    if (!$membre) {
        Session::flash('danger', 'Membre introuvable.');
        redirect(url('academie/index.php'));
    }
    $pageTitle = 'Cotisations - ' . $membre['prenom'] . ' ' . $membre['nom'];
} else {
    $pageTitle = 'Gestion des cotisations';
}

$breadcrumb = [
    ['label' => 'Académie', 'url' => url('academie/index.php')],
    ['label' => 'Cotisations']
];

if ($membre) {
    array_splice($breadcrumb, 1, 0, [
        ['label' => $membre['prenom'] . ' ' . $membre['nom'], 'url' => url('academie/voir.php?id=' . $membreId)]
    ]);
}

// Filtres
$filters = [
    'membre_id' => $membreId ?: null,
    'statut' => sanitize(get('statut')),
    'mois' => (int)get('mois'),
    'annee' => (int)get('annee') ?: date('Y'),
    'en_retard' => get('retard') === '1'
];

$page = max(1, (int)get('page', 1));
$result = Cotisation::getAll($filters, $page, 20);

// Statistiques (sans filtre d'année pour montrer les retards de toutes les années)
$stats = Cotisation::getStats();
$cotisationsRetard = Cotisation::getEnRetard(5);

// Traitement des actions
if (isPost()) {
    if (!verifyCsrf()) {
        Session::flash('danger', 'Token de sécurité invalide.');
    } else {
        $action = post('action');

        if ($action === 'payer') {
            $cotisationId = (int)post('cotisation_id');
            $modePaiement = sanitize(post('mode_paiement'));
            $reference = sanitize(post('reference'));

            $result_paiement = Cotisation::payer($cotisationId, [
                'mode_paiement' => $modePaiement,
                'reference' => $reference,
                'encaisse_par' => Auth::id()
            ]);

            if ($result_paiement['success']) {
                Auth::logAction(Auth::id(), 'payment', 'cotisations', $cotisationId);
                Session::flash('success', $result_paiement['message']);
            } else {
                Session::flash('danger', $result_paiement['message']);
            }

            redirect(url('academie/cotisations.php' . ($membreId ? '?membre=' . $membreId : '')));
        }

        if ($action === 'generer' && Auth::isAdmin()) {
            $mois = (int)post('mois');
            $annee = (int)post('annee');

            if ($mois >= 1 && $mois <= 12 && $annee >= 2020) {
                $count = Cotisation::genererCotisationsMensuelles($mois, $annee);
                Session::flash('success', "$count cotisation(s) générée(s) pour " . getMonthName($mois) . " $annee.");
            } else {
                Session::flash('danger', 'Période invalide.');
            }

            redirect(url('academie/cotisations.php'));
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <?php if ($membre): ?>
                Cotisations de <?= e($membre['prenom'] . ' ' . $membre['nom']) ?>
            <?php else: ?>
                Gestion des cotisations
            <?php endif; ?>
        </h4>
        <p class="text-muted mb-0"><?= $result['total'] ?> cotisation(s)</p>
    </div>
    <div class="d-flex gap-2">
        <?php if (Auth::isAdmin()): ?>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#genererModal">
            <i class="fas fa-cogs me-2"></i>Générer cotisations
        </button>
        <?php endif; ?>
        <?php if ($membre): ?>
        <a href="<?= url('academie/cotisations.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-list me-2"></i>Toutes les cotisations
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Statistiques -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-success text-white">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($stats['montant_encaisse'] ?? 0) ?></div>
                <div class="kpi-label">Encaissé (<?= $stats['payees'] ?? 0 ?>)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning text-white">
                <i class="fas fa-hourglass-half"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($stats['montant_attendu'] ?? 0) ?></div>
                <div class="kpi-label">En attente (<?= $stats['en_attente'] ?? 0 ?>)</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger text-white">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($stats['montant_retard'] ?? 0) ?></div>
                <div class="kpi-label">En retard</div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-info text-white">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-content">
                <?php
                $tauxRecouvrement = ($stats['total_cotisations'] ?? 0) > 0
                    ? round(($stats['payees'] / $stats['total_cotisations']) * 100, 1)
                    : 0;
                ?>
                <div class="kpi-value"><?= $tauxRecouvrement ?>%</div>
                <div class="kpi-label">Taux de recouvrement</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Liste des cotisations -->
    <div class="col-xl-8">
        <!-- Filtres -->
        <div class="card mb-4">
            <div class="card-body py-2">
                <form method="GET" class="row g-2 align-items-center">
                    <?php if ($membreId): ?>
                        <input type="hidden" name="membre" value="<?= $membreId ?>">
                    <?php endif; ?>
                    <div class="col-md-3">
                        <select class="form-select form-select-sm" name="statut">
                            <option value="">Tous statuts</option>
                            <option value="paye" <?= $filters['statut'] === 'paye' ? 'selected' : '' ?>>Payé</option>
                            <option value="en_attente" <?= $filters['statut'] === 'en_attente' ? 'selected' : '' ?>>En attente</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="mois">
                            <option value="">Tous mois</option>
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $filters['mois'] == $m ? 'selected' : '' ?>>
                                    <?= getMonthName($m) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" name="annee">
                            <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                                <option value="<?= $y ?>" <?= $filters['annee'] == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="retard" value="1"
                                   id="retard" <?= $filters['en_retard'] ? 'checked' : '' ?>>
                            <label class="form-check-label small" for="retard">En retard</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fas fa-filter"></i> Filtrer
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tableau -->
        <div class="card">
            <div class="card-body p-0">
                <?php if (empty($result['data'])): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-receipt fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">Aucune cotisation trouvée</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <?php if (!$membre): ?>
                                    <th>Membre</th>
                                    <?php endif; ?>
                                    <th>Période</th>
                                    <th>Montant</th>
                                    <th>Échéance</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $moisNoms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                                foreach ($result['data'] as $c):
                                    // Calculer période et date_echeance depuis mois/annee
                                    $periode = $moisNoms[$c['mois']] . ' ' . $c['annee'];
                                    $dateEcheance = date('Y-m-t', strtotime($c['annee'] . '-' . str_pad($c['mois'], 2, '0', STR_PAD_LEFT) . '-01'));
                                    $isLate = $c['statut'] === 'en_attente' && $dateEcheance < date('Y-m-d');
                                ?>
                                <tr class="<?= $isLate ? 'table-danger' : '' ?>">
                                    <?php if (!$membre): ?>
                                    <td>
                                        <a href="<?= url('academie/voir.php?id=' . $c['membre_id']) ?>" class="text-dark">
                                            <strong><?= e($c['membre_prenom'] . ' ' . $c['membre_nom']) ?></strong>
                                        </a>
                                        <br>
                                        <small class="text-muted"><?= e($c['categorie']) ?></small>
                                    </td>
                                    <?php endif; ?>
                                    <td>
                                        <strong><?= e($periode) ?></strong>
                                    </td>
                                    <td class="fw-semibold"><?= formatMoney($c['montant']) ?></td>
                                    <td>
                                        <?= formatDate($dateEcheance, 'd/m/Y') ?>
                                        <?php if ($isLate): ?>
                                            <br><small class="text-danger">
                                                <?= (new DateTime($dateEcheance))->diff(new DateTime())->days ?> jours
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $c['statut'] === 'paye' ? 'bg-success' : ($isLate ? 'bg-danger' : 'bg-warning') ?>">
                                            <?= $c['statut'] === 'paye' ? 'Payé' : ($isLate ? 'En retard' : 'En attente') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($c['date_paiement']): ?>
                                            <?= formatDate($c['date_paiement'], 'd/m/Y') ?>
                                            <br><small class="text-muted"><?= ucfirst($c['mode_paiement']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($c['statut'] !== 'paye'): ?>
                                            <button type="button" class="btn btn-sm btn-success"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#payerModal"
                                                    data-id="<?= $c['id'] ?>"
                                                    data-membre="<?= e($c['membre_prenom'] . ' ' . $c['membre_nom']) ?>"
                                                    data-periode="<?= e($periode) ?>"
                                                    data-montant="<?= $c['montant'] ?>">
                                                <i class="fas fa-money-bill"></i>
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($result['pages'] > 1): ?>
                    <div class="card-footer">
                        <?= pagination($result['current_page'], $result['pages'], url('academie/cotisations') . '?' . http_build_query(array_filter($filters))) ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Cotisations en retard -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>En retard</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($cotisationsRetard)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                        <p class="mb-0">Aucune cotisation en retard</p>
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($cotisationsRetard as $c): ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <a href="<?= url('academie/voir.php?id=' . $c['membre_id']) ?>" class="fw-semibold text-dark text-decoration-none">
                                        <?= e($c['membre_prenom'] . ' ' . $c['membre_nom']) ?>
                                    </a>
                                    <br>
                                    <small class="text-muted"><?= e($c['periode']) ?></small>
                                </div>
                                <div class="text-end">
                                    <strong class="text-danger"><?= formatMoney($c['montant']) ?></strong>
                                    <br>
                                    <small class="text-danger"><?= $c['jours_retard'] ?> jours</small>
                                </div>
                            </div>
                            <div class="mt-2">
                                <a href="tel:<?= e($c['telephone_parent']) ?>" class="btn btn-sm btn-outline-primary me-1">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-success"
                                        data-bs-toggle="modal"
                                        data-bs-target="#payerModal"
                                        data-id="<?= $c['id'] ?>"
                                        data-membre="<?= e($c['membre_prenom'] . ' ' . $c['membre_nom']) ?>"
                                        data-periode="<?= e($c['periode']) ?>"
                                        data-montant="<?= $c['montant'] ?>">
                                    <i class="fas fa-money-bill me-1"></i>Encaisser
                                </button>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
            <?php if (!empty($cotisationsRetard)): ?>
            <div class="card-footer">
                <a href="<?= url('academie/cotisations.php?retard=1') ?>" class="btn btn-sm btn-outline-danger w-100">
                    Voir tous les retards
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Payer -->
<div class="modal fade" id="payerModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="payer">
                <input type="hidden" name="cotisation_id" id="payer_cotisation_id">

                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-money-bill me-2"></i>Encaisser cotisation</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3 text-center">
                        <div class="h6" id="payer_membre"></div>
                        <div class="text-muted" id="payer_periode"></div>
                        <div class="h3 text-success" id="payer_montant"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="mode_paiement"
                                       id="mode_<?= $key ?>" value="<?= $key ?>"
                                       <?= $key === 'especes' ? 'checked' : '' ?>>
                                <label class="btn btn-outline-primary w-100" for="mode_<?= $key ?>">
                                    <?= $label ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="reference" class="form-label">Référence (optionnel)</label>
                        <input type="text" class="form-control" id="reference" name="reference"
                               placeholder="N° transaction Wave/OM">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-check me-2"></i>Valider le paiement
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Générer -->
<?php if (Auth::isAdmin()): ?>
<div class="modal fade" id="genererModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="generer">

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-cogs me-2"></i>Générer cotisations mensuelles</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">
                        Cette action va créer automatiquement une cotisation pour chaque membre actif
                        n'ayant pas encore de cotisation pour la période sélectionnée.
                    </p>

                    <div class="row g-3">
                        <div class="col-6">
                            <label for="gen_mois" class="form-label">Mois</label>
                            <select class="form-select" id="gen_mois" name="mois" required>
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?= $m ?>" <?= $m == date('n') ? 'selected' : '' ?>>
                                        <?= getMonthName($m) ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="gen_annee" class="form-label">Année</label>
                            <select class="form-select" id="gen_annee" name="annee" required>
                                <?php for ($y = date('Y'); $y <= date('Y') + 1; $y++): ?>
                                    <option value="<?= $y ?>"><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-cogs me-2"></i>Générer
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$inlineJs = "
// Remplir le modal de paiement
document.getElementById('payerModal').addEventListener('show.bs.modal', function(event) {
    const button = event.relatedTarget;
    document.getElementById('payer_cotisation_id').value = button.dataset.id;
    document.getElementById('payer_membre').textContent = button.dataset.membre;
    document.getElementById('payer_periode').textContent = button.dataset.periode;
    document.getElementById('payer_montant').textContent = new Intl.NumberFormat('fr-FR').format(button.dataset.montant) + ' FCFA';
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
