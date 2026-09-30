<?php
/**
 * Supprimer une dépense
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Depense.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('depenses/index'));
}

$id = (int)post('id');

if ($id > 0) {
    try {
        if (Depense::delete($id)) {
            Session::flash('success', 'Dépense supprimée avec succès.');
        } else {
            Session::flash('danger', 'Impossible de supprimer cette dépense.');
        }
    } catch (Exception $e) {
        Session::flash('danger', 'Erreur: ' . $e->getMessage());
    }
}

redirect(url('depenses/index'));
