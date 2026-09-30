<?php
/**
 * Remboursement d'une réservation annulée
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Caisse.php';

Auth::requireLogin();

$sessionActive = Caisse::getActiveSession(Auth::id());

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Réservation non spécifiée.');
    redirect(url('reservations/index.php'));
}

$reservation = Reservation::getById($id);
if (!$reservation) {
    Session::flash('danger', 'Réservation introuvable.');
    redirect(url('reservations/index.php'));
}

if ($reservation['statut_reservation'] !== 'annulee') {
    Session::flash('warning', 'Le remboursement n\'est possible que pour les réservations annulées.');
    redirect(url('reservations/voir.php?id=' . $id));
}

$resteRemboursable = (float)$reservation['montant_paye'];

if ($resteRemboursable <= 0) {
    Session::flash('info', 'Aucun montant à rembourser pour cette réservation.');
    redirect(url('reservations/voir.php?id=' . $id));
}

$pageTitle = 'Remboursement - ' . $reservation['numero_ticket'];
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index.php')],
    ['label' => $reservation['numero_ticket'], 'url' => url('reservations/voir.php?id=' . $id)],
    ['label' => 'Remboursement']
];

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $montant = (float)post('montant');
        $modePaiement = sanitize(post('mode_paiement'));
        $motif = trim(sanitize(post('motif')));
        $reference = sanitize(post('reference'));

        $result = Reservation::addRefund($id, [
            'montant' => $montant,
            'mode_paiement' => $modePaiement,
            'motif' => $motif,
            'reference' => $reference,
            'recu_par' => Auth::id(),
            'session_caisse_id' => $sessionActive['id'] ?? null
        ]);

        if ($result['success']) {
            Auth::logAction(Auth::id(), 'refund', 'paiements', $result['paiement_id']);
            Session::flash('success', 'Remboursement enregistré avec succès.');
            redirect(url('reservations/voir.php?id=' . $id));
        } else {
            $errors[] = $result['message'];
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-body">
                <h5 class="mb-2">
                    <span class="badge bg-light text-dark me-2"><?= e($reservation['numero_ticket']) ?></span>
                    <?= e($reservation['client_nom']) ?>
                </h5>
                <p class="text-muted mb-0">
                    <i class="fas fa-calendar me-1"></i>
                    <?= formatDateFr($reservation['date_reservation']) ?>
                    &nbsp;•&nbsp;
                    <i class="fas fa-clock me-1"></i>
                    <?= formatTimeFull($reservation['heure_debut']) ?> - <?= formatTimeFull($reservation['heure_fin']) ?>
                    &nbsp;•&nbsp;
                    <i class="fas fa-futbol me-1"></i>
                    <?= e($reservation['terrain_nom']) ?>
                </p>
            </div>
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

        <div class="card">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="fas fa-undo me-2"></i>Remboursement</h5>
            </div>
            <div class="card-body">
                <div class="bg-light rounded p-3 mb-4 text-center">
                    <div class="text-muted mb-1">Montant remboursable</div>
                    <div class="h2 text-warning mb-0"><?= formatMoney($resteRemboursable) ?></div>
                </div>

                <form method="POST"
                      data-confirm="Confirmer le remboursement ? Cette opération sera enregistrée dans la caisse."
                      data-confirm-title="Valider le remboursement"
                      data-confirm-text="<i class='fas fa-undo me-1'></i> Rembourser"
                      data-confirm-class="btn-warning"
                      data-confirm-icon="fa-undo"
                      data-confirm-icon-class="text-warning">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Montant à rembourser <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control" name="montant"
                                   value="<?= post('montant', $resteRemboursable) ?>"
                                   min="1" max="<?= $resteRemboursable ?>" step="any" required autofocus>
                            <span class="input-group-text">FCFA</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mode de remboursement <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="mode_paiement"
                                       id="mode_<?= $key ?>" value="<?= $key ?>"
                                       <?= post('mode_paiement', 'especes') === $key ? 'checked' : '' ?>>
                                <label class="btn btn-outline-warning w-100" for="mode_<?= $key ?>">
                                    <?php
                                    $icon = match($key) {
                                        'especes' => 'fa-money-bill',
                                        'wave' => 'fa-mobile-alt',
                                        'om' => 'fa-mobile-alt',
                                        'carte' => 'fa-credit-card',
                                        default => 'fa-money-check'
                                    };
                                    ?>
                                    <i class="fas <?= $icon ?> me-2"></i><?= $label ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Motif du remboursement <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="motif"
                               value="<?= e(post('motif', '')) ?>"
                               placeholder="Ex: annulation client, intempéries, terrain indisponible..." required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Référence (optionnel)</label>
                        <input type="text" class="form-control" name="reference"
                               value="<?= e(post('reference', '')) ?>"
                               placeholder="Numéro de transaction si Wave/OM/Carte">
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('reservations/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-check me-2"></i>Valider le remboursement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
