<?php
/**
 * Nouvelle réservation
 * Interface optimisée pour une création rapide
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Client.php';
require_once APP_PATH . 'models/Notification.php';

Auth::requireLogin();

$pageTitle = 'Nouvelle réservation';
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index.php')],
    ['label' => 'Nouvelle']
];

// Récupérer les terrains actifs
$terrains = Terrain::getAll('actif');

// Terrain pré-sélectionné
$terrainId = (int)get('terrain');

// Date par défaut (aujourd'hui)
$dateDefaut = get('date', date('Y-m-d'));

// Client pré-sélectionné
$clientId = (int)get('client');
$clientPreselect = null;
if ($clientId) {
    $clientPreselect = Client::getById($clientId);
}

// Heures pré-sélectionnées (depuis le planning)
$heureDebut = get('heure_debut', '');
$heureFin = get('heure_fin', '');

$errors = [];
$data = [
    'client_id' => $clientId,
    'terrain_id' => $terrainId,
    'date_reservation' => $dateDefaut,
    'heure_debut' => $heureDebut,
    'heure_fin' => $heureFin,
    'mode_paiement' => 'especes',
    'paiement_immediat' => '',
    'notes' => ''
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
            'mode_paiement' => sanitize(post('mode_paiement')),
            'paiement_immediat' => (float)post('paiement_immediat'),
            'notes' => sanitize(post('notes')),
            'cree_par' => Auth::id()
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
            // Comparaison en op-time (gère les créneaux après-minuit)
            $debutOp = wallToOp($data['heure_debut']);
            $finOp   = wallToOp($data['heure_fin']);
            if ($debutOp >= $finOp) {
                $errors[] = 'L\'heure de fin doit être après l\'heure de début.';
            }
        }

        if (empty($errors)) {
            $result = Reservation::create($data);

            if ($result['success'] && !empty($result['id'])) {
                Session::flash('success', 'Réservation créée avec succès ! Ticket: ' . $result['numero_ticket']);

                // Notifier les admins de la nouvelle réservation
                $terrain = Terrain::getById($data['terrain_id']);
                $client = Client::getById($data['client_id']);
                Notification::notifyNewReservation([
                    'id' => $result['id'],
                    'client_nom' => $client['prenom'] . ' ' . $client['nom'],
                    'terrain_nom' => $terrain['nom'] ?? 'Terrain',
                    'date_reservation' => $data['date_reservation'],
                    'heure_debut' => $data['heure_debut'],
                    'heure_fin' => $data['heure_fin']
                ]);

                // Rediriger vers la page de détails ou d'impression
                if (post('action') === 'save_print') {
                    redirect(url('reservations/recu.php?id=' . $result['id']));
                } else {
                    redirect(url('reservations/voir.php?id=' . $result['id']));
                }
            } else if ($result['success']) {
                // ID non retourné, rediriger vers la liste
                Session::flash('success', 'Réservation créée avec succès ! Ticket: ' . $result['numero_ticket']);
                redirect(url('reservations/index.php'));
            } else {
                $errors[] = $result['message'];
            }
        }
    }
}

// Créneaux horaires disponibles (en op-time, ex: 08:00 → 26:40)
$timeSlots = getTimeSlots();

// Règles tarifaires pour JS (matinal / normal / weekend sur les cartes)
// heure_pointe_debut = heure de coupure : avant = matinal, à partir de = normal.
$tarifRules = [
    'jours_weekend'      => array_values(array_filter(array_map('intval', explode(',', getParam('jours_weekend', '6,7'))))),
    'heure_pointe_debut' => getParam('heure_pointe_debut', '16:00'),
];

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
.time-slot {
    padding: 8px 15px;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}
.time-slot:hover:not(.disabled) {
    border-color: var(--primary);
    background-color: rgba(26, 58, 107, 0.05);
}
.time-slot.selected {
    background-color: var(--primary);
    color: white;
    border-color: var(--primary);
}
.time-slot.disabled {
    background-color: #f8f9fa;
    color: #adb5bd;
    cursor: not-allowed;
}
/* Créneaux réservés dans le select */
option.slot-reserved {
    background-color: #ffebee !important;
    color: #c62828 !important;
}
option.slot-transition {
    background-color: #fff3e0 !important;
    color: #e65100 !important;
}
/* Indication visuelle des créneaux réservés */
.creneaux-info {
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 10px;
}
.creneau-badge {
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 500;
}
.creneau-badge.reserved {
    background-color: #ffebee;
    color: #c62828;
    border: 1px solid #ffcdd2;
}
.creneau-badge.transition {
    background-color: #fff3e0;
    color: #e65100;
    border: 1px solid #ffe0b2;
}
.price-display {
    font-size: 1.5rem;
    font-weight: 700;
    color: var(--primary);
}
/* Styles Select2 personnalisés - Design moderne */
.select2-container .select2-selection--single {
    height: 44px !important;
    border: 1px solid #ced4da;
    border-radius: 4px;
    background-color: #fff;
}
.select2-container--default .select2-selection--single .select2-selection__rendered {
    line-height: 42px !important;
    padding-left: 12px;
    color: #333;
}
.select2-container--default .select2-selection--single .select2-selection__placeholder {
    color: #6c757d;
}
.select2-container--default .select2-selection--single .select2-selection__arrow {
    height: 42px !important;
    right: 8px;
}
.select2-container--default.select2-container--focus .select2-selection--single {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}
/* Dropdown */
.select2-container--default .select2-dropdown {
    border: 1px solid #ced4da;
    border-radius: 4px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}
