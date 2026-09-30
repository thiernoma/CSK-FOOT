<?php
/**
 * API Séances
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Seance.php';
require_once APP_PATH . 'models/Presence.php';

header('Content-Type: application/json');

Auth::requireLogin();

$action = get('action', post('action'));

try {
    switch ($action) {
        case 'calendar':
            // Événements pour le calendrier
            $start = get('start');
            $end = get('end');
            $categorie = get('categorie');

            if (!$start || !$end) {
                throw new Exception('Dates requises');
            }

            $events = Seance::getForCalendar($start, $end);

            // Filtrer par catégorie si spécifié
            if ($categorie) {
                $events = array_filter($events, fn($e) => $e['extendedProps']['categorie'] === $categorie);
                $events = array_values($events);
            }

            echo json_encode(['success' => true, 'events' => $events]);
            break;

        case 'today':
            // Séances du jour
            $seances = Seance::getToday();
            echo json_encode(['success' => true, 'seances' => $seances]);
            break;

        case 'upcoming':
            // Séances à venir
            $limit = (int)get('limit', 10);
            $categorie = get('categorie');
            $seances = Seance::getUpcoming($limit, $categorie);
            echo json_encode(['success' => true, 'seances' => $seances]);
            break;

        case 'stats':
            // Statistiques
            $dateDebut = get('date_debut');
            $dateFin = get('date_fin');
            $stats = Seance::getStats($dateDebut, $dateFin);
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;

        case 'duplicate':
            // Dupliquer une séance
            Auth::requirePermission('academie');

            $id = (int)post('id');
            $newDate = post('date');

            if (!$id || !$newDate) {
                throw new Exception('Données manquantes');
            }

            $newId = Seance::duplicate($id, $newDate);
            Auth::logAction(Auth::id(), 'create', 'seances', $newId, 'Duplication de #' . $id);

            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Séance dupliquée']);
            break;

        default:
            throw new Exception('Action non reconnue');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
