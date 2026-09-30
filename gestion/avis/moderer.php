<?php
/**
 * Modérer un avis
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Avis.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('avis/'));
}

$id = (int)post('id');
$action = post('action');

if ($id > 0) {
    $statut = $action === 'approuver' ? 'approuve' : 'rejete';

    if (Avis::update($id, ['statut' => $statut])) {
        Session::flash('success', 'Avis ' . ($action === 'approuver' ? 'approuvé' : 'rejeté') . '.');
    } else {
        Session::flash('danger', 'Erreur lors de la modération.');
    }
}

redirect(url('avis/'));