/* Champ de recherche dans le dropdown */
.select2-container--default .select2-search--dropdown {
    padding: 10px;
    background-color: #f8f9fa;
    border-bottom: 1px solid #e9ecef;
}
.select2-container--default .select2-search--dropdown .select2-search__field {
    padding: 10px 12px;
    font-size: 0.95rem;
    border: 1px solid #ced4da;
    border-radius: 4px;
    outline: none;
    width: 100%;
    box-sizing: border-box;
}
.select2-container--default .select2-search--dropdown .select2-search__field:focus {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.15rem rgba(13, 110, 253, 0.15);
}
/* Options dans la liste */
.select2-container--default .select2-results__options {
    max-height: 280px;
}
.select2-container--default .select2-results__option {
    padding: 10px 12px;
    font-size: 0.95rem;
    border-bottom: 1px solid #f0f0f0;
}
.select2-container--default .select2-results__option:last-child {
    border-bottom: none;
}
.select2-container--default .select2-results__option--highlighted[aria-selected] {
    background-color: #0d6efd !important;
    color: #fff;
}
.select2-container--default .select2-results__option[aria-selected=true] {
    background-color: #e7f1ff;
    color: #0d6efd;
}
/* FORCER l'affichage du champ de recherche */
.select2-search--dropdown {
    display: block !important;
    padding: 10px !important;
    background-color: #f8f9fa !important;
    border-bottom: 1px solid #e9ecef !important;
}
.select2-search--dropdown .select2-search__field {
    display: block !important;
    width: 100% !important;
    padding: 10px 12px !important;
    font-size: 0.95rem !important;
    border: 1px solid #ced4da !important;
    border-radius: 4px !important;
    box-sizing: border-box !important;
}

/* Style pour le texte blanc au survol dans Select2 */
.select2-results__option--highlighted .client-name,
.select2-results__option--highlighted .client-phone {
    color: #ffffff !important;
}
.select2-results__option--highlighted .client-initials {
    background-color: #ffffff !important;
    color: #0d6efd !important;
}
</style>

