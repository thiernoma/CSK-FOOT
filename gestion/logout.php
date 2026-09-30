<?php
/**
 * Déconnexion
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';

// Déconnecter l'utilisateur
Auth::logout();

// Message de confirmation
Session::flash('success', 'Vous avez été déconnecté avec succès.');

// Rediriger vers la page de connexion
redirect(url('https://csk-foot.com/gestion'));
