<?php
/**
 * Connexion à la base de données - PDO
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    /**
     * Obtenir l'instance de connexion PDO (Singleton)
     */
    public static function getInstance(): PDO {
        if (self::$instance === null) {
            try {
                $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
                ];

                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);

            } catch (PDOException $e) {
                if (DEV_MODE) {
                    die("Erreur de connexion à la base de données: " . $e->getMessage());
                } else {
                    error_log("Database connection error: " . $e->getMessage());
                    die("Une erreur est survenue. Veuillez réessayer plus tard.");
                }
            }
        }

        return self::$instance;
    }

    /**
     * Exécuter une requête préparée
     */
    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::getInstance()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Récupérer une seule ligne
     */
    public static function fetchOne(string $sql, array $params = []): ?array {
        $result = self::query($sql, $params)->fetch();
        return $result ?: null;
    }

    /**
     * Récupérer toutes les lignes
     */
    public static function fetchAll(string $sql, array $params = []): array {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * Insérer et retourner l'ID
     */
    public static function insert(string $table, array $data): int {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));

        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        self::query($sql, $data);

        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Mettre à jour des enregistrements
     */
    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $set = [];
        foreach (array_keys($data) as $column) {
            $set[] = "{$column} = :{$column}";
        }
        $setString = implode(', ', $set);

        $sql = "UPDATE {$table} SET {$setString} WHERE {$where}";
        $params = array_merge($data, $whereParams);

        return self::query($sql, $params)->rowCount();
    }

    /**
     * Supprimer des enregistrements
     */
    public static function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        return self::query($sql, $params)->rowCount();
    }

    /**
     * Compter les enregistrements
     */
    public static function count(string $table, string $where = '1=1', array $params = []): int {
        $sql = "SELECT COUNT(*) as total FROM {$table} WHERE {$where}";
        $result = self::fetchOne($sql, $params);
        return (int) ($result['total'] ?? 0);
    }

    /**
     * Vérifier si un enregistrement existe
     */
    public static function exists(string $table, string $where, array $params = []): bool {
        return self::count($table, $where, $params) > 0;
    }

    /**
     * Démarrer une transaction
     */
    public static function beginTransaction(): bool {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Valider une transaction
     */
    public static function commit(): bool {
        $pdo = self::getInstance();
        if ($pdo->inTransaction()) {
            return $pdo->commit();
        }
        return false;
    }

    /**
     * Annuler une transaction
     */
    public static function rollback(): bool {
        $pdo = self::getInstance();
        if ($pdo->inTransaction()) {
            return $pdo->rollBack();
        }
        return false;
    }

    /**
     * Empêcher le clonage
     */
    private function __clone() {}

    /**
     * Empêcher la désérialisation
     */
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}