<div class="row justify-content-center">
    <div class="col-xl-10">
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
                        Sélectionner le client
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8">
                            <label class="form-label">Rechercher un client</label>
                            <select class="form-select" id="clientSelect" name="client_id">
                                <option value="">Sélectionner un client</option>
                                <?php
                                // Charger tous les clients actifs
                                // Tous les clients (aucun filtre de statut ni limite)
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
                            <button type="button" class="btn btn-outline-primary w-100 btn-lg" data-bs-toggle="modal" data-bs-target="#newClientModal">
                                <i class="fas fa-user-plus me-2"></i>Nouveau client
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Étape 2: Terrain -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">2</span>
                        Choisir le terrain
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($terrains as $terrain): ?>
                        <div class="col-md-4">
                            <div class="card terrain-card position-relative h-100 <?= $data['terrain_id'] == $terrain['id'] ? 'selected' : '' ?>"
                                 data-terrain-id="<?= $terrain['id'] ?>"
                                 data-prix-heure="<?= (float)$terrain['prix_heure'] ?>"
                                 data-prix-pointe="<?= (float)($terrain['prix_heure_pointe'] ?: $terrain['prix_heure']) ?>"
                                 data-prix-weekend="<?= (float)($terrain['prix_weekend'] ?: ($terrain['prix_heure_pointe'] ?: $terrain['prix_heure'])) ?>"
                                 onclick="selectTerrain(<?= $terrain['id'] ?>)">
                                <div class="card-body text-center">
                                    <i class="fas fa-futbol fa-3x text-primary mb-3"></i>
                                    <h5><?= e($terrain['nom']) ?></h5>
                                    <span class="badge bg-secondary mb-2"><?= terrainTypeLabel($terrain['type']) ?></span>
                                    <?php $prixNormalTerrain = (float)($terrain['prix_heure_pointe'] ?: $terrain['prix_heure']); ?>
                                    <div class="fw-bold text-accent terrain-prix" data-base="<?= $prixNormalTerrain ?>">
                                        <?= formatMoney($prixNormalTerrain) ?>/h
                                    </div>
                                    <div class="terrain-prix-badge mt-1" style="min-height: 18px;"></div>
                                    <?php if ($terrain['capacite']): ?>
                                        <small class="text-muted"><?= $terrain['capacite'] ?> joueurs max</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="terrain_id" id="terrainId" value="<?= e($data['terrain_id']) ?>">
                    <input type="hidden" id="prixHeure" value="<?php if ($terrainId) { $tt = Terrain::getById($terrainId); echo (float)($tt['prix_heure_pointe'] ?: ($tt['prix_heure'] ?? 0)); } else { echo 0; } ?>">
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
                                   id="dateReservation" value="<?= e($data['date_reservation']) ?>"
                                   min="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Heure de début</label>
                            <select class="form-select form-select-lg" name="heure_debut" id="heureDebut" required>
                                <option value="">Sélectionner...</option>
                                <?php foreach ($timeSlots as $slot): $sw = opToWall($slot); ?>
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
                                <?php foreach ($timeSlots as $slot): $sw = opToWall($slot); ?>
                                    <option value="<?= $slot ?>" <?= $data['heure_fin'] === $slot ? 'selected' : '' ?>>
                                        <?= $sw['wall'] ?><?= $sw['next_day'] ? ' (lendemain)' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Créneaux réservés pour ce jour -->
                    <div id="creneauxReserves" class="mt-3"></div>

                    <!-- Aperçu disponibilité -->
                    <div id="disponibiliteInfo" class="mt-3"></div>
                </div>
            </div>

            <!-- Étape 4: Paiement -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <span class="badge bg-primary rounded-pill me-2">4</span>
                        Paiement
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="bg-light p-4 rounded text-center">
                                <div class="text-muted mb-2">Montant total</div>
                                <div class="price-display" id="montantTotal">0 FCFA</div>
                                <div class="text-muted small" id="dureeInfo">-</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Mode de paiement</label>
                            <select class="form-select" name="mode_paiement" id="modePaiement">
                                <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= $data['mode_paiement'] === $key ? 'selected' : '' ?>>
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Montant encaissé maintenant</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="paiement_immediat"
                                       id="paiementImmediat" value="<?= e($data['paiement_immediat']) ?>"
                                       min="0" step="500" placeholder="0">
                                <span class="input-group-text">FCFA</span>
                            </div>
                            <div class="form-text">Laissez vide ou 0 si paiement ultérieur</div>
                        </div>
                    </div>

                    <!-- Option Acompte -->
                    <div class="row g-3 mt-2">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="acompteRequis" name="acompte_requis_check"
                                       onchange="toggleAcompte()">
                                <label class="form-check-label" for="acompteRequis">
                                    <strong>Exiger un acompte</strong>
                                    <small class="text-muted d-block">Le client devra verser un acompte minimum</small>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6" id="acompteSection" style="display: none;">
                            <label class="form-label">Montant minimum de l'acompte</label>
                            <div class="input-group">
                                <input type="number" class="form-control" name="acompte_requis"
                                       id="acompteRequisMontant" min="0" step="500" placeholder="Ex: 5000">
                                <span class="input-group-text">FCFA</span>
                                <button type="button" class="btn btn-outline-secondary" onclick="setAcompte50()">50%</button>
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mt-3">
                        <label class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" name="notes" rows="2" placeholder="Remarques, demandes spéciales..."><?= e($data['notes']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="d-flex justify-content-between">
                <a href="<?= url('reservations/index.php') ?>" class="btn btn-outline-secondary btn-lg">
                    <i class="fas fa-arrow-left me-2"></i>Annuler
                </a>
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="save" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                    <button type="submit" name="action" value="save_print" class="btn btn-accent btn-lg">
                        <i class="fas fa-print me-2"></i>Enregistrer & Imprimer
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Nouveau Client -->
<div class="modal fade" id="newClientModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-user-plus me-2"></i>Nouveau client</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="newClientForm">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prénom</label>
                            <input type="text" class="form-control" name="prenom" id="newClientPrenom">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="nom" id="newClientNom" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" name="telephone" id="newClientTel"
                                   placeholder="77 XXX XX XX" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="newClientEmail">
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                <button type="button" class="btn btn-primary" onclick="createClient()">
                    <i class="fas fa-save me-2"></i>Créer le client
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
// URL de base pour les appels API
var APP_BASE_URL = '" . APP_URL . "';

