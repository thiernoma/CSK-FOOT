<?php
/**
 * Modèle Presence - Gestion des présences aux séances
 * Complexe Sportif Kaira
 */

class Presence
{
    /**
     * Statuts de présence
     */
    public static function getStatuts(): array
    {
        return [
            'present' => 'Présent',
            'absent' => 'Absent',
            'retard' => 'En retard',
            'excuse' => 'Absent excusé',
            'blesse' => 'Blessé'
        ];
    }

    /**
     * Récupérer une présence par ID
     */
    public static function getById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT p.*, m.nom as membre_nom, m.prenom as membre_prenom,
                    m.numero_licence, m.categorie as membre_categorie
             FROM presences p
             JOIN membres_academie m ON p.membre_id = m.id
             WHERE p.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Présences d'une séance
     */
    public static function getBySeance(int $seanceId): array
    {
        return Database::fetchAll(
            "SELECT p.*, m.nom as membre_nom, m.prenom as membre_prenom,
                    m.numero_licence, m.photo
             FROM presences p
             JOIN membres_academie m ON p.membre_id = m.id
             WHERE p.seance_id = :seance_id
             ORDER BY m.nom, m.prenom",
            ['seance_id' => $seanceId]
        );
    }

    /**
     * Historique d'un membre
     */
    public static function getByMembre(int $membreId, int $limit = 50): array
    {
        return Database::fetchAll(
            "SELECT p.*, s.date_seance, s.heure_debut, s.heure_fin,
                    s.type_seance, s.categorie as seance_categorie
             FROM presences p
             JOIN seances s ON p.seance_id = s.id
             WHERE p.membre_id = :membre_id
             ORDER BY s.date_seance DESC, s.heure_debut DESC
             LIMIT $limit",
            ['membre_id' => $membreId]
        );
    }

