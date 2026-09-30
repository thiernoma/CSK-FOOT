<?php
/**
 * Répondre à un avis
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../../includes/init.php';
require_once APP_PATH . 'models/Avis.php';

Auth::requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(url('avis/'));
}

$id = (int)post('id');
$reponse = sanitize(post('reponse'));

if ($id > 0 && $reponse) {
    if (Avis::update($id, ['reponse_admin' => $reponse])) {
        Session::flash('success', 'Réponse enregistrée avec succès.');
    } else {
        Session::flash('danger', 'Erreur lors de l\'enregistrement de la réponse.');
    }
}

redirect(url('avis/'));
