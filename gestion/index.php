<?php
/**
 * Point d'entrée Back-office
 * Complexe Sportif Kaira - Académie Khaïra Foot
 */

require_once __DIR__ . '/../includes/init.php';

// Rediriger vers le dashboard si connecté, sinon vers login
if (Auth::check()) {
    redirect(url('dashboard.php'));
} else {
    redirect(url('login.php'));
}
