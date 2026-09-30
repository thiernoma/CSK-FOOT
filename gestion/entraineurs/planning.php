<?php
/**
 * Planning d'un entraîneur
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

// Navigation entre semaines
$semaineOffset = (int)get('semaine', 0);
$dateDebut = date('Y-m-d', strtotime("monday this week {$semaineOffset} weeks"));
$dateFin = date('Y-m-d', strtotime($dateDebut . ' +6 days'));

$pageTitle = 'Planning - ' . $entraineur['prenom'] . ' ' . $entraineur['nom'];
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Entraîneurs', 'url' => url('entraineurs/index.php')],
    ['label' => $entraineur['prenom'] . ' ' . $entraineur['nom'], 'url' => url('entraineurs/voir.php?id=' . $id)],
    ['label' => 'Planning']
];

// Récupérer les séances de la semaine
$seances = Entraineur::getSeances($id, $dateDebut, $dateFin);

// Organiser par jour
$jours = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
$planningParJour = [];
for ($i = 0; $i < 7; $i++) {
    $date = date('Y-m-d', strtotime($dateDebut . " +{$i} days"));
    $planningParJour[$date] = [
        'jour' => $jours[$i],
        'date' => $date,
        'seances' => []
    ];
}

foreach ($seances as $seance) {
    if (isset($planningParJour[$seance['date_seance']])) {
        $planningParJour[$seance['date_seance']]['seances'][] = $seance;
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <?php if ($entraineur['photo']): ?>
            <img src="<?= uploads('entraineurs/' . $entraineur['photo']) ?>"
                 class="rounded-circle me-3" style="width: 50px; height: 50px; object-fit: cover;">
        <?php else: ?>
            <div class="user-avatar me-3" style="width: 50px; height: 50px; font-size: 20px;">
                <?= getInitials($entraineur['nom'], $entraineur['prenom']) ?>
            </div>
        <?php endif; ?>
        <div>
            <h4 class="mb-0">Planning de <?= e($entraineur['prenom'] . ' ' . $entraineur['nom']) ?></h4>
            <small class="text-muted">
                Semaine du <?= formatDate($dateDebut, 'd/m/Y') ?> au <?= formatDate($dateFin, 'd/m/Y') ?>
            </small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('entraineurs/planning.php?id=' . $id . '&semaine=' . ($semaineOffset - 1)) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-chevron-left"></i>
        </a>
        <?php if ($semaineOffset != 0): ?>
        <a href="<?= url('entraineurs/planning.php?id=' . $id) ?>" class="btn btn-outline-primary">
            Aujourd'hui
        </a>
        <?php endif; ?>
        <a href="<?= url('entraineurs/planning.php?id=' . $id . '&semaine=' . ($semaineOffset + 1)) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>
</div>

<div class="row g-3">
    <?php foreach ($planningParJour as $date => $jour): ?>
        <?php
        $isToday = $date === date('Y-m-d');
        $isPast = $date < date('Y-m-d');
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card h-100 <?= $isToday ? 'border-primary' : '' ?>">
                <div class="card-header <?= $isToday ? 'bg-primary text-white' : ($isPast ? 'bg-light' : '') ?>">
                    <div class="d-flex justify-content-between align-items-center">
                        <strong><?= $jour['jour'] ?></strong>
                        <span><?= formatDate($date, 'd/m') ?></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($jour['seances'])): ?>
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-calendar-times mb-2"></i>
                            <p class="mb-0 small">Pas de séance</p>
                        </div>
                    <?php else: ?>
                        <ul class="list-group list-group-flush">
                            <?php foreach ($jour['seances'] as $seance): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <div class="fw-semibold">
                                                <?= formatTime($seance['heure_debut']) ?> - <?= formatTime($seance['heure_fin']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <i class="fas fa-futbol me-1"></i><?= e($seance['terrain_nom'] ?? 'Non défini') ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <span class="badge bg-primary"><?= e($seance['categorie']) ?></span>
                                            <?php if ($seance['nb_inscrits'] > 0): ?>
                                                <div class="small text-muted mt-1">
                                                    <i class="fas fa-users"></i> <?= $seance['nb_presents'] ?>/<?= $seance['nb_inscrits'] ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($seance['description']): ?>
                                        <div class="small mt-2 text-muted">
                                            <?= e($seance['description']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php
                                    $statutClass = match($seance['statut']) {
                                        'terminee' => 'bg-success',
                                        'en_cours' => 'bg-warning',
                                        'annulee' => 'bg-danger',
                                        default => 'bg-secondary'
                                    };
                                    $statutLabel = match($seance['statut']) {
                                        'terminee' => 'Terminée',
                                        'en_cours' => 'En cours',
                                        'annulee' => 'Annulée',
                                        default => 'Planifiée'
                                    };
                                    ?>
                                    <div class="mt-2">
                                        <span class="badge <?= $statutClass ?>"><?= $statutLabel ?></span>
                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Statistiques de la semaine -->
<div class="card mt-4">
    <div class="card-header">
        <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>Récapitulatif de la semaine</h6>
    </div>
    <div class="card-body">
        <?php
        $totalSeances = count($seances);
        $seancesTerminees = count(array_filter($seances, fn($s) => $s['statut'] === 'terminee'));
        $seancesAVenir = count(array_filter($seances, fn($s) => $s['statut'] === 'planifiee'));
        $seancesAnnulees = count(array_filter($seances, fn($s) => $s['statut'] === 'annulee'));
        ?>
        <div class="row text-center">
            <div class="col">
                <div class="h4 mb-0 text-primary"><?= $totalSeances ?></div>
                <small class="text-muted">Total séances</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-success"><?= $seancesTerminees ?></div>
                <small class="text-muted">Terminées</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-warning"><?= $seancesAVenir ?></div>
                <small class="text-muted">À venir</small>
            </div>
            <div class="col">
                <div class="h4 mb-0 text-danger"><?= $seancesAnnulees ?></div>
                <small class="text-muted">Annulées</small>
            </div>
        </div>
    </div>
</div>

<div class="mt-4">
    <a href="<?= url('entraineurs/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Retour au profil
    </a>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
