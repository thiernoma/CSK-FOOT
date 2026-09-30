<?php
/**
 * Modèle Notification - Gestion des notifications in-app
 * Complexe Sportif Kaira
 */

class Notification
{
    /**
     * Créer une notification
     */
    public static function create(array $data): int
    {
        return Database::insert('notifications', [
            'user_id' => $data['user_id'] ?? null,
            'titre' => $data['titre'],
            'message' => $data['message'],
            'type' => $data['type'] ?? 'info',
            'icone' => $data['icone'] ?? 'bell',
            'lien' => $data['lien'] ?? null,
            'reference_type' => $data['reference_type'] ?? null,
            'reference_id' => $data['reference_id'] ?? null
        ]);
    }

    /**
     * Créer une notification pour tous les utilisateurs d'un rôle
     */
    public static function notifyRole(string $role, array $data): void
    {
        $users = Database::fetchAll(
            "SELECT u.id FROM users u
             JOIN roles r ON u.role_id = r.id
             WHERE r.slug = :role AND u.statut = 'actif'",
            ['role' => $role]
        );

        foreach ($users as $user) {
            $data['user_id'] = $user['id'];
            self::create($data);
        }
    }

    /**
     * Créer une notification pour tous les admins
     */
    public static function notifyAdmins(array $data): void
    {
        $admins = Database::fetchAll(
            "SELECT id FROM users WHERE role_id IN (1, 2) AND statut = 'actif'"
        );

        foreach ($admins as $admin) {
            $data['user_id'] = $admin['id'];
            self::create($data);
        }
    }

    /**
     * Récupérer les notifications d'un utilisateur
     */
    public static function getForUser(int $userId, int $limit = 20, bool $unreadOnly = false): array
    {
        $where = "(user_id = :user_id OR user_id IS NULL)";
        if ($unreadOnly) {
            $where .= " AND lu = 0";
        }

        return Database::fetchAll(
            "SELECT * FROM notifications
             WHERE $where
             ORDER BY created_at DESC
             LIMIT $limit",
            ['user_id' => $userId]
        );
    }

    /**
     * Compter les notifications non lues
     */
    public static function countUnread(int $userId): int
    {
        $result = Database::fetchOne(
            "SELECT COUNT(*) as total FROM notifications
             WHERE (user_id = :user_id OR user_id IS NULL) AND lu = 0",
            ['user_id' => $userId]
        );
        return (int)($result['total'] ?? 0);
    }

    /**
     * Marquer une notification comme lue
     */
    public static function markAsRead(int $id, int $userId): bool
    {
        return Database::update('notifications', [
            'lu' => 1,
            'lu_at' => date('Y-m-d H:i:s')
        ], 'id = :id AND (user_id = :user_id OR user_id IS NULL)', [
            'id' => $id,
            'user_id' => $userId
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public static function markAllAsRead(int $userId): bool
    {
        return Database::query(
            "UPDATE notifications SET lu = 1, lu_at = NOW()
             WHERE (user_id = :user_id OR user_id IS NULL) AND lu = 0",
            ['user_id' => $userId]
        )->rowCount() > 0;
    }

    /**
     * Supprimer une notification
     */
    public static function delete(int $id): bool
    {
        return Database::delete('notifications', 'id = :id', ['id' => $id]);
    }

    /**
     * Supprimer les anciennes notifications
     */
    public static function purgeOld(int $days = 30): int
    {
        $result = Database::query(
            "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            ['days' => $days]
        );
        return $result->rowCount();
    }

    // ========== NOTIFICATIONS PRÉDÉFINIES ==========

    /**
     * Notification nouvelle réservation
     */
    public static function notifyNewReservation(array $reservation): void
    {
        self::notifyAdmins([
            'titre' => 'Nouvelle réservation',
            'message' => sprintf(
                '%s a réservé %s le %s de %s à %s',
                $reservation['client_nom'] ?? 'Un client',
                $reservation['terrain_nom'] ?? 'un terrain',
                formatDate($reservation['date_reservation'], 'd/m/Y'),
                formatTimeFull($reservation['heure_debut']),
                formatTimeFull($reservation['heure_fin'])
            ),
            'type' => 'success',
            'icone' => 'calendar-check',
            'lien' => ADMIN_URL . '/reservations/voir.php?id=' . $reservation['id'],
            'reference_type' => 'reservation',
            'reference_id' => $reservation['id']
        ]);
    }

    /**
     * Notification réservation annulée
     */
    public static function notifyReservationCancelled(array $reservation): void
    {
        self::notifyAdmins([
            'titre' => 'Réservation annulée',
            'message' => sprintf(
                'Réservation #%s annulée (%s le %s)',
                $reservation['numero_ticket'] ?? $reservation['id'],
                $reservation['terrain_nom'] ?? '',
                formatDate($reservation['date_reservation'], 'd/m/Y')
            ),
            'type' => 'warning',
            'icone' => 'calendar-times',
            'lien' => ADMIN_URL . '/reservations/voir.php?id=' . $reservation['id'],
            'reference_type' => 'reservation',
            'reference_id' => $reservation['id']
        ]);
    }

    /**
     * Notification paiement reçu
     */
    public static function notifyPaymentReceived(array $paiement): void
    {
        self::notifyAdmins([
            'titre' => 'Paiement reçu',
            'message' => sprintf(
                '%s reçu de %s',
                formatMoney($paiement['montant']),
                $paiement['client_nom'] ?? 'Un client'
            ),
            'type' => 'success',
            'icone' => 'money-bill-wave',
            'lien' => ADMIN_URL . '/paiements/index.php',
            'reference_type' => 'paiement',
            'reference_id' => $paiement['id']
        ]);
    }

    /**
     * Notification cotisation en retard
     */
    public static function notifyCotisationEnRetard(array $cotisation, array $membre): void
    {
        self::notifyAdmins([
            'titre' => 'Cotisation en retard',
            'message' => sprintf(
                '%s %s - Cotisation %s en retard',
                $membre['prenom'],
                $membre['nom'],
                $cotisation['periode'] ?? ''
            ),
            'type' => 'danger',
            'icone' => 'exclamation-triangle',
            'lien' => ADMIN_URL . '/academie/cotisations.php',
            'reference_type' => 'cotisation',
            'reference_id' => $cotisation['id']
        ]);
    }

    /**
     * Notification nouveau membre inscrit
     */
    public static function notifyNewMember(array $membre): void
    {
        self::notifyAdmins([
            'titre' => 'Nouveau membre inscrit',
            'message' => sprintf(
                '%s %s inscrit en catégorie %s',
                $membre['prenom'],
                $membre['nom'],
                $membre['categorie'] ?? ''
            ),
            'type' => 'info',
            'icone' => 'user-plus',
            'lien' => ADMIN_URL . '/academie/voir.php?id=' . $membre['id'],
            'reference_type' => 'membre',
            'reference_id' => $membre['id']
        ]);
    }

    /**
     * Notification alerte stock/maintenance
     */
    public static function notifyAlert(string $titre, string $message, string $lien = null): void
    {
        self::notifyAdmins([
            'titre' => $titre,
            'message' => $message,
            'type' => 'warning',
            'icone' => 'exclamation-circle',
            'lien' => $lien
        ]);
    }
}
