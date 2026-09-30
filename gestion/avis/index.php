<?php
/**
 * Gestion des avis clients
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Avis.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Avis Clients';

// Filtres
$filters = [
    'terrain_id' => get('terrain') ?: null,
    'statut' => get('statut') ?: null
];

// Récupérer les données
$avis = Avis::getAll($filters);
$terrains = Terrain::getAll('actif');
$stats = Avis::getStatsGlobal();
$classement = Avis::getClassementTerrains();

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="container-fluid py-4">
    <!-- En-tête -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="fas fa-star me-2"></i><?= $pageTitle ?></h1>
            <p class="text-muted mb-0">Modération et statistiques des avis clients</p>
        </div>
    </div>

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Total Avis</h6>
                            <h2 class="mb-0"><?= $stats['total_avis'] ?? 0 ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-comments fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-dark opacity-75">En attente</h6>
                            <h2 class="mb-0"><?= $stats['en_attente'] ?? 0 ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-clock fa-2x text-dark opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-white-50">Approuvés</h6>
                            <h2 class="mb-0"><?= $stats['approuves'] ?? 0 ?></h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-check-circle fa-2x text-white-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="text-muted">Note Moyenne</h6>
                            <h2 class="mb-0">
                                <?= $stats['note_moyenne'] ?? '-' ?>
                                <small class="text-warning"><i class="fas fa-star"></i></small>
                            </h2>
                        </div>
                        <div class="align-self-center">
                            <i class="fas fa-chart-line fa-2x text-muted"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Liste des avis -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <div class="row align-items-center">
                        <div class="col">
                            <h5 class="mb-0">Liste des avis</h5>
                        </div>
                        <div class="col-auto">
                            <form method="GET" class="d-flex gap-2">
                                <select name="statut" class="form-select form-select-sm">
                                    <option value="">Tous les statuts</option>
                                    <option value="en_attente" <?= $filters['statut'] == 'en_attente' ? 'selected' : '' ?>>En attente</option>
                                    <option value="approuve" <?= $filters['statut'] == 'approuve' ? 'selected' : '' ?>>Approuvés</option>
                                    <option value="rejete" <?= $filters['statut'] == 'rejete' ? 'selected' : '' ?>>Rejetés</option>
                                </select>
                                <select name="terrain" class="form-select form-select-sm">
                                    <option value="">Tous les terrains</option>
                                    <?php foreach ($terrains as $terrain): ?>
                                    <option value="<?= $terrain['id'] ?>" <?= $filters['terrain_id'] == $terrain['id'] ? 'selected' : '' ?>>
                                        <?= e($terrain['nom']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-filter"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($avis)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-star text-muted fa-3x mb-3"></i>
                        <p class="text-muted">Aucun avis pour le moment</p>
                    </div>
                    <?php else: ?>
                    <?php foreach ($avis as $a): ?>
                    <div class="border-bottom p-3">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <strong><?= e($a['client_nom_complet'] ?: $a['nom_client'] ?: 'Anonyme') ?></strong>
                                <span class="text-muted ms-2">
                                    <i class="fas fa-futbol me-1"></i><?= e($a['terrain_nom']) ?>
                                </span>
                            </div>
                            <div>
                                <?php
                                $statutClass = match($a['statut']) {
                                    'approuve' => 'success',
                                    'en_attente' => 'warning',
                                    'rejete' => 'danger',
                                    default => 'secondary'
                                };
                                ?>
                                <span class="badge bg-<?= $statutClass ?>"><?= ucfirst($a['statut']) ?></span>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star <?= $i <= $a['note'] ? 'text-warning' : 'text-muted' ?>"></i>
                            <?php endfor; ?>
                            <span class="ms-2 text-muted"><?= $a['note'] ?>/5</span>
                            <?php if ($a['recommande']): ?>
                            <span class="badge bg-success ms-2"><i class="fas fa-thumbs-up me-1"></i>Recommande</span>
                            <?php endif; ?>
                        </div>

                        <!-- Commentaire -->
                        <?php if ($a['commentaire']): ?>
                        <p class="mb-2"><?= nl2br(e($a['commentaire'])) ?></p>
                        <?php endif; ?>

                        <!-- Détails des notes -->
                        <?php if ($a['qualite_terrain'] || $a['proprete'] || $a['eclairage'] || $a['accueil']): ?>
                        <div class="d-flex flex-wrap gap-3 small text-muted mb-2">
                            <?php if ($a['qualite_terrain']): ?>
                            <span>Qualité: <?= $a['qualite_terrain'] ?>/5</span>
                            <?php endif; ?>
                            <?php if ($a['proprete']): ?>
                            <span>Propreté: <?= $a['proprete'] ?>/5</span>
                            <?php endif; ?>
                            <?php if ($a['eclairage']): ?>
                            <span>Éclairage: <?= $a['eclairage'] ?>/5</span>
                            <?php endif; ?>
                            <?php if ($a['accueil']): ?>
                            <span>Accueil: <?= $a['accueil'] ?>/5</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Réponse admin -->
                        <?php if ($a['reponse_admin']): ?>
                        <div class="bg-light p-2 rounded mb-2">
                            <small class="text-muted">Réponse de l'équipe:</small>
                            <p class="mb-0 small"><?= nl2br(e($a['reponse_admin'])) ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                <?= date('d/m/Y H:i', strtotime($a['created_at'])) ?>
                            </small>
                            <div class="btn-group btn-group-sm">
                                <?php if ($a['statut'] === 'en_attente'): ?>
                                <form method="POST" action="<?= url('avis/moderer.php') ?>" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="action" value="approuver">
                                    <button type="submit" class="btn btn-outline-success" title="Approuver">
                                        <i class="fas fa-check"></i>
                                    </button>
                                </form>
                                <form method="POST" action="<?= url('avis/moderer.php') ?>" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="action" value="rejeter">
                                    <button type="submit" class="btn btn-outline-danger" title="Rejeter">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <button type="button" class="btn btn-outline-primary"
                                        onclick="showReplyModal(<?= $a['id'] ?>, '<?= e(addslashes($a['reponse_admin'] ?? '')) ?>')"
                                        title="Répondre">
                                    <i class="fas fa-reply"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger" title="Supprimer"
                                        onclick="confirmDelete(<?= $a['id'] ?>)">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Classement terrains -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <h5 class="mb-0"><i class="fas fa-trophy text-warning me-2"></i>Classement Terrains</h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($classement as $index => $t): ?>
                    <div class="d-flex align-items-center p-3 border-bottom">
                        <div class="flex-shrink-0 me-3">
                            <span class="badge bg-<?= $index < 3 ? ['warning', 'secondary', 'danger'][$index] : 'light text-dark' ?> rounded-circle"
                                  style="width: 30px; height: 30px; line-height: 22px;">
                                <?= $index + 1 ?>
                            </span>
                        </div>
                        <div class="flex-grow-1">
                            <strong><?= e($t['nom']) ?></strong>
                            <div class="small">
                                <?php if ($t['note_moyenne']): ?>
                                <span class="text-warning">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($t['note_moyenne']) ? '' : '-o' ?>"></i>
                                    <?php endfor; ?>
                                </span>
                                <span class="text-muted ms-1"><?= $t['note_moyenne'] ?>/5</span>
                                <?php else: ?>
                                <span class="text-muted">Pas encore noté</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge bg-light text-dark"><?= $t['nb_avis'] ?> avis</span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de réponse -->
<div class="modal fade" id="replyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= url('avis/repondre.php') ?>">
                <input type="hidden" name="id" id="replyAvisId">
                <div class="modal-header">
                    <h5 class="modal-title">Répondre à l'avis</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label class="form-label">Votre réponse</label>
                        <textarea name="reponse" id="replyText" class="form-control" rows="4"
                                  placeholder="Merci pour votre retour..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-primary">Envoyer la réponse</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de confirmation suppression -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= url('avis/supprimer.php') ?>" id="deleteForm">
                <input type="hidden" name="id" id="deleteAvisId">
                <div class="modal-header border-0">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-danger me-2"></i>Confirmer la suppression</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-trash-alt fa-3x text-danger mb-3"></i>
                    <p class="mb-0">Êtes-vous sûr de vouloir supprimer cet avis ?</p>
                    <p class="text-muted small">Cette action est irréversible.</p>
                </div>
                <div class="modal-footer border-0 justify-content-center">
                    <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Annuler</button>
                    <button type="submit" class="btn btn-danger px-4"><i class="fas fa-trash me-2"></i>Supprimer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function showReplyModal(id, existingReply) {
    document.getElementById('replyAvisId').value = id;
    document.getElementById('replyText').value = existingReply || '';
    new bootstrap.Modal(document.getElementById('replyModal')).show();
}

function confirmDelete(id) {
    document.getElementById('deleteAvisId').value = id;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
