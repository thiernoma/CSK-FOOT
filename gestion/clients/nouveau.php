<?php
/**
 * Nouveau client
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Client.php';

Auth::requireLogin();

$pageTitle = 'Nouveau client';
$breadcrumb = [
    ['label' => 'Clients', 'url' => url('clients/index.php')],
    ['label' => 'Nouveau']
];

$errors = [];
$data = [
    'nom' => '',
    'prenom' => '',
    'telephone' => '',
    'telephone_alt' => '',
    'email' => '',
    'adresse' => '',
    'type_client' => 'particulier',
    'notes' => ''
];

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
            'notes' => sanitize(post('notes'))
        ];

        // Validation
        if (empty($data['nom'])) {
            $errors[] = 'Le nom est obligatoire.';
        }

        if (empty($data['telephone'])) {
            $errors[] = 'Le téléphone est obligatoire.';
        }

        // Vérifier si le téléphone existe déjà
        if (Client::getByPhone($data['telephone'])) {
            $errors[] = 'Ce numéro de téléphone est déjà enregistré.';
        }

        if ($data['email'] && !isValidEmail($data['email'])) {
            $errors[] = 'L\'adresse email n\'est pas valide.';
        }

        if (empty($errors)) {
            try {
                $clientId = Client::create($data);
                Auth::logAction(Auth::id(), 'create', 'clients', $clientId);

                Session::flash('success', 'Client créé avec succès !');

                // Rediriger vers réservation si demandé
                if (post('redirect_reservation')) {
                    redirect(url('reservations/nouveau.php?client=' . $clientId));
                } else {
                    redirect(url('clients/voir.php?id=' . $clientId));
                }
            } catch (Exception $e) {
                $errors[] = 'Erreur lors de la création du client.';
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
            <div class="card-header">
                <h5><i class="fas fa-user-plus me-2"></i>Nouveau client</h5>
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
                                   value="<?= e($data['prenom']) ?>" placeholder="Prénom">
                        </div>

                        <!-- Nom -->
                        <div class="col-md-6">
                            <label for="nom" class="form-label">Nom <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nom" name="nom"
                                   value="<?= e($data['nom']) ?>" required placeholder="Nom de famille">
                        </div>

                        <!-- Téléphone -->
                        <div class="col-md-6">
                            <label for="telephone" class="form-label">Téléphone <span class="text-danger">*</span></label>
                            <input type="tel" class="form-control" id="telephone" name="telephone"
                                   value="<?= e($data['telephone']) ?>" required placeholder="77 XXX XX XX">
                        </div>

                        <!-- Téléphone alternatif -->
                        <div class="col-md-6">
                            <label for="telephone_alt" class="form-label">Téléphone secondaire</label>
                            <input type="tel" class="form-control" id="telephone_alt" name="telephone_alt"
                                   value="<?= e($data['telephone_alt']) ?>" placeholder="Optionnel">
                        </div>

                        <!-- Email -->
                        <div class="col-md-6">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= e($data['email']) ?>" placeholder="email@exemple.com">
                        </div>

                        <!-- Type client -->
                        <div class="col-md-6">
                            <label for="type_client" class="form-label">Type de client</label>
                            <select class="form-select" id="type_client" name="type_client">
                                <option value="particulier" <?= $data['type_client'] === 'particulier' ? 'selected' : '' ?>>Particulier</option>
                                <option value="entreprise" <?= $data['type_client'] === 'entreprise' ? 'selected' : '' ?>>Entreprise</option>
                                <option value="association" <?= $data['type_client'] === 'association' ? 'selected' : '' ?>>Association</option>
                            </select>
                        </div>

                        <!-- Adresse -->
                        <div class="col-12">
                            <label for="adresse" class="form-label">Adresse</label>
                            <textarea class="form-control" id="adresse" name="adresse" rows="2"
                                      placeholder="Adresse complète"><?= e($data['adresse']) ?></textarea>
                        </div>

                        <!-- Notes -->
                        <div class="col-12">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2"
                                      placeholder="Informations complémentaires..."><?= e($data['notes']) ?></textarea>
                        </div>
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between flex-wrap gap-2">
                        <a href="<?= url('clients/index.php') ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Annuler
                        </a>
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Enregistrer
                            </button>
                            <button type="submit" name="redirect_reservation" value="1" class="btn btn-accent">
                                <i class="fas fa-calendar-plus me-2"></i>Enregistrer & Réserver
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
