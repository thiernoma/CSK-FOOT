<?php
/**
 * Planning des séances (calendrier)
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Seance.php';

Auth::requireLogin();
Auth::requirePermission('academie');

$pageTitle = 'Planning des séances';
$breadcrumb = [
    ['label' => 'Académie'],
    ['label' => 'Séances', 'url' => url('seances/index.php')],
    ['label' => 'Planning']
];

$categories = Seance::getCategories();

// Plage horaire d'affichage du planning, depuis les paramètres (fallback constantes)
$heureOuverture = getParam('heure_ouverture', OPENING_TIME);
$heureFermeture = getParam('heure_fermeture', CLOSING_TIME);
[$ohH, $ohM] = array_map('intval', explode(':', $heureOuverture));
[$fhH, $fhM] = array_map('intval', explode(':', $heureFermeture));
$slotMinTime = sprintf('%02d:%02d:00', $ohH, $ohM);
if ($fhH < $ohH || ($fhH === $ohH && $fhM <= $ohM)) {
    $fhH += 24;
}
$slotMaxTime = sprintf('%02d:%02d:00', $fhH, $fhM);

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Planning des séances</h4>
        <p class="text-muted mb-0">Vue calendrier des entraînements</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" id="filterCategorie" style="width:auto;">
            <option value="">Toutes catégories</option>
            <?php foreach ($categories as $key => $label): ?>
                <option value="<?= $key ?>"><?= $label ?></option>
            <?php endforeach; ?>
        </select>
        <a href="<?= url('seances/index.php') ?>" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-list me-1"></i>Liste
        </a>
    </div>
</div>

<!-- Légende -->
<div class="card mb-4">
    <div class="card-body py-2">
        <div class="d-flex flex-wrap gap-3 align-items-center">
            <small class="text-muted">Catégories:</small>
            <?php foreach ($categories as $key => $label): ?>
                <span class="badge" style="background-color: <?= Seance::getCategorieColor($key) ?>">
                    <?= $key ?>
                </span>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Calendrier -->
<div class="card">
    <div class="card-body">
        <div id="calendar"></div>
    </div>
</div>

<!-- Modal détails séance -->
<div class="modal fade" id="seanceDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Détails de la séance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Contenu dynamique -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                <a href="#" id="btnPresences" class="btn btn-primary">
                    <i class="fas fa-user-check me-2"></i>Gérer les présences
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
document.addEventListener('DOMContentLoaded', function() {
    var calendarEl = document.getElementById('calendar');

    // Vérifier si FullCalendar est chargé
    if (typeof FullCalendar === 'undefined') {
        calendarEl.innerHTML = '<div class=\"alert alert-danger\">Erreur: FullCalendar n\\'est pas chargé. Veuillez rafraîchir la page.</div>';
        console.error('FullCalendar non chargé');
        return;
    }

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'timeGridWeek',
        locale: 'fr',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay'
        },
        buttonText: {
            today: 'Aujourd\\'hui',
            month: 'Mois',
            week: 'Semaine',
            day: 'Jour'
        },
        slotMinTime: '{$slotMinTime}',
        slotMaxTime: '{$slotMaxTime}',
        allDaySlot: false,
        height: 'auto',
        nowIndicator: true,
        slotDuration: '00:30:00',
        events: function(info, successCallback, failureCallback) {
            var categorie = document.getElementById('filterCategorie').value;
            var startDate = info.startStr.split('T')[0];
            var endDate = info.endStr.split('T')[0];
            fetch('" . APP_URL . "/api/seances.php?action=calendar&start=' + startDate + '&end=' + endDate + '&categorie=' + categorie)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.events) {
                        successCallback(data.events);
                    } else {
                        successCallback([]);
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    failureCallback(error);
                });
        },
        eventClick: function(info) {
            var props = info.event.extendedProps;
            document.getElementById('modalTitle').textContent = info.event.title;
            document.getElementById('modalBody').innerHTML = `
                <table class='table table-sm mb-0'>
                    <tr><th style='width:120px;'>Date</th><td>` + new Date(info.event.start).toLocaleDateString('fr-FR', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'}) + `</td></tr>
                    <tr><th>Horaire</th><td>` + new Date(info.event.start).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) + ` - ` + new Date(info.event.end).toLocaleTimeString('fr-FR', {hour: '2-digit', minute: '2-digit'}) + `</td></tr>
                    <tr><th>Catégorie</th><td><span class='badge' style='background-color: ` + info.event.backgroundColor + `'>` + (props.categorie || '-') + `</span></td></tr>
                    <tr><th>Terrain</th><td>` + (props.terrain || '-') + `</td></tr>
                    <tr><th>Entraîneur</th><td>` + (props.entraineur || '-') + `</td></tr>
                    <tr><th>Statut</th><td>` + translateSeanceStatus(props.statut) + `</td></tr>
                </table>
            `;
            document.getElementById('btnPresences').href = '" . url('seances/presences') . "?id=' + info.event.id;
            new bootstrap.Modal(document.getElementById('seanceDetailModal')).show();
        },
        eventDidMount: function(info) {
            var props = info.event.extendedProps;
            info.el.title = info.event.title + ' - ' + (props.terrain || 'Sans terrain');
        }
    });
    calendar.render();

    // Filtre par catégorie
    document.getElementById('filterCategorie').addEventListener('change', function() {
        calendar.refetchEvents();
    });
});

function translateSeanceStatus(status) {
    var translations = {
        'planifiee': '<span class=\"badge bg-secondary\">Planifiée</span>',
        'en_cours': '<span class=\"badge bg-warning\">En cours</span>',
        'terminee': '<span class=\"badge bg-success\">Terminée</span>',
        'annulee': '<span class=\"badge bg-danger\">Annulée</span>'
    };
    return translations[status] || status;
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
