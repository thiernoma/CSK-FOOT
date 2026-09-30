<?php
/**
 * Fonctions utilitaires globales
 * Complexe Sportif Kaira
 */

/**
 * Redirection HTTP
 */
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

/**
 * Redirection vers la page précédente
 */
function back(): void {
    $referer = $_SERVER['HTTP_REFERER'] ?? ADMIN_URL . '/dashboard';
    redirect($referer);
}

/**
 * Générer une URL absolue pour le back-office (sans extension .php)
 */
function url(string $path = ''): string {
    $path = ltrim($path, '/');
    // Supprimer l'extension .php
    $path = preg_replace('/\.php$/', '', $path);
    return ADMIN_URL . '/' . $path;
}

/**
 * Générer une URL absolue pour le site public (sans extension .php)
 */
function siteUrl(string $path = ''): string {
    $path = ltrim($path, '/');
    // Supprimer l'extension .php
    $path = preg_replace('/\.php$/', '', $path);
    return SITE_URL . '/' . $path;
}

/**
 * Générer une URL vers un asset (avec cache-buster basé sur la date de modification)
 */
function asset(string $path): string {
    $clean = ltrim($path, '/');
    $url = APP_URL . '/public/' . $clean;
    $fs = realpath(__DIR__ . '/../public/' . $clean);
    if ($fs && is_file($fs)) {
        $url .= '?v=' . filemtime($fs);
    }
    return $url;
}

/**
 * Générer une URL vers un fichier uploadé
 */
function uploads(string $path): string {
    return APP_URL . '/uploads/' . ltrim($path, '/');
}

/**
 * Échapper pour affichage HTML
 */
function e($value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Dump and die (debug)
 */
function dd(...$vars): void {
    echo '<pre style="background:#1a1a2e;color:#0f0;padding:20px;margin:10px;border-radius:5px;">';
    foreach ($vars as $var) {
        var_dump($var);
        echo "\n---\n";
    }
    echo '</pre>';
    exit;
}

/**
 * Afficher les messages flash
 */
function showFlashMessages(): string {
    $html = '';
    $messages = Session::getFlash();

    foreach ($messages as $type => $msgs) {
        foreach ($msgs as $msg) {
            $icon = match($type) {
                'success' => 'check-circle',
                'danger', 'error' => 'exclamation-circle',
                'warning' => 'exclamation-triangle',
                'info' => 'info-circle',
                default => 'bell'
            };

            $html .= '<div class="alert alert-' . e($type) . ' alert-dismissible fade show" role="alert">';
            $html .= '<i class="fas fa-' . $icon . ' me-2"></i>';
            $html .= e($msg);
            $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
            $html .= '</div>';
        }
    }

    return $html;
}

/**
 * Générer un token CSRF dans un champ caché
 */
function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . generateCSRFToken() . '">';
}

/**
 * Vérifier le token CSRF d'une requête POST
 */
function verifyCsrf(): bool {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }

    $token = $_POST['csrf_token'] ?? '';
    if (!verifyCSRFToken($token)) {
        Session::flash('danger', 'Token de sécurité invalide. Veuillez réessayer.');
        return false;
    }

    return true;
}

/**
 * Obtenir une valeur POST avec valeur par défaut
 */
function post(string $key, $default = null) {
    return $_POST[$key] ?? $default;
}

/**
 * Obtenir une valeur GET avec valeur par défaut
 */
