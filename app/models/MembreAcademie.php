<?php
/**
 * Modèle MembreAcademie
 * Gestion des membres de l'académie
 * Complexe Sportif Kaira
 */

class MembreAcademie
{
    /**
     * Récupère tous les membres avec pagination et filtres
     */
    public static function getAll($filters = [], $page = 1, $perPage = 20)
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['categorie'])) {
            $where[] = 'm.categorie = :categorie';
            $params['categorie'] = $filters['categorie'];
        }

        if (!empty($filters['statut'])) {
            $where[] = 'm.statut = :statut';
            $params['statut'] = $filters['statut'];
        }

        if (!empty($filters['search'])) {
            // Placeholders distincts (prepared statements natifs : pas de réutilisation possible)
            $where[] = '(m.nom LIKE :search1 OR m.prenom LIKE :search2 OR m.telephone_parent LIKE :search3)';
            $like = '%' . $filters['search'] . '%';
            $params['search1'] = $like;
            $params['search2'] = $like;
            $params['search3'] = $like;
        }

        if (!empty($filters['cotisation_status'])) {
            // Vérifie les cotisations en retard basé sur mois/année au lieu de date_echeance
            $currentMonth = (int) date('n');
            $currentYear = (int) date('Y');
            if ($filters['cotisation_status'] === 'a_jour') {
                $where[] = "NOT EXISTS (SELECT 1 FROM cotisations c WHERE c.membre_id = m.id AND c.statut = 'en_attente' AND (c.annee < $currentYear OR (c.annee = $currentYear AND c.mois < $currentMonth)))";
            } elseif ($filters['cotisation_status'] === 'retard') {
                $where[] = "EXISTS (SELECT 1 FROM cotisations c WHERE c.membre_id = m.id AND c.statut = 'en_attente' AND (c.annee < $currentYear OR (c.annee = $currentYear AND c.mois < $currentMonth)))";
            }
        }

        if (!empty($filters['entraineur_id'])) {
            $where[] = 'm.entraineur_principal_id = :entraineur_id';
            $params['entraineur_id'] = $filters['entraineur_id'];
        }

        $whereClause = implode(' AND ', $where);
        $offset = ($page - 1) * $perPage;

        // Count total
        $countSql = "SELECT COUNT(*) as total FROM membres_academie m WHERE $whereClause";
        $total = Database::fetchOne($countSql, $params)['total'];

        // Get current month/year for cotisation check
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        // Get data with entraineur join
        $sql = "SELECT m.*,
                    e.nom as entraineur_nom,
                    e.prenom as entraineur_prenom,
                    (SELECT SUM(montant) FROM cotisations WHERE membre_id = m.id AND statut = 'paye') as total_paye,
                    (SELECT COUNT(*) FROM cotisations WHERE membre_id = m.id AND statut = 'en_attente' AND (annee < $currentYear OR (annee = $currentYear AND mois < $currentMonth))) as cotisations_retard
                FROM membres_academie m
                LEFT JOIN entraineurs e ON m.entraineur_principal_id = e.id
                WHERE $whereClause
                ORDER BY m.created_at DESC
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
     * Récupère un membre par son ID
     */
    public static function getById($id)
    {
        $sql = "SELECT m.*,
                    e.nom as entraineur_nom,
                    e.prenom as entraineur_prenom,
                    (SELECT SUM(montant) FROM cotisations WHERE membre_id = m.id AND statut = 'paye') as total_cotisations_payees,
                    (SELECT COUNT(*) FROM presences WHERE membre_id = m.id) as total_presences,
                    (SELECT COUNT(*) FROM presences WHERE membre_id = m.id AND statut = 'present') as presences_effectives
                FROM membres_academie m
                LEFT JOIN entraineurs e ON m.entraineur_principal_id = e.id
                WHERE m.id = :id";

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Crée un nouveau membre
     */
    public static function create($data)
    {
        // Générer le numéro de licence
        $data['numero_licence'] = self::generateLicenceNumber();

        $id = Database::insert('membres_academie', [
            'numero_licence' => $data['numero_licence'],
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'date_naissance' => $data['date_naissance'],
            'categorie' => $data['categorie'],
            'photo' => $data['photo'] ?? null,
            'nom_parent' => $data['nom_parent'] ?? null,
            'telephone_parent' => $data['telephone_parent'],
            'telephone_parent_alt' => $data['telephone_parent_alt'] ?? null,
            'email_parent' => $data['email_parent'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'ecole' => $data['ecole'] ?? null,
            'niveau_scolaire' => $data['niveau_scolaire'] ?? null,
            'position_preferee' => $data['position_preferee'] ?? null,
            'pied_fort' => $data['pied_fort'] ?? 'droit',
            'entraineur_principal_id' => $data['entraineur_principal_id'] ?? null,
            'date_inscription' => $data['date_inscription'] ?? date('Y-m-d'),
            'montant_inscription' => $data['montant_inscription'] ?? 0,
            'cotisation_mensuelle' => $data['cotisation_mensuelle'],
            'notes_medicales' => $data['notes_medicales'] ?? null,
            'personne_urgence' => $data['personne_urgence'] ?? null,
            'telephone_urgence' => $data['telephone_urgence'] ?? null,
            'statut' => $data['statut'] ?? 'actif'
        ]);

        return $id;
    }

    /**
     * Met à jour un membre
     */
    public static function update($id, $data)
    {
        $updateData = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'date_naissance' => $data['date_naissance'],
            'categorie' => $data['categorie'],
            'nom_parent' => $data['nom_parent'] ?? null,
            'telephone_parent' => $data['telephone_parent'],
            'telephone_parent_alt' => $data['telephone_parent_alt'] ?? null,
            'email_parent' => $data['email_parent'] ?? null,
            'adresse' => $data['adresse'] ?? null,
            'ecole' => $data['ecole'] ?? null,
            'niveau_scolaire' => $data['niveau_scolaire'] ?? null,
            'position_preferee' => $data['position_preferee'] ?? null,
            'pied_fort' => $data['pied_fort'] ?? 'droit',
            'entraineur_principal_id' => $data['entraineur_principal_id'] ?? null,
            'cotisation_mensuelle' => $data['cotisation_mensuelle'],
            'notes_medicales' => $data['notes_medicales'] ?? null,
            'personne_urgence' => $data['personne_urgence'] ?? null,
            'telephone_urgence' => $data['telephone_urgence'] ?? null,
            'statut' => $data['statut']
        ];

        if (isset($data['photo'])) {
            $updateData['photo'] = $data['photo'];
        }

        return Database::update('membres_academie', $updateData, 'id = :id', ['id' => $id]);
    }

    /**
     * Génère un numéro de licence unique
     */
    public static function generateLicenceNumber()
    {
        $year = date('Y');
        $prefix = 'AKF-' . $year . '-';

        $sql = "SELECT numero_licence FROM membres_academie
                WHERE numero_licence LIKE :prefix
                ORDER BY numero_licence DESC LIMIT 1";

        $last = Database::fetchOne($sql, ['prefix' => $prefix . '%']);

        if ($last) {
            $lastNum = (int)substr($last['numero_licence'], -4);
            $newNum = $lastNum + 1;
        } else {
            $newNum = 1;
        }

        return $prefix . str_pad($newNum, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recherche de membres (autocomplete)
     */
    public static function search($query, $limit = 10)
    {
        // Placeholders distincts (prepared natifs) + LIMIT en entier littéral
        // (un LIMIT lié serait envoyé comme chaîne et provoquerait une erreur SQL).
        $limit = max(1, (int)$limit);

        $sql = "SELECT id, numero_licence, nom, prenom, categorie, telephone_parent, statut
                FROM membres_academie
                WHERE (nom LIKE :query1 OR prenom LIKE :query2 OR numero_licence LIKE :query3)
                AND statut = 'actif'
                ORDER BY nom, prenom
                LIMIT $limit";

        $like = '%' . $query . '%';
        return Database::fetchAll($sql, [
            'query1' => $like,
            'query2' => $like,
            'query3' => $like,
        ]);
    }

    /**
     * Récupère les cotisations d'un membre
     */
    public static function getCotisations($membreId, $limit = null)
    {
        $sql = "SELECT c.*, u.nom as recu_par_nom
                FROM cotisations c
                LEFT JOIN users u ON c.recu_par = u.id
                WHERE c.membre_id = :membre_id
                ORDER BY c.annee DESC, c.mois DESC";

        if ($limit) {
            $sql .= " LIMIT $limit";
        }

        return Database::fetchAll($sql, ['membre_id' => $membreId]);
    }

    /**
     * Récupère les présences d'un membre
     */
    public static function getPresences($membreId, $limit = 20)
    {
        // LIMIT en entier littéral : en prepared natif, un LIMIT lié part comme chaîne -> erreur SQL
        $limit = max(1, (int)$limit);

        $sql = "SELECT p.*, s.date_seance, s.heure_debut, s.heure_fin,
                    t.nom as terrain_nom, e.nom as entraineur_nom
                FROM presences p
                JOIN seances s ON p.seance_id = s.id
                LEFT JOIN terrains t ON s.terrain_id = t.id
                LEFT JOIN entraineurs e ON s.entraineur_id = e.id
                WHERE p.membre_id = :membre_id
                ORDER BY s.date_seance DESC
                LIMIT $limit";

        return Database::fetchAll($sql, [
            'membre_id' => $membreId,
        ]);
    }

    /**
     * Statistiques d'un membre
     */
    public static function getStats($membreId)
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM presences WHERE membre_id = :id1) as total_seances,
                    (SELECT COUNT(*) FROM presences WHERE membre_id = :id2 AND statut = 'present') as presences,
                    (SELECT SUM(montant) FROM cotisations WHERE membre_id = :id3 AND statut = 'paye') as total_paye,
                    (SELECT COUNT(*) FROM cotisations WHERE membre_id = :id4 AND statut = 'en_attente' AND (annee < YEAR(CURDATE()) OR (annee = YEAR(CURDATE()) AND mois < MONTH(CURDATE())))) as cotisations_retard,
                    (SELECT SUM(montant) FROM cotisations WHERE membre_id = :id5 AND statut = 'en_attente') as total_du";

        return Database::fetchOne($sql, [
            'id1' => $membreId,
            'id2' => $membreId,
            'id3' => $membreId,
            'id4' => $membreId,
            'id5' => $membreId
        ]);
    }

    /**
     * Liste des membres par catégorie
     */
    public static function getByCategorie($categorie)
    {
        $sql = "SELECT m.*
                FROM membres_academie m
                WHERE m.categorie = :categorie AND m.statut = 'actif'
                ORDER BY m.nom, m.prenom";

        return Database::fetchAll($sql, ['categorie' => $categorie]);
    }

    /**
     * Membres en retard de cotisation
     */
    public static function getMembresEnRetard()
    {
        $sql = "SELECT m.*,
                    COUNT(c.id) as nb_cotisations_retard,
                    SUM(c.montant) as montant_du
                FROM membres_academie m
                INNER JOIN cotisations c ON m.id = c.membre_id
                WHERE c.statut = 'en_attente' AND (c.annee < YEAR(CURDATE()) OR (c.annee = YEAR(CURDATE()) AND c.mois < MONTH(CURDATE())))
                AND m.statut = 'actif'
                GROUP BY m.id
                ORDER BY montant_du DESC";

        return Database::fetchAll($sql);
    }

    /**
     * Statistiques globales de l'académie
     */
    public static function getGlobalStats()
    {
        $sql = "SELECT
                    (SELECT COUNT(*) FROM membres_academie WHERE statut = 'actif') as total_actifs,
                    (SELECT COUNT(*) FROM membres_academie WHERE statut = 'actif' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as nouveaux_30j,
                    (SELECT COUNT(DISTINCT membre_id) FROM cotisations WHERE statut = 'en_attente' AND (annee < YEAR(CURDATE()) OR (annee = YEAR(CURDATE()) AND mois < MONTH(CURDATE())))) as membres_en_retard,
                    (SELECT SUM(montant) FROM cotisations WHERE statut = 'paye' AND MONTH(date_paiement) = MONTH(CURDATE()) AND YEAR(date_paiement) = YEAR(CURDATE())) as ca_mois,
                    (SELECT SUM(montant) FROM cotisations WHERE statut = 'en_attente') as total_impaye";

        return Database::fetchOne($sql);
    }

    /**
     * Répartition par catégorie
     */
    public static function getRepartitionCategorie()
    {
        $sql = "SELECT categorie, COUNT(*) as total
                FROM membres_academie
                WHERE statut = 'actif'
                GROUP BY categorie
                ORDER BY
                    CASE categorie
                        WHEN 'U7' THEN 1
                        WHEN 'U9' THEN 2
                        WHEN 'U11' THEN 3
                        WHEN 'U13' THEN 4
                        WHEN 'U15' THEN 5
                        WHEN 'U17' THEN 6
                        WHEN 'U19' THEN 7
                        WHEN 'senior' THEN 8
                    END";

        return Database::fetchAll($sql);
    }

    /**
     * Calcule l'âge à partir de la date de naissance
     */
    public static function calculateAge($dateNaissance)
    {
        $birthDate = new DateTime($dateNaissance);
        $today = new DateTime('today');
        return $birthDate->diff($today)->y;
    }

    /**
     * Détermine automatiquement la catégorie selon l'âge
     */
    public static function determineCategorie($dateNaissance)
    {
        $age = self::calculateAge($dateNaissance);

        foreach (CATEGORIES_AGE as $cat => $range) {
            if ($age >= $range['min'] && $age <= $range['max']) {
                return $cat;
            }
        }

        return 'senior';
    }

    /**
     * Supprime un membre (soft delete)
     */
    public static function delete($id)
    {
        return Database::update('membres_academie',
            ['statut' => 'inactif'],
            'id = :id',
            ['id' => $id]
        );
    }
}
