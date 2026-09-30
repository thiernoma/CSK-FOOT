<?php
/**
 * Modèle Terrain
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';

class Terrain {

    /**
     * Récupérer tous les terrains
     */
    public static function getAll(string $statut = null): array {
        $sql = "SELECT * FROM terrains";
        $params = [];

        if ($statut) {
            $sql .= " WHERE statut = :statut";
            $params['statut'] = $statut;
        }

        $sql .= " ORDER BY ordre_affichage, nom";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Récupérer un terrain par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT * FROM terrains WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Récupérer les terrains parents (ceux qui n'ont pas de parent)
     */
    public static function getParentTerrains(): array {
        return Database::fetchAll(
            "SELECT * FROM terrains WHERE parent_id IS NULL AND statut = 'actif' ORDER BY ordre_affichage, nom"
        );
    }

    /**
     * Récupérer les terrains enfants d'un terrain parent
     */
    public static function getChildTerrains(int $parentId): array {
        return Database::fetchAll(
            "SELECT * FROM terrains WHERE parent_id = :parent_id ORDER BY ordre_affichage, nom",
            ['parent_id' => $parentId]
        );
    }

    /**
     * Récupérer tous les terrains liés (parent + enfants + petits-enfants)
     */
    public static function getRelatedTerrainIds(int $terrainId): array {
        $ids = [$terrainId];
        $terrain = self::getById($terrainId);

        if (!$terrain) {
            return $ids;
        }

        // Récupérer le parent et tous ses ancêtres
        $currentId = $terrain['parent_id'];
        while ($currentId) {
            $ids[] = $currentId;
            $parent = self::getById($currentId);
            $currentId = $parent['parent_id'] ?? null;
        }

        // Récupérer tous les descendants (enfants, petits-enfants, etc.)
        $descendants = self::getAllDescendants($terrainId);
        $ids = array_merge($ids, $descendants);

        return array_unique($ids);
    }

    /**
     * Récupérer tous les descendants d'un terrain (récursif)
     */
    public static function getAllDescendants(int $terrainId): array {
        $descendants = [];
        $children = self::getChildTerrains($terrainId);

        foreach ($children as $child) {
            $descendants[] = $child['id'];
            $childDescendants = self::getAllDescendants($child['id']);
            $descendants = array_merge($descendants, $childDescendants);
        }

        return $descendants;
    }

    /**
     * Créer un terrain
     */
    public static function create(array $data): int {
        return Database::insert('terrains', [
            'nom' => $data['nom'],
            'type' => $data['type'],
            'capacite' => $data['capacite'] ?? null,
            'nb_joueurs_min' => $data['nb_joueurs_min'] ?? null,
            'nb_joueurs_max' => $data['nb_joueurs_max'] ?? null,
            'prix_heure' => $data['prix_heure'],
            'prix_heure_pointe' => $data['prix_heure_pointe'] ?? null,
            'prix_weekend' => $data['prix_weekend'] ?? null,
            'description' => $data['description'] ?? null,
            'equipements' => $data['equipements'] ?? null,
            'statut' => $data['statut'] ?? 'actif',
            'photo' => $data['photo'] ?? null,
            'ordre_affichage' => $data['ordre_affichage'] ?? 0,
            'parent_id' => !empty($data['parent_id']) ? $data['parent_id'] : null
        ]);
    }

    /**
     * Mettre à jour un terrain
     */
    public static function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];

        $allowedFields = ['nom', 'type', 'capacite', 'nb_joueurs_min', 'nb_joueurs_max',
                          'prix_heure', 'prix_heure_pointe', 'prix_weekend', 'description',
                          'equipements', 'statut', 'photo', 'ordre_affichage', 'parent_id',
                          'latitude', 'longitude', 'adresse', 'video_url', 'video_type'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        return Database::update('terrains', $fields, 'id = :id', $params) > 0;
    }

    /**
     * Supprimer un terrain
     */
    public static function delete(int $id): bool {
        // Vérifier s'il y a des réservations liées
        $count = Database::count('reservations', 'terrain_id = :id', ['id' => $id]);
        if ($count > 0) {
            return false;
        }

        return Database::delete('terrains', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Alias pour checkAvailability (compatibilité API)
     */
    public static function checkAvailability(int $terrainId, string $date, string $heureDebut, string $heureFin, ?int $excludeReservationId = null): bool {
        return self::isAvailable($terrainId, $date, $heureDebut, $heureFin, $excludeReservationId);
    }

    /**
     * Vérifier la disponibilité d'un créneau
     * Prend en compte le temps de transition entre réservations
     * Note: La hiérarchie parent/fils est désactivée (colonne parent_id non présente)
     */
    public static function isAvailable(int $terrainId, string $date, string $heureDebut, string $heureFin, ?int $excludeReservationId = null): bool {
        // Période de fermeture exceptionnelle (ex: Magal) : aucun créneau réservable
        if (isDateFermee($date)) {
            return false;
        }

        // Récupérer le temps de transition
        $tempsTransition = (int) getParam('temps_transition', '10');

        $params = [
            'terrain_id' => $terrainId,
            'p_date' => $date,
            'p_heure_fin' => $heureFin,
            'p_heure_debut' => $heureDebut
        ];

        // La condition vérifie si le nouveau créneau chevauche avec les réservations existantes
        // en tenant compte du temps de transition (heure_fin + N minutes)
        $sql = "SELECT COUNT(*) as conflicts FROM reservations
                WHERE terrain_id = :terrain_id
                AND date_reservation = :p_date
                AND statut_reservation NOT IN ('annulee', 'terminee')
                AND (
                    heure_debut < :p_heure_fin AND DATE_ADD(heure_fin, INTERVAL {$tempsTransition} MINUTE) > :p_heure_debut
                )";

        if ($excludeReservationId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeReservationId;
        }

        $result = Database::fetchOne($sql, $params);
        return ($result['conflicts'] ?? 0) == 0;
    }

    /**
     * Vérifier la disponibilité simple (sans hiérarchie) - pour les cas spécifiques
     */
    public static function isAvailableSimple(int $terrainId, string $date, string $heureDebut, string $heureFin, ?int $excludeReservationId = null): bool {
        $sql = "SELECT COUNT(*) as conflicts FROM reservations
                WHERE terrain_id = :terrain_id
                AND date_reservation = :date
                AND statut_reservation NOT IN ('annulee', 'terminee')
                AND (
                    (heure_debut < :heure_fin AND heure_fin > :heure_debut)
                )";

        $params = [
            'terrain_id' => $terrainId,
            'date' => $date,
            'heure_debut' => $heureDebut,
            'heure_fin' => $heureFin
        ];

        if ($excludeReservationId) {
            $sql .= " AND id != :exclude_id";
            $params['exclude_id'] = $excludeReservationId;
        }

        $result = Database::fetchOne($sql, $params);
        return ($result['conflicts'] ?? 0) == 0;
    }

    /**
     * Récupérer les créneaux réservés pour un terrain et une date
     */
    public static function getReservedSlots(int $terrainId, string $date): array {
        return Database::fetchAll(
            "SELECT heure_debut, heure_fin, r.statut_reservation,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             WHERE r.terrain_id = :terrain_id
             AND r.date_reservation = :date
             AND r.statut_reservation NOT IN ('annulee')
             ORDER BY heure_debut",
            ['terrain_id' => $terrainId, 'date' => $date]
        );
    }

    /**
     * Récupérer les créneaux réservés AVEC les marges de transition
     * Retourne les plages horaires complètement bloquées
     */
    public static function getReservedSlotsWithTransition(int $terrainId, string $date): array {
        // Récupérer le temps de transition depuis les paramètres
        $tempsTransition = (int) getParam('temps_transition', '10');

        // Récupérer les réservations de base
        $reservations = Database::fetchAll(
            "SELECT heure_debut, heure_fin, r.statut_reservation,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom
             FROM reservations r
             JOIN clients c ON r.client_id = c.id
             WHERE r.terrain_id = :terrain_id
             AND r.date_reservation = :date
             AND r.statut_reservation NOT IN ('annulee')
             ORDER BY heure_debut",
            ['terrain_id' => $terrainId, 'date' => $date]
        );

        // Ajouter les marges de transition à chaque réservation (supporte op-time >= 24h)
        $slotsWithTransition = [];
        foreach ($reservations as $res) {
            $heureFinAvecTransition = minutesToOpTime(opTimeToMinutes($res['heure_fin']) + $tempsTransition) . ':00';

            $slotsWithTransition[] = [
                'heure_debut' => $res['heure_debut'],
                'heure_fin' => $res['heure_fin'],
                'heure_fin_avec_transition' => $heureFinAvecTransition,
                'statut_reservation' => $res['statut_reservation'],
                'client_nom' => $res['client_nom'],
                'temps_transition' => $tempsTransition
            ];
        }

        return $slotsWithTransition;
    }

    /**
     * Obtenir le prochain créneau disponible après une heure donnée
     */
    public static function getNextAvailableSlot(int $terrainId, string $date, string $heureDebut): ?string {
        $tempsTransition = (int) getParam('temps_transition', '10');

        // Trouver la réservation qui bloque cette heure
        $reservation = Database::fetchOne(
            "SELECT heure_fin FROM reservations
             WHERE terrain_id = :terrain_id
             AND date_reservation = :date
             AND heure_debut <= :heure AND heure_fin > :heure
             AND statut_reservation NOT IN ('annulee')
             ORDER BY heure_fin DESC
             LIMIT 1",
            ['terrain_id' => $terrainId, 'date' => $date, 'heure' => $heureDebut]
        );

        if ($reservation) {
            // Retourner l'heure de fin + temps de transition (en op-time)
            return minutesToOpTime(opTimeToMinutes($reservation['heure_fin']) + $tempsTransition);
        }

        return null; // Créneau libre
    }

    /**
     * Calculer le prix selon l'heure et le jour
     * Utilise les paramètres "heure_pointe_debut/fin", "jours_pointe", "jours_weekend"
     */
    public static function calculatePrice(int $terrainId, string $date, string $heureDebut, string $heureFin): float {
        $terrain = self::getById($terrainId);
        if (!$terrain) {
            return 0;
        }

        // Normaliser en op-time (au cas où l'appelant fournit du wall-clock après-minuit)
        $heureDebut = wallToOp($heureDebut);
        $heureFin   = wallToOp($heureFin);

        $dayOfWeek = (int)date('N', strtotime($date)); // 1=Lundi, 7=Dimanche
        $startHour = (int)substr($heureDebut, 0, 2);
        $startMinutes = opTimeToMinutes($heureDebut);

        // Modèle tarifaire :
        //  - prix_heure_pointe = TARIF NORMAL (prix par défaut)
        //  - prix_heure        = TARIF MATINAL (réduction, appliqué avant l'heure de coupure)
        //  - prix_weekend      = tarif week-end (prioritaire les jours de week-end)
        // L'heure de coupure (début du tarif normal / fin du matinal) = paramètre "heure_pointe_debut".
        $joursWeekend = array_filter(array_map('intval', explode(',', getParam('jours_weekend', '6,7'))));

        $heureCoupure = getParam('heure_pointe_debut', '16:00');
        $coupureMin = (int)substr($heureCoupure, 0, 2) * 60 + (int)substr($heureCoupure, 3, 2);

        $prixMatinal = (float)$terrain['prix_heure'];
        $prixNormal  = !empty($terrain['prix_heure_pointe']) ? (float)$terrain['prix_heure_pointe'] : $prixMatinal;

        // Tarif normal par défaut
        $tarifHoraire = $prixNormal;

        if (in_array($dayOfWeek, $joursWeekend, true) && !empty($terrain['prix_weekend'])) {
            // Week-end (prioritaire)
            $tarifHoraire = (float)$terrain['prix_weekend'];
        } elseif ($startMinutes < $coupureMin) {
            // Matinal (avant l'heure de coupure)
            $tarifHoraire = $prixMatinal;
        }

        // Calculer la durée (supporte op-time >= 24h)
        $dureeHeures = opDurationHours($heureDebut, $heureFin);

        return $tarifHoraire * $dureeHeures;
    }

    /**
     * Statistiques d'un terrain
     */
    public static function getStats(int $terrainId, string $periode = 'mois'): array {
        $dateCondition = match($periode) {
            'jour' => "DATE(r.created_at) = CURDATE()",
            'semaine' => "r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'mois' => "MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())",
            'annee' => "YEAR(r.created_at) = YEAR(CURDATE())",
            default => "1=1"
        };

        return Database::fetchOne(
            "SELECT
                COUNT(*) as nb_reservations,
                COALESCE(SUM(r.duree_heures), 0) as heures_totales,
                COALESCE(SUM(r.montant_paye), 0) as ca_total,
                COALESCE(AVG(r.montant), 0) as panier_moyen
             FROM reservations r
             WHERE r.terrain_id = :terrain_id
             AND r.statut_reservation != 'annulee'
             AND {$dateCondition}",
            ['terrain_id' => $terrainId]
        );
    }

    /**
     * Taux d'occupation d'un terrain
     */
    public static function getOccupancyRate(int $terrainId, string $date): float {
        // Heures d'ouverture en op-time (peut dépasser 24h si fermeture après minuit)
        $heuresOuverture = CLOSING_HOUR_OP - OPENING_HOUR_OP;
        if ($heuresOuverture <= 0) $heuresOuverture = 1; // garde-fou

        $result = Database::fetchOne(
            "SELECT COALESCE(SUM(duree_heures), 0) as heures_reservees
             FROM reservations
             WHERE terrain_id = :terrain_id
             AND date_reservation = :date
             AND statut_reservation NOT IN ('annulee')",
            ['terrain_id' => $terrainId, 'date' => $date]
        );

        $heuresReservees = $result['heures_reservees'] ?? 0;
        return ($heuresReservees / $heuresOuverture) * 100;
    }
}