    /**
     * Initialiser les présences pour une séance (tous les membres de la catégorie)
     */
    public static function initializeForSeance(int $seanceId): int
    {
        $seance = Database::fetchOne(
            "SELECT categorie FROM seances WHERE id = :id",
            ['id' => $seanceId]
        );

        if (!$seance) {
            throw new Exception('Séance introuvable');
        }

        // Récupérer les membres actifs de cette catégorie
        $membres = Database::fetchAll(
            "SELECT id FROM membres_academie
             WHERE categorie = :categorie AND statut = 'actif'",
            ['categorie' => $seance['categorie']]
        );

        $count = 0;
        foreach ($membres as $membre) {
            // Vérifier si déjà inscrit
            $exists = Database::fetchOne(
                "SELECT id FROM presences WHERE seance_id = :seance_id AND membre_id = :membre_id",
                ['seance_id' => $seanceId, 'membre_id' => $membre['id']]
            );

            if (!$exists) {
                Database::insert('presences', [
                    'seance_id' => $seanceId,
                    'membre_id' => $membre['id'],
                    'statut' => 'absent' // Par défaut absent, sera mis à jour
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Enregistrer la présence d'un membre
     */
    public static function record(int $seanceId, int $membreId, string $statut, ?string $commentaire = null): int
    {
        // Vérifier si existe déjà
        $existing = Database::fetchOne(
            "SELECT id FROM presences WHERE seance_id = :seance_id AND membre_id = :membre_id",
            ['seance_id' => $seanceId, 'membre_id' => $membreId]
        );

        if ($existing) {
            // Mettre à jour
            Database::update('presences', [
                'statut' => $statut,
                'commentaire' => $commentaire,
                'heure_arrivee' => $statut === 'present' ? date('H:i:s') : null
            ], 'id = :id', ['id' => $existing['id']]);

            return $existing['id'];
        }

        // Créer
        return Database::insert('presences', [
            'seance_id' => $seanceId,
            'membre_id' => $membreId,
            'statut' => $statut,
            'commentaire' => $commentaire,
            'heure_arrivee' => $statut === 'present' ? date('H:i:s') : null
        ]);
    }

    /**
     * Marquer tous présents/absents
     */
    public static function markAll(int $seanceId, string $statut): int
    {
        return Database::query(
            "UPDATE presences SET statut = :statut WHERE seance_id = :seance_id",
            ['statut' => $statut, 'seance_id' => $seanceId]
        )->rowCount();
    }

    /**
     * Supprimer une présence
     */
    public static function delete(int $id): bool
    {
        return Database::delete('presences', 'id = :id', ['id' => $id]);
    }

    /**
     * Statistiques d'un membre
     */
    public static function getMembreStats(int $membreId, ?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-01-01');
        $dateFin = $dateFin ?? date('Y-m-d');

        $stats = Database::fetchOne(
            "SELECT
                COUNT(*) as total_seances,
                SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presents,
                SUM(CASE WHEN p.statut = 'absent' THEN 1 ELSE 0 END) as absents,
                SUM(CASE WHEN p.statut = 'retard' THEN 1 ELSE 0 END) as retards,
                SUM(CASE WHEN p.statut = 'excuse' THEN 1 ELSE 0 END) as excuses,
                SUM(CASE WHEN p.statut = 'blesse' THEN 1 ELSE 0 END) as blesses
             FROM presences p
             JOIN seances s ON p.seance_id = s.id
             WHERE p.membre_id = :membre_id
               AND s.date_seance BETWEEN :debut AND :fin",
            ['membre_id' => $membreId, 'debut' => $dateDebut, 'fin' => $dateFin]
        );

        $stats['taux_presence'] = $stats['total_seances'] > 0
            ? round(($stats['presents'] / $stats['total_seances']) * 100, 1)
            : 0;

        return $stats;
    }

    /**
     * Statistiques d'une séance
     */
    public static function getSeanceStats(int $seanceId): array
    {
        return Database::fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN statut = 'present' THEN 1 ELSE 0 END) as presents,
                SUM(CASE WHEN statut = 'absent' THEN 1 ELSE 0 END) as absents,
                SUM(CASE WHEN statut = 'retard' THEN 1 ELSE 0 END) as retards,
                SUM(CASE WHEN statut = 'excuse' THEN 1 ELSE 0 END) as excuses,
                SUM(CASE WHEN statut = 'blesse' THEN 1 ELSE 0 END) as blesses
             FROM presences
             WHERE seance_id = :seance_id",
            ['seance_id' => $seanceId]
        );
    }

    /**
     * Top des absences (membres les plus absents)
     */
    public static function getTopAbsents(int $limit = 10, ?string $categorie = null): array
    {
        $catFilter = '';
        $params = ['mois' => date('Y-m-01')];

        if ($categorie) {
            $catFilter = 'AND m.categorie = :categorie';
            $params['categorie'] = $categorie;
        }

        return Database::fetchAll(
            "SELECT m.id, m.nom, m.prenom, m.numero_licence, m.categorie,
                    COUNT(*) as total_seances,
                    SUM(CASE WHEN p.statut = 'absent' THEN 1 ELSE 0 END) as absences,
                    ROUND(SUM(CASE WHEN p.statut = 'absent' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as taux_absence
             FROM membres_academie m
             JOIN presences p ON m.id = p.membre_id
             JOIN seances s ON p.seance_id = s.id
             WHERE s.date_seance >= :mois $catFilter
             GROUP BY m.id
             HAVING absences > 0
             ORDER BY taux_absence DESC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Membres assidus (meilleur taux de présence)
     */
    public static function getTopPresents(int $limit = 10, ?string $categorie = null): array
    {
        $catFilter = '';
        $params = ['mois' => date('Y-m-01')];

        if ($categorie) {
            $catFilter = 'AND m.categorie = :categorie';
            $params['categorie'] = $categorie;
        }

        return Database::fetchAll(
            "SELECT m.id, m.nom, m.prenom, m.numero_licence, m.categorie,
                    COUNT(*) as total_seances,
                    SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presences,
                    ROUND(SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 1) as taux_presence
             FROM membres_academie m
             JOIN presences p ON m.id = p.membre_id
             JOIN seances s ON p.seance_id = s.id
             WHERE s.date_seance >= :mois $catFilter
             GROUP BY m.id
             HAVING total_seances >= 3
             ORDER BY taux_presence DESC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Évolution mensuelle des présences
     */
    public static function getMonthlyEvolution(?string $categorie = null): array
    {
        $catFilter = '';
        $params = [];

        if ($categorie) {
            $catFilter = 'AND s.categorie = :categorie';
            $params['categorie'] = $categorie;
        }

        return Database::fetchAll(
            "SELECT
                DATE_FORMAT(s.date_seance, '%Y-%m') as mois,
                COUNT(DISTINCT s.id) as nb_seances,
                COUNT(p.id) as total_presences,
                SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presents,
                ROUND(SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) * 100.0 / COUNT(p.id), 1) as taux
             FROM seances s
             LEFT JOIN presences p ON s.id = p.seance_id
             WHERE s.date_seance >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH) $catFilter
             GROUP BY DATE_FORMAT(s.date_seance, '%Y-%m')
             ORDER BY mois ASC",
            $params
        );
    }
}
