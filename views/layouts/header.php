<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= e($pageDescription ?? 'Système de gestion du Complexe Sportif Kaira') ?>">
    <meta name="base-url" content="<?= APP_URL ?>">
    <title><?= e($pageTitle ?? 'CSK Gestion') ?> | <?= APP_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="<?= asset('images/favicon.ico') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= asset('images/favicon.png') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome 6 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">

    <?php if (isset($extraCss)): ?>
        <?php foreach ($extraCss as $css): ?>
            <link href="<?= asset($css) ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
<body>
    <div class="app-wrapper">
        <!-- Sidebar -->
        <?php include __DIR__ . '/sidebar.php'; ?>

        <!-- Contenu principal -->
        <div class="main-content" id="mainContent">
            <!-- Header -->
            <header class="main-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggle" title="Basculer le menu">
                        <i class="fas fa-bars"></i>
                    </button>

                    <nav class="breadcrumb-wrapper" aria-label="breadcrumb">
                        <ol class="breadcrumb mb-0">
                            <li class="breadcrumb-item">
                                <a href="<?= url('dashboard.php') ?>">
                                    <i class="fas fa-home"></i>
                                </a>
                            </li>
                            <?php if (isset($breadcrumb)): ?>
                                <?php foreach ($breadcrumb as $item): ?>
                                    <?php if (isset($item['url'])): ?>
                                        <li class="breadcrumb-item">
                                            <a href="<?= $item['url'] ?>"><?= e($item['label']) ?></a>
                                        </li>
                                    <?php else: ?>
                                        <li class="breadcrumb-item active"><?= e($item['label']) ?></li>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </ol>
                    </nav>

                    <div class="global-search d-none d-lg-block">
                        <i class="fas fa-search"></i>
                        <input type="text" id="globalSearch" placeholder="Rechercher..." autocomplete="off">
                    </div>
                </div>

                <div class="header-right">
                    <!-- Bouton nouvelle réservation rapide -->
                    <a href="<?= url('reservations/nouveau.php') ?>" class="btn btn-accent btn-sm d-none d-md-flex" title="Nouvelle réservation">
                        <i class="fas fa-plus"></i>
                        <span>Réservation</span>
                    </a>

                    <!-- Notifications -->
                    <div class="dropdown" id="notificationsDropdown">
                        <button class="header-icon-btn" data-bs-toggle="dropdown" title="Notifications" id="notifBtn">
                            <i class="fas fa-bell"></i>
                            <span class="badge" id="notifCount" style="display:none;">0</span>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                            <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Notifications</h6>
                                <a href="#" class="text-muted small" onclick="markAllNotificationsRead(event)">Tout marquer lu</a>
                            </div>
                            <div class="notifications-list" id="notificationsList" style="max-height: 350px; overflow-y: auto;">
                                <div class="text-center py-4 text-muted">
                                    <i class="fas fa-spinner fa-spin"></i> Chargement...
                                </div>
                            </div>
                            <div class="p-2 text-center border-top">
                                <a href="<?= url('notifications/index') ?>">Voir toutes les notifications</a>
                            </div>
                        </div>
                    </div>

                    <!-- Profil utilisateur -->
                    <div class="dropdown">
                        <div class="user-dropdown" data-bs-toggle="dropdown">
                            <div class="user-avatar">
                                <?= getInitials(Session::get('user_nom', ''), Session::get('user_prenom', '')) ?>
                            </div>
                            <div class="user-info d-none d-md-block">
                                <div class="user-name"><?= e(Session::getUserFullName()) ?></div>
                                <div class="user-role"><?= e(ucfirst(Session::getUserRole())) ?></div>
                            </div>
                            <i class="fas fa-chevron-down ms-2 d-none d-md-block text-muted"></i>
                        </div>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="<?= url('profil.php') ?>">
                                    <i class="fas fa-user me-2"></i> Mon profil
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <a class="dropdown-item text-danger" href="<?= url('logout.php') ?>">
                                    <i class="fas fa-sign-out-alt me-2"></i> Déconnexion
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </header>

            <!-- Zone de contenu -->
            <main class="content-wrapper">
                <!-- Messages flash -->
                <?= showFlashMessages() ?>
