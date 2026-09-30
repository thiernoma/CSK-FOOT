<?php
/**
 * Modèle CategorieDepense
 * Catégories de dépenses paramétrables
 */

require_once __DIR__ . '/../../config/database.php';

class CategorieDepense {

    public static function getAll(bool $actifSeul = false): array {
        $where = $actifSeul ? 'WHERE actif = 1' : '';
        return Database::fetchAll(
            "SELECT * FROM categories_depenses {$where} ORDER BY ordre ASC, nom ASC"
        );
    }

    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT * FROM categories_depenses WHERE id = :id",
            ['id' => $id]
        );
    }

    public static function create(array $data): array {
        $nom = trim($data['nom'] ?? '');
        if (empty($nom)) {
            return ['success' => false, 'message' => 'Le nom est obligatoire.'];
        }

        try {
            $id = Database::insert('categories_depenses', [
                'nom' => $nom,
                'description' => $data['description'] ?? null,
                'icone' => $data['icone'] ?? 'fa-file-invoice',
                'couleur' => $data['couleur'] ?? '#6c757d',
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

        $nom = trim($data['nom'] ?? '');
        if (empty($nom)) {
            return ['success' => false, 'message' => 'Le nom est obligatoire.'];
        }

        try {
            Database::update('categories_depenses', [
                'nom' => $nom,
                'description' => $data['description'] ?? null,
                'icone' => $data['icone'] ?? 'fa-file-invoice',
                'couleur' => $data['couleur'] ?? '#6c757d',
                'actif' => !empty($data['actif']) ? 1 : 0,
                'ordre' => (int)($data['ordre'] ?? 0)
            ], 'id = :id', ['id' => $id]);
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

        $count = Database::fetchOne(
            "SELECT COUNT(*) as nb FROM depenses WHERE categorie_id = :id",
            ['id' => $id]
        );
        if (($count['nb'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Impossible de supprimer : des dépenses utilisent cette catégorie. Désactivez-la plutôt.'];
        }

        try {
            Database::query("DELETE FROM categories_depenses WHERE id = :id", ['id' => $id]);
            return ['success' => true, 'message' => 'Catégorie supprimée.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }
}
