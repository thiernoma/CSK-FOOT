<?php
/**
 * Modifier un client
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Client.php';

Auth::requireLogin();

$id = (int)get('id');
if (!$id) {
    Session::flash('danger', 'Client non spécifié.');
    redirect(url('clients/index.php'));
}

$client = Client::getById($id);
if (!$client) {
    Session::flash('danger', 'Client introuvable.');
    redirect(url('clients/index.php'));
}

$pageTitle = 'Modifier ' . ($client['prenom'] ?? '') . ' ' . $client['nom'];
$breadcrumb = [
    ['label' => 'Clients', 'url' => url('clients/index.php')],
    ['label' => ($client['prenom'] ?? '') . ' ' . $client['nom'], 'url' => url('clients/voir.php?id=' . $id)],
    ['label' => 'Modifier']
];

$errors = [];
$data = $client;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $data = [
            'nom' => sanitize(post('nom')),
            'prenom' => sanitize(post('prenom')),
            'telephone' => sanitize(post('telephone')),
            'telephone_alt' => sanitize(post('telephone_alt')),
            'email' => sanitize(post('email')),
            'adresse' => sanitize(post('adresse')),
            'type_client' => sanitize(post('type_client')),
            'statut' => sanitize(post('statut')),
            'notes' => sanitize(post('notes'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire.';
        }

        if (empty($data['telephone'])) {
            $errors[] = 'Le téléphone est obligatoire.';
        }

        // Vérifier si le téléphone existe déjà (autre client)
        $existingClient = Client::getByPhone($data['telephone']);
        if ($existingClient && $existingClient['id'] != $id) {
            $errors[] = 'Ce numéro de téléphone est déjà utilisé par un autre client.';
        }

        if ($data['email'] && !isValidEmail($data['email'])) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }

        if (empty($errors)) {
            try {
                Client::update($id, $data);
                Auth::logAction(Auth::id(), 'update', 'clients', $id);

                Session::flash('success', 'Client mis à jour avec succès !');
                redirect(url('clients/voir.php?id=' . $id));
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la mise à jour.';
                if (DEV_MODE) {
                    $errors[] = $e->getMessage();
                }
            }
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="row justify-content-center">
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5><i class="fas fa-user-edit me-2"></i>Modifier le client</h5>
                <span class="badge <?= statusBadgeClass($client['statut']) ?>"><?= translateStatus($client['statut']) ?></span>
            </div>
            <div class="card-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?= e($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>

                    <div class="row g-3">
                        <!-- Prénom -->
                        <div class="col-md-6">
                            <label for="prenom" class="form-label">Prénom</label>
                            <input type="text" class="form-control" id="prenom" name="prenom"
                                   value="<?= e($data['prenom']) ?>">
                        </div>

                        <!-- Nom -->
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?= e($data['nom']) ?>" required>
                        </div>

                        <!-- Téléphone -->
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                   value="<?= e($data['telephone']) ?>" required>
                        </div>

                        <!-- Téléphone alternatif -->
                        <div class="col-md-6">
                            <label for="telephone_alt" class="form-label">Téléphone secondaire</label>
                            <input type="tel" class="form-control" id="telephone_alt" name="telephone_alt"
                                   value="<?= e($data['telephone_alt']) ?>">
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e($data['email']) ?>">
                        </div>

                        <!-- Type client -->
                        <div class="col-md-3">
                            <label for="type_client" class="form-label">Type de client</label>
                            <select class="form-select" id="type_client" name="type_client">
                                <option value="particulier" <?= $data['type_client'] === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                                <option value="entreprise" <?= $data['type_client'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                                <option value="association" <?= $data['type_client'] === 'association' ? 'selected' : '' ?>>Association</option>
                            </select>
                        </div>

                        <!-- Statut -->
                        <div class="col-md-3">
                            <label for="statut" class="form-label">Statut</label>
                            <select class="form-select" id="statut" name="statut">
                                <option value="actif" <?= $data['statut'] === 'actif' ? 'selected' : '' ?>>Actif</option>
                                <option value="vip" <?= $data['statut'] === 'vip' ? 'selected' : '' ?>>VIP</option>
                                <option value="inactif" <?= $data['statut'] === 'inactif' ? 'selected' : '' ?>>Inactif</option>
                                <option value="bloque" <?= $data['statut'] === 'bloque' ? 'selected' : '' ?>>Bloqué</option>
                            </select>
                        </div>

                        <!-- Adresse -->
                        <div class="col-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"><?= e($data['adresse']) ?></textarea>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"><?= e($data['notes']) ?></textarea>
                        </div>
                    </div>

                    <!-- Statistiques (lecture seule) -->
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="mb-3">Statistiques</h6>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="h5 mb-0"><?= $client['nb_reservations'] ?></div>
                                <small class="text-muted">Réservations</small>
                            </div>
                            <div class="col-md-4">
                                <div class="h5 mb-0 text-success"><?= formatMoney($client['montant_total']) ?></div>
                                <small class="text-muted">CA Total</small>
                            </div>
                            <div class="col-md-4">
                                <div class="h5 mb-0"><?= $client['points_fidelite'] ?></div>
                                <small class="text-muted">Points fidélité</small>
                            </div>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="<?= url('clients/voir.php?id=' . $id) ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Enregistrer les modifications
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
