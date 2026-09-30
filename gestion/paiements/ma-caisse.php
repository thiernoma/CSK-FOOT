<?php
/**
 * Ma caisse — ouverture / fermeture par le caissier
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Caisse.php';

Auth::requireLogin();

$pageTitle = 'Ma caisse';
$breadcrumb = [
    ['label' => 'Paiements', 'url' => url('paiements/index.php')],
    ['label' => 'Ma caisse']
];

$userId = Auth::id();
$session = Caisse::getActiveSession($userId);
$errors = [];

// Pour le formulaire d'ouverture : récupérer le solde de la dernière session clôturée
// du même utilisateur — il devient le fond de caisse par défaut (continuité de la trésorerie).
$soldePrecedent = null;
$datePrecedente = null;
if (!$session) {
    $row = Database::fetchOne(
        "SELECT montant_declare, heure_cloture
         FROM clotures_caisse
         WHERE utilisateur_id = :uid AND statut IN ('cloturee', 'validee')
         ORDER BY heure_cloture DESC LIMIT 1",
        ['uid' => $userId]
    );
    if ($row && $row['montant_declare'] !== null) {
        $soldePrecedent = (float)$row['montant_declare'];
        $datePrecedente = $row['heure_cloture'];
    }
}

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');

        if ($action === 'ouvrir') {
            $fond = (float)post('fond_caisse');
            $notes = trim(sanitize(post('notes_ouverture')));

            $result = Caisse::open($userId, $fond, $notes ?: null);
            if ($result['success']) {
                Auth::logAction($userId, 'caisse_open', 'clotures_caisse', $result['session_id']);
                Session::flash('success', $result['message']);
                redirect(url('paiements/ma-caisse.php'));
            } else {
                $errors[] = $result['message'];
            }
        } elseif ($action === 'cloturer') {
            $montantDeclare = (float)post('montant_declare');
            $commentaire = trim(sanitize(post('commentaire')));

            if (!$session) {
                $errors[] = 'Aucune session ouverte.';
            } else {
                $result = Caisse::close((int)$session['id'], $userId, $montantDeclare, $commentaire ?: null);
                if ($result['success']) {
                    Auth::logAction($userId, 'caisse_close', 'clotures_caisse', $session['id']);
                    Session::flash('success', $result['message']);
                    redirect(url('paiements/ma-caisse.php'));
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

// Recharger après éventuelle action
$session = Caisse::getActiveSession($userId);

// Si session active, calculer les totaux
$totals = null;
$expectedCash = null;
$paiementsSession = [];
if ($session) {
    $totals = Caisse::computeTotals((int)$session['id']);
    $depCashSession = Caisse::getCashExpenses((int)$session['id']);
    $expectedCash = (float)$session['fond_caisse'] + $totals['especes'] - $depCashSession;
    $paiementsSession = Caisse::getSessionPayments((int)$session['id']);

    // Dépenses totales (tous modes) attachées à la session — pour KPI Net incluant le fond
    $depRow = Database::fetchOne(
        "SELECT COUNT(*) as nb, COALESCE(SUM(montant), 0) as total
         FROM depenses
         WHERE session_caisse_id = :sid AND statut = 'payee'",
        ['sid' => $session['id']]
    );
    $totals['total_depenses'] = (float)($depRow['total'] ?? 0);
    $totals['nb_depenses']    = (int)($depRow['nb'] ?? 0);

    // Solde net session = fond + (encaissé − remboursé) − dépenses
    $totals['solde_net'] = (float)$session['fond_caisse']
                         + (float)$totals['total_encaisse']
                         - (float)$totals['total_rembourse']
                         - $totals['total_depenses'];

    // Détail des dépenses pour affichage
    $depensesSession = Database::fetchAll(
        "SELECT d.*, c.nom as categorie_nom, c.icone as categorie_icone, c.couleur as categorie_couleur
         FROM depenses d
         LEFT JOIN categories_depenses c ON d.categorie_id = c.id
         WHERE d.session_caisse_id = :sid AND d.statut = 'payee'
         ORDER BY d.created_at DESC",
        ['sid' => $session['id']]
    );
}

// Historique des dernières sessions de l'utilisateur
$historique = Database::fetchAll(
    "SELECT * FROM clotures_caisse
     WHERE utilisateur_id = :uid AND statut != 'ouverte'
     ORDER BY heure_ouverture DESC
     LIMIT 10",
    ['uid' => $userId]
);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><i class="fas fa-cash-register me-2"></i>Ma caisse</h4>
        <p class="text-muted mb-0"><?= e(Auth::user()['prenom'] ?? '') ?> <?= e(Auth::user()['nom'] ?? '') ?></p>
    </div>
    <?php if (Auth::isAdmin()): ?>
    <a href="<?= url('paiements/caisse.php') ?>" class="btn btn-outline-primary">
        <i class="fas fa-th-large me-2"></i>Vue d'ensemble
    </a>
    <?php endif; ?>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <ul class="mb-0">
            <?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if (!$session): ?>
<!-- ===== Aucune session ouverte → formulaire d'ouverture ===== -->
<div class="row justify-content-center">
    <div class="col-md-7">
        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-key me-2"></i>Ouverture de caisse</h5>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Saisissez le montant en espèces présent dans votre caisse au début de votre service.
                </p>
                <?php if ($soldePrecedent !== null): ?>
                <div class="alert alert-info d-flex align-items-center mb-3" role="alert">
                    <i class="fas fa-info-circle fa-lg me-2"></i>
                    <div class="flex-grow-1">
                        Solde de votre dernière clôture
                        <?php if ($datePrecedente): ?>
                            <small class="text-muted">(<?= formatDate($datePrecedente, 'd/m/Y H:i') ?>)</small>
                        <?php endif; ?>
                        : <strong><?= formatMoney($soldePrecedent) ?></strong> — pré-rempli ci-dessous.
                    </div>
                </div>
                <?php endif; ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="ouvrir">

                    <div class="mb-3">
                        <label class="form-label">Fond de caisse (espèces) <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control" name="fond_caisse"
                                   value="<?= e($soldePrecedent ?? 0) ?>" min="0" step="any" required autofocus>
                            <span class="input-group-text">FCFA</span>
                        </div>
                        <?php if ($soldePrecedent !== null): ?>
                        <small class="form-text text-muted">
                            Vous pouvez ajuster ce montant si vous avez retiré ou ajouté du cash depuis la clôture.
                        </small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" name="notes_ouverture" rows="2"
                                  placeholder="Ex: prise de poste, remarques..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-play me-2"></i>Ouvrir ma caisse
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ===== Session ouverte → tableau de bord + clôture ===== -->
<div class="row g-4">
    <!-- Tableau de bord -->
    <div class="col-xl-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge bg-success mb-2"><i class="fas fa-circle me-1"></i>Caisse ouverte</span>
                        <p class="mb-0 text-muted">
                            Depuis <?= formatDate($session['heure_ouverture'], 'd/m/Y à H:i') ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <small class="text-muted">Fond de caisse</small>
                        <div class="h4 mb-0"><?= formatMoney($session['fond_caisse']) ?></div>
                    </div>
                </div>
                <?php if (!empty($session['notes_ouverture'])): ?>
                <hr>
                <small class="text-muted"><i class="fas fa-sticky-note me-1"></i><?= e($session['notes_ouverture']) ?></small>
                <?php endif; ?>
            </div>
        </div>

        <!-- Totaux par mode -->
        <div class="card mb-3">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-wallet me-2"></i>Mouvements de la session</h6>
            </div>
            <div class="card-body">
                <div class="row g-2 text-center mb-3">
                    <div class="col-6 col-md">
                        <div class="bg-primary bg-opacity-10 rounded p-2">
                            <small class="text-muted d-block">Fond</small>
                            <strong><?= formatMoney($session['fond_caisse']) ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="bg-success bg-opacity-10 rounded p-2">
                            <small class="text-muted d-block">Encaissé</small>
                            <strong class="text-success">+ <?= formatMoney($totals['total_encaisse']) ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="bg-danger bg-opacity-10 rounded p-2">
                            <small class="text-muted d-block">Remboursé</small>
                            <strong class="text-danger">- <?= formatMoney($totals['total_rembourse']) ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="bg-warning bg-opacity-10 rounded p-2">
                            <small class="text-muted d-block">Dépenses</small>
                            <strong class="text-warning">- <?= formatMoney($totals['total_depenses']) ?></strong>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="bg-info bg-opacity-10 rounded p-2" title="Fond + (Encaissé − Remboursé) − Dépenses">
                            <small class="text-muted d-block">Solde net</small>
                            <strong class="text-<?= $totals['solde_net'] >= 0 ? 'info' : 'danger' ?>">
                                <?= formatMoney($totals['solde_net']) ?>
                            </strong>
                        </div>
                    </div>
                    <div class="col-6 col-md">
                        <div class="bg-light rounded p-2">
                            <small class="text-muted d-block">Transactions</small>
                            <strong><?= $totals['nb_transactions'] ?></strong>
                            <?php if ($totals['nb_depenses'] > 0): ?>
                                <small class="d-block text-muted">+ <?= $totals['nb_depenses'] ?> dép.</small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td><i class="fas fa-money-bill text-success me-2"></i>Espèces</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['especes']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-mobile-alt text-info me-2"></i>Wave</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['wave']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-mobile-alt text-warning me-2"></i>Orange Money</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['om']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-credit-card text-primary me-2"></i>Carte</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['carte']) ?></td>
                        </tr>
                        <?php if ($totals['virement'] != 0): ?>
                        <tr>
                            <td><i class="fas fa-university me-2"></i>Virement</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['virement']) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($totals['cheque'] != 0): ?>
                        <tr>
                            <td><i class="fas fa-money-check me-2"></i>Chèque</td>
                            <td class="text-end fw-bold"><?= formatMoney($totals['cheque']) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Transactions récentes de la session -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Transactions de la session</h6>
                <a href="<?= url('paiements/nouveau.php') ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus me-1"></i>Encaissement libre
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($paiementsSession)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune transaction</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="max-height: 350px;">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="sticky-top bg-light">
                                <tr>
                                    <th>Heure</th>
                                    <th>Catégorie</th>
                                    <th>Détail</th>
                                    <th>Mode</th>
                                    <th class="text-end">Montant</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiementsSession as $p): $isRefund = $p['type_paiement'] === 'remboursement'; ?>
                                <tr class="<?= $isRefund ? 'table-warning' : '' ?>">
                                    <td><small><?= formatDate($p['created_at'], 'H:i') ?></small></td>
                                    <td>
                                        <?php if (!empty($p['categorie_icone'])): ?>
                                            <i class="fas <?= e($p['categorie_icone']) ?> me-1"></i>
                                        <?php endif; ?>
                                        <small><?= e($p['categorie_libelle'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['numero_ticket'])): ?>
                                            <a href="<?= url('reservations/voir.php?id=' . $p['reservation_id']) ?>"><?= e($p['numero_ticket']) ?></a>
                                            — <small class="text-muted"><?= e($p['client_nom']) ?></small>
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

        <!-- Dépenses de la session -->
        <?php if (!empty($depensesSession)): ?>
        <div class="card mt-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-file-invoice-dollar text-warning me-2"></i>Dépenses de la session</h6>
                <a href="<?= url('depenses/nouveau.php') ?>" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-plus me-1"></i>Nouvelle dépense
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Heure</th>
                                <th>Catégorie</th>
                                <th>Libellé</th>
                                <th>Mode</th>
                                <th class="text-end">Montant</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($depensesSession as $d): ?>
                            <tr>
                                <td><small><?= formatDate($d['created_at'], 'H:i') ?></small></td>
                                <td>
                                    <?php if (!empty($d['categorie_icone'])): ?>
                                        <i class="fas <?= e($d['categorie_icone']) ?> me-1" style="color: <?= e($d['categorie_couleur'] ?? '#dc3545') ?>;"></i>
                                    <?php endif; ?>
                                    <small><?= e($d['categorie_nom'] ?? '-') ?></small>
                                </td>
                                <td><small><?= e($d['libelle']) ?></small></td>
                                <td><small><?= ucfirst($d['mode_paiement'] ?? '-') ?></small></td>
                                <td class="text-end">
                                    <strong class="text-warning">- <?= formatMoney($d['montant']) ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="4" class="fw-bold">TOTAL dépenses</td>
                                <td class="text-end fw-bold text-warning">- <?= formatMoney($totals['total_depenses']) ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Clôture -->
    <div class="col-xl-5">
        <div class="card border-warning">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-lock me-2"></i>Clôturer ma caisse</h5>
            </div>
            <div class="card-body">
                <div class="bg-light rounded p-3 mb-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Fond initial</span>
                        <strong><?= formatMoney($session['fond_caisse']) ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Mouvements espèces (paiements)</span>
                        <strong class="<?= $totals['especes'] >= 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($totals['especes'] >= 0 ? '+ ' : '') ?><?= formatMoney($totals['especes']) ?>
                        </strong>
                    </div>
                    <?php if ($depCashSession > 0): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span>Dépenses espèces</span>
                        <strong class="text-warning">- <?= formatMoney($depCashSession) ?></strong>
                    </div>
                    <?php endif; ?>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between">
                        <strong>Espèces attendues en caisse</strong>
                        <strong class="text-primary h5 mb-0"><?= formatMoney($expectedCash) ?></strong>
                    </div>
                </div>

                <form method="POST"
                      data-confirm="Confirmer la clôture de votre caisse ? Cette action est irréversible."
                      data-confirm-title="Clôturer la caisse"
                      data-confirm-text="<i class='fas fa-lock me-1'></i> Clôturer"
                      data-confirm-class="btn-warning"
                      data-confirm-icon="fa-lock"
                      data-confirm-icon-class="text-warning">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="cloturer">

                    <div class="mb-3">
                        <label class="form-label">Espèces réellement comptées <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control" name="montant_declare"
                                   id="montantDeclare" value="<?= $expectedCash ?>" min="0" step="any" required>
                            <span class="input-group-text">FCFA</span>
                        </div>
                        <div id="ecartDisplay" class="form-text"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Commentaire</label>
                        <textarea class="form-control" name="commentaire" rows="2"
                                  placeholder="Justifier un éventuel écart..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-warning w-100">
                        <i class="fas fa-lock me-2"></i>Clôturer ma caisse
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Historique -->
<?php if (!empty($historique)): ?>
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Mes 10 dernières sessions</h6>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover mb-0">
                <thead>
                    <tr>
                        <th>Ouverture</th>
                        <th>Fermeture</th>
                        <th class="text-end">Fond</th>
                        <th class="text-end">Encaissé</th>
                        <th class="text-end">Déclaré</th>
                        <th class="text-end">Écart</th>
                        <th>Statut</th>
                        <th class="text-end">Détails</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historique as $h): ?>
                    <tr>
                        <td><small><?= formatDate($h['heure_ouverture'], 'd/m/Y H:i') ?></small></td>
                        <td><small><?= $h['heure_cloture'] ? formatDate($h['heure_cloture'], 'd/m/Y H:i') : '-' ?></small></td>
                        <td class="text-end"><?= formatMoney($h['fond_caisse']) ?></td>
                        <td class="text-end text-success"><?= formatMoney($h['total_encaissements']) ?></td>
                        <td class="text-end"><?= $h['montant_declare'] !== null ? formatMoney($h['montant_declare']) : '-' ?></td>
                        <td class="text-end fw-bold <?= ($h['ecart'] ?? 0) == 0 ? 'text-muted' : (($h['ecart'] ?? 0) > 0 ? 'text-success' : 'text-danger') ?>">
                            <?= $h['ecart'] !== null ? formatMoney($h['ecart']) : '-' ?>
                        </td>
                        <td>
                            <?php
                            $badgeClass = match($h['statut']) {
                                'cloturee' => 'bg-warning text-dark',
                                'validee' => 'bg-success',
                                default => 'bg-secondary'
                            };
                            ?>
                            <span class="badge <?= $badgeClass ?>"><?= ucfirst($h['statut']) ?></span>
                        </td>
                        <td class="text-end">
                            <a href="<?= url('paiements/session.php?id=' . $h['id']) ?>"
                               class="btn btn-sm btn-outline-primary"
                               title="Voir les détails de cette session">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$inlineJs = "
const md = document.getElementById('montantDeclare');
const exp = " . ($expectedCash ?? 0) . ";
if (md) {
    function refreshEcart() {
        const v = parseFloat(md.value || 0);
        const ecart = v - exp;
        const div = document.getElementById('ecartDisplay');
        if (ecart === 0) {
            div.innerHTML = '<span class=\"text-muted\">Aucun écart.</span>';
        } else if (ecart > 0) {
            div.innerHTML = '<span class=\"text-success\">Excédent : ' + ecart.toLocaleString('fr-FR') + ' FCFA</span>';
        } else {
            div.innerHTML = '<span class=\"text-danger\">Manquant : ' + Math.abs(ecart).toLocaleString('fr-FR') + ' FCFA</span>';
        }
    }
    md.addEventListener('input', refreshEcart);
    refreshEcart();
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
