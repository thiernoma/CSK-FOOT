<?php
/**
 * Profil d'un membre de l'académie
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MembreAcademie.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Membre non spécifié.');
    redirect(url('academie/index.php'));
}

$membre = MembreAcademie::getById($id);
if (!$membre) {
    Session::flash('danger', 'Membre introuvable.');
    redirect(url('academie/index.php'));
}

$pageTitle = $membre['prenom'] . ' ' . $membre['nom'];
$breadcrumb = [
    ['label' => 'Académie', 'url' => url('academie/index.php')],
    ['label' => $pageTitle]
];

// Statistiques
$stats = MembreAcademie::getStats($id);

// Cotisations
$cotisations = MembreAcademie::getCotisations($id, 12);

// Présences
$presences = MembreAcademie::getPresences($id, 10);

// Calcul du taux de présence
$tauxPresence = $stats['total_seances'] > 0
    ? round(($stats['presences'] / $stats['total_seances']) * 100, 1)
    : 0;

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div class="d-flex align-items-center">
        <?php if ($membre['photo']): ?>
            <img src="<?= uploads('membres/' . $membre['photo']) ?>"
                 class="rounded-circle me-3" style="width: 70px; height: 70px; object-fit: cover;"
                 alt="<?= e($membre['prenom']) ?>">
        <?php else: ?>
            <div class="user-avatar me-3" style="width: 70px; height: 70px; font-size: 28px;">
                <?= getInitials($membre['nom'], $membre['prenom']) ?>
            </div>
        <?php endif; ?>
        <div>
            <h4 class="mb-1"><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></h4>
            <span class="badge bg-primary me-2"><?= $membre['categorie'] ?></span>
            <span class="badge <?= statusBadgeClass($membre['statut']) ?>"><?= translateStatus($membre['statut']) ?></span>
            <br>
            <small class="text-muted">
                <i class="fas fa-id-card me-1"></i><?= e($membre['numero_licence']) ?>
            </small>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= url('academie/cotisations.php?membre=' . $id) ?>" class="btn btn-success">
            <i class="fas fa-money-bill me-2"></i>Cotisations
        </a>
        <a href="<?= url('academie/modifier.php?id=' . $id) ?>" class="btn btn-outline-primary">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
        <a href="<?= url('academie/carte.php?id=' . $id) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-id-card me-2"></i>Carte
        </a>
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
                        <td class="text-muted" width="40%"><i class="fas fa-birthday-cake me-2"></i>Âge</td>
                        <td>
                            <strong><?= MembreAcademie::calculateAge($membre['date_naissance']) ?> ans</strong>
                            <small class="text-muted">(<?= formatDate($membre['date_naissance'], 'd/m/Y') ?>)</small>
                        </td>
                    </tr>
                    <?php if ($membre['position_preferee']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-futbol me-2"></i>Position</td>
                        <td><?= ucfirst($membre['position_preferee']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-shoe-prints me-2"></i>Pied fort</td>
                        <td><?= ucfirst($membre['pied_fort']) ?></td>
                    </tr>
                    <?php if ($membre['entraineur_nom']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-whistle me-2"></i>Entraîneur</td>
                        <td><?= e($membre['entraineur_prenom'] . ' ' . $membre['entraineur_nom']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-calendar me-2"></i>Inscrit le</td>
                        <td><?= formatDate($membre['date_inscription'], 'd/m/Y') ?></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Contact Parent -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>Contact Parent</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <?php if ($membre['nom_parent']): ?>
                    <tr>
                        <td class="text-muted" width="40%">Nom</td>
                        <td><?= e($membre['nom_parent']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-phone me-2"></i>Téléphone</td>
                        <td>
                            <a href="tel:<?= e($membre['telephone_parent']) ?>">
                                <?= formatPhone($membre['telephone_parent']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php if ($membre['telephone_parent_alt']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-phone me-2"></i>Tél. secondaire</td>
                        <td><?= formatPhone($membre['telephone_parent_alt']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membre['email_parent']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-envelope me-2"></i>Email</td>
                        <td>
                            <a href="mailto:<?= e($membre['email_parent']) ?>">
                                <?= e($membre['email_parent']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membre['adresse']): ?>
                    <tr>
                        <td class="text-muted"><i class="fas fa-map-marker me-2"></i>Adresse</td>
                        <td><?= nl2br(e($membre['adresse'])) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Scolarité -->
        <?php if ($membre['ecole'] || $membre['niveau_scolaire']): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Scolarité</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <?php if ($membre['ecole']): ?>
                    <tr>
                        <td class="text-muted">École</td>
                        <td><?= e($membre['ecole']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membre['niveau_scolaire']): ?>
                    <tr>
                        <td class="text-muted">Niveau</td>
                        <td><?= e($membre['niveau_scolaire']) ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Urgence & Médical -->
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h6 class="mb-0"><i class="fas fa-first-aid me-2"></i>Urgence</h6>
            </div>
            <div class="card-body">
                <?php if ($membre['personne_urgence'] || $membre['telephone_urgence']): ?>
                <table class="table table-borderless mb-0">
                    <?php if ($membre['personne_urgence']): ?>
                    <tr>
                        <td class="text-muted">Contact</td>
                        <td><?= e($membre['personne_urgence']) ?></td>
                    </tr>
                    <?php endif; ?>
                    <?php if ($membre['telephone_urgence']): ?>
                    <tr>
                        <td class="text-muted">Téléphone</td>
                        <td>
                            <a href="tel:<?= e($membre['telephone_urgence']) ?>" class="text-danger fw-bold">
                                <?= formatPhone($membre['telephone_urgence']) ?>
                            </a>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
                <?php endif; ?>

                <?php if ($membre['notes_medicales']): ?>
                <div class="mt-3 p-2 bg-light rounded">
                    <small class="text-muted">Notes médicales:</small>
                    <p class="mb-0 small"><?= nl2br(e($membre['notes_medicales'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Colonne droite -->
    <div class="col-xl-8">
        <!-- Statistiques -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="h3 text-primary mb-0"><?= $stats['total_seances'] ?? 0 ?></div>
                        <small class="text-muted">Séances</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="h3 text-success mb-0"><?= $tauxPresence ?>%</div>
                        <small class="text-muted">Présence</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <div class="h5 text-info mb-0"><?= formatMoney($stats['total_paye'] ?? 0) ?></div>
                        <small class="text-muted">Total payé</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <?php if (($stats['cotisations_retard'] ?? 0) > 0): ?>
                            <div class="h5 text-danger mb-0"><?= formatMoney($stats['total_du'] ?? 0) ?></div>
                            <small class="text-danger"><?= $stats['cotisations_retard'] ?> en retard</small>
                        <?php else: ?>
                            <div class="h5 text-success mb-0"><i class="fas fa-check"></i></div>
                            <small class="text-success">À jour</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cotisations -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-money-bill me-2"></i>Cotisations récentes</h6>
                <a href="<?= url('academie/cotisations.php?membre=' . $id) ?>" class="btn btn-sm btn-outline-primary">
                    Voir tout
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($cotisations)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune cotisation enregistrée</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Période</th>
                                    <th>Montant</th>
                                    <th>Échéance</th>
                                    <th>Statut</th>
                                    <th>Paiement</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $moisNoms = ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'];
                                foreach ($cotisations as $c):
                                    $periode = $moisNoms[$c['mois']] . ' ' . $c['annee'];
                                    // Date d'échéance = dernier jour du mois
                                    $dateEcheance = date('Y-m-t', strtotime($c['annee'] . '-' . str_pad($c['mois'], 2, '0', STR_PAD_LEFT) . '-01'));
                                    $isLate = $c['statut'] === 'en_attente' && $dateEcheance < date('Y-m-d');
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= e($periode) ?></strong>
                                    </td>
                                    <td><?= formatMoney($c['montant']) ?></td>
                                    <td><?= formatDate($dateEcheance, 'd/m/Y') ?></td>
                                    <td>
                                        <span class="badge <?= $c['statut'] === 'paye' ? 'bg-success' : ($isLate ? 'bg-danger' : 'bg-warning') ?>">
                                            <?= $c['statut'] === 'paye' ? 'Payé' : ($isLate ? 'En retard' : 'En attente') ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($c['date_paiement']): ?>
                                            <?= formatDate($c['date_paiement'], 'd/m/Y') ?>
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

        <!-- Présences -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0"><i class="fas fa-clipboard-check me-2"></i>Dernières présences</h6>
                <div class="progress" style="width: 100px; height: 8px;">
                    <div class="progress-bar bg-success" style="width: <?= $tauxPresence ?>%"></div>
                </div>
            </div>
            <div class="card-body p-0">
                <?php if (empty($presences)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune présence enregistrée</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Horaire</th>
                                    <th>Terrain</th>
                                    <th>Entraîneur</th>
                                    <th>Présent</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presences as $p): ?>
                                <tr>
                                    <td><?= formatDate($p['date_seance'], 'd/m/Y') ?></td>
                                    <td><?= formatTime($p['heure_debut']) ?> - <?= formatTime($p['heure_fin']) ?></td>
                                    <td><?= e($p['terrain_nom']) ?></td>
                                    <td><?= e($p['entraineur_nom']) ?></td>
                                    <td>
                                        <?php if ($p['present']): ?>
                                            <span class="badge bg-success"><i class="fas fa-check"></i></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger"><i class="fas fa-times"></i></span>
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
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