function get(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

/**
 * Vérifier si la requête est POST
 */
function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * Vérifier si la requête est AJAX
 */
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Réponse JSON
 */
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Formater un numéro de téléphone sénégalais
 */
function formatPhone(string $phone): string {
    $phone = preg_replace('/[^0-9]/', '', $phone);

    if (strlen($phone) === 9) {
        return substr($phone, 0, 2) . ' ' . substr($phone, 2, 3) . ' ' . substr($phone, 5, 2) . ' ' . substr($phone, 7, 2);
    }

    return $phone;
}

/**
 * Obtenir l'initiale d'un nom pour avatar
 */
function getInitials(string $nom, string $prenom = ''): string {
    $initials = strtoupper(substr($nom, 0, 1));
    if ($prenom) {
        $initials = strtoupper(substr($prenom, 0, 1)) . $initials;
    }
    return $initials;
}

/**
 * Formater une durée en heures
 */
function formatDuration(float $hours): string {
    if ($hours == 1) {
        return '1 heure';
    } elseif ($hours == intval($hours)) {
        return intval($hours) . ' heures';
    } else {
        return str_replace('.', 'h', $hours);
    }
}

/**
 * Calculer le temps relatif (il y a X minutes)
 */
function timeAgo(string $datetime): string {
    $time = strtotime($datetime);
    $diff = time() - $time;

    if ($diff < 60) {
        return 'À l\'instant';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return 'Il y a ' . $mins . ' min';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return 'Il y a ' . $hours . ' h';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return 'Il y a ' . $days . ' jour' . ($days > 1 ? 's' : '');
    } else {
        return formatDate($datetime, 'd/m/Y');
    }
}

/**
 * Libellé affiché pour un type de terrain (valeur de terrains.type)
 */
function terrainTypeLabel(?string $type): string {
    $labels = [
        'grand'     => 'Grand',
        'mini'      => 'Mini',
        'petit'     => 'Petits camps (5v5)',
        'petit_6v6' => 'Petits camps (6v6)',
    ];
    return $labels[$type ?? ''] ?? ucfirst((string) $type);
}

/**
 * Générer une classe de badge selon le statut
 */
function statusBadgeClass(string $status): string {
    return match($status) {
        'actif', 'confirmee', 'paye', 'present', 'terminee' => 'bg-success',
        'en_attente', 'planifiee' => 'bg-warning text-dark',
        'partiel', 'en_cours' => 'bg-info',
        'inactif', 'annulee', 'absent', 'no_show' => 'bg-danger',
        'suspendu', 'bloque', 'maintenance' => 'bg-secondary',
        default => 'bg-primary'
    };
}

/**
 * Traduire un statut en français
 */
function translateStatus(string $status): string {
    return match($status) {
        'actif' => 'Actif',
        'inactif' => 'Inactif',
        'suspendu' => 'Suspendu',
        'bloque' => 'Bloqué',
        'confirmee' => 'Confirmée',
        'en_cours' => 'En cours',
        'terminee' => 'Terminée',
        'annulee' => 'Annulée',
        'no_show' => 'Non présenté',
        'paye' => 'Payé',
        'en_attente' => 'En attente',
        'partiel' => 'Partiel',
        'rembourse' => 'Remboursé',
        'planifiee' => 'Planifiée',
        'maintenance' => 'Maintenance',
        'ferme' => 'Fermé',
        'exonere' => 'Exonéré',
        default => ucfirst($status)
    };
}

/**
 * Obtenir le nom du mois en français
 */
function getMonthName(int $month): string {
    $months = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
    ];
    return $months[$month] ?? '';
}

/**
 * Obtenir le nom du jour en français
 */
function getDayName(string $date): string {
    $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    return $days[(int)date('w', strtotime($date))];
}

/**
 * Formater une date complète en français
 */
function formatDateFr(string $date): string {
    $timestamp = strtotime($date);
    $day = getDayName($date);
    $dayNum = date('j', $timestamp);
    $month = getMonthName((int)date('n', $timestamp));
    $year = date('Y', $timestamp);

    return "{$day} {$dayNum} {$month} {$year}";
}

/**
 * Pagination
 */
function paginate(int $total, int $perPage, int $currentPage): array {
    $totalPages = max(1, ceil($total / $perPage));
    $currentPage = max(1, min($currentPage, $totalPages));
    $offset = ($currentPage - 1) * $perPage;

    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_previous' => $currentPage > 1,
        'has_next' => $currentPage < $totalPages,
        'previous_page' => $currentPage - 1,
        'next_page' => $currentPage + 1
    ];
}

/**
 * Générer le HTML de pagination Bootstrap
 */
