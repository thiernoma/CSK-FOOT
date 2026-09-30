<?php
/**
 * Enregistrement d'une séance
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

$recurrent = (int)post('recurrent') === 1;

try {
    if ($recurrent) {
        // Séances récurrentes
        $jours = post('jours') ?? [];
        if (empty($jours)) {
            throw new Exception('Sélectionnez au moins un jour.');
        }

        $data = [
            'heure_debut' => post('rec_heure_debut'),
            'heure_fin' => post('rec_heure_fin'),
            'categorie' => post('categorie'),
            'type_seance' => post('type_seance') ?? 'entrainement',
            'terrain_id' => post('terrain_id') ?: null,
            'entraineur_id' => post('entraineur_id') ?: null,
            'description' => sanitize(post('description'))
        ];

        $count = Seance::generateRecurring(
            $data,
            array_map('intval', $jours),
            post('rec_date_debut'),
            post('rec_date_fin')
        );

        Auth::logAction(Auth::id(), 'create', 'seances', 0, "Création de $count séances récurrentes");
        Session::flash('success', "$count séance(s) créée(s) avec succès !");

    } else {
        // Séance unique
        $data = [
            'date_seance' => post('date_seance'),
            'heure_debut' => post('heure_debut'),
            'heure_fin' => post('heure_fin'),
            'categorie' => post('categorie'),
            'type_seance' => post('type_seance') ?? 'entrainement',
            'terrain_id' => post('terrain_id') ?: null,
            'entraineur_id' => post('entraineur_id') ?: null,
            'description' => sanitize(post('description'))
        ];

        // Validation
        if (empty($data['date_seance'])) {
            throw new Exception('La date est obligatoire.');
        }
        if (empty($data['heure_debut']) || empty($data['heure_fin'])) {
            throw new Exception('Les horaires sont obligatoires.');
        }
        if ($data['heure_fin'] <= $data['heure_debut']) {
            throw new Exception('L\'heure de fin doit être après l\'heure de début.');
        }

        $id = Seance::create($data);
        Auth::logAction(Auth::id(), 'create', 'seances', $id);
        Session::flash('success', 'Séance créée avec succès !');
    }

} catch (Exception $e) {
    Session::flash('danger', $e->getMessage());
}

redirect(url('seances/index.php'));
