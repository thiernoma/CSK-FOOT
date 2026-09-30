<?php
/**
 * Rapports et statistiques
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Client.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/MembreAcademie.php';
require_once APP_PATH . 'models/Cotisation.php';

Auth::requireLogin();

$pageTitle = 'Rapports & Statistiques';
$breadcrumb = [
    ['label' => 'Rapports']
];

// Période sélectionnée
$periode = sanitize(get('periode', 'mois'));
$mois = (int)get('mois') ?: date('n');
$annee = (int)get('annee') ?: date('Y');
$dateDebutInput = sanitize(get('date_debut', ''));
$dateFinInput   = sanitize(get('date_fin', ''));

// Dates selon la période
switch ($periode) {
    case 'jour':
        $dateDebut = date('Y-m-d');
        $dateFin = date('Y-m-d');
        $periodeLabel = 'Aujourd\'hui';
        break;
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin = date('Y-m-d', strtotime('sunday this week'));
        $periodeLabel = 'Cette semaine';
        break;
    case 'mois':
        $dateDebut = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
        $dateFin = date('Y-m-t', strtotime($dateDebut));
        $periodeLabel = getMonthName($mois) . " $annee";
        break;
    case 'annee':
        $dateDebut = "$annee-01-01";
        $dateFin = "$annee-12-31";
        $periodeLabel = "Année $annee";
        break;
    case 'intervalle':
        // Validation : si dates manquantes ou invalides, fallback sur le mois courant
        $dateDebut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
        $dateFin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
        // Inverser si la fin est avant le début
        if ($dateFin < $dateDebut) {
            [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
        }
        $periodeLabel = 'Du ' . formatDate($dateDebut) . ' au ' . formatDate($dateFin);
        break;
    default:
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-t');
        $periodeLabel = 'Ce mois';
}

// Helper pour le nom du mois - utilise la fonction de helpers.php

// Statistiques réservations (volume, heures, panier moyen — spécifiques au métier réservation)
$statsReservations = Database::fetchOne(
    "SELECT
        COUNT(*) as total,
        AVG(montant) as panier_moyen,
        SUM(duree_heures) as heures_total
     FROM reservations
     WHERE date_reservation BETWEEN :debut AND :fin
       AND statut_reservation != 'annulee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// CA total (toutes catégories d'encaissement) = paiements − remboursements + cotisations.
// On exclut la catégorie 'cotisation_academie' du sum paiements pour éviter le double-comptage
// (les cotisations sont déjà sommées séparément depuis leur table).
$statsEncaissements = Database::fetchOne(
    "SELECT
        COALESCE(SUM(CASE WHEN p.type_paiement = 'paiement'      THEN p.montant END), 0) as encaisse,
        COALESCE(SUM(CASE WHEN p.type_paiement = 'remboursement' THEN p.montant END), 0) as rembourse
     FROM paiements p
     LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
     WHERE DATE(p.created_at) BETWEEN :debut AND :fin
       AND (c.code IS NULL OR c.code != 'cotisation_academie')",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

$totalCotisations = (float)(Database::fetchOne(
    "SELECT COALESCE(SUM(montant), 0) as total
     FROM cotisations
     WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
)['total'] ?? 0);

$caTotal = (float)$statsEncaissements['encaisse'] - (float)$statsEncaissements['rembourse'] + $totalCotisations;
$statsReservations['ca_total'] = $caTotal; // pour rétro-compat avec le rendu existant

// CA par mode de paiement (encaissements uniquement, hors remboursements)
$caParMode = Database::fetchAll(
    "SELECT mode_paiement, SUM(montant) as total
     FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
       AND type_paiement = 'paiement'
     GROUP BY mode_paiement
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// CA par catégorie d'encaissement (toutes catégories sauf cotisation_academie qui est ajoutée
// séparément depuis la table cotisations pour éviter le double-comptage)
$caParCategorie = Database::fetchAll(
    "SELECT
        COALESCE(c.libelle, 'Sans catégorie') as libelle,
        COALESCE(c.icone, 'fa-coins') as icone,
        COALESCE(c.couleur, '#6c757d') as couleur,
        SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                 WHEN p.type_paiement = 'remboursement' THEN -p.montant
                 ELSE 0 END) as total,
        COUNT(*) as nb
     FROM paiements p
     LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
     WHERE DATE(p.created_at) BETWEEN :debut AND :fin
       AND (c.code IS NULL OR c.code != 'cotisation_academie')
     GROUP BY p.categorie_id
     HAVING total <> 0
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Ajouter les cotisations académie comme ligne virtuelle (elles ne passent pas par paiements)
if ($totalCotisations > 0) {
    $caParCategorie[] = [
        'libelle' => 'Cotisations académie',
        'icone' => 'fa-graduation-cap',
        'couleur' => '#E8631A',
        'total' => $totalCotisations,
        'nb' => (int)(Database::fetchOne(
            "SELECT COUNT(*) as nb FROM cotisations
             WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
            ['debut' => $dateDebut, 'fin' => $dateFin]
        )['nb'] ?? 0)
    ];
    // Re-trier
    usort($caParCategorie, fn($a, $b) => $b['total'] <=> $a['total']);
}

// Top terrains — CA = argent réellement encaissé sur la période (base caisse),
// cohérent avec la KPI Chiffre d'affaires. On agrège d'abord les paiements par
// réservation pour éviter le double-comptage des heures.
$topTerrains = Database::fetchAll(
    "SELECT t.nom,
            COUNT(rp.reservation_id) as nb_reservations,
            COALESCE(SUM(rp.encaisse), 0) as ca,
            COALESCE(SUM(rp.duree_heures), 0) as heures
     FROM terrains t
     LEFT JOIN (
        SELECT r.id as reservation_id, r.terrain_id, r.duree_heures,
               SUM(p.montant) as encaisse
        FROM reservations r
        JOIN paiements p ON p.reservation_id = r.id
           AND p.type_paiement = 'paiement'
           AND DATE(p.created_at) BETWEEN :debut AND :fin
        GROUP BY r.id
     ) rp ON rp.terrain_id = t.id
     GROUP BY t.id
     ORDER BY ca DESC
     LIMIT 5",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Top clients — CA = argent réellement encaissé sur la période (base caisse)
$topClients = Database::fetchAll(
    "SELECT CONCAT(c.prenom, ' ', c.nom) as nom, c.telephone,
            COUNT(rp.reservation_id) as nb_reservations,
            COALESCE(SUM(rp.encaisse), 0) as ca
     FROM clients c
     JOIN (
        SELECT r.id as reservation_id, r.client_id,
               SUM(p.montant) as encaisse
        FROM reservations r
        JOIN paiements p ON p.reservation_id = r.id
           AND p.type_paiement = 'paiement'
           AND DATE(p.created_at) BETWEEN :debut AND :fin
        GROUP BY r.id
     ) rp ON rp.client_id = c.id
     GROUP BY c.id
     ORDER BY ca DESC
     LIMIT 10",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Répartition par jour de la semaine
$parJour = Database::fetchAll(
    "SELECT DAYOFWEEK(date_reservation) as jour,
            COUNT(*) as nb,
            SUM(montant) as ca
     FROM reservations
     WHERE date_reservation BETWEEN :debut AND :fin
     GROUP BY DAYOFWEEK(date_reservation)
     ORDER BY jour",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

$joursSemaine = ['', 'Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];

// Évolution quotidienne du CA (toutes catégories : paiements + cotisations, net des remboursements)
$evolutionCA = Database::fetchAll(
    "SELECT date, SUM(ca) as ca, SUM(nb) as nb
     FROM (
        SELECT DATE(p.created_at) as date,
               SUM(CASE WHEN p.type_paiement = 'paiement' THEN p.montant
                        WHEN p.type_paiement = 'remboursement' THEN -p.montant
                        ELSE 0 END) as ca,
               COUNT(*) as nb
        FROM paiements p
        LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
        WHERE DATE(p.created_at) BETWEEN :debut1 AND :fin1
          AND (c.code IS NULL OR c.code != 'cotisation_academie')
        GROUP BY DATE(p.created_at)
        UNION ALL
        SELECT date_paiement as date, SUM(montant) as ca, COUNT(*) as nb
        FROM cotisations
        WHERE statut = 'paye' AND date_paiement BETWEEN :debut2 AND :fin2
        GROUP BY date_paiement
     ) t
     GROUP BY date
     ORDER BY date",
    ['debut1' => $dateDebut, 'fin1' => $dateFin, 'debut2' => $dateDebut, 'fin2' => $dateFin]
);

// Stats académie
// "total_actifs" reste un instantané "état actuel" (n'a pas de sens d'être projeté dans le passé)
$statsAcademie = MembreAcademie::getGlobalStats();

// Stats cotisations alignées sur la plage de dates
// - Sur la période : encaissements & nombre payé via date_paiement
// - État actuel    : en_attente, montant attendu, montant en retard (instantané)
$statsCotisationsPeriode = Database::fetchOne(
    "SELECT
        COUNT(*) as payees,
        COALESCE(SUM(montant), 0) as montant_encaisse
     FROM cotisations
     WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);
$statsCotisationsGlobal = Database::fetchOne(
    "SELECT
        COUNT(*) as en_attente,
        COALESCE(SUM(montant), 0) as montant_attendu,
        COALESCE(SUM(CASE WHEN annee < YEAR(CURDATE())
                            OR (annee = YEAR(CURDATE()) AND mois < MONTH(CURDATE()))
                          THEN montant END), 0) as montant_retard
     FROM cotisations
     WHERE statut = 'en_attente'"
);
$statsCotisations = array_merge($statsCotisationsPeriode, $statsCotisationsGlobal, [
    // Taux de recouvrement sur la période = payées (période) / (payées + en_attente actuelles)
    'total_cotisations' => (int)$statsCotisationsPeriode['payees'] + (int)$statsCotisationsGlobal['en_attente'],
]);

// Stats dépenses sur la période (toutes catégories, statut payée uniquement)
$statsDepenses = Database::fetchOne(
    "SELECT
        COUNT(*) as nb,
        COALESCE(SUM(montant), 0) as total
     FROM depenses
     WHERE statut = 'payee' AND date_depense BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Dépenses par catégorie (toutes catégories)
$depensesParCategorie = Database::fetchAll(
    "SELECT c.nom as libelle, c.icone, c.couleur,
            COUNT(d.id) as nb, COALESCE(SUM(d.montant), 0) as total
     FROM depenses d
     JOIN categories_depenses c ON d.categorie_id = c.id
     WHERE d.statut = 'payee' AND d.date_depense BETWEEN :debut AND :fin
     GROUP BY c.id, c.nom, c.icone, c.couleur
     HAVING total > 0
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Évolution quotidienne des dépenses (pour ajouter au graphique CA)
$evolutionDepenses = Database::fetchAll(
    "SELECT date_depense as date, SUM(montant) as total
     FROM depenses
     WHERE statut = 'payee' AND date_depense BETWEEN :debut AND :fin
     GROUP BY date_depense
     ORDER BY date_depense",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Bilan net (chiffre d'affaires − dépenses)
$beneficeNet = (float)$caTotal - (float)$statsDepenses['total'];
$marge = $caTotal > 0 ? round((($beneficeNet) / $caTotal) * 100, 1) : 0;

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Rapports & Statistiques</h4>
        <p class="text-muted mb-0"><?= $periodeLabel ?></p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()">
            <i class="fas fa-print me-2"></i>Imprimer
        </button>
        <a href="<?= url('rapports/export.php?' . http_build_query([
                'periode' => $periode,
                'mois' => $mois,
                'annee' => $annee,
                'date_debut' => $dateDebut,
                'date_fin' => $dateFin
            ])) ?>" class="btn btn-success">
            <i class="fas fa-file-excel me-2"></i>Exporter
        </a>
    </div>
</div>

<!-- Filtres période -->
<div class="card mb-4">
    <div class="card-body py-2">
        <form method="GET" class="row g-2 align-items-center">
            <div class="col-auto">
                <select class="form-select form-select-sm" name="periode" onchange="this.form.submit()">
                    <option value="jour" <?= $periode === 'jour' ? 'selected' : '' ?>>Aujourd'hui</option>
                    <option value="semaine" <?= $periode === 'semaine' ? 'selected' : '' ?>>Cette semaine</option>
                    <option value="mois" <?= $periode === 'mois' ? 'selected' : '' ?>>Mois</option>
                    <option value="annee" <?= $periode === 'annee' ? 'selected' : '' ?>>Année</option>
                    <option value="intervalle" <?= $periode === 'intervalle' ? 'selected' : '' ?>>Intervalle personnalisé</option>
                </select>
            </div>
            <?php if ($periode === 'intervalle'): ?>
            <div class="col-auto">
                <div class="input-group input-group-sm">
                    <span class="input-group-text">Du</span>
                    <input type="date" class="form-control form-control-sm" name="date_debut"
                           value="<?= e($dateDebut) ?>" required>
                    <span class="input-group-text">au</span>
                    <input type="date" class="form-control form-control-sm" name="date_fin"
                           value="<?= e($dateFin) ?>" required>
                </div>
            </div>
            <?php else: ?>
                <?php if ($periode === 'mois'): ?>
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="mois">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $mois == $m ? 'selected' : '' ?>><?= getMonthName($m) ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-auto">
                    <select class="form-select form-select-sm" name="annee">
                        <?php for ($y = date('Y'); $y >= 2020; $y--): ?>
                            <option value="<?= $y ?>" <?= $annee == $y ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            <?php endif; ?>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="fas fa-sync"></i>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KPIs principaux -->
<div class="row g-3 mb-4">
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-success text-white">
                <i class="fas fa-money-bill-wave"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($statsReservations['ca_total'] ?? 0) ?></div>
                <div class="kpi-label">Chiffre d'affaires</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-primary text-white">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $statsReservations['total'] ?? 0 ?></div>
                <div class="kpi-label">Réservations</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-info text-white">
                <i class="fas fa-clock"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= number_format($statsReservations['heures_total'] ?? 0, 1) ?>h</div>
                <div class="kpi-label">Heures de jeu</div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-warning text-white">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($statsReservations['panier_moyen'] ?? 0) ?></div>
                <div class="kpi-label">Panier moyen</div>
            </div>
        </div>
    </div>
</div>

<!-- KPIs financiers : Dépenses & Bénéfice net -->
<div class="row g-3 mb-4">
    <div class="col-xl-4 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-danger text-white">
                <i class="fas fa-file-invoice-dollar"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= formatMoney($statsDepenses['total'] ?? 0) ?></div>
                <div class="kpi-label">Dépenses (<?= $statsDepenses['nb'] ?? 0 ?>)</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-<?= $beneficeNet >= 0 ? 'success' : 'danger' ?> text-white">
                <i class="fas fa-balance-scale"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value text-<?= $beneficeNet >= 0 ? 'success' : 'danger' ?>">
                    <?= formatMoney($beneficeNet) ?>
                </div>
                <div class="kpi-label">Bénéfice net (CA − Dépenses)</div>
            </div>
        </div>
    </div>
    <div class="col-xl-4 col-md-6">
        <div class="kpi-card">
            <div class="kpi-icon bg-secondary text-white">
                <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-content">
                <div class="kpi-value"><?= $marge ?>%</div>
                <div class="kpi-label">Marge nette</div>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Graphique évolution CA -->
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-line me-2"></i>Évolution CA vs Dépenses</h6>
            </div>
            <div class="card-body">
                <canvas id="evolutionChart" height="100"></canvas>
            </div>
        </div>
    </div>

    <!-- Répartition par mode de paiement -->
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>Modes de paiement</h6>
            </div>
            <div class="card-body">
                <canvas id="paiementChart" height="150"></canvas>
                <div class="mt-3">
                    <?php foreach ($caParMode as $mode): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span><?= ucfirst($mode['mode_paiement']) ?></span>
                        <strong><?= formatMoney($mode['total']) ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Répartition par catégorie d'encaissement -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Répartition du chiffre d'affaires par catégorie</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($caParCategorie)): ?>
                    <div class="text-center py-4 text-muted"><p class="mb-0">Aucun encaissement sur la période</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th class="text-center">Nb opérations</th>
                                <th class="text-end">Montant net</th>
                                <th class="text-end" style="width: 30%;">Part</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalCat = array_sum(array_column($caParCategorie, 'total'));
                            foreach ($caParCategorie as $c):
                                $pct = $totalCat > 0 ? round(($c['total'] / $totalCat) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge me-2" style="background-color: <?= e($c['couleur']) ?>;">
                                        <i class="fas <?= e($c['icone']) ?> me-1"></i>
                                    </span>
                                    <?= e($c['libelle']) ?>
                                </td>
                                <td class="text-center"><?= $c['nb'] ?></td>
                                <td class="text-end fw-semibold <?= $c['total'] < 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= formatMoney($c['total']) ?>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar" role="progressbar"
                                                 style="width: <?= $pct ?>%; background-color: <?= e($c['couleur']) ?>;"></div>
                                        </div>
                                        <small class="text-muted" style="min-width: 45px; text-align: right;"><?= $pct ?>%</small>
                                    </div>
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

    <!-- Répartition des dépenses par catégorie -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Répartition des dépenses par catégorie</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($depensesParCategorie)): ?>
                    <div class="text-center py-4 text-muted"><p class="mb-0">Aucune dépense sur la période</p></div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Catégorie</th>
                                <th class="text-center">Nb dépenses</th>
                                <th class="text-end">Montant</th>
                                <th class="text-end" style="width: 30%;">Part</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $totalDep = array_sum(array_column($depensesParCategorie, 'total'));
                            foreach ($depensesParCategorie as $d):
                                $pct = $totalDep > 0 ? round(($d['total'] / $totalDep) * 100, 1) : 0;
                            ?>
                            <tr>
                                <td>
                                    <span class="badge me-2" style="background-color: <?= e($d['couleur'] ?? '#dc3545') ?>;">
                                        <i class="fas <?= e($d['icone'] ?? 'fa-money-bill') ?>"></i>
                                    </span>
                                    <?= e($d['libelle']) ?>
                                </td>
                                <td class="text-center"><?= $d['nb'] ?></td>
                                <td class="text-end fw-semibold text-danger">- <?= formatMoney($d['total']) ?></td>
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="progress flex-grow-1" style="height: 8px;">
                                            <div class="progress-bar bg-danger" role="progressbar"
                                                 style="width: <?= $pct ?>%; background-color: <?= e($d['couleur'] ?? '#dc3545') ?>;"></div>
                                        </div>
                                        <small class="text-muted" style="min-width: 45px; text-align: right;"><?= $pct ?>%</small>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <td colspan="2" class="text-end fw-bold">TOTAL</td>
                                <td class="text-end fw-bold text-danger">- <?= formatMoney($totalDep) ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Top terrains -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-trophy me-2"></i>Performance des terrains</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Terrain</th>
                                <th class="text-center">Réservations</th>
                                <th class="text-center">Heures</th>
                                <th class="text-end">CA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topTerrains as $t): ?>
                            <tr>
                                <td class="fw-semibold"><?= e($t['nom']) ?></td>
                                <td class="text-center"><?= $t['nb_reservations'] ?></td>
                                <td class="text-center"><?= number_format($t['heures'] ?? 0, 1) ?>h</td>
                                <td class="text-end fw-semibold text-success"><?= formatMoney($t['ca'] ?? 0) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Répartition par jour -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Répartition par jour</h6>
            </div>
            <div class="card-body">
                <canvas id="jourChart" height="150"></canvas>
            </div>
        </div>
    </div>

    <!-- Top clients -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-users me-2"></i>Top 10 clients</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($topClients)): ?>
                    <div class="text-center py-4 text-muted">
                        <p class="mb-0">Aucune donnée pour cette période</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Client</th>
                                    <th class="text-center">Réservations</th>
                                    <th class="text-end">CA</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topClients as $i => $c): ?>
                                <tr>
                                    <td>
                                        <?php if ($i < 3): ?>
                                            <span class="badge bg-<?= $i === 0 ? 'warning' : ($i === 1 ? 'secondary' : 'danger') ?>">
                                                <?= $i + 1 ?>
                                            </span>
                                        <?php else: ?>
                                            <?= $i + 1 ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?= e($c['nom']) ?></strong><br>
                                        <small class="text-muted"><?= formatPhone($c['telephone']) ?></small>
                                    </td>
                                    <td class="text-center"><?= $c['nb_reservations'] ?></td>
                                    <td class="text-end fw-semibold text-success"><?= formatMoney($c['ca']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Stats Académie -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Académie</h6>
            </div>
            <div class="card-body">
                <div class="row g-3 text-center">
                    <div class="col-4">
                        <div class="h3 text-primary mb-0"><?= $statsAcademie['total_actifs'] ?? 0 ?></div>
                        <small class="text-muted">Membres actifs <span class="text-warning" title="État actuel — ne dépend pas du filtre">*</span></small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-success mb-0"><?= formatMoney($statsCotisations['montant_encaisse'] ?? 0) ?></div>
                        <small class="text-muted">Cotisations encaissées <small class="text-info">(<?= $statsCotisations['payees'] ?? 0 ?>)</small></small>
                    </div>
                    <div class="col-4">
                        <div class="h3 text-danger mb-0"><?= formatMoney($statsCotisations['montant_retard'] ?? 0) ?></div>
                        <small class="text-muted">En retard <span class="text-warning" title="État actuel — cotisations encore dues">*</span></small>
                    </div>
                </div>

                <hr>

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span>Taux de recouvrement (période)</span>
                    <?php
                    $tauxRecouv = ($statsCotisations['total_cotisations'] ?? 0) > 0
                        ? round(($statsCotisations['payees'] / $statsCotisations['total_cotisations']) * 100, 1)
                        : 0;
                    ?>
                    <strong><?= $tauxRecouv ?>%</strong>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-success" style="width: <?= $tauxRecouv ?>%"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<?php
// Préparer les données pour les graphiques.
// On unifie les jours CA + dépenses dans une seule timeline pour superposer les deux courbes.
$timelineByDate = [];
foreach ($evolutionCA as $e) {
    $timelineByDate[$e['date']] = ['ca' => (float)$e['ca'], 'dep' => 0.0];
}
foreach ($evolutionDepenses as $d) {
    if (!isset($timelineByDate[$d['date']])) $timelineByDate[$d['date']] = ['ca' => 0.0, 'dep' => 0.0];
    $timelineByDate[$d['date']]['dep'] = (float)$d['total'];
}
ksort($timelineByDate);

$evolutionLabels = [];
$evolutionData = [];
$evolutionDepData = [];
foreach ($timelineByDate as $date => $vals) {
    $evolutionLabels[] = formatDate($date, 'd/m');
    $evolutionData[] = $vals['ca'];
    $evolutionDepData[] = $vals['dep'];
}

$paiementLabels = array_map(fn($p) => ucfirst($p['mode_paiement']), $caParMode);
$paiementData = array_map(fn($p) => $p['total'], $caParMode);

$jourData = array_fill(0, 7, 0);
foreach ($parJour as $j) {
    $jourData[$j['jour'] - 1] = $j['ca'];
}

$inlineJs = "
// Graphique évolution CA
new Chart(document.getElementById('evolutionChart'), {
    type: 'line',
    data: {
        labels: " . json_encode($evolutionLabels) . ",
        datasets: [
            {
                label: 'CA',
                data: " . json_encode($evolutionData) . ",
                borderColor: '#1A3A6B',
                backgroundColor: 'rgba(26, 58, 107, 0.1)',
                fill: true,
                tension: 0.3
            },
            {
                label: 'Dépenses',
                data: " . json_encode($evolutionDepData) . ",
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: true, position: 'top' }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value) + ' F';
                    }
                }
            }
        }
    }
});

// Graphique modes de paiement
new Chart(document.getElementById('paiementChart'), {
    type: 'doughnut',
    data: {
        labels: " . json_encode($paiementLabels) . ",
        datasets: [{
            data: " . json_encode($paiementData) . ",
            backgroundColor: ['#1A3A6B', '#E8631A', '#28A745', '#17a2b8', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom' }
        }
    }
});

// Graphique par jour
new Chart(document.getElementById('jourChart'), {
    type: 'bar',
    data: {
        labels: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
        datasets: [{
            label: 'CA',
            data: " . json_encode($jourData) . ",
            backgroundColor: '#E8631A'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return new Intl.NumberFormat('fr-FR').format(value);
                    }
                }
            }
        }
    }
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