function paginationHtml(array $pagination, string $baseUrl): string {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }

    $html = '<nav><ul class="pagination justify-content-center">';

    // Bouton précédent
    $html .= '<li class="page-item' . ($pagination['has_previous'] ? '' : ' disabled') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['previous_page'] . '">';
    $html .= '<i class="fas fa-chevron-left"></i></a></li>';

    // Pages
    $start = max(1, $pagination['current_page'] - 2);
    $end = min($pagination['total_pages'], $pagination['current_page'] + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $pagination['current_page'] ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $pagination['total_pages']) {
        if ($end < $pagination['total_pages'] - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '?page=' . $pagination['total_pages'] . '">' . $pagination['total_pages'] . '</a></li>';
    }

    // Bouton suivant
    $html .= '<li class="page-item' . ($pagination['has_next'] ? '' : ' disabled') . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . '?page=' . $pagination['next_page'] . '">';
    $html .= '<i class="fas fa-chevron-right"></i></a></li>';

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Générer le HTML de pagination Bootstrap (version simplifiée)
 */
function pagination(int $currentPage, int $totalPages, string $baseUrl): string {
    if ($totalPages <= 1) {
        return '';
    }

    // Déterminer le séparateur pour les paramètres URL
    $separator = (strpos($baseUrl, '?') !== false) ? '&' : '?';

    $html = '<nav><ul class="pagination pagination-sm justify-content-center mb-0">';

    // Bouton précédent
    $prevDisabled = $currentPage <= 1 ? ' disabled' : '';
    $prevPage = max(1, $currentPage - 1);
    $html .= '<li class="page-item' . $prevDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $prevPage . '">';
    $html .= '<i class="fas fa-chevron-left"></i></a></li>';

    // Pages
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);

    if ($start > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=1">1</a></li>';
        if ($start > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }

    for ($i = $start; $i <= $end; $i++) {
        $active = $i === $currentPage ? ' active' : '';
        $html .= '<li class="page-item' . $active . '">';
        $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $i . '">' . $i . '</a></li>';
    }

    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . $separator . 'page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }

    // Bouton suivant
    $nextDisabled = $currentPage >= $totalPages ? ' disabled' : '';
    $nextPage = min($totalPages, $currentPage + 1);
    $html .= '<li class="page-item' . $nextDisabled . '">';
    $html .= '<a class="page-link" href="' . $baseUrl . $separator . 'page=' . $nextPage . '">';
    $html .= '<i class="fas fa-chevron-right"></i></a></li>';

    $html .= '</ul></nav>';

    return $html;
}

/**
 * Obtenir les créneaux horaires disponibles en op-time.
 *
 * Si appelé avec deux entiers (ancienne signature, ex: 8, 23) : renvoie HH:00 / HH:30.
 *
 * Si appelé sans paramètre, utilise OPENING_TIME / CLOSING_TIME_OP (ex: "08:00" → "26:40")
 * et inclut un dernier slot pour la fermeture exacte (ex: "26:40").
 *
 * @param int|string|null $start Hour entier (8) ou string "HH:MM" (op-time)
 * @param int|string|null $end   Hour entier (23) ou string "HH:MM" (op-time)
 * @param int $stepMinutes Pas en minutes (défaut 30)
 * @return string[] Liste de créneaux en op-time "HH:MM"
 */
function getTimeSlots($start = null, $end = null, int $stepMinutes = 30): array {
    if ($start === null) $start = defined('OPENING_TIME') ? OPENING_TIME : '08:00';
    if ($end === null)   $end   = defined('CLOSING_TIME_OP') ? CLOSING_TIME_OP : '23:00';

    // Compatibilité avec ancienne signature (entiers)
    if (is_int($start)) $start = sprintf('%02d:00', $start);
    if (is_int($end))   $end   = sprintf('%02d:00', $end);

    $startMin = opTimeToMinutes($start);
    $endMin   = opTimeToMinutes($end);
    if ($endMin <= $startMin) return [];

    $slots = [];
    for ($m = $startMin; $m < $endMin; $m += $stepMinutes) {
        $slots[] = minutesToOpTime($m);
    }
    // Toujours inclure exactement la fin (ex: 26:40 même si pas multiple du pas)
    if (empty($slots) || end($slots) !== minutesToOpTime($endMin)) {
        $slots[] = minutesToOpTime($endMin);
    }
    return $slots;
}

/**
 * Valider un fichier uploadé
 */
function validateUpload(array $file, array $allowedTypes = [], int $maxSize = 0): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée.',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale du formulaire.',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement téléchargé.',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été téléchargé.',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant.',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture sur le disque.',
        ];
        return ['valid' => false, 'message' => $errors[$file['error']] ?? 'Erreur inconnue.'];
    }

    if ($maxSize && $file['size'] > $maxSize) {
        return ['valid' => false, 'message' => 'Le fichier est trop volumineux.'];
    }

    if ($allowedTypes && !in_array($file['type'], $allowedTypes)) {
        return ['valid' => false, 'message' => 'Type de fichier non autorisé.'];
    }

    return ['valid' => true];
}

/**
 * Uploader un fichier
 */
