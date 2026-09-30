<?php
/**
 * Configuration principale - Complexe Sportif Kaira
 * Académie Khaïra Foot (AKF)
 * "Discipline, Travail, Réussite"
 */

// Mode développement (mettre à false en production)
define('DEV_MODE', true);

// Configuration des erreurs pour les APIs (JSON)
// Désactiver l'affichage pour éviter de corrompre les réponses JSON
if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false) {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
} elseif (DEV_MODE) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ERROR | E_WARNING | E_PARSE);
}

// Configuration de la base de données
define('DB_HOST', 'localhost');
define('DB_NAME', 'hayu2268_cskfoot');
define('DB_USER', 'hayu2268_cskfoot');
define('DB_PASS', '***');
define('DB_CHARSET', 'utf8mb4');

// Configuration de l'application
define('APP_NAME', 'CSK Foot');
define('APP_FULL_NAME', 'CSK Foot');
define('ACADEMIE_NAME', 'CSK Foot');
define('APP_SLOGAN', 'Discipline, Travail, Réussite');
define('APP_VERSION', '1.0.0');

// Détection automatique de l'environnement (local ou production)
$isLocalhost = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', '127.0.0.1'])
               || strpos($_SERVER['HTTP_HOST'] ?? '', 'localhost') !== false;

if ($isLocalhost) {
    // Environnement local
    define('APP_URL', 'http://localhost/CSK');
    define('SITE_URL', 'http://localhost/CSK');
    define('ADMIN_URL', 'http://localhost/CSK/gestion');
} else {
    // Environnement production (cksfoot.com)
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $domain = $_SERVER['HTTP_HOST'] ?? 'cksfoot.com';
    define('APP_URL', $protocol . '://' . $domain);
    define('SITE_URL', $protocol . '://' . $domain);
    define('ADMIN_URL', $protocol . '://' . $domain . '/gestion');
}

// Informations de contact
define('CONTACT_ADDRESS', 'Mbacké Baol, Rond-point Héliport, En face station MKA');
define('CONTACT_PHONE_1', '+221 77 698 08 95');
define('CONTACT_PHONE_2', '+221 33 881 76 09');
define('CONTACT_EMAIL', 'contact@ak-foot.com');

