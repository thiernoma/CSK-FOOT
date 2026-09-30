<?php
/**
 * Gestion des messages de contact
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MessageContact.php';

Auth::requireLogin();
Auth::requireAdmin();

$pageTitle = 'Messages de contact';
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Messages']
];

// Filtres
$filters = [
    'statut' => sanitize(get('statut')),
    'sujet' => sanitize(get('sujet')),
    'recherche' => sanitize(get('q')),
    'date_debut' => sanitize(get('date_debut')),
    'date_fin' => sanitize(get('date_fin'))
];

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 20;
$total = MessageContact::count($filters);
$pagination = paginate($total, $perPage, $page);
$messages = MessageContact::getAll($filters, $perPage, $pagination['offset']);

// Stats
$stats = MessageContact::getStats();

// Traitement des actions POST
$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action');
        $messageId = (int)post('message_id');

        switch ($action) {
            case 'mark_read':
                if (MessageContact::markAsRead($messageId)) {
                    Session::flash('success', 'Message marqué comme lu.');
                }
                redirect(url('admin/messages.php') . '?' . http_build_query($_GET));
                break;

            case 'reply':
                $reponse = trim(post('reponse'));
                if (empty($reponse)) {
                    $errors[] = 'La réponse ne peut pas être vide.';
                } else {
                    if (MessageContact::markAsReplied($messageId, $reponse, Auth::id())) {
                        Session::flash('success', 'Réponse enregistrée avec succès.');
                        redirect(url('admin/messages.php'));
                    } else {
                        $errors[] = 'Erreur lors de l\'enregistrement de la réponse.';
                    }
                }
                break;

            case 'archive':
                if (MessageContact::archive($messageId)) {
                    Session::flash('success', 'Message archivé.');
                }
                redirect(url('admin/messages.php') . '?' . http_build_query($_GET));
                break;

            case 'delete':
                if (MessageContact::delete($messageId)) {
                    Session::flash('success', 'Message supprimé.');
                    Auth::logAction(Auth::id(), 'delete', 'messages_contact', $messageId);
                }
                redirect(url('admin/messages.php') . '?' . http_build_query($_GET));
                break;
        }
    }
}

// Vue détail d'un message ?
$viewMessage = null;
if (get('id')) {
    $viewMessage = MessageContact::getById((int)get('id'));
    if ($viewMessage && $viewMessage['statut'] === 'nouveau') {
        MessageContact::markAsRead($viewMessage['id']);
        $viewMessage['statut'] = 'lu';
    }
}

// Labels pour les sujets
$sujetsLabels = [
    'reservation' => 'Réservation terrain',
    'academie' => 'Inscription académie',
    'partenariat' => 'Partenariat',
    'autre' => 'Autre'
];

// Labels pour les statuts
$statutsLabels = [
    'nouveau' => 'Nouveau',
    'lu' => 'Lu',
    'traite' => 'Traité',
    'archive' => 'Archivé'
];

$statutsClasses = [
    'nouveau' => 'bg-danger',
    'lu' => 'bg-warning text-dark',
    'traite' => 'bg-success',
    'archive' => 'bg-secondary'
];

include VIEWS_PATH . 'layouts/header.php';
?>

<style>
.message-card {
    transition: all 0.2s;
    border-left: 4px solid transparent;
}
.message-card:hover {
    background-color: #f8f9fa;
}
.message-card.unread {
    background-color: #fff8e6;
    border-left-color: #ffc107;
}
.message-card.unread .message-sender {
    font-weight: 700;
}
.message-preview {
    color: #6c757d;
    font-size: 0.9rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 400px;
}
.message-meta {
    font-size: 0.85rem;
    color: #6c757d;
}
.message-actions {
    opacity: 0;
    transition: opacity 0.2s;
}
.message-card:hover .message-actions {
    opacity: 1;
}
.stat-card-mini {
    text-align: center;
    padding: 15px;
    border-radius: 10px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}
.stat-card-mini.nouveaux {
    background: linear-gradient(135deg, #fff3cd 0%, #ffeeba 100%);
}
.stat-card-mini .stat-value {
    font-size: 1.8rem;
    font-weight: 700;
    color: var(--primary);
}
.stat-card-mini .stat-label {
    font-size: 0.85rem;
    color: #6c757d;
}
.detail-panel {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}
.message-content {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    line-height: 1.7;
    white-space: pre-wrap;
}
.response-box {
    background: #e8f5e9;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #28a745;
}
</style>

<?php if ($viewMessage): ?>
<!-- Vue détail du message -->
<div class="row">
    <div class="col-lg-8">
        <div class="card detail-panel">
            <div class="card-header d-flex justify-content-between align-items-center">
                <div>
                    <a href="<?= url('admin/messages.php') ?>" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <span class="badge <?= $statutsClasses[$viewMessage['statut']] ?> me-2">
                        <?= $statutsLabels[$viewMessage['statut']] ?>
                    </span>
                    <span class="badge bg-info">
                        <?= $sujetsLabels[$viewMessage['sujet']] ?? $viewMessage['sujet'] ?>
                    </span>
                </div>
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    <?= formatDateFr($viewMessage['created_at']) ?> à <?= formatTime($viewMessage['created_at']) ?>
                </small>
            </div>
            <div class="card-body">
                <!-- Infos expéditeur -->
                <div class="row mb-4">
                    <div class="col-md-4">
                        <label class="text-muted small">Nom</label>
                        <div class="fw-bold"><?= e($viewMessage['nom']) ?></div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Téléphone</label>
                        <div>
                            <a href="tel:<?= $viewMessage['telephone'] ?>" class="text-decoration-none">
                                <i class="fas fa-phone me-1"></i><?= formatPhone($viewMessage['telephone']) ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label class="text-muted small">Email</label>
                        <div>
                            <?php if ($viewMessage['email']): ?>
                            <a href="mailto:<?= $viewMessage['email'] ?>" class="text-decoration-none">
                                <i class="fas fa-envelope me-1"></i><?= e($viewMessage['email']) ?>
                            </a>
                            <?php else: ?>
                            <span class="text-muted">-</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Message -->
                <label class="text-muted small">Message</label>
                <div class="message-content mb-4">
                    <?= nl2br(e($viewMessage['message'])) ?>
                </div>

                <!-- Réponse existante -->
                <?php if ($viewMessage['reponse']): ?>
                <label class="text-muted small">
                    <i class="fas fa-reply me-1"></i>Réponse
                    (par <?= e($viewMessage['repondu_par_prenom'] . ' ' . $viewMessage['repondu_par_nom']) ?>
                    le <?= formatDate($viewMessage['repondu_le']) ?>)
                </label>
                <div class="response-box">
                    <?= nl2br(e($viewMessage['reponse'])) ?>
                </div>
                <?php endif; ?>

                <!-- Formulaire de réponse -->
                <?php if ($viewMessage['statut'] !== 'traite' && $viewMessage['statut'] !== 'archive'): ?>
                <hr class="my-4">
                <form method="post" action="<?= url('admin/messages.php') ?>">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="reply">
                    <input type="hidden" name="message_id" value="<?= $viewMessage['id'] ?>">

                    <div class="mb-3">
                        <label class="form-label">
                            <i class="fas fa-reply me-1"></i>Votre réponse
                        </label>
                        <textarea class="form-control" name="reponse" rows="5" required
                                  placeholder="Écrivez votre réponse ici..."></textarea>
                        <small class="text-muted">
                            Note: Cette réponse est enregistrée pour suivi interne. Pour répondre au client,
                            utilisez le téléphone ou l'email ci-dessus.
                        </small>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-1"></i>Enregistrer et marquer traité
                        </button>
                        <a href="<?= url('admin/messages.php') ?>" class="btn btn-outline-secondary">Annuler</a>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <!-- Actions rapides -->
        <div class="card mb-3">
            <div class="card-header">
                <i class="fas fa-bolt me-2"></i>Actions rapides
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($viewMessage['telephone']): ?>
                    <a href="tel:<?= $viewMessage['telephone'] ?>" class="btn btn-outline-primary">
                        <i class="fas fa-phone me-2"></i>Appeler
                    </a>
                    <a href="https://wa.me/221<?= preg_replace('/[^0-9]/', '', $viewMessage['telephone']) ?>"
                       target="_blank" class="btn btn-outline-success">
                        <i class="fab fa-whatsapp me-2"></i>WhatsApp
                    </a>
                    <?php endif; ?>
                    <?php if ($viewMessage['email']): ?>
                    <a href="mailto:<?= $viewMessage['email'] ?>" class="btn btn-outline-info">
                        <i class="fas fa-envelope me-2"></i>Envoyer un email
                    </a>
                    <?php endif; ?>

                    <hr>

                    <?php if ($viewMessage['statut'] !== 'archive'): ?>
                    <form method="post" action="<?= url('admin/messages.php') ?>" class="d-inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="archive">
                        <input type="hidden" name="message_id" value="<?= $viewMessage['id'] ?>">
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-archive me-2"></i>Archiver
                        </button>
                    </form>
                    <?php endif; ?>

                    <form method="post" action="<?= url('admin/messages.php') ?>"
                          data-confirm="Supprimer définitivement ce message ? Cette action est irréversible."
                          data-confirm-title="Supprimer le message"
                          data-confirm-text="<i class='fas fa-trash me-1'></i> Supprimer"
                          data-confirm-class="btn-danger"
                          data-confirm-icon="fa-trash"
                          data-confirm-icon-class="text-danger">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="message_id" value="<?= $viewMessage['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger w-100">
                            <i class="fas fa-trash me-2"></i>Supprimer
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Métadonnées -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-info-circle me-2"></i>Informations
            </div>
            <div class="card-body small">
                <p class="mb-2">
                    <strong>Reçu le:</strong><br>
                    <?= formatDateFr($viewMessage['created_at']) ?> à <?= formatTime($viewMessage['created_at']) ?>
                </p>
                <?php if ($viewMessage['ip_address']): ?>
                <p class="mb-2">
                    <strong>Adresse IP:</strong><br>
                    <?= e($viewMessage['ip_address']) ?>
                </p>
                <?php endif; ?>
                <?php if ($viewMessage['user_agent']): ?>
                <p class="mb-0">
                    <strong>Navigateur:</strong><br>
                    <small class="text-muted"><?= e(substr($viewMessage['user_agent'], 0, 100)) ?>...</small>
                </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php else: ?>
<!-- Liste des messages -->

<!-- Stats rapides -->
<div class="row mb-4">
    <div class="col-6 col-md-3">
        <div class="stat-card-mini nouveaux">
            <div class="stat-value"><?= $stats['nouveaux'] ?? 0 ?></div>
            <div class="stat-label">Nouveaux</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card-mini">
            <div class="stat-value"><?= $stats['lus'] ?? 0 ?></div>
            <div class="stat-label">En attente</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card-mini">
            <div class="stat-value"><?= $stats['traites'] ?? 0 ?></div>
            <div class="stat-label">Traités</div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="stat-card-mini">
            <div class="stat-value"><?= $stats['total'] ?? 0 ?></div>
            <div class="stat-label">Total</div>
        </div>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">Rechercher</label>
                <input type="text" class="form-control" name="q" value="<?= e($filters['recherche']) ?>"
                       placeholder="Nom, téléphone, email...">
            </div>
            <div class="col-md-2">
                <label class="form-label">Statut</label>
                <select class="form-select" name="statut">
                    <option value="">Tous</option>
                    <?php foreach ($statutsLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filters['statut'] === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Sujet</label>
                <select class="form-select" name="sujet">
                    <option value="">Tous</option>
                    <?php foreach ($sujetsLabels as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $filters['sujet'] === $key ? 'selected' : '' ?>>
                        <?= $label ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Du</label>
                <input type="date" class="form-control" name="date_debut" value="<?= e($filters['date_debut']) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Au</label>
                <input type="date" class="form-control" name="date_fin" value="<?= e($filters['date_fin']) ?>">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Liste des messages -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>
            <i class="fas fa-envelope me-2"></i>Messages
            <span class="badge bg-secondary ms-2"><?= $total ?></span>
        </span>
    </div>

    <?php if (empty($messages)): ?>
    <div class="card-body text-center py-5">
        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
        <p class="text-muted mb-0">Aucun message trouvé</p>
    </div>
    <?php else: ?>
    <div class="list-group list-group-flush">
        <?php foreach ($messages as $msg): ?>
        <div class="list-group-item message-card <?= $msg['statut'] === 'nouveau' ? 'unread' : '' ?>">
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center mb-1">
                        <span class="message-sender me-2"><?= e($msg['nom']) ?></span>
                        <span class="badge <?= $statutsClasses[$msg['statut']] ?> me-2" style="font-size:0.7rem;">
                            <?= $statutsLabels[$msg['statut']] ?>
                        </span>
                        <span class="badge bg-light text-dark" style="font-size:0.7rem;">
                            <?= $sujetsLabels[$msg['sujet']] ?? $msg['sujet'] ?>
                        </span>
                    </div>
                    <div class="message-preview mb-1">
                        <?= e(substr($msg['message'], 0, 100)) ?><?= strlen($msg['message']) > 100 ? '...' : '' ?>
                    </div>
                    <div class="message-meta">
                        <i class="fas fa-phone me-1"></i><?= formatPhone($msg['telephone']) ?>
                        <?php if ($msg['email']): ?>
                        <span class="mx-2">|</span>
                        <i class="fas fa-envelope me-1"></i><?= e($msg['email']) ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="text-end">
                    <div class="text-muted small mb-2">
                        <?= timeAgo($msg['created_at']) ?>
                    </div>
                    <div class="message-actions">
                        <a href="<?= url('admin/messages.php?id=' . $msg['id']) ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if ($msg['statut'] !== 'archive'): ?>
                        <form method="post" class="d-inline">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="archive">
                            <input type="hidden" name="message_id" value="<?= $msg['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-secondary" title="Archiver">
                                <i class="fas fa-archive"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="card-footer">
        <?= paginationHtml($pagination, url('admin/messages.php') . '?' . http_build_query(array_filter($filters))) ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
