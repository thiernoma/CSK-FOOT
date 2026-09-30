<?php
/**
 * Modèle Reservation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/Terrain.php';
require_once __DIR__ . '/Client.php';

class Reservation {

    /**
     * Récupérer toutes les réservations avec filtres
     */
    public static function getAll(array $filters = [], int $limit = 0, int $offset = 0): array {
        $sql = "SELECT r.*, t.nom as terrain_nom, t.type as terrain_type,
                       CONCAT(c.prenom, ' ', c.nom) as client_nom, c.telephone as client_telephone
                FROM reservations r
                JOIN terrains t ON r.terrain_id = t.id
                JOIN clients c ON r.client_id = c.id
                WHERE 1=1";
        $params = [];

        if (!empty($filters['date'])) {
            $sql .= " AND r.date_reservation = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['date_debut'])) {
            $sql .= " AND r.date_reservation >= :date_debut";
            $params['date_debut'] = $filters['date_debut'];
        }

        if (!empty($filters['date_fin'])) {
            $sql .= " AND r.date_reservation <= :date_fin";
            $params['date_fin'] = $filters['date_fin'];
        }

        if (!empty($filters['terrain_id'])) {
            $sql .= " AND r.terrain_id = :terrain_id";
            $params['terrain_id'] = $filters['terrain_id'];
        }

        if (!empty($filters['client_id'])) {
            $sql .= " AND r.client_id = :client_id";
            $params['client_id'] = $filters['client_id'];
        }

        if (!empty($filters['statut_reservation'])) {
            $sql .= " AND r.statut_reservation = :statut_reservation";
            $params['statut_reservation'] = $filters['statut_reservation'];
        }

        if (!empty($filters['statut_paiement'])) {
            $sql .= " AND r.statut_paiement = :statut_paiement";
            $params['statut_paiement'] = $filters['statut_paiement'];
        }

        if (!empty($filters['recherche'])) {
            $searchTerm = '%' . $filters['recherche'] . '%';
            $sql .= " AND (r.numero_ticket LIKE :recherche1 OR c.nom LIKE :recherche2 OR c.telephone LIKE :recherche3)";
            $params['recherche1'] = $searchTerm;
            $params['recherche2'] = $searchTerm;
            $params['recherche3'] = $searchTerm;
        }

        $sql .= " ORDER BY r.date_reservation DESC, r.heure_debut DESC";

        if ($limit > 0) {
            $sql .= " LIMIT {$offset}, {$limit}";
        }

        return Database::fetchAll($sql, $params);
    }

    /**
     * Compter les réservations
     */
    public static function count(array $filters = []): int {
        $sql = "SELECT COUNT(*) as total FROM reservations r WHERE 1=1";
        $params = [];

        if (!empty($filters['date'])) {
            $sql .= " AND r.date_reservation = :date";
            $params['date'] = $filters['date'];
        }

        if (!empty($filters['terrain_id'])) {
            $sql .= " AND r.terrain_id = :terrain_id";
            $params['terrain_id'] = $filters['terrain_id'];
        }

        if (!empty($filters['statut_reservation'])) {
            $sql .= " AND r.statut_reservation = :statut_reservation";
            $params['statut_reservation'] = $filters['statut_reservation'];
        }

        $result = Database::fetchOne($sql, $params);
        return (int)($result['total'] ?? 0);
    }

    /**
     * Récupérer une réservation par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT r.*, t.nom as terrain_nom, t.type as terrain_type, t.prix_heure,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom, c.telephone as client_telephone, c.email as client_email,
                    u.nom as cree_par_nom,
                    ur.nom as remise_par_nom
             FROM reservations r
             JOIN terrains t ON r.terrain_id = t.id
             JOIN clients c ON r.client_id = c.id
             LEFT JOIN users u ON r.cree_par = u.id
             LEFT JOIN users ur ON r.remise_par = ur.id
             WHERE r.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Récupérer une réservation par numéro de ticket
     */
    public static function getByTicket(string $ticket): ?array {
        return Database::fetchOne(
            "SELECT r.*, t.nom as terrain_nom, CONCAT(c.prenom, ' ', c.nom) as client_nom
             FROM reservations r
             JOIN terrains t ON r.terrain_id = t.id
             JOIN clients c ON r.client_id = c.id
             WHERE r.numero_ticket = :ticket",
            ['ticket' => $ticket]
        );
    }

    /**
     * Générer le prochain numéro de ticket
     */
    public static function generateTicketNumber(): string {
        $year = date('Y');

        // Mettre à jour et récupérer le compteur
        Database::query(
            "INSERT INTO compteur_tickets (annee, dernier_numero)
             VALUES (:annee, 1)
             ON DUPLICATE KEY UPDATE dernier_numero = dernier_numero + 1",
            ['annee' => $year]
        );

        $result = Database::fetchOne(
            "SELECT dernier_numero FROM compteur_tickets WHERE annee = :annee",
            ['annee' => $year]
        );

        $numero = $result['dernier_numero'] ?? 1;
        return $year . '-' . str_pad($numero, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Ajuster l'heure de début si elle coïncide avec l'heure de fin d'une autre réservation
     * Décale selon le paramètre temps_transition pour permettre une transition entre les réservations
     */
    public static function ajusterHeureDebut(array $data): array {
        // Récupérer le temps de transition depuis les paramètres (par défaut 10 minutes)
        $tempsTransition = (int) getParam('temps_transition', '10');

        // Si le temps de transition est 0, pas de décalage
        if ($tempsTransition <= 0) {
            return $data;
        }

        // Vérifier s'il existe une réservation dont l'heure de fin = heure de début demandée
        $reservationExistante = Database::fetchOne(
            "SELECT id, heure_fin FROM reservations
             WHERE terrain_id = :terrain_id
             AND date_reservation = :date_reservation
             AND heure_fin = :heure_debut
             AND statut_reservation != 'annulee'",
            [
                'terrain_id' => $data['terrain_id'],
                'date_reservation' => $data['date_reservation'],
                'heure_debut' => $data['heure_debut']
            ]
        );

        // Si une réservation existe avec cette heure de fin, décaler début ET fin selon le paramètre.
        // Utilise opTimeToMinutes/minutesToOpTime pour supporter les op-times >= 24h.
        if ($reservationExistante) {
            $debutMin = opTimeToMinutes($data['heure_debut']) + $tempsTransition;
            $finMin   = opTimeToMinutes($data['heure_fin'])   + $tempsTransition;
            $data['heure_debut'] = minutesToOpTime($debutMin) . ':00';
            $data['heure_fin']   = minutesToOpTime($finMin)   . ':00';
        }

        return $data;
    }

    /**
     * Créer une réservation
     */
    /**
     * Résoudre la session caisse active d'un utilisateur (helper privé).
     * Retourne l'ID de la session ouverte la plus récente, ou null.
     */
    private static function resolveActiveSession(?int $userId): ?int {
        if (!$userId) return null;
        $row = Database::fetchOne(
            "SELECT id FROM clotures_caisse
             WHERE utilisateur_id = :uid AND statut = 'ouverte'
             ORDER BY heure_ouverture DESC LIMIT 1",
            ['uid' => $userId]
        );
        return isset($row['id']) ? (int)$row['id'] : null;
    }

    public static function create(array $data): array {
        // Normaliser en op-time (heures > 24h pour les créneaux après minuit)
        $data['heure_debut'] = wallToOp($data['heure_debut']);
        $data['heure_fin']   = wallToOp($data['heure_fin']);

        // Ajuster l'heure de début selon le temps de transition configuré
        $data = self::ajusterHeureDebut($data);

        // Période de fermeture exceptionnelle (message explicite)
        if (isDateFermee($data['date_reservation'])) {
            return ['success' => false, 'message' => messageFermeture() . '.'];
        }

        // Vérifier la disponibilité
        if (!Terrain::isAvailable($data['terrain_id'], $data['date_reservation'], $data['heure_debut'], $data['heure_fin'])) {
            return ['success' => false, 'message' => 'Ce créneau n\'est plus disponible.'];
        }

        // Calculer la durée (supporte les op-times >= 24h)
        $dureeHeures = opDurationHours($data['heure_debut'], $data['heure_fin']);

        if ($dureeHeures <= 0) {
            return ['success' => false, 'message' => 'L\'heure de fin doit être après l\'heure de début.'];
        }

        // Calculer le montant si non spécifié
        if (empty($data['montant'])) {
            $data['montant'] = Terrain::calculatePrice(
                $data['terrain_id'],
                $data['date_reservation'],
                $data['heure_debut'],
                $data['heure_fin']
            );
        }

        // Générer le token QR
        $qrToken = self::generateQRToken();

        try {
            Database::beginTransaction();

            // Générer le numéro de ticket À L'INTÉRIEUR de la transaction
            $numeroTicket = self::generateTicketNumber();

            // Déterminer le statut initial
            $statutInitial = 'confirmee';
            $acompteRequis = !empty($data['acompte_requis']) ? (float)$data['acompte_requis'] : null;

            // Insérer la réservation
            $reservationId = Database::insert('reservations', [
                'numero_ticket' => $numeroTicket,
                'client_id' => $data['client_id'],
                'terrain_id' => $data['terrain_id'],
                'date_reservation' => $data['date_reservation'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'duree_heures' => $dureeHeures,
                'montant' => $data['montant'],
                'montant_paye' => 0,
                'acompte_requis' => $acompteRequis,
                'acompte_paye' => 0,
                'statut_paiement' => 'en_attente',
                'mode_paiement' => $data['mode_paiement'] ?? null,
                'statut_reservation' => $statutInitial,
                'notes' => $data['notes'] ?? null,
                'cree_par' => $data['cree_par'] ?? null,
                'qr_code_token' => $qrToken
            ]);

            // Si paiement immédiat - insérer directement sans appeler addPayment
            if (!empty($data['paiement_immediat']) && $data['paiement_immediat'] > 0) {
                $montantPaiement = min($data['paiement_immediat'], $data['montant']);

                // Insérer le paiement
                $catReservation = Database::fetchOne(
                    "SELECT id FROM categories_encaissement WHERE code = 'reservation' LIMIT 1"
                );
                // Résoudre automatiquement la session caisse active si pas fournie explicitement
                $sessionCaisseId = $data['session_caisse_id']
                    ?? self::resolveActiveSession($data['cree_par'] ?? null);

                Database::insert('paiements', [
                    'reservation_id' => $reservationId,
                    'categorie_id' => $catReservation['id'] ?? null,
                    'montant' => $montantPaiement,
                    'mode_paiement' => $data['mode_paiement'],
                    'type_paiement' => 'paiement',
                    'recu_par' => $data['cree_par'] ?? null,
                    'session_caisse_id' => $sessionCaisseId
                ]);

                // Mettre à jour la réservation avec le paiement
                $statutPaiement = $montantPaiement >= $data['montant'] ? 'paye' : 'partiel';
                Database::update('reservations', [
                    'montant_paye' => $montantPaiement,
                    'statut_paiement' => $statutPaiement
                ], 'id = :id', ['id' => $reservationId]);
            }

            // Mettre à jour les stats client
            Client::updateStats($data['client_id']);

            Database::commit();

            return [
                'success' => true,
                'id' => $reservationId,
                'numero_ticket' => $numeroTicket,
                'message' => 'Réservation créée avec succès.'
            ];

        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Mettre à jour une réservation
     */
    public static function update(int $id, array $data): array {
        $reservation = self::getById($id);
        if (!$reservation) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        // Normaliser les heures fournies en op-time
        if (isset($data['heure_debut'])) $data['heure_debut'] = wallToOp($data['heure_debut']);
        if (isset($data['heure_fin']))   $data['heure_fin']   = wallToOp($data['heure_fin']);

        // Si changement de créneau, vérifier la disponibilité
        $terrainId = $data['terrain_id'] ?? $reservation['terrain_id'];
        $date = $data['date_reservation'] ?? $reservation['date_reservation'];
        $heureDebut = $data['heure_debut'] ?? $reservation['heure_debut'];
        $heureFin = $data['heure_fin'] ?? $reservation['heure_fin'];

        if ($terrainId != $reservation['terrain_id'] ||
            $date != $reservation['date_reservation'] ||
            $heureDebut != $reservation['heure_debut'] ||
            $heureFin != $reservation['heure_fin']) {

            if (!Terrain::isAvailable($terrainId, $date, $heureDebut, $heureFin, $id)) {
                return ['success' => false, 'message' => 'Ce créneau n\'est plus disponible.'];
            }
        }

        // Calculer la durée si les heures changent (supporte op-time >= 24h)
        if (isset($data['heure_debut']) || isset($data['heure_fin'])) {
            $data['duree_heures'] = opDurationHours($heureDebut, $heureFin);
        }

        // Recalculer le montant si nécessaire
        if (isset($data['recalculer_montant']) && $data['recalculer_montant']) {
            $data['montant'] = Terrain::calculatePrice($terrainId, $date, $heureDebut, $heureFin);
        }

        $fields = [];
        $allowedFields = ['terrain_id', 'date_reservation', 'heure_debut', 'heure_fin',
                          'duree_heures', 'montant', 'statut_reservation', 'notes', 'modifie_par'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[$field] = $data[$field];
            }
        }

        if (!empty($fields)) {
            Database::update('reservations', $fields, 'id = :id', ['id' => $id]);
        }

        return ['success' => true, 'message' => 'Réservation mise à jour.'];
    }

    /**
     * Annuler une réservation
     */
    public static function cancel(int $id, string $motif, int $userId): array {
        $reservation = self::getById($id);
        if (!$reservation) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        if ($reservation['statut_reservation'] === 'annulee') {
            return ['success' => false, 'message' => 'Cette réservation est déjà annulée.'];
        }

        Database::update('reservations', [
            'statut_reservation' => 'annulee',
            'motif_annulation' => $motif,
            'modifie_par' => $userId
        ], 'id = :id', ['id' => $id]);

        // Mettre à jour les stats client
        Client::updateStats($reservation['client_id']);

        return ['success' => true, 'message' => 'Réservation annulée.'];
    }

    /**
     * Ajouter un paiement
     */
    public static function addPayment(int $reservationId, array $data): array {
        $reservation = self::getById($reservationId);
        if (!$reservation) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        $montantNet = $reservation['montant'] - ($reservation['remise'] ?? 0);
        $resteAPayer = $montantNet - $reservation['montant_paye'];
        $montant = min($data['montant'], $resteAPayer);

        if ($montant <= 0) {
            return ['success' => false, 'message' => 'Cette réservation est déjà payée.'];
        }

        try {
            Database::beginTransaction();

            // Insérer le paiement (résolution auto de la session active si pas fournie)
            $catReservation = Database::fetchOne(
                "SELECT id FROM categories_encaissement WHERE code = 'reservation' LIMIT 1"
            );
            $sessionCaisseId = $data['session_caisse_id']
                ?? self::resolveActiveSession($data['recu_par'] ?? null);
            $paiementId = Database::insert('paiements', [
                'reservation_id' => $reservationId,
                'categorie_id' => $catReservation['id'] ?? null,
                'montant' => $montant,
                'mode_paiement' => $data['mode_paiement'],
                'reference' => $data['reference'] ?? null,
                'type_paiement' => 'paiement',
                'notes' => $data['notes'] ?? null,
                'recu_par' => $data['recu_par'] ?? null,
                'session_caisse_id' => $sessionCaisseId
            ]);

            // Mettre à jour le montant payé et le statut
            $nouveauMontantPaye = $reservation['montant_paye'] + $montant;
            $nouveauStatut = $nouveauMontantPaye >= $montantNet ? 'paye' : 'partiel';

            // Vérifier si l'acompte est payé
            $acompteRequis = $reservation['acompte_requis'] ?? 0;
            $acomptePaye = ($acompteRequis > 0 && $nouveauMontantPaye >= $acompteRequis) ? 1 : 0;
            $dateAcompte = ($acomptePaye && !$reservation['acompte_paye']) ? date('Y-m-d H:i:s') : $reservation['date_acompte'];

            Database::update('reservations', [
                'montant_paye' => $nouveauMontantPaye,
                'statut_paiement' => $nouveauStatut,
                'mode_paiement' => $data['mode_paiement'],
                'acompte_paye' => $acomptePaye,
                'date_acompte' => $dateAcompte
            ], 'id = :id', ['id' => $reservationId]);

            // Mettre à jour les stats client
            Client::updateStats($reservation['client_id']);

            Database::commit();

            return [
                'success' => true,
                'paiement_id' => $paiementId,
                'message' => 'Paiement enregistré avec succès.'
            ];

        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Appliquer une remise à une réservation
     */
    public static function applyRemise(int $reservationId, float $montant, string $motif, int $userId): array {
        $reservation = self::getById($reservationId);
        if (!$reservation) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        if ($reservation['statut_reservation'] === 'annulee') {
            return ['success' => false, 'message' => 'Impossible d\'appliquer une remise sur une réservation annulée.'];
        }

        if ($montant < 0) {
            return ['success' => false, 'message' => 'Le montant de la remise doit être positif.'];
        }

        // La remise ne peut pas dépasser ce qui reste à facturer (montant - déjà payé)
        $maxRemise = $reservation['montant'] - $reservation['montant_paye'];
        if ($montant > $maxRemise) {
            return ['success' => false, 'message' => 'La remise ne peut pas dépasser ' . formatMoney($maxRemise) . ' (montant non encore payé).'];
        }

        try {
            Database::beginTransaction();

            Database::update('reservations', [
                'remise' => $montant,
                'motif_remise' => $motif,
                'remise_par' => $userId,
                'date_remise' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $reservationId]);

            // Recalculer le statut de paiement après remise
            $montantNet = $reservation['montant'] - $montant;
            $nouveauStatut = 'en_attente';
            if ($reservation['montant_paye'] >= $montantNet && $montantNet > 0) {
                $nouveauStatut = 'paye';
            } elseif ($reservation['montant_paye'] > 0) {
                $nouveauStatut = 'partiel';
            } elseif ($montantNet <= 0) {
                $nouveauStatut = 'paye';
            }

            Database::update('reservations', [
                'statut_paiement' => $nouveauStatut
            ], 'id = :id', ['id' => $reservationId]);

            Database::commit();

            return ['success' => true, 'message' => 'Remise appliquée avec succès.'];

        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Enregistrer un remboursement
     */
    public static function addRefund(int $reservationId, array $data): array {
        $reservation = self::getById($reservationId);
        if (!$reservation) {
            return ['success' => false, 'message' => 'Réservation introuvable.'];
        }

        $montant = (float)($data['montant'] ?? 0);
        $modePaiement = $data['mode_paiement'] ?? null;
        $motif = trim($data['motif'] ?? '');

        if ($montant <= 0) {
            return ['success' => false, 'message' => 'Le montant du remboursement doit être supérieur à 0.'];
        }

        if ($montant > $reservation['montant_paye']) {
            return ['success' => false, 'message' => 'Le remboursement ne peut pas dépasser le montant déjà payé (' . formatMoney($reservation['montant_paye']) . ').'];
        }

        if (empty($modePaiement)) {
            return ['success' => false, 'message' => 'Veuillez sélectionner un mode de remboursement.'];
        }

        if (empty($motif)) {
            return ['success' => false, 'message' => 'Le motif du remboursement est obligatoire.'];
        }

        try {
            Database::beginTransaction();

            $catReservation = Database::fetchOne(
                "SELECT id FROM categories_encaissement WHERE code = 'reservation' LIMIT 1"
            );
            $sessionCaisseId = $data['session_caisse_id']
                ?? self::resolveActiveSession($data['recu_par'] ?? null);
            $paiementId = Database::insert('paiements', [
                'reservation_id' => $reservationId,
                'categorie_id' => $catReservation['id'] ?? null,
                'montant' => $montant,
                'mode_paiement' => $modePaiement,
                'reference' => $data['reference'] ?? null,
                'type_paiement' => 'remboursement',
                'motif' => $motif,
                'notes' => $data['notes'] ?? null,
                'recu_par' => $data['recu_par'] ?? null,
                'session_caisse_id' => $sessionCaisseId
            ]);

            // Décrémenter le montant payé
            $nouveauMontantPaye = max(0, $reservation['montant_paye'] - $montant);
            $montantNet = $reservation['montant'] - ($reservation['remise'] ?? 0);

            $nouveauStatut = 'en_attente';
            if ($nouveauMontantPaye >= $montantNet && $montantNet > 0) {
                $nouveauStatut = 'paye';
            } elseif ($nouveauMontantPaye > 0) {
                $nouveauStatut = 'partiel';
            }

            Database::update('reservations', [
                'montant_paye' => $nouveauMontantPaye,
                'statut_paiement' => $nouveauStatut
            ], 'id = :id', ['id' => $reservationId]);

            Client::updateStats($reservation['client_id']);

            Database::commit();

            return [
                'success' => true,
                'paiement_id' => $paiementId,
                'message' => 'Remboursement enregistré avec succès.'
            ];

        } catch (Exception $e) {
            Database::rollback();
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Total déjà remboursé pour une réservation
     */
    public static function getTotalRefunded(int $reservationId): float {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(montant), 0) as total
             FROM paiements
             WHERE reservation_id = :id AND type_paiement = 'remboursement'",
            ['id' => $reservationId]
        );
        return (float)($row['total'] ?? 0);
    }

    /**
     * Récupérer les paiements d'une réservation
     */
    public static function getPayments(int $reservationId): array {
        return Database::fetchAll(
            "SELECT p.*, u.nom as recu_par_nom
             FROM paiements p
             LEFT JOIN users u ON p.recu_par = u.id
             WHERE p.reservation_id = :reservation_id
             ORDER BY p.created_at DESC",
            ['reservation_id' => $reservationId]
        );
    }

    /**
     * Réservations pour le calendrier (FullCalendar)
     */
    public static function getForCalendar(string $start, string $end, ?int $terrainId = null): array {
        $sql = "SELECT r.id, r.numero_ticket, r.date_reservation, r.heure_debut, r.heure_fin,
                       r.statut_reservation, r.statut_paiement, r.montant,
                       t.nom as terrain_nom, t.id as terrain_id,
                       CONCAT(c.prenom, ' ', c.nom) as client_nom
                FROM reservations r
                JOIN terrains t ON r.terrain_id = t.id
                JOIN clients c ON r.client_id = c.id
                WHERE r.date_reservation BETWEEN :start AND :end
                AND r.statut_reservation != 'annulee'";

        $params = ['start' => $start, 'end' => $end];

        if ($terrainId) {
            $sql .= " AND r.terrain_id = :terrain_id";
            $params['terrain_id'] = $terrainId;
        }

        $sql .= " ORDER BY r.date_reservation, r.heure_debut";

        $reservations = Database::fetchAll($sql, $params);

        // Formater pour FullCalendar.
        // La couleur reflète l'état TEMPOREL réel (et non le champ DB qui n'est jamais mis à jour) :
        //   en_cours  (turquoise)  : now est dans [heure_debut, heure_fin]   ← priorité 1
        //   terminee  (gris)       : now > heure_fin                          ← priorité 2
        //   non_payee (jaune)      : statut_paiement = 'en_attente'           ← priorité 3
        //   confirmee (vert)       : sinon                                    ← priorité 4
        $now = new DateTime();
        $events = [];
        foreach ($reservations as $r) {
            $startIso = opDateTimeIso($r['date_reservation'], $r['heure_debut']);
            $endIso   = opDateTimeIso($r['date_reservation'], $r['heure_fin']);
            $startTs  = new DateTime($startIso);
            $endTs    = new DateTime($endIso);

            if ($now >= $startTs && $now < $endTs) {
                $color = '#17A2B8'; $displayStatus = 'en_cours';
            } elseif ($now >= $endTs) {
                $color = '#6c757d'; $displayStatus = 'terminee';
            } elseif ($r['statut_paiement'] === 'en_attente') {
                $color = '#ffc107'; $displayStatus = 'non_payee';
            } else {
                $color = '#28A745'; $displayStatus = 'confirmee';
            }

            $events[] = [
                'id' => $r['id'],
                'title' => $r['client_nom'] . ' - ' . $r['terrain_nom'],
                // Reporter sur le lendemain si op-time >= 24h
                'start' => $startIso,
                'end'   => $endIso,
                'backgroundColor' => $color,
                'borderColor' => $color,
                'extendedProps' => [
                    'ticket' => $r['numero_ticket'],
                    'terrain' => $r['terrain_nom'],
                    'terrain_id' => $r['terrain_id'],
                    'client' => $r['client_nom'],
                    'display_status' => $displayStatus,
                    'montant' => $r['montant'],
                    'statut' => $r['statut_reservation'],
                    'paiement' => $r['statut_paiement']
                ]
            ];
        }

        return $events;
    }

    /**
     * Statistiques des réservations
     */
    public static function getStats(string $periode = 'jour'): array {
        $dateCondition = match($periode) {
            'jour' => "DATE(r.created_at) = CURDATE()",
            'semaine' => "r.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
            'mois' => "MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())",
            default => "1=1"
        };

        return Database::fetchOne(
            "SELECT
                COUNT(*) as total,
                SUM(CASE WHEN statut_reservation = 'confirmee' THEN 1 ELSE 0 END) as confirmees,
                SUM(CASE WHEN statut_reservation = 'terminee' THEN 1 ELSE 0 END) as terminees,
                SUM(CASE WHEN statut_reservation = 'annulee' THEN 1 ELSE 0 END) as annulees,
                SUM(CASE WHEN statut_paiement = 'paye' THEN 1 ELSE 0 END) as payees,
                COALESCE(SUM(montant), 0) as montant_total,
                COALESCE(SUM(montant_paye), 0) as montant_encaisse
             FROM reservations r
             WHERE {$dateCondition}"
        );
    }

    /**
     * Générer un token QR unique pour une réservation
     */
    public static function generateQRToken(): string {
        return bin2hex(random_bytes(16));
    }

    /**
     * Récupérer une réservation par token QR
     */
    public static function getByQRToken(string $token): ?array {
        return Database::fetchOne(
            "SELECT r.*, t.nom as terrain_nom, t.type as terrain_type,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom, c.telephone as client_telephone
             FROM reservations r
             JOIN terrains t ON r.terrain_id = t.id
             JOIN clients c ON r.client_id = c.id
             WHERE r.qr_code_token = :token",
            ['token' => $token]
        );
    }

    /**
     * Générer l'URL du QR code pour une réservation
     */
    public static function getQRCodeUrl(array $reservation, int $size = 120): string {
        $token = $reservation['qr_code_token'] ?? null;
        if (!$token) {
            return '';
        }
        $verificationUrl = APP_URL . '/site/verification.php?token=' . $token;
        // Utiliser l'API QR Server (gratuite et fiable)
        return "https://api.qrserver.com/v1/create-qr-code/?size={$size}x{$size}&data=" . urlencode($verificationUrl);
    }

    /**
     * Enregistrer le check-in d'une réservation
     */
    public static function checkIn(int $id): bool {
        return Database::update('reservations', [
            'check_in_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]) > 0;
    }

    /**
     * Enregistrer le check-out d'une réservation
     */
    public static function checkOut(int $id): bool {
        return Database::update('reservations', [
            'check_out_at' => date('Y-m-d H:i:s'),
            'statut_reservation' => 'terminee'
        ], 'id = :id', ['id' => $id]) > 0;
    }
}
