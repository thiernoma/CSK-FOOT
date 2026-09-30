<?php
/**
 * Détails d'une réservation
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Reservation.php';

Auth::requireLogin();

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

$pageTitle = 'Réservation ' . $reservation['numero_ticket'];
$breadcrumb = [
    ['label' => 'Réservations', 'url' => url('reservations/index.php')],
    ['label' => $reservation['numero_ticket']]
];

// Récupérer les paiements
$paiements = Reservation::getPayments($id);

// Calcul du reste à payer (en tenant compte de la remise)
$remise = (float)($reservation['remise'] ?? 0);
$montantNet = $reservation['montant'] - $remise;
$resteAPayer = $montantNet - $reservation['montant_paye'];
$totalRembourse = Reservation::getTotalRefunded($id);
$peutRembourser = $reservation['statut_reservation'] === 'annulee' && $reservation['montant_paye'] > 0;

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">
            <span class="badge bg-light text-dark"><?= e($reservation['numero_ticket']) ?></span>
        </h4>
        <div class="d-flex gap-2 align-items-center">
            <span class="badge <?= statusBadgeClass($reservation['statut_reservation']) ?>">
                <?= translateStatus($reservation['statut_reservation']) ?>
            </span>
            <span class="badge <?= statusBadgeClass($reservation['statut_paiement']) ?>">
                <?= translateStatus($reservation['statut_paiement']) ?>
            </span>
        </div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($reservation['statut_paiement'] !== 'paye' && $reservation['statut_reservation'] !== 'annulee'): ?>
        <a href="<?= url('reservations/paiement.php?id=' . $id) ?>" class="btn btn-success">
            <i class="fas fa-money-bill me-2"></i>Encaisser
        </a>
        <?php endif; ?>
        <?php if ($peutRembourser): ?>
        <a href="<?= url('reservations/remboursement.php?id=' . $id) ?>" class="btn btn-warning">
            <i class="fas fa-undo me-2"></i>Rembourser
        </a>
        <?php endif; ?>
        <a href="<?= url('reservations/recu.php?id=' . $id) ?>" class="btn btn-primary" target="_blank">
            <i class="fas fa-print me-2"></i>Imprimer reçu
        </a>
        <?php if ($reservation['statut_reservation'] === 'confirmee'): ?>
        <a href="<?= url('reservations/modifier.php?id=' . $id) ?>" class="btn btn-outline-secondary">
            <i class="fas fa-edit me-2"></i>Modifier
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">
    <!-- Colonne gauche: Infos réservation -->
    <div class="col-xl-8">
        <!-- Détails de la réservation -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Détails de la réservation</h5>
            </div>
            <div class="card-body">
                <div class="row g-4">
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="40%">N° Ticket</td>
                                <td class="fw-bold"><?= e($reservation['numero_ticket']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Date</td>
                                <td class="fw-bold"><?= formatDateFr($reservation['date_reservation']) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Horaire</td>
                                <td class="fw-bold">
                                    <?= formatTimeFull($reservation['heure_debut']) ?> - <?= formatTimeFull($reservation['heure_fin']) ?>
                                    <span class="text-muted">(<?= formatDuration($reservation['duree_heures']) ?>)</span>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Terrain</td>
                                <td>
                                    <a href="<?= url('terrains/voir.php?id=' . $reservation['terrain_id']) ?>" class="fw-bold">
                                        <?= e($reservation['terrain_nom']) ?>
                                    </a>
                                    <span class="badge bg-secondary ms-1"><?= terrainTypeLabel($reservation['terrain_type']) ?></span>
                                </td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted" width="40%">Montant</td>
                                <td class="fw-bold h5 text-primary mb-0"><?= formatMoney($reservation['montant']) ?></td>
                            </tr>
                            <?php if ($remise > 0): ?>
                            <tr>
                                <td class="text-muted">Remise</td>
                                <td class="fw-bold text-warning">
                                    - <?= formatMoney($remise) ?>
                                    <?php if (!empty($reservation['motif_remise'])): ?>
                                        <br><small class="text-muted fst-italic"><?= e($reservation['motif_remise']) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($reservation['remise_par_nom'])): ?>
                                        <br><small class="text-muted">par <?= e($reservation['remise_par_nom']) ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr>
                                <td class="text-muted">Net à payer</td>
                                <td class="fw-bold"><?= formatMoney($montantNet) ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="text-muted">Payé</td>
                                <td class="fw-bold text-success"><?= formatMoney($reservation['montant_paye']) ?></td>
                            </tr>
                            <?php if ($totalRembourse > 0): ?>
                            <tr>
                                <td class="text-muted">Remboursé</td>
                                <td class="fw-bold text-danger"><?= formatMoney($totalRembourse) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if ($resteAPayer > 0 && $reservation['statut_reservation'] !== 'annulee'): ?>
                            <tr>
                                <td class="text-muted">Reste à payer</td>
                                <td class="fw-bold text-danger"><?= formatMoney($resteAPayer) ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($reservation['acompte_requis']) && $reservation['acompte_requis'] > 0): ?>
                            <tr>
                                <td class="text-muted">Acompte requis</td>
                                <td>
                                    <?= formatMoney($reservation['acompte_requis']) ?>
                                    <?php if ($reservation['acompte_paye']): ?>
                                        <span class="badge bg-success ms-1"><i class="fas fa-check"></i> Payé</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark ms-1">En attente</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <td class="text-muted">Mode paiement</td>
                                <td>
                                    <?php if ($reservation['mode_paiement']): ?>
                                        <?= ucfirst($reservation['mode_paiement']) ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <?php if ($reservation['notes']): ?>
                <hr>
                <div>
                    <h6 class="text-muted mb-2">Notes</h6>
                    <p class="mb-0"><?= nl2br(e($reservation['notes'])) ?></p>
                </div>
                <?php endif; ?>

                <?php if ($reservation['statut_reservation'] === 'annulee' && !empty($reservation['motif_annulation'])): ?>
                <hr>
                <div class="alert alert-danger mb-0">
                    <h6 class="mb-2"><i class="fas fa-times-circle me-2"></i>Motif d'annulation</h6>
                    <p class="mb-0"><?= nl2br(e($reservation['motif_annulation'])) ?></p>
                </div>
                <?php endif; ?>

                <hr>
                <div class="d-flex justify-content-between text-muted small">
                    <span>Créée le <?= formatDate($reservation['created_at'], 'd/m/Y H:i') ?></span>
                    <?php if ($reservation['cree_par_nom']): ?>
                    <span>Par <?= e($reservation['cree_par_nom']) ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Historique des paiements -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-receipt me-2"></i>Historique des paiements</h5>
                <?php if ($resteAPayer > 0): ?>
                <a href="<?= url('reservations/paiement.php?id=' . $id) ?>" class="btn btn-sm btn-success">
                    <i class="fas fa-plus me-1"></i>Ajouter paiement
                </a>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($paiements)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-receipt fa-2x mb-2 opacity-50"></i>
                        <p class="mb-0">Aucun paiement enregistré</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Montant</th>
                                    <th>Mode</th>
                                    <th>Référence</th>
                                    <th>Reçu par</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paiements as $p): ?>
                                <tr>
                                    <td><?= formatDate($p['created_at'], 'd/m/Y H:i') ?></td>
                                    <td class="fw-bold <?= $p['type_paiement'] === 'remboursement' ? 'text-danger' : 'text-success' ?>">
                                        <?= $p['type_paiement'] === 'remboursement' ? '-' : '+' ?>
                                        <?= formatMoney($p['montant']) ?>
                                    </td>
                                    <td><?= ucfirst($p['mode_paiement']) ?></td>
                                    <td><?= e($p['reference'] ?? '-') ?></td>
                                    <td><?= e(recuParLabel($p['recu_par_nom'] ?? null, $p['mode_paiement'] ?? '')) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Colonne droite: Client -->
    <div class="col-xl-4">
        <!-- Infos client -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Client</h5>
            </div>
            <div class="card-body">
                <div class="d-flex align-items-center mb-3">
                    <div class="user-avatar me-3" style="width: 50px; height: 50px; font-size: 18px;">
                        <?= getInitials(explode(' ', $reservation['client_nom'])[1] ?? '', explode(' ', $reservation['client_nom'])[0] ?? '') ?>
                    </div>
                    <div>
                        <h5 class="mb-0">
                            <a href="<?= url('clients/voir.php?id=' . $reservation['client_id']) ?>">
                                <?= e($reservation['client_nom']) ?>
                            </a>
                        </h5>
                    </div>
                </div>

                <div class="mb-2">
                    <i class="fas fa-phone text-muted me-2"></i>
                    <a href="tel:<?= e($reservation['client_telephone']) ?>">
                        <?= formatPhone($reservation['client_telephone']) ?>
                    </a>
                </div>

                <?php if ($reservation['client_email']): ?>
                <div class="mb-2">
                    <i class="fas fa-envelope text-muted me-2"></i>
                    <a href="mailto:<?= e($reservation['client_email']) ?>">
                        <?= e($reservation['client_email']) ?>
                    </a>
                </div>
                <?php endif; ?>

                <hr>

                <a href="<?= url('clients/voir.php?id=' . $reservation['client_id']) ?>" class="btn btn-outline-primary btn-sm w-100">
                    <i class="fas fa-eye me-2"></i>Voir le profil client
                </a>
            </div>
        </div>

        <!-- QR Code de vérification -->
        <?php if (!empty($reservation['qr_code_token'])): ?>
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code</h5>
            </div>
            <div class="card-body text-center">
                <img src="<?= Reservation::getQRCodeUrl($reservation, 150) ?>"
                     alt="QR Code" class="mb-2">
                <p class="text-muted small mb-0">Scanner pour vérifier la réservation</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions rapides -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-bolt me-2"></i>Actions</h5>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="<?= url('reservations/recu.php?id=' . $id) ?>" class="btn btn-primary" target="_blank">
                        <i class="fas fa-print me-2"></i>Imprimer le reçu
                    </a>

                    <?php if ($reservation['statut_reservation'] === 'confirmee'): ?>
                    <a href="<?= url('reservations/modifier.php?id=' . $id) ?>" class="btn btn-outline-primary">
                        <i class="fas fa-edit me-2"></i>Modifier la réservation
                    </a>

                    <a href="<?= url('reservations/annuler.php?id=' . $id) ?>" class="btn btn-outline-danger">
                        <i class="fas fa-times me-2"></i>Annuler la réservation
                    </a>
                    <?php endif; ?>

                    <?php if ($peutRembourser): ?>
                    <a href="<?= url('reservations/remboursement.php?id=' . $id) ?>" class="btn btn-warning">
                        <i class="fas fa-undo me-2"></i>Rembourser <?= formatMoney($reservation['montant_paye']) ?>
                    </a>
                    <?php endif; ?>

                    <a href="<?= url('reservations/nouveau.php?client=' . $reservation['client_id']) ?>" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i>Nouvelle réservation pour ce client
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
