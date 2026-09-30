<?php
/**
 * Détails d'une session de caisse
 * Vue lecture seule avec KPIs, paiements et dépenses de la session
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Caisse.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Session non spécifiée.');
    redirect(url('paiements/ma-caisse.php'));
}

$session = Caisse::getById($id);
if (!$session) {
    Session::flash('danger', 'Session introuvable.');
    redirect(url('paiements/ma-caisse.php'));
}

// Contrôle d'accès : un caissier ne voit que ses propres sessions ; admin/directeur voit tout
$canSeeAll = Auth::isAdmin();
if (!$canSeeAll && (int)$session['utilisateur_id'] !== (int)Auth::id()) {
    Session::flash('danger', 'Vous ne pouvez consulter que vos propres sessions.');
    redirect(url('paiements/ma-caisse.php'));
}

// Totaux live (cohérents pour les sessions ouvertes ; identiques aux totaux DB pour les clôturées)
$totals = Caisse::computeTotals($id);
$depCashSession = Caisse::getCashExpenses($id);
$expectedCash = (float)$session['fond_caisse'] + (float)$totals['especes'] - $depCashSession;

// Total des dépenses (tous modes)
$depRow = Database::fetchOne(
    "SELECT COUNT(*) as nb, COALESCE(SUM(montant), 0) as total
     FROM depenses WHERE session_caisse_id = :sid AND statut = 'payee'",
    ['sid' => $id]
);
$totalDepenses = (float)($depRow['total'] ?? 0);
$nbDepenses    = (int)($depRow['nb'] ?? 0);

// Solde net = fond + (encaissé − remboursé) − dépenses (tous modes)
$soldeNet = (float)$session['fond_caisse']
          + (float)$totals['total_encaisse']
          - (float)$totals['total_rembourse']
          - $totalDepenses;

// Paiements et dépenses détaillés
$paiements = Caisse::getSessionPayments($id);
$depenses = Database::fetchAll(
    "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur, t.nom as terrain_nom
     FROM depenses d
     LEFT JOIN categories_depenses c ON d.categorie_id = c.id
     LEFT JOIN terrains t ON d.terrain_id = t.id
     WHERE d.session_caisse_id = :sid AND d.statut = 'payee'
     ORDER BY d.created_at DESC",
    ['sid' => $id]
);

$pageTitle = 'Session caisse #' . $id;
$breadcrumb = [
    ['label' => 'Paiements', 'url' => url('paiements/index.php')],
    ['label' => 'Ma caisse', 'url' => url('paiements/ma-caisse.php')],
    ['label' => 'Session #' . $id]
];

$statutBadge = match($session['statut']) {
    'ouverte'  => '<span class="badge bg-success"><i class="fas fa-circle me-1"></i>Ouverte</span>',
    'cloturee' => '<span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>Clôturée</span>',
    'validee'  => '<span class="badge bg-primary"><i class="fas fa-check-circle me-1"></i>Validée</span>',
    default    => '<span class="badge bg-secondary">' . e($session['statut']) . '</span>'
};

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1">
            <i class="fas fa-cash-register me-2"></i>
            Session caisse #<?= $id ?>
            <?= $statutBadge ?>
        </h4>
        <p class="text-muted mb-0">
            <i class="fas fa-user me-1"></i>
            <?= e(trim(($session['user_prenom'] ?? '') . ' ' . ($session['user_nom'] ?? ''))) ?>
            &nbsp;•&nbsp;
            <i class="fas fa-calendar me-1"></i>
            <?= formatDate($session['heure_ouverture'], 'd/m/Y') ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimer
        </button>
        <a href="<?= url('paiements/ma-caisse.php') ?>" class="btn btn-outline-primary">
            <i class="fas fa-arrow-left me-2"></i>Retour
        </a>
    </div>
</div>

<!-- KPIs synthèse -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-cash-register fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= formatMoney($session['fond_caisse']) ?></div>
                <small>Fond de caisse</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-arrow-down fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">+ <?= formatMoney($totals['total_encaisse']) ?></div>
                <small>Encaissé</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-arrow-up fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">- <?= formatMoney($totals['total_rembourse']) ?></div>
                <small>Remboursé</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-file-invoice-dollar fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">- <?= formatMoney($totalDepenses) ?></div>
                <small>Dépenses</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-info text-white h-100">
            <div class="card-body text-center py-3" title="Fond + (Encaissé − Remboursé) − Dépenses">
                <i class="fas fa-balance-scale fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= formatMoney($soldeNet) ?></div>
                <small>Solde net</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-dark text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-receipt fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= $totals['nb_transactions'] ?></div>
                <small>Transactions<?= $nbDepenses ? ' (+ ' . $nbDepenses . ' dép.)' : '' ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Info session + Clôture / Validation -->
<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Informations</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td class="text-muted">Caissier</td>
                        <td class="text-end fw-bold"><?= e(trim(($session['user_prenom'] ?? '') . ' ' . ($session['user_nom'] ?? ''))) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted">Ouverture</td>
                        <td class="text-end"><?= formatDate($session['heure_ouverture'], 'd/m/Y à H:i') ?></td>
                    </tr>
                    <?php if ($session['heure_cloture']): ?>
                    <tr>
                        <td class="text-muted">Clôture</td>
                        <td class="text-end"><?= formatDate($session['heure_cloture'], 'd/m/Y à H:i') ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($session['cloturee_par_nom'])): ?>
                    <tr>
                        <td class="text-muted">Clôturée par</td>
                        <td class="text-end"><?= e($session['cloturee_par_nom']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($session['validee_par_nom'])): ?>
                    <tr>
                        <td class="text-muted">Validée par</td>
                        <td class="text-end"><?= e($session['validee_par_nom']) ?>
                            <?= $session['heure_validation'] ? '<br><small class="text-muted">' . formatDate($session['heure_validation'], 'd/m H:i') . '</small>' : '' ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($session['notes_ouverture'])): ?>
                    <tr>
                        <td class="text-muted">Notes ouverture</td>
                        <td class="text-end fst-italic"><?= e($session['notes_ouverture']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if (!empty($session['commentaire'])): ?>
                    <tr>
                        <td class="text-muted">Commentaire clôture</td>
                        <td class="text-end fst-italic"><?= e($session['commentaire']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-balance-scale me-2"></i>Réconciliation espèces</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr>
                        <td>Fond initial</td>
                        <td class="text-end"><?= formatMoney($session['fond_caisse']) ?></td>
                    </tr>
                    <tr>
                        <td>Mouvements espèces (paiements)</td>
                        <td class="text-end <?= $totals['especes'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($totals['especes'] >= 0 ? '+ ' : '') ?><?= formatMoney($totals['especes']) ?>
                        </td>
                    </tr>
                    <?php if ($depCashSession > 0): ?>
                    <tr>
                        <td>Dépenses espèces</td>
                        <td class="text-end text-warning">- <?= formatMoney($depCashSession) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="border-top">
                        <td class="fw-bold">Espèces attendues</td>
                        <td class="text-end fw-bold text-primary"><?= formatMoney($expectedCash) ?></td>
                    </tr>
                    <?php if ($session['montant_declare'] !== null): ?>
                    <tr>
                        <td>Espèces déclarées</td>
                        <td class="text-end"><?= formatMoney($session['montant_declare']) ?></td>
                    </tr>
                    <tr class="border-top">
                        <td class="fw-bold">Écart</td>
                        <td class="text-end fw-bold <?= ($session['ecart'] ?? 0) == 0 ? 'text-muted' : (($session['ecart'] ?? 0) > 0 ? 'text-success' : 'text-danger') ?>">
                            <?= formatMoney($session['ecart'] ?? 0) ?>
                            <?php if (($session['ecart'] ?? 0) > 0): ?>
                                <small>(excédent)</small>
                            <?php elseif (($session['ecart'] ?? 0) < 0): ?>
                                <small>(manquant)</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Paiements + Dépenses -->
<div class="row g-4">
    <div class="col-xl-7">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Transactions de paiement</h6>
                <span class="badge bg-success"><?= count($paiements) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($paiements)): ?>
                    <div class="text-center py-4 text-muted">Aucune transaction</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Heure</th>
                                <th>Catégorie</th>
                                <th>Détail</th>
                                <th>Mode</th>
                                <th class="text-end">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paiements as $p): $isRefund = $p['type_paiement'] === 'remboursement'; ?>
                            <tr class="<?= $isRefund ? 'table-warning' : '' ?>">
                                <td><small><?= formatDate($p['created_at'], 'H:i') ?></small></td>
                                <td>
                                    <?php if (!empty($p['categorie_icone'])): ?>
                                        <i class="fas <?= e($p['categorie_icone']) ?> me-1"></i>
                                    <?php endif; ?>
                                    <small><?= e($p['categorie_libelle'] ?? '-') ?></small>
                                    <?php if ($isRefund): ?>
                                        <span class="badge bg-danger">REMB</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($p['numero_ticket'])): ?>
                                        <a href="<?= url('reservations/voir.php?id=' . $p['reservation_id']) ?>" class="small"><?= e($p['numero_ticket']) ?></a>
                                        <small class="text-muted">— <?= e($p['client_nom']) ?></small>
                                    <?php else: ?>
                                        <small><?= e($p['libelle'] ?? '-') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= ucfirst($p['mode_paiement']) ?></small></td>
                                <td class="text-end">
                                    <strong class="<?= $isRefund ? 'text-danger' : 'text-success' ?>">
                                        <?= $isRefund ? '-' : '+' ?><?= formatMoney($p['montant']) ?>
                                    </strong>
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

    <div class="col-xl-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Dépenses</h6>
                <span class="badge bg-warning text-dark"><?= count($depenses) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($depenses)): ?>
                    <div class="text-center py-4 text-muted">Aucune dépense</div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Heure</th>
                                <th>Catégorie / Libellé</th>
                                <th>Mode</th>
                                <th class="text-end">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($depenses as $d): ?>
                            <tr>
                                <td><small><?= formatDate($d['created_at'], 'H:i') ?></small></td>
                                <td>
                                    <?php if (!empty($d['categorie_icone'])): ?>
                                        <i class="fas <?= e($d['categorie_icone']) ?> me-1" style="color: <?= e($d['categorie_couleur'] ?? '#dc3545') ?>;"></i>
                                    <?php endif; ?>
                                    <small><?= e($d['categorie_nom'] ?? '-') ?></small>
                                    <br><small class="text-muted"><?= e($d['libelle']) ?></small>
                                </td>
                                <td><small><?= ucfirst($d['mode_paiement'] ?? '-') ?></small></td>
                                <td class="text-end">
                                    <strong class="text-warning">- <?= formatMoney($d['montant']) ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="3" class="fw-bold">Total</td>
                                <td class="text-end fw-bold text-warning">- <?= formatMoney($totalDepenses) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
