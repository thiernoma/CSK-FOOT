<?php
/**
 * Vue d'ensemble de la caisse — sessions du jour, transactions, validation
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Caisse.php';

Auth::requireLogin();

if (!Auth::isAdmin()) {
    Session::flash('warning', 'La caisse globale est réservée aux administrateurs et directeurs.');
    redirect(url('paiements/ma-caisse.php'));
}

$pageTitle = 'Caisse';
$breadcrumb = [
    ['label' => 'Paiements', 'url' => url('paiements/index.php')],
    ['label' => 'Caisse']
];

$date = get('date', date('Y-m-d'));
$sessionFilter = (int)get('session_id', 0);
$canValidate = Auth::isAdmin();

// Action validation par admin
if (isPost() && post('action') === 'valider' && $canValidate) {
    if (!verifyCsrf()) {
        Session::flash('danger', 'Token de sécurité invalide.');
    } else {
        $sid = (int)post('session_id');
        $r = Caisse::validate($sid, Auth::id());
        if ($r['success']) {
            Auth::logAction(Auth::id(), 'caisse_validate', 'clotures_caisse', $sid);
            Session::flash('success', $r['message']);
        } else {
            Session::flash('danger', $r['message']);
        }
        redirect(url('paiements/caisse.php?date=' . $date));
    }
}

// Sessions du jour
$sessions = Caisse::getByDate($date);

// Pour chaque session, calculer les totaux live (encaissé + dépenses) — utile pour les sessions
// encore ouvertes dont total_encaissements en base n'est pas encore rempli (rempli à la clôture).
foreach ($sessions as &$s) {
    $live = Caisse::computeTotals((int)$s['id']);
    $s['live_total_encaisse']    = (float)$live['total_encaisse'];
    $s['live_total_rembourse']   = (float)$live['total_rembourse'];
    $s['live_total_net']         = $s['live_total_encaisse'] - $s['live_total_rembourse'];
    $s['live_nb_transactions']   = (int)$live['nb_transactions'];
    // Flux espèces (entrées espèces nettes de remboursements espèces)
    $s['live_especes_net']       = (float)$live['especes'];

    // Dépenses attachées à cette session
    $depRow = Database::fetchOne(
        "SELECT COUNT(*) as nb,
                COALESCE(SUM(montant), 0) as total,
                COALESCE(SUM(CASE WHEN mode_paiement = 'especes' THEN montant END), 0) as total_especes
         FROM depenses
         WHERE session_caisse_id = :sid AND statut = 'payee'",
        ['sid' => $s['id']]
    );
    $s['live_total_depenses']         = (float)($depRow['total'] ?? 0);
    $s['live_total_depenses_especes'] = (float)($depRow['total_especes'] ?? 0);
    $s['live_nb_depenses']            = (int)($depRow['nb'] ?? 0);

    // Solde net = fond + (encaissements tous modes − remboursements) − dépenses tous modes
    $s['live_solde'] = (float)$s['fond_caisse']
                     + $s['live_total_net']
                     - $s['live_total_depenses'];
}
unset($s);

// Filtre session : permet de focaliser les agrégations sur une caisse spécifique.
// Quand actif, on ajoute une clause AND p.session_caisse_id = :sid à toutes les requêtes paiements.
$sessionClause = '';
$sessionParams = ['date' => $date];
if ($sessionFilter > 0) {
    $sessionClause = ' AND p.session_caisse_id = :sid';
    $sessionParams['sid'] = $sessionFilter;
}

// Sessions actuellement sélectionnée (pour l'affichage du label)
$sessionSelectionnee = null;
if ($sessionFilter > 0) {
    foreach ($sessions as $s) {
        if ((int)$s['id'] === $sessionFilter) { $sessionSelectionnee = $s; break; }
    }
}

// Tous les paiements du jour (filtre date + optionnel session)
$paiements = Database::fetchAll(
    "SELECT p.*,
            r.numero_ticket, r.heure_debut, r.heure_fin,
            CONCAT(c.prenom, ' ', c.nom) as client_nom,
            t.nom as terrain_nom,
            cat.libelle as categorie_libelle, cat.icone as categorie_icone, cat.couleur as categorie_couleur,
            u.nom as recu_par_nom
     FROM paiements p
     LEFT JOIN reservations r ON p.reservation_id = r.id
     LEFT JOIN clients c ON r.client_id = c.id
     LEFT JOIN terrains t ON r.terrain_id = t.id
     LEFT JOIN categories_encaissement cat ON p.categorie_id = cat.id
     LEFT JOIN users u ON p.recu_par = u.id
     WHERE DATE(p.created_at) = :date" . $sessionClause . "
     ORDER BY p.created_at DESC",
    $sessionParams
);

// Totaux globaux
$totaux = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_transactions,
        COALESCE(SUM(CASE WHEN type_paiement='paiement' THEN montant ELSE 0 END),0) as total_encaisse,
        COALESCE(SUM(CASE WHEN type_paiement='remboursement' THEN montant ELSE 0 END),0) as total_rembourse,
        COALESCE(SUM(CASE WHEN mode_paiement='especes' AND type_paiement='paiement' THEN montant ELSE 0 END),0) as especes_in,
        COALESCE(SUM(CASE WHEN mode_paiement='especes' AND type_paiement='remboursement' THEN montant ELSE 0 END),0) as especes_out,
        COALESCE(SUM(CASE WHEN mode_paiement='wave' AND type_paiement='paiement' THEN montant ELSE 0 END),0) as wave,
        COALESCE(SUM(CASE WHEN mode_paiement='om' AND type_paiement='paiement' THEN montant ELSE 0 END),0) as om,
        COALESCE(SUM(CASE WHEN mode_paiement='carte' AND type_paiement='paiement' THEN montant ELSE 0 END),0) as carte
     FROM paiements p
     WHERE DATE(p.created_at) = :date" . $sessionClause,
    $sessionParams
);
$totaux['especes'] = (float)$totaux['especes_in'] - (float)$totaux['especes_out'];

// Totaux par catégorie d'encaissement
$totauxCategories = Database::fetchAll(
    "SELECT cat.libelle, cat.icone, cat.couleur,
            COALESCE(SUM(CASE WHEN p.type_paiement='paiement' THEN p.montant ELSE 0 END),0) as total_in,
            COALESCE(SUM(CASE WHEN p.type_paiement='remboursement' THEN p.montant ELSE 0 END),0) as total_out,
            COUNT(*) as nb
     FROM paiements p
     LEFT JOIN categories_encaissement cat ON p.categorie_id = cat.id
     WHERE DATE(p.created_at) = :date" . $sessionClause . "
     GROUP BY p.categorie_id, cat.libelle, cat.icone, cat.couleur
     ORDER BY total_in DESC",
    $sessionParams
);

// Encaissements par terrain (uniquement les paiements liés à une réservation)
$paiementsParTerrain = Database::fetchAll(
    "SELECT t.id, t.nom as terrain_nom,
            COUNT(p.id) as nb_paiements,
            COUNT(DISTINCT r.id) as nb_reservations,
            COALESCE(SUM(CASE WHEN p.type_paiement='paiement'      THEN p.montant END), 0) as total_in,
            COALESCE(SUM(CASE WHEN p.type_paiement='remboursement' THEN p.montant END), 0) as total_out
     FROM paiements p
     JOIN reservations r ON p.reservation_id = r.id
     JOIN terrains t ON r.terrain_id = t.id
     WHERE DATE(p.created_at) = :date" . $sessionClause . "
     GROUP BY t.id, t.nom
     HAVING (total_in - total_out) <> 0
     ORDER BY total_in DESC",
    $sessionParams
);

// Dépenses du jour par catégorie (toutes catégories, statut payée).
// Si un filtre session est actif, on ne montre que les dépenses attachées à cette session.
$depSessionClause = '';
$depSessionParams = ['date' => $date];
if ($sessionFilter > 0) {
    $depSessionClause = ' AND d.session_caisse_id = :sid';
    $depSessionParams['sid'] = $sessionFilter;
}

$depensesParCategorie = Database::fetchAll(
    "SELECT c.nom as libelle, c.icone, c.couleur,
            COUNT(d.id) as nb, COALESCE(SUM(d.montant), 0) as total
     FROM depenses d
     JOIN categories_depenses c ON d.categorie_id = c.id
     WHERE d.statut = 'payee' AND d.date_depense = :date" . $depSessionClause . "
     GROUP BY c.id, c.nom, c.icone, c.couleur
     HAVING total > 0
     ORDER BY total DESC",
    $depSessionParams
);
$totalDepensesJour = (float)array_sum(array_column($depensesParCategorie, 'total'));

// Fond de caisse total : si filtre session actif → fond de cette session, sinon somme des fonds de toutes les sessions du jour
$totalFond = 0.0;
if ($sessionFilter > 0 && $sessionSelectionnee) {
    $totalFond = (float)$sessionSelectionnee['fond_caisse'];
} else {
    foreach ($sessions as $s) {
        $totalFond += (float)$s['fond_caisse'];
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h4 class="mb-1"><i class="fas fa-cash-register me-2"></i>Caisse — vue d'ensemble</h4>
        <p class="text-muted mb-0">
            <?= formatDateFr($date) ?>
            <?php if ($sessionSelectionnee): ?>
                <span class="badge bg-info ms-2">
                    <i class="fas fa-filter me-1"></i>Caissier
                    : <?= e(trim(($sessionSelectionnee['user_prenom'] ?? '') . ' ' . ($sessionSelectionnee['user_nom'] ?? ''))) ?>
                    (session #<?= $sessionSelectionnee['id'] ?>)
                </span>
                <a href="<?= url('paiements/caisse.php?date=' . $date) ?>" class="ms-2 small text-decoration-none">
                    <i class="fas fa-times"></i> retirer le filtre
                </a>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <form method="GET" class="d-flex gap-2 align-items-center">
            <input type="date" class="form-control form-control-sm" name="date" value="<?= e($date) ?>" onchange="this.form.submit()">
            <?php if (!empty($sessions)): ?>
            <select name="session_id" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 220px;">
                <option value="0">Toutes les caisses</option>
                <?php foreach ($sessions as $s):
                    $label = trim(($s['user_prenom'] ?? '') . ' ' . ($s['user_nom'] ?? ''));
                    $statut = match($s['statut']) {
                        'ouverte' => '🟢',
                        'cloturee' => '🟡',
                        'validee' => '🔵',
                        default => '⚪'
                    };
                ?>
                    <option value="<?= $s['id'] ?>" <?= $sessionFilter == $s['id'] ? 'selected' : '' ?>>
                        <?= $statut ?> <?= e($label) ?> — #<?= $s['id'] ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </form>
        <a href="<?= url('paiements/ma-caisse.php') ?>" class="btn btn-success">
            <i class="fas fa-cash-register me-2"></i>Ma caisse
        </a>
        <a href="<?= url('paiements/nouveau.php') ?>" class="btn btn-accent">
            <i class="fas fa-plus me-2"></i>Encaissement
        </a>
    </div>
</div>

<!-- Totaux globaux -->
<div class="row g-3 mb-4">
    <div class="col-md-2 col-6">
        <div class="card bg-primary text-white h-100">
            <div class="card-body text-center py-3"
                 title="<?= $sessionFilter > 0 ? 'Fond de cette session' : 'Somme des fonds des sessions du jour' ?>">
                <i class="fas fa-cash-register fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= formatMoney($totalFond) ?></div>
                <small>Fond de caisse</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-success text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-arrow-down fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">+ <?= formatMoney($totaux['total_encaisse']) ?></div>
                <small>Encaissé</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-danger text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-arrow-up fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">- <?= formatMoney($totaux['total_rembourse']) ?></div>
                <small>Remboursé</small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-warning text-dark h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-file-invoice-dollar fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0">- <?= formatMoney($totalDepensesJour) ?></div>
                <small>Dépenses<?= $sessionFilter > 0 ? ' (caisse)' : ' du jour' ?></small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <?php $soldeJour = $totalFond + ($totaux['total_encaisse'] - $totaux['total_rembourse']) - $totalDepensesJour; ?>
        <div class="card bg-<?= $soldeJour >= 0 ? 'info' : 'dark' ?> text-white h-100">
            <div class="card-body text-center py-3"
                 title="Fond + (Encaissé − Remboursé) − Dépenses">
                <i class="fas fa-balance-scale fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= formatMoney($soldeJour) ?></div>
                <small>
                    Solde net
                    <small class="d-block opacity-75" style="font-size: 0.7em;">
                        fond <?= formatMoney($totalFond) ?> incl.
                    </small>
                </small>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="card bg-dark text-white h-100">
            <div class="card-body text-center py-3">
                <i class="fas fa-receipt fa-2x mb-2 opacity-75"></i>
                <div class="h5 mb-0"><?= $totaux['nb_transactions'] ?></div>
                <small>Transactions</small>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Sessions caissiers -->
    <div class="col-xl-6">
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Sessions caissiers du jour</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($sessions)): ?>
                    <div class="text-center py-4 text-muted">Aucune session ce jour</div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($sessions as $s): ?>
                        <?php
                        $statutBadge = match($s['statut']) {
                            'ouverte' => '<span class="badge bg-success"><i class="fas fa-circle me-1"></i>Ouverte</span>',
                            'cloturee' => '<span class="badge bg-warning text-dark"><i class="fas fa-lock me-1"></i>Clôturée</span>',
                            'validee' => '<span class="badge bg-primary"><i class="fas fa-check-circle me-1"></i>Validée</span>',
                            default => '<span class="badge bg-secondary">' . e($s['statut']) . '</span>'
                        };
                        ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?= e(($s['user_prenom'] ?? '') . ' ' . ($s['user_nom'] ?? '')) ?></strong>
                                    <?= $statutBadge ?>
                                    <a href="<?= url('paiements/caisse.php?date=' . $date . '&session_id=' . $s['id']) ?>"
                                       class="btn btn-sm btn-outline-info ms-1" title="Filtrer la vue sur cette session">
                                        <i class="fas fa-filter"></i>
                                    </a>
                                    <a href="<?= url('paiements/session.php?id=' . $s['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Voir le détail complet">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <br>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        Ouverte <?= formatDate($s['heure_ouverture'], 'H:i') ?>
                                        <?php if ($s['heure_cloture']): ?>
                                            → fermée <?= formatDate($s['heure_cloture'], 'H:i') ?>
                                        <?php endif; ?>
                                        — <?= $s['live_nb_transactions'] ?> tx
                                        <?php if ($s['live_nb_depenses'] > 0): ?>
                                            · <?= $s['live_nb_depenses'] ?> dép
                                        <?php endif; ?>
                                    </small>
                                </div>
                                <div class="text-end small">
                                    <div><span class="text-muted">Fond :</span> <strong><?= formatMoney($s['fond_caisse']) ?></strong></div>
                                    <div><span class="text-muted">Encaissé :</span>
                                        <strong class="text-success">+ <?= formatMoney($s['live_total_encaisse']) ?></strong>
                                    </div>
                                    <?php if ($s['live_total_rembourse'] > 0): ?>
                                    <div><span class="text-muted">Remboursé :</span>
                                        <strong class="text-danger">- <?= formatMoney($s['live_total_rembourse']) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($s['live_total_depenses'] > 0): ?>
                                    <div><span class="text-muted">Dépenses :</span>
                                        <strong class="text-warning">- <?= formatMoney($s['live_total_depenses']) ?></strong>
                                    </div>
                                    <?php endif; ?>
                                    <div class="border-top mt-1 pt-1"
                                         title="Solde net = fond + (encaissé − remboursé) − dépenses">
                                        <span class="text-muted">Solde net :</span>
                                        <strong class="text-<?= $s['live_solde'] >= 0 ? 'primary' : 'danger' ?>">
                                            <?= formatMoney($s['live_solde']) ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>

                            <?php if ($s['statut'] !== 'ouverte'): ?>
                            <div class="mt-2 pt-2 border-top">
                                <div class="row g-2 small">
                                    <div class="col-6"><span class="text-muted">Espèces attendues :</span> <strong><?= formatMoney($s['solde_caisse']) ?></strong></div>
                                    <div class="col-6"><span class="text-muted">Déclaré :</span> <strong><?= formatMoney($s['montant_declare']) ?></strong></div>
                                    <div class="col-12">
                                        <span class="text-muted">Écart :</span>
                                        <strong class="<?= ($s['ecart'] ?? 0) == 0 ? 'text-muted' : (($s['ecart'] ?? 0) > 0 ? 'text-success' : 'text-danger') ?>">
                                            <?= formatMoney($s['ecart'] ?? 0) ?>
                                            <?= ($s['ecart'] ?? 0) > 0 ? ' (excédent)' : (($s['ecart'] ?? 0) < 0 ? ' (manquant)' : '') ?>
                                        </strong>
                                    </div>
                                    <?php if (!empty($s['commentaire'])): ?>
                                    <div class="col-12"><small class="fst-italic text-muted"><?= e($s['commentaire']) ?></small></div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($s['statut'] === 'cloturee' && $canValidate): ?>
                                <form method="POST" class="mt-2 d-inline"
                                      data-confirm="Valider définitivement cette session de caisse ?"
                                      data-confirm-title="Valider la session"
                                      data-confirm-text="<i class='fas fa-check me-1'></i> Valider"
                                      data-confirm-class="btn-primary"
                                      data-confirm-icon="fa-check-circle"
                                      data-confirm-icon-class="text-primary">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="action" value="valider">
                                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-check me-1"></i>Valider la session
                                    </button>
                                </form>
                                <?php elseif ($s['statut'] === 'validee'): ?>
                                <small class="text-success"><i class="fas fa-check me-1"></i>Validée par <?= e($s['validee_par_nom'] ?? '') ?>
                                    <?= $s['heure_validation'] ? ' le ' . formatDate($s['heure_validation'], 'd/m H:i') : '' ?>
                                </small>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>

        <!-- Détail par mode de paiement -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-wallet me-2"></i>Par mode de paiement (encaissé)</h6>
            </div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tbody>
                        <tr>
                            <td><i class="fas fa-money-bill text-success me-2"></i>Espèces (net)</td>
                            <td class="text-end fw-bold"><?= formatMoney($totaux['especes']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-mobile-alt text-info me-2"></i>Wave</td>
                            <td class="text-end fw-bold"><?= formatMoney($totaux['wave']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-mobile-alt text-warning me-2"></i>Orange Money</td>
                            <td class="text-end fw-bold"><?= formatMoney($totaux['om']) ?></td>
                        </tr>
                        <tr>
                            <td><i class="fas fa-credit-card text-primary me-2"></i>Carte</td>
                            <td class="text-end fw-bold"><?= formatMoney($totaux['carte']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Détail par catégorie d'encaissement -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Encaissements par catégorie</h6>
            </div>
            <div class="card-body">
                <?php if (empty($totauxCategories)): ?>
                    <p class="text-muted mb-0">Aucune transaction</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($totauxCategories as $tc): ?>
                        <tr>
                            <td>
                                <?php if (!empty($tc['icone'])): ?>
                                    <i class="fas <?= e($tc['icone']) ?> me-2" style="color: <?= e($tc['couleur'] ?? '#666') ?>;"></i>
                                <?php endif; ?>
                                <?= e($tc['libelle'] ?? 'Sans catégorie') ?>
                                <small class="text-muted">(<?= $tc['nb'] ?>)</small>
                            </td>
                            <td class="text-end">
                                <span class="text-success fw-bold">+ <?= formatMoney($tc['total_in']) ?></span>
                                <?php if ($tc['total_out'] > 0): ?>
                                    <br><small class="text-danger">- <?= formatMoney($tc['total_out']) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Encaissements par terrain (réservations uniquement) -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-futbol me-2"></i>Encaissements par terrain</h6>
            </div>
            <div class="card-body">
                <?php if (empty($paiementsParTerrain)): ?>
                    <p class="text-muted mb-0">Aucun encaissement lié à un terrain</p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Terrain</th>
                            <th class="text-center">Réservations</th>
                            <th class="text-end">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paiementsParTerrain as $pt): $netTerrain = (float)$pt['total_in'] - (float)$pt['total_out']; ?>
                        <tr>
                            <td>
                                <i class="fas fa-map-marker-alt text-primary me-1"></i>
                                <?= e($pt['terrain_nom']) ?>
                            </td>
                            <td class="text-center">
                                <span class="badge bg-primary"><?= $pt['nb_reservations'] ?></span>
                                <small class="text-muted">/ <?= $pt['nb_paiements'] ?> pmts</small>
                            </td>
                            <td class="text-end">
                                <strong class="text-success">+ <?= formatMoney($pt['total_in']) ?></strong>
                                <?php if ($pt['total_out'] > 0): ?>
                                    <br><small class="text-danger">- <?= formatMoney($pt['total_out']) ?></small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dépenses par catégorie (filtrées par session si filtre actif) -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0">
                    <i class="fas fa-file-invoice-dollar me-2"></i>Dépenses<?= $sessionFilter > 0 ? ' de cette caisse' : ' du jour' ?> par catégorie
                </h6>
                <?php if ($sessionFilter > 0): ?>
                    <span class="badge bg-info"><i class="fas fa-filter me-1"></i>Session #<?= $sessionFilter ?></span>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <?php if (empty($depensesParCategorie)): ?>
                    <p class="text-muted mb-0">
                        <?= $sessionFilter > 0
                            ? 'Aucune dépense attachée à cette session'
                            : 'Aucune dépense ce jour' ?>
                    </p>
                <?php else: ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($depensesParCategorie as $dep): ?>
                        <tr>
                            <td>
                                <?php if (!empty($dep['icone'])): ?>
                                    <i class="fas <?= e($dep['icone']) ?> me-2" style="color: <?= e($dep['couleur'] ?? '#dc3545') ?>;"></i>
                                <?php endif; ?>
                                <?= e($dep['libelle']) ?>
                                <small class="text-muted">(<?= $dep['nb'] ?>)</small>
                            </td>
                            <td class="text-end">
                                <strong class="text-danger">- <?= formatMoney($dep['total']) ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td class="fw-bold">TOTAL dépenses</td>
                            <td class="text-end fw-bold text-danger">- <?= formatMoney($totalDepensesJour) ?></td>
                        </tr>
                    </tfoot>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Transactions -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-receipt me-2"></i>Transactions du jour</h6>
                <span class="badge bg-primary"><?= count($paiements) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($paiements)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-receipt fa-3x mb-3 opacity-50"></i>
                        <p class="mb-0">Aucune transaction</p>
                    </div>
                <?php else: ?>
                <div class="table-responsive" style="max-height: 800px;">
                    <table class="table table-hover table-sm mb-0">
                        <thead class="sticky-top bg-light">
                            <tr>
                                <th>Heure</th>
                                <th>Catégorie / Détail</th>
                                <th>Caissier</th>
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
                                        <i class="fas <?= e($p['categorie_icone']) ?> me-1" style="color: <?= e($p['categorie_couleur'] ?? '#666') ?>;"></i>
                                    <?php endif; ?>
                                    <small><?= e($p['categorie_libelle'] ?? 'Sans catégorie') ?></small>
                                    <?php if ($isRefund): ?>
                                        <span class="badge bg-danger">REMB</span>
                                    <?php endif; ?>
                                    <br>
                                    <?php if (!empty($p['numero_ticket'])): ?>
                                        <a href="<?= url('reservations/voir.php?id=' . $p['reservation_id']) ?>" class="small">
                                            <?= e($p['numero_ticket']) ?>
                                        </a>
                                        <small class="text-muted">— <?= e($p['client_nom']) ?></small>
                                    <?php else: ?>
                                        <small><?= e($p['libelle'] ?? '-') ?></small>
                                    <?php endif; ?>
                                    <?php if ($isRefund && !empty($p['motif'])): ?>
                                        <br><small class="text-muted fst-italic"><?= e($p['motif']) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><small><?= e(recuParLabel($p['recu_par_nom'] ?? null, $p['mode_paiement'] ?? '')) ?></small></td>
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
                                    <i class="fas <?= $modeIcon ?>" title="<?= ucfirst($p['mode_paiement']) ?>"></i>
                                </td>
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
            <?php if (!empty($paiements)): ?>
            <div class="card-footer">
                <button type="button" class="btn btn-outline-primary btn-sm" onclick="window.print();">
                    <i class="fas fa-print me-2"></i>Imprimer
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