// Règles tarifaires (depuis paramètres de l'application)
var TARIF_RULES = " . json_encode($tarifRules) . ";

// Convertit op-time HH:MM (peut dépasser 24h) en minutes
function _opToMinutes(t) {
    if (!t) return 0;
    var p = t.split(':');
    return parseInt(p[0], 10) * 60 + parseInt(p[1], 10);
}

// Renvoie le tarif applicable selon la date et l'heure de début.
// prix_heure = matinal (avant coupure), prix_heure_pointe = normal (défaut),
// prix_weekend = week-end (prioritaire). Coupure = TARIF_RULES.heure_pointe_debut.
// Retourne { rate, label } où label vaut 'weekend' | 'matinal' | ''.
function getEffectiveRate(card, dateStr, heureDebut) {
    var matinal = parseFloat(card.dataset.prixHeure)   || 0;
    var normal  = parseFloat(card.dataset.prixPointe)  || matinal;
    var weekend = parseFloat(card.dataset.prixWeekend) || normal;

    if (!dateStr) return { rate: normal, label: '' };

    // jour de la semaine : 1=Lundi … 7=Dimanche
    var d = new Date(dateStr + 'T12:00:00');
    var iso = d.getDay() === 0 ? 7 : d.getDay(); // 0=Dim → 7

    if (TARIF_RULES.jours_weekend.indexOf(iso) !== -1 && weekend > 0) {
        return { rate: weekend, label: 'weekend' };
    }

    if (heureDebut) {
        var startMin = _opToMinutes(heureDebut);
        var coupure  = _opToMinutes(TARIF_RULES.heure_pointe_debut);
        if (startMin < coupure) {
            return { rate: matinal, label: 'matinal' };
        }
    }

    return { rate: normal, label: '' };
}

// Met à jour le prix affiché sur chaque carte terrain selon la date/heure courantes
function refreshTerrainPrices() {
    var date = document.getElementById('dateReservation').value;
    var heure = document.getElementById('heureDebut').value;

    document.querySelectorAll('.terrain-card').forEach(function(card) {
        var r = getEffectiveRate(card, date, heure);
        var prixEl = card.querySelector('.terrain-prix');
        var badgeEl = card.querySelector('.terrain-prix-badge');
        if (prixEl) prixEl.textContent = formatMoney(r.rate) + '/h';
        if (badgeEl) {
            if (r.label === 'weekend') {
                badgeEl.innerHTML = '<span class=\"badge bg-warning text-dark\"><i class=\"fas fa-umbrella-beach me-1\"></i>Tarif weekend</span>';
            } else if (r.label === 'matinal') {
                var coupure = TARIF_RULES.heure_pointe_debut.substring(0,5);
                badgeEl.innerHTML = '<span class=\"badge bg-success\"><i class=\"fas fa-sun me-1\"></i>Matinal (avant ' + coupure + ')</span>';
            } else {
                badgeEl.innerHTML = '';
            }
        }
    });

    // Mettre à jour le hidden input avec le tarif effectif du terrain sélectionné
    var selected = document.querySelector('.terrain-card.selected');
    if (selected) {
        var r = getEffectiveRate(selected, date, heure);
        document.getElementById('prixHeure').value = r.rate;
        calculatePrice();
    }
}

