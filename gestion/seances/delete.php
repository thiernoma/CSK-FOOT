<?php
/**
 * Suppression d'une séance
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Seance.php';

Auth::requireLogin();
Auth::requirePermission('academie');

if (!isPost() || !verifyCsrf()) {
    Session::flash('danger', 'Requête invalide.');
    redirect(url('seances/index.php'));
}

$id = (int)post('id');
if (!$id) {
    Session::flash('danger', 'Séance non spécifiée.');
    redirect(url('seances/index.php'));
}

try {
    Seance::delete($id);
    Auth::logAction(Auth::id(), 'delete', 'seances', $id);
    Session::flash('success', 'Séance supprimée.');
} catch (Exception $e) {
    Session::flash('danger', $e->getMessage());
}

redirect(url('seances/index.php'));
