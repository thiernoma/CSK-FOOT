<?php
/**
 * Modèle MessageContact
 * Gestion des messages du formulaire de contact
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';

class MessageContact {

    /**
     * Récupérer tous les messages avec filtres
     */
    public static function getAll(array $filters = [], int $limit = 0, int $offset = 0): array {
        $sql = "SELECT m.*,
                       u.nom as repondu_par_nom, u.prenom as repondu_par_prenom
                FROM messages_contact m
                LEFT JOIN users u ON m.repondu_par = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['statut'])) {
            $sql .= " AND m.statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['sujet'])) {
            $sql .= " AND m.sujet = :sujet";
            $params['sujet'] = $filters['sujet'];
        }

        if (!empty($filters['recherche'])) {
            $sql .= " AND (m.nom LIKE :recherche OR m.telephone LIKE :recherche2 OR m.email LIKE :recherche3 OR m.message LIKE :recherche4)";
            $params['recherche'] = '%' . $filters['recherche'] . '%';
            $params['recherche2'] = '%' . $filters['recherche'] . '%';
            $params['recherche3'] = '%' . $filters['recherche'] . '%';
            $params['recherche4'] = '%' . $filters['recherche'] . '%';
        }

        if (!empty($filters['date_debut'])) {
            $sql .= " AND DATE(m.created_at) >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $sql .= " AND DATE(m.created_at) <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }

        $sql .= " ORDER BY m.created_at DESC";

        if ($limit > 0) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Compter les messages
     */
    public static function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM messages_contact WHERE 1=1";
        $params = [];

        if (!empty($filters['statut'])) {
            $sql .= " AND statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['sujet'])) {
            $sql .= " AND sujet = :sujet";
            $params['sujet'] = $filters['sujet'];
        }

        if (!empty($filters['recherche'])) {
            $sql .= " AND (nom LIKE :recherche OR telephone LIKE :recherche2 OR email LIKE :recherche3)";
            $params['recherche'] = '%' . $filters['recherche'] . '%';
            $params['recherche2'] = '%' . $filters['recherche'] . '%';
            $params['recherche3'] = '%' . $filters['recherche'] . '%';
        }

        $result = Database::fetchOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Compter les messages non lus
     */
    public static function countUnread(): int {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as total FROM messages_contact WHERE statut = 'nouveau'"
        );
        return (int)($result['total'] ?? 0);
    }

    /**
     * Récupérer un message par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT m.*,
                    u.nom as repondu_par_nom, u.prenom as repondu_par_prenom
             FROM messages_contact m
             LEFT JOIN users u ON m.repondu_par = u.id
             WHERE m.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Marquer comme lu
     */
    public static function markAsRead(int $id): bool {
        return Database::update(
            'messages_contact',
            ['statut' => 'lu'],
            'id = :id AND statut = :old_statut',
            ['id' => $id, 'old_statut' => 'nouveau']
        ) >= 0;
    }

    /**
     * Marquer comme traité avec réponse
     */
    public static function markAsReplied(int $id, string $reponse, int $userId): bool {
        return Database::update(
            'messages_contact',
            [
                'statut' => 'traite',
                'reponse' => $reponse,
                'repondu_par' => $userId,
                'repondu_le' => date('Y-m-d H:i:s')
            ],
            'id = :id',
            ['id' => $id]
        ) > 0;
    }

    /**
     * Archiver un message
     */
    public static function archive(int $id): bool {
        return Database::update(
            'messages_contact',
            ['statut' => 'archive'],
            'id = :id',
            ['id' => $id]
        ) > 0;
    }

    /**
     * Supprimer un message
     */
    public static function delete(int $id): bool {
        return Database::delete('messages_contact', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Statistiques des messages
     */
    public static function getStats(): array {
        return Database::fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'nouveau' THEN 1 ELSE 0 END) as nouveaux,
                SUM(CASE WHEN statut = 'lu' THEN 1 ELSE 0 END) as lus,
                SUM(CASE WHEN statut = 'traite' THEN 1 ELSE 0 END) as traites,
                SUM(CASE WHEN statut = 'archive' THEN 1 ELSE 0 END) as archives,
                SUM(CASE WHEN sujet = 'reservation' THEN 1 ELSE 0 END) as sujet_reservation,
                SUM(CASE WHEN sujet = 'academie' THEN 1 ELSE 0 END) as sujet_academie,
                SUM(CASE WHEN sujet = 'partenariat' THEN 1 ELSE 0 END) as sujet_partenariat,
                SUM(CASE WHEN sujet = 'autre' THEN 1 ELSE 0 END) as sujet_autre
             FROM messages_contact"
        ) ?: [
            'total' => 0, 'nouveaux' => 0, 'lus' => 0, 'traites' => 0, 'archives' => 0,
            'sujet_reservation' => 0, 'sujet_academie' => 0, 'sujet_partenariat' => 0, 'sujet_autre' => 0
        ];
    }

    /**
     * Messages récents (pour dashboard)
     */
    public static function getRecent(int $limit = 5): array {
        return Database::fetchAll(
            "SELECT * FROM messages_contact
             WHERE statut IN ('nouveau', 'lu')
             ORDER BY created_at DESC
             LIMIT {$limit}"
        );
    }
}