// ============================================
// Initialiser Select2 pour les clients
// ============================================
jQuery(document).ready(function($) {
    console.log('jQuery ready, Select2 disponible:', typeof $.fn.select2);

    if (typeof $.fn.select2 === 'undefined') {
        console.error('Select2 non chargé!');
        return;
    }

    // Détruire si déjà initialisé
    if ($('#clientSelect').hasClass('select2-hidden-accessible')) {
        $('#clientSelect').select2('destroy');
    }

    $('#clientSelect').select2({
        language: 'fr',
        placeholder: 'Sélectionner un client',
        allowClear: true,
        width: '100%',
        minimumResultsForSearch: 1,
        templateResult: formatClientOption,
        templateSelection: formatClientSelection
    });

    console.log('Select2 initialisé');

    // Personnaliser le champ de recherche à l'ouverture
    $('#clientSelect').on('select2:open', function() {
        console.log('Select2 ouvert');
        var searchField = document.querySelector('.select2-search--dropdown .select2-search__field');
        console.log('Champ recherche trouvé:', searchField);
        if (searchField) {
            searchField.placeholder = 'Rechercher par nom ou téléphone...';
            searchField.focus();
        }
    });
});

// Format d'affichage des options dans la liste
function formatClientOption(option) {
    if (!option.id) {
        return option.text;
    }
    var el = option.element;
    var prenom = el.getAttribute('data-prenom') || '';
    var nom = el.getAttribute('data-nom') || '';
    var tel = el.getAttribute('data-telephone') || '';
    var fullName = (prenom ? prenom + ' ' : '') + nom;

    // Générer les initiales (2 lettres max)
    var initials = '';
    if (prenom && nom) {
        initials = (prenom[0] + nom[0]).toUpperCase();
    } else if (nom) {
        // Si pas de prénom, prendre les 2 premières lettres du nom
        initials = nom.substring(0, 2).toUpperCase();
    }

    var container = document.createElement('div');
    container.className = 'd-flex align-items-center';
    container.title = fullName + ' - ' + tel; // Tooltip au survol
    container.innerHTML = '<div class=\"client-initials bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2\" style=\"width:32px;height:32px;font-size:11px;flex-shrink:0;\" title=\"' + fullName + '\">' + initials + '</div>' +
        '<div>' +
        '<div class=\"client-name fw-semibold\">' + fullName + '</div>' +
        '<small class=\"client-phone text-muted\"><i class=\"fas fa-phone me-1\"></i>' + tel + '</small>' +
        '</div>';
    return container;
}

// Format d'affichage de la sélection
function formatClientSelection(option) {
    if (!option.id) {
        return option.text;
    }
    return option.text;
}

// Sélection terrain (prix calculé dynamiquement selon date/heure : weekend ou pointe)
function selectTerrain(id) {
    document.querySelectorAll('.terrain-card').forEach(c => c.classList.remove('selected'));
    var card = event.currentTarget;
    card.classList.add('selected');
    document.getElementById('terrainId').value = id;

    var date = document.getElementById('dateReservation').value;
    var heure = document.getElementById('heureDebut').value;
    var r = getEffectiveRate(card, date, heure);
    document.getElementById('prixHeure').value = r.rate;

    calculatePrice();
    loadReservedSlots();
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
            document.getElementById('paiementImmediat').max = montant;
        } else {
            document.getElementById('montantTotal').textContent = '0 FCFA';
            document.getElementById('dureeInfo').textContent = 'Horaire invalide';
        }
    }
}

// Variable globale pour stocker les créneaux réservés
var reservedSlots = [];
var tempsTransition = 10;

