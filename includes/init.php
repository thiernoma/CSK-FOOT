<?php
/**
 * Fichier d'initialisation global
 * À inclure en haut de chaque page
 * Complexe Sportif Kaira
 */

// Charger la configuration
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Charger les classes et helpers
require_once __DIR__ . '/Session.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/helpers.php';

// Démarrer la session
Session::start();

// Variable globale pour le titre de la page
$pageTitle = APP_NAME;
$pageDescription = '';

// Obtenir l'URL de base pour les assets
$baseUrl = APP_URL;
