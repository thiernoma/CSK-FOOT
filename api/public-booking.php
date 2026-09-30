<?php
/**
 * API Réservation publique
 * Permet aux visiteurs de réserver en ligne
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Client.php';
require_once APP_PATH . 'models/Terrain.php';

// Headers CORS pour API publique
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$action = post('action', get('action'));

try {
    switch ($action) {
        case 'create_public':
            // Créer une réservation publique
            $terrainId = (int)post('terrain_id');
            $date = sanitize(post('date'));
            $heureDebut = sanitize(post('heure_debut'));
            $heureFin = sanitize(post('heure_fin'));
            $clientPrenom = sanitize(post('client_prenom'));
            $clientNom = sanitize(post('client_nom'));
            $clientTelephone = sanitize(post('client_telephone'));
            $clientEmail = sanitize(post('client_email'));

            // Validation
            $errors = [];

            if (!$terrainId) {
                $errors[] = 'Terrain non spécifié.';
            }

            if (!$date || !strtotime($date)) {
                $errors[] = 'Date invalide.';
            } elseif ($date < date('Y-m-d')) {
                $errors[] = 'La date ne peut pas être dans le passé.';
            }

            if (!$heureDebut || !$heureFin) {
                $errors[] = 'Horaires non spécifiés.';
            } else {
                // Normaliser en op-time (gère les créneaux après-minuit)
                $heureDebut = wallToOp($heureDebut);
                $heureFin   = wallToOp($heureFin);
                if ($heureDebut >= $heureFin) {
                    $errors[] = 'L\'heure de fin doit être après l\'heure de début.';
                }
            }

            if (empty($clientNom)) {
                $errors[] = 'Le nom est obligatoire.';
            }

            if (empty($clientTelephone)) {
                $errors[] = 'Le téléphone est obligatoire.';
            } elseif (!preg_match('/^(77|78|76|70|75)\d{7}$/', preg_replace('/\s+/', '', $clientTelephone))) {
                $errors[] = 'Numéro de téléphone sénégalais invalide.';
            }

            if ($clientEmail && !filter_var($clientEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Email invalide.';
            }

            // Vérifier le terrain existe
            $terrain = Terrain::getById($terrainId);
            if (!$terrain) {
                $errors[] = 'Terrain introuvable.';
            } elseif ($terrain['statut'] !== 'actif') {
                $errors[] = 'Ce terrain n\'est pas disponible.';
            }

            // Vérifier la disponibilité
            if (empty($errors)) {
                $isAvailable = Terrain::checkAvailability($terrainId, $date, $heureDebut, $heureFin);
                if (!$isAvailable) {
                    $errors[] = 'Ce créneau est déjà réservé.';
                }
            }

            if (!empty($errors)) {
                jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 400);
            }

            // Nettoyer le téléphone
            $clientTelephone = preg_replace('/\s+/', '', $clientTelephone);

            // Chercher ou créer le client
            $client = Client::getByPhone($clientTelephone);
            if (!$client) {
                // Créer un nouveau client
                $clientId = Client::create([
                    'nom' => $clientNom,
                    'prenom' => $clientPrenom,
                    'telephone' => $clientTelephone,
                    'email' => $clientEmail,
                    'type_client' => 'particulier',
                    'statut' => 'actif'
                ]);
            } else {
                $clientId = $client['id'];
                // Mettre à jour les infos si nécessaire
                if ($clientPrenom && !$client['prenom']) {
                    Client::update($clientId, [
                        'prenom' => $clientPrenom,
                        'nom' => $client['nom'],
                        'telephone' => $client['telephone'],
                        'telephone_alt' => $client['telephone_alt'],
                        'email' => $clientEmail ?: $client['email'],
                        'adresse' => $client['adresse'],
                        'type_client' => $client['type_client'],
                        'statut' => $client['statut'],
                        'notes' => $client['notes']
                    ]);
                }
            }

            // Calculer le montant
            $montant = Terrain::calculatePrice($terrainId, $date, $heureDebut, $heureFin);

            // Calculer la durée (supporte op-time >= 24h)
            $dureeHeures = opDurationHours($heureDebut, $heureFin);

            // Créer la réservation
            $reservationData = [
                'client_id' => $clientId,
                'terrain_id' => $terrainId,
                'date_reservation' => $date,
                'heure_debut' => $heureDebut,
                'heure_fin' => $heureFin,
                'duree_heures' => $dureeHeures,
                'montant' => $montant,
                'montant_paye' => 0,
                'statut_paiement' => 'en_attente',
                'statut_reservation' => 'confirmee',
                'source' => 'site_web',
                'notes' => 'Réservation en ligne'
            ];

            $reservation = Reservation::create($reservationData);

            if ($reservation['success']) {
                // TODO: Envoyer SMS de confirmation

                jsonResponse([
                    'success' => true,
                    'message' => 'Réservation confirmée !',
                    'numero_ticket' => $reservation['numero_ticket'],
                    'reservation_id' => $reservation['id'],
                    'montant' => $montant
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => $reservation['message'] ?? 'Erreur lors de la création de la réservation.'
                ], 500);
            }
            break;

        case 'check_availability':
            // Vérifier la disponibilité d'un créneau
            $terrainId = (int)get('terrain_id');
            $date = sanitize(get('date'));
            $heureDebut = sanitize(get('heure_debut'));
            $heureFin = sanitize(get('heure_fin'));

            if (!$terrainId || !$date || !$heureDebut || !$heureFin) {
                jsonResponse(['success' => false, 'message' => 'Paramètres manquants.'], 400);
            }

            // Normaliser en op-time pour matcher le format en base
            $heureDebut = wallToOp($heureDebut);
            $heureFin   = wallToOp($heureFin);

            $isAvailable = Terrain::checkAvailability($terrainId, $date, $heureDebut, $heureFin);
            $prix = Terrain::calculatePrice($terrainId, $date, $heureDebut, $heureFin);

            jsonResponse([
                'success'   => true,
                'available' => $isAvailable,
                'prix'      => $prix,
                'ferme'     => isDateFermee($date),
                'message'   => isDateFermee($date) ? messageFermeture() : ''
            ]);
            break;

        case 'get_terrains':
            // Liste des terrains disponibles
            $terrains = Terrain::getAll('actif');
            $result = array_map(function($t) {
                return [
                    'id' => $t['id'],
                    'nom' => $t['nom'],
                    'type' => $t['type'],
                    'capacite' => $t['capacite'],
                    'prix_heure' => $t['prix_heure'],
                    'prix_heure_pointe' => $t['prix_heure_pointe'],
                    'prix_weekend' => $t['prix_weekend'],
                    'photo' => $t['photo']
                ];
            }, $terrains);

            jsonResponse($result);
            break;

        case 'get_slots':
            // Créneaux disponibles pour un terrain et une date
            $terrainId = (int)get('terrain_id');
            $date = sanitize(get('date'));

            if (!$terrainId || !$date) {
                jsonResponse(['success' => false, 'message' => 'Paramètres manquants.'], 400);
            }

            // Période de fermeture exceptionnelle : aucun créneau réservable ce jour-là
            if (isDateFermee($date)) {
                jsonResponse([
                    'success' => true,
                    'date'    => $date,
                    'ferme'   => true,
                    'message' => messageFermeture(),
                    'slots'   => []
                ]);
            }

            // Créneaux possibles — identiques au back-office (pas de 30 min, de l'ouverture
            // jusqu'à la fermeture) via getTimeSlots().
            $allSlots = getTimeSlots();

            // Récupérer les créneaux réservés (op-time)
            $reserved = Terrain::getReservedSlots($terrainId, $date);
            $reservedTimes = array_map(function($r) {
                return substr($r['heure_debut'], 0, 5);
            }, $reserved);

            // Marquer les créneaux + ajouter info "lendemain" pour l'affichage
            $slots = array_map(function($slot) use ($reservedTimes) {
                $w = opToWall($slot);
                return [
                    'time' => $slot,                  // op-time, ex: "25:00"
                    'wall' => $w['wall'],             // wall-clock, ex: "01:00"
                    'next_day' => $w['next_day'],
                    'available' => !in_array($slot, $reservedTimes)
                ];
            }, $allSlots);

            jsonResponse([
                'success' => true,
                'date' => $date,
                'slots' => $slots
            ]);
            break;

        case 'verify_booking':
            // Vérifier une réservation par numéro de ticket et téléphone
            $ticket = sanitize(get('ticket'));
            $telephone = sanitize(get('telephone'));

            if (!$ticket || !$telephone) {
                jsonResponse(['success' => false, 'message' => 'Paramètres manquants.'], 400);
            }

            $telephone = preg_replace('/\s+/', '', $telephone);

            $reservation = Database::fetchOne(
                "SELECT r.*, t.nom as terrain_nom
                 FROM reservations r
                 JOIN terrains t ON r.terrain_id = t.id
                 JOIN clients c ON r.client_id = c.id
                 WHERE r.numero_ticket = :ticket
                 AND c.telephone = :telephone",
                ['ticket' => $ticket, 'telephone' => $telephone]
            );

            if ($reservation) {
                jsonResponse([
                    'success' => true,
                    'reservation' => [
                        'numero_ticket' => $reservation['numero_ticket'],
                        'terrain' => $reservation['terrain_nom'],
                        'date' => $reservation['date_reservation'],
                        'heure_debut' => $reservation['heure_debut'],
                        'heure_fin' => $reservation['heure_fin'],
                        'montant' => $reservation['montant'],
                        'statut_paiement' => $reservation['statut_paiement'],
                        'statut_reservation' => $reservation['statut_reservation']
                    ]
                ]);
            } else {
                jsonResponse([
                    'success' => false,
                    'message' => 'Réservation non trouvée.'
                ], 404);
            }
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Action non reconnue.'], 400);
    }

} catch (Exception $e) {
    error_log('Public booking API error: ' . $e->getMessage());
    jsonResponse([
        'success' => false,
        'message' => DEV_MODE ? $e->getMessage() : 'Une erreur est survenue.'
    ], 500);
}
