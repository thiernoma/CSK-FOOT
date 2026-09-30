<?php
/**
 * Planning des réservations (Calendrier FullCalendar)
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';

Auth::requireLogin();

$pageTitle = 'Planning des réservations';
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index')],
    ['label' => 'Planning']
];

// Récupérer les terrains
$terrains = Terrain::getAll('actif');

// Terrain sélectionné
$terrainId = (int)get('terrain');

// Plage horaire d'affichage du planning, depuis les paramètres (fallback constantes globales)
$heureOuverture = getParam('heure_ouverture', OPENING_TIME);
$heureFermeture = getParam('heure_fermeture', CLOSING_TIME);

[$ohH, $ohM] = array_map('intval', explode(':', $heureOuverture));
[$fhH, $fhM] = array_map('intval', explode(':', $heureFermeture));
$slotMinTime = sprintf('%02d:%02d:00', $ohH, $ohM);
// Si la fermeture passe minuit (ex: 02:40), on ajoute 24h pour FullCalendar
if ($fhH < $ohH || ($fhH === $ohH && $fhM <= $ohM)) {
    $fhH += 24;
}
$slotMaxTime = sprintf('%02d:%02d:00', $fhH, $fhM);

include VIEWS_PATH . 'layouts/header.php';
?>

<style>
.fc {
    background: white;
    border-radius: 10px;
    padding: 15px;
}
.fc-header-toolbar {
    margin-bottom: 15px !important;
}
.fc-event {
    cursor: pointer;
    border-radius: 5px;
    padding: 2px 5px;
}
.fc-timegrid-slot {
    height: 35px !important;
}
.terrain-filter {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}
.terrain-filter .btn {
    border-radius: 20px;
}
.terrain-filter .btn.active {
    background-color: var(--accent);
    border-color: var(--accent);
    color: white;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Planning des réservations</h4>
        <p class="text-muted mb-0">Vue calendrier des réservations</p>
    </div>
    <a href="<?= url('reservations/nouveau') ?>" class="btn btn-accent">
        <i class="fas fa-plus me-2"></i>Nouvelle réservation
    </a>
</div>

<!-- Filtres terrains -->
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div class="terrain-filter" id="terrainFilter">
                <button type="button" class="btn btn-outline-primary <?= !$terrainId ? 'active' : '' ?>" data-terrain="">
                    <i class="fas fa-layer-group me-1"></i>Tous les terrains
                </button>
                <?php foreach ($terrains as $terrain): ?>
                <button type="button" class="btn btn-outline-primary <?= $terrainId == $terrain['id'] ? 'active' : '' ?>" data-terrain="<?= $terrain['id'] ?>">
                    <i class="fas fa-futbol me-1"></i><?= e($terrain['nom']) ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary" id="btnToday">
                    Aujourd'hui
                </button>
                <button type="button" class="btn btn-outline-secondary" id="btnPrint" onclick="window.print()">
                    <i class="fas fa-print me-1"></i>Imprimer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Légende -->
<div class="mb-4">
    <div class="d-flex gap-4 flex-wrap">
        <span><span class="badge" style="background:#28A745">&nbsp;&nbsp;&nbsp;</span> Confirmée</span>
        <span><span class="badge" style="background:#17A2B8">&nbsp;&nbsp;&nbsp;</span> En cours</span>
        <span><span class="badge" style="background:#ffc107">&nbsp;&nbsp;&nbsp;</span> Non payée</span>
        <span><span class="badge" style="background:#6c757d">&nbsp;&nbsp;&nbsp;</span> Terminée</span>
    </div>
</div>

<!-- Calendrier -->
<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal détails réservation -->
<div class="modal fade" id="reservationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Détails de la réservation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reservationDetails">
                <!-- Contenu chargé dynamiquement -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" id="btnVoirDetails" class="btn btn-primary">
                    <i class="fas fa-eye me-2"></i>Voir détails
                </a>
            </div>
        </div>
    </div>
</div>

<?php
// Variables pour le JavaScript (seront injectées dans le footer via $inlineJs)
$appUrlJs = APP_URL;
$adminUrlJs = ADMIN_URL;
$terrainIdJs = $terrainId ?: 'null';

$inlineJs = "
// Configuration URLs depuis PHP
var APP_URL = '{$appUrlJs}';
var ADMIN_URL = '{$adminUrlJs}';
var selectedTerrainId = {$terrainIdJs};

console.log('Script planning réservations chargé');
console.log('APP_URL:', APP_URL);
console.log('ADMIN_URL:', ADMIN_URL);
console.log('FullCalendar disponible:', typeof FullCalendar !== 'undefined');

var reservationCalendar = null;

// Initialiser le calendrier
(function initReservationPlanning() {
    var calendarEl = document.getElementById('calendar');

    if (!calendarEl) {
        console.error('Element #calendar non trouvé');
        return;
    }

    if (typeof FullCalendar === 'undefined') {
        calendarEl.innerHTML = '<div class=\"alert alert-danger\">Erreur: FullCalendar non chargé. Veuillez rafraîchir la page.</div>';
        console.error('FullCalendar non disponible');
        return;
    }

    console.log('Initialisation du calendrier...');

    reservationCalendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: \"Aujourd'hui\",
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour'
        },
        slotMinTime: '{$slotMinTime}',
        slotMaxTime: '{$slotMaxTime}',
        slotDuration: '00:30:00',
        allDaySlot: false,
        weekends: true,
        nowIndicator: true,
        selectable: true,
        selectMirror: true,
        height: 'auto',

        events: function(fetchInfo, successCallback, failureCallback) {
            var start = fetchInfo.startStr.split('T')[0];
            var end = fetchInfo.endStr.split('T')[0];
            var apiUrl = APP_URL + '/api/reservations.php?action=calendar&start=' + start + '&end=' + end;

            if (selectedTerrainId) {
                apiUrl += '&terrain_id=' + selectedTerrainId;
            }

            console.log('Fetching events from:', apiUrl);

            fetch(apiUrl)
                .then(function(response) {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(function(data) {
                    console.log('Data received:', data);
                    if (Array.isArray(data)) {
                        successCallback(data);
                    } else {
                        successCallback([]);
                    }
                })
                .catch(function(error) {
                    console.error('Erreur API:', error);
                    failureCallback(error);
                });
        },

        eventClick: function(info) {
            showReservationModal(info.event);
        },

        select: function(info) {
            var date = info.startStr.split('T')[0];
            var heureDebut = info.startStr.split('T')[1].substring(0, 5);
            var heureFin = info.endStr.split('T')[1].substring(0, 5);
            var url = ADMIN_URL + '/reservations/nouveau?date=' + date + '&heure_debut=' + heureDebut + '&heure_fin=' + heureFin;
            if (selectedTerrainId) url += '&terrain=' + selectedTerrainId;
            window.location.href = url;
        },

        eventDidMount: function(info) {
            var props = info.event.extendedProps;
            if (props && props.client) {
                info.el.title = props.client + ' - ' + (props.terrain || '');
            }
        }
    });

    reservationCalendar.render();
    console.log('Calendrier rendu');

    // Filtres terrain
    var filterContainer = document.getElementById('terrainFilter');
    if (filterContainer) {
        filterContainer.addEventListener('click', function(e) {
            var btn = e.target.closest('button[data-terrain]');
            if (!btn) return;

            selectedTerrainId = btn.dataset.terrain || null;

            filterContainer.querySelectorAll('.btn').forEach(function(b) {
                b.classList.remove('active');
            });
            btn.classList.add('active');

            if (reservationCalendar) {
                reservationCalendar.refetchEvents();
            }
        });
    }

    // Bouton Aujourd'hui
    var btnToday = document.getElementById('btnToday');
    if (btnToday) {
        btnToday.addEventListener('click', function() {
            if (reservationCalendar) reservationCalendar.today();
        });
    }
})();

function showReservationModal(event) {
    var props = event.extendedProps || {};
    var details = document.getElementById('reservationDetails');

    var startTime = event.start ? event.start.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '-';
    var endTime = event.end ? event.end.toLocaleTimeString('fr-FR', {hour: '2-digit', minute:'2-digit'}) : '-';

    var paymentLabels = {
        'paye': 'Payé', 'partiel': 'Partiel', 'en_attente': 'En attente',
        'confirmee': 'Confirmée', 'en_cours': 'En cours', 'terminee': 'Terminée', 'annulee': 'Annulée'
    };
    var paymentClasses = {
        'paye': 'bg-success', 'partiel': 'bg-info', 'en_attente': 'bg-warning text-dark',
        'confirmee': 'bg-success', 'terminee': 'bg-secondary'
    };

    var paymentLabel = paymentLabels[props.paiement] || props.paiement || '-';
    var paymentClass = paymentClasses[props.paiement] || 'bg-primary';
    var montantFormatted = props.montant ? new Intl.NumberFormat('fr-FR').format(props.montant) + ' FCFA' : '0 FCFA';

    details.innerHTML =
        '<div class=\"mb-3\"><span class=\"badge bg-light text-dark\">' + (props.ticket || '') + '</span></div>' +
        '<table class=\"table table-borderless\">' +
        '<tr><td class=\"text-muted\">Client</td><td class=\"fw-bold\">' + (props.client || '-') + '</td></tr>' +
        '<tr><td class=\"text-muted\">Terrain</td><td>' + (props.terrain || '-') + '</td></tr>' +
        '<tr><td class=\"text-muted\">Horaire</td><td>' + startTime + ' - ' + endTime + '</td></tr>' +
        '<tr><td class=\"text-muted\">Montant</td><td class=\"fw-bold\">' + montantFormatted + '</td></tr>' +
        '<tr><td class=\"text-muted\">Paiement</td><td><span class=\"badge ' + paymentClass + '\">' + paymentLabel + '</span></td></tr>' +
        '</table>';

    document.getElementById('btnVoirDetails').href = ADMIN_URL + '/reservations/voir?id=' + event.id;

    var modal = new bootstrap.Modal(document.getElementById('reservationModal'));
    modal.show();
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
