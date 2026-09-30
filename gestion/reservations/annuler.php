<?php
/**
 * Annuler une réservation (avec remboursement optionnel)
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Notification.php';
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

if ($reservation['statut_reservation'] !== 'confirmee') {
    Session::flash('warning', 'Cette réservation ne peut pas être annulée.');
    redirect(url('reservations/voir.php?id=' . $id));
}

$montantPaye = (float)$reservation['montant_paye'];
$montantRembourseDeja = Reservation::getTotalRefunded($id);
$resteRemboursable = $montantPaye; // montant_paye est déjà net des remboursements

$pageTitle = 'Annuler la réservation - ' . $reservation['numero_ticket'];
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index.php')],
    ['label' => $reservation['numero_ticket'], 'url' => url('reservations/voir.php?id=' . $id)],
    ['label' => 'Annulation']
];

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $motif = trim(sanitize(post('motif')));
        $rembourser = post('rembourser') === '1';
        $montantRemb = (float)post('montant_remboursement');
        $modeRemb = sanitize(post('mode_remboursement'));
        $motifRemb = trim(sanitize(post('motif_remboursement')));
        $referenceRemb = sanitize(post('reference_remboursement'));

        if (empty($motif)) {
            $errors[] = 'Le motif d\'annulation est obligatoire.';
        }

        if ($rembourser) {
            if ($montantRemb <= 0) {
                $errors[] = 'Le montant du remboursement doit être supérieur à 0.';
            }
            if ($montantRemb > $resteRemboursable) {
                $errors[] = 'Le remboursement ne peut pas dépasser ' . formatMoney($resteRemboursable) . '.';
            }
            if (empty($modeRemb)) {
                $errors[] = 'Veuillez sélectionner un mode de remboursement.';
            }
            if (empty($motifRemb)) {
                $errors[] = 'Le motif du remboursement est obligatoire.';
            }
        }

        if (empty($errors)) {
            $userInfo = Auth::user();
            $motifComplet = $motif . ' (par ' . ($userInfo['prenom'] ?? '') . ' ' . ($userInfo['nom'] ?? '') . ')';

            // Annulation
            $resultCancel = Reservation::cancel($id, $motifComplet, Auth::id());

            if (!$resultCancel['success']) {
                $errors[] = $resultCancel['message'];
            } else {
                Auth::logAction(Auth::id(), 'cancel', 'reservations', $id);
                Notification::notifyReservationCancelled($reservation);

                // Remboursement éventuel
                if ($rembourser) {
                    $resultRefund = Reservation::addRefund($id, [
                        'montant' => $montantRemb,
                        'mode_paiement' => $modeRemb,
                        'reference' => $referenceRemb,
                        'motif' => $motifRemb,
                        'recu_par' => Auth::id(),
                        'session_caisse_id' => $sessionActive['id'] ?? null
                    ]);

                    if ($resultRefund['success']) {
                        Auth::logAction(Auth::id(), 'refund', 'paiements', $resultRefund['paiement_id']);
                        Session::flash('success', 'Réservation annulée et remboursement enregistré.');
                    } else {
                        Session::flash('warning', 'Réservation annulée, mais le remboursement a échoué : ' . $resultRefund['message']);
                    }
                } else {
                    Session::flash('success', 'Réservation annulée.');
                }

                redirect(url('reservations/voir.php?id=' . $id));
            }
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
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
            <div class="card-header bg-danger text-white">
                <h5 class="mb-0"><i class="fas fa-times-circle me-2"></i>Annulation de la réservation</h5>
            </div>
            <div class="card-body">
                <form method="POST"
                      data-confirm="Confirmer l'annulation de cette réservation ?"
                      data-confirm-title="Annuler la réservation"
                      data-confirm-text="<i class='fas fa-ban me-1'></i> Annuler la réservation"
                      data-confirm-class="btn-danger"
                      data-confirm-icon="fa-ban"
                      data-confirm-icon-class="text-danger">
                    <?= csrfField() ?>

                    <div class="mb-4">
                        <label class="form-label">Motif d'annulation <span class="text-danger">*</span></label>
                        <textarea class="form-control" name="motif" rows="3"
                                  placeholder="Indiquez le motif de l'annulation..." required><?= e(post('motif', '')) ?></textarea>
                    </div>

                    <?php if ($montantPaye > 0): ?>
                    <div class="card border-warning mb-3">
                        <div class="card-header bg-warning bg-opacity-25">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox"
                                       id="rembourserCheck" name="rembourser" value="1"
                                       <?= post('rembourser') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label fw-bold" for="rembourserCheck">
                                    <i class="fas fa-undo me-2"></i>Effectuer un remboursement
                                </label>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Montant payé : <strong><?= formatMoney($montantPaye) ?></strong>
                            </small>
                        </div>
                        <div class="card-body" id="remboursementBody" style="display: <?= post('rembourser') === '1' ? 'block' : 'none' ?>;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label">Montant à rembourser <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" name="montant_remboursement"
                                               value="<?= post('montant_remboursement', $resteRemboursable) ?>"
                                               min="0" max="<?= $resteRemboursable ?>" step="any">
                                        <span class="input-group-text">FCFA</span>
                                    </div>
                                    <small class="text-muted">Maximum : <?= formatMoney($resteRemboursable) ?></small>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label">Mode de remboursement <span class="text-danger">*</span></label>
                                    <select class="form-select" name="mode_remboursement">
                                        <option value="">-- Choisir --</option>
                                        <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                                        <option value="<?= $key ?>" <?= post('mode_remboursement') === $key ? 'selected' : '' ?>><?= $label ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Motif du remboursement <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="motif_remboursement"
                                           value="<?= e(post('motif_remboursement', '')) ?>"
                                           placeholder="Ex: annulation client, intempéries, terrain indisponible...">
                                </div>

                                <div class="col-md-12">
                                    <label class="form-label">Référence (optionnel)</label>
                                    <input type="text" class="form-control" name="reference_remboursement"
                                           value="<?= e(post('reference_remboursement', '')) ?>"
                                           placeholder="Numéro de transaction si Wave/OM/Carte">
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Aucun paiement n'a été enregistré pour cette réservation. Aucun remboursement nécessaire.
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= url('reservations/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Retour
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-check me-2"></i>Confirmer l'annulation
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
const cb = document.getElementById('rembourserCheck');
if (cb) {
    cb.addEventListener('change', function() {
        document.getElementById('remboursementBody').style.display = this.checked ? 'block' : 'none';
    });
}
";

include VIEWS_PATH . 'layouts/footer.php';
?>
