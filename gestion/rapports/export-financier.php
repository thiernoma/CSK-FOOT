<?php
/**
 * Export Excel du rapport financier
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';

Auth::requireLogin();

// Période sélectionnée
$typePeriode = sanitize(get('type_periode', 'mois'));
$mois = (int)get('mois') ?: date('n');
$annee = (int)get('annee') ?: date('Y');
$dateDebutInput = sanitize(get('date_debut', ''));
$dateFinInput   = sanitize(get('date_fin', ''));

if ($typePeriode === 'annee') {
    $dateDebut = "$annee-01-01";
    $dateFin = "$annee-12-31";
    $periodeLabel = "Année $annee";
    $filenamePeriode = $annee;
} elseif ($typePeriode === 'intervalle') {
    $dateDebut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
    $dateFin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
    if ($dateFin < $dateDebut) [$dateDebut, $dateFin] = [$dateFin, $dateDebut];
    $periodeLabel = 'Du ' . formatDate($dateDebut) . ' au ' . formatDate($dateFin);
    $filenamePeriode = str_replace('-', '', $dateDebut) . '_' . str_replace('-', '', $dateFin);
} else {
    $dateDebut = "$annee-" . str_pad($mois, 2, '0', STR_PAD_LEFT) . "-01";
    $dateFin = date('Y-m-t', strtotime($dateDebut));
    $periodeLabel = getMonthName($mois) . " $annee";
    $filenamePeriode = $annee . '_' . str_pad($mois, 2, '0', STR_PAD_LEFT);
}

// ==================== RECETTES ====================

// Recettes des réservations
$recettesReservations = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_reservations,
        SUM(montant) as montant_total,
        SUM(montant_paye) as montant_encaisse,
        SUM(montant - montant_paye) as montant_restant
     FROM reservations
     WHERE date_reservation BETWEEN :debut AND :fin
     AND statut_reservation != 'annulee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes par terrain — base caisse : argent réellement encaissé sur la période
// (table paiements, rattaché au terrain via la réservation), cohérent avec le total RECETTES.
$recettesParTerrain = Database::fetchAll(
    "SELECT t.nom as terrain,
            COUNT(DISTINCT p.reservation_id) as nb_reservations,
            COALESCE(SUM(p.montant), 0) as montant_encaisse
     FROM terrains t
     LEFT JOIN reservations r ON r.terrain_id = t.id
     LEFT JOIN paiements p ON p.reservation_id = r.id
        AND p.type_paiement = 'paiement'
        AND DATE(p.created_at) BETWEEN :debut AND :fin
     GROUP BY t.id
     ORDER BY montant_encaisse DESC, t.nom ASC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes par mode de paiement
$recettesParMode = Database::fetchAll(
    "SELECT mode_paiement,
            COUNT(*) as nb_paiements,
            SUM(montant) as total
     FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
     AND type_paiement = 'paiement'
     GROUP BY mode_paiement
     ORDER BY total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Recettes des cotisations académie
$recettesCotisations = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_cotisations,
        SUM(montant) as montant_total,
        SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant_encaisse
     FROM cotisations
     WHERE (annee = :annee AND mois = :mois) OR date_paiement BETWEEN :debut AND :fin",
    ['annee' => $annee, 'mois' => $mois, 'debut' => $dateDebut, 'fin' => $dateFin]
);

// Total des recettes
$totalRecettes = Database::fetchOne(
    "SELECT SUM(montant) as total FROM paiements
     WHERE DATE(created_at) BETWEEN :debut AND :fin
     AND type_paiement = 'paiement'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// ==================== DÉPENSES ====================

// Total des dépenses
$totalDepenses = Database::fetchOne(
    "SELECT
        COUNT(*) as nb_depenses,
        SUM(montant) as montant_total
     FROM depenses
     WHERE date_depense BETWEEN :debut AND :fin
     AND statut = 'payee'",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Dépenses par catégorie
$depensesParCategorie = Database::fetchAll(
    "SELECT c.nom as categorie,
            COUNT(d.id) as nb_depenses,
            SUM(d.montant) as montant_total
     FROM categories_depenses c
     LEFT JOIN depenses d ON c.id = d.categorie_id
        AND d.date_depense BETWEEN :debut AND :fin
        AND d.statut = 'payee'
     GROUP BY c.id
     HAVING montant_total > 0
     ORDER BY montant_total DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Liste des dépenses détaillées
$listeDepenses = Database::fetchAll(
    "SELECT d.*, c.nom as categorie_nom, t.nom as terrain_nom
     FROM depenses d
     JOIN categories_depenses c ON d.categorie_id = c.id
     LEFT JOIN terrains t ON d.terrain_id = t.id
     WHERE d.date_depense BETWEEN :debut AND :fin
     AND d.statut = 'payee'
     ORDER BY d.date_depense DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// Liste des paiements
$listePaiements = Database::fetchAll(
    "SELECT p.*, r.numero_ticket, c.nom as client_nom, c.prenom as client_prenom
     FROM paiements p
     JOIN reservations r ON p.reservation_id = r.id
     JOIN clients c ON r.client_id = c.id
     WHERE DATE(p.created_at) BETWEEN :debut AND :fin
     AND p.type_paiement = 'paiement'
     ORDER BY p.created_at DESC",
    ['debut' => $dateDebut, 'fin' => $dateFin]
);

// ==================== BILAN ====================
$bilan = ($totalRecettes['total'] ?? 0) - ($totalDepenses['montant_total'] ?? 0);

// Génération du fichier Excel (format CSV compatible Excel)
$filename = "rapport_financier_{$filenamePeriode}.csv";

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// BOM UTF-8 pour Excel
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');

// Titre
fputcsv($output, ['RAPPORT FINANCIER - ' . strtoupper($periodeLabel)], ';');
fputcsv($output, ['Complexe Sportif Kaira'], ';');
fputcsv($output, ['Date de génération: ' . date('d/m/Y H:i')], ';');
fputcsv($output, [], ';');

// ==================== RÉSUMÉ ====================
fputcsv($output, ['=== RÉSUMÉ ==='], ';');
fputcsv($output, [], ';');
fputcsv($output, ['Total Recettes', number_format($totalRecettes['total'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['Total Dépenses', number_format($totalDepenses['montant_total'] ?? 0, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, ['RÉSULTAT NET', number_format($bilan, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($output, [], ';');

// ==================== RECETTES PAR TERRAIN ====================
fputcsv($output, ['=== RECETTES PAR TERRAIN ==='], ';');
fputcsv($output, ['Terrain', 'Réservations', 'Montant Encaissé'], ';');
foreach ($recettesParTerrain as $t) {
    fputcsv($output, [
        $t['terrain'],
        $t['nb_reservations'] ?? 0,
        number_format($t['montant_encaisse'] ?? 0, 0, ',', ' ')
    ], ';');
}
fputcsv($output, [
    'TOTAL',
    array_sum(array_column($recettesParTerrain, 'nb_reservations')),
    number_format(array_sum(array_column($recettesParTerrain, 'montant_encaisse')), 0, ',', ' ')
], ';');
fputcsv($output, [], ';');

// ==================== RECETTES PAR MODE DE PAIEMENT ====================
fputcsv($output, ['=== RECETTES PAR MODE DE PAIEMENT ==='], ';');
fputcsv($output, ['Mode de paiement', 'Nb Paiements', 'Montant Total'], ';');
foreach ($recettesParMode as $mode) {
    fputcsv($output, [
        ucfirst($mode['mode_paiement']),
        $mode['nb_paiements'],
        number_format($mode['total'], 0, ',', ' ')
    ], ';');
}
fputcsv($output, [], ';');

// ==================== DÉPENSES PAR CATÉGORIE ====================
fputcsv($output, ['=== DÉPENSES PAR CATÉGORIE ==='], ';');
fputcsv($output, ['Catégorie', 'Nb Dépenses', 'Montant Total'], ';');
foreach ($depensesParCategorie as $cat) {
    fputcsv($output, [
        $cat['categorie'],
        $cat['nb_depenses'],
        number_format($cat['montant_total'], 0, ',', ' ')
    ], ';');
}
fputcsv($output, [
    'TOTAL',
    $totalDepenses['nb_depenses'] ?? 0,
    number_format($totalDepenses['montant_total'] ?? 0, 0, ',', ' ')
], ';');
fputcsv($output, [], ';');

// ==================== DÉTAIL DES PAIEMENTS ====================
fputcsv($output, ['=== DÉTAIL DES PAIEMENTS ==='], ';');
fputcsv($output, ['Date', 'N° Ticket', 'Client', 'Mode', 'Montant', 'Référence'], ';');
foreach ($listePaiements as $p) {
    fputcsv($output, [
        date('d/m/Y H:i', strtotime($p['created_at'])),
        $p['numero_ticket'],
        $p['client_prenom'] . ' ' . $p['client_nom'],
        ucfirst($p['mode_paiement']),
        number_format($p['montant'], 0, ',', ' '),
        $p['reference'] ?? ''
    ], ';');
}
fputcsv($output, [], ';');

// ==================== DÉTAIL DES DÉPENSES ====================
fputcsv($output, ['=== DÉTAIL DES DÉPENSES ==='], ';');
fputcsv($output, ['Date', 'Catégorie', 'Description', 'Terrain', 'Fournisseur', 'Mode Paiement', 'Montant'], ';');
foreach ($listeDepenses as $d) {
    fputcsv($output, [
        date('d/m/Y', strtotime($d['date_depense'])),
        $d['categorie_nom'],
        $d['description'] ?? $d['libelle'],
        $d['terrain_nom'] ?? '',
        $d['fournisseur'] ?? '',
        ucfirst($d['mode_paiement'] ?? ''),
        number_format($d['montant'], 0, ',', ' ')
    ], ';');
}

fclose($output);
exit;
