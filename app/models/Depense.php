<?php
/**
 * Modèle Depense
 * Gestion des dépenses du complexe sportif
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';

class Depense {

    /**
     * Récupérer toutes les dépenses avec filtres
     */
    public static function getAll(array $filters = []): array {
        $sql = "SELECT d.*, c.nom as categorie_nom, c.icone, c.couleur,
                       t.nom as terrain_nom, u.nom as created_by_nom
                FROM depenses d
                JOIN categories_depenses c ON d.categorie_id = c.id
                LEFT JOIN terrains t ON d.terrain_id = t.id
                LEFT JOIN users u ON d.created_by = u.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['categorie_id'])) {
            $sql .= " AND d.categorie_id = :categorie_id";
            $params['categorie_id'] = $filters['categorie_id'];
        }

        if (!empty($filters['terrain_id'])) {
            $sql .= " AND d.terrain_id = :terrain_id";
            $params['terrain_id'] = $filters['terrain_id'];
        }

        if (!empty($filters['statut'])) {
            $sql .= " AND d.statut = :statut";
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['date_jour'])) {
            $sql .= " AND d.date_depense = :date_jour";
            $params['date_jour'] = $filters['date_jour'];
        }

        if (!empty($filters['date_debut'])) {
            $sql .= " AND d.date_depense >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $sql .= " AND d.date_depense <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }

        // Le filtre mois/année ne s'applique qu'au mode "mois".
        // En mode "jour" (date_jour) ou "intervalle" (date_debut/date_fin), les bornes
        // de date font foi : appliquer aussi mois/année écraserait l'intervalle.
        $hasDateRange = !empty($filters['date_debut']) || !empty($filters['date_fin']);
        if (empty($filters['date_jour']) && !$hasDateRange) {
            if (!empty($filters['mois'])) {
                $sql .= " AND MONTH(d.date_depense) = :mois";
                $params['mois'] = $filters['mois'];
            }

            if (!empty($filters['annee'])) {
                $sql .= " AND YEAR(d.date_depense) = :annee";
                $params['annee'] = $filters['annee'];
            }
        }

        $sql .= " ORDER BY d.date_depense DESC, d.created_at DESC";

        if (!empty($filters['limit'])) {
            $sql .= " LIMIT " . (int)$filters['limit'];
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Récupérer une dépense par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT d.*, c.nom as categorie_nom, c.icone, c.couleur,
                    t.nom as terrain_nom, u.nom as created_by_nom
             FROM depenses d
             JOIN categories_depenses c ON d.categorie_id = c.id
             LEFT JOIN terrains t ON d.terrain_id = t.id
             LEFT JOIN users u ON d.created_by = u.id
             WHERE d.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Créer une dépense
     */
    public static function create(array $data): int {
        // Attacher automatiquement la session caisse active du créateur (si caissier avec caisse ouverte)
        $sessionCaisseId = $data['session_caisse_id'] ?? null;
        if ($sessionCaisseId === null && !empty($data['created_by'])) {
            $row = Database::fetchOne(
                "SELECT id FROM clotures_caisse
                 WHERE utilisateur_id = :uid AND statut = 'ouverte'
                 ORDER BY heure_ouverture DESC LIMIT 1",
                ['uid' => $data['created_by']]
            );
            $sessionCaisseId = $row['id'] ?? null;
        }

        return Database::insert('depenses', [
            'categorie_id' => $data['categorie_id'],
            'terrain_id' => $data['terrain_id'] ?? null,
            'libelle' => $data['libelle'],
            'description' => $data['description'] ?? null,
            'montant' => $data['montant'],
            'date_depense' => $data['date_depense'],
            'date_echeance' => $data['date_echeance'] ?? null,
            'statut' => $data['statut'] ?? 'payee',
            'mode_paiement' => $data['mode_paiement'] ?? null,
            'reference_paiement' => $data['reference_paiement'] ?? null,
            'fournisseur' => $data['fournisseur'] ?? null,
            'piece_jointe' => $data['piece_jointe'] ?? null,
            'recurrence' => $data['recurrence'] ?? 'unique',
            'notes' => $data['notes'] ?? null,
            'created_by' => $data['created_by'] ?? null,
            'session_caisse_id' => $sessionCaisseId
        ]);
    }

    /**
     * Mettre à jour une dépense
     */
    public static function update(int $id, array $data): bool {
        $fields = [];
        $allowedFields = ['categorie_id', 'terrain_id', 'libelle', 'description', 'montant',
                          'date_depense', 'date_echeance', 'statut', 'mode_paiement',
                          'reference_paiement', 'fournisseur', 'piece_jointe', 'recurrence', 'notes'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        return Database::update('depenses', $fields, 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Supprimer une dépense
     */
    public static function delete(int $id): bool {
        return Database::delete('depenses', 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Mettre à jour le statut d'une dépense
     */
    public static function updateStatut(int $id, string $statut): bool {
        return Database::update('depenses', ['statut' => $statut], 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Statistiques des dépenses
     */
    public static function getStats(string $periode = 'mois', ?int $annee = null, ?string $dateDebut = null, ?string $dateFin = null): array {
        $annee = $annee ?? date('Y');

        $params = [];
        if ($periode === 'intervalle' && $dateDebut && $dateFin) {
            $conditions = "d.date_depense BETWEEN :debut AND :fin";
            $params = ['debut' => $dateDebut, 'fin' => $dateFin];
        } else {
            $conditions = match($periode) {
                'jour' => "DATE(d.date_depense) = CURDATE()",
                'semaine' => "d.date_depense >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                'mois' => "MONTH(d.date_depense) = MONTH(CURDATE()) AND YEAR(d.date_depense) = YEAR(CURDATE())",
                'annee' => "YEAR(d.date_depense) = :annee",
                default => "1=1"
            };
            if ($periode === 'annee') $params = ['annee' => $annee];
        }

        return Database::fetchOne(
            "SELECT
                COUNT(*) as nb_depenses,
                COALESCE(SUM(CASE WHEN d.statut = 'payee' THEN d.montant ELSE 0 END), 0) as total_payees,
                COALESCE(SUM(CASE WHEN d.statut = 'annulee' THEN d.montant ELSE 0 END), 0) as total_annulees,
                COALESCE(SUM(d.montant), 0) as total_global
             FROM depenses d
             WHERE {$conditions}",
            $params
        );
    }

    /**
     * Dépenses par catégorie
     */
    public static function getByCategorie(string $periode = 'mois', ?int $annee = null, ?string $dateDebut = null, ?string $dateFin = null): array {
        $annee = $annee ?? date('Y');

        if ($periode === 'intervalle' && $dateDebut && $dateFin) {
            $conditions = "d.date_depense BETWEEN " . Database::getInstance()->quote($dateDebut)
                       . " AND " . Database::getInstance()->quote($dateFin);
        } else {
            $conditions = match($periode) {
                'jour' => "DATE(d.date_depense) = CURDATE()",
                'semaine' => "d.date_depense >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                'mois' => "MONTH(d.date_depense) = MONTH(CURDATE()) AND YEAR(d.date_depense) = YEAR(CURDATE())",
                'annee' => "YEAR(d.date_depense) = " . (int)$annee,
                default => "1=1"
            };
        }

        return Database::fetchAll(
            "SELECT c.id, c.nom, c.icone, c.couleur,
                    COUNT(d.id) as nb_depenses,
                    COALESCE(SUM(d.montant), 0) as total
             FROM categories_depenses c
             LEFT JOIN depenses d ON c.id = d.categorie_id AND d.statut = 'payee' AND {$conditions}
             WHERE c.actif = 1
             GROUP BY c.id, c.nom, c.icone, c.couleur
             ORDER BY total DESC"
        );
    }

    /**
     * Évolution mensuelle des dépenses
     */
    public static function getEvolutionMensuelle(int $annee = null): array {
        $annee = $annee ?? date('Y');

        return Database::fetchAll(
            "SELECT
                MONTH(date_depense) as mois,
                COALESCE(SUM(montant), 0) as total
             FROM depenses
             WHERE YEAR(date_depense) = :annee AND statut = 'payee'
             GROUP BY MONTH(date_depense)
             ORDER BY mois",
            ['annee' => $annee]
        );
    }

    /**
     * Récupérer toutes les catégories
     */
    public static function getCategories(): array {
        return Database::fetchAll(
            "SELECT id, nom, icone, couleur, actif FROM categories_depenses WHERE actif = 1 ORDER BY ordre ASC, nom ASC"
        );
    }

    /**
     * Bilan financier (revenus - dépenses)
     */
    public static function getBilanFinancier(string $periode = 'mois', ?int $annee = null, ?string $dateDebut = null, ?string $dateFin = null): array {
        $annee = $annee ?? date('Y');

        if ($periode === 'intervalle' && $dateDebut && $dateFin) {
            $conditions = "date_ref BETWEEN " . Database::getInstance()->quote($dateDebut)
                       . " AND " . Database::getInstance()->quote($dateFin);
        } else {
            $conditions = match($periode) {
                'jour' => "DATE(date_ref) = CURDATE()",
                'semaine' => "date_ref >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
                'mois' => "MONTH(date_ref) = MONTH(CURDATE()) AND YEAR(date_ref) = YEAR(CURDATE())",
                'annee' => "YEAR(date_ref) = " . (int)$annee,
                default => "1=1"
            };
        }

        // Encaissements toutes catégories sauf cotisation_academie (les cotisations sont sommées
        // séparément depuis leur table pour éviter le double-comptage)
        $encaissements = Database::fetchOne(
            "SELECT
                COALESCE(SUM(CASE WHEN p.type_paiement = 'paiement'      THEN p.montant END), 0) as encaisse,
                COALESCE(SUM(CASE WHEN p.type_paiement = 'remboursement' THEN p.montant END), 0) as rembourse
             FROM paiements p
             LEFT JOIN categories_encaissement c ON p.categorie_id = c.id
             WHERE " . str_replace('date_ref', 'DATE(p.created_at)', $conditions) . "
               AND (c.code IS NULL OR c.code != 'cotisation_academie')"
        );

        // Cotisations académie (table séparée, ne passent pas par paiements)
        $cotisations = Database::fetchOne(
            "SELECT COALESCE(SUM(montant), 0) as total
             FROM cotisations
             WHERE statut = 'paye'
             AND " . str_replace('date_ref', 'date_paiement', $conditions)
        );

        // Dépenses (toutes catégories, statut payée uniquement)
        $depenses = Database::fetchOne(
            "SELECT COALESCE(SUM(montant), 0) as total
             FROM depenses
             WHERE statut = 'payee'
             AND " . str_replace('date_ref', 'date_depense', $conditions)
        );

        $revenusEncaissements = (float)$encaissements['encaisse'] - (float)$encaissements['rembourse'];
        $revenusCotisations = (float)($cotisations['total'] ?? 0);
        $totalRevenus = $revenusEncaissements + $revenusCotisations;
        $totalDepenses = (float)($depenses['total'] ?? 0);

        return [
            'revenus_encaissements' => $revenusEncaissements,
            'revenus_reservations'  => $revenusEncaissements, // alias rétro-compat
            'revenus_cotisations'   => $revenusCotisations,
            'total_revenus' => $totalRevenus,
            'total_depenses' => $totalDepenses,
            'benefice' => $totalRevenus - $totalDepenses,
            'marge' => $totalRevenus > 0 ? round((($totalRevenus - $totalDepenses) / $totalRevenus) * 100, 1) : 0
        ];
    }
}
