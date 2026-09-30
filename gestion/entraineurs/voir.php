<?php
/**
 * Profil d'un entraîneur
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Entraîneur non spécifié.');
    redirect(url('entraineurs/index.php'));
}

$entraineur = Entraineur::getById($id);
if (!$entraineur) {
    Session::flash('danger', 'Entraîneur introuvable.');
    redirect(url('entraineurs/index.php'));
}

$pageTitle = $entraineur['prenom'] . ' ' . $entraineur['nom'];
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Entraîneurs', 'url' => url('entraineurs/index.php')],
    ['label' => $pageTitle]
];

// Statistiques
$stats = Entraineur::getStats($id, 'mois');

// Joueurs assignés
$joueurs = Entraineur::getJoueurs($id);

// Planning de la semaine
$planningHebdo = Entraineur::getPlanningHebdo($id);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <?php if ($entraineur['photo']): ?>
            <img src="<?= uploads('entraineurs/' . $entraineur['photo']) ?>"
                 class="rounded-circle me-3" style="width: 70px; height: 70px; object-fit: cover;">
        <?php else: ?>
            <div class="user-avatar me-3" style="width: 70px; height: 70px; font-size: 28px;">
                <?= getInitials($entraineur['nom'], $entraineur['prenom']) ?>
            </div>
        <?php endif; ?>
        <div>
            <h4 class="mb-1"><?= e($entraineur['prenom'] . ' ' . $entraineur['nom']) ?></h4>
            <span class="badge <?= statusBadgeClass($entraineur['statut']) ?>"><?= translateStatus($entraineur['statut']) ?></span>
            <?php if ($entraineur['specialite']): ?>
                <span class="badge bg-secondary"><?= e($entraineur['specialite']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('entraineurs/planning.php?id=' . $id) ?>" class="btn btn-primary">
            <i class="fas fa-calendar-week me-2"></i>Planning
        </a>
        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('entraineurs/modifier.php?id=' . $id) ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne gauche -->
    <div class="col-xl-4">
        <!-- Informations -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informations</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tr>
                        <td class="text-muted" width="40%"><i class="fas fa-phone me-2"></i>Téléphone</td>
                        <td>
                            <a href="tel:<?= e($entraineur['telephone']) ?>">
                                <?= formatPhone($entraineur['telephone']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php if ($entraineur['email']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-envelope me-2"></i>Email</td>
                        <td>
                            <a href="mailto:<?= e($entraineur['email']) ?>">
                                <?= e($entraineur['email']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($entraineur['categories']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-users me-2"></i>Catégories</td>
                        <td><?= e($entraineur['categories']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($entraineur['diplomes']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-certificate me-2"></i>Diplômes</td>
                        <td><?= e($entraineur['diplomes']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-calendar me-2"></i>Depuis</td>
                        <td><?= formatDate($entraineur['date_embauche'], 'd/m/Y') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Statistiques -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Statistiques du mois</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-6">
                        <div class="bg-primary-light rounded p-3">
                            <div class="h3 mb-0 text-primary"><?= $stats['nb_seances'] ?? 0 ?></div>
                            <small class="text-muted">Séances</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="bg-success text-white rounded p-3">
                            <div class="h3 mb-0"><?= count($joueurs) ?></div>
                            <small>Joueurs</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="bg-light rounded p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Taux de présence moyen</span>
                                <strong><?= number_format($stats['taux_presence_moyen'] ?? 0, 1) ?>%</strong>
                            </div>
                            <div class="progress mt-2" style="height: 8px;">
                                <div class="progress-bar bg-success" style="width: <?= $stats['taux_presence_moyen'] ?? 0 ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Colonne droite -->
    <div class="col-xl-8">
        <!-- Planning de la semaine -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-calendar-week me-2"></i>Planning de la semaine</h6>
                <a href="<?= url('entraineurs/planning.php?id=' . $id) ?>" class="btn btn-sm btn-outline-primary">
                    Voir tout
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($planningHebdo)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-calendar-times fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">Aucune séance planifiée cette semaine</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Horaire</th>
                                    <th>Terrain</th>
                                    <th>Catégorie</th>
                                    <th>Présence</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($planningHebdo as $s): ?>
                                <tr>
                                    <td>
                                        <?= formatDate($s['date_seance'], 'D d/m') ?>
                                    </td>
                                    <td>
                                        <?= formatTime($s['heure_debut']) ?> - <?= formatTime($s['heure_fin']) ?>
                                    </td>
                                    <td><?= e($s['terrain_nom']) ?></td>
                                    <td><span class="badge bg-primary"><?= e($s['categorie']) ?></span></td>
                                    <td>
                                        <?php if ($s['nb_inscrits'] > 0): ?>
                                            <?= $s['nb_presents'] ?>/<?= $s['nb_inscrits'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Joueurs assignés -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Joueurs assignés (<?= count($joueurs) ?>)</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($joueurs)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucun joueur assigné</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Joueur</th>
                                    <th>Catégorie</th>
                                    <th>Position</th>
                                    <th>Contact parent</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($joueurs as $j): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="user-avatar me-2" style="width: 35px; height: 35px; font-size: 12px;">
                                                <?= getInitials($j['nom'], $j['prenom']) ?>
                                            </div>
                                            <div>
                                                <a href="<?= url('academie/voir.php?id=' . $j['id']) ?>" class="text-dark text-decoration-none fw-semibold">
                                                    <?= e($j['prenom'] . ' ' . $j['nom']) ?>
                                                </a>
                                                <br>
                                                <small class="text-muted"><?= e($j['numero_licence']) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-primary"><?= $j['categorie'] ?></span></td>
                                    <td><?= $j['position_preferee'] ? ucfirst($j['position_preferee']) : '-' ?></td>
                                    <td>
                                        <a href="tel:<?= e($j['telephone_parent']) ?>">
                                            <?= formatPhone($j['telephone_parent']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <a href="<?= url('academie/voir.php?id=' . $j['id']) ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
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
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
