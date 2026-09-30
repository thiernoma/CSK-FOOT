<?php
/**
 * Configuration des notifications
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'services/NotificationService.php';

Auth::requireLogin();
Auth::requireAdmin();

$pageTitle = 'Configuration des notifications';
$breadcrumb = [
    ['label' => 'Administration'],
    ['label' => 'Notifications']
];

// Les fonctions getParam() et setParam() sont définies dans helpers.php

$errors = [];
$success = '';
$testResult = null;

// Traitement du formulaire
if (isPost() && verifyCsrf()) {
    $action = post('action');

    if ($action === 'save') {
        // Sauvegarder la configuration
        $params = [
            // SMS
            'sms_enabled' => post('sms_enabled') ? '1' : '0',
            'sms_provider' => post('sms_provider'),
            'sms_api_key' => post('sms_api_key'),
            'sms_api_secret' => post('sms_api_secret'),
            'sms_sender_id' => post('sms_sender_id'),

            // WhatsApp
            'whatsapp_enabled' => post('whatsapp_enabled') ? '1' : '0',
            'whatsapp_provider' => post('whatsapp_provider'),
            'whatsapp_api_key' => post('whatsapp_api_key'),
            'whatsapp_phone' => post('whatsapp_phone'),

            // Email
            'email_enabled' => post('email_enabled') ? '1' : '0',
            'smtp_host' => post('smtp_host'),
            'smtp_port' => post('smtp_port'),
            'smtp_user' => post('smtp_user'),
            'smtp_pass' => post('smtp_pass'),
            'email_from' => post('email_from'),
            'email_from_name' => post('email_from_name'),

            // Général
            'notification_test_mode' => post('notification_test_mode') ? '1' : '0'
        ];

        foreach ($params as $key => $value) {
            setParam($key, $value);
        }

        Auth::logAction(Auth::id(), 'update', 'parametres', 0, 'Configuration notifications');
        $success = 'Configuration enregistrée avec succès !';

    } elseif ($action === 'test') {
        // Tester l'envoi
        $channel = post('test_channel');
        $recipient = post('test_recipient');
        $message = "Ceci est un message de test du " . APP_NAME . " envoyé le " . date('d/m/Y à H:i');

        try {
            $notif = NotificationService::getInstance();

            switch ($channel) {
                case 'sms':
                    $testResult = $notif->sendSMS($recipient, $message);
                    break;
                case 'whatsapp':
                    $testResult = $notif->sendWhatsApp($recipient, $message);
                    break;
                case 'email':
                    $testResult = $notif->sendEmail($recipient, 'Test ' . APP_NAME, $message);
                    break;
            }
        } catch (Exception $e) {
            $testResult = ['success' => false, 'error' => $e->getMessage()];
        }
    }
}

// Récupérer les logs récents
$recentLogs = Database::fetchAll(
    "SELECT * FROM notifications_log ORDER BY created_at DESC LIMIT 20"
) ?? [];

include VIEWS_PATH . 'layouts/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1">Configuration des notifications</h4>
        <p class="text-muted mb-0">SMS, WhatsApp et Email</p>
    </div>
    <a href="<?= url('admin/parametres.php') ?>" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-2"></i>Retour
    </a>
</div>

<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle me-2"></i><?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($testResult): ?>
    <div class="alert alert-<?= $testResult['success'] ? 'success' : 'danger' ?> alert-dismissible fade show">
        <i class="fas fa-<?= $testResult['success'] ? 'check' : 'times' ?>-circle me-2"></i>
        <?php if ($testResult['success']): ?>
            Message envoyé avec succès !
        <?php else: ?>
            Erreur: <?= e($testResult['error'] ?? 'Erreur inconnue') ?>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<form method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="save">

    <div class="row">
        <div class="col-lg-8">
            <!-- Configuration SMS -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-sms me-2"></i>Configuration SMS</h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="sms_enabled" id="sms_enabled"
                               <?= getParam('sms_enabled') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="sms_enabled">Activer</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Provider</label>
                            <select class="form-select" name="sms_provider">
                                <option value="orange" <?= getParam('sms_provider') === 'orange' ? 'selected' : '' ?>>
                                    Orange Sénégal
                                </option>
                                <option value="twilio" <?= getParam('sms_provider') === 'twilio' ? 'selected' : '' ?>>
                                    Twilio
                                </option>
                                <option value="infobip" <?= getParam('sms_provider') === 'infobip' ? 'selected' : '' ?>>
                                    Infobip
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Sender ID</label>
                            <input type="text" class="form-control" name="sms_sender_id"
                                   value="<?= e(getParam('sms_sender_id', 'CSK')) ?>" maxlength="11">
                            <small class="text-muted">Nom qui apparaîtra (max 11 car.)</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Key / Client ID</label>
                            <input type="text" class="form-control" name="sms_api_key"
                                   value="<?= e(getParam('sms_api_key')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">API Secret / Client Secret</label>
                            <input type="password" class="form-control" name="sms_api_secret"
                                   value="<?= e(getParam('sms_api_secret')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration WhatsApp -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fab fa-whatsapp me-2"></i>Configuration WhatsApp</h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="whatsapp_enabled" id="whatsapp_enabled"
                               <?= getParam('whatsapp_enabled') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="whatsapp_enabled">Activer</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Provider</label>
                            <select class="form-select" name="whatsapp_provider">
                                <option value="twilio" <?= getParam('whatsapp_provider') === 'twilio' ? 'selected' : '' ?>>
                                    Twilio
                                </option>
                                <option value="meta" <?= getParam('whatsapp_provider') === 'meta' ? 'selected' : '' ?>>
                                    Meta (Facebook) Business API
                                </option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Numéro WhatsApp Business</label>
                            <input type="text" class="form-control" name="whatsapp_phone"
                                   value="<?= e(getParam('whatsapp_phone')) ?>" placeholder="221XXXXXXXXX">
                        </div>
                        <div class="col-12">
                            <label class="form-label">API Key / Access Token</label>
                            <input type="password" class="form-control" name="whatsapp_api_key"
                                   value="<?= e(getParam('whatsapp_api_key')) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Email -->
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-envelope me-2"></i>Configuration Email (SMTP)</h6>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="email_enabled" id="email_enabled"
                               <?= getParam('email_enabled', true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="email_enabled">Activer</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label">Serveur SMTP</label>
                            <input type="text" class="form-control" name="smtp_host"
                                   value="<?= e(getParam('smtp_host', 'smtp.gmail.com')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Port</label>
                            <input type="number" class="form-control" name="smtp_port"
                                   value="<?= e(getParam('smtp_port', 587)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Utilisateur SMTP</label>
                            <input type="text" class="form-control" name="smtp_user"
                                   value="<?= e(getParam('smtp_user')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Mot de passe SMTP</label>
                            <input type="password" class="form-control" name="smtp_pass"
                                   value="<?= e(getParam('smtp_pass')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email expéditeur</label>
                            <input type="email" class="form-control" name="email_from"
                                   value="<?= e(getParam('email_from', 'noreply@csk.sn')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nom expéditeur</label>
                            <input type="text" class="form-control" name="email_from_name"
                                   value="<?= e(getParam('email_from_name', APP_NAME)) ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Options générales -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Options</h6>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="notification_test_mode" id="notification_test_mode"
                               <?= getParam('notification_test_mode') ? 'checked' : '' ?>>
                        <label class="form-check-label" for="notification_test_mode">
                            <strong>Mode test</strong> - Les messages ne sont pas réellement envoyés
                        </label>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">
                <i class="fas fa-save me-2"></i>Enregistrer la configuration
            </button>
        </div>

        <div class="col-lg-4">
            <!-- Test d'envoi -->
            <div class="card mb-4">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Tester l'envoi</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Canal</label>
                        <select class="form-select" name="test_channel" form="testForm">
                            <option value="sms">SMS</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="email">Email</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Destinataire</label>
                        <input type="text" class="form-control" name="test_recipient" form="testForm"
                               placeholder="Téléphone ou email">
                    </div>
                    <button type="submit" form="testForm" class="btn btn-outline-primary w-100">
                        <i class="fas fa-paper-plane me-2"></i>Envoyer un test
                    </button>
                </div>
            </div>

            <!-- Logs récents -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0"><i class="fas fa-history me-2"></i>Envois récents</h6>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentLogs)): ?>
                        <p class="text-muted text-center py-4">Aucun envoi récent</p>
                    <?php else: ?>
                        <div class="list-group list-group-flush" style="max-height:400px;overflow-y:auto;">
                            <?php foreach ($recentLogs as $log): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <span class="badge bg-<?= $log['type'] === 'sms' ? 'info' : ($log['type'] === 'whatsapp' ? 'success' : 'secondary') ?>">
                                            <?= strtoupper($log['type']) ?>
                                        </span>
                                        <span class="badge bg-<?= $log['statut'] === 'envoye' ? 'success' : 'danger' ?>">
                                            <?= $log['statut'] === 'envoye' ? '✓' : '✗' ?>
                                        </span>
                                    </div>
                                    <small class="text-muted"><?= formatDate($log['created_at'], 'd/m H:i') ?></small>
                                </div>
                                <small class="d-block text-truncate"><?= e($log['destinataire']) ?></small>
                                <?php if (!empty($log['erreur'])): ?>
                                    <small class="text-danger"><?= e($log['erreur']) ?></small>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Form test séparé -->
<form id="testForm" method="POST">
    <?= csrfField() ?>
    <input type="hidden" name="action" value="test">
</form>

<?php include VIEWS_PATH . 'layouts/footer.php'; ?>