// Chemins de l'application
define('ROOT_PATH', dirname(__DIR__) . '/');
define('APP_PATH', ROOT_PATH . 'app/');
define('VIEWS_PATH', ROOT_PATH . 'views/');
define('PUBLIC_PATH', ROOT_PATH . 'public/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('PDF_PATH', ROOT_PATH . 'pdf/');
define('LOGS_PATH', ROOT_PATH . 'logs/');

// Configuration des sessions
define('SESSION_NAME', 'CSK_SESSION');
define('SESSION_LIFETIME', 86400); // 24 heures
define('SESSION_SECURE', false); // Mettre à true avec HTTPS
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes

// Configuration des uploads
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5 Mo
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Configuration monétaire
define('CURRENCY', 'FCFA');
define('CURRENCY_SYMBOL', 'FCFA');
define('DECIMAL_SEPARATOR', ',');
define('THOUSAND_SEPARATOR', ' ');

// Configuration des terrains - Tarifs par défaut
define('DEFAULT_MINI_TERRAIN_PRICE', 20000); // 20 000 FCFA/heure
define('DEFAULT_GRAND_TERRAIN_PRICE', 50000); // 50 000 FCFA/heure

// Numérotation des tickets
define('TICKET_PREFIX', date('Y') . '-');
define('TICKET_LENGTH', 6); // 6 chiffres après le préfixe

// Configuration des catégories d'âge (Académie)
define('CATEGORIES_AGE', [
    'U7' => ['min' => 5, 'max' => 7, 'label' => 'Moins de 7 ans'],
    'U9' => ['min' => 7, 'max' => 9, 'label' => 'Moins de 9 ans'],
    'U11' => ['min' => 9, 'max' => 11, 'label' => 'Moins de 11 ans'],
    'U13' => ['min' => 11, 'max' => 13, 'label' => 'Moins de 13 ans'],
    'U15' => ['min' => 13, 'max' => 15, 'label' => 'Moins de 15 ans'],
    'U17' => ['min' => 15, 'max' => 17, 'label' => 'Moins de 17 ans'],
    'U19' => ['min' => 17, 'max' => 19, 'label' => 'Moins de 19 ans'],
    'Senior' => ['min' => 19, 'max' => 99, 'label' => 'Seniors']
]);

// Modes de paiement
define('MODES_PAIEMENT', [
    'especes' => 'Espèces',
    'wave' => 'Wave',
    'om' => 'Orange Money',
    'carte' => 'Carte bancaire',
    'virement' => 'Virement'
]);

// ============================================
// Configuration des paiements mobiles
// ============================================

// Wave Business API — https://docs.wave.com/business
// 1) Clé API Checkout (format: wave_sn_prod_xxxxxxxx) — auth Bearer
//    Portail Wave Business > Paramètres > API & Webhooks > Clés API
define('WAVE_API_KEY', '***');
// 1b) Secret de signature des requêtes API SORTANTES (format: wave_sn_AKS_xxxxxxxx)
//     Utilisé uniquement si "request signing" est activé sur votre compte Wave (optionnel pour le Checkout).
define('WAVE_API_SECRET', '***');
// 2) Secret de signature des webhooks (format: wave_sn_WHS_xxxxxxxx)
//    Portail Wave Business > Paramètres > API & Webhooks > Webhooks (secret de signature)
define('WAVE_WEBHOOK_SECRET', '***');
// 3) (Optionnel) ID de marchand agrégé — uniquement si vous encaissez pour un sous-marchand
define('WAVE_AGGREGATED_MERCHANT_ID', '');
// URL du webhook à enregistrer sur Wave (adapte-la automatiquement au domaine courant)
define('WAVE_CALLBACK_URL', APP_URL . '/api/payments/wave-callback.php');
define('WAVE_ENABLED', true); // Activé — clés API + webhook renseignées


// Orange Money API
// Documentation: https://developer.orange.com/
define('ORANGE_MONEY_API_KEY', '');
define('ORANGE_MONEY_MERCHANT_KEY', '');
define('ORANGE_MONEY_MERCHANT_NUMBER', '');
define('ORANGE_MONEY_CALLBACK_URL', APP_URL . '/api/payments/om-callback.php');
define('ORANGE_MONEY_ENABLED', false); // Activer quand les clés sont configurées

// Montant minimum pour acompte (30% par défaut)
define('ACOMPTE_MINIMUM_PERCENT', 30);

// Rôles utilisateurs
define('ROLES', [
    'super_admin' => [
        'nom' => 'Super Administrateur',
        'niveau' => 100,
        'permissions' => ['*'] // Accès complet à toutes les fonctionnalités
    ],
    'directeur' => [
        'nom' => 'Directeur',
        'niveau' => 80,
        'permissions' => [
            'rapports',              // Statistiques et rapports financiers
            'validation_paiements', // Validation des paiements
            'academie',             // Gestion complète académie (membres, cotisations, séances)
            'clients',              // Gestion des clients
            'reservations',         // Gestion des réservations
            'terrains',             // Gestion des terrains
            'depenses',             // Gestion des dépenses
            'avis',                 // Modération des avis clients
            'messages',             // Consultation des messages de contact
            'entraineurs'           // Gestion des entraîneurs
        ]
    ],
    'caissier' => [
        'nom' => 'Caissier/Agent',
        'niveau' => 50,
        'permissions' => [
            'reservations',         // Créer et gérer les réservations
            'clients',              // Créer et modifier les clients
            'paiements',            // Enregistrer les paiements
            'caisse',               // Accès à la caisse du jour
            'impression',           // Impression des reçus
            'avis_lecture'          // Consultation des avis (sans modération)
        ]
    ],
    'entraineur' => [
        'nom' => 'Entraîneur',
        'niveau' => 30,
        'permissions' => [
            'planning',             // Consultation du planning
            'seances',              // Gestion des séances d'entraînement
            'presences',            // Gestion des présences
            'joueurs_lecture',      // Consultation des fiches joueurs
            'academie_lecture'      // Consultation membres académie
        ]
    ],
    'consultant' => [
        'nom' => 'Consultant',
        'niveau' => 10,
        'permissions' => [
            'lecture_seule',        // Lecture uniquement (dashboard, listes)
            'rapports_lecture'      // Consultation des rapports
        ]
    ]
]);

// Charte graphique
define('COLORS', [
    'primary' => '#01305E',      // Navy Blue
    'accent' => '#E8631A',        // Orange
    'background' => '#F4F6FA',    // Gris clair
    'success' => '#28A745',       // Vert
    'danger' => '#DC3545',        // Rouge
    'warning' => '#FFC107',       // Jaune
    'info' => '#17A2B8',          // Bleu info
    'dark' => '#01305E',          // Gris foncé
    'light' => '#F8F9FA'          // Blanc cassé
]);

// Horaires d'ouverture par défaut (heure murale "wall-clock")
// Si la fermeture est < ouverture, elle est considérée comme "le lendemain".
define('OPENING_TIME', '08:00');
define('CLOSING_TIME', '02:40'); // 02h40 du matin (lendemain)

// Conserve les anciennes constantes pour compatibilité (heures entières)
define('OPENING_HOUR', (int)substr(OPENING_TIME, 0, 2));  // 8
define('CLOSING_HOUR', (int)substr(CLOSING_TIME, 0, 2));  // 2

// Heures en "operating-time" (toujours croissantes même après minuit)
// Si l'heure de fermeture est strictement antérieure à l'ouverture en wall-clock,
// on lui ajoute 24h pour qu'elle reste supérieure dans la même "journée d'exploitation".
define('OPENING_HOUR_OP', OPENING_HOUR);
define('CLOSING_HOUR_OP', CLOSING_HOUR < OPENING_HOUR ? CLOSING_HOUR + 24 : CLOSING_HOUR);
define('CLOSING_TIME_OP', sprintf('%02d:%s', CLOSING_HOUR_OP, substr(CLOSING_TIME, 3, 2))); // ex: "26:40"

// Fuseau horaire
date_default_timezone_set('Africa/Dakar');

// Configuration des erreurs
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . 'php_errors.log');
}

