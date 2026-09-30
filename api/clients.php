<?php
/**
 * API Clients
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Client.php';

// Vérifier l'authentification
if (!Auth::check()) {
    jsonResponse(['error' => 'Non autorisé'], 401);
}

$action = get('action', post('action'));

switch ($action) {
    case 'search':
        // Recherche de clients
        $query = sanitize(get('q', ''));
        if (strlen($query) < 2) {
            jsonResponse([]);
        }
        $clients = Client::search($query, 10);
        jsonResponse($clients);
        break;

    case 'get':
        // Récupérer un client
        $id = (int)get('id');
        $client = Client::getById($id);
        if ($client) {
            jsonResponse($client);
        } else {
            jsonResponse(['error' => 'Client non trouvé'], 404);
        }
        break;

    case 'create':
        // Créer un client
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        $nom = sanitize($input['nom'] ?? '');
        $prenom = sanitize($input['prenom'] ?? '');
        $telephone = sanitize($input['telephone'] ?? '');
        $email = sanitize($input['email'] ?? '');

        if (empty($nom) || empty($telephone)) {
            jsonResponse(['success' => false, 'message' => 'Nom et téléphone obligatoires'], 400);
        }

        // Vérifier si le téléphone existe déjà
        if (Client::getByPhone($telephone)) {
            jsonResponse(['success' => false, 'message' => 'Ce numéro de téléphone existe déjà'], 400);
        }

        try {
            $clientId = Client::create([
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'email' => $email
            ]);

            Auth::logAction(Auth::id(), 'create', 'clients', $clientId);

            jsonResponse([
                'success' => true,
                'id' => $clientId,
                'message' => 'Client créé avec succès'
            ]);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'update':
        // Mettre à jour un client
        $id = (int)get('id', post('id'));
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            $input = $_POST;
        }

        try {
            Client::update($id, $input);
            Auth::logAction(Auth::id(), 'update', 'clients', $id);

            jsonResponse(['success' => true, 'message' => 'Client mis à jour']);
        } catch (Exception $e) {
            jsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
        break;

    case 'stats':
        // Statistiques d'un client
        $id = (int)get('id');
        $stats = Client::getStats($id);
        jsonResponse($stats);
        break;

    case 'reservations':
        // Réservations d'un client
        $id = (int)get('id');
        $limit = (int)get('limit', 10);
        $reservations = Client::getReservations($id, $limit);
        jsonResponse($reservations);
        break;

    default:
        jsonResponse(['error' => 'Action non reconnue'], 400);
}
