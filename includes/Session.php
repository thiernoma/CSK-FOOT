<?php
/**
 * Gestionnaire de sessions sécurisées
 * Complexe Sportif Kaira
 */

class Session {
    private static bool $started = false;

    /**
     * Démarrer la session de manière sécurisée
     */
    public static function start(): void {
        if (self::$started) {
            return;
        }

        // Configuration sécurisée des sessions
        ini_set('session.use_strict_mode', 1);
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_samesite', 'Strict');

        if (defined('SESSION_SECURE') && SESSION_SECURE) {
            ini_set('session.cookie_secure', 1);
        }

        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path' => '/',
            'domain' => '',
            'secure' => SESSION_SECURE,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        session_start();
        self::$started = true;

        // Régénérer l'ID de session périodiquement
        if (!isset($_SESSION['_last_regeneration'])) {
            self::regenerate();
        } elseif (time() - $_SESSION['_last_regeneration'] > 300) {
            // Régénérer toutes les 5 minutes
            self::regenerate();
        }

        // Vérifier le timeout de session
        if (isset($_SESSION['_last_activity'])) {
            if (time() - $_SESSION['_last_activity'] > SESSION_LIFETIME) {
                self::destroy();
                return;
            }
        }
        $_SESSION['_last_activity'] = time();
    }

    /**
     * Régénérer l'ID de session
     */
    public static function regenerate(): void {
        session_regenerate_id(true);
        $_SESSION['_last_regeneration'] = time();
    }

    /**
     * Définir une valeur en session
     */
    public static function set(string $key, $value): void {
        self::start();
        $_SESSION[$key] = $value;
    }

    /**
     * Obtenir une valeur de la session
     */
    public static function get(string $key, $default = null) {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Vérifier si une clé existe
     */
    public static function has(string $key): bool {
        self::start();
        return isset($_SESSION[$key]);
    }

    /**
     * Supprimer une valeur
     */
    public static function remove(string $key): void {
        self::start();
        unset($_SESSION[$key]);
    }

    /**
     * Détruire la session
     */
    public static function destroy(): void {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
        self::$started = false;
    }

    /**
     * Définir un message flash
     */
    public static function flash(string $type, string $message): void {
        self::start();
        $_SESSION['_flash'][$type][] = $message;
    }

    /**
     * Obtenir et supprimer les messages flash
     */
    public static function getFlash(string $type = null): array {
        self::start();

        if ($type === null) {
            $messages = $_SESSION['_flash'] ?? [];
            unset($_SESSION['_flash']);
            return $messages;
        }

        $messages = $_SESSION['_flash'][$type] ?? [];
        unset($_SESSION['_flash'][$type]);
        return $messages;
    }

    /**
     * Vérifier s'il y a des messages flash
     */
    public static function hasFlash(string $type = null): bool {
        self::start();

        if ($type === null) {
            return !empty($_SESSION['_flash']);
        }

        return !empty($_SESSION['_flash'][$type]);
    }

    /**
     * Obtenir l'ID utilisateur connecté
     */
    public static function getUserId(): ?int {
        return self::get('user_id');
    }

    /**
     * Vérifier si l'utilisateur est connecté
     */
    public static function isLoggedIn(): bool {
        return self::has('user_id') && self::has('user_logged_in');
    }

    /**
     * Obtenir le rôle de l'utilisateur
     */
    public static function getUserRole(): ?string {
        return self::get('user_role');
    }

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public static function hasRole(string $role): bool {
        return self::get('user_role') === $role;
    }

    /**
     * Vérifier si l'utilisateur a une permission
     */
    public static function hasPermission(string $permission): bool {
        $permissions = self::get('user_permissions', []);

        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }

    /**
     * Définir les données de l'utilisateur connecté
     */
    public static function setUser(array $user): void {
        self::set('user_id', $user['id']);
        self::set('user_nom', $user['nom']);
        self::set('user_prenom', $user['prenom'] ?? '');
        self::set('user_email', $user['email']);
        self::set('user_role', $user['role_nom']);
        self::set('user_role_id', $user['role_id']);
        self::set('user_permissions', json_decode($user['permissions'] ?? '[]', true));
        self::set('user_logged_in', true);
        self::regenerate();
    }

    /**
     * Obtenir les infos de l'utilisateur connecté
     */
    public static function getUser(): array {
        return [
            'id' => self::get('user_id'),
            'nom' => self::get('user_nom'),
            'prenom' => self::get('user_prenom'),
            'email' => self::get('user_email'),
            'role' => self::get('user_role'),
            'role_id' => self::get('user_role_id'),
            'permissions' => self::get('user_permissions', [])
        ];
    }

    /**
     * Obtenir le nom complet de l'utilisateur
     */
    public static function getUserFullName(): string {
        $prenom = self::get('user_prenom', '');
        $nom = self::get('user_nom', '');
        return trim($prenom . ' ' . $nom);
    }
}
