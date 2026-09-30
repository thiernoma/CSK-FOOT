<?php
/**
 * Gestionnaire d'authentification
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Session.php';

class Auth {

    /**
     * Tenter une connexion
     */
    public static function attempt(string $email, string $password): array {
        Session::start();

        // Vérifier si l'IP est bloquée
        if (self::isIPBlocked()) {
            return [
                'success' => false,
                'message' => 'Trop de tentatives. Veuillez réessayer dans quelques minutes.'
            ];
        }

        // Rechercher l'utilisateur
        $sql = "SELECT u.*, r.nom as role_nom, r.id as role_niveau, r.permissions
                FROM users u
                JOIN roles r ON u.role_id = r.id
                WHERE u.email = :email";

        $user = Database::fetchOne($sql, ['email' => $email]);

        if (!$user) {
            self::logFailedAttempt($email);
            return [
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.'
            ];
        }

        // Vérifier le statut du compte
        if ($user['statut'] !== 'actif') {
            return [
                'success' => false,
                'message' => 'Votre compte est désactivé. Contactez l\'administrateur.'
            ];
        }

        // Vérifier le mot de passe
        if (!password_verify($password, $user['password_hash'])) {
            self::logFailedAttempt($email, $user['id']);
            return [
                'success' => false,
                'message' => 'Email ou mot de passe incorrect.'
            ];
        }

        // Connexion réussie
        Database::update('users', [
            'derniere_connexion' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $user['id']]);

        // Créer la session
        Session::setUser($user);

        // Log de connexion
        self::logAction($user['id'], 'login', 'users', $user['id']);

        return [
            'success' => true,
            'message' => 'Connexion réussie.',
            'user' => [
                'id' => $user['id'],
                'nom' => $user['nom'],
                'prenom' => $user['prenom'],
                'role' => $user['role_nom']
            ]
        ];
    }

    /**
     * Déconnexion
     */
    public static function logout(): void {
        if (Session::isLoggedIn()) {
            self::logAction(Session::getUserId(), 'logout', 'users', Session::getUserId());
        }
        Session::destroy();
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public static function check(): bool {
        return Session::isLoggedIn();
    }

    /**
     * Obtenir l'utilisateur connecté
     */
    public static function user(): ?array {
        if (!self::check()) {
            return null;
        }
        return Session::getUser();
    }

    /**
     * Obtenir l'ID de l'utilisateur connecté
     */
    public static function id(): ?int {
        return Session::getUserId();
    }

    /**
     * Vérifier un rôle
     */
    public static function hasRole(string $role): bool {
        return Session::hasRole($role);
    }

    /**
     * Vérifier une permission
     */
    public static function can(string $permission): bool {
        return Session::hasPermission($permission);
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public static function isAdmin(): bool {
        return self::hasRole('super_admin') || self::hasRole('directeur');
    }

    /**
     * Exiger une connexion (redirection si non connecté)
     */
    public static function requireLogin(): void {
        if (!self::check()) {
            Session::flash('warning', 'Veuillez vous connecter pour accéder à cette page.');
            header('Location: ' . APP_URL . '/gestion/login');
            exit;
        }
    }

    /**
     * Exiger un rôle spécifique
     */
    public static function requireRole(string ...$roles): void {
        self::requireLogin();

        $userRole = Session::getUserRole();
        if (!in_array($userRole, $roles)) {
            Session::flash('danger', 'Vous n\'avez pas les droits pour accéder à cette page.');
            header('Location: ' . APP_URL . '/gestion/');
            exit;
        }
    }

    /**
     * Exiger une permission
     */
    public static function requirePermission(string $permission): void {
        self::requireLogin();

        if (!self::can($permission)) {
            Session::flash('danger', 'Permission refusée.');
            header('Location: ' . APP_URL . '/gestion/');
            exit;
        }
    }

    /**
     * Exiger un accès administrateur (super_admin ou directeur)
     */
    public static function requireAdmin(): void {
        self::requireLogin();

        if (!self::isAdmin()) {
            Session::flash('danger', 'Accès réservé aux administrateurs.');
            header('Location: ' . APP_URL . '/gestion/');
            exit;
        }
    }

    /**
     * Changer le mot de passe
     */
    public static function changePassword(int $userId, string $currentPassword, string $newPassword): array {
        $sql = "SELECT password_hash FROM users WHERE id = :id";
        $user = Database::fetchOne($sql, ['id' => $userId]);

        if (!$user) {
            return ['success' => false, 'message' => 'Utilisateur non trouvé.'];
        }

        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Mot de passe actuel incorrect.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères.'];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::update('users', ['password_hash' => $hash], 'id = :id', ['id' => $userId]);

        self::logAction($userId, 'password_change', 'users', $userId);

        return ['success' => true, 'message' => 'Mot de passe modifié avec succès.'];
    }

    /**
     * Générer un token de réinitialisation
     */
    public static function generateResetToken(string $email): array {
        $sql = "SELECT id, nom FROM users WHERE email = :email AND statut = 'actif'";
        $user = Database::fetchOne($sql, ['email' => $email]);

        if (!$user) {
            // Ne pas révéler si l'email existe ou non
            return ['success' => true, 'message' => 'Si cet email existe, un lien de réinitialisation a été envoyé.'];
        }

        $token = bin2hex(random_bytes(32));
        $expire = date('Y-m-d H:i:s', time() + 3600); // 1 heure

        Database::update('users', [
            'token_reset' => $token,
            'token_reset_expire' => $expire
        ], 'id = :id', ['id' => $user['id']]);

        // TODO: Envoyer l'email avec le lien
        // Pour l'instant, on retourne le token (à enlever en production)
        return [
            'success' => true,
            'message' => 'Un lien de réinitialisation a été envoyé à votre email.',
            'token' => $token // À retirer en production
        ];
    }

    /**
     * Réinitialiser le mot de passe avec un token
     */
    public static function resetPassword(string $token, string $newPassword): array {
        $sql = "SELECT id FROM users WHERE token_reset = :token AND token_reset_expire > NOW()";
        $user = Database::fetchOne($sql, ['token' => $token]);

        if (!$user) {
            return ['success' => false, 'message' => 'Lien invalide ou expiré.'];
        }

        if (strlen($newPassword) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        Database::update('users', [
            'password_hash' => $hash,
            'token_reset' => null,
            'token_reset_expire' => null,
            'statut' => 'actif'
        ], 'id = :id', ['id' => $user['id']]);

        self::logAction($user['id'], 'password_reset', 'users', $user['id']);

        return ['success' => true, 'message' => 'Mot de passe réinitialisé avec succès.'];
    }

    /**
     * Logger une tentative de connexion échouée
     */
    private static function logFailedAttempt(string $email, ?int $userId = null): void {
        self::logAction($userId, 'login_failed', 'users', null, ['email' => $email]);
    }

    /**
     * Vérifier si l'IP est bloquée
     */
    private static function isIPBlocked(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $sql = "SELECT COUNT(*) as attempts FROM logs_audit
                WHERE ip_address = :ip
                AND action = 'login_failed'
                AND created_at > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)";

        $result = Database::fetchOne($sql, [
            'ip' => $ip,
            'minutes' => LOCKOUT_TIME / 60
        ]);

        return ($result['attempts'] ?? 0) >= (MAX_LOGIN_ATTEMPTS * 3);
    }

    /**
     * Logger une action
     */
    public static function logAction(
        ?int $userId,
        string $action,
        ?string $table = null,
        ?int $targetId = null,
        ?array $details = null
    ): void {
        try {
            Database::insert('logs_audit', [
                'user_id' => $userId,
                'action' => $action,
                'table_cible' => $table,
                'id_cible' => $targetId,
                'anciennes_valeurs' => null,
                'nouvelles_valeurs' => $details ? json_encode($details) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
        } catch (Exception $e) {
            // Ne pas bloquer l'application si le log échoue
            error_log("Audit log error: " . $e->getMessage());
        }
    }

    /**
     * Créer un utilisateur
     */
    public static function createUser(array $data): array {
        // Validation
        if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => 'Email invalide.'];
        }

        if (empty($data['password']) || strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Le mot de passe doit contenir au moins 8 caractères.'];
        }

        // Vérifier si l'email existe
        if (Database::exists('users', 'email = :email', ['email' => $data['email']])) {
            return ['success' => false, 'message' => 'Cet email est déjà utilisé.'];
        }

        $userId = Database::insert('users', [
            'nom' => sanitize($data['nom']),
            'prenom' => sanitize($data['prenom'] ?? ''),
            'email' => sanitize($data['email']),
            'telephone' => sanitize($data['telephone'] ?? ''),
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role_id' => (int)$data['role_id'],
            'statut' => 'actif'
        ]);

        self::logAction(self::id(), 'create_user', 'users', $userId);

        return ['success' => true, 'message' => 'Utilisateur créé avec succès.', 'id' => $userId];
    }
}
