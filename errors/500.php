<?php
/**
 * Page 500 - Erreur serveur
 * Complexe Sportif Kaira
 */

http_response_code(500);

// Charger la config si possible
$adminUrl = '/gestion';
if (file_exists(__DIR__ . '/../config/config.php')) {
    @include_once __DIR__ . '/../config/config.php';
    if (defined('ADMIN_URL')) {
        $adminUrl = ADMIN_URL;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur serveur | CSK</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #1A3A6B 0%, #0d1f3c 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        .error-container {
            text-align: center;
            color: white;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            line-height: 1;
            color: #ffc107;
        }
        .error-title {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .error-message {
            color: rgba(255,255,255,0.7);
            margin-bottom: 30px;
        }
        .btn-home {
            background: #E8631A;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            color: white;
            text-decoration: none;
            transition: all 0.3s;
        }
        .btn-home:hover {
            background: #c4520f;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code">500</div>
        <h1 class="error-title">Erreur serveur</h1>
        <p class="error-message">Une erreur inattendue s'est produite. Veuillez réessayer plus tard.</p>
        <a href="<?= $adminUrl ?>/dashboard" class="btn-home">
            <i class="fas fa-home me-2"></i>Retour à l'accueil
        </a>
    </div>
</body>
</html>
