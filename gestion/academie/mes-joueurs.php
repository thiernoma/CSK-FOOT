<?php
/**
 * Mes joueurs - Vue entraîneur
 * Affiche les joueurs assignés et ceux en retard de cotisation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/MembreAcademie.php';
require_once APP_PATH . 'models/Entraineur.php';

Auth::requireLogin();

$pageTitle = 'Mes Joueurs';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Mes Joueurs']
];

// Récupérer l'entraîneur associé à l'utilisateur connecté
$entraineur = Entraineur::getByUserId(Auth::id());

// Si l'utilisateur n'est pas un entraîneur, vérifier s'il a accès via son rôle
if (!$entraineur && !Auth::isAdmin()) {
    Session::flash('warning', 'Vous n\'êtes pas associé à un profil entraîneur.');
    redirect(url('dashboard'));
}

// Si admin, permettre de voir tous les entraîneurs
$entraineurId = $entraineur ? $entraineur['id'] : (int)get('entraineur');
if (!$entraineurId && Auth::isAdmin()) {
    // Rediriger vers la liste standard
    redirect(url('academie/index'));
}

// Récupérer les données de l'entraîneur si admin a sélectionné un ID
if (!$entraineur && $entraineurId) {
    $entraineur = Entraineur::getById($entraineurId);
}

if (!$entraineur) {
    Session::flash('danger', 'Entraîneur non trouvé.');
    redirect(url('dashboard'));
}

// Récupérer tous les joueurs de l'entraîneur
$joueurs = Entraineur::getJoueurs($entraineur['id']);

// Récupérer les joueurs en retard de cotisation
$joueursEnRetard = Entraineur::getJoueursEnRetard($entraineur['id']);

// Statistiques
$totalJoueurs = count($joueurs);
$totalEnRetard = count($joueursEnRetard);
$montantTotalDu = array_sum(array_column($joueursEnRetard, 'montant_du'));

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Mes Joueurs</h4>
        <p class="text-muted mb-0">
            Entraîneur: <?= e($entraineur['prenom'] . ' ' . $entraineur['nom']) ?>
        </p>
    </div>
</div>

<!-- Statistiques rapides -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary-light text-primary">
                <i class="fas fa-users"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $totalJoueurs ?></div>
                <div class="kpi-label">Joueurs assignés</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger text-white">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $totalEnRetard ?></div>
                <div class="kpi-label">En retard de paiement</div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning text-white">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($montantTotalDu) ?></div>
                <div class="kpi-label">Montant total dû</div>
            </div>
        </div>
    </div>
</div>

<!-- Alerte pour les joueurs en retard -->
<?php if ($totalEnRetard > 0): ?>
<div class="alert alert-danger d-flex align-items-center mb-4">
    <i class="fas fa-exclamation-circle fa-2x me-3"></i>
    <div>
        <strong><?= $totalEnRetard ?> joueur(s) en retard de cotisation</strong>
        <p class="mb-0">Montant total impayé: <?= formatMoney($montantTotalDu) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" data-bs-toggle="tab" href="#retard" role="tab">
            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
            En retard (<?= $totalEnRetard ?>)
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" data-bs-toggle="tab" href="#tous" role="tab">
            <i class="fas fa-users me-2"></i>
            Tous les joueurs (<?= $totalJoueurs ?>)
        </a>
    </li>
</ul>

<div class="tab-content">
    <!-- Tab: Joueurs en retard -->
    <div class="tab-pane fade show active" id="retard" role="tabpanel">
        <div class="card">
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Joueurs en retard de cotisation</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($joueursEnRetard)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3 text-success"></i>
                    <p class="mb-0">Tous vos joueurs sont à jour de cotisation !</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Joueur</th>
                                <th>N° Licence</th>
                                <th>Catégorie</th>
                                <th>Contact parent</th>
                                <th class="text-center">Cotisations en retard</th>
                                <th class="text-end">Montant dû</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($joueursEnRetard as $membre): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 40px; height: 40px;">
                                            <?= getInitials($membre['nom'], $membre['prenom']) ?>
                                        </div>
                                        <div>
                                            <strong><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= MembreAcademie::calculateAge($membre['date_naissance']) ?> ans
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= e($membre['numero_licence']) ?></code></td>
                                <td><span class="badge bg-primary"><?= e($membre['categorie']) ?></span></td>
                                <td>
                                    <?php if ($membre['nom_parent']): ?>
                                        <small><?= e($membre['nom_parent']) ?></small><br>
                                    <?php endif; ?>
                                    <a href="tel:<?= e($membre['telephone_parent']) ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i><?= formatPhone($membre['telephone_parent']) ?>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-danger fs-6"><?= $membre['nb_cotisations_retard'] ?> mois</span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-danger"><?= formatMoney($membre['montant_du']) ?></strong>
                                </td>
                                <td>
                                    <a href="<?= url('academie/cotisations.php?membre=' . $membre['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Voir cotisations">
                                        <i class="fas fa-money-bill"></i>
                                    </a>
                                    <a href="<?= url('academie/voir.php?id=' . $membre['id']) ?>"
                                       class="btn btn-sm btn-outline-secondary" title="Voir profil">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="5" class="text-end"><strong>Total</strong></td>
                                <td class="text-end"><strong class="text-danger fs-5"><?= formatMoney($montantTotalDu) ?></strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Tab: Tous les joueurs -->
    <div class="tab-pane fade" id="tous" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-users me-2"></i>Tous mes joueurs</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($joueurs)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-users-slash fa-3x mb-3 opacity-50"></i>
                    <p class="mb-0">Aucun joueur assigné</p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Joueur</th>
                                <th>N° Licence</th>
                                <th>Catégorie</th>
                                <th>Contact parent</th>
                                <th>Statut cotisation</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Créer un tableau des IDs en retard pour vérification rapide
                            $idsEnRetard = array_column($joueursEnRetard, 'id');
                            foreach ($joueurs as $membre):
                                $enRetard = in_array($membre['id'], $idsEnRetard);
                            ?>
                            <tr class="<?= $enRetard ? 'table-danger' : '' ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="user-avatar me-2" style="width: 40px; height: 40px;">
                                            <?= getInitials($membre['nom'], $membre['prenom']) ?>
                                        </div>
                                        <div>
                                            <strong><?= e($membre['prenom'] . ' ' . $membre['nom']) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= MembreAcademie::calculateAge($membre['date_naissance']) ?> ans
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td><code><?= e($membre['numero_licence']) ?></code></td>
                                <td><span class="badge bg-primary"><?= e($membre['categorie']) ?></span></td>
                                <td>
                                    <?php if ($membre['nom_parent']): ?>
                                        <small><?= e($membre['nom_parent']) ?></small><br>
                                    <?php endif; ?>
                                    <a href="tel:<?= e($membre['telephone_parent']) ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i><?= formatPhone($membre['telephone_parent']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($enRetard): ?>
                                        <span class="badge bg-danger">En retard</span>
                                    <?php else: ?>
                                        <span class="badge bg-success">À jour</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?= url('academie/voir.php?id=' . $membre['id']) ?>"
                                       class="btn btn-sm btn-outline-primary" title="Voir profil">
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
