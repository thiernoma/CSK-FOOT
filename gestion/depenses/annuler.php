<?php
/**
 * Annuler une dépense (changer statut à "annulee")
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
        // Vérifier que la dépense existe
        $depense = Depense::getById($id);

        if (!$depense) {
            Session::flash('danger', 'Dépense introuvable.');
        } elseif ($depense['statut'] === 'annulee') {
            Session::flash('warning', 'Cette dépense est déjà annulée.');
        } else {
            // Changer le statut à "annulee"
            if (Depense::updateStatut($id, 'annulee')) {
                Session::flash('success', 'Dépense annulée avec succès.');
            } else {
                Session::flash('danger', 'Impossible d\'annuler cette dépense.');
            }
        }
    } catch (Exception $e) {
        Session::flash('danger', 'Erreur: ' . $e->getMessage());
    }
} else {
    Session::flash('danger', 'Dépense non spécifiée.');
}

redirect(url('depenses/index'));
