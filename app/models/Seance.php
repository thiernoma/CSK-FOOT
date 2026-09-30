<?php
/**
 * Modèle Seance - Gestion des séances d'entraînement
 * Complexe Sportif Kaira
 */

class Seance
{
    /**
     * Catégories d'âge
     */
    public static function getCategories(): array
    {
        return [
            'U6' => 'U6 (moins de 6 ans)',
            'U8' => 'U8 (moins de 8 ans)',
            'U10' => 'U10 (moins de 10 ans)',
            'U12' => 'U12 (moins de 12 ans)',
            'U14' => 'U14 (moins de 14 ans)',
            'U16' => 'U16 (moins de 16 ans)',
            'U18' => 'U18 (moins de 18 ans)',
            'seniors' => 'Seniors'
        ];
    }

    /**
     * Types de séance
     */
    public static function getTypes(): array
    {
        return [
            'entrainement' => 'Entraînement',
            'match_amical' => 'Match amical',
            'match_officiel' => 'Match officiel',
            'stage' => 'Stage',
            'tournoi' => 'Tournoi',
            'evaluation' => 'Évaluation'
        ];
    }

    /**
     * Récupérer une séance par ID
     */
    public static function getById(int $id): ?array
    {
        return Database::fetchOne(
            "SELECT s.*, t.nom as terrain_nom,
                    CONCAT(e.nom, ' ', e.prenom) as entraineur_nom,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id) as nb_inscrits,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id AND statut = 'present') as nb_presents
             FROM seances s
             LEFT JOIN terrains t ON s.terrain_id = t.id
             LEFT JOIN entraineurs e ON s.entraineur_id = e.id
             WHERE s.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Liste des séances avec filtres
     */
    public static function getAll(array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['categorie'])) {
            $where[] = 's.categorie = :categorie';
            $params['categorie'] = $filters['categorie'];
        }

        if (!empty($filters['type'])) {
            $where[] = 's.type_seance = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['entraineur_id'])) {
            $where[] = 's.entraineur_id = :entraineur_id';
            $params['entraineur_id'] = $filters['entraineur_id'];
        }

        if (!empty($filters['terrain_id'])) {
            $where[] = 's.terrain_id = :terrain_id';
            $params['terrain_id'] = $filters['terrain_id'];
        }

        if (!empty($filters['date_debut'])) {
            $where[] = 's.date_seance >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $where[] = 's.date_seance <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }

        if (!empty($filters['statut'])) {
            $where[] = 's.statut = :statut';
            $params['statut'] = $filters['statut'];
        }

        $whereClause = implode(' AND ', $where);

        return Database::fetchAll(
            "SELECT s.*, t.nom as terrain_nom,
                    CONCAT(e.nom, ' ', e.prenom) as entraineur_nom,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id) as nb_inscrits,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id AND statut = 'present') as nb_presents
             FROM seances s
             LEFT JOIN terrains t ON s.terrain_id = t.id
             LEFT JOIN entraineurs e ON s.entraineur_id = e.id
             WHERE $whereClause
             ORDER BY s.date_seance DESC, s.heure_debut ASC
             LIMIT $limit OFFSET $offset",
            $params
        );
    }

    /**
     * Compter les séances avec filtres
     */
    public static function count(array $filters = []): int
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['categorie'])) {
            $where[] = 'categorie = :categorie';
            $params['categorie'] = $filters['categorie'];
        }

        if (!empty($filters['type'])) {
            $where[] = 'type_seance = :type';
            $params['type'] = $filters['type'];
        }

        if (!empty($filters['entraineur_id'])) {
            $where[] = 'entraineur_id = :entraineur_id';
            $params['entraineur_id'] = $filters['entraineur_id'];
        }

        if (!empty($filters['date_debut'])) {
            $where[] = 'date_seance >= :date_debut';
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $where[] = 'date_seance <= :date_fin';
            $params['date_fin'] = $filters['date_fin'];
        }

        if (!empty($filters['statut'])) {
            $where[] = 'statut = :statut';
            $params['statut'] = $filters['statut'];
        }

        $whereClause = implode(' AND ', $where);

        return (int)Database::fetchOne(
            "SELECT COUNT(*) as total FROM seances WHERE $whereClause",
            $params
        )['total'];
    }

    /**
     * Créer une séance
     */
    public static function create(array $data): int
    {
        return Database::insert('seances', [
            'date_seance' => $data['date_seance'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'categorie' => $data['categorie'],
            'type_seance' => $data['type_seance'] ?? 'entrainement',
            'terrain_id' => $data['terrain_id'] ?: null,
            'entraineur_id' => $data['entraineur_id'] ?: null,
            'description' => $data['description'] ?? null,
            'statut' => $data['statut'] ?? 'planifiee'
        ]);
    }

    /**
     * Mettre à jour une séance
     */
    public static function update(int $id, array $data): bool
    {
        return Database::update('seances', [
            'date_seance' => $data['date_seance'],
            'heure_debut' => $data['heure_debut'],
            'heure_fin' => $data['heure_fin'],
            'categorie' => $data['categorie'],
            'type_seance' => $data['type_seance'] ?? 'entrainement',
            'terrain_id' => $data['terrain_id'] ?: null,
            'entraineur_id' => $data['entraineur_id'] ?: null,
            'description' => $data['description'] ?? null,
            'statut' => $data['statut'] ?? 'planifiee'
        ], 'id = :id', ['id' => $id]);
    }

    /**
     * Supprimer une séance
     */
    public static function delete(int $id): bool
    {
        // Supprimer d'abord les présences associées
        Database::query("DELETE FROM presences WHERE seance_id = :id", ['id' => $id]);

        return Database::delete('seances', 'id = :id', ['id' => $id]);
    }

    /**
     * Dupliquer une séance pour une nouvelle date
     */
    public static function duplicate(int $id, string $newDate): int
    {
        $seance = self::getById($id);
        if (!$seance) {
            throw new Exception('Séance source introuvable');
        }

        return self::create([
            'date_seance' => $newDate,
            'heure_debut' => $seance['heure_debut'],
            'heure_fin' => $seance['heure_fin'],
            'categorie' => $seance['categorie'],
            'type_seance' => $seance['type_seance'],
            'terrain_id' => $seance['terrain_id'],
            'entraineur_id' => $seance['entraineur_id'],
            'description' => $seance['description'],
            'statut' => 'planifiee'
        ]);
    }

    /**
     * Générer des séances récurrentes
     */
    public static function generateRecurring(array $data, array $jours, string $dateDebut, string $dateFin): int
    {
        $count = 0;
        $current = new DateTime($dateDebut);
        $end = new DateTime($dateFin);

        while ($current <= $end) {
            $dayOfWeek = (int)$current->format('N'); // 1=Lundi, 7=Dimanche

            if (in_array($dayOfWeek, $jours)) {
                $data['date_seance'] = $current->format('Y-m-d');
                self::create($data);
                $count++;
            }

            $current->modify('+1 day');
        }

        return $count;
    }

    /**
     * Séances à venir (aujourd'hui et plus)
     */
    public static function getUpcoming(int $limit = 10, ?string $categorie = null): array
    {
        $params = ['today' => date('Y-m-d')];
        $catFilter = '';

        if ($categorie) {
            $catFilter = 'AND s.categorie = :categorie';
            $params['categorie'] = $categorie;
        }

        return Database::fetchAll(
            "SELECT s.*, t.nom as terrain_nom,
                    CONCAT(e.nom, ' ', e.prenom) as entraineur_nom
             FROM seances s
             LEFT JOIN terrains t ON s.terrain_id = t.id
             LEFT JOIN entraineurs e ON s.entraineur_id = e.id
             WHERE s.date_seance >= :today AND s.statut = 'planifiee' $catFilter
             ORDER BY s.date_seance ASC, s.heure_debut ASC
             LIMIT $limit",
            $params
        );
    }

    /**
     * Séances du jour
     */
    public static function getToday(): array
    {
        return Database::fetchAll(
            "SELECT s.*, t.nom as terrain_nom,
                    CONCAT(e.nom, ' ', e.prenom) as entraineur_nom,
                    (SELECT COUNT(*) FROM presences WHERE seance_id = s.id) as nb_inscrits
             FROM seances s
             LEFT JOIN terrains t ON s.terrain_id = t.id
             LEFT JOIN entraineurs e ON s.entraineur_id = e.id
             WHERE s.date_seance = CURDATE()
             ORDER BY s.heure_debut ASC"
        );
    }

    /**
     * Planning hebdomadaire pour FullCalendar
     */
    public static function getForCalendar(string $start, string $end): array
    {
        $seances = Database::fetchAll(
            "SELECT s.*, t.nom as terrain_nom,
                    CONCAT(e.nom, ' ', e.prenom) as entraineur_nom
             FROM seances s
             LEFT JOIN terrains t ON s.terrain_id = t.id
             LEFT JOIN entraineurs e ON s.entraineur_id = e.id
             WHERE s.date_seance BETWEEN :start AND :end
             ORDER BY s.date_seance, s.heure_debut",
            ['start' => $start, 'end' => $end]
        );

        $events = [];
        foreach ($seances as $s) {
            $events[] = [
                'id' => $s['id'],
                'title' => $s['categorie'] . ' - ' . self::getTypes()[$s['type_seance']],
                'start' => opDateTimeIso($s['date_seance'], $s['heure_debut']),
                'end'   => opDateTimeIso($s['date_seance'], $s['heure_fin']),
                'backgroundColor' => self::getCategorieColor($s['categorie']),
                'extendedProps' => [
                    'categorie' => $s['categorie'],
                    'type' => $s['type_seance'],
                    'terrain' => $s['terrain_nom'],
                    'entraineur' => $s['entraineur_nom'],
                    'statut' => $s['statut']
                ]
            ];
        }

        return $events;
    }

    /**
     * Couleur par catégorie
     */
    public static function getCategorieColor(string $categorie): string
    {
        return match($categorie) {
            'U6' => '#4CAF50',
            'U8' => '#8BC34A',
            'U10' => '#CDDC39',
            'U12' => '#FFC107',
            'U14' => '#FF9800',
            'U16' => '#FF5722',
            'U18' => '#E91E63',
            'seniors' => '#9C27B0',
            default => '#607D8B'
        };
    }

    /**
     * Statistiques des séances
     */
    public static function getStats(?string $dateDebut = null, ?string $dateFin = null): array
    {
        $dateDebut = $dateDebut ?? date('Y-m-01');
        $dateFin = $dateFin ?? date('Y-m-d');

        return [
            'total_seances' => Database::fetchOne(
                "SELECT COUNT(*) as total FROM seances
                 WHERE date_seance BETWEEN :debut AND :fin",
                ['debut' => $dateDebut, 'fin' => $dateFin]
            )['total'],

            'seances_terminees' => Database::fetchOne(
                "SELECT COUNT(*) as total FROM seances
                 WHERE date_seance BETWEEN :debut AND :fin AND statut = 'terminee'",
                ['debut' => $dateDebut, 'fin' => $dateFin]
            )['total'],

            'taux_presence_moyen' => Database::fetchOne(
                "SELECT ROUND(AVG(
                    CASE WHEN total > 0 THEN (presents * 100.0 / total) ELSE 0 END
                 ), 1) as taux
                 FROM (
                     SELECT s.id,
                            COUNT(p.id) as total,
                            SUM(CASE WHEN p.statut = 'present' THEN 1 ELSE 0 END) as presents
                     FROM seances s
                     LEFT JOIN presences p ON s.id = p.seance_id
                     WHERE s.date_seance BETWEEN :debut AND :fin
                     GROUP BY s.id
                 ) as stats",
                ['debut' => $dateDebut, 'fin' => $dateFin]
            )['taux'] ?? 0,

            'par_categorie' => Database::fetchAll(
                "SELECT categorie, COUNT(*) as total
                 FROM seances
                 WHERE date_seance BETWEEN :debut AND :fin
                 GROUP BY categorie
                 ORDER BY total DESC",
                ['debut' => $dateDebut, 'fin' => $dateFin]
            ),

            'par_type' => Database::fetchAll(
                "SELECT type_seance, COUNT(*) as total
                 FROM seances
                 WHERE date_seance BETWEEN :debut AND :fin
                 GROUP BY type_seance
                 ORDER BY total DESC",
                ['debut' => $dateDebut, 'fin' => $dateFin]
            )
        ];
    }
}
