<?php
/**
 * Paramètres du système
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';

Auth::requireLogin();
Auth::requireAdmin();

$pageTitle = 'Paramètres';
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Paramètres']
];

// Les fonctions getParam() et setParam() sont définies dans helpers.php

$errors = [];
$success = false;

if (isPost()) {
    if (!verifyCsrf()) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $section = post('section');

        if ($section === 'general') {
            setParam('app_name', sanitize(post('app_name')), 'Nom de l\'application');
            setParam('app_slogan', sanitize(post('app_slogan')), 'Slogan');
            setParam('academie_name', sanitize(post('academie_name')), 'Nom de l\'académie');
            setParam('contact_email', sanitize(post('contact_email')), 'Email de contact');
            setParam('contact_telephone', sanitize(post('contact_telephone')), 'Téléphone de contact');
            setParam('adresse', sanitize(post('adresse')), 'Adresse');
            $success = true;
        }

        if ($section === 'logo') {
            // Réinitialiser au logo par défaut
            if (post('reset_logo')) {
                $old = trim((string)getParam('logo_path', ''));
                if ($old !== '' && strpos($old, 'uploads/') === 0) {
                    @unlink(PUBLIC_PATH . $old);
                }
                setParam('logo_path', '', 'Logo personnalisé (chemin relatif à /public)');
                $success = true;
            } elseif (!empty($_FILES['logo']['name'])) {
                $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
                $allowed = ['png', 'jpg', 'jpeg', 'webp', 'svg'];

                if ($_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = "Échec du téléversement du logo.";
                } elseif (!in_array($ext, $allowed, true)) {
                    $errors[] = 'Format de logo non supporté (PNG, JPG, WEBP ou SVG).';
                } elseif ($_FILES['logo']['size'] > 2 * 1024 * 1024) {
                    $errors[] = 'Le logo est trop volumineux (max 2 Mo).';
                } else {
                    // Vérifier que les formats bitmap sont de vraies images
                    if ($ext !== 'svg' && getimagesize($_FILES['logo']['tmp_name']) === false) {
                        $errors[] = 'Le fichier fourni n\'est pas une image valide.';
                    } else {
                        $uploadDir = PUBLIC_PATH . 'uploads/';
                        if (!is_dir($uploadDir)) {
                            @mkdir($uploadDir, 0755, true);
                        }
                        $filename = 'logo_' . date('YmdHis') . '_' . substr(uniqid(), -5) . '.' . $ext;
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $filename)) {
                            // Supprimer l'ancien logo personnalisé
                            $old = trim((string)getParam('logo_path', ''));
                            if ($old !== '' && strpos($old, 'uploads/') === 0) {
                                @unlink(PUBLIC_PATH . $old);
                            }
                            setParam('logo_path', 'uploads/' . $filename, 'Logo personnalisé (chemin relatif à /public)');
                            $success = true;
                        } else {
                            $errors[] = "Impossible d'enregistrer le logo.";
                        }
                    }
                }
            } else {
                $errors[] = 'Veuillez sélectionner un fichier image.';
            }
        }

        if ($section === 'horaires') {
            setParam('heure_ouverture', sanitize(post('heure_ouverture')), 'Heure d\'ouverture');
            setParam('heure_fermeture', sanitize(post('heure_fermeture')), 'Heure de fermeture');
            setParam('jours_ouverture', sanitize(post('jours_ouverture')), 'Jours d\'ouverture');
            $success = true;
        }

        if ($section === 'reservations') {
            setParam('duree_min_reservation', sanitize(post('duree_min_reservation')), 'Durée minimum de réservation');
            setParam('delai_annulation', sanitize(post('delai_annulation')), 'Délai d\'annulation (heures)');
            setParam('reservation_avance_max', sanitize(post('reservation_avance_max')), 'Réservation max jours à l\'avance');
            setParam('acompte_minimum', sanitize(post('acompte_minimum')), 'Acompte minimum (%)');
            setParam('temps_transition', sanitize(post('temps_transition')), 'Temps de transition entre 2 réservations (minutes)');
            $success = true;
        }

        if ($section === 'fermeture') {
            // Période de fermeture exceptionnelle (ex: Magal) — vide = aucune fermeture
            $fDebut = sanitize(post('fermeture_debut'));
            $fFin   = sanitize(post('fermeture_fin'));
            // Si une seule date est fournie, on neutralise la période (les deux sont requises)
            if ($fDebut === '' || $fFin === '') {
                $fDebut = $fFin = '';
            } elseif ($fFin < $fDebut) {
                [$fDebut, $fFin] = [$fFin, $fDebut];
            }
            setParam('fermeture_debut', $fDebut, 'Début de la période de fermeture (aucune réservation)');
            setParam('fermeture_fin', $fFin, 'Fin de la période de fermeture (aucune réservation)');
            setParam('fermeture_motif', sanitize(post('fermeture_motif')), 'Motif de la fermeture (ex: Magal)');
            $success = true;
        }

        if ($section === 'tarification') {
            // Heure de coupure : avant = tarif matinal, à partir de = tarif normal.
            setParam('heure_pointe_debut', sanitize(post('heure_pointe_debut')), 'Heure de coupure : fin du tarif matinal / début du tarif normal');

            $joursWeekend = post('jours_weekend');
            $joursWeekend = is_array($joursWeekend) ? implode(',', array_map('intval', $joursWeekend)) : '';
            setParam('jours_weekend', $joursWeekend, 'Jours considérés comme week-end (1=Lun, 7=Dim, CSV)');
            $success = true;
        }

        if ($section === 'notifications') {
            setParam('sms_api_key', sanitize(post('sms_api_key')), 'Clé API SMS');
            setParam('sms_sender', sanitize(post('sms_sender')), 'Nom expéditeur SMS');
            setParam('sms_enabled', post('sms_enabled') ? '1' : '0', 'SMS activé');
            setParam('whatsapp_enabled', post('whatsapp_enabled') ? '1' : '0', 'WhatsApp activé');
            setParam('email_enabled', post('email_enabled') ? '1' : '0', 'Email activé');
            $success = true;
        }

        if ($section === 'academie') {
            setParam('cotisation_defaut', sanitize(post('cotisation_defaut')), 'Cotisation mensuelle par défaut');
            setParam('frais_inscription_defaut', sanitize(post('frais_inscription_defaut')), 'Frais d\'inscription par défaut');
            setParam('jour_echeance_cotisation', sanitize(post('jour_echeance_cotisation')), 'Jour échéance cotisation');
            $success = true;
        }

        if ($success) {
            Auth::logAction(Auth::id(), 'update', 'parametres', 0);
            Session::flash('success', 'Paramètres enregistrés avec succès !');
            redirect(url('admin/parametres.php'));
        }
    }
}

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Paramètres du système</h4>
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

<div class="row g-4">
    <!-- Paramètres généraux -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Informations générales</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="general">

                    <div class="mb-3">
                        <label class="form-label">Nom du complexe</label>
                        <input type="text" class="form-control" name="app_name"
                               value="<?= e(getParam('app_name', APP_FULL_NAME)) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Slogan</label>
                        <input type="text" class="form-control" name="app_slogan"
                               value="<?= e(getParam('app_slogan', APP_SLOGAN)) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nom de l'académie</label>
                        <input type="text" class="form-control" name="academie_name"
                               value="<?= e(getParam('academie_name', ACADEMIE_NAME)) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email de contact</label>
                        <input type="email" class="form-control" name="contact_email"
                               value="<?= e(getParam('contact_email', 'contact@csk-kaira.sn')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Téléphone</label>
                        <input type="tel" class="form-control" name="contact_telephone"
                               value="<?= e(getParam('contact_telephone', '')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Adresse</label>
                        <textarea class="form-control" name="adresse" rows="2"><?= e(getParam('adresse', '')) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>

        <!-- Logo -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-image me-2"></i>Logo</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Ce logo est utilisé dans l'espace d'administration <strong>et</strong> sur le site public.
                    Formats : PNG, JPG, WEBP ou SVG (max 2 Mo). Fond transparent (PNG/SVG) recommandé.
                </p>

                <div class="d-flex align-items-center gap-3 mb-3">
                    <img src="<?= getLogoUrl() ?>" alt="Logo actuel"
                         style="width:72px;height:72px;object-fit:contain;background:#f1f3f5;border:1px solid #dee2e6;border-radius:8px;padding:4px;">
                    <div class="small text-muted">
                        Logo actuel<br>
                        <?= trim((string)getParam('logo_path', '')) !== '' ? '<span class="text-success">Personnalisé</span>' : '<span>Par défaut</span>' ?>
                    </div>
                </div>

                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="logo">
                    <div class="mb-3">
                        <label class="form-label">Choisir un nouveau logo</label>
                        <input type="file" class="form-control" name="logo" accept=".png,.jpg,.jpeg,.webp,.svg,image/*" required>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload me-2"></i>Mettre à jour le logo
                    </button>
                    <?php if (trim((string)getParam('logo_path', '')) !== ''): ?>
                    <button type="submit" name="reset_logo" value="1" class="btn btn-outline-secondary" formnovalidate
                            onclick="return confirm('Rétablir le logo par défaut ?');">
                        <i class="fas fa-undo me-2"></i>Logo par défaut
                    </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <!-- Horaires -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Horaires d'ouverture</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="horaires">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Heure d'ouverture</label>
                            <input type="time" class="form-control" name="heure_ouverture"
                                   value="<?= e(getParam('heure_ouverture', '08:00')) ?>">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Heure de fermeture</label>
                            <input type="time" class="form-control" name="heure_fermeture"
                                   value="<?= e(getParam('heure_fermeture', '23:00')) ?>">
                        </div>
                    </div>
                    <div class="mb-3 mt-3">
                        <label class="form-label">Jours d'ouverture</label>
                        <input type="text" class="form-control" name="jours_ouverture"
                               value="<?= e(getParam('jours_ouverture', 'Tous les jours')) ?>"
                               placeholder="Ex: Lundi - Dimanche">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Réservations -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-calendar-check me-2"></i>Réservations</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="reservations">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Durée minimum (heures)</label>
                            <input type="number" class="form-control" name="duree_min_reservation"
                                   value="<?= e(getParam('duree_min_reservation', '1')) ?>" min="0.5" step="0.5">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Délai annulation (heures)</label>
                            <input type="number" class="form-control" name="delai_annulation"
                                   value="<?= e(getParam('delai_annulation', '24')) ?>" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Réservation max (jours à l'avance)</label>
                            <input type="number" class="form-control" name="reservation_avance_max"
                                   value="<?= e(getParam('reservation_avance_max', '30')) ?>" min="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Acompte minimum (%)</label>
                            <input type="number" class="form-control" name="acompte_minimum"
                                   value="<?= e(getParam('acompte_minimum', '50')) ?>" min="0" max="100">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Temps de transition (minutes)</label>
                            <input type="number" class="form-control" name="temps_transition"
                                   value="<?= e(getParam('temps_transition', '10')) ?>" min="0" max="60" step="5">
                            <small class="text-muted">Délai entre deux réservations consécutives</small>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Fermeture exceptionnelle (ex: Magal) -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-calendar-times me-2"></i>Fermeture exceptionnelle</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    Bloque <strong>toutes les réservations</strong> (site public et back-office) sur la plage de dates indiquée.
                    Utile pour un événement comme le <em>Magal</em>, des travaux ou des congés.
                    Laissez les deux dates <strong>vides</strong> pour désactiver la fermeture.
                </p>

                <?php $periodeFermeture = getPeriodeFermeture(); ?>
                <?php if ($periodeFermeture): ?>
                    <div class="alert alert-warning py-2">
                        <i class="fas fa-ban me-2"></i>
                        <strong>Fermeture active :</strong> <?= e(messageFermeture()) ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success py-2">
                        <i class="fas fa-check-circle me-2"></i>Aucune fermeture programmée — les réservations sont ouvertes.
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="fermeture">

                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Du</label>
                            <input type="date" class="form-control" name="fermeture_debut"
                                   value="<?= e(getParam('fermeture_debut', '')) ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Au</label>
                            <input type="date" class="form-control" name="fermeture_fin"
                                   value="<?= e(getParam('fermeture_fin', '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Motif (affiché aux clients)</label>
                            <input type="text" class="form-control" name="fermeture_motif"
                                   value="<?= e(getParam('fermeture_motif', '')) ?>"
                                   placeholder="Ex: Magal de Touba">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Tarification heures de pointe / week-end -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-tags me-2"></i>Tarification — Matinal / Normal & week-end</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small">
                    <strong>Avant l'heure de coupure</strong> : le tarif <em>« Tarif matinal »</em> (réduit) s'applique.
                    <strong>À partir de l'heure de coupure</strong> : le tarif <em>« Tarif normal »</em> (prix par défaut) s'applique.
                    Les jours <strong>week-end</strong> appliquent le tarif <em>« Tarif week-end »</em>, prioritaire.
                </p>
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="tarification">

                    <?php $joursLabels = [1=>'Lun',2=>'Mar',3=>'Mer',4=>'Jeu',5=>'Ven',6=>'Sam',7=>'Dim']; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Heure de coupure (fin du matinal / début du normal)</label>
                            <input type="time" class="form-control" name="heure_pointe_debut"
                                   value="<?= e(getParam('heure_pointe_debut', '16:00')) ?>">
                            <small class="text-muted">Avant cette heure = tarif matinal ; à partir de cette heure = tarif normal.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Jours considérés comme week-end</label>
                            <?php
                            $joursWeekendDefault = '6,7'; // Sam-Dim
                            $joursWeekend = explode(',', getParam('jours_weekend', $joursWeekendDefault));
                            ?>
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($joursLabels as $num => $lbl): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="jours_weekend[]"
                                           id="jwe_<?= $num ?>" value="<?= $num ?>"
                                           <?= in_array((string)$num, $joursWeekend) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="jwe_<?= $num ?>"><?= $lbl ?></label>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Académie -->
    <div class="col-xl-6">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-graduation-cap me-2"></i>Académie</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="academie">

                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label">Cotisation mensuelle (FCFA)</label>
                            <input type="number" class="form-control" name="cotisation_defaut"
                                   value="<?= e(getParam('cotisation_defaut', '15000')) ?>" min="0" step="500">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Frais d'inscription (FCFA)</label>
                            <input type="number" class="form-control" name="frais_inscription_defaut"
                                   value="<?= e(getParam('frais_inscription_defaut', '25000')) ?>" min="0" step="500">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Jour échéance cotisation</label>
                            <select class="form-select" name="jour_echeance_cotisation">
                                <?php for ($d = 1; $d <= 28; $d++): ?>
                                    <option value="<?= $d ?>" <?= getParam('jour_echeance_cotisation', '10') == $d ? 'selected' : '' ?>>
                                        <?= $d ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Notifications -->
    <div class="col-xl-12">
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0"><i class="fas fa-bell me-2"></i>Notifications</h6>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="section" value="notifications">

                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="sms_enabled"
                                           name="sms_enabled" <?= getParam('sms_enabled', '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="sms_enabled">
                                        <i class="fas fa-sms me-2"></i>Activer SMS
                                    </label>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label small">Clé API SMS (Orange/Twilio)</label>
                                    <input type="text" class="form-control form-control-sm" name="sms_api_key"
                                           value="<?= e(getParam('sms_api_key', '')) ?>" placeholder="Votre clé API">
                                </div>
                                <div class="mb-0">
                                    <label class="form-label small">Nom expéditeur</label>
                                    <input type="text" class="form-control form-control-sm" name="sms_sender"
                                           value="<?= e(getParam('sms_sender', 'CSK')) ?>" maxlength="11">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="whatsapp_enabled"
                                           name="whatsapp_enabled" <?= getParam('whatsapp_enabled', '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="whatsapp_enabled">
                                        <i class="fab fa-whatsapp me-2"></i>Activer WhatsApp
                                    </label>
                                </div>
                                <p class="text-muted small mb-0">
                                    Intégration WhatsApp Business API pour les notifications automatiques.
                                </p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3">
                                <div class="form-check form-switch mb-3">
                                    <input class="form-check-input" type="checkbox" id="email_enabled"
                                           name="email_enabled" <?= getParam('email_enabled', '0') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="email_enabled">
                                        <i class="fas fa-envelope me-2"></i>Activer Email
                                    </label>
                                </div>
                                <p class="text-muted small mb-0">
                                    Envoi d'emails de confirmation et rappels.
                                </p>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-3">
                        <i class="fas fa-save me-2"></i>Enregistrer
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
