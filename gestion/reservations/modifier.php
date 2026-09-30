<?php
/**
 * Modifier une réservation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Client.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Réservation non spécifiée.');
    redirect(url('reservations/index'));
}

$reservation = Reservation::getById($id);
if (!$reservation) {
    Session::flash('danger', 'Réservation introuvable.');
    redirect(url('reservations/index'));
}

// Vérifier si la réservation peut être modifiée
if ($reservation['statut_reservation'] === 'annulee') {
    Session::flash('warning', 'Cette réservation est annulée et ne peut pas être modifiée.');
    redirect(url('reservations/voir?id=' . $id));
}

$pageTitle = 'Modifier la réservation #' . $reservation['numero_ticket'];
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index')],
    ['label' => $reservation['numero_ticket'], 'url' => url('reservations/voir?id=' . $id)],
    ['label' => 'Modifier']
];

// Récupérer les terrains actifs
$terrains = Terrain::getAll('actif');

// Récupérer le client actuel
$clientActuel = Client::getById($reservation['client_id']);

$errors = [];
$data = [
    'client_id' => $reservation['client_id'],
    'terrain_id' => $reservation['terrain_id'],
    'date_reservation' => $reservation['date_reservation'],
    // On garde l'op-time tel quel (ex: "26:40") pour matcher l'option du select
    'heure_debut' => substr($reservation['heure_debut'], 0, 5),
    'heure_fin' => substr($reservation['heure_fin'], 0, 5),
    'notes' => $reservation['notes'] ?? ''
];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'client_id' => (int)post('client_id'),
            'terrain_id' => (int)post('terrain_id'),
            'date_reservation' => sanitize(post('date_reservation')),
            'heure_debut' => sanitize(post('heure_debut')),
            'heure_fin' => sanitize(post('heure_fin')),
            'notes' => sanitize(post('notes'))
        ];

        // Validation
        if (empty($data['client_id'])) {
            $errors[] = 'Veuillez sélectionner un client.';
        }

        if (empty($data['terrain_id'])) {
            $errors[] = 'Veuillez sélectionner un terrain.';
        }

        if (empty($data['date_reservation'])) {
            $errors[] = 'La date est obligatoire.';
        }

        if (empty($data['heure_debut']) || empty($data['heure_fin'])) {
            $errors[] = 'Les heures de début et fin sont obligatoires.';
        } else {
            // Normaliser en op-time (gère les créneaux après-minuit)
            $data['heure_debut'] = wallToOp($data['heure_debut']);
            $data['heure_fin']   = wallToOp($data['heure_fin']);

            if ($data['heure_debut'] >= $data['heure_fin']) {
                $errors[] = 'L\'heure de fin doit être après l\'heure de début.';
            }
        }

        // Ajuster l'heure de début/fin selon le temps de transition (chevauchement)
        if (empty($errors)) {
            $tempsTransition = (int) getParam('temps_transition', '10');

            if ($tempsTransition > 0) {
                $reservationExistante = Database::fetchOne(
                    "SELECT id, heure_fin FROM reservations
                     WHERE terrain_id = :terrain_id
                     AND date_reservation = :date_reservation
                     AND heure_fin = :heure_debut
                     AND statut_reservation != 'annulee'
                     AND id != :exclude_id",
                    [
                        'terrain_id' => $data['terrain_id'],
                        'date_reservation' => $data['date_reservation'],
                        'heure_debut' => $data['heure_debut'],
                        'exclude_id' => $id
                    ]
                );

                if ($reservationExistante) {
                    $debutMin = opTimeToMinutes($data['heure_debut']) + $tempsTransition;
                    $finMin   = opTimeToMinutes($data['heure_fin'])   + $tempsTransition;
                    $data['heure_debut'] = minutesToOpTime($debutMin) . ':00';
                    $data['heure_fin']   = minutesToOpTime($finMin)   . ':00';
                }
            }
        }

        // Vérifier la disponibilité (en excluant la réservation actuelle)
        if (empty($errors)) {
            $isAvailable = Terrain::isAvailable(
                $data['terrain_id'],
                $data['date_reservation'],
                $data['heure_debut'],
                $data['heure_fin'],
                $id // Exclure cette réservation de la vérification
            );

            if (!$isAvailable) {
                $errors[] = 'Ce créneau n\'est pas disponible.';
            }
        }

        if (empty($errors)) {
            // Calculer la nouvelle durée (supporte op-time >= 24h)
            $dureeHeures = opDurationHours($data['heure_debut'], $data['heure_fin']);

            // Calculer le nouveau montant
            $terrain = Terrain::getById($data['terrain_id']);
            $montant = Terrain::calculatePrice(
                $data['terrain_id'],
                $data['date_reservation'],
                $data['heure_debut'],
                $data['heure_fin']
            );

            // Mettre à jour la réservation
            $updateData = [
                'client_id' => $data['client_id'],
                'terrain_id' => $data['terrain_id'],
                'date_reservation' => $data['date_reservation'],
                'heure_debut' => $data['heure_debut'],
                'heure_fin' => $data['heure_fin'],
                'duree_heures' => $dureeHeures,
                'montant' => $montant,
                'notes' => $data['notes'],
                'modifie_par' => Auth::id()
            ];

            // Mettre à jour le statut de paiement si le montant a changé
            $montantPaye = $reservation['montant_paye'] ?? 0;
            if ($montant != $reservation['montant']) {
                if ($montantPaye >= $montant) {
                    $updateData['statut_paiement'] = 'paye';
                } elseif ($montantPaye > 0) {
                    $updateData['statut_paiement'] = 'partiel';
                } else {
                    $updateData['statut_paiement'] = 'en_attente';
                }
            }

            try {
                Database::update('reservations', $updateData, 'id = :id', ['id' => $id]);
                Auth::logAction(Auth::id(), 'update', 'reservations', $id);

                Session::flash('success', 'Réservation modifiée avec succès !');
                redirect(url('reservations/voir?id=' . $id));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la modification.';
                if (DEV_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

// Créneaux autorisés (règle commune site + back-office, voir helpers.php).
// On garde l'heure déjà saisie si elle sort de la règle (ex: ancienne réservation à 08:30).
$slotsDebut = withCurrentSlot(getReservationStartSlots(), $data['heure_debut'] ?? '');
$slotsFin   = withCurrentSlot(getReservationEndSlots(), $data['heure_fin'] ?? '');

include VIEWS_PATH . 'layouts/header.php';
?>

<style>
.terrain-card {
    cursor: pointer;
    transition: all 0.3s;
    border: 2px solid transparent;
}
.terrain-card:hover {
    border-color: var(--primary);
    transform: translateY(-3px);
}
.terrain-card.selected {
    border-color: var(--accent);
    background-color: rgba(232, 99, 26, 0.05);
}
.terrain-card.selected::after {
    content: '\f00c';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    position: absolute;
    top: 10px;
    right: 10px;
    background: var(--accent);
    color: white;
    width: 25px;
    height: 25px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
}
.price-display {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}
/* Styles Select2 */
.select2-container .select2-selection--single {
    height: 44px !important;
    border: 1px solid #ced4da;
    border-radius: 4px;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 42px !important;
    padding-left: 12px;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #0d6efd !important;
}
.select2-results__option--highlighted .client-name,
.select2-results__option--highlighted .client-phone {
    color: #ffffff !important;
}
</style>

