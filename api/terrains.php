<?php
/**
 * API Terrains
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

// Vérifier l'authentification
if (!Auth::check()) {
    jsonResponse(['error' => 'Non autorisé'], 401);
}

$action = get('action', post('action'));

switch ($action) {
    case 'list':
        // Liste des terrains
        $statut = sanitize(get('statut'));
        $terrains = Terrain::getAll($statut ?: null);
        jsonResponse($terrains);
        break;

    case 'get':
        // Récupérer un terrain
        $id = (int)get('id');
        $terrain = Terrain::getById($id);

        if ($terrain) {
            jsonResponse($terrain);
        } else {
            jsonResponse(['error' => 'Terrain non trouvé'], 404);
        }
        break;

    case 'disponibilite':
        // Disponibilités d'un terrain pour une date
        $terrainId = (int)get('id');
        $date = sanitize(get('date', date('Y-m-d')));

        if (!$terrainId) {
            jsonResponse(['error' => 'ID terrain requis'], 400);
        }

        $terrain = Terrain::getById($terrainId);
        if (!$terrain) {
            jsonResponse(['error' => 'Terrain non trouvé'], 404);
        }

        $creneauxReserves = Terrain::getReservedSlots($terrainId, $date);
        $tauxOccupation = Terrain::getOccupancyRate($terrainId, $date);

        jsonResponse([
            'terrain' => $terrain,
            'date' => $date,
            'creneaux_reserves' => $creneauxReserves,
            'taux_occupation' => $tauxOccupation
        ]);
        break;

    case 'stats':
        // Statistiques d'un terrain
        $id = (int)get('id');
        $periode = sanitize(get('periode', 'mois'));

        if (!$id) {
            jsonResponse(['error' => 'ID terrain requis'], 400);
        }

        $stats = Terrain::getStats($id, $periode);
        jsonResponse($stats);
        break;

    case 'occupation':
        // Taux d'occupation
        $id = (int)get('id');
        $date = sanitize(get('date', date('Y-m-d')));

        if (!$id) {
            jsonResponse(['error' => 'ID terrain requis'], 400);
        }

        $taux = Terrain::getOccupancyRate($id, $date);
        jsonResponse(['taux' => $taux]);
        break;

    case 'prix':
        // Calculer le prix
        $id = (int)get('id');
        $date = sanitize(get('date'));
        $heureDebut = wallToOp(sanitize(get('heure_debut')));
        $heureFin   = wallToOp(sanitize(get('heure_fin')));

        if (!$id || !$date || !$heureDebut || !$heureFin) {
            jsonResponse(['error' => 'Paramètres manquants'], 400);
        }

        $prix = Terrain::calculatePrice($id, $date, $heureDebut, $heureFin);
        jsonResponse(['prix' => $prix]);
        break;

    default:
        jsonResponse(['error' => 'Action non reconnue'], 400);
}
