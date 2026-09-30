<?php
/**
 * Page 403 - Accès refusé
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';

http_response_code(403);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accès refusé | <?= APP_NAME ?></title>
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">
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
            color: #dc3545;
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
        <div class="error-code">403</div>
        <h1 class="error-title">Accès refusé</h1>
        <p class="error-message">Vous n'avez pas les permissions nécessaires pour accéder à cette ressource.</p>
        <a href="<?= url('dashboard') ?>" class="btn-home">
            <i class="fas fa-home me-2"></i>Retour à l'accueil
        </a>
    </div>
</body>
</html>
