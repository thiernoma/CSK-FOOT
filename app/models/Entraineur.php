<?php
/**
 * Modèle Entraineur
 * Gestion des entraîneurs de l'académie
 * Complexe Sportif Kaira
 */

class Entraineur
{
    /**
     * Récupère tous les entraîneurs
     */
    public static function getAll($filters = null)
    {
        $sql = "SELECT e.*,
                    (SELECT COUNT(*) FROM seances WHERE entraineur_id = e.id AND date_seance >= CURDATE()) as seances_planifiees
                FROM entraineurs e";

        $params = [];

        // Support pour ancien format (string) et nouveau format (array)
        if (is_array($filters) && !empty($filters['statut'])) {
            $sql .= " WHERE e.statut = :statut";
            $params['statut'] = $filters['statut'];
        } elseif (is_string($filters) && !empty($filters)) {
            $sql .= " WHERE e.statut = :statut";
            $params['statut'] = $filters;
        }

        $sql .= " ORDER BY e.nom, e.prenom";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Récupère un entraîneur par son ID
     */
    public static function getById($id)
    {
        $sql = "SELECT e.*,
                    (SELECT COUNT(*) FROM seances WHERE entraineur_id = e.id AND date_seance >= CURDATE()) as seances_planifiees
                FROM entraineurs e
                WHERE e.id = :id";

        return Database::fetchOne($sql, ['id' => $id]);
    }

    /**
     * Récupère un entraîneur par son user_id
     */
    public static function getByUserId($userId)
    {
        $sql = "SELECT e.*,
                    (SELECT COUNT(*) FROM seances WHERE entraineur_id = e.id AND date_seance >= CURDATE()) as seances_planifiees
                FROM entraineurs e
                WHERE e.user_id = :user_id";

        return Database::fetchOne($sql, ['user_id' => $userId]);
    }

    /**
     * Récupère les joueurs en retard de cotisation pour un entraîneur
     */
    public static function getJoueursEnRetard($entraineurId)
    {
        $currentMonth = (int) date('n');
        $currentYear = (int) date('Y');

        $sql = "SELECT m.*,
                    COUNT(c.id) as nb_cotisations_retard,
                    SUM(c.montant) as montant_du
                FROM membres_academie m
                INNER JOIN cotisations c ON m.id = c.membre_id
                WHERE m.entraineur_principal_id = :entraineur_id
                AND m.statut = 'actif'
                AND c.statut = 'en_attente'
                AND (c.annee < $currentYear OR (c.annee = $currentYear AND c.mois < $currentMonth))
                GROUP BY m.id
                ORDER BY montant_du DESC";

        return Database::fetchAll($sql, ['entraineur_id' => $entraineurId]);
    }

    /**
     * Crée un nouvel entraîneur
     */
    public static function create($data)
    {
        return Database::insert('entraineurs', [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'],
            'email' => $data['email'] ?? null,
            'specialite' => $data['specialite'] ?? null,
            'categories' => $data['categories'] ?? null,
            'diplomes' => $data['diplomes'] ?? null,
            'date_embauche' => $data['date_embauche'] ?? date('Y-m-d'),
            'salaire' => $data['salaire'] ?? null,
            'photo' => $data['photo'] ?? null,
            'statut' => $data['statut'] ?? 'actif'
        ]);
    }

    /**
     * Met à jour un entraîneur
     */
    public static function update($id, $data)
    {
        $updateData = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'telephone' => $data['telephone'],
            'email' => $data['email'] ?? null,
            'specialite' => $data['specialite'] ?? null,
            'categories' => $data['categories'] ?? null,
            'diplomes' => $data['diplomes'] ?? null,
            'salaire' => $data['salaire'] ?? null,
            'statut' => $data['statut']
        ];

        if (isset($data['photo'])) {
            $updateData['photo'] = $data['photo'];
        }

        return Database::update('entraineurs', $updateData, 'id = :id', ['id' => $id]);
    }

