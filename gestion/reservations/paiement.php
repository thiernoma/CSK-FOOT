<?php
/**
 * Encaissement d'une réservation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';
require_once APP_PATH . 'models/Notification.php';
require_once APP_PATH . 'models/Caisse.php';

Auth::requireLogin();

// Récupérer la session de caisse active de l'utilisateur (si caissier)
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

// Calcul du reste à payer (en tenant compte de la remise déjà appliquée)
$remiseActuelle = (float)($reservation['remise'] ?? 0);
$montantNet = $reservation['montant'] - $remiseActuelle;
$resteAPayer = $montantNet - $reservation['montant_paye'];

if ($resteAPayer <= 0) {
    Session::flash('info', 'Cette réservation est déjà payée.');
    redirect(url('reservations/voir.php?id=' . $id));
}

if ($reservation['statut_reservation'] === 'annulee') {
    Session::flash('warning', 'Impossible d\'encaisser une réservation annulée.');
    redirect(url('reservations/voir.php?id=' . $id));
}

$pageTitle = 'Encaissement - ' . $reservation['numero_ticket'];
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index.php')],
    ['label' => $reservation['numero_ticket'], 'url' => url('reservations/voir.php?id=' . $id)],
    ['label' => 'Encaissement']
];

$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $action = post('action', 'paiement');

        if ($action === 'remise') {
            // Application d'une remise
            $remise = (float)post('remise_montant');
            $motifRemise = sanitize(post('motif_remise'));

            if ($remise < 0) {
                $errors[] = 'La remise doit être positive.';
            }

            if (empty($motifRemise)) {
                $errors[] = 'Le motif de la remise est obligatoire.';
            }

            if (empty($errors)) {
                $result = Reservation::applyRemise($id, $remise, $motifRemise, Auth::id());
                if ($result['success']) {
                    Auth::logAction(Auth::id(), 'remise', 'reservations', $id);
                    Session::flash('success', 'Remise appliquée avec succès.');
                    redirect(url('reservations/paiement.php?id=' . $id));
                } else {
                    $errors[] = $result['message'];
                }
            }
        } else {
            // Encaissement classique
            $montant = (float)post('montant');
            $modePaiement = sanitize(post('mode_paiement'));
            $reference = sanitize(post('reference'));

            if ($montant <= 0) {
                $errors[] = 'Le montant doit être supérieur à 0.';
            }

            if ($montant > $resteAPayer) {
                $errors[] = 'Le montant ne peut pas dépasser le reste à payer (' . formatMoney($resteAPayer) . ').';
            }

            if (empty($modePaiement)) {
                $errors[] = 'Veuillez sélectionner un mode de paiement.';
            }

            if (empty($errors)) {
                $result = Reservation::addPayment($id, [
                    'montant' => $montant,
                    'mode_paiement' => $modePaiement,
                    'reference' => $reference,
                    'recu_par' => Auth::id(),
                    'session_caisse_id' => $sessionActive['id'] ?? null
                ]);

                if ($result['success']) {
                    Auth::logAction(Auth::id(), 'payment', 'paiements', $result['paiement_id']);

                    // Notifier les admins du paiement reçu
                    Notification::notifyPaymentReceived([
                        'id' => $result['paiement_id'],
                        'montant' => $montant,
                        'client_nom' => $reservation['client_nom']
                    ]);

                    Session::flash('success', 'Paiement enregistré avec succès !');

                    if ($montant >= $resteAPayer) {
                        if (post('print_receipt')) {
                            redirect(url('reservations/recu.php?id=' . $id));
                        }
                    }

                    redirect(url('reservations/voir.php?id=' . $id));
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

// Historique des paiements
$paiements = Reservation::getPayments($id);

// Autorisation pour appliquer / modifier une remise
// Caissiers et gérants inclus (caissier => permission 'paiements')
$peutFaireRemise = Auth::isAdmin()
    || Auth::can('validation_paiements')
    || Auth::can('paiements')
    || Auth::hasRole('gerant');

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <!-- Résumé réservation -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
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
                    <div class="col-md-4 text-md-end">
                        <div class="h4 text-primary mb-0"><?= formatMoney($reservation['montant']) ?></div>
                        <span class="badge <?= statusBadgeClass($reservation['statut_paiement']) ?>">
                            <?= translateStatus($reservation['statut_paiement']) ?>
                        </span>
                    </div>
                </div>
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

        <?php if (!$sessionActive && !Auth::isAdmin()): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Aucune caisse ouverte.</strong> Ouvrez votre caisse pour rattacher cet encaissement à votre session.
                </div>
                <a href="<?= url('paiements/ma-caisse.php') ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-cash-register me-1"></i>Ouvrir ma caisse
                </a>
            </div>
        <?php elseif ($sessionActive): ?>
            <div class="alert alert-info py-2">
                <i class="fas fa-cash-register me-2"></i>
                Caisse ouverte depuis <?= formatDate($sessionActive['heure_ouverture'], 'd/m/Y à H:i') ?>
                — fond de caisse : <strong><?= formatMoney($sessionActive['fond_caisse']) ?></strong>
            </div>
        <?php endif; ?>

        <?php if ($remiseActuelle > 0): ?>
        <div class="alert alert-info d-flex justify-content-between align-items-center">
            <div>
                <i class="fas fa-tag me-2"></i>
                <strong>Remise appliquée : <?= formatMoney($remiseActuelle) ?></strong>
                <?php if (!empty($reservation['motif_remise'])): ?>
                    — <?= e($reservation['motif_remise']) ?>
                <?php endif; ?>
                <?php if (!empty($reservation['remise_par_nom'])): ?>
                    <br><small class="text-muted">Accordée par <?= e($reservation['remise_par_nom']) ?>
                    <?php if (!empty($reservation['date_remise'])): ?>
                        le <?= formatDate($reservation['date_remise'], 'd/m/Y H:i') ?>
                    <?php endif; ?>
                    </small>
                <?php endif; ?>
            </div>
            <?php if ($peutFaireRemise): ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" data-bs-target="#remiseForm">
                <i class="fas fa-edit me-1"></i>Modifier
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($peutFaireRemise): ?>
        <div class="collapse mb-4 <?= ($remiseActuelle == 0) ? '' : '' ?>" id="remiseForm">
            <div class="card border-warning">
                <div class="card-header bg-warning text-dark">
                    <h6 class="mb-0"><i class="fas fa-tag me-2"></i>Appliquer une remise</h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="remise">

                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label">Montant remise</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="remise_montant"
                                           value="<?= $remiseActuelle ?>" min="0"
                                           max="<?= $reservation['montant'] - $reservation['montant_paye'] ?>"
                                           step="any" required>
                                    <span class="input-group-text">FCFA</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Motif <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="motif_remise"
                                       value="<?= e($reservation['motif_remise'] ?? '') ?>"
                                       placeholder="Ex: client fidèle, geste commercial..." required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-check me-1"></i>Appliquer
                                </button>
                            </div>
                        </div>
                        <small class="text-muted mt-2 d-block">
                            La remise est limitée au montant non encore payé (<?= formatMoney($reservation['montant'] - $reservation['montant_paye']) ?>).
                            Saisir 0 pour annuler la remise.
                        </small>
                    </form>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Formulaire paiement -->
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Encaissement</h5>
                    </div>
                    <div class="card-body">
                        <div class="bg-light rounded p-3 mb-4">
                            <?php if ($remiseActuelle > 0): ?>
                            <div class="d-flex justify-content-between text-muted small mb-1">
                                <span>Montant initial</span>
                                <span><?= formatMoney($reservation['montant']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-warning small mb-1">
                                <span>Remise</span>
                                <span>- <?= formatMoney($remiseActuelle) ?></span>
                            </div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span>Net à payer</span>
                                <span class="fw-bold"><?= formatMoney($montantNet) ?></span>
                            </div>
                            <div class="d-flex justify-content-between text-success small mb-2">
                                <span>Déjà payé</span>
                                <span>- <?= formatMoney($reservation['montant_paye']) ?></span>
                            </div>
                            <hr class="my-2">
                            <?php endif; ?>
                            <div class="text-center">
                                <div class="text-muted mb-1">Reste à payer</div>
                                <div class="h2 text-danger mb-0"><?= formatMoney($resteAPayer) ?></div>
                            </div>
                        </div>

                        <?php if ($peutFaireRemise && $remiseActuelle == 0): ?>
                        <div class="text-center mb-3">
                            <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="collapse" data-bs-target="#remiseForm">
                                <i class="fas fa-tag me-1"></i>Appliquer une remise
                            </button>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrfField() ?>
                            <input type="hidden" name="action" value="paiement">

                            <div class="mb-3">
                                <label class="form-label">Montant à encaisser <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <input type="number" class="form-control" name="montant"
                                           value="<?= $resteAPayer ?>" min="1" max="<?= $resteAPayer ?>"
                                           step="any" required autofocus>
                                    <span class="input-group-text">FCFA</span>
                                </div>
                                <div class="d-flex gap-2 mt-2">
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(<?= $resteAPayer ?>)">
                                        Tout payer
                                    </button>
                                    <?php if ($resteAPayer > 10000): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(10000)">
                                        10 000
                                    </button>
                                    <?php endif; ?>
                                    <?php if ($resteAPayer > 5000): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="setAmount(5000)">
                                        5 000
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                                    <div class="col-6">
                                        <input type="radio" class="btn-check" name="mode_paiement"
                                               id="mode_<?= $key ?>" value="<?= $key ?>"
                                               <?= $key === 'especes' ? 'checked' : '' ?>>
                                        <label class="btn btn-outline-primary w-100" for="mode_<?= $key ?>">
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

                            <div class="mb-4" id="referenceField" style="display: none;">
                                <label class="form-label">Référence transaction</label>
                                <input type="text" class="form-control" name="reference"
                                       placeholder="Numéro de transaction Wave/OM">
                            </div>

                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-success btn-lg">
                                    <i class="fas fa-check me-2"></i>Valider le paiement
                                </button>
                                <button type="submit" name="print_receipt" value="1" class="btn btn-outline-success">
                                    <i class="fas fa-print me-2"></i>Valider & Imprimer le reçu
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Historique -->
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-history me-2"></i>Paiements précédents</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($paiements)): ?>
                            <div class="text-center py-4 text-muted">
                                <p class="mb-0">Aucun paiement</p>
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush">
                                <?php foreach ($paiements as $p): ?>
                                <li class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <strong class="<?= $p['type_paiement'] === 'remboursement' ? 'text-danger' : 'text-success' ?>">
                                                <?= $p['type_paiement'] === 'remboursement' ? '-' : '+' ?><?= formatMoney($p['montant']) ?>
                                            </strong>
                                            <br>
                                            <small class="text-muted"><?= ucfirst($p['mode_paiement']) ?></small>
                                            <?php if ($p['type_paiement'] === 'remboursement' && !empty($p['motif'])): ?>
                                                <br><small class="text-muted fst-italic"><?= e($p['motif']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted"><?= formatDate($p['created_at'], 'd/m/Y H:i') ?></small>
                                        </div>
                                    </div>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between">
                            <span>Déjà payé:</span>
                            <strong class="text-success"><?= formatMoney($reservation['montant_paye']) ?></strong>
                        </div>
                    </div>
                </div>

                <a href="<?= url('reservations/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary w-100 mt-3">
                    <i class="fas fa-arrow-left me-2"></i>Retour
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
function setAmount(amount) {
    document.querySelector('input[name=\"montant\"]').value = amount;
}

// Afficher le champ référence pour Wave/OM
document.querySelectorAll('input[name=\"mode_paiement\"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const refField = document.getElementById('referenceField');
        refField.style.display = ['wave', 'om', 'carte'].includes(this.value) ? 'block' : 'none';
    });
});
";

include VIEWS_PATH . 'layouts/footer.php';
?>
