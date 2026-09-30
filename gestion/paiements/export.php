<?php
/**
 * Export CSV des encaissements (toutes catégories)
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';

Auth::requireLogin();

// Filtres (mêmes que paiements/index.php)
$dateDebut = get('date_debut', date('Y-m-01'));
$dateFin   = get('date_fin', date('Y-m-d'));
$modePaiement = get('mode');
$categorieFilter = (int)get('categorie');

// Validation des dates
if (!strtotime($dateDebut)) $dateDebut = date('Y-m-01');
if (!strtotime($dateFin))   $dateFin   = date('Y-m-d');
if ($dateFin < $dateDebut) [$dateDebut, $dateFin] = [$dateFin, $dateDebut];

// Construire la requête
$sql = "SELECT p.*, r.numero_ticket, r.date_reservation,
               CONCAT(c.prenom, ' ', c.nom) as client_nom, t.nom as terrain_nom,
               u.nom as recu_par_nom, u.prenom as recu_par_prenom,
               cat.libelle as categorie_libelle,
               sc.id as session_id, sc.statut as session_statut
        FROM paiements p
        LEFT JOIN reservations r ON p.reservation_id = r.id
        LEFT JOIN clients c ON r.client_id = c.id
        LEFT JOIN terrains t ON r.terrain_id = t.id
        LEFT JOIN users u ON p.recu_par = u.id
        LEFT JOIN categories_encaissement cat ON p.categorie_id = cat.id
        LEFT JOIN clotures_caisse sc ON p.session_caisse_id = sc.id
        WHERE DATE(p.created_at) BETWEEN :date_debut AND :date_fin";

$params = ['date_debut' => $dateDebut, 'date_fin' => $dateFin];

if ($modePaiement) {
    $sql .= " AND p.mode_paiement = :mode";
    $params['mode'] = $modePaiement;
}
if ($categorieFilter > 0) {
    $sql .= " AND p.categorie_id = :cat";
    $params['cat'] = $categorieFilter;
}

$sql .= " ORDER BY p.created_at DESC";

$paiements = Database::fetchAll($sql, $params);

// Totaux
$totaux = Database::fetchOne(
    "SELECT
        COUNT(*) as nb,
        COALESCE(SUM(CASE WHEN type_paiement = 'paiement'      THEN montant END), 0) as enc,
        COALESCE(SUM(CASE WHEN type_paiement = 'remboursement' THEN montant END), 0) as remb
     FROM paiements
     WHERE DATE(created_at) BETWEEN :a AND :b",
    ['a' => $dateDebut, 'b' => $dateFin]
);

$net = (float)$totaux['enc'] - (float)$totaux['remb'];

// Audit
Auth::logAction(Auth::id(), 'export', 'paiements', null, [
    'date_debut' => $dateDebut, 'date_fin' => $dateFin, 'nb_lignes' => count($paiements)
]);

// Émission CSV
$filename = 'encaissements_' . str_replace('-', '', $dateDebut) . '_' . str_replace('-', '', $dateFin) . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM UTF-8 pour Excel
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

// En-tête
fputcsv($out, ['ENCAISSEMENTS - ' . APP_FULL_NAME], ';');
fputcsv($out, ['Période', 'du ' . formatDate($dateDebut) . ' au ' . formatDate($dateFin)], ';');
fputcsv($out, ['Généré le', date('d/m/Y H:i')], ';');
fputcsv($out, ['Total encaissé', number_format((float)$totaux['enc'], 0, ',', ' ') . ' FCFA'], ';');
fputcsv($out, ['Total remboursé', number_format((float)$totaux['remb'], 0, ',', ' ') . ' FCFA'], ';');
fputcsv($out, ['Net', number_format($net, 0, ',', ' ') . ' FCFA'], ';');
fputcsv($out, [], ';');

// Colonnes
fputcsv($out, [
    'Date',
    'Heure',
    'Type',
    'Catégorie',
    'Libellé / Référence',
    'Ticket réservation',
    'Client',
    'Terrain',
    'Mode paiement',
    'Référence externe',
    'Montant (FCFA)',
    'Reçu par',
    'Session caisse'
], ';');

// Lignes
foreach ($paiements as $p) {
    $detail = $p['libelle'] ?? '';
    if (empty($detail) && !empty($p['numero_ticket'])) {
        $detail = 'Réservation ' . $p['numero_ticket'];
    }

    fputcsv($out, [
        formatDate($p['created_at'], 'd/m/Y'),
        formatDate($p['created_at'], 'H:i'),
        $p['type_paiement'] === 'remboursement' ? 'Remboursement' : 'Encaissement',
        $p['categorie_libelle'] ?? 'Sans catégorie',
        $detail,
        $p['numero_ticket'] ?? '',
        $p['client_nom'] ?? '',
        $p['terrain_nom'] ?? '',
        ucfirst($p['mode_paiement']),
        $p['reference'] ?? '',
        ($p['type_paiement'] === 'remboursement' ? '-' : '') . number_format($p['montant'], 0, ',', ' '),
        trim(($p['recu_par_prenom'] ?? '') . ' ' . ($p['recu_par_nom'] ?? '')),
        $p['session_id'] ? '#' . $p['session_id'] . ' (' . ($p['session_statut'] ?? '') . ')' : ''
    ], ';');
}

fclose($out);
exit;
