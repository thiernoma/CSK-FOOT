<?php
/**
 * Modèle User
 * Gestion des utilisateurs du système
 * Complexe Sportif Kaira
 */

class User
{
    /**
     * Récupère tous les utilisateurs avec pagination
     */
    public static function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['role_id'])) {
            $where[] = 'u.role_id = :role_id';
            $params['role_id'] = $filters['role_id'];
        }

        if (!empty($filters['statut'])) {
            $where[] = 'u.statut = :statut';
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['search'])) {
            // Placeholders distincts (prepared statements natifs : pas de réutilisation possible)
            $where[] = '(u.nom LIKE :search1 OR u.email LIKE :search2)';
            $like = '%' . $filters['search'] . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM users u WHERE $whereClause";
        $total = Database::fetchOne($countSql, $params)['total'];

        // Get data
        $sql = "SELECT u.*, r.nom as role_nom, r.permissions
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE $whereClause
                ORDER BY u.created_at DESC
                LIMIT $perPage OFFSET $offset";

        $data = Database::fetchAll($sql, $params);

        return [
            'data' => $data,
            'total' => $total,
            'pages' => ceil($total / $perPage),
            'current_page' => $page
        ];
    }

    /**
     * Récupère un utilisateur par son ID
     */
    public static function getById($id)
    {
        $sql = "SELECT u.*, r.nom as role_nom, r.permissions
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.id = :id";

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Récupère un utilisateur par son email
     */
    public static function getByEmail($email)
    {
        $sql = "SELECT u.*, r.nom as role_nom, r.permissions
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.email = :email";

        return Database::fetchOne($sql, ['email' => $email]);
    }

    /**
     * Crée un nouvel utilisateur
     */
    public static function create($data)
    {
        // Vérifier si l'email existe déjà
        $existing = self::getByEmail($data['email']);
        if ($existing) {
            throw new Exception('Cet email est déjà utilisé.');
        }

        // Hasher le mot de passe
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        return Database::insert('users', [
            'nom' => $data['nom'],
            'email' => $data['email'],
            'password_hash' => $passwordHash,
            'role_id' => $data['role_id'],
            'telephone' => $data['telephone'] ?? null,
            'statut' => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour un utilisateur
     */
    public static function update($id, $data)
    {
        // Vérifier si l'email existe déjà (autre utilisateur)
        $existing = self::getByEmail($data['email']);
        if ($existing && $existing['id'] != $id) {
            throw new Exception('Cet email est déjà utilisé par un autre utilisateur.');
        }

        $updateData = [
            'nom' => $data['nom'],
            'email' => $data['email'],
            'role_id' => $data['role_id'],
            'telephone' => $data['telephone'] ?? null,
            'statut' => $data['statut']
        ];

        // Mettre à jour le mot de passe si fourni
        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        return Database::update('users', $updateData, 'id = :id', ['id' => $id]);
    }

    /**
     * Change le mot de passe
     */
    public static function changePassword($id, $newPassword)
    {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        return Database::update('users', ['password_hash' => $hash], 'id = :id', ['id' => $id]);
    }

    /**
     * Active/Désactive un utilisateur
     */
    public static function toggleStatus($id)
    {
        $user = self::getById($id);
        if (!$user) {
            return false;
        }

        $newStatus = $user['statut'] === 'actif' ? 'inactif' : 'actif';
        return Database::update('users', ['statut' => $newStatus], 'id = :id', ['id' => $id]);
    }

    /**
     * Récupère tous les rôles
     */
    public static function getRoles()
    {
        return Database::fetchAll("SELECT * FROM roles ORDER BY id");
    }

    /**
     * Met à jour la dernière connexion
     */
    public static function updateLastLogin($id)
    {
        return Database::update('users',
            ['derniere_connexion' => date('Y-m-d H:i:s')],
            'id = :id',
            ['id' => $id]
        );
    }

    /**
     * Statistiques des utilisateurs
     */
    public static function getStats()
    {
        return Database::fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'actif' THEN 1 ELSE 0 END) as actifs,
                SUM(CASE WHEN statut = 'inactif' THEN 1 ELSE 0 END) as inactifs,
                SUM(CASE WHEN derniere_connexion >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as connectes_24h
             FROM users
             WHERE statut != 'supprime'"
        );
    }

    /**
     * Supprime un utilisateur (soft delete)
     */
    public static function delete($id)
    {
        // Ne pas supprimer le super admin
        $user = self::getById($id);
        if ($user && $user['role_id'] == 1) {
            throw new Exception('Impossible de supprimer le super administrateur.');
        }

        return Database::update('users', ['statut' => 'supprime'], 'id = :id', ['id' => $id]);
    }
}
