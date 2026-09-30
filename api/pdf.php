<?php
/**
 * API de génération PDF
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'services/PdfGenerator.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Paiement.php';
require_once APP_PATH . 'models/MembreAcademie.php';

Auth::requireLogin();

$type = get('type');
$id = (int)get('id');
$action = get('action', 'display'); // display ou download

try {
    $pdf = new PdfGenerator();
    $content = '';
    $filename = '';

    switch ($type) {
        case 'facture':
            if (!$id) {
                throw new Exception('ID de réservation manquant');
            }
            $reservation = Reservation::getById($id);
            if (!$reservation) {
                throw new Exception('Réservation introuvable');
            }
            $content = $pdf->generateFacture($reservation);
            $filename = 'facture_' . $reservation['numero_ticket'] . '.pdf';
            break;

        case 'recu':
            if (!$id) {
                throw new Exception('ID de paiement manquant');
            }
            $paiement = Paiement::getById($id);
            if (!$paiement) {
                throw new Exception('Paiement introuvable');
            }
            $content = $pdf->generateRecu($paiement);
            $filename = 'recu_' . str_pad($paiement['id'], 6, '0', STR_PAD_LEFT) . '.pdf';
            break;

        case 'carte':
            if (!$id) {
                throw new Exception('ID de membre manquant');
            }
            $membre = MembreAcademie::getById($id);
            if (!$membre) {
                throw new Exception('Membre introuvable');
            }
            $content = $pdf->generateCarteMembre($membre);
            $filename = 'carte_' . $membre['numero_licence'] . '.pdf';
            break;

        case 'rapport-financier':
            Auth::requirePermission('rapports');

            $dateDebut = get('date_debut', date('Y-m-01'));
            $dateFin = get('date_fin', date('Y-m-d'));

            // Récupérer les données
            $data = [
                'total_reservations' => Database::fetchOne(
                    "SELECT COALESCE(SUM(montant_total), 0) as total
                     FROM reservations
                     WHERE date_reservation BETWEEN :debut AND :fin",
                    ['debut' => $dateDebut, 'fin' => $dateFin]
                )['total'],

                'total_paiements' => Database::fetchOne(
                    "SELECT COALESCE(SUM(montant), 0) as total
                     FROM paiements
                     WHERE date_paiement BETWEEN :debut AND :fin",
                    ['debut' => $dateDebut, 'fin' => $dateFin]
                )['total'],

                'total_cotisations' => Database::fetchOne(
                    "SELECT COALESCE(SUM(montant), 0) as total
                     FROM cotisations
                     WHERE date_paiement BETWEEN :debut AND :fin AND statut = 'paye'",
                    ['debut' => $dateDebut, 'fin' => $dateFin]
                )['total'],

                'par_mode' => Database::fetchAll(
                    "SELECT mode_paiement, SUM(montant) as total
                     FROM paiements
                     WHERE date_paiement BETWEEN :debut AND :fin
                     GROUP BY mode_paiement",
                    ['debut' => $dateDebut, 'fin' => $dateFin]
                ),

                'par_terrain' => Database::fetchAll(
                    "SELECT t.nom, COUNT(r.id) as nb_reservations, SUM(r.montant_total) as total
                     FROM terrains t
                     LEFT JOIN reservations r ON t.id = r.terrain_id
                        AND r.date_reservation BETWEEN :debut AND :fin
                     GROUP BY t.id
                     ORDER BY total DESC",
                    ['debut' => $dateDebut, 'fin' => $dateFin]
                )
            ];

            $periode = 'Du ' . formatDate($dateDebut, 'd/m/Y') . ' au ' . formatDate($dateFin, 'd/m/Y');
            $content = $pdf->generateRapportFinancier($data, $periode);
            $filename = 'rapport_financier_' . $dateDebut . '_' . $dateFin . '.pdf';
            break;

        case 'presence':
            if (!$id) {
                throw new Exception('ID de séance manquant');
            }

            $seance = Database::fetchOne(
                "SELECT s.*, t.nom as terrain_nom,
                        CONCAT(e.nom, ' ', e.prenom) as entraineur_nom
                 FROM seances s
                 LEFT JOIN terrains t ON s.terrain_id = t.id
                 LEFT JOIN entraineurs e ON s.entraineur_id = e.id
                 WHERE s.id = :id",
                ['id' => $id]
            );

            if (!$seance) {
                throw new Exception('Séance introuvable');
            }

            $presences = Database::fetchAll(
                "SELECT p.*, m.nom as membre_nom, m.prenom as membre_prenom, m.numero_licence
                 FROM presences p
                 JOIN membres_academie m ON p.membre_id = m.id
                 WHERE p.seance_id = :seance_id
                 ORDER BY m.nom, m.prenom",
                ['seance_id' => $id]
            );

            $content = $pdf->generateListePresence($seance, $presences);
            $filename = 'presence_' . $seance['date_seance'] . '.pdf';
            break;

        default:
            throw new Exception('Type de document non reconnu');
    }

    if ($action === 'download') {
        $pdf->download($content, $filename);
    } else {
        $pdf->display($content, $filename);
    }

} catch (Exception $e) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
