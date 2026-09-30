<?php
/**
 * Modèle CategorieEncaissement
 * Catégories d'encaissement paramétrables (réservation, buvette, location matériel, etc.)
 */

require_once __DIR__ . '/../../config/database.php';

class CategorieEncaissement {

    public static function getAll(bool $actifSeul = false): array {
        $where = $actifSeul ? 'WHERE actif = 1' : '';
        return Database::fetchAll(
            "SELECT * FROM categories_encaissement {$where} ORDER BY ordre ASC, libelle ASC"
        );
    }

    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT * FROM categories_encaissement WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function getByCode(string $code): ?array {
        return Database::fetchOne(
            "SELECT * FROM categories_encaissement WHERE code = :code",
            ['code' => $code]
        );
    }

    /**
     * Catégories utilisables pour un encaissement libre (hors 'reservation')
     */
    public static function getForFreePayment(): array {
        return Database::fetchAll(
            "SELECT * FROM categories_encaissement
             WHERE actif = 1 AND code != 'reservation'
             ORDER BY ordre ASC, libelle ASC"
        );
    }

    public static function create(array $data): array {
        $code = strtolower(preg_replace('/[^a-z0-9_]/', '_', $data['code'] ?? ''));
        $libelle = trim($data['libelle'] ?? '');

        if (empty($code) || empty($libelle)) {
            return ['success' => false, 'message' => 'Le code et le libellé sont obligatoires.'];
        }

        if (self::getByCode($code)) {
            return ['success' => false, 'message' => 'Ce code existe déjà.'];
        }

        try {
            $id = Database::insert('categories_encaissement', [
                'code' => $code,
                'libelle' => $libelle,
                'icone' => $data['icone'] ?? null,
                'couleur' => $data['couleur'] ?? '#01305E',
                'actif' => !empty($data['actif']) ? 1 : 0,
                'ordre' => (int)($data['ordre'] ?? 0),
                'systeme' => 0
            ]);
            return ['success' => true, 'id' => $id, 'message' => 'Catégorie créée.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    public static function update(int $id, array $data): array {
        $cat = self::getById($id);
        if (!$cat) {
            return ['success' => false, 'message' => 'Catégorie introuvable.'];
        }

        $libelle = trim($data['libelle'] ?? '');
        if (empty($libelle)) {
            return ['success' => false, 'message' => 'Le libellé est obligatoire.'];
        }

        // Le code n'est pas modifiable pour les catégories systèmes
        $fields = [
            'libelle' => $libelle,
            'icone' => $data['icone'] ?? null,
            'couleur' => $data['couleur'] ?? '#01305E',
            'actif' => !empty($data['actif']) ? 1 : 0,
            'ordre' => (int)($data['ordre'] ?? 0)
        ];

        if (!$cat['systeme'] && !empty($data['code'])) {
            $newCode = strtolower(preg_replace('/[^a-z0-9_]/', '_', $data['code']));
            $existing = self::getByCode($newCode);
            if ($existing && $existing['id'] != $id) {
                return ['success' => false, 'message' => 'Ce code existe déjà.'];
            }
            $fields['code'] = $newCode;
        }

        try {
            Database::update('categories_encaissement', $fields, 'id = :id', ['id' => $id]);
            return ['success' => true, 'message' => 'Catégorie mise à jour.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    public static function delete(int $id): array {
        $cat = self::getById($id);
        if (!$cat) {
            return ['success' => false, 'message' => 'Catégorie introuvable.'];
        }
        if ($cat['systeme']) {
            return ['success' => false, 'message' => 'Cette catégorie est protégée (système).'];
        }

        // Vérifier qu'aucun paiement ne l'utilise
        $count = Database::fetchOne(
            "SELECT COUNT(*) as nb FROM paiements WHERE categorie_id = :id",
            ['id' => $id]
        );
        if (($count['nb'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Impossible de supprimer : des encaissements utilisent cette catégorie. Désactivez-la plutôt.'];
        }

        try {
            Database::query("DELETE FROM categories_encaissement WHERE id = :id", ['id' => $id]);
            return ['success' => true, 'message' => 'Catégorie supprimée.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
}
