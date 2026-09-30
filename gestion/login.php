<?php
/**
 * Page de connexion
 * Complexe Sportif Kaira - Académie Khaïra Foot
 */

require_once __DIR__ . '/../includes/init.php';

// Rediriger si déjà connecté
if (Auth::check()) {
    redirect(url('dashboard.php'));
}

$error = '';
$email = '';

// Traitement du formulaire
if (isPost()) {
    if (!verifyCsrf()) {
        $error = 'Token de sécurité invalide. Veuillez réessayer.';
    } else {
        $email = sanitize(post('email', ''));
        $password = post('password', '');
        $remember = post('remember') === 'on';

        if (empty($email) || empty($password)) {
            $error = 'Veuillez remplir tous les champs.';
        } else {
            $result = Auth::attempt($email, $password);

            if ($result['success']) {
                // Redirection vers la page demandée ou dashboard
                $redirect = Session::get('redirect_after_login', url('dashboard.php'));
                Session::remove('redirect_after_login');
                redirect($redirect);
            } else {
                $error = $result['message'];
            }
        }
    }
}

// Message de salutation selon l'heure
$greeting = getGreeting();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion | <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= asset('css/login.css') ?>" rel="stylesheet">
</head>
<body>
    <!-- Particules animées -->
    <div class="particles">
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
        <div class="particle"></div>
    </div>

    <div class="login-container">
        <div class="login-card">
            <!-- Header avec logo -->
            <div class="login-header">
                <div class="logo-wrapper">
                    <img src="<?= getLogoUrl() ?>" alt="<?= ACADEMIE_NAME ?>" onerror="this.innerHTML='<span style=\'font-size:32px;font-weight:bold;color:#1A3A6B;\'>AKF</span>';this.style.display='flex';this.style.alignItems='center';this.style.justifyContent='center';">
                </div>
                <h1><?= APP_FULL_NAME ?></h1>
                <div class="slogan"><?= APP_SLOGAN ?></div>
            </div>

            <!-- Message de salutation -->
            <div class="greeting">
                <h2><?= $greeting ?>, <span>bienvenue !</span></h2>
            </div>

            <!-- Message d'erreur -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- Messages flash -->
            <?php if (Session::hasFlash('warning')): ?>
                <?php foreach (Session::getFlash('warning') as $msg): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?= e($msg) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (Session::hasFlash('success')): ?>
                <?php foreach (Session::getFlash('success') as $msg): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?= e($msg) ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Formulaire de connexion -->
            <form method="POST" action="" class="login-form" id="loginForm">
                <?= csrfField() ?>

                <div class="form-group">
                    <label for="email">Adresse email</label>
                    <div class="input-wrapper">
                        <input
                            type="email"
                            id="email"
                            name="email"
                            value="<?= e($email) ?>"
                            placeholder="votre@email.com"
                            required
                            autocomplete="email"
                            autofocus
                        >
                        <i class="fas fa-envelope"></i>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Mot de passe</label>
                    <div class="input-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            placeholder="••••••••"
                            required
                            autocomplete="current-password"
                        >
                        <i class="fas fa-lock"></i>
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <i class="fas fa-eye" id="toggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Se souvenir de moi</span>
                    </label>
                    <a href="<?= url('forgot-password.php') ?>" class="forgot-password">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <span class="btn-text">Se connecter</span>
                    <i class="fas fa-arrow-right"></i>
                    <span class="spinner"></span>
                </button>
            </form>

            <!-- Footer -->
            <div class="login-footer">
                <div class="football-icons">
                    <i class="fas fa-futbol"></i>
                    <i class="fas fa-futbol"></i>
                    <i class="fas fa-futbol"></i>
                </div>
                <p>&copy; <?= date('Y') ?> <?= ACADEMIE_NAME ?>. Tous droits réservés.</p>
            </div>
        </div>
    </div>

    <script>
        // Toggle affichage mot de passe
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Animation du bouton au submit
        document.getElementById('loginForm').addEventListener('submit', function() {
            const btn = document.getElementById('submitBtn');
            btn.classList.add('loading');
            btn.disabled = true;
        });

        // Focus sur le premier champ vide
        document.addEventListener('DOMContentLoaded', function() {
            const email = document.getElementById('email');
            const password = document.getElementById('password');

            if (email.value === '') {
                email.focus();
            } else {
                password.focus();
            }
        });
    </script>
</body>
</html>
