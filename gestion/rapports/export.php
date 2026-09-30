<?php
/**
 * Export Excel des rapports et statistiques
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Client.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/MembreAcademie.php';
require_once APP_PATH . 'models/Cotisation.php';

Auth::requireLogin();

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
        $filenamePeriode = date('Y-m-d');
        break;
    case 'semaine':
        $dateDebut = date('Y-m-d', strtotime('monday this week'));
        $dateFin = date('Y-m-d', strtotime('sunday this week'));
        $periodeLabel = 'Semaine du ' . date('d/m/Y', strtotime($dateDebut));
        $filenamePeriode = 'semaine_' . date('Y-m-d', strtotime($dateDebut));
        break;
    case 'mois':
        $dateDebut = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
        $dateFin = date('Y-m-t', strtotime($dateDebut));
        $periodeLabel = getMonthName($mois) . " $annee";
        $filenamePeriode = $annee . '_' . str_pad($mois, 2, '0', STR_PAD_LEFT);
        break;
    case 'annee':
        $dateDebut = "$annee-01-01";
        $dateFin = "$annee-12-31";
        $periodeLabel = "Année $annee";
        $filenamePeriode = $annee;
        break;
    case 'intervalle':
        $dateDebut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
        $dateFin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
        if ($dateFin < $dateDebut) {
            [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
        }
        $periodeLabel = 'Du ' . formatDate($dateDebut) . ' au ' . formatDate($dateFin);
        $filenamePeriode = str_replace('-', '', $dateDebut) . '_' . str_replace('-', '', $dateFin);
        break;
    default:
        $dateDebut = date('Y-m-01');
        $dateFin = date('Y-m-t');
        $periodeLabel = 'Ce mois';
        $filenamePeriode = date('Y_m');
}

// ==================== STATISTIQUES ====================

// Statistiques réservations (volume / heures / panier moyen — métier réservation uniquement)
$statsReservations = Database::fetchOne(
    "SELECT
        COUNT(*) as total,
        SUM(CASE WHEN statut_paiement = 'paye' THEN 1 ELSE 0 END) as payees,
        AVG(montant) as panier_moyen,
        SUM(duree_heures) as heures_total
     FROM reservations
     WHERE date_reservation BETWEEN :debut AND :fin
       AND statut_reservation != 'annulee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// CA total (toutes catégories) — exclusion de cotisation_academie pour éviter le double-comptage
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
$statsReservations['ca_total'] = $caTotal;
$statsReservations['encaisse'] = $caTotal;

// CA par mode de paiement (encaissements uniquement)
$caParMode = Database::fetchAll(
    "SELECT mode_paiement, COUNT(*) as nb_paiements, SUM(montant) as total
     FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
       AND type_paiement = 'paiement'
     GROUP BY mode_paiement
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// CA par catégorie d'encaissement (toutes catégories sauf cotisation_academie qui est ajoutée
// séparément ci-dessous depuis la table cotisations)
$caParCategorie = Database::fetchAll(
    "SELECT
        COALESCE(c.libelle, 'Sans catégorie') as libelle,
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
if ($totalCotisations > 0) {
    $caParCategorie[] = [
        'libelle' => 'Cotisations académie',
        'total' => $totalCotisations,
        'nb' => (int)(Database::fetchOne(
            "SELECT COUNT(*) as nb FROM cotisations
             WHERE statut = 'paye' AND date_paiement BETWEEN :debut AND :fin",
            ['debut' => $dateDebut, 'fin' => $dateFin]
        )['nb'] ?? 0)
    ];
    usort($caParCategorie, fn($a, $b) => $b['total'] <=> $a['total']);
}

// Top terrains — CA = argent réellement encaissé sur la période (base caisse)
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
     ORDER BY ca DESC",
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
     LIMIT 20",
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

// Stats dépenses sur la période
$statsDepenses = Database::fetchOne(
    "SELECT COUNT(*) as nb, COALESCE(SUM(montant), 0) as total
     FROM depenses WHERE statut = 'payee' AND date_depense BETWEEN :debut AND :fin",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);
$depensesParCategorie = Database::fetchAll(
    "SELECT c.nom as libelle, COUNT(d.id) as nb, COALESCE(SUM(d.montant), 0) as total
     FROM depenses d
     JOIN categories_depenses c ON d.categorie_id = c.id
     WHERE d.statut = 'payee' AND d.date_depense BETWEEN :debut AND :fin
     GROUP BY c.id, c.nom
     HAVING total > 0
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);
$beneficeNet = (float)$caTotal - (float)$statsDepenses['total'];
$marge = $caTotal > 0 ? round(($beneficeNet / $caTotal) * 100, 1) : 0;

// Stats académie
$statsAcademie = MembreAcademie::getGlobalStats();

// Stats cotisations alignées sur la plage de dates (mêmes règles que rapports/index.php)
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
    'total_cotisations' => (int)$statsCotisationsPeriode['payees'] + (int)$statsCotisationsGlobal['en_attente'],
]);

// Liste des réservations
$reservations = Database::fetchAll(
    "SELECT r.*, t.nom as terrain_nom, c.nom as client_nom, c.prenom as client_prenom, c.telephone
     FROM reservations r
     JOIN terrains t ON r.terrain_id = t.id
     JOIN clients c ON r.client_id = c.id
     WHERE r.date_reservation BETWEEN :debut AND :fin
     ORDER BY r.date_reservation DESC, r.heure_debut DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// ==================== GÉNÉRATION CSV ====================

$filename = "rapport_statistiques_{$filenamePeriode}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Titre
fputcsv($output, ['RAPPORT & STATISTIQUES - ' . strtoupper($periodeLabel)], ';');
fputcsv($output, ['Complexe Sportif Kaira'], ';');
fputcsv($output, ['Date de génération: ' . date('d/m/Y H:i')], ';');
fputcsv($output, ['Période: du ' . date('d/m/Y', strtotime($dateDebut)) . ' au ' . date('d/m/Y', strtotime($dateFin))], ';');
fputcsv($output, [], ';');

// ==================== RÉSUMÉ GÉNÉRAL ====================
fputcsv($output, ['=== RÉSUMÉ GÉNÉRAL ==='], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Chiffre d\'affaires total', number_format($statsReservations['ca_total'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Montant encaissé', number_format($statsReservations['encaisse'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Nombre de réservations', $statsReservations['total'] ?? 0], ';');
fputcsv($output, ['Réservations payées', $statsReservations['payees'] ?? 0], ';');
fputcsv($output, ['Heures de jeu totales', number_format($statsReservations['heures_total'] ?? 0, 1, ',', ' ') . ' h'], ';');
fputcsv($output, ['Panier moyen', number_format($statsReservations['panier_moyen'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Dépenses (nb)', $statsDepenses['nb'] ?? 0], ';');
fputcsv($output, ['Dépenses (total)', number_format($statsDepenses['total'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Bénéfice net', number_format($beneficeNet, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Marge nette', $marge . ' %'], ';');
fputcsv($output, [], ';');

// ==================== RECETTES PAR MODE DE PAIEMENT ====================
fputcsv($output, ['=== RECETTES PAR MODE DE PAIEMENT ==='], ';');
fputcsv($output, ['Mode de paiement', 'Nb Paiements', 'Montant Total'], ';');
$totalPaiements = 0;
foreach ($caParMode as $mode) {
    fputcsv($output, [
        ucfirst($mode['mode_paiement']),
        $mode['nb_paiements'],
        number_format($mode['total'], 0, ',', ' ')
    ], ';');
    $totalPaiements += $mode['total'];
}
fputcsv($output, ['TOTAL', '', number_format($totalPaiements, 0, ',', ' ')], ';');
fputcsv($output, [], ';');

// ==================== RECETTES PAR CATÉGORIE D'ENCAISSEMENT ====================
fputcsv($output, ['=== RECETTES PAR CATÉGORIE D\'ENCAISSEMENT ==='], ';');
fputcsv($output, ['Catégorie', 'Nb Opérations', 'Montant Net'], ';');
$totalCat = 0;
foreach ($caParCategorie as $c) {
    fputcsv($output, [
        $c['libelle'],
        $c['nb'],
        number_format($c['total'], 0, ',', ' ')
    ], ';');
    $totalCat += $c['total'];
}
fputcsv($output, ['TOTAL', '', number_format($totalCat, 0, ',', ' ')], ';');
fputcsv($output, [], ';');

// ==================== DÉPENSES PAR CATÉGORIE ====================
fputcsv($output, ['=== DÉPENSES PAR CATÉGORIE ==='], ';');
fputcsv($output, ['Catégorie', 'Nb Dépenses', 'Montant'], ';');
$totalDep = 0;
foreach ($depensesParCategorie as $d) {
    fputcsv($output, [
        $d['libelle'],
        $d['nb'],
        number_format($d['total'], 0, ',', ' ')
    ], ';');
    $totalDep += $d['total'];
}
fputcsv($output, ['TOTAL DÉPENSES', '', number_format($totalDep, 0, ',', ' ')], ';');
fputcsv($output, [], ';');

// ==================== PERFORMANCE DES TERRAINS ====================
fputcsv($output, ['=== PERFORMANCE DES TERRAINS ==='], ';');
fputcsv($output, ['Terrain', 'Nb Réservations', 'Heures', 'Chiffre d\'Affaires'], ';');
foreach ($topTerrains as $t) {
    fputcsv($output, [
        $t['nom'],
        $t['nb_reservations'] ?? 0,
        number_format($t['heures'] ?? 0, 1, ',', ' ') . ' h',
        number_format($t['ca'] ?? 0, 0, ',', ' ')
    ], ';');
}
fputcsv($output, [], ';');

// ==================== RÉPARTITION PAR JOUR ====================
fputcsv($output, ['=== RÉPARTITION PAR JOUR DE LA SEMAINE ==='], ';');
fputcsv($output, ['Jour', 'Nb Réservations', 'Chiffre d\'Affaires'], ';');
$jourData = [];
foreach ($parJour as $j) {
    $jourData[$j['jour']] = $j;
}
for ($d = 1; $d <= 7; $d++) {
    $data = $jourData[$d] ?? ['nb' => 0, 'ca' => 0];
    fputcsv($output, [
        $joursSemaine[$d],
        $data['nb'] ?? 0,
        number_format($data['ca'] ?? 0, 0, ',', ' ')
    ], ';');
}
fputcsv($output, [], ';');

// ==================== TOP CLIENTS ====================
fputcsv($output, ['=== TOP 20 CLIENTS ==='], ';');
fputcsv($output, ['Rang', 'Client', 'Téléphone', 'Nb Réservations', 'Chiffre d\'Affaires'], ';');
$rang = 1;
foreach ($topClients as $c) {
    fputcsv($output, [
        $rang++,
        $c['nom'],
        $c['telephone'],
        $c['nb_reservations'],
        number_format($c['ca'], 0, ',', ' ')
    ], ';');
}
fputcsv($output, [], ';');

// ==================== ACADÉMIE ====================
fputcsv($output, ['=== STATISTIQUES ACADÉMIE ==='], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Membres actifs', $statsAcademie['total_actifs'] ?? 0], ';');
fputcsv($output, ['Total membres', $statsAcademie['total_membres'] ?? 0], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Cotisations totales', $statsCotisations['total_cotisations'] ?? 0], ';');
fputcsv($output, ['Cotisations payées', $statsCotisations['payees'] ?? 0], ';');
fputcsv($output, ['Cotisations en attente', $statsCotisations['en_attente'] ?? 0], ';');
fputcsv($output, ['Montant encaissé', number_format($statsCotisations['montant_encaisse'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Montant en attente', number_format($statsCotisations['montant_attendu'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Montant en retard', number_format($statsCotisations['montant_retard'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');

$tauxRecouv = ($statsCotisations['total_cotisations'] ?? 0) > 0
    ? round(($statsCotisations['payees'] / $statsCotisations['total_cotisations']) * 100, 1)
    : 0;
fputcsv($output, ['Taux de recouvrement', $tauxRecouv . '%'], ';');
fputcsv($output, [], ';');

// ==================== DÉTAIL DES RÉSERVATIONS ====================
fputcsv($output, ['=== DÉTAIL DES RÉSERVATIONS ==='], ';');
fputcsv($output, ['Date', 'N° Ticket', 'Terrain', 'Client', 'Téléphone', 'Horaire', 'Durée', 'Montant', 'Payé', 'Statut'], ';');
foreach ($reservations as $r) {
    fputcsv($output, [
        date('d/m/Y', strtotime($r['date_reservation'])),
        $r['numero_ticket'],
        $r['terrain_nom'],
        $r['client_prenom'] . ' ' . $r['client_nom'],
        $r['telephone'],
        formatTimeFull($r['heure_debut']) . ' - ' . formatTimeFull($r['heure_fin']),
        $r['duree_heures'] . 'h',
        number_format($r['montant'], 0, ',', ' '),
        number_format($r['montant_paye'], 0, ',', ' '),
        $r['statut_paiement'] === 'paye' ? 'Payé' : ($r['statut_paiement'] === 'partiel' ? 'Partiel' : 'Non payé')
    ], ';');
}

fclose($output);
exit;
