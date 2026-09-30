<?php
/**
 * API Présences
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Presence.php';

Auth::requireLogin();
Auth::requirePermission('academie');

$action = post('action', get('action'));

try {
    switch ($action) {
        case 'add':
            // Ajouter un membre à une séance
            $seanceId = (int)post('seance_id');
            $membreId = (int)post('membre_id');
            $statut = post('statut', 'present');

            if (!$seanceId || !$membreId) {
                throw new Exception('Données manquantes');
            }

            $id = Presence::record($seanceId, $membreId, $statut);

            if (isAjax()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'id' => $id]);
            } else {
                Session::flash('success', 'Membre ajouté.');
                redirect(url('seances/presences.php?id=' . $seanceId));
            }
            break;

        case 'update':
            // Mettre à jour le statut
            $seanceId = (int)post('seance_id');
            $membreId = (int)post('membre_id');
            $statut = post('statut');
            $commentaire = sanitize(post('commentaire'));

            if (!$seanceId || !$membreId || !$statut) {
                throw new Exception('Données manquantes');
            }

            Presence::record($seanceId, $membreId, $statut, $commentaire);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            // Supprimer une présence
            $id = (int)post('id');
            if (!$id) {
                throw new Exception('ID manquant');
            }

            Presence::delete($id);

            header('Content-Type: application/json');
            echo json_encode(['success' => true]);
            break;

        case 'stats_membre':
            // Statistiques d'un membre
            $membreId = (int)get('membre_id');
            $dateDebut = get('date_debut');
            $dateFin = get('date_fin');

            if (!$membreId) {
                throw new Exception('Membre non spécifié');
            }

            $stats = Presence::getMembreStats($membreId, $dateDebut, $dateFin);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'evolution':
            // Évolution mensuelle
            $categorie = get('categorie');
            $evolution = Presence::getMonthlyEvolution($categorie);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'evolution' => $evolution]);
            break;

        case 'top_absents':
            // Top des absences
            $limit = (int)get('limit', 10);
            $categorie = get('categorie');
            $data = Presence::getTopAbsents($limit, $categorie);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'top_presents':
            // Top des présences
            $limit = (int)get('limit', 10);
            $categorie = get('categorie');
            $data = Presence::getTopPresents($limit, $categorie);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    if (isAjax()) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    } else {
        Session::flash('danger', $e->getMessage());
        redirect($_SERVER['HTTP_REFERER'] ?? url('seances/index.php'));
    }
}
