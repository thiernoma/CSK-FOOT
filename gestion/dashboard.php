<?php
/**
 * Tableau de bord principal
 * Complexe Sportif Kaira - Académie Khaïra Foot
 */

require_once __DIR__ . '/../includes/init.php';

// Vérifier l'authentification
Auth::requireLogin();

$pageTitle = 'Tableau de bord';
$breadcrumb = [['label' => 'Tableau de bord']];

// Récupérer les statistiques du jour
try {
    // Réservations du jour
    $reservationsJour = Database::fetchOne("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN statut_reservation = 'confirmee' THEN 1 ELSE 0 END) as confirmees,
            SUM(CASE WHEN statut_reservation = 'en_cours' THEN 1 ELSE 0 END) as en_cours,
            SUM(CASE WHEN statut_reservation = 'terminee' THEN 1 ELSE 0 END) as terminees
        FROM reservations
        WHERE date_reservation = CURDATE()
    ");

    // CA du jour (toutes catégories : paiements − remboursements + cotisations).
    // Exclusion de la catégorie 'cotisation_academie' du sum paiements pour éviter
    // un double-comptage (les cotisations sont sommées séparément depuis leur table).
    $caJour = Database::fetchOne("
        SELECT (
            COALESCE((SELECT SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                                      WHEN p.type_paiement = 'remboursement' THEN -p.montant END)
                      FROM paiements p
                      LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
                      WHERE DATE(p.created_at) = CURDATE()
                        AND (c.code IS NULL OR c.code != 'cotisation_academie')), 0)
            +
            COALESCE((SELECT SUM(montant) FROM cotisations
                      WHERE statut = 'paye' AND date_paiement = CURDATE()), 0)
        ) as total
    ");

    // CA du mois (toutes catégories, mêmes règles)
    $caMois = Database::fetchOne("
        SELECT (
            COALESCE((SELECT SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                                      WHEN p.type_paiement = 'remboursement' THEN -p.montant END)
                      FROM paiements p
                      LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
                      WHERE MONTH(p.created_at) = MONTH(CURDATE())
                        AND YEAR(p.created_at) = YEAR(CURDATE())
                        AND (c.code IS NULL OR c.code != 'cotisation_academie')), 0)
            +
            COALESCE((SELECT SUM(montant) FROM cotisations
                      WHERE statut = 'paye'
                        AND MONTH(date_paiement) = MONTH(CURDATE())
                        AND YEAR(date_paiement) = YEAR(CURDATE())), 0)
        ) as total
    ");

    // Terrains disponibles
    $terrainsStats = Database::fetchOne("
        SELECT
            COUNT(*) as total,
            SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs
        FROM terrains
    ");

    // Membres académie actifs
    $membresActifs = Database::fetchOne("
        SELECT COUNT(*) as total
        FROM membres_academie
        WHERE statut = 'actif'
    ");

    // Prochaines réservations du jour
    $prochainesReservations = Database::fetchAll("
        SELECT
            r.id,
            r.numero_ticket,
            r.heure_debut,
            r.heure_fin,
            r.montant,
            r.statut_paiement,
            r.statut_reservation,
            t.nom as terrain_nom,
            CONCAT(c.prenom, ' ', c.nom) as client_nom,
            c.telephone as client_telephone
        FROM reservations r
        JOIN terrains t ON r.terrain_id = t.id
        JOIN clients c ON r.client_id = c.id
        WHERE r.date_reservation = CURDATE()
        AND r.statut_reservation IN ('confirmee', 'en_cours')
        AND r.heure_debut >= CURTIME()
        ORDER BY r.heure_debut
        LIMIT 5
    ");

    // Derniers paiements
    $derniersPaiements = Database::fetchAll("
        SELECT
            p.id,
            p.montant,
            p.mode_paiement,
            p.created_at,
            r.numero_ticket,
            CONCAT(c.prenom, ' ', c.nom) as client_nom
        FROM paiements p
        JOIN reservations r ON p.reservation_id = r.id
        JOIN clients c ON r.client_id = c.id
        WHERE p.type_paiement = 'paiement'
        ORDER BY p.created_at DESC
        LIMIT 5
    ");

    // Données pour le graphique CA (7 derniers jours, toutes catégories : paiements + cotisations)
    // Exclusion cotisation_academie de paiements pour éviter le double-comptage
    $caGraphique = Database::fetchAll("
        SELECT jour, SUM(total) as total
        FROM (
            SELECT DATE(p.created_at) as jour,
                   SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                            WHEN p.type_paiement = 'remboursement' THEN -p.montant END) as total
            FROM paiements p
            LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
            WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
              AND (c.code IS NULL OR c.code != 'cotisation_academie')
            GROUP BY DATE(p.created_at)
            UNION ALL
            SELECT date_paiement as jour, SUM(montant) as total
            FROM cotisations
            WHERE statut = 'paye' AND date_paiement >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
            GROUP BY date_paiement
        ) t
        GROUP BY jour
        ORDER BY jour
    ");

    // Cotisations en retard
    $cotisationsRetard = Database::fetchOne("
        SELECT COUNT(*) as total
        FROM cotisations c
        JOIN membres_academie m ON c.membre_id = m.id
        WHERE c.statut IN ('en_attente', 'partiel')
        AND m.statut = 'actif'
    ");

} catch (Exception $e) {
    // En cas d'erreur, utiliser des valeurs par défaut
    $reservationsJour = ['total' => 0, 'confirmees' => 0, 'en_cours' => 0, 'terminees' => 0];
    $caJour = ['total' => 0];
    $caMois = ['total' => 0];
    $terrainsStats = ['total' => 0, 'actifs' => 0];
    $membresActifs = ['total' => 0];
    $prochainesReservations = [];
    $derniersPaiements = [];
    $caGraphique = [];
    $cotisationsRetard = ['total' => 0];

    if (DEV_MODE) {
        Session::flash('danger', 'Erreur base de données: ' . $e->getMessage());
    }
}

// Préparer les données du graphique
$graphLabels = [];
$graphData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $graphLabels[] = date('d/m', strtotime($date));
    $found = false;
    foreach ($caGraphique as $row) {
        if ($row['jour'] === $date) {
            $graphData[] = (int)$row['total'];
            $found = true;
            break;
        }
    }
    if (!$found) {
        $graphData[] = 0;
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<!-- En-tête de page -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1"><?= getGreeting() ?>, <?= e(Session::get('user_prenom', '')) ?> !</h4>
        <p class="text-muted mb-0">Voici un aperçu de l'activité du <?= formatDateFr(date('Y-m-d')) ?></p>
    </div>
    <div>
        <a href="<?= url('reservations/nouveau.php') ?>" class="btn btn-accent">
            <i class="fas fa-plus me-2"></i>Nouvelle réservation
        </a>
    </div>
</div>

<!-- KPI Cards -->
<div class="row g-4 mb-4">
    <!-- Réservations du jour -->
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card primary">
            <div class="kpi-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="kpi-value"><?= $reservationsJour['total'] ?? 0 ?></div>
            <div class="kpi-label">Réservations du jour</div>
            <div class="d-flex gap-3 small">
                <span class="text-success"><i class="fas fa-check-circle me-1"></i><?= $reservationsJour['confirmees'] ?? 0 ?> confirmées</span>
                <span class="text-info"><i class="fas fa-clock me-1"></i><?= $reservationsJour['en_cours'] ?? 0 ?> en cours</span>
            </div>
        </div>
    </div>

    <!-- CA du jour -->
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card accent">
            <div class="kpi-icon">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="kpi-value"><?= formatMoney($caJour['total'] ?? 0) ?></div>
            <div class="kpi-label">Chiffre d'affaires du jour</div>
            <div class="kpi-trend up">
                <i class="fas fa-chart-line"></i>
                <span>Ce mois: <?= formatMoney($caMois['total'] ?? 0) ?></span>
            </div>
        </div>
    </div>

    <!-- Terrains -->
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card success">
            <div class="kpi-icon">
                <i class="fas fa-futbol"></i>
            </div>
            <div class="kpi-value"><?= $terrainsStats['actifs'] ?? 0 ?> / <?= $terrainsStats['total'] ?? 0 ?></div>
            <div class="kpi-label">Terrains disponibles</div>
            <a href="<?= url('terrains/index.php') ?>" class="small text-success">
                <i class="fas fa-arrow-right me-1"></i>Gérer les terrains
            </a>
        </div>
    </div>

    <!-- Membres Académie -->
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card info">
            <div class="kpi-icon">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="kpi-value"><?= $membresActifs['total'] ?? 0 ?></div>
            <div class="kpi-label">Membres Académie actifs</div>
            <?php if (($cotisationsRetard['total'] ?? 0) > 0): ?>
                <div class="kpi-trend down">
                    <i class="fas fa-exclamation-triangle"></i>
                    <span><?= $cotisationsRetard['total'] ?> cotisations en retard</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Graphique CA -->
    <div class="col-xl-8">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-chart-line me-2 text-primary"></i>Évolution du CA (7 derniers jours)</h5>
            </div>
            <div class="card-body">
                <canvas id="caChart" height="300"></canvas>
            </div>
        </div>
    </div>

    <!-- Prochaines réservations -->
    <div class="col-xl-4">
        <div class="card h-100">
            <div class="card-header">
                <h5><i class="fas fa-clock me-2 text-accent"></i>Prochaines réservations</h5>
                <a href="<?= url('reservations/index.php') ?>" class="btn btn-sm btn-outline-primary">Voir tout</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($prochainesReservations)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                        <p>Aucune réservation à venir aujourd'hui</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($prochainesReservations as $reservation): ?>
                            <a href="<?= url('reservations/voir.php?id=' . $reservation['id']) ?>" class="list-group-item list-group-item-action">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <div class="fw-semibold"><?= e($reservation['client_nom']) ?></div>
                                        <small class="text-muted">
                                            <i class="fas fa-futbol me-1"></i><?= e($reservation['terrain_nom']) ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-semibold text-primary">
                                            <?= formatTime($reservation['heure_debut']) ?> - <?= formatTime($reservation['heure_fin']) ?>
                                        </div>
                                        <span class="badge <?= statusBadgeClass($reservation['statut_paiement']) ?>">
                                            <?= translateStatus($reservation['statut_paiement']) ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-4 mt-2">
    <!-- Derniers paiements -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-receipt me-2 text-success"></i>Derniers paiements</h5>
                <a href="<?= url('paiements/index.php') ?>" class="btn btn-sm btn-outline-primary">Historique</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($derniersPaiements)): ?>
                    <div class="text-center py-4 text-muted">
                        <p>Aucun paiement récent</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Ticket</th>
                                    <th>Client</th>
                                    <th>Mode</th>
                                    <th class="text-end">Montant</th>
                                    <th>Heure</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($derniersPaiements as $paiement): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-light text-dark"><?= e($paiement['numero_ticket']) ?></span>
                                        </td>
                                        <td><?= e($paiement['client_nom']) ?></td>
                                        <td>
                                            <?php
                                            $modeIcon = match($paiement['mode_paiement']) {
                                                'especes' => 'fa-money-bill',
                                                'wave' => 'fa-mobile-alt',
                                                'om' => 'fa-mobile-alt',
                                                'carte' => 'fa-credit-card',
                                                default => 'fa-money-check'
                                            };
                                            ?>
                                            <i class="fas <?= $modeIcon ?> me-1"></i>
                                            <?= ucfirst($paiement['mode_paiement']) ?>
                                        </td>
                                        <td class="text-end fw-semibold text-success">
                                            <?= formatMoney($paiement['montant']) ?>
                                        </td>
                                        <td class="text-muted">
                                            <?= timeAgo($paiement['created_at']) ?>
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

    <!-- Raccourcis rapides -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-bolt me-2 text-warning"></i>Actions rapides</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <a href="<?= url('reservations/nouveau.php') ?>" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-plus-circle fa-2x mb-2 d-block"></i>
                            Nouvelle réservation
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= url('clients/nouveau.php') ?>" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-user-plus fa-2x mb-2 d-block"></i>
                            Nouveau client
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= url('reservations/planning.php') ?>" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-calendar-week fa-2x mb-2 d-block"></i>
                            Voir le planning
                        </a>
                    </div>
                    <div class="col-6">
                        <a href="<?= url(Auth::isAdmin() ? 'paiements/caisse.php' : 'paiements/ma-caisse.php') ?>" class="btn btn-outline-primary w-100 py-3">
                            <i class="fas fa-cash-register fa-2x mb-2 d-block"></i>
                            <?= Auth::isAdmin() ? 'Caisse du jour' : 'Ma caisse' ?>
                        </a>
                    </div>
                </div>

                <!-- Raccourcis clavier -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6 class="mb-2"><i class="fas fa-keyboard me-2"></i>Raccourcis clavier</h6>
                    <div class="d-flex flex-wrap gap-3 small">
                        <span><kbd>N</kbd> Nouvelle réservation</span>
                        <span><kbd>/</kbd> Rechercher</span>
                        <span><kbd>Esc</kbd> Fermer</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
// Graphique CA avec Chart.js
const ctx = document.getElementById('caChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: " . json_encode($graphLabels) . ",
        datasets: [{
            label: 'CA (FCFA)',
            data: " . json_encode($graphData) . ",
            borderColor: '#1A3A6B',
            backgroundColor: 'rgba(26, 58, 107, 0.1)',
            borderWidth: 3,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#E8631A',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 7
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                backgroundColor: '#1A3A6B',
                padding: 12,
                callbacks: {
                    label: function(context) {
                        return formatMoney(context.raw);
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return value.toLocaleString('fr-FR') + ' F';
                    }
                },
                grid: {
                    color: 'rgba(0,0,0,0.05)'
                }
            },
            x: {
                grid: {
                    display: false
                }
            }
        }
    }
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
