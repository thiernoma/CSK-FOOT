<?php
/**
 * Encaissement libre (hors réservation)
 * Buvette, location matériel, cotisations, autres revenus
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Caisse.php';
require_once APP_PATH . 'models/CategorieEncaissement.php';

Auth::requireLogin();

$pageTitle = 'Nouvel encaissement';
$breadcrumb = [
    ['label' => 'Paiements', 'url' => url('paiements/index.php')],
    ['label' => 'Nouvel encaissement']
];

$userId = Auth::id();
$sessionActive = Caisse::getActiveSession($userId);
$categories = CategorieEncaissement::getForFreePayment();
$errors = [];

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $categorieId = (int)post('categorie_id');
        $libelle = trim(sanitize(post('libelle')));
        $montant = (float)post('montant');
        $modePaiement = sanitize(post('mode_paiement'));
        $reference = sanitize(post('reference'));
        $notes = trim(sanitize(post('notes')));

        $cat = $categorieId ? CategorieEncaissement::getById($categorieId) : null;

        if (!$cat) {
            $errors[] = 'Catégorie invalide.';
        } elseif ($cat['code'] === 'reservation') {
            $errors[] = 'Pour un paiement de réservation, utilisez la page de réservation correspondante.';
        }
        if (empty($libelle)) {
            $errors[] = 'Le libellé est obligatoire.';
        }
        if ($montant <= 0) {
            $errors[] = 'Le montant doit être supérieur à 0.';
        }
        if (empty($modePaiement)) {
            $errors[] = 'Veuillez sélectionner un mode de paiement.';
        }

        if (!$sessionActive && !Auth::isAdmin()) {
            $errors[] = 'Vous devez ouvrir votre caisse avant d\'enregistrer un encaissement.';
        }

        if (empty($errors)) {
            try {
                $paiementId = Database::insert('paiements', [
                    'reservation_id' => null,
                    'categorie_id' => $categorieId,
                    'libelle' => $libelle,
                    'montant' => $montant,
                    'mode_paiement' => $modePaiement,
                    'reference' => $reference ?: null,
                    'type_paiement' => 'paiement',
                    'notes' => $notes ?: null,
                    'recu_par' => $userId,
                    'session_caisse_id' => $sessionActive['id'] ?? null
                ]);

                Auth::logAction($userId, 'payment_libre', 'paiements', $paiementId);
                Session::flash('success', 'Encaissement enregistré : ' . formatMoney($montant) . '.');
                redirect(url('paiements/nouveau.php'));
            } catch (Exception $e) {
                $errors[] = 'Erreur: ' . $e->getMessage();
            }
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-7">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><i class="fas fa-plus-circle me-2"></i>Nouvel encaissement</h4>
            <a href="<?= url('paiements/index.php') ?>" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-1"></i>Retour
            </a>
        </div>

        <?php if (!$sessionActive && !Auth::isAdmin()): ?>
            <div class="alert alert-warning d-flex justify-content-between align-items-center">
                <div>
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Aucune caisse ouverte.</strong> Vous devez ouvrir votre caisse pour enregistrer un encaissement.
                </div>
                <a href="<?= url('paiements/ma-caisse.php') ?>" class="btn btn-sm btn-warning">
                    <i class="fas fa-cash-register me-1"></i>Ouvrir ma caisse
                </a>
            </div>
        <?php elseif ($sessionActive): ?>
            <div class="alert alert-info py-2">
                <i class="fas fa-cash-register me-2"></i>
                Caisse ouverte depuis <?= formatDate($sessionActive['heure_ouverture'], 'd/m/Y à H:i') ?>
            </div>
        <?php endif; ?>

        <?php if ($errors): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-money-bill-wave me-2"></i>Encaissement</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>

                    <div class="mb-3">
                        <label class="form-label">Catégorie <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach ($categories as $cat): ?>
                            <div class="col-md-6">
                                <input type="radio" class="btn-check" name="categorie_id"
                                       id="cat_<?= $cat['id'] ?>" value="<?= $cat['id'] ?>"
                                       <?= post('categorie_id') == $cat['id'] ? 'checked' : '' ?> required>
                                <label class="btn btn-outline-primary w-100 text-start" for="cat_<?= $cat['id'] ?>"
                                       style="border-color: <?= e($cat['couleur']) ?>;">
                                    <?php if (!empty($cat['icone'])): ?>
                                        <i class="fas <?= e($cat['icone']) ?> me-2" style="color: <?= e($cat['couleur']) ?>;"></i>
                                    <?php endif; ?>
                                    <?= e($cat['libelle']) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if (empty($categories)): ?>
                            <small class="text-muted">Aucune catégorie disponible. <a href="<?= url('paiements/categories.php') ?>">Configurer les catégories</a>.</small>
                        <?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Libellé <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="libelle"
                               value="<?= e(post('libelle', '')) ?>"
                               placeholder="Ex: Coca + Eau, location 2 ballons, cotisation U13 mois de mars..." required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Montant <span class="text-danger">*</span></label>
                        <div class="input-group input-group-lg">
                            <input type="number" class="form-control" name="montant"
                                   value="<?= post('montant', '') ?>" min="1" step="any" required>
                            <span class="input-group-text">FCFA</span>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Mode de paiement <span class="text-danger">*</span></label>
                        <div class="row g-2">
                            <?php foreach (MODES_PAIEMENT as $key => $label): ?>
                            <div class="col-6 col-md-4">
                                <input type="radio" class="btn-check" name="mode_paiement"
                                       id="mode_<?= $key ?>" value="<?= $key ?>"
                                       <?= post('mode_paiement', 'especes') === $key ? 'checked' : '' ?>>
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

                    <div class="mb-3" id="referenceField" style="display: none;">
                        <label class="form-label">Référence transaction</label>
                        <input type="text" class="form-control" name="reference"
                               value="<?= e(post('reference', '')) ?>" placeholder="Numéro de transaction">
                    </div>

                    <div class="mb-4">
                        <label class="form-label">Notes (optionnel)</label>
                        <textarea class="form-control" name="notes" rows="2"><?= e(post('notes', '')) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-success btn-lg w-100">
                        <i class="fas fa-check me-2"></i>Enregistrer l'encaissement
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$inlineJs = "
document.querySelectorAll('input[name=\"mode_paiement\"]').forEach(radio => {
    radio.addEventListener('change', function() {
        document.getElementById('referenceField').style.display = ['wave','om','carte'].includes(this.value) ? 'block' : 'none';
    });
});
document.querySelector('input[name=\"mode_paiement\"]:checked')?.dispatchEvent(new Event('change'));
";

include VIEWS_PATH . 'layouts/footer.php';
?>
