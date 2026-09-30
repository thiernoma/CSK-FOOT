<?php
/**
 * Logs d'audit
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';

Auth::requireLogin();
Auth::requireAdmin();

$pageTitle = 'Logs d\'audit';
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Logs d\'audit']
];

// Filtres
$filters = [
    'user_id' => (int)get('user'),
    'action' => sanitize(get('action')),
    'table' => sanitize(get('table')),
    'date_debut' => sanitize(get('date_debut')),
    'date_fin' => sanitize(get('date_fin'))
];

// Construction de la requête
$where = ['1=1'];
$params = [];

if ($filters['user_id']) {
    $where[] = 'l.user_id = :user_id';
    $params['user_id'] = $filters['user_id'];
}

if ($filters['action']) {
    $where[] = 'l.action = :action';
    $params['action'] = $filters['action'];
}

if ($filters['table']) {
    $where[] = 'l.table_cible = :table_cible';
    $params['table_cible'] = $filters['table'];
}

if ($filters['date_debut']) {
    $where[] = 'DATE(l.created_at) >= :date_debut';
    $params['date_debut'] = $filters['date_debut'];
}

if ($filters['date_fin']) {
    $where[] = 'DATE(l.created_at) <= :date_fin';
    $params['date_fin'] = $filters['date_fin'];
}

$whereClause = implode(' AND ', $where);

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

// Total
$total = Database::fetchOne(
    "SELECT COUNT(*) as total FROM logs_audit l WHERE $whereClause",
    $params
)['total'];

$totalPages = ceil($total / $perPage);

// Données
$logs = Database::fetchAll(
    "SELECT l.*, u.nom as user_nom, u.email as user_email
     FROM logs_audit l
     LEFT JOIN users u ON l.user_id = u.id
     WHERE $whereClause
     ORDER BY l.created_at DESC
     LIMIT $perPage OFFSET $offset",
    $params
);

// Liste des utilisateurs pour le filtre
$users = Database::fetchAll("SELECT id, nom FROM users ORDER BY nom");

// Liste des actions uniques
$actions = Database::fetchAll("SELECT DISTINCT action FROM logs_audit ORDER BY action");

// Liste des tables uniques
$tables = Database::fetchAll("SELECT DISTINCT table_cible FROM logs_audit WHERE table_cible IS NOT NULL ORDER BY table_cible");

// Traduction des actions
function translateAction($action) {
    return match($action) {
        'create' => 'Création',
        'update' => 'Modification',
        'delete' => 'Suppression',
        'login' => 'Connexion',
        'logout' => 'Déconnexion',
        'payment' => 'Paiement',
        'view' => 'Consultation',
        default => ucfirst($action)
    };
}

// Badge couleur pour action
function actionBadgeClass($action) {
    return match($action) {
        'create' => 'bg-success',
        'update' => 'bg-primary',
        'delete' => 'bg-danger',
        'login' => 'bg-info',
        'logout' => 'bg-secondary',
        'payment' => 'bg-warning',
        default => 'bg-light text-dark'
    };
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Logs d'audit</h4>
        <p class="text-muted mb-0"><?= number_format($total) ?> entrée(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('admin/logs.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-sync"></i>
        </a>
        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#purgeModal">
            <i class="fas fa-trash me-2"></i>Purger
        </button>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="user">
                    <option value="">Tous les utilisateurs</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= $u['id'] ?>" <?= $filters['user_id'] == $u['id'] ? 'selected' : '' ?>>
                            <?= e($u['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="action">
                    <option value="">Toutes actions</option>
                    <?php foreach ($actions as $a): ?>
                        <option value="<?= $a['action'] ?>" <?= $filters['action'] === $a['action'] ? 'selected' : '' ?>>
                            <?= translateAction($a['action']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="table">
                    <option value="">Toutes tables</option>
                    <?php foreach ($tables as $t): ?>
                        <option value="<?= $t['table_cible'] ?>" <?= $filters['table'] === $t['table_cible'] ? 'selected' : '' ?>>
                            <?= e($t['table_cible']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_debut"
                       value="<?= e($filters['date_debut']) ?>" placeholder="Date début">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_fin"
                       value="<?= e($filters['date_fin']) ?>" placeholder="Date fin">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-primary w-100">
                    <i class="fas fa-filter me-1"></i>Filtrer
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des logs -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($logs)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-history fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">Aucun log trouvé</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" style="font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="width:160px;">Date/Heure</th>
                            <th>Utilisateur</th>
                            <th>Action</th>
                            <th>Table</th>
                            <th>ID</th>
                            <th>IP</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="text-muted">
                                <?= formatDate($log['created_at'], 'd/m/Y H:i:s') ?>
                            </td>
                            <td>
                                <?php if ($log['user_nom']): ?>
                                    <strong><?= e($log['user_nom']) ?></strong>
                                    <br><small class="text-muted"><?= e($log['user_email']) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">Système</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge <?= actionBadgeClass($log['action']) ?>">
                                    <?= translateAction($log['action']) ?>
                                </span>
                            </td>
                            <td>
                                <code><?= e($log['table_cible'] ?? '-') ?></code>
                            </td>
                            <td>
                                <?php if ($log['id_cible']): ?>
                                    <span class="badge bg-light text-dark">#<?= $log['id_cible'] ?></span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= e($log['ip_address']) ?></small>
                            </td>
                            <td>
                                <?php
                                $details = $log['nouvelles_valeurs'] ?? $log['anciennes_valeurs'];
                                if ($details): ?>
                                    <button class="btn btn-sm btn-outline-secondary"
                                            data-bs-toggle="popover"
                                            data-bs-trigger="click"
                                            data-bs-html="true"
                                            data-bs-content="<pre style='max-width:300px;font-size:11px;white-space:pre-wrap;'><?= e($details) ?></pre>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?= pagination($page, $totalPages, url('admin/logs') . '?' . http_build_query(array_filter($filters))) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Purge -->
<div class="modal fade" id="purgeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('admin/logs-purge.php') ?>">
                <?= csrfField() ?>

                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Purger les logs</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Cette action est irréversible. Les logs supprimés ne pourront pas être récupérés.
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Supprimer les logs de plus de</label>
                        <select class="form-select" name="older_than" required>
                            <option value="30">30 jours</option>
                            <option value="60">60 jours</option>
                            <option value="90" selected>90 jours</option>
                            <option value="180">6 mois</option>
                            <option value="365">1 an</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Purger
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$inlineJs = "
// Initialiser les popovers
var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle=\"popover\"]'))
var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
    return new bootstrap.Popover(popoverTriggerEl)
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
