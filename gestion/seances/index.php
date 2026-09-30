<?php
/**
 * Liste des séances d'entraînement
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Seance.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();
Auth::requirePermission('academie');

$pageTitle = 'Séances d\'entraînement';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Séances']
];

// Filtres
$filters = [
    'categorie' => sanitize(get('categorie')),
    'type' => sanitize(get('type')),
    'entraineur_id' => (int)get('entraineur'),
    'date_debut' => sanitize(get('date_debut')),
    'date_fin' => sanitize(get('date_fin')),
    'statut' => sanitize(get('statut'))
];

// Pagination
$page = max(1, (int)get('page', 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$total = Seance::count($filters);
$totalPages = ceil($total / $perPage);
$seances = Seance::getAll($filters, $perPage, $offset);

// Listes pour les filtres
$categories = Seance::getCategories();
$types = Seance::getTypes();
$entraineurs = Entraineur::getAll(['statut' => 'actif']);

// Terrains pour le modal
$terrains = Database::fetchAll("SELECT id, nom FROM terrains WHERE statut = 'actif' ORDER BY nom");

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Séances d'entraînement</h4>
        <p class="text-muted mb-0"><?= number_format($total) ?> séance(s)</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('seances/planning.php') ?>" class="btn btn-outline-primary">
            <i class="fas fa-calendar-week me-2"></i>Planning
        </a>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#seanceModal">
            <i class="fas fa-plus me-2"></i>Nouvelle séance
        </button>
    </div>
</div>

<!-- Filtres -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="categorie">
                    <option value="">Toutes catégories</option>
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filters['categorie'] === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="type">
                    <option value="">Tous types</option>
                    <?php foreach ($types as $key => $label): ?>
                        <option value="<?= $key ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <select class="form-select form-select-sm" name="entraineur">
                    <option value="">Tous entraîneurs</option>
                    <?php foreach ($entraineurs as $e): ?>
                        <option value="<?= $e['id'] ?>" <?= $filters['entraineur_id'] == $e['id'] ? 'selected' : '' ?>>
                            <?= e($e['nom'] . ' ' . $e['prenom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_debut"
                       value="<?= e($filters['date_debut']) ?>" placeholder="Du">
            </div>
            <div class="col-md-2">
                <input type="date" class="form-control form-control-sm" name="date_fin"
                       value="<?= e($filters['date_fin']) ?>" placeholder="Au">
            </div>
            <div class="col-md-2">
                <div class="d-flex gap-1">
                    <button type="submit" class="btn btn-sm btn-primary flex-grow-1">
                        <i class="fas fa-filter"></i>
                    </button>
                    <a href="<?= url('seances/index.php') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Liste des séances -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($seances)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">Aucune séance trouvée</p>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Horaire</th>
                            <th>Catégorie</th>
                            <th>Type</th>
                            <th>Terrain</th>
                            <th>Entraîneur</th>
                            <th class="text-center">Présences</th>
                            <th>Statut</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($seances as $seance): ?>
                        <tr>
                            <td>
                                <strong><?= formatDate($seance['date_seance'], 'D d M') ?></strong>
                            </td>
                            <td>
                                <?= substr($seance['heure_debut'], 0, 5) ?> - <?= substr($seance['heure_fin'], 0, 5) ?>
                            </td>
                            <td>
                                <span class="badge" style="background-color: <?= Seance::getCategorieColor($seance['categorie']) ?>">
                                    <?= $seance['categorie'] ?>
                                </span>
                            </td>
                            <td><?= $types[$seance['type_seance']] ?? $seance['type_seance'] ?></td>
                            <td><?= e($seance['terrain_nom'] ?? '-') ?></td>
                            <td><?= e($seance['entraineur_nom'] ?? '-') ?></td>
                            <td class="text-center">
                                <?php if ($seance['nb_inscrits'] > 0): ?>
                                    <span class="badge bg-<?= $seance['nb_presents'] == $seance['nb_inscrits'] ? 'success' : 'warning' ?>">
                                        <?= $seance['nb_presents'] ?>/<?= $seance['nb_inscrits'] ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statutClass = match($seance['statut']) {
                                    'terminee' => 'success',
                                    'annulee' => 'danger',
                                    'en_cours' => 'warning',
                                    default => 'secondary'
                                };
                                $statutLabel = match($seance['statut']) {
                                    'planifiee' => 'Planifiée',
                                    'en_cours' => 'En cours',
                                    'terminee' => 'Terminée',
                                    'annulee' => 'Annulée',
                                    default => $seance['statut']
                                };
                                ?>
                                <span class="badge bg-<?= $statutClass ?>"><?= $statutLabel ?></span>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="<?= url('seances/presences.php?id=' . $seance['id']) ?>"
                                       class="btn btn-outline-primary" title="Gérer les présences">
                                        <i class="fas fa-user-check"></i>
                                    </a>
                                    <a href="<?= url('seances/modifier.php?id=' . $seance['id']) ?>"
                                       class="btn btn-outline-secondary" title="Modifier">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-outline-danger"
                                            onclick="confirmDelete(<?= $seance['id'] ?>)" title="Supprimer">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
            <div class="card-footer">
                <?= pagination($page, $totalPages, url('seances/index') . '?' . http_build_query(array_filter($filters))) ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nouvelle Séance -->
<div class="modal fade" id="seanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= url('seances/save.php') ?>">
                <?= csrfField() ?>

                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-calendar-plus me-2"></i>Nouvelle séance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="tab-content">
                        <!-- Séance unique -->
                        <div class="tab-pane fade show active" id="tab-unique">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Date <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="date_seance"
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Heure début <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="heure_debut"
                                           value="16:00" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Heure fin <span class="text-danger">*</span></label>
                                    <input type="time" class="form-control" name="heure_fin"
                                           value="18:00" required>
                                </div>
                            </div>
                        </div>

                        <!-- Séances récurrentes -->
                        <div class="tab-pane fade" id="tab-recurrent">
                            <input type="hidden" name="recurrent" value="0" id="recurrentFlag">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Date début <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="rec_date_debut"
                                           value="<?= date('Y-m-d') ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Date fin <span class="text-danger">*</span></label>
                                    <input type="date" class="form-control" name="rec_date_fin"
                                           value="<?= date('Y-m-d', strtotime('+3 months')) ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Heure début</label>
                                    <input type="time" class="form-control" name="rec_heure_debut" value="16:00">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Heure fin</label>
                                    <input type="time" class="form-control" name="rec_heure_fin" value="18:00">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Jours de la semaine</label>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php
                                        $jours = ['1' => 'Lun', '2' => 'Mar', '3' => 'Mer', '4' => 'Jeu', '5' => 'Ven', '6' => 'Sam', '7' => 'Dim'];
                                        foreach ($jours as $num => $nom):
                                        ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="jours[]"
                                                   value="<?= $num ?>" id="jour<?= $num ?>">
                                            <label class="form-check-label" for="jour<?= $num ?>"><?= $nom ?></label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <!-- Champs communs -->
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                            <select class="form-select" name="categorie" required>
                                <?php foreach ($categories as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Type de séance</label>
                            <select class="form-select" name="type_seance">
                                <?php foreach ($types as $key => $label): ?>
                                    <option value="<?= $key ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Terrain</label>
                            <select class="form-select" name="terrain_id">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($terrains as $t): ?>
                                    <option value="<?= $t['id'] ?>"><?= e($t['nom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Entraîneur</label>
                            <select class="form-select" name="entraineur_id">
                                <option value="">-- Sélectionner --</option>
                                <?php foreach ($entraineurs as $e): ?>
                                    <option value="<?= $e['id'] ?>"><?= e($e['nom'] . ' ' . $e['prenom']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description / Notes</label>
                            <textarea class="form-control" name="description" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between">
                    <ul class="nav nav-pills" role="tablist">
                        <li class="nav-item">
                            <button type="button" class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-unique">
                                Séance unique
                            </button>
                        </li>
                        <li class="nav-item">
                            <button type="button" class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-recurrent">
                                Séances récurrentes
                            </button>
                        </li>
                    </ul>
                    <div>
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Créer
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Form suppression -->
<form id="deleteForm" method="POST" action="<?= url('seances/delete.php') ?>" style="display:none;">
    <?= csrfField() ?>
    <input type="hidden" name="id" id="deleteId">
</form>

<?php
$inlineJs = "
// Gestion des onglets récurrents
document.querySelectorAll('[data-bs-toggle=\"tab\"]').forEach(function(tab) {
    tab.addEventListener('shown.bs.tab', function(e) {
        document.getElementById('recurrentFlag').value = e.target.getAttribute('data-bs-target') === '#tab-recurrent' ? '1' : '0';
    });
});

// Confirmation suppression
function confirmDelete(id) {
    confirmModal({
        title: 'Supprimer la séance',
        message: 'Supprimer cette séance ? Les présences associées seront également supprimées.',
        confirmText: '<i class=\"fas fa-trash me-1\"></i> Supprimer',
        confirmClass: 'btn-danger',
        icon: 'fa-trash',
        iconClass: 'text-danger'
    }, function() {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    });
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