// Formate un op-time en wall-clock avec mention lendemain si >= 24h
function fmtOpTime(t) {
    if (!t) return '';
    var p = t.split(':');
    var h = parseInt(p[0], 10);
    var m = parseInt(p[1], 10);
    var wall = (h % 24 < 10 ? '0' : '') + (h % 24) + ':' + (m < 10 ? '0' : '') + m;
    return h >= 24 ? wall + ' (lendemain)' : wall;
}

// Charger les créneaux réservés pour un terrain et une date
function loadReservedSlots() {
    const terrainId = document.getElementById('terrainId').value;
    const date = document.getElementById('dateReservation').value;

    if (!terrainId || !date) {
        document.getElementById('creneauxReserves').innerHTML = '';
        return;
    }

    fetch(APP_BASE_URL + '/api/reservations.php?action=availability&terrain_id=' + terrainId + '&date=' + date)
        .then(r => r.json())
        .then(data => {
            reservedSlots = data.creneaux_reserves || [];
            tempsTransition = data.temps_transition || 10;

            // Afficher les créneaux réservés
            displayReservedSlots();

            // Marquer les options dans les selects
            markReservedOptions();
        });
}

// Afficher les créneaux réservés
function displayReservedSlots() {
    const container = document.getElementById('creneauxReserves');

    if (reservedSlots.length === 0) {
        container.innerHTML = '<div class=\"alert alert-info mb-0\"><i class=\"fas fa-info-circle me-2\"></i>Aucune réservation pour ce jour</div>';
        return;
    }

    var html = '<div class=\"alert alert-warning mb-2\"><i class=\"fas fa-exclamation-triangle me-2\"></i>Créneaux déjà réservés (+ ' + tempsTransition + ' min de préparation) :</div>';
    html += '<div class=\"creneaux-info\">';

    reservedSlots.forEach(function(slot) {
        var finAvecTransition = slot.heure_fin_avec_transition || slot.heure_fin;
        html += '<span class=\"creneau-badge reserved\">' + fmtOpTime(slot.heure_debut) + ' - ' + fmtOpTime(slot.heure_fin) + '</span>';
        if (slot.heure_fin_avec_transition && slot.heure_fin_avec_transition !== slot.heure_fin) {
            html += '<span class=\"creneau-badge transition\">Prépa: ' + fmtOpTime(slot.heure_fin) + ' - ' + fmtOpTime(finAvecTransition) + '</span>';
        }
    });

    html += '</div>';
    container.innerHTML = html;
}

// Marquer les options réservées dans les selects d'heures
function markReservedOptions() {
    const selectDebut = document.getElementById('heureDebut');
    const selectFin = document.getElementById('heureFin');

    // Réinitialiser toutes les options
    Array.from(selectDebut.options).forEach(opt => {
        opt.classList.remove('slot-reserved', 'slot-transition');
        opt.disabled = false;
    });
    Array.from(selectFin.options).forEach(opt => {
        opt.classList.remove('slot-reserved', 'slot-transition');
        opt.disabled = false;
    });

    if (reservedSlots.length === 0) return;

    // Marquer les créneaux réservés
    reservedSlots.forEach(function(slot) {
        var debutParts = slot.heure_debut.split(':');
        var debutMinutes = parseInt(debutParts[0]) * 60 + parseInt(debutParts[1]);

        var finParts = slot.heure_fin.split(':');
        var finMinutes = parseInt(finParts[0]) * 60 + parseInt(finParts[1]);

        var finAvecTransition = slot.heure_fin_avec_transition || slot.heure_fin;
        var finTransParts = finAvecTransition.split(':');
        var finTransMinutes = parseInt(finTransParts[0]) * 60 + parseInt(finTransParts[1]);

        // Parcourir les options de début
        Array.from(selectDebut.options).forEach(opt => {
            if (!opt.value) return;
            var optParts = opt.value.split(':');
            var optMinutes = parseInt(optParts[0]) * 60 + parseInt(optParts[1]);

            // Si l'option est dans la période réservée
            if (optMinutes >= debutMinutes && optMinutes < finMinutes) {
                opt.classList.add('slot-reserved');
                opt.title = 'Créneau réservé';
            }
            // Si l'option est dans la période de transition
            else if (optMinutes >= finMinutes && optMinutes < finTransMinutes) {
                opt.classList.add('slot-transition');
                opt.title = 'Temps de préparation';
            }
        });
    });
}

