<?php
/**
 * API Réservations
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Terrain.php';

$action = get('action', post('action'));

// Actions publiques (sans authentification requise)
$publicActions = ['availability', 'check_disponibilite', 'creneaux_reserves', 'calculer_prix'];

// Vérifier l'authentification pour les actions non publiques
if (!in_array($action, $publicActions) && !Auth::check()) {
    jsonResponse(['error' => 'Non autorisé'], 401);
}

switch ($action) {
    case 'availability':
        // Disponibilité pour le site public (créneaux réservés pour un terrain et une date)
        $terrainId = (int)get('terrain_id');
        $date = sanitize(get('date'));

        if (!$terrainId || !$date) {
            jsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $creneaux = Terrain::getReservedSlots($terrainId, $date);
        jsonResponse([
            'creneaux_reserves' => $creneaux,
            'ferme'             => isDateFermee($date),
            'message_fermeture' => isDateFermee($date) ? messageFermeture() : ''
        ]);
        break;

    case 'calendar':
        // Événements pour FullCalendar
        $start = sanitize(get('start'));
        $end = sanitize(get('end'));
        $terrainId = get('terrain_id') ? (int)get('terrain_id') : null;

        if (!$start || !$end) {
            jsonResponse(['error' => 'Dates requises'], 400);
        }

        $events = Reservation::getForCalendar($start, $end, $terrainId);
        jsonResponse($events);
        break;

    case 'check_disponibilite':
        // Vérifier la disponibilité d'un créneau
        $terrainId = (int)get('terrain_id');
        $date = sanitize(get('date'));
        $heureDebut = wallToOp(sanitize(get('heure_debut')));
        $heureFin   = wallToOp(sanitize(get('heure_fin')));
        $excludeId = get('exclude_id') ? (int)get('exclude_id') : null;

        if (!$terrainId || !$date || !$heureDebut || !$heureFin) {
            jsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $disponible = Terrain::isAvailable($terrainId, $date, $heureDebut, $heureFin, $excludeId);
        jsonResponse(['disponible' => $disponible]);
        break;

    case 'creneaux_reserves':
        // Créneaux réservés pour un terrain et une date
        $terrainId = (int)get('terrain_id');
        $date = sanitize(get('date'));

        if (!$terrainId || !$date) {
            jsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $creneaux = Terrain::getReservedSlots($terrainId, $date);
        jsonResponse($creneaux);
        break;

    case 'calculer_prix':
        // Calculer le prix d'une réservation
        $terrainId = (int)get('terrain_id');
        $date = sanitize(get('date'));
        $heureDebut = wallToOp(sanitize(get('heure_debut')));
        $heureFin   = wallToOp(sanitize(get('heure_fin')));

        if (!$terrainId || !$date || !$heureDebut || !$heureFin) {
            jsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $prix = Terrain::calculatePrice($terrainId, $date, $heureDebut, $heureFin);
        jsonResponse(['prix' => $prix]);
        break;

    case 'get':
        // Récupérer une réservation
        $id = (int)get('id');
        $reservation = Reservation::getById($id);

        if ($reservation) {
            jsonResponse($reservation);
        } else {
            jsonResponse(['error' => 'Réservation non trouvée'], 404);
        }
        break;

    case 'create':
        // Créer une réservation
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $data = [
            'client_id' => (int)($input['client_id'] ?? 0),
            'terrain_id' => (int)($input['terrain_id'] ?? 0),
            'date_reservation' => sanitize($input['date_reservation'] ?? ''),
            'heure_debut' => sanitize($input['heure_debut'] ?? ''),
            'heure_fin' => sanitize($input['heure_fin'] ?? ''),
            'mode_paiement' => sanitize($input['mode_paiement'] ?? ''),
            'paiement_immediat' => (float)($input['paiement_immediat'] ?? 0),
            'notes' => sanitize($input['notes'] ?? ''),
            'cree_par' => Auth::id()
        ];

        $result = Reservation::create($data);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'add_payment':
        // Ajouter un paiement
        $reservationId = (int)get('id', post('reservation_id'));
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $data = [
            'montant' => (float)($input['montant'] ?? 0),
            'mode_paiement' => sanitize($input['mode_paiement'] ?? ''),
            'reference' => sanitize($input['reference'] ?? ''),
            'notes' => sanitize($input['notes'] ?? ''),
            'recu_par' => Auth::id()
        ];

        $result = Reservation::addPayment($reservationId, $data);
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'cancel':
        // Annuler une réservation
        $id = (int)get('id', post('id'));
        $motif = sanitize(post('motif', 'Annulation par l\'utilisateur'));

        $result = Reservation::cancel($id, $motif, Auth::id());
        jsonResponse($result, $result['success'] ? 200 : 400);
        break;

    case 'stats':
        // Statistiques des réservations
        $periode = sanitize(get('periode', 'jour'));
        $stats = Reservation::getStats($periode);
        jsonResponse($stats);
        break;

    case 'list':
        // Liste des réservations
        $filters = [
            'date' => sanitize(get('date')),
            'terrain_id' => get('terrain_id') ? (int)get('terrain_id') : null,
            'client_id' => get('client_id') ? (int)get('client_id') : null,
            'statut_reservation' => sanitize(get('statut')),
            'statut_paiement' => sanitize(get('paiement'))
        ];

        $limit = min((int)get('limit', 20), 100);
        $offset = (int)get('offset', 0);

        $reservations = Reservation::getAll($filters, $limit, $offset);
        $total = Reservation::count($filters);

        jsonResponse([
            'data' => $reservations,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ]);
        break;

    default:
        jsonResponse(['error' => 'Action non reconnue'], 400);
}
