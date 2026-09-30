<?php
/**
 * Liste des notifications
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Notification.php';

Auth::requireLogin();

$pageTitle = 'Mes notifications';
$breadcrumb = [
    ['label' => 'Notifications']
];

$userId = Auth::id();

// Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'mark_read') {
        $id = (int)post('id');
        if ($id) {
            Notification::markAsRead($id, $userId);
        }
    } elseif ($action === 'mark_all_read') {
        Notification::markAllAsRead($userId);
        setFlash('success', 'Toutes les notifications ont été marquées comme lues.');
    } elseif ($action === 'delete') {
        $id = (int)post('id');
        if ($id) {
            Notification::delete($id, $userId);
            setFlash('success', 'Notification supprimée.');
        }
    }

    redirect(url('notifications/index'));
}

// Filtres
$filter = get('filter', 'all');
$unreadOnly = $filter === 'unread';

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 20;

// Récupérer les notifications
$notifications = Notification::getForUser($userId, 100, $unreadOnly);
$total = count($notifications);
$totalPages = ceil($total / $perPage);

// Paginer manuellement
$notifications = array_slice($notifications, ($page - 1) * $perPage, $perPage);

// Compteur non-lues
$unreadCount = Notification::countUnread($userId);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Mes notifications</h4>
        <p class="text-muted mb-0">
            <?= $total ?> notification(s)
            <?php if ($unreadCount > 0): ?>
                <span class="badge bg-danger ms-2"><?= $unreadCount ?> non lue(s)</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($unreadCount > 0): ?>
        <form method="POST" class="d-inline">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="mark_all_read">
            <button type="submit" class="btn btn-outline-primary">
                <i class="fas fa-check-double me-2"></i>Tout marquer comme lu
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="btn-group" role="group">
            <a href="<?= url('notifications/index') ?>"
               class="btn btn-sm <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Toutes
            </a>
            <a href="<?= url('notifications/index?filter=unread') ?>"
               class="btn btn-sm <?= $filter === 'unread' ? 'btn-primary' : 'btn-outline-primary' ?>">
                Non lues
                <?php if ($unreadCount > 0): ?>
                    <span class="badge bg-white text-primary ms-1"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>
</div>

<!-- Liste des notifications -->
<div class="card">
    <?php if (empty($notifications)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-bell-slash fa-3x text-muted mb-3 opacity-50"></i>
            <h5 class="text-muted">Aucune notification</h5>
            <p class="text-muted mb-0">
                <?= $filter === 'unread' ? 'Vous n\'avez pas de notifications non lues.' : 'Vous n\'avez pas encore reçu de notifications.' ?>
            </p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications as $notif): ?>
                <?php
                $isUnread = $notif['lu'] == 0;
                $typeColors = [
                    'info' => 'primary',
                    'success' => 'success',
                    'warning' => 'warning',
                    'danger' => 'danger'
                ];
                $color = $typeColors[$notif['type']] ?? 'secondary';
                ?>
                <div class="list-group-item <?= $isUnread ? 'bg-light' : '' ?>">
                    <div class="d-flex align-items-start">
                        <div class="notification-icon-lg bg-<?= $color ?> text-white me-3">
                            <i class="fas fa-<?= e($notif['icone'] ?? 'bell') ?>"></i>
                        </div>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1 <?= $isUnread ? 'fw-bold' : '' ?>">
                                        <?= e($notif['titre']) ?>
                                        <?php if ($isUnread): ?>
                                            <span class="badge bg-primary ms-2">Nouveau</span>
                                        <?php endif; ?>
                                    </h6>
                                    <p class="mb-1 text-muted"><?= e($notif['message']) ?></p>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        <?= formatDate($notif['created_at'], 'd/m/Y à H:i') ?>
                                    </small>
                                </div>
                                <div class="d-flex gap-2">
                                    <?php if ($notif['lien']): ?>
                                        <a href="<?= e($notif['lien']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($isUnread): ?>
                                        <form method="POST" class="d-inline">
                                            <?= csrfField() ?>
                                            <input type="hidden" name="action" value="mark_read">
                                            <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Marquer comme lu">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>

                                    <form method="POST" class="d-inline"
                                          data-confirm="Supprimer cette notification ?"
                                          data-confirm-title="Supprimer la notification"
                                          data-confirm-text="<i class='fas fa-trash me-1'></i> Supprimer"
                                          data-confirm-class="btn-danger"
                                          data-confirm-icon="fa-bell-slash"
                                          data-confirm-icon-class="text-danger">
                                        <?= csrfField() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= $notif['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Supprimer">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?= pagination($page, $totalPages, url('notifications/index') . ($filter !== 'all' ? '?filter=' . $filter . '&' : '?')) ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.notification-icon-lg {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
</style>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