<div class="row justify-content-center">
    <div class="col-xl-10">
        <!-- Alerte modification -->
        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            Vous modifiez la réservation <strong><?= e($reservation['numero_ticket']) ?></strong>.
            Le montant sera recalculé si vous changez le terrain ou les horaires.
        </div>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="POST" id="reservationForm">
            <?= csrfField() ?>

            <!-- Étape 1: Client -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">1</span>
                        Client
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label">Client</label>
                            <select class="form-select" id="clientSelect" name="client_id" required>
                                <option value="">Sélectionner un client</option>
                                <?php
                                // Tous les clients (aucun filtre de statut) pour que la liste soit complète
                                // et que le client de la réservation s'affiche toujours, même s'il n'est pas "actif".
                                $allClients = Client::getAll([], 0);
                                foreach ($allClients as $client):
                                    $selected = ($data['client_id'] == $client['id']) ? 'selected' : '';
                                ?>
                                <option value="<?= $client['id'] ?>"
                                        data-prenom="<?= e($client['prenom'] ?? '') ?>"
                                        data-nom="<?= e($client['nom']) ?>"
                                        data-telephone="<?= e($client['telephone']) ?>"
                                        <?= $selected ?>>
                                    <?= e(($client['prenom'] ? $client['prenom'] . ' ' : '') . $client['nom']) ?> - <?= e($client['telephone']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <a href="<?= url('clients/voir?id=' . $data['client_id']) ?>" class="btn btn-outline-secondary w-100" target="_blank">
                                <i class="fas fa-eye me-2"></i>Voir le client
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Étape 2: Terrain -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">2</span>
                        Terrain
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($terrains as $terrain): ?>
                        <div class="col-md-4">
                            <div class="card terrain-card position-relative h-100 <?= $data['terrain_id'] == $terrain['id'] ? 'selected' : '' ?>"
                                 onclick="selectTerrain(<?= $terrain['id'] ?>, <?= $terrain['prix_heure'] ?>)">
                                <div class="card-body text-center">
                                    <i class="fas fa-futbol fa-3x text-primary mb-3"></i>
                                    <h5><?= e($terrain['nom']) ?></h5>
                                    <span class="badge bg-secondary mb-2"><?= terrainTypeLabel($terrain['type']) ?></span>
                                    <div class="fw-bold text-accent"><?= formatMoney($terrain['prix_heure']) ?>/h</div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="terrain_id" id="terrainId" value="<?= e($data['terrain_id']) ?>">
                    <input type="hidden" id="prixHeure" value="<?= Terrain::getById($data['terrain_id'])['prix_heure'] ?? 0 ?>">
                </div>
            </div>

            <!-- Étape 3: Date et Créneau -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">3</span>
                        Date et créneau horaire
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Date de réservation</label>
                            <input type="date" class="form-control form-control-lg" name="date_reservation"
                                   id="dateReservation" value="<?= e($data['date_reservation']) ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure de début</label>
                            <select class="form-select form-select-lg" name="heure_debut" id="heureDebut" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($slotsDebut as $slot): $sw = opToWall($slot); ?>
                                    <option value="<?= $slot ?>" <?= $data['heure_debut'] === $slot ? 'selected' : '' ?>>
                                        <?= $sw['wall'] ?><?= $sw['next_day'] ? ' (lendemain)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure de fin</label>
                            <select class="form-select form-select-lg" name="heure_fin" id="heureFin" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($slotsFin as $slot): $sw = opToWall($slot); ?>
                                    <option value="<?= $slot ?>" <?= $data['heure_fin'] === $slot ? 'selected' : '' ?>>
                                        <?= $sw['wall'] ?><?= $sw['next_day'] ? ' (lendemain)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Aperçu disponibilité -->
                    <div id="disponibiliteInfo" class="mt-3"></div>
                </div>
            </div>

            <!-- Étape 4: Résumé -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">4</span>
                        Résumé
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="bg-light p-4 rounded text-center">
                                <div class="text-muted mb-2">Nouveau montant</div>
                                <div class="price-display" id="montantTotal"><?= formatMoney($reservation['montant']) ?></div>
                                <div class="text-muted small" id="dureeInfo"><?= $reservation['duree_heures'] ?> heure(s)</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-4 rounded text-center">
                                <div class="text-muted mb-2">Déjà payé</div>
                                <div class="h4 text-success"><?= formatMoney($reservation['montant_paye'] ?? 0) ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="bg-light p-4 rounded text-center">
                                <div class="text-muted mb-2">Reste à payer</div>
                                <div class="h4 text-danger" id="resteAPayer"><?= formatMoney(($reservation['montant'] ?? 0) - ($reservation['montant_paye'] ?? 0)) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Remarques, demandes spéciales..."><?= e($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between">
                <a href="<?= url('reservations/voir?id=' . $id) ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Annuler
                </a>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-2"></i>Enregistrer les modifications
                </button>
            </div>
        </form>
    </div>
</div>

<?php
$montantPaye = $reservation['montant_paye'] ?? 0;
$inlineJs = "
var APP_BASE_URL = '" . APP_URL . "';
var reservationId = " . $id . ";
var montantPaye = " . $montantPaye . ";

// Initialiser Select2
jQuery(document).ready(function($) {
    if (typeof $.fn.select2 !== 'undefined') {
        $('#clientSelect').select2({
            language: 'fr',
            placeholder: 'Sélectionner un client',
            width: '100%'
        });
    }
});

// Sélection terrain
function selectTerrain(id, prix) {
    document.querySelectorAll('.terrain-card').forEach(c => c.classList.remove('selected'));
    event.currentTarget.classList.add('selected');
    document.getElementById('terrainId').value = id;
    document.getElementById('prixHeure').value = prix;
    calculatePrice();
    checkAvailability();
}

// Calcul du prix
function calculatePrice() {
    const prixHeure = parseFloat(document.getElementById('prixHeure').value) || 0;
    const debut = document.getElementById('heureDebut').value;
    const fin = document.getElementById('heureFin').value;

    if (debut && fin && prixHeure > 0) {
        const [dh, dm] = debut.split(':').map(Number);
        const [fh, fm] = fin.split(':').map(Number);
        const duree = (fh + fm/60) - (dh + dm/60);

        if (duree > 0) {
            const montant = duree * prixHeure;
            document.getElementById('montantTotal').textContent = formatMoney(montant);
            document.getElementById('dureeInfo').textContent = duree + ' heure(s)';

            const reste = Math.max(0, montant - montantPaye);
            document.getElementById('resteAPayer').textContent = formatMoney(reste);
        }
    }
}

// Vérifier disponibilité
function checkAvailability() {
    const terrainId = document.getElementById('terrainId').value;
    const date = document.getElementById('dateReservation').value;
    const debut = document.getElementById('heureDebut').value;
    const fin = document.getElementById('heureFin').value;

    if (!terrainId || !date || !debut || !fin) return;

    fetch(APP_BASE_URL + '/api/reservations.php?action=check_disponibilite&terrain_id=' + terrainId +
          '&date=' + date + '&heure_debut=' + debut + '&heure_fin=' + fin + '&exclude_id=' + reservationId)
        .then(r => r.json())
        .then(data => {
            const info = document.getElementById('disponibiliteInfo');
            if (data.disponible) {
                info.innerHTML = '<div class=\"alert alert-success mb-0\"><i class=\"fas fa-check-circle me-2\"></i>Créneau disponible</div>';
            } else {
                info.innerHTML = '<div class=\"alert alert-danger mb-0\"><i class=\"fas fa-times-circle me-2\"></i>Créneau non disponible</div>';
            }
        });
}

// Event listeners
document.getElementById('heureDebut').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
document.getElementById('heureFin').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
document.getElementById('dateReservation').addEventListener('change', checkAvailability);

// Init
calculatePrice();
checkAvailability();
";

include VIEWS_PATH . 'layouts/footer.php';
?>