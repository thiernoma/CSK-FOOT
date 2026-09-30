<?php
/**
 * Modèle SessionCaisse
 * Gère l'ouverture, la fermeture et la validation des sessions de caisse
 * Une session = un caissier travaillant pendant une période, avec un fond de caisse
 */

require_once __DIR__ . '/../../config/database.php';

class Caisse {

    /**
     * Récupérer la session active (ouverte) d'un utilisateur
     */
    public static function getActiveSession(int $userId): ?array {
        return Database::fetchOne(
            "SELECT * FROM clotures_caisse
             WHERE utilisateur_id = :user_id AND statut = 'ouverte'
             ORDER BY heure_ouverture DESC
             LIMIT 1",
            ['user_id' => $userId]
        );
    }

    /**
     * Récupérer une session par ID
     */
    public static function getById(int $id): ?array {
        return Database::fetchOne(
            "SELECT s.*,
                    u.nom as user_nom, u.prenom as user_prenom,
                    uc.nom as cloturee_par_nom,
                    uv.nom as validee_par_nom
             FROM clotures_caisse s
             LEFT JOIN users u ON s.utilisateur_id = u.id
             LEFT JOIN users uc ON s.cloturee_par = uc.id
             LEFT JOIN users uv ON s.validee_par = uv.id
             WHERE s.id = :id",
            ['id' => $id]
        );
    }

    /**
     * Vérifier qu'un utilisateur peut ouvrir une nouvelle session
     * (aucune session ouverte précédente)
     */
    public static function canOpen(int $userId): array {
        $existing = self::getActiveSession($userId);
        if ($existing) {
            return [
                'allowed' => false,
                'reason' => 'Vous avez déjà une session ouverte depuis le ' .
                            date('d/m/Y H:i', strtotime($existing['heure_ouverture'])) .
                            '. Veuillez la clôturer avant d\'en ouvrir une nouvelle.',
                'session_id' => $existing['id']
            ];
        }
        return ['allowed' => true];
    }

    /**
     * Ouvrir une session de caisse
     */
    public static function open(int $userId, float $fondCaisse, ?string $notes = null): array {
        $check = self::canOpen($userId);
        if (!$check['allowed']) {
            return ['success' => false, 'message' => $check['reason'], 'session_id' => $check['session_id'] ?? null];
        }

        if ($fondCaisse < 0) {
            return ['success' => false, 'message' => 'Le fond de caisse ne peut pas être négatif.'];
        }

        try {
            $id = Database::insert('clotures_caisse', [
                'date_cloture' => date('Y-m-d'),
                'utilisateur_id' => $userId,
                'fond_caisse' => $fondCaisse,
                'heure_ouverture' => date('Y-m-d H:i:s'),
                'notes_ouverture' => $notes,
                'statut' => 'ouverte'
            ]);

            return [
                'success' => true,
                'session_id' => $id,
                'message' => 'Caisse ouverte avec un fond de ' . formatMoney($fondCaisse) . '.'
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Calculer les totaux d'une session (à partir des paiements liés)
     */
    public static function computeTotals(int $sessionId): array {
        $row = Database::fetchOne(
            "SELECT
                COUNT(*) AS nb_transactions,
                COALESCE(SUM(CASE WHEN type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS total_encaisse,
                COALESCE(SUM(CASE WHEN type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS total_rembourse,
                COALESCE(SUM(CASE WHEN mode_paiement = 'especes' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS especes_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'especes' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS especes_out,
                COALESCE(SUM(CASE WHEN mode_paiement = 'wave' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS wave_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'wave' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS wave_out,
                COALESCE(SUM(CASE WHEN mode_paiement = 'om' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS om_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'om' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS om_out,
                COALESCE(SUM(CASE WHEN mode_paiement = 'carte' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS carte_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'carte' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS carte_out,
                COALESCE(SUM(CASE WHEN mode_paiement = 'virement' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS virement_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'virement' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS virement_out,
                COALESCE(SUM(CASE WHEN mode_paiement = 'cheque' AND type_paiement = 'paiement' THEN montant ELSE 0 END), 0) AS cheque_in,
                COALESCE(SUM(CASE WHEN mode_paiement = 'cheque' AND type_paiement = 'remboursement' THEN montant ELSE 0 END), 0) AS cheque_out
             FROM paiements
             WHERE session_caisse_id = :sid",
            ['sid' => $sessionId]
        );

        return [
            'nb_transactions' => (int)($row['nb_transactions'] ?? 0),
            'total_encaisse' => (float)($row['total_encaisse'] ?? 0),
            'total_rembourse' => (float)($row['total_rembourse'] ?? 0),
            'especes' => (float)($row['especes_in'] ?? 0) - (float)($row['especes_out'] ?? 0),
            'wave' => (float)($row['wave_in'] ?? 0) - (float)($row['wave_out'] ?? 0),
            'om' => (float)($row['om_in'] ?? 0) - (float)($row['om_out'] ?? 0),
            'carte' => (float)($row['carte_in'] ?? 0) - (float)($row['carte_out'] ?? 0),
            'virement' => (float)($row['virement_in'] ?? 0) - (float)($row['virement_out'] ?? 0),
            'cheque' => (float)($row['cheque_in'] ?? 0) - (float)($row['cheque_out'] ?? 0)
        ];
    }

    /**
     * Total des dépenses espèces attachées à une session (payées en cash depuis la caisse)
     */
    public static function getCashExpenses(int $sessionId): float {
        $row = Database::fetchOne(
            "SELECT COALESCE(SUM(montant), 0) as total
             FROM depenses
             WHERE session_caisse_id = :sid AND statut = 'payee' AND mode_paiement = 'especes'",
            ['sid' => $sessionId]
        );
        return (float)($row['total'] ?? 0);
    }

    /**
     * Espèces attendues en caisse (fond + entrées espèces - sorties espèces - dépenses espèces)
     */
    public static function expectedCash(array $session): float {
        $totals = self::computeTotals((int)$session['id']);
        $depCash = self::getCashExpenses((int)$session['id']);
        return (float)$session['fond_caisse'] + $totals['especes'] - $depCash;
    }

    /**
     * Clôturer une session
     */
    public static function close(int $sessionId, int $userId, float $montantDeclare, ?string $commentaire = null): array {
        $session = self::getById($sessionId);
        if (!$session) {
            return ['success' => false, 'message' => 'Session introuvable.'];
        }

        if ($session['statut'] !== 'ouverte') {
            return ['success' => false, 'message' => 'Cette session n\'est pas ouverte.'];
        }

        if ($session['utilisateur_id'] != $userId) {
            return ['success' => false, 'message' => 'Vous ne pouvez clôturer que votre propre session.'];
        }

        if ($montantDeclare < 0) {
            return ['success' => false, 'message' => 'Le montant déclaré ne peut pas être négatif.'];
        }

        $totals = self::computeTotals($sessionId);
        $depCash = self::getCashExpenses($sessionId);
        $expectedCash = (float)$session['fond_caisse'] + $totals['especes'] - $depCash;
        $ecart = $montantDeclare - $expectedCash;

        try {
            Database::update('clotures_caisse', [
                'total_especes' => $totals['especes'],
                'total_wave' => $totals['wave'],
                'total_om' => $totals['om'],
                'total_carte' => $totals['carte'],
                'total_virement' => $totals['virement'],
                'total_cheque' => $totals['cheque'],
                'total_encaissements' => $totals['total_encaisse'] - $totals['total_rembourse'],
                'solde_caisse' => $expectedCash,
                'montant_declare' => $montantDeclare,
                'ecart' => $ecart,
                'commentaire' => $commentaire,
                'statut' => 'cloturee',
                'cloturee_par' => $userId,
                'heure_cloture' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $sessionId]);

            return [
                'success' => true,
                'message' => 'Caisse clôturée. ' . ($ecart == 0
                    ? 'Aucun écart.'
                    : ($ecart > 0
                        ? 'Excédent : ' . formatMoney($ecart)
                        : 'Manquant : ' . formatMoney(abs($ecart)))),
                'ecart' => $ecart
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Valider une session clôturée (par directeur/admin)
     */
    public static function validate(int $sessionId, int $userId): array {
        $session = self::getById($sessionId);
        if (!$session) {
            return ['success' => false, 'message' => 'Session introuvable.'];
        }
        if ($session['statut'] !== 'cloturee') {
            return ['success' => false, 'message' => 'Seules les sessions clôturées peuvent être validées.'];
        }

        try {
            Database::update('clotures_caisse', [
                'statut' => 'validee',
                'validee_par' => $userId,
                'heure_validation' => date('Y-m-d H:i:s')
            ], 'id = :id', ['id' => $sessionId]);

            return ['success' => true, 'message' => 'Session validée.'];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Erreur: ' . $e->getMessage()];
        }
    }

    /**
     * Sessions d'une date (toutes statuts confondus)
     */
    public static function getByDate(string $date): array {
        return Database::fetchAll(
            "SELECT s.*,
                    u.nom as user_nom, u.prenom as user_prenom,
                    uc.nom as cloturee_par_nom,
                    uv.nom as validee_par_nom
             FROM clotures_caisse s
             LEFT JOIN users u ON s.utilisateur_id = u.id
             LEFT JOIN users uc ON s.cloturee_par = uc.id
             LEFT JOIN users uv ON s.validee_par = uv.id
             WHERE s.date_cloture = :date
             ORDER BY s.heure_ouverture DESC",
            ['date' => $date]
        );
    }

    /**
     * Récupérer les paiements liés à une session
     */
    public static function getSessionPayments(int $sessionId): array {
        return Database::fetchAll(
            "SELECT p.*,
                    r.numero_ticket, r.heure_debut, r.heure_fin,
                    CONCAT(c.prenom, ' ', c.nom) as client_nom,
                    t.nom as terrain_nom,
                    cat.libelle as categorie_libelle, cat.code as categorie_code, cat.icone as categorie_icone
             FROM paiements p
             LEFT JOIN reservations r ON p.reservation_id = r.id
             LEFT JOIN clients c ON r.client_id = c.id
             LEFT JOIN terrains t ON r.terrain_id = t.id
             LEFT JOIN categories_encaissement cat ON p.categorie_id = cat.id
             WHERE p.session_caisse_id = :sid
             ORDER BY p.created_at DESC",
            ['sid' => $sessionId]
        );
    }

    /**
     * Sessions en attente de validation
     */
    public static function getPendingValidation(int $limit = 50): array {
        return Database::fetchAll(
            "SELECT s.*,
                    u.nom as user_nom, u.prenom as user_prenom,
                    uc.nom as cloturee_par_nom
             FROM clotures_caisse s
             LEFT JOIN users u ON s.utilisateur_id = u.id
             LEFT JOIN users uc ON s.cloturee_par = uc.id
             WHERE s.statut = 'cloturee'
             ORDER BY s.heure_cloture DESC
             LIMIT {$limit}"
        );
    }
}