    /**
     * Récupère les joueurs assignés à un entraîneur
     */
    public static function getJoueurs($entraineurId)
    {
        $sql = "SELECT m.*
                FROM membres_academie m
                WHERE m.entraineur_principal_id = :entraineur_id
                AND m.statut = 'actif'
                ORDER BY m.categorie, m.nom";

        return Database::fetchAll($sql, ['entraineur_id' => $entraineurId]);
    }

    /**
     * Récupère les séances d'un entraîneur
     */
    public static function getSeances($entraineurId, $dateDebut = null, $dateFin = null)
    {
        $params = ['entraineur_id' => $entraineurId];
        $where = ['s.entraineur_id = :entraineur_id'];

        if ($dateDebut) {
            $where[] = 's.date_seance >= :date_debut';
            $params['date_debut'] = $dateDebut;
        }

        if ($dateFin) {
            $where[] = 's.date_seance <= :date_fin';
            $params['date_fin'] = $dateFin;
        }

        $whereClause = implode(' AND ', $where);

        $sql = "SELECT s.*, t.nom as terrain_nom,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id) as nb_inscrits,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id AND statut = 'present') as nb_presents
                FROM seances s
                LEFT JOIN terrains t ON s.terrain_id = t.id
                WHERE $whereClause
                ORDER BY s.date_seance, s.heure_debut";

        return Database::fetchAll($sql, $params);
    }

    /**
     * Statistiques d'un entraîneur
     */
    public static function getStats($entraineurId, $periode = 'mois')
    {
        $dateCondition = match($periode) {
            'jour' => 'date_seance = CURDATE()',
            'semaine' => 'date_seance >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'mois' => 'date_seance >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
            'annee' => 'date_seance >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
            default => '1=1'
        };

        // Pour la sous-requête, on utilise un préfixe s.
        $dateConditionWithAlias = match($periode) {
            'jour' => 's.date_seance = CURDATE()',
            'semaine' => 's.date_seance >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)',
            'mois' => 's.date_seance >= DATE_SUB(CURDATE(), INTERVAL 1 MONTH)',
            'annee' => 's.date_seance >= DATE_SUB(CURDATE(), INTERVAL 1 YEAR)',
            default => '1=1'
        };

        $sql = "SELECT
                    (SELECT COUNT(*) FROM seances_entrainement WHERE entraineur_id = :id1 AND $dateCondition) as nb_seances,
                    (SELECT AVG(taux_presence) FROM (
                        SELECT s.id,
                            (SELECT COUNT(*) FROM presences WHERE seance_id = s.id AND statut = 'present') /
                            NULLIF((SELECT COUNT(*) FROM presences WHERE seance_id = s.id), 0) * 100 as taux_presence
                        FROM seances_entrainement s
                        WHERE s.entraineur_id = :id2 AND $dateConditionWithAlias
                    ) as sub) as taux_presence_moyen,
                    (SELECT COUNT(*) FROM membres_academie WHERE entraineur_principal_id = :id3 AND statut = 'actif') as nb_joueurs_assignes";

        return Database::fetchOne($sql, [
            'id1' => $entraineurId,
            'id2' => $entraineurId,
            'id3' => $entraineurId
        ]);
    }

    /**
     * Planning de la semaine
     */
    public static function getPlanningHebdo($entraineurId, $dateDebut = null)
    {
        if (!$dateDebut) {
            $dateDebut = date('Y-m-d', strtotime('monday this week'));
        }
        $dateFin = date('Y-m-d', strtotime($dateDebut . ' +6 days'));

        return self::getSeances($entraineurId, $dateDebut, $dateFin);
    }

    /**
     * Supprime un entraîneur (soft delete)
     */
    public static function delete($id)
    {
        return Database::update('entraineurs',
            ['statut' => 'inactif'],
            'id = :id',
            ['id' => $id]
        );
    }
}