// Token CSRF
function generateCSRFToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken(string $token): bool {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Fonction de formatage monétaire
function formatMoney($amount): string {
    return number_format($amount, 0, DECIMAL_SEPARATOR, THOUSAND_SEPARATOR) . ' ' . CURRENCY_SYMBOL;
}

// Libellé "Reçu par" : le caissier s'il existe, sinon le fournisseur pour un paiement en ligne
function recuParLabel($recuParNom, $modePaiement = ''): string {
    if (!empty($recuParNom)) {
        return $recuParNom;
    }
    switch ($modePaiement) {
        case 'wave': return 'Wave Business';
        case 'om':   return 'Orange Money';
        default:     return '-';
    }
}
// Fonction de formatage date
function formatDate($date, $format = 'd/m/Y'): string {
    if ($date instanceof DateTime) {
        return $date->format($format);
    }
    return date($format, strtotime($date));
}

// Fonction de formatage heure
// Gère les heures "operating-time" pouvant dépasser 24h (ex: "26:40" → "02:40").
// Si la valeur dépasse 24h, on retire 24h pour afficher le wall-clock.
function formatTime($time): string {
    if ($time === null || $time === '') return '';
    if (preg_match('/^(\d{1,3}):(\d{2})/', (string)$time, $m)) {
        $h = (int)$m[1] % 24;
        return sprintf('%02d:%02d', $h, (int)$m[2]);
    }
    return date('H:i', strtotime($time));
}

// Format heure avec mention "(lendemain)" si l'op-time dépasse 24h
function formatTimeFull($time): string {
    if ($time === null || $time === '') return '';
    if (preg_match('/^(\d{1,3}):(\d{2})/', (string)$time, $m)) {
        $h = (int)$m[1];
        $min = (int)$m[2];
        $wall = sprintf('%02d:%02d', $h % 24, $min);
        return $h >= 24 ? $wall . ' (lendemain)' : $wall;
    }
    return formatTime($time);
}

// Convertit une heure wall-clock en operating-time selon la "journée d'exploitation".
// Ex: "02:40" → "26:40", "22:00" → "22:00".
// Une heure < OPENING_HOUR est considérée comme appartenant au lendemain.
function wallToOp(string $time): string {
    if (!preg_match('/^(\d{1,3}):(\d{2})/', $time, $m)) return $time;
    $h = (int)$m[1];
    $min = (int)$m[2];
    if ($h >= 24) return sprintf('%02d:%02d', $h, $min); // déjà op-time
    if ($h < OPENING_HOUR) $h += 24;
    return sprintf('%02d:%02d', $h, $min);
}

// Convertit une op-time en wall-clock + flag is_next_day
function opToWall(string $time): array {
    if (!preg_match('/^(\d{1,3}):(\d{2})/', $time, $m)) return ['wall' => $time, 'next_day' => false];
    $h = (int)$m[1];
    $min = (int)$m[2];
    return [
        'wall' => sprintf('%02d:%02d', $h % 24, $min),
        'next_day' => $h >= 24
    ];
}

// Op-time HH:MM (ou HH:MM:SS) → minutes depuis 00:00
function opTimeToMinutes(string $time): int {
    if (!preg_match('/^(\d{1,3}):(\d{2})/', $time, $m)) return 0;
    return ((int)$m[1]) * 60 + ((int)$m[2]);
}

// Minutes → "HH:MM" en op-time
function minutesToOpTime(int $minutes): string {
    return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
}

// Durée en heures décimales entre deux op-times (ex: 22:00 → 26:40 = 4.6666h)
function opDurationHours(string $debut, string $fin): float {
    return (opTimeToMinutes($fin) - opTimeToMinutes($debut)) / 60.0;
}

// Combine (date YYYY-MM-DD, op-time HH:MM[:SS]) en ISO datetime valide pour FullCalendar.
// Si l'op-time dépasse 24h, on incrémente la date.
function opDateTimeIso(string $date, string $opTime): string {
    if (!preg_match('/^(\d{1,3}):(\d{2})(?::(\d{2}))?/', $opTime, $m)) {
        return $date . 'T' . $opTime;
    }
    $h = (int)$m[1];
    $min = (int)$m[2];
    $sec = isset($m[3]) ? (int)$m[3] : 0;
    if ($h >= 24) {
        $date = date('Y-m-d', strtotime($date . ' +1 day'));
        $h -= 24;
    }
    return sprintf('%sT%02d:%02d:%02d', $date, $h, $min, $sec);
}

// Message de salutation selon l'heure
function getGreeting(): string {
    $hour = (int)date('H');
    if ($hour >= 5 && $hour < 12) {
        return 'Bonjour';
    } elseif ($hour >= 12 && $hour < 18) {
        return 'Bon après-midi';
    } else {
        return 'Bonsoir';
    }
}

// Génération de numéro de ticket
function generateTicketNumber($lastNumber = 0): string {
    $newNumber = $lastNumber + 1;
    return TICKET_PREFIX . str_pad($newNumber, TICKET_LENGTH, '0', STR_PAD_LEFT);
}

// Sanitization des entrées
function sanitize($data) {
    if (is_array($data)) {
        return array_map('sanitize', $data);
    }
    if ($data === null) {
        return '';
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Validation email
function isValidEmail($email): bool {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validation téléphone sénégalais
function isValidPhone($phone): bool {
    // Format: 77XXXXXXX, 78XXXXXXX, 76XXXXXXX, 70XXXXXXX, 75XXXXXXX
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^(77|78|76|70|75|33)[0-9]{7}$/', $phone);
}

// Génération de mot de passe aléatoire
function generateRandomPassword($length = 12): string {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%';
    return substr(str_shuffle($chars), 0, $length);
}