function uploadFile(array $file, string $destination, string $prefix = ''): array {
    $validation = validateUpload($file, ALLOWED_IMAGE_TYPES, MAX_UPLOAD_SIZE);
    if (!$validation['valid']) {
        return $validation;
    }

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $prefix . '_' . uniqid() . '.' . $ext;
    $path = UPLOADS_PATH . $destination . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $path)) {
        return ['valid' => false, 'message' => 'Erreur lors de l\'enregistrement du fichier.'];
    }

    return ['valid' => true, 'filename' => $filename, 'path' => $path];
}

/**
 * Générer l'URL d'un QR code via Google Charts API
 */
function generateQRCodeUrl(string $data, int $size = 150): string {
    $encodedData = urlencode($data);
    return "https://chart.googleapis.com/chart?cht=qr&chs={$size}x{$size}&chl={$encodedData}&choe=UTF-8";
}

/**
 * Générer un token unique pour vérification de réservation
 */
function generateVerificationToken(): string {
    return bin2hex(random_bytes(16));
}

/**
 * Générer l'URL de vérification d'une réservation
 */
function getReservationVerificationUrl(string $token): string {
    return SITE_URL . '/site/verification?token=' . $token;
}

/**
 * Récupérer un paramètre de configuration depuis la base de données
 */
function getParam(string $cle, $default = '') {
    $param = Database::fetchOne("SELECT valeur FROM parametres WHERE cle = :cle", ['cle' => $cle]);
    return $param ? $param['valeur'] : $default;
}

/**
 * URL du logo (personnalisé depuis les Paramètres, sinon logo par défaut).
 * Utilisé dans l'admin et le site public.
 */
function getLogoUrl(): string {
    $rel = trim((string)getParam('logo_path', ''));
    if ($rel !== '') {
        $fs = PUBLIC_PATH . ltrim($rel, '/');
        if (is_file($fs)) {
            return asset($rel);
        }
    }
    return asset('images/logo-akf.png');
}

/**
 * Chemin filesystem du logo (pour PDF, e-mails, etc.).
 */
function getLogoFsPath(): string {
    $rel = trim((string)getParam('logo_path', ''));
    if ($rel !== '') {
        $fs = PUBLIC_PATH . ltrim($rel, '/');
        if (is_file($fs)) {
            return $fs;
        }
    }
    return ROOT_PATH . 'public/images/logo-akf.png';
}

/**
 * Période de fermeture exceptionnelle (ex: Magal, travaux, congés).
 * Aucune réservation n'est possible sur cette plage de dates.
 *
 * @return array|null ['debut' => 'Y-m-d', 'fin' => 'Y-m-d', 'motif' => string] ou null si non configurée
 */
function getPeriodeFermeture(): ?array {
    $debut = trim((string)getParam('fermeture_debut', ''));
    $fin   = trim((string)getParam('fermeture_fin', ''));

    if ($debut === '' || $fin === '' || !strtotime($debut) || !strtotime($fin)) {
        return null;
    }
    if ($fin < $debut) {
        [$debut, $fin] = [$fin, $debut];
    }

    return [
        'debut' => $debut,
        'fin'   => $fin,
        'motif' => trim((string)getParam('fermeture_motif', '')),
    ];
}

/**
 * La date (format Y-m-d) tombe-t-elle dans la période de fermeture ?
 */
function isDateFermee(string $date): bool {
    $p = getPeriodeFermeture();
    if (!$p || $date === '') {
        return false;
    }
    return ($date >= $p['debut'] && $date <= $p['fin']);
}

/**
 * Message d'information sur la fermeture (chaîne vide si aucune période configurée).
 */
function messageFermeture(): string {
    $p = getPeriodeFermeture();
    if (!$p) {
        return '';
    }
    $motif = $p['motif'] !== '' ? ' — ' . $p['motif'] : '';
    return 'Réservations indisponibles du ' . formatDate($p['debut']) . ' au ' . formatDate($p['fin']) . $motif;
}

/**
 * Définir un paramètre de configuration dans la base de données
 */
function setParam(string $cle, $valeur, string $description = ''): void {
    $existing = Database::fetchOne("SELECT cle FROM parametres WHERE cle = :cle", ['cle' => $cle]);
    if ($existing) {
        Database::update('parametres', ['valeur' => $valeur], 'cle = :cle', ['cle' => $cle]);
    } else {
        Database::insert('parametres', [
            'cle' => $cle,
            'valeur' => $valeur,
            'description' => $description
        ]);
    }
}
