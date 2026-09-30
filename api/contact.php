<?php
/**
 * API Contact - Formulaire de contact public
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';

// Headers pour API JSON
header('Content-Type: application/json; charset=utf-8');

// Seulement POST accepté
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Méthode non autorisée']);
    exit;
}

// Récupérer les données du formulaire
$nom = trim($_POST['nom'] ?? '');
$telephone = trim($_POST['telephone'] ?? '');
$email = trim($_POST['email'] ?? '');
$sujet = trim($_POST['sujet'] ?? '');
$message = trim($_POST['message'] ?? '');
$captcha = isset($_POST['captcha']) ? (int)$_POST['captcha'] : null;

// Validation des champs obligatoires
$errors = [];

if (empty($nom)) {
    $errors[] = 'Le nom est obligatoire';
}

if (empty($telephone)) {
    $errors[] = 'Le téléphone est obligatoire';
}

if (empty($message)) {
    $errors[] = 'Le message est obligatoire';
}

// Validation du captcha côté serveur
if ($captcha === null) {
    $errors[] = 'Veuillez répondre à la question anti-robot';
} else {
    // Vérifier la réponse du captcha (stockée en session)
    $expectedAnswer = $_SESSION['captcha_answer'] ?? null;
    if ($expectedAnswer === null || $captcha !== $expectedAnswer) {
        $errors[] = 'Réponse anti-robot incorrecte';
    }
}

// Protection anti-spam supplémentaire
// Vérifier qu'il y a eu au moins 3 secondes depuis le chargement de la page
$lastPageLoad = $_SESSION['contact_page_load'] ?? 0;
if (time() - $lastPageLoad < 3) {
    $errors[] = 'Veuillez patienter avant d\'envoyer le formulaire';
}

// Validation email si fourni
if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'L\'adresse email n\'est pas valide';
}

// Validation téléphone (format sénégalais)
$telClean = preg_replace('/[^0-9]/', '', $telephone);
if (!empty($telephone) && !preg_match('/^(77|78|76|70|75|33)[0-9]{7}$/', $telClean)) {
    $errors[] = 'Le numéro de téléphone n\'est pas valide';
}

// Protection contre les injections HTML/Script dans le message
$message = strip_tags($message);
$nom = strip_tags($nom);

// Retourner les erreurs si présentes
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => implode('. ', $errors)
    ]);
    exit;
}

// Traduire le sujet
$sujets = [
    'reservation' => 'Réservation terrain',
    'academie' => 'Inscription académie',
    'partenariat' => 'Partenariat',
    'autre' => 'Autre'
];
$sujetLabel = $sujets[$sujet] ?? 'Autre';

// Enregistrer le message dans la base de données
try {
    $messageId = Database::insert('messages_contact', [
        'nom' => $nom,
        'telephone' => $telephone,
        'email' => $email ?: null,
        'sujet' => $sujet,
        'message' => $message,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        'statut' => 'nouveau'
    ]);

    // Invalider le captcha utilisé (générer un nouveau à la prochaine visite)
    unset($_SESSION['captcha_answer']);

    // Créer une notification pour les admins (optionnel)
    try {
        // Notifier tous les super_admin et directeur
        $admins = Database::fetchAll(
            "SELECT id FROM users WHERE role IN ('super_admin', 'directeur') AND statut = 'actif'"
        );

        foreach ($admins as $admin) {
            Database::insert('notifications', [
                'utilisateur_id' => $admin['id'],
                'type' => 'info',
                'titre' => 'Nouveau message de contact',
                'message' => "Message de {$nom} - Sujet: {$sujetLabel}",
                'lien' => '/gestion/admin/messages.php?id=' . $messageId,
                'icone' => 'envelope'
            ]);
        }
    } catch (Exception $e) {
        // Ne pas bloquer si les notifications échouent
        error_log('Erreur notification contact: ' . $e->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Votre message a été envoyé avec succès. Nous vous répondrons dans les plus brefs délais.'
    ]);

} catch (Exception $e) {
    error_log('Erreur enregistrement message contact: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Une erreur est survenue lors de l\'envoi du message. Veuillez réessayer.'
    ]);
}
