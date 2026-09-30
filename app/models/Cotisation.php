<?php
/**
 * Modèle Cotisation
 * Gestion des cotisations des membres de l'académie
 * Complexe Sportif Kaira
 */

class Cotisation
{
    /**
     * Récupère toutes les cotisations avec filtres
     */
    public static function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['membre_id'])) {
            $where[] = 'c.membre_id = :membre_id';
            $params['membre_id'] = $filters['membre_id'];
        }

        if (!empty($filters['statut'])) {
            $where[] = 'c.statut = :statut';
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['periode'])) {
            $where[] = 'c.periode LIKE :periode';
            $params['periode'] = '%' . $filters['periode'] . '%';
        }

        if (!empty($filters['en_retard'])) {
            $where[] = 'c.statut = "en_attente" AND (c.annee < YEAR(CURDATE()) OR (c.annee = YEAR(CURDATE()) AND c.mois < MONTH(CURDATE())))';
        }

        if (!empty($filters['mois'])) {
            $where[] = 'c.mois = :mois';
            $params['mois'] = $filters['mois'];
        }

        if (!empty($filters['annee'])) {
            $where[] = 'c.annee = :annee';
            $params['annee'] = $filters['annee'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM cotisations c WHERE $whereClause";
        $total = Database::fetchOne($countSql, $params)['total'];

        // Get data
        $sql = "SELECT c.*,
                    m.nom as membre_nom, m.prenom as membre_prenom, m.matricule,
                    m.categorie, m.telephone_parent,
                    u.nom as recu_par_nom
                FROM cotisations c
                JOIN membres_academie m ON c.membre_id = m.id
                LEFT JOIN users u ON c.recu_par = u.id
                WHERE $whereClause
                ORDER BY c.annee DESC, c.mois DESC, c.created_at DESC
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
     * Récupère une cotisation par ID
     */
    public static function getById($id)
    {
        $sql = "SELECT c.*,
                    m.nom as membre_nom, m.prenom as membre_prenom, m.matricule,
                    m.categorie, m.telephone_parent, m.email_parent,
                    u.nom as recu_par_nom
                FROM cotisations c
                JOIN membres_academie m ON c.membre_id = m.id
                LEFT JOIN users u ON c.recu_par = u.id
                WHERE c.id = :id";

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Crée une cotisation
     */
    public static function create($data)
    {
        return Database::insert('cotisations', [
            'membre_id' => $data['membre_id'],
            'mois' => $data['mois'],
            'annee' => $data['annee'],
            'montant' => $data['montant'],
            'statut' => $data['statut'] ?? 'en_attente'
        ]);
    }

    /**
     * Génère les cotisations mensuelles pour tous les membres actifs
     */
    public static function genererCotisationsMensuelles($mois, $annee)
    {
        // Récupérer les membres actifs sans cotisation pour cette période
        $sql = "SELECT id, montant_cotisation
                FROM membres_academie
                WHERE statut = 'actif'
                AND montant_cotisation > 0
                AND id NOT IN (
                    SELECT membre_id FROM cotisations
                    WHERE mois = :mois AND annee = :annee
                )";

        $membres = Database::fetchAll($sql, ['mois' => $mois, 'annee' => $annee]);

        $count = 0;
        foreach ($membres as $membre) {
            self::create([
                'membre_id' => $membre['id'],
                'mois' => $mois,
                'annee' => $annee,
                'montant' => $membre['montant_cotisation']
            ]);
            $count++;
        }

        return $count;
    }

    /**
     * Enregistre un paiement de cotisation
     */
    public static function payer($id, $data)
    {
        $cotisation = self::getById($id);
        if (!$cotisation) {
            return ['success' => false, 'message' => 'Cotisation introuvable.'];
        }

        if ($cotisation['statut'] === 'paye') {
            return ['success' => false, 'message' => 'Cette cotisation est déjà payée.'];
        }

        // Compatibilité : encaisse_par ou recu_par
        $userId = $data['encaisse_par'] ?? $data['recu_par'] ?? null;

        // Catégorie d'encaissement "cotisation_academie"
        $categorieId = (int)(Database::fetchOne(
            "SELECT id FROM categories_encaissement WHERE code = 'cotisation_academie' LIMIT 1"
        )['id'] ?? 0);

        // Session de caisse active du caissier (peut être null si admin/directeur sans caisse)
        $sessionCaisseId = null;
        if ($userId) {
            $session = Database::fetchOne(
                "SELECT id FROM clotures_caisse
                 WHERE utilisateur_id = :uid AND statut = 'ouverte'
                 ORDER BY heure_ouverture DESC LIMIT 1",
                ['uid' => $userId]
            );
            $sessionCaisseId = $session['id'] ?? null;
        }

        try {
            Database::beginTransaction();

            Database::update('cotisations', [
                'statut' => 'paye',
                'mode_paiement' => $data['mode_paiement'],
                'montant_paye' => $cotisation['montant'],
                'date_paiement' => date('Y-m-d'),
                'recu_par' => $userId,
                'notes' => $data['notes'] ?? null
            ], 'id = :id', ['id' => $id]);

            // Trace l'encaissement dans paiements pour qu'il apparaisse dans la caisse
            if ($categorieId > 0) {
                Database::insert('paiements', [
                    'reservation_id' => null,
                    'categorie_id' => $categorieId,
                    'montant' => $cotisation['montant'],
                    'mode_paiement' => $data['mode_paiement'],
                    'reference' => $data['reference'] ?? null,
                    'type_paiement' => 'paiement',
                    'recu_par' => $userId,
                    'session_caisse_id' => $sessionCaisseId,
                    'libelle' => 'Cotisation #' . $id,
                ]);
            }

            Database::commit();
        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }

        return ['success' => true, 'message' => 'Cotisation payée avec succès.'];
    }

    /**
     * Cotisations en retard
     */
    public static function getEnRetard($limit = null)
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        // Calculer le premier jour du mois courant pour le calcul des jours de retard
        $firstDayCurrentMonth = "$currentYear-" . str_pad($currentMonth, 2, '0', STR_PAD_LEFT) . "-01";

        $sql = "SELECT c.*,
                    m.nom as membre_nom, m.prenom as membre_prenom, m.matricule,
                    m.categorie, m.telephone_parent,
                    CONCAT(
                        CASE c.mois
                            WHEN 1 THEN 'Janvier' WHEN 2 THEN 'Février' WHEN 3 THEN 'Mars'
                            WHEN 4 THEN 'Avril' WHEN 5 THEN 'Mai' WHEN 6 THEN 'Juin'
                            WHEN 7 THEN 'Juillet' WHEN 8 THEN 'Août' WHEN 9 THEN 'Septembre'
                            WHEN 10 THEN 'Octobre' WHEN 11 THEN 'Novembre' WHEN 12 THEN 'Décembre'
                        END, ' ', c.annee
                    ) as periode,
                    DATEDIFF('$firstDayCurrentMonth', CONCAT(c.annee, '-', LPAD(c.mois, 2, '0'), '-01')) as jours_retard
                FROM cotisations c
                JOIN membres_academie m ON c.membre_id = m.id
                WHERE c.statut = 'en_attente'
                AND (c.annee < $currentYear OR (c.annee = $currentYear AND c.mois < $currentMonth))
                AND m.statut = 'actif'
                ORDER BY c.annee ASC, c.mois ASC";

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        return Database::fetchAll($sql);
    }

    /**
     * Statistiques des cotisations
     */
    public static function getStats($annee = null)
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        // Si une année est spécifiée, on filtre sur cette année pour les stats principales
        // Mais on calcule toujours les retards sur toutes les années
        if ($annee) {
            $sql = "SELECT
                        (SELECT COUNT(*) FROM cotisations WHERE annee = :annee1) as total_cotisations,
                        (SELECT COUNT(*) FROM cotisations WHERE annee = :annee2 AND statut = 'paye') as payees,
                        (SELECT COUNT(*) FROM cotisations WHERE annee = :annee3 AND statut = 'en_attente') as en_attente,
                        (SELECT SUM(montant) FROM cotisations WHERE annee = :annee4 AND statut = 'paye') as montant_encaisse,
                        (SELECT SUM(montant) FROM cotisations WHERE annee = :annee5 AND statut = 'en_attente') as montant_attendu,
                        (SELECT SUM(montant) FROM cotisations WHERE statut = 'en_attente' AND (annee < $currentYear OR (annee = $currentYear AND mois < $currentMonth))) as montant_retard";

            return Database::fetchOne($sql, [
                'annee1' => $annee,
                'annee2' => $annee,
                'annee3' => $annee,
                'annee4' => $annee,
                'annee5' => $annee
            ]);
        }

        // Sans filtre d'année, on prend toutes les cotisations
        $sql = "SELECT
                    COUNT(*) as total_cotisations,
                    SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as payees,
                    SUM(CASE WHEN statut = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant_encaisse,
                    SUM(CASE WHEN statut = 'en_attente' THEN montant ELSE 0 END) as montant_attendu,
                    SUM(CASE WHEN statut = 'en_attente' AND (annee < $currentYear OR (annee = $currentYear AND mois < $currentMonth)) THEN montant ELSE 0 END) as montant_retard
                FROM cotisations";

        return Database::fetchOne($sql);
    }

    /**
     * Statistiques par mois
     */
    public static function getStatsMensuelles($annee = null)
    {
        if (!$annee) {
            $annee = date('Y');
        }

        $sql = "SELECT
                    mois,
                    COUNT(*) as total,
                    SUM(CASE WHEN statut = 'paye' THEN 1 ELSE 0 END) as payees,
                    SUM(CASE WHEN statut = 'paye' THEN montant ELSE 0 END) as montant
                FROM cotisations
                WHERE annee = :annee
                GROUP BY mois
                ORDER BY mois";

        return Database::fetchAll($sql, ['annee' => $annee]);
    }

    /**
     * Résumé par catégorie
     */
    public static function getResumeParCategorie($periode = null)
    {
        $whereClause = '';
        $params = [];

        if ($periode) {
            $whereClause = "AND c.periode = :periode";
            $params['periode'] = $periode;
        }

        $sql = "SELECT
                    m.categorie,
                    COUNT(DISTINCT m.id) as nb_membres,
                    COUNT(c.id) as nb_cotisations,
                    SUM(CASE WHEN c.statut = 'paye' THEN 1 ELSE 0 END) as payees,
                    SUM(CASE WHEN c.statut = 'paye' THEN c.montant ELSE 0 END) as montant_encaisse,
                    SUM(CASE WHEN c.statut = 'en_attente' THEN c.montant ELSE 0 END) as montant_attendu
                FROM membres_academie m
                LEFT JOIN cotisations c ON m.id = c.membre_id $whereClause
                WHERE m.statut = 'actif'
                GROUP BY m.categorie
                ORDER BY m.categorie";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Supprime une cotisation
     */
    public static function delete($id)
    {
        return Database::delete('cotisations', 'id = :id AND statut = "en_attente"', ['id' => $id]);
    }
}
