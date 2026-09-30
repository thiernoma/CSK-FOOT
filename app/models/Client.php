<?php
/**
 * Modèle Client
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';

class Client {

    /**
     * Récupérer tous les clients
     */
    public static function getAll(array $filters = [], int $limit = 0, int $offset = 0): array {
        $sql = "SELECT * FROM clients WHERE 1=1";
        $params = [];

        if (!empty($filters['statut'])) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['type_client'])) {
            $sql .= " AND type_client = :type_client";
            $params['type_client'] = $filters['type_client'];
        }

        if (!empty($filters['recherche'])) {
            // Placeholders distincts : en prepared statements natifs (EMULATE_PREPARES=false),
            // MySQL n'autorise pas la réutilisation d'un même paramètre nommé.
            $sql .= " AND (nom LIKE :rech1 OR prenom LIKE :rech2 OR telephone LIKE :rech3 OR email LIKE :rech4)";
            $like = '%' . $filters['recherche'] . '%';
            $params['rech1'] = $like;
            $params['rech2'] = $like;
            $params['rech3'] = $like;
            $params['rech4'] = $like;
        }

        $sql .= " ORDER BY nom, prenom";

        if ($limit > 0) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Compter les clients
     */
    public static function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM clients WHERE 1=1";
        $params = [];

        if (!empty($filters['statut'])) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['recherche'])) {
            $sql .= " AND (nom LIKE :rech1 OR prenom LIKE :rech2 OR telephone LIKE :rech3)";
            $like = '%' . $filters['recherche'] . '%';
            $params['rech1'] = $like;
            $params['rech2'] = $like;
            $params['rech3'] = $like;
        }

        $result = Database::fetchOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Récupérer un client par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT * FROM clients WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Récupérer un client par téléphone
     */
    public static function getByPhone(string $phone): ?array {
        return Database::fetchOne(
            "SELECT * FROM clients WHERE telephone = :phone1 OR telephone_alt = :phone2",
            ['phone1' => $phone, 'phone2' => $phone]
        );
    }

    /**
     * Rechercher des clients (autocomplétion)
     */
    public static function search(string $query, int $limit = 10): array {
        $limit = (int)$limit; // S'assurer que c'est un entier
        return Database::fetchAll(
            "SELECT id, nom, prenom, telephone, email
             FROM clients
             WHERE statut = 'actif'
             AND (nom LIKE :query1
             OR prenom LIKE :query2
             OR telephone LIKE :query3
             OR CONCAT(prenom, ' ', nom) LIKE :query4)
             ORDER BY nb_reservations DESC, nom
             LIMIT {$limit}",
            [
                'query1' => '%' . $query . '%',
                'query2' => '%' . $query . '%',
                'query3' => '%' . $query . '%',
                'query4' => '%' . $query . '%'
            ]
        );
    }

    /**
     * Créer un client
     */
    public static function create(array $data): int {
        return Database::insert('clients', [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'] ?? null,
            'telephone' => $data['telephone'],
            'telephone_alt' => $data['telephone_alt'] ?? null,
            'email' => $data['email'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'type_client' => $data['type_client'] ?? 'particulier',
            'notes' => $data['notes'] ?? null,
            'statut' => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Mettre à jour un client
     */
    public static function update(int $id, array $data): bool {
        $fields = [];
        $allowedFields = ['nom', 'prenom', 'telephone', 'telephone_alt', 'email',
                          'adresse', 'type_client', 'notes', 'statut', 'points_fidelite'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        return Database::update('clients', $fields, 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Supprimer un client
     */
    public static function delete(int $id): bool {
        // Vérifier s'il a des réservations
        $count = Database::count('reservations', 'client_id = :id', ['id' => $id]);
        if ($count > 0) {
            return false;
        }

        return Database::delete('clients', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Historique des réservations d'un client
     */
    public static function getReservations(int $clientId, int $limit = 0): array {
        $sql = "SELECT r.*, t.nom as terrain_nom, t.type as terrain_type
                FROM reservations r
                JOIN terrains t ON r.terrain_id = t.id
                WHERE r.client_id = :client_id
                ORDER BY r.date_reservation DESC, r.heure_debut DESC";

        if ($limit > 0) {
            $sql .= " LIMIT {$limit}";
        }

        return Database::fetchAll($sql, ['client_id' => $clientId]);
    }

    /**
     * Statistiques d'un client
     */
    public static function getStats(int $clientId): array {
        return Database::fetchOne(
            "SELECT
                COUNT(*) as nb_reservations,
                COALESCE(SUM(montant_paye), 0) as montant_total,
                COALESCE(AVG(montant), 0) as panier_moyen,
                MAX(date_reservation) as derniere_visite
             FROM reservations
             WHERE client_id = :client_id
             AND statut_reservation != 'annulee'",
            ['client_id' => $clientId]
        );
    }

    /**
     * Mettre à jour les statistiques du client
     */
    public static function updateStats(int $clientId): void {
        $stats = self::getStats($clientId);

        Database::update('clients', [
            'nb_reservations' => $stats['nb_reservations'],
            'montant_total' => $stats['montant_total']
        ], 'id = :id', ['id' => $clientId]);
    }

    /**
     * Vérifier si le client a droit à une récompense fidélité
     */
    public static function checkLoyaltyReward(int $clientId): bool {
        $client = self::getById($clientId);
        if (!$client) return false;

        $rewardThreshold = 10; // Configurable via parametres
        return ($client['nb_reservations'] % $rewardThreshold) === 0;
    }

    /**
     * Top clients par CA
     */
    public static function getTopClients(int $limit = 10, string $periode = 'annee'): array {
        $dateCondition = match($periode) {
            'mois' => "AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)",
            'trimestre' => "AND r.created_at >= DATE_SUB(CURDATE(), INTERVAL 3 MONTH)",
            'annee' => "AND YEAR(r.created_at) = YEAR(CURDATE())",
            default => ""
        };

        return Database::fetchAll(
            "SELECT c.*, COUNT(r.id) as nb_reservations_periode, SUM(r.montant_paye) as ca_periode
             FROM clients c
             LEFT JOIN reservations r ON c.id = r.client_id AND r.statut_reservation != 'annulee' {$dateCondition}
             GROUP BY c.id
             ORDER BY ca_periode DESC
             LIMIT {$limit}"
        );
    }
}
