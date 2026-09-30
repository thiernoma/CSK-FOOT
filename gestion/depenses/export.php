<?php
/**
 * Export des dépenses (Excel/PDF)
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Depense.php';

Auth::requireLogin();

$format = get('format', 'excel');
$periode = get('periode', 'mois');
$dateDebutInput = sanitize(get('date_debut', ''));
$dateFinInput   = sanitize(get('date_fin', ''));

// Construire les filtres
$filters = [
    'categorie_id' => get('categorie') ?: null,
    'mois' => get('mois') ?: date('m'),
    'annee' => get('annee') ?: date('Y'),
];

$moisNoms = [
    1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
    5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
    9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
];

// Titre selon la période
if ($periode === 'jour') {
    $filters['date_jour'] = date('Y-m-d');
    $titrePeriode = 'du ' . date('d/m/Y');
    $filenamePeriode = date('Y-m-d');
} elseif ($periode === 'intervalle') {
    $debut = ($dateDebutInput && strtotime($dateDebutInput)) ? $dateDebutInput : date('Y-m-01');
    $fin   = ($dateFinInput   && strtotime($dateFinInput))   ? $dateFinInput   : date('Y-m-t');
    if ($fin < $debut) [$debut, $fin] = [$fin, $debut];
    $filters['date_debut'] = $debut;
    $filters['date_fin']   = $fin;
    $titrePeriode = 'du ' . date('d/m/Y', strtotime($debut)) . ' au ' . date('d/m/Y', strtotime($fin));
    $filenamePeriode = str_replace('-', '', $debut) . '_' . str_replace('-', '', $fin);
} else {
    $titrePeriode = $moisNoms[(int)$filters['mois']] . ' ' . $filters['annee'];
    $filenamePeriode = $filters['mois'] . '_' . $filters['annee'];
}

// Récupérer les dépenses
$depenses = Depense::getAll($filters);

// Calculer le total
$total = array_sum(array_column($depenses, 'montant'));
$totalPayees = array_sum(array_map(function($d) {
    return $d['statut'] === 'payee' ? $d['montant'] : 0;
}, $depenses));
$totalAnnulees = array_sum(array_map(function($d) {
    return $d['statut'] === 'annulee' ? $d['montant'] : 0;
}, $depenses));

if ($format === 'excel') {
    // Export CSV (compatible Excel)
    $filename = 'depenses_' . $filenamePeriode . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // BOM UTF-8 pour Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // En-tête
    fputcsv($output, ['RAPPORT DES DEPENSES - ' . strtoupper($titrePeriode)], ';');
    fputcsv($output, ['Généré le ' . date('d/m/Y H:i')], ';');
    fputcsv($output, [], ';');

    // Colonnes
    fputcsv($output, [
        'Date',
        'Libellé',
        'Catégorie',
        'Fournisseur',
        'Montant (FCFA)',
        'Statut',
        'Mode paiement',
        'Référence'
    ], ';');

    // Données
    foreach ($depenses as $d) {
        $statutLabel = match($d['statut']) {
            'payee' => 'Payée',
            'annulee' => 'Annulée',
            default => 'Payée'
        };
        $modeLabel = match($d['mode_paiement'] ?? '') {
            'especes' => 'Espèces',
            'wave' => 'Wave',
            'om' => 'Orange Money',
            'carte' => 'Carte bancaire',
            'virement' => 'Virement',
            'cheque' => 'Chèque',
            default => $d['mode_paiement'] ?? '-'
        };

        fputcsv($output, [
            date('d/m/Y', strtotime($d['date_depense'])),
            $d['libelle'],
            $d['categorie_nom'],
            $d['fournisseur'] ?? '-',
            number_format($d['montant'], 0, ',', ' '),
            $statutLabel,
            $modeLabel,
            $d['reference_paiement'] ?? '-'
        ], ';');
    }

    // Totaux
    fputcsv($output, [], ';');
    fputcsv($output, ['', '', '', 'TOTAL', number_format($total, 0, ',', ' '), '', '', ''], ';');
    fputcsv($output, ['', '', '', 'Payées', number_format($totalPayees, 0, ',', ' '), '', '', ''], ';');
    fputcsv($output, ['', '', '', 'Annulées', number_format($totalAnnulees, 0, ',', ' '), '', '', ''], ';');

    fclose($output);
    exit;

} elseif ($format === 'pdf') {
    // Export PDF simple (HTML to Print)
    $filename = 'depenses_' . $filenamePeriode;
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dépenses <?= e($titrePeriode) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, sans-serif; font-size: 12px; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #1a3a6b; padding-bottom: 15px; }
        .header h1 { color: #1a3a6b; font-size: 20px; margin-bottom: 5px; }
        .header p { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #1a3a6b; color: white; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .montant { font-weight: bold; color: #dc3545; }
        .total-row { background-color: #f8f9fa !important; font-weight: bold; }
        .badge { padding: 3px 8px; border-radius: 3px; font-size: 10px; }
        .badge-success { background-color: #28a745; color: white; }
        .badge-warning { background-color: #ffc107; color: #333; }
        .badge-secondary { background-color: #6c757d; color: white; }
        .summary { display: flex; justify-content: space-between; margin-top: 20px; }
        .summary-box { background: #f8f9fa; padding: 15px; border-radius: 5px; width: 30%; text-align: center; }
        .summary-box h3 { font-size: 18px; margin-bottom: 5px; }
        .summary-box.total h3 { color: #dc3545; }
        .summary-box.payee h3 { color: #28a745; }
        .summary-box.attente h3 { color: #6c757d; }
        .footer { margin-top: 30px; text-align: center; color: #666; font-size: 10px; border-top: 1px solid #ddd; padding-top: 10px; }
        @media print {
            body { padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="margin-bottom: 20px; text-align: center;">
        <button onclick="window.print()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; background: #1a3a6b; color: white; border: none; border-radius: 5px;">
            Imprimer / Enregistrer en PDF
        </button>
        <button onclick="window.close()" style="padding: 10px 30px; font-size: 14px; cursor: pointer; margin-left: 10px;">
            Fermer
        </button>
    </div>

    <div class="header">
        <h1><?= e(APP_FULL_NAME) ?></h1>
        <p>Rapport des Dépenses - <?= e($titrePeriode) ?></p>
        <p>Généré le <?= date('d/m/Y à H:i') ?></p>
    </div>

    <?php if (empty($depenses)): ?>
    <p style="text-align: center; padding: 40px; color: #666;">Aucune dépense pour cette période</p>
    <?php else: ?>
    <table>
        <thead>
            <tr>
                <th width="80">Date</th>
                <th>Libellé</th>
                <th>Catégorie</th>
                <th>Fournisseur</th>
                <th width="100" class="text-right">Montant</th>
                <th width="80" class="text-center">Statut</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($depenses as $d):
                $statutClass = match($d['statut']) {
                    'payee' => 'success',
                    'annulee' => 'secondary',
                    default => 'success'
                };
                $statutLabel = match($d['statut']) {
                    'payee' => 'Payée',
                    'annulee' => 'Annulée',
                    default => 'Payée'
                };
            ?>
            <tr>
                <td><?= date('d/m/Y', strtotime($d['date_depense'])) ?></td>
                <td><strong><?= e($d['libelle']) ?></strong></td>
                <td><?= e($d['categorie_nom']) ?></td>
                <td><?= e($d['fournisseur'] ?? '-') ?></td>
                <td class="text-right montant"><?= formatMoney($d['montant']) ?></td>
                <td class="text-center">
                    <span class="badge badge-<?= $statutClass ?>"><?= $statutLabel ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="4" class="text-right">TOTAL</td>
                <td class="text-right montant"><?= formatMoney($total) ?></td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-box total">
            <p>Total dépenses</p>
            <h3><?= formatMoney($total) ?></h3>
        </div>
        <div class="summary-box payee">
            <p>Dépenses payées</p>
            <h3><?= formatMoney($totalPayees) ?></h3>
        </div>
        <div class="summary-box attente">
            <p>Annulées</p>
            <h3><?= formatMoney($totalAnnulees) ?></h3>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <p><?= e(APP_FULL_NAME) ?> - Système de gestion</p>
    </div>
</body>
</html>
<?php
    exit;
}

// Format non reconnu, rediriger
Session::flash('warning', 'Format d\'export non reconnu.');
redirect(url('depenses/index'));
