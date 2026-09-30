<?php
/**
 * Gestion des présences d'une séance
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Seance.php';
require_once APP_PATH . 'models/Presence.php';
require_once APP_PATH . 'models/MembreAcademie.php';

Auth::requireLogin();
Auth::requirePermission('academie');

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Séance non spécifiée.');
    redirect(url('seances/index.php'));
}

$seance = Seance::getById($id);
if (!$seance) {
    Session::flash('danger', 'Séance introuvable.');
    redirect(url('seances/index.php'));
}

$pageTitle = 'Présences - ' . formatDate($seance['date_seance'], 'd/m/Y');
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Séances', 'url' => url('seances/index.php')],
    ['label' => 'Présences']
];

// Traitement des actions
if (isPost() && verifyCsrf()) {
    $action = post('action');

    switch ($action) {
        case 'initialize':
            $count = Presence::initializeForSeance($id);
            Session::flash('success', "$count membre(s) ajouté(s) à la liste.");
            break;

        case 'save_all':
            $presences = post('presence') ?? [];
            foreach ($presences as $membreId => $statut) {
                Presence::record($id, (int)$membreId, $statut);
            }
            Session::flash('success', 'Présences enregistrées.');
            break;

        case 'mark_all':
            $statut = post('statut');
            Presence::markAll($id, $statut);
            Session::flash('success', 'Tous marqués comme ' . Presence::getStatuts()[$statut] . '.');
            break;
    }

    redirect(url('seances/presences.php?id=' . $id));
}

// Récupérer les présences
$presences = Presence::getBySeance($id);
$stats = Presence::getSeanceStats($id);
$statuts = Presence::getStatuts();

// Membres de la catégorie non encore ajoutés
$membresDisponibles = Database::fetchAll(
    "SELECT id, nom, prenom, numero_licence
     FROM membres_academie
     WHERE categorie = :categorie AND statut = 'actif'
       AND id NOT IN (SELECT membre_id FROM presences WHERE seance_id = :seance_id)
     ORDER BY nom, prenom",
    ['categorie' => $seance['categorie'], 'seance_id' => $id]
);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Gestion des présences</h4>
        <p class="text-muted mb-0">
            <span class="badge" style="background-color: <?= Seance::getCategorieColor($seance['categorie']) ?>">
                <?= $seance['categorie'] ?>
            </span>
            <?= formatDate($seance['date_seance'], 'l d F Y') ?>
            • <?= substr($seance['heure_debut'], 0, 5) ?> - <?= substr($seance['heure_fin'], 0, 5) ?>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= APP_URL ?>/api/pdf.php?type=presence&id=<?= $id ?>" class="btn btn-outline-secondary" target="_blank">
            <i class="fas fa-print me-2"></i>Imprimer
        </a>
        <a href="<?= url('seances/index.php') ?>" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-2"></i>Retour
        </a>
    </div>
</div>

<!-- Info séance -->
<div class="row mb-4">
    <div class="col-md-8">
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4">
                        <small class="text-muted">Type</small>
                        <p class="mb-0 fw-bold"><?= Seance::getTypes()[$seance['type_seance']] ?? '-' ?></p>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Terrain</small>
                        <p class="mb-0 fw-bold"><?= e($seance['terrain_nom'] ?? '-') ?></p>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted">Entraîneur</small>
                        <p class="mb-0 fw-bold"><?= e($seance['entraineur_nom'] ?? '-') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-light">
            <div class="card-body text-center">
                <div class="d-flex justify-content-around">
                    <div>
                        <div class="h3 text-success mb-0"><?= $stats['presents'] ?? 0 ?></div>
                        <small class="text-muted">Présents</small>
                    </div>
                    <div>
                        <div class="h3 text-danger mb-0"><?= $stats['absents'] ?? 0 ?></div>
                        <small class="text-muted">Absents</small>
                    </div>
                    <div>
                        <div class="h3 text-warning mb-0"><?= $stats['retards'] ?? 0 ?></div>
                        <small class="text-muted">Retards</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Actions rapides -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex justify-content-between align-items-center">
            <div class="d-flex gap-2">
                <?php if (!empty($membresDisponibles)): ?>
                <form method="POST" class="d-inline">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="initialize">
                    <button type="submit" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-users me-1"></i>Ajouter tous les membres (<?= count($membresDisponibles) ?>)
                    </button>
                </form>
                <?php endif; ?>

                <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#addMembreModal">
                    <i class="fas fa-user-plus me-1"></i>Ajouter un membre
                </button>
            </div>

            <?php if (!empty($presences)): ?>
            <form method="POST" class="d-flex gap-2">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="mark_all">
                <select class="form-select form-select-sm" name="statut" style="width:auto;">
                    <?php foreach ($statuts as $key => $label): ?>
                        <option value="<?= $key ?>"><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-sm btn-secondary">Marquer tous</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Liste des présences -->
<div class="card">
    <div class="card-body p-0">
        <?php if (empty($presences)): ?>
            <div class="text-center py-5 text-muted">
                <i class="fas fa-user-clock fa-3x mb-3 opacity-50"></i>
                <p class="mb-3">Aucun membre inscrit à cette séance</p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="initialize">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-users me-2"></i>Ajouter les membres de la catégorie <?= $seance['categorie'] ?>
                    </button>
                </form>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="save_all">

                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width:50px;"></th>
                                <th>Membre</th>
                                <th>N° Licence</th>
                                <th class="text-center">Statut</th>
                                <th>Heure arrivée</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($presences as $i => $p): ?>
                            <tr class="<?= $p['statut'] === 'absent' ? 'table-danger' : ($p['statut'] === 'present' ? 'table-success' : '') ?>">
                                <td class="text-center">
                                    <?php if (!empty($p['photo'])): ?>
                                        <img src="<?= uploads('membres/' . $p['photo']) ?>"
                                             class="rounded-circle" width="35" height="35" style="object-fit:cover;">
                                    <?php else: ?>
                                        <div class="rounded-circle bg-secondary d-flex align-items-center justify-content-center"
                                             style="width:35px;height:35px;">
                                            <span class="text-white small"><?= strtoupper(substr($p['membre_nom'], 0, 1)) ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e($p['membre_nom'] . ' ' . $p['membre_prenom']) ?></strong>
                                </td>
                                <td>
                                    <code><?= e($p['numero_licence']) ?></code>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm" role="group">
                                        <?php foreach (['present' => 'success', 'absent' => 'danger', 'retard' => 'warning', 'excuse' => 'info', 'blesse' => 'secondary'] as $s => $color): ?>
                                        <input type="radio" class="btn-check" name="presence[<?= $p['membre_id'] ?>]"
                                               value="<?= $s ?>" id="p<?= $p['membre_id'] ?>_<?= $s ?>"
                                               <?= $p['statut'] === $s ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-<?= $color ?>" for="p<?= $p['membre_id'] ?>_<?= $s ?>"
                                               title="<?= $statuts[$s] ?>">
                                            <?php
                                            $icon = match($s) {
                                                'present' => 'check',
                                                'absent' => 'times',
                                                'retard' => 'clock',
                                                'excuse' => 'envelope',
                                                'blesse' => 'medkit'
                                            };
                                            ?>
                                            <i class="fas fa-<?= $icon ?>"></i>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($p['heure_arrivee']): ?>
                                        <small><?= substr($p['heure_arrivee'], 0, 5) ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <span class="text-muted"><?= count($presences) ?> membre(s)</span>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer les présences
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Ajout membre -->
<div class="modal fade" id="addMembreModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="<?= APP_URL ?>/api/presence.php">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="seance_id" value="<?= $id ?>">

                <div class="modal-header">
                    <h5 class="modal-title">Ajouter un membre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($membresDisponibles)): ?>
                        <p class="text-muted">Tous les membres de la catégorie <?= $seance['categorie'] ?> sont déjà inscrits.</p>
                    <?php else: ?>
                        <div class="mb-3">
                            <label class="form-label">Sélectionner un membre</label>
                            <select class="form-select" name="membre_id" required>
                                <option value="">-- Choisir --</option>
                                <?php foreach ($membresDisponibles as $m): ?>
                                    <option value="<?= $m['id'] ?>">
                                        <?= e($m['nom'] . ' ' . $m['prenom']) ?> (<?= $m['numero_licence'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Statut</label>
                            <select class="form-select" name="statut">
                                <?php foreach ($statuts as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $key === 'present' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                    <?php if (!empty($membresDisponibles)): ?>
                        <button type="submit" class="btn btn-primary">Ajouter</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
