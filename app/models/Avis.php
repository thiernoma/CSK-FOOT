<?php
/**
 * Modèle Avis
 * Gestion des avis et notes des terrains
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';

class Avis {

    /**
     * Récupérer tous les avis avec filtres
     */
    public static function getAll(array $filters = []): array {
        $sql = "SELECT a.*, t.nom as terrain_nom,
                       CONCAT(c.prenom, ' ', c.nom) as client_nom_complet,
                       r.numero_ticket
                FROM avis_terrains a
                JOIN terrains t ON a.terrain_id = t.id
                LEFT JOIN clients c ON a.client_id = c.id
                LEFT JOIN reservations r ON a.reservation_id = r.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['terrain_id'])) {
            $sql .= " AND a.terrain_id = :terrain_id";
            $params['terrain_id'] = $filters['terrain_id'];
        }

        if (!empty($filters['statut'])) {
            $sql .= " AND a.statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['note_min'])) {
            $sql .= " AND a.note >= :note_min";
            $params['note_min'] = $filters['note_min'];
        }

        $sql .= " ORDER BY a.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Récupérer un avis par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT a.*, t.nom as terrain_nom,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom_complet
             FROM avis_terrains a
             JOIN terrains t ON a.terrain_id = t.id
             LEFT JOIN clients c ON a.client_id = c.id
             WHERE a.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Créer un avis
     */
    public static function create(array $data): int {
        return Database::insert('avis_terrains', [
            'terrain_id' => $data['terrain_id'],
            'client_id' => $data['client_id'] ?? null,
            'reservation_id' => $data['reservation_id'] ?? null,
            'nom_client' => $data['nom_client'] ?? null,
            'email_client' => $data['email_client'] ?? null,
            'note' => $data['note'],
            'commentaire' => $data['commentaire'] ?? null,
            'qualite_terrain' => $data['qualite_terrain'] ?? null,
            'proprete' => $data['proprete'] ?? null,
            'eclairage' => $data['eclairage'] ?? null,
            'accueil' => $data['accueil'] ?? null,
            'rapport_qualite_prix' => $data['rapport_qualite_prix'] ?? null,
            'recommande' => $data['recommande'] ?? 1,
            'statut' => $data['statut'] ?? 'en_attente',
            'ip_address' => $data['ip_address'] ?? null
        ]);
    }

    /**
     * Mettre à jour un avis (modération)
     */
    public static function update(int $id, array $data): bool {
        $fields = [];
        $allowedFields = ['statut', 'reponse_admin'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (isset($data['reponse_admin'])) {
            $fields['reponse_date'] = date('Y-m-d H:i:s');
        }

        if (empty($fields)) {
            return false;
        }

        return Database::update('avis_terrains', $fields, 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Supprimer un avis
     */
    public static function delete(int $id): bool {
        return Database::delete('avis_terrains', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Statistiques des avis pour un terrain
     */
    public static function getStatsTerrain(int $terrainId): array {
        $stats = Database::fetchOne(
            "SELECT
                COUNT(*) as nb_avis,
                ROUND(AVG(note), 1) as note_moyenne,
                ROUND(AVG(qualite_terrain), 1) as moy_qualite,
                ROUND(AVG(proprete), 1) as moy_proprete,
                ROUND(AVG(eclairage), 1) as moy_eclairage,
                ROUND(AVG(accueil), 1) as moy_accueil,
                ROUND(AVG(rapport_qualite_prix), 1) as moy_rapport_prix,
                SUM(CASE WHEN recommande = 1 THEN 1 ELSE 0 END) as nb_recommandations
             FROM avis_terrains
             WHERE terrain_id = :terrain_id AND statut = 'approuve'",
            ['terrain_id' => $terrainId]
        );

        // Distribution des notes
        $distribution = Database::fetchAll(
            "SELECT note, COUNT(*) as count
             FROM avis_terrains
             WHERE terrain_id = :terrain_id AND statut = 'approuve'
             GROUP BY note
             ORDER BY note DESC",
            ['terrain_id' => $terrainId]
        );

        $stats['distribution'] = [];
        for ($i = 5; $i >= 1; $i--) {
            $found = false;
            foreach ($distribution as $d) {
                if ($d['note'] == $i) {
                    $stats['distribution'][$i] = $d['count'];
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $stats['distribution'][$i] = 0;
            }
        }

        return $stats;
    }

    /**
     * Statistiques globales des avis
     */
    public static function getStatsGlobal(): array {
        return Database::fetchOne(
            "SELECT
                COUNT(*) as total_avis,
                SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                SUM(CASE WHEN statut = 'approuve' THEN 1 ELSE 0 END) as approuves,
                SUM(CASE WHEN statut = 'rejete' THEN 1 ELSE 0 END) as rejetes,
                ROUND(AVG(CASE WHEN statut = 'approuve' THEN note ELSE NULL END), 1) as note_moyenne
             FROM avis_terrains"
        );
    }

    /**
     * Avis récents pour le dashboard
     */
    public static function getRecents(int $limit = 5): array {
        // LIMIT en entier littéral : en prepared natif, un LIMIT lié part comme chaîne -> erreur SQL
        $limit = max(1, (int)$limit);
        return Database::fetchAll(
            "SELECT a.*, t.nom as terrain_nom
             FROM avis_terrains a
             JOIN terrains t ON a.terrain_id = t.id
             ORDER BY a.created_at DESC
             LIMIT $limit"
        );
    }

    /**
     * Classement des terrains par note
     */
    public static function getClassementTerrains(): array {
        return Database::fetchAll(
            "SELECT t.id, t.nom, t.photo,
                    COUNT(a.id) as nb_avis,
                    ROUND(AVG(a.note), 1) as note_moyenne,
                    SUM(CASE WHEN a.recommande = 1 THEN 1 ELSE 0 END) as nb_recommandations
             FROM terrains t
             LEFT JOIN avis_terrains a ON t.id = a.terrain_id AND a.statut = 'approuve'
             WHERE t.statut = 'actif'
             GROUP BY t.id, t.nom, t.photo
             ORDER BY note_moyenne DESC, nb_avis DESC"
        );
    }

    /**
     * Vérifier si un client peut laisser un avis (1 avis par réservation)
     */
    public static function canReview(int $reservationId): bool {
        $count = Database::count(
            'avis_terrains',
            'reservation_id = :reservation_id',
            ['reservation_id' => $reservationId]
        );
        return $count === 0;
    }
}