// Vérifier disponibilité
function checkAvailability() {
    const terrainId = document.getElementById('terrainId').value;
    const date = document.getElementById('dateReservation').value;
    const debut = document.getElementById('heureDebut').value;
    const fin = document.getElementById('heureFin').value;

    if (!terrainId || !date || !debut || !fin) return;

    fetch(APP_BASE_URL + '/api/reservations.php?action=check_disponibilite&terrain_id=' + terrainId +
          '&date=' + date + '&heure_debut=' + debut + '&heure_fin=' + fin)
        .then(r => r.json())
        .then(data => {
            const info = document.getElementById('disponibiliteInfo');
            if (data.disponible) {
                info.innerHTML = '<div class=\"alert alert-success mb-0\"><i class=\"fas fa-check-circle me-2\"></i>Créneau disponible</div>';
            } else {
                info.innerHTML = '<div class=\"alert alert-danger mb-0\"><i class=\"fas fa-times-circle me-2\"></i>Créneau non disponible - conflit avec une réservation existante</div>';
            }
        });
}

// Créer nouveau client
function createClient() {
    const nom = document.getElementById('newClientNom').value;
    const prenom = document.getElementById('newClientPrenom').value;
    const tel = document.getElementById('newClientTel').value;
    const email = document.getElementById('newClientEmail').value;

    if (!nom || !tel) {
        alertModal('Le nom et le téléphone sont obligatoires.', { title: 'Champs requis', type: 'warning' });
        return;
    }

    fetch(APP_BASE_URL + '/api/clients.php?action=create', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({nom, prenom, telephone: tel, email})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Ajouter le nouveau client au select et le sélectionner
            var clientText = (prenom ? prenom + ' ' : '') + nom + ' - ' + tel;
            var newOption = new Option(clientText, data.id, true, true);
            newOption.setAttribute('data-prenom', prenom);
            newOption.setAttribute('data-nom', nom);
            newOption.setAttribute('data-telephone', tel);
            jQuery('#clientSelect').append(newOption).trigger('change');

            bootstrap.Modal.getInstance(document.getElementById('newClientModal')).hide();
            document.getElementById('newClientForm').reset();
            showToast('Client créé avec succès', 'success');
        } else {
            alertModal(data.message || 'Erreur lors de la création du client.', { title: 'Erreur', type: 'danger' });
        }
    });
}

// Gestion acompte
function toggleAcompte() {
    const section = document.getElementById('acompteSection');
    const checkbox = document.getElementById('acompteRequis');
    section.style.display = checkbox.checked ? 'block' : 'none';
    if (!checkbox.checked) {
        document.getElementById('acompteRequisMontant').value = '';
    }
}

function setAcompte50() {
    const prixHeure = parseFloat(document.getElementById('prixHeure').value) || 0;
    const debut = document.getElementById('heureDebut').value;
    const fin = document.getElementById('heureFin').value;

    if (debut && fin && prixHeure > 0) {
        const [dh, dm] = debut.split(':').map(Number);
        const [fh, fm] = fin.split(':').map(Number);
        const duree = (fh + fm/60) - (dh + dm/60);

        if (duree > 0) {
            const montant = Math.round((duree * prixHeure * 0.5) / 500) * 500; // Arrondi à 500
            document.getElementById('acompteRequisMontant').value = montant;
        }
    }
}

// Event listeners
document.getElementById('heureDebut').addEventListener('change', () => { refreshTerrainPrices(); checkAvailability(); });
document.getElementById('heureFin').addEventListener('change', () => { calculatePrice(); checkAvailability(); });
document.getElementById('dateReservation').addEventListener('change', () => { refreshTerrainPrices(); loadReservedSlots(); checkAvailability(); });

// Init : afficher les prix selon la date/heure pré-remplies
refreshTerrainPrices();
calculatePrice();
// Charger les créneaux réservés si terrain et date déjà sélectionnés
if (document.getElementById('terrainId').value && document.getElementById('dateReservation').value) {
    loadReservedSlots();
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
