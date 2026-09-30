<?php
/**
 * Sidebar de navigation
 * Complexe Sportif Kaira
 */

// Déterminer la page active
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));
$currentPath = $_SERVER['REQUEST_URI'] ?? '';

function isActive($pages, $checkDir = true): string {
    global $currentPage, $currentDir;
    if (is_array($pages)) {
        foreach ($pages as $page) {
            if ($currentPage === $page) {
                return 'active';
            }
            if ($checkDir && $currentDir === $page) {
                return 'active';
            }
        }
    } else {
        if ($currentPage === $pages) {
            return 'active';
        }
        if ($checkDir && $currentDir === $pages) {
            return 'active';
        }
    }
    return '';
}

// Fonction pour vérifier si on est dans un répertoire spécifique
function isInDir($dir): bool {
    global $currentDir;
    return $currentDir === $dir;
}
?>

<aside class="sidebar" id="sidebar">
    <!-- Logo -->
    <div class="sidebar-logo">
        <a href="<?= url('dashboard.php') ?>">
            <img src="<?= getLogoUrl() ?>" alt="<?= ACADEMIE_NAME ?>" onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2280%22 height=%2280%22 viewBox=%220 0 80 80%22><circle cx=%2240%22 cy=%2240%22 r=%2238%22 fill=%22%231A3A6B%22/><text x=%2240%22 y=%2248%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2224%22 font-weight=%22bold%22>AKF</text></svg>'">
        </a>
        <h4><?= APP_NAME ?></h4>
        <div class="slogan"><?= APP_SLOGAN ?></div>
    </div>

    <!-- Menu principal -->
    <nav class="sidebar-menu">
        <!-- Dashboard -->
        <a href="<?= url('dashboard.php') ?>" class="menu-item <?= isActive('dashboard') ?>" title="Tableau de bord">
            <i class="fas fa-th-large"></i>
            <span>Tableau de bord</span>
        </a>

        <!-- Section Réservations -->
        <div class="menu-label">Réservations</div>
        
        <a href="<?= url('reservations/index.php') ?>" class="menu-item <?= isInDir('reservations') && $currentPage === 'index' ? 'active' : '' ?>" title="Liste des réservations">
            <i class="fas fa-calendar-alt"></i>
            <span>Réservations</span>
        </a>
        
        <!-- <a href="<?= url('reservations/nouveau.php') ?>" class="menu-item <?= isInDir('reservations') && $currentPage === 'nouveau' ? 'active' : '' ?>" title="Nouvelle réservation">
            <i class="fas fa-plus-circle"></i>
            <span>Nouvelle réservation</span>
        </a>  -->

        <a href="<?= url('reservations/planning.php') ?>" class="menu-item <?= isInDir('reservations') && $currentPage === 'planning' ? 'active' : '' ?>" title="Planning">
            <i class="fas fa-calendar-week"></i>
            <span>Planning</span>
        </a>

        <!-- Section Gestion -->
        <div class="menu-label">Gestion</div>

        <a href="<?= url('terrains/index.php') ?>" class="menu-item <?= isInDir('terrains') ? 'active' : '' ?>" title="Terrains">
            <i class="fas fa-futbol"></i>
            <span>Terrains</span>
        </a>

        <a href="<?= url('clients/index.php') ?>" class="menu-item <?= isInDir('clients') ? 'active' : '' ?>" title="Clients">
            <i class="fas fa-users"></i>
            <span>Clients</span>
        </a>

        <a href="<?= url('paiements/index.php') ?>" class="menu-item <?= isInDir('paiements') && !in_array($currentPage, ['caisse', 'ma-caisse', 'nouveau', 'categories']) ? 'active' : '' ?>" title="Paiements">
            <i class="fas fa-money-bill-wave"></i>
            <span>Paiements</span>
        </a>

        <a href="<?= url('paiements/ma-caisse.php') ?>" class="menu-item <?= $currentPage === 'ma-caisse' ? 'active' : '' ?>" title="Ma caisse">
            <i class="fas fa-cash-register"></i>
            <span>Ma caisse</span>
        </a>

        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('paiements/caisse.php') ?>" class="menu-item <?= $currentPage === 'caisse' ? 'active' : '' ?>" title="Vue d'ensemble caisse">
            <i class="fas fa-th-large"></i>
            <span>Caisse globale</span>
        </a>
        <?php endif; ?>

        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('paiements/categories.php') ?>" class="menu-item <?= isInDir('paiements') && $currentPage === 'categories' ? 'active' : '' ?>" title="Catégories d'encaissement">
            <i class="fas fa-tags"></i>
            <span>Catégories encaiss.</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('depenses/index.php') ?>" class="menu-item <?= isInDir('depenses') && $currentPage !== 'categories' ? 'active' : '' ?>" title="Dépenses">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Dépenses</span>
        </a>

        <?php if (Auth::isAdmin()): ?>
        <a href="<?= url('depenses/categories.php') ?>" class="menu-item <?= isInDir('depenses') && $currentPage === 'categories' ? 'active' : '' ?>" title="Catégories de dépenses">
            <i class="fas fa-tags"></i>
            <span>Catégories dépenses</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('avis/index.php') ?>" class="menu-item <?= isActive('avis') ?>" title="Avis clients">
            <i class="fas fa-star"></i>
            <span>Avis clients</span>
        </a>

        <!-- Section Académie -->
        <?php if (Auth::can('academie') || Auth::can('academie_lecture') || Auth::can('*')): ?>
        <div class="menu-label">Académie</div>

        <?php if (Auth::can('academie') || Auth::can('*')): ?>
        <a href="<?= url('academie/index.php') ?>" class="menu-item <?= isInDir('academie') && $currentPage === 'index' ? 'active' : '' ?>" title="Membres">
            <i class="fas fa-user-graduate"></i>
            <span>Membres</span>
        </a>

        <a href="<?= url('academie/cotisations.php') ?>" class="menu-item <?= isInDir('academie') && $currentPage === 'cotisations' ? 'active' : '' ?>" title="Cotisations">
            <i class="fas fa-hand-holding-usd"></i>
            <span>Cotisations</span>
        </a>
        <?php endif; ?>

        <a href="<?= url('academie/mes-joueurs.php') ?>" class="menu-item <?= isInDir('academie') && $currentPage === 'mes-joueurs' ? 'active' : '' ?>" title="Mes Joueurs">
            <i class="fas fa-user-friends"></i>
            <span>Mes Joueurs</span>
        </a>

        <a href="<?= url('seances/index.php') ?>" class="menu-item <?= isActive('seances') ?>" title="Séances">
            <i class="fas fa-running"></i>
            <span>Séances</span>
        </a>

        <?php if (Auth::can('academie') || Auth::can('*')): ?>
        <a href="<?= url('entraineurs/index.php') ?>" class="menu-item <?= isActive('entraineurs') ?>" title="Entraîneurs">
            <i class="fas fa-user-tie"></i>
            <span>Entraîneurs</span>
        </a>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Section Rapports -->
        <?php if (Auth::can('rapports') || Auth::can('*')): ?>
        <div class="menu-label">Rapports</div>

        <?php /* Page "Rapports & Statistiques" masquée du menu (page toujours accessible par URL directe).
                 Pour la réafficher, décommenter le bloc ci-dessous.
        <a href="<?= url('rapports/index.php') ?>" class="menu-item <?= ($currentDir === 'rapports' && $currentPage === 'index') ? 'active' : '' ?>" title="Rapports">
            <i class="fas fa-chart-bar"></i>
            <span>Statistiques</span>
        </a>
        */ ?>

        <a href="<?= url('rapports/financier.php') ?>" class="menu-item <?= ($currentDir === 'rapports' && $currentPage === 'financier') ? 'active' : '' ?>" title="Rapport financier">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Rapport financier</span>
        </a>
        <?php endif; ?>

        <!-- Section Administration -->
        <?php if (Auth::isAdmin()): ?>
        <div class="menu-label">Administration</div>

        <a href="<?= url('admin/utilisateurs.php') ?>" class="menu-item <?= isActive('utilisateurs') ?>" title="Utilisateurs">
            <i class="fas fa-user-cog"></i>
            <span>Utilisateurs</span>
        </a>

        <?php
        // Compter les messages non lus
        $unreadMessages = 0;
        try {
            if (!class_exists('MessageContact')) {
                require_once APP_PATH . 'models/MessageContact.php';
            }
            $unreadMessages = MessageContact::countUnread();
        } catch (Exception $e) {
            // Table pas encore créée, ignorer
        }
        ?>
        <a href="<?= url('admin/messages.php') ?>" class="menu-item <?= isActive('messages') ?>" title="Messages">
            <i class="fas fa-envelope"></i>
            <span>Messages</span>
            <?php if ($unreadMessages > 0): ?>
            <span class="badge bg-danger ms-auto"><?= $unreadMessages ?></span>
            <?php endif; ?>
        </a>

        <a href="<?= url('admin/parametres.php') ?>" class="menu-item <?= isActive('parametres') ?>" title="Paramètres">
            <i class="fas fa-sliders-h"></i>
            <span>Paramètres</span>
        </a>

        <a href="<?= url('admin/logs.php') ?>" class="menu-item <?= isActive('logs') ?>" title="Logs d'audit">
            <i class="fas fa-history"></i>
            <span>Logs d'audit</span>
        </a>

        <!-- <a href="<?= url('admin/notifications.php') ?>" class="menu-item <?= isActive('notifications') ?>" title="Notifications">
            <i class="fas fa-bell"></i>
            <span>Notifications</span>
        </a> -->
        <?php endif; ?>
    </nav>
</aside>
