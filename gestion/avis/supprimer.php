<?php
/**
 * Supprimer un avis
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Avis.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('avis/'));
}

$id = (int)post('id');

if ($id > 0) {
    if (Avis::delete($id)) {
        Session::flash('success', 'Avis supprimé.');
    } else {
        Session::flash('danger', 'Erreur lors de la suppression.');
    }
}

redirect(url('avis/'));
