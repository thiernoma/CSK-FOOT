<?php
/**
 * Landing Page - Site public
 * Complexe Sportif Kaira
 */

require_once __DIR__ . '/../includes/init.php';
require_once APP_PATH . 'models/Terrain.php';
require_once APP_PATH . 'models/Avis.php';

// Récupérer les terrains actifs
$terrains = Terrain::getAll('actif');

// Stats pour la landing
$stats = Database::fetchOne(
    "SELECT
        (SELECT COUNT(*) FROM terrains WHERE statut = 'actif') as nb_terrains,
        (SELECT COUNT(*) FROM membres_academie WHERE statut = 'actif') as nb_membres,
        (SELECT COUNT(*) FROM reservations WHERE YEAR(date_reservation) = YEAR(CURDATE())) as nb_reservations,
        (SELECT COUNT(*) FROM clients) as nb_clients"
);

// Récupérer les avis approuvés pour affichage
$avisRecents = Avis::getAll(['statut' => 'approuve', 'limit' => 6]);

// Récupérer les coordonnées GPS du premier terrain (pour la carte globale)
$locationTerrain = null;
foreach ($terrains as $t) {
    if (!empty($t['latitude']) && !empty($t['longitude'])) {
        $locationTerrain = $t;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="<?= APP_FULL_NAME ?> - Réservez votre terrain de football en ligne. <?= APP_SLOGAN ?>">
    <meta name="keywords" content="football, terrain, réservation, Dakar, Sénégal, académie, sport">

    <title><?= APP_FULL_NAME ?> - <?= ACADEMIE_NAME ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/png" href="<?= asset('images/favicon.png') ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- CSS -->
    <link rel="stylesheet" href="<?= asset('css/site.css') ?>">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <a href="#" class="navbar-brand">
                <img src="<?= getLogoUrl() ?>" alt="<?= ACADEMIE_NAME ?>"
                     onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2250%22 height=%2250%22 viewBox=%220 0 50 50%22><circle cx=%2225%22 cy=%2225%22 r=%2223%22 fill=%22%23E8631A%22/><text x=%2225%22 y=%2232%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2214%22 font-weight=%22bold%22>AKF</text></svg>'">
                <span class="brand-text"><?= APP_NAME ?></span>
            </a>

            <ul class="nav-menu" id="navMenu">
                <li><a href="#accueil" class="nav-link">Accueil</a></li>
                <li><a href="#terrains" class="nav-link">Terrains</a></li>
                <li><a href="#academie" class="nav-link">Académie</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <li><a href="<?= siteUrl('site/reserver.php') ?>" class="nav-link nav-cta">Réserver</a></li>
            </ul>

            <button class="mobile-toggle" id="mobileToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="accueil">
        <!-- Football Field Animation Background -->
        <div class="football-field-bg">
            <svg viewBox="0 0 1200 800" preserveAspectRatio="xMidYMid slice" class="field-svg">
                <!-- Field base -->
                <rect x="50" y="50" width="1100" height="700" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>

                <!-- Center circle -->
                <circle cx="600" cy="400" r="100" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3" class="center-circle"/>

                <!-- Center line -->
                <line x1="600" y1="50" x2="600" y2="750" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>

                <!-- Center dot -->
                <circle cx="600" cy="400" r="8" fill="rgba(255,255,255,0.15)" class="center-dot"/>

                <!-- Left penalty area -->
                <rect x="50" y="200" width="180" height="400" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <rect x="50" y="280" width="70" height="240" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <circle cx="170" cy="400" r="8" fill="rgba(255,255,255,0.15)"/>
                <path d="M 230 320 A 80 80 0 0 1 230 480" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>

                <!-- Right penalty area -->
                <rect x="970" y="200" width="180" height="400" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <rect x="1080" y="280" width="70" height="240" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <circle cx="1030" cy="400" r="8" fill="rgba(255,255,255,0.15)"/>
                <path d="M 970 320 A 80 80 0 0 0 970 480" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>

                <!-- Corner arcs -->
                <path d="M 50 70 A 20 20 0 0 0 70 50" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <path d="M 1130 50 A 20 20 0 0 0 1150 70" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <path d="M 50 730 A 20 20 0 0 1 70 750" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>
                <path d="M 1130 750 A 20 20 0 0 1 1150 730" fill="none" stroke="rgba(255,255,255,0.1)" stroke-width="3"/>

                <!-- Animated Logo Ball -->
                <defs>
                    <clipPath id="logoClip">
                        <circle cx="0" cy="0" r="30"/>
                    </clipPath>
                </defs>
                <g class="football-anim">
                    <!-- Cercle de fond avec effet glow -->
                    <circle cx="0" cy="0" r="35" fill="rgba(232,99,26,0.3)">
                        <animateMotion dur="12s" repeatCount="indefinite" path="M 200,300 Q 400,200 600,400 T 1000,350 Q 800,500 600,400 T 200,300"/>
                        <animate attributeName="r" values="35;40;35" dur="2s" repeatCount="indefinite"/>
                    </circle>
                    <!-- Logo image animé -->
                    <image href="<?= getLogoUrl() ?>" x="-30" y="-30" width="60" height="60" clip-path="url(#logoClip)">
                        <animateMotion dur="12s" repeatCount="indefinite" path="M 200,300 Q 400,200 600,400 T 1000,350 Q 800,500 600,400 T 200,300"/>
                        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="4s" repeatCount="indefinite" additive="sum"/>
                    </image>
                    <!-- Cercle de bordure -->
                    <circle cx="0" cy="0" r="32" fill="none" stroke="#E8631A" stroke-width="3">
                        <animateMotion dur="12s" repeatCount="indefinite" path="M 200,300 Q 400,200 600,400 T 1000,350 Q 800,500 600,400 T 200,300"/>
                    </circle>
                </g>

                <!-- Floating particles -->
                <circle cx="150" cy="150" r="3" fill="rgba(232,99,26,0.5)" class="particle p1"/>
                <circle cx="900" cy="200" r="4" fill="rgba(232,99,26,0.4)" class="particle p2"/>
                <circle cx="300" cy="600" r="3" fill="rgba(232,99,26,0.5)" class="particle p3"/>
                <circle cx="1000" cy="550" r="5" fill="rgba(232,99,26,0.3)" class="particle p4"/>
                <circle cx="500" cy="150" r="4" fill="rgba(255,255,255,0.2)" class="particle p5"/>
                <circle cx="750" cy="650" r="3" fill="rgba(255,255,255,0.2)" class="particle p6"/>
            </svg>

        </div>

        <div class="container">
            <div class="hero-content">
                <span class="hero-badge">
                    <i class="fas fa-star me-2"></i> <?= APP_SLOGAN ?>
                </span>
                <h1>Réservez votre <span>terrain</span> en quelques clics</h1>
                <p>Le Complexe Sportif Kaira vous offre des terrains de qualité professionnelle pour vos matchs et entraînements. Réservation simple, rapide et sécurisée.</p>

                <div class="hero-buttons">
                    <a href="<?= siteUrl('site/reserver.php') ?>" class="btn btn-primary btn-lg">
                        <i class="fas fa-calendar-plus"></i>
                        Réserver maintenant
                    </a>
                    <a href="#terrains" class="btn btn-outline btn-lg">
                        <i class="fas fa-play"></i>
                        Découvrir
                    </a>
                </div>

                <div class="hero-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= $stats['nb_terrains'] ?? 3 ?></div>
                        <div class="stat-label">Terrains</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Terrains Section -->
    <section class="terrains-section" id="terrains">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Nos installations</span>
                <h2 class="section-title">Nos Terrains</h2>
                <p class="section-subtitle">Des terrains de qualité pour tous vos matchs et entraînements</p>
            </div>

            <div class="terrains-grid">
                <?php foreach ($terrains as $terrain):
                    // Récupérer les stats d'avis pour ce terrain
                    $terrainStats = Avis::getStatsTerrain($terrain['id']);
                ?>
                <div class="terrain-card" data-terrain-id="<?= $terrain['id'] ?>">
                    <div class="terrain-image">
                        <?php if ($terrain['photo']): ?>
                            <img src="<?= uploads('terrains/' . $terrain['photo']) ?>" alt="<?= e($terrain['nom']) ?>" style="width:100%;height:100%;object-fit:cover;">
                        <?php else: ?>
                            <i class="fas fa-futbol"></i>
                        <?php endif; ?>
                        <span class="terrain-badge">Disponible</span>
                        <?php if (!empty($terrain['video_url'])): ?>
                        <button class="terrain-video-btn" onclick="playVideo('<?= e($terrain['video_url']) ?>', '<?= e($terrain['nom']) ?>')" title="Voir la vidéo">
                            <i class="fas fa-play"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="terrain-content">
                        <div class="terrain-header-row">
                            <div>
                                <h3 class="terrain-name"><?= e($terrain['nom']) ?></h3>
                                <span class="terrain-type"><?= ucfirst($terrain['type']) ?></span>
                            </div>
                            <?php if ($terrainStats['note_moyenne']): ?>
                            <div class="terrain-rating">
                                <span class="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star<?= $i <= round($terrainStats['note_moyenne']) ? '' : '-o' ?>" style="color: <?= $i <= round($terrainStats['note_moyenne']) ? '#ffc107' : '#ddd' ?>;"></i>
                                    <?php endfor; ?>
                                </span>
                                <span class="rating-value"><?= $terrainStats['note_moyenne'] ?></span>
                                <span class="rating-count">(<?= $terrainStats['nb_avis'] ?>)</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="terrain-features">
                            <?php if ($terrain['capacite']): ?>
                            <span><i class="fas fa-users"></i> <?= $terrain['capacite'] ?> joueurs</span>
                            <?php endif; ?>
                            <span><i class="fas fa-lightbulb"></i> Éclairage</span>
                            <span><i class="fas fa-parking"></i> Parking</span>
                            <?php if (!empty($terrain['video_url'])): ?>
                            <span><i class="fas fa-video"></i> Vidéo</span>
                            <?php endif; ?>
                        </div>

                        <div class="terrain-price">
                            <div>
                                <span class="price-value"><?= formatMoney($terrain['prix_heure']) ?></span>
                                <span class="price-label">/ heure</span>
                                <?php
                                $prixPointe  = (float)($terrain['prix_heure_pointe'] ?? 0);
                                $prixWeekend = (float)($terrain['prix_weekend'] ?? 0);
                                $base = (float)$terrain['prix_heure'];
                                $heurePointe = substr(getParam('heure_pointe_debut', '18:00'), 0, 5)
                                             . ' – ' . substr(getParam('heure_pointe_fin', '22:00'), 0, 5);
                                $tarifsSpeciaux = [];
                                if ($prixWeekend > 0 && $prixWeekend != $base) {
                                    $tarifsSpeciaux[] = '<span class="badge bg-warning text-dark" title="Samedi et dimanche">Weekend : ' . formatMoney($prixWeekend) . '</span>';
                                }
                                if ($prixPointe > 0 && $prixPointe != $base) {
                                    $tarifsSpeciaux[] = '<span class="badge bg-info" title="En semaine, de ' . $heurePointe . '">Pointe ' . $heurePointe . ' : ' . formatMoney($prixPointe) . '</span>';
                                }
                                if (!empty($tarifsSpeciaux)):
                                ?>
                                <div class="mt-1 d-flex flex-wrap gap-1" style="font-size: 0.75em;">
                                    <?= implode(' ', $tarifsSpeciaux) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="terrain-actions">
                                <?php if (!empty($terrain['latitude']) && !empty($terrain['longitude'])): ?>
                                <a href="https://www.google.com/maps/dir/?api=1&destination=<?= $terrain['latitude'] ?>,<?= $terrain['longitude'] ?>"
                                   target="_blank"
                                   class="terrain-btn-icon"
                                   title="Itinéraire">
                                    <i class="fas fa-directions"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?= siteUrl('site/reserver.php?terrain=' . $terrain['id']) ?>" class="terrain-btn">
                                    Réserver
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section" id="services">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Pourquoi nous choisir</span>
                <h2 class="section-title">Nos Avantages</h2>
                <p class="section-subtitle">Une expérience sportive complète et professionnelle</p>
            </div>

            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h3 class="feature-title">Réservation Facile</h3>
                    <p class="feature-text">Réservez votre terrain en quelques clics, 24h/24 et 7j/7. Confirmation instantanée par SMS.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title">Paiement Sécurisé</h3>
                    <p class="feature-text">Payez en toute sécurité par Wave, Orange Money ou espèces sur place.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-futbol"></i>
                    </div>
                    <h3 class="feature-title">Équipements Pro</h3>
                    <p class="feature-text">Terrains synthétiques de dernière génération, vestiaires, éclairage LED.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <h3 class="feature-title">Horaires Flexibles</h3>
                    <p class="feature-text">Ouvert de 8h à 23h tous les jours. Créneaux adaptés à vos disponibilités.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Avis Section -->
    <?php if (!empty($avisRecents)): ?>
    <section class="reviews-section" id="avis">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Témoignages</span>
                <h2 class="section-title">Ce que disent nos clients</h2>
                <p class="section-subtitle">Découvrez les avis de notre communauté</p>
            </div>

            <div class="reviews-grid">
                <?php foreach ($avisRecents as $avis): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="review-avatar">
                            <?= strtoupper(substr($avis['client_nom_complet'] ?: $avis['nom_client'] ?: 'A', 0, 1)) ?>
                        </div>
                        <div class="review-info">
                            <h4><?= e($avis['client_nom_complet'] ?: $avis['nom_client'] ?: 'Anonyme') ?></h4>
                            <span class="review-terrain"><i class="fas fa-futbol"></i> <?= e($avis['terrain_nom']) ?></span>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star" style="color: <?= $i <= $avis['note'] ? '#ffc107' : '#ddd' ?>;"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <?php if ($avis['commentaire']): ?>
                    <p class="review-text"><?= e(substr($avis['commentaire'], 0, 200)) ?><?= strlen($avis['commentaire']) > 200 ? '...' : '' ?></p>
                    <?php endif; ?>
                    <?php if ($avis['recommande']): ?>
                    <div class="review-recommend">
                        <i class="fas fa-thumbs-up"></i> Recommande ce terrain
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="reviews-cta">
                <a href="<?= siteUrl('site/avis.php') ?>" class="btn btn-outline">
                    <i class="fas fa-star"></i> Laisser un avis
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>


    <!-- Academy Section -->
    <section class="academy-section" id="academie">
        <div class="container">
            <div class="academy-content">
                <div class="academy-text">
                    <span class="section-badge" style="background:rgba(232,99,26,0.3);border:none;">Notre académie</span>
                    <h2><?= ACADEMIE_NAME ?></h2>
                    <p>Rejoignez notre académie de football et développez votre talent avec nos entraîneurs diplômés. Formation complète pour toutes les catégories d'âge.</p>

                    <ul class="academy-features">
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Entraîneurs certifiés</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Suivi personnalisé de chaque joueur</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Participation aux tournois locaux</span>
                        </li>
                        <li>
                            <i class="fas fa-check-circle"></i>
                            <span>Équipement fourni</span>
                        </li>
                    </ul>

                    <a href="#contact" class="btn btn-primary">
                        <i class="fas fa-user-plus"></i>
                        Inscription Académie
                    </a>
                </div>

                <div class="academy-video">
                    <iframe
                        src="https://www.youtube.com/embed/VLlcCaWAi8I"
                        title="Académie Khaïra Foot"
                        frameborder="0"
                        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                        allowfullscreen>
                    </iframe>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact-section" id="contact">
        <div class="container">
            <div class="section-header">
                <span class="section-badge">Nous contacter</span>
                <h2 class="section-title">Contactez-nous</h2>
                <p class="section-subtitle">Une question ? N'hésitez pas à nous écrire</p>
            </div>

            <div class="contact-grid">
                <div class="contact-info">
                    <h3>Informations</h3>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Adresse</h4>
                            <p><?= CONTACT_ADDRESS ?></p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Téléphone</h4>
                            <p><a href="tel:<?= str_replace(' ', '', CONTACT_PHONE_1) ?>" style="color: inherit; text-decoration: none;"><?= CONTACT_PHONE_1 ?></a><br>
                               <a href="tel:<?= str_replace(' ', '', CONTACT_PHONE_2) ?>" style="color: inherit; text-decoration: none;"><?= CONTACT_PHONE_2 ?></a></p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Email</h4>
                            <p><a href="mailto:<?= CONTACT_EMAIL ?>" style="color: inherit; text-decoration: none;"><?= CONTACT_EMAIL ?></a></p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="contact-text">
                            <h4>Horaires</h4>
                            <p>Tous les jours<br>08h00 - 23h00</p>
                        </div>
                    </div>

                    <div class="social-links">
                        <a href="#" class="social-link" title="Facebook"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link" title="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://wa.me/221776980895" target="_blank" class="social-link" title="WhatsApp"><i class="fab fa-whatsapp"></i></a>
                        <a href="#" class="social-link" title="YouTube"><i class="fab fa-youtube"></i></a>
                    </div>
                </div>

                <?php
                // Générer une question anti-robot simple
                $num1 = rand(2, 9);
                $num2 = rand(2, 9);
                $captchaAnswer = $num1 + $num2;
                $_SESSION['captcha_answer'] = $captchaAnswer;
                $_SESSION['contact_page_load'] = time(); // Pour protection anti-spam
                ?>
                <form class="contact-form" id="contactForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="nom">Nom complet</label>
                            <input type="text" class="form-control" id="nom" name="nom" required>
                        </div>
                        <div class="form-group">
                            <label for="telephone">Téléphone</label>
                            <input type="tel" class="form-control" id="telephone" name="telephone" required placeholder="77 XXX XX XX">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="sujet">Sujet</label>
                        <select class="form-control" id="sujet" name="sujet">
                            <option value="reservation">Réservation terrain</option>
                            <option value="academie">Inscription académie</option>
                            <option value="partenariat">Partenariat</option>
                            <option value="autre">Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="message">Message</label>
                        <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                    </div>

                    <!-- Vérification anti-robot -->
                    <div class="form-group captcha-group">
                        <label for="captcha">
                            <i class="fas fa-robot"></i> Vérification anti-robot
                        </label>
                        <div class="captcha-question">
                            <span class="captcha-text">Combien font <strong><?= $num1 ?> + <?= $num2 ?></strong> ?</span>
                            <input type="number" class="form-control captcha-input" id="captcha" name="captcha" required placeholder="Votre réponse" min="0" max="99">
                        </div>
                        <input type="hidden" id="captchaExpected" value="<?= $captchaAnswer ?>">
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn" style="width:100%;" disabled>
                        <i class="fas fa-paper-plane"></i>
                        Envoyer le message
                    </button>
                    <p class="captcha-hint">
                        <i class="fas fa-info-circle"></i> Répondez à la question pour activer le bouton d'envoi
                    </p>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-grid">
                <div class="footer-brand">
                    <img src="<?= getLogoUrl() ?>" alt="<?= ACADEMIE_NAME ?>"
                         onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2260%22 height=%2260%22 viewBox=%220 0 60 60%22><circle cx=%2230%22 cy=%2230%22 r=%2228%22 fill=%22%23E8631A%22/><text x=%2230%22 y=%2238%22 text-anchor=%22middle%22 fill=%22white%22 font-size=%2218%22 font-weight=%22bold%22>AKF</text></svg>'">
                    <p><?= APP_FULL_NAME ?> - <?= ACADEMIE_NAME ?>. <?= APP_SLOGAN ?></p>
                    <p><strong>Ouvert tous les jours de 8h à 23h</strong></p>
                </div>

                <div>
                    <h4 class="footer-title">Liens rapides</h4>
                    <ul class="footer-links">
                        <li><a href="#accueil">Accueil</a></li>
                        <li><a href="#terrains">Nos terrains</a></li>
                        <li><a href="#academie">Académie</a></li>
                        <li><a href="#contact">Contact</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-title">Services</h4>
                    <ul class="footer-links">
                        <li><a href="<?= siteUrl('site/reserver.php') ?>">Réservation en ligne</a></li>
                        <li><a href="#academie">Formation jeunes</a></li>
                        <li><a href="#">Tournois</a></li>
                        <li><a href="#">Location événement</a></li>
                    </ul>
                </div>

                <div>
                    <h4 class="footer-title">Paiements acceptés</h4>
                    <ul class="footer-links">
                        <li><i class="fas fa-money-bill-wave" style="margin-right:10px;color:var(--accent);"></i> Espèces</li>
                        <li><i class="fas fa-mobile-alt" style="margin-right:10px;color:#FF6B00;"></i> Orange Money</li>
                        <li><i class="fas fa-mobile-alt" style="margin-right:10px;color:#00B2FF;"></i> Wave</li>
                    </ul>
                </div>
            </div>

            <div class="footer-bottom">
                <p>&copy; <?= date('Y') ?> <?= APP_FULL_NAME ?>. Tous droits réservés.</p>
                <p style="font-size: 0.9rem; margin-top: 5px;"><?= CONTACT_ADDRESS ?> | <?= CONTACT_PHONE_1 ?></p>
                <div class="social-links" style="margin:0;">
                    <a href="#" class="social-link" style="width:35px;height:35px;"><i class="fab fa-facebook-f"></i></a>
                    <a href="#" class="social-link" style="width:35px;height:35px;"><i class="fab fa-instagram"></i></a>
                    <a href="https://wa.me/221776980895" target="_blank" class="social-link" style="width:35px;height:35px;"><i class="fab fa-whatsapp"></i></a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Video Modal -->
    <div class="video-modal" id="videoModal">
        <div class="video-modal-content">
            <button class="video-modal-close" onclick="closeVideo()">&times;</button>
            <div class="video-container" id="videoContainer"></div>
        </div>
    </div>

    <!-- Notification Modal -->
    <div class="notification-modal" id="notificationModal">
        <div class="notification-content">
            <div class="notification-icon" id="notificationIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h3 class="notification-title" id="notificationTitle">Succès</h3>
            <p class="notification-message" id="notificationMessage">Votre action a été effectuée avec succès.</p>
            <button class="notification-btn" onclick="closeNotification()">OK</button>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="confirmation-modal" id="confirmationModal">
        <div class="confirmation-content">
            <div class="confirmation-icon">
                <i class="fas fa-question-circle"></i>
            </div>
            <h3 class="confirmation-title" id="confirmationTitle">Confirmation</h3>
            <p class="confirmation-message" id="confirmationMessage">Êtes-vous sûr de vouloir continuer ?</p>
            <div class="confirmation-buttons">
                <button class="confirmation-btn confirmation-btn-cancel" onclick="closeConfirmation(false)">Annuler</button>
                <button class="confirmation-btn confirmation-btn-confirm" id="confirmationConfirmBtn" onclick="closeConfirmation(true)">Confirmer</button>
            </div>
        </div>
    </div>

    <!-- Additional CSS for modals and terrain actions -->
    <style>
        /* Terrain actions with itinerary button */
        .terrain-actions {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .terrain-btn-icon {
            width: 42px;
            height: 42px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #1a472a;
            color: white;
            border-radius: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .terrain-btn-icon:hover {
            background: #E8631A;
            transform: translateY(-2px);
        }

        .terrain-btn-icon i {
            font-size: 18px;
        }

        /* Notification Modal */
        .notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .notification-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .notification-content {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 400px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .notification-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
        }

        .notification-icon.success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }

        .notification-icon.error {
            background: linear-gradient(135deg, #dc3545, #c82333);
            color: white;
        }

        .notification-icon.warning {
            background: linear-gradient(135deg, #ffc107, #ffb300);
            color: #333;
        }

        .notification-icon.info {
            background: linear-gradient(135deg, #17a2b8, #138496);
            color: white;
        }

        .notification-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a472a;
            margin-bottom: 10px;
        }

        .notification-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .notification-btn {
            background: #E8631A;
            color: white;
            border: none;
            padding: 12px 40px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .notification-btn:hover {
            background: #d55a17;
            transform: translateY(-2px);
        }

        /* Confirmation Modal */
        .confirmation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }

        .confirmation-modal.active {
            display: flex;
            animation: fadeIn 0.3s ease;
        }

        .confirmation-content {
            background: white;
            border-radius: 16px;
            padding: 40px;
            text-align: center;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        .confirmation-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #17a2b8, #138496);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 40px;
            color: white;
        }

        .confirmation-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a472a;
            margin-bottom: 10px;
        }

        .confirmation-message {
            color: #666;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .confirmation-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .confirmation-btn {
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
        }

        .confirmation-btn-cancel {
            background: #e0e0e0;
            color: #333;
        }

        .confirmation-btn-cancel:hover {
            background: #d0d0d0;
        }

        .confirmation-btn-confirm {
            background: #E8631A;
            color: white;
        }

        .confirmation-btn-confirm:hover {
            background: #d55a17;
            transform: translateY(-2px);
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Captcha Styles */
        .captcha-group {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
            margin-bottom: 20px;
        }

        .captcha-group label {
            color: #1a472a;
            font-weight: 600;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .captcha-group label i {
            color: #E8631A;
        }

        .captcha-question {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .captcha-text {
            font-size: 18px;
            color: #333;
        }

        .captcha-text strong {
            color: #E8631A;
            font-size: 22px;
        }

        .captcha-input {
            width: 120px !important;
            text-align: center;
            font-size: 18px !important;
            font-weight: 600;
            border: 2px solid #dee2e6 !important;
        }

        .captcha-input:focus {
            border-color: #E8631A !important;
            box-shadow: 0 0 0 3px rgba(232, 99, 26, 0.2) !important;
        }

        .captcha-input.valid {
            border-color: #28a745 !important;
            background-color: #d4edda !important;
        }

        .captcha-input.invalid {
            border-color: #dc3545 !important;
            background-color: #f8d7da !important;
        }

        .captcha-hint {
            text-align: center;
            font-size: 13px;
            color: #6c757d;
            margin-top: 15px;
            margin-bottom: 0;
        }

        .captcha-hint i {
            color: #E8631A;
        }

        #submitBtn:disabled {
            background: #adb5bd;
            cursor: not-allowed;
            transform: none;
        }

        #submitBtn:disabled:hover {
            background: #adb5bd;
            transform: none;
        }

        #submitBtn.enabled {
            animation: pulse 0.5s ease;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        @media (max-width: 576px) {
            .captcha-question {
                flex-direction: column;
                align-items: flex-start;
            }
            .captcha-input {
                width: 100% !important;
            }
        }
    </style>

    <!-- JavaScript -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Navbar scroll effect
            window.addEventListener('scroll', function() {
                var navbar = document.getElementById('navbar');
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });

            // Mobile menu toggle
            document.getElementById('mobileToggle').addEventListener('click', function() {
                document.getElementById('navMenu').classList.toggle('active');
            });

            // Close mobile menu on link click
            var navLinks = document.querySelectorAll('.nav-link');
            for (var i = 0; i < navLinks.length; i++) {
                navLinks[i].addEventListener('click', function() {
                    document.getElementById('navMenu').classList.remove('active');
                });
            }

            // Captcha validation
            var captchaInput = document.getElementById('captcha');
            var submitBtn = document.getElementById('submitBtn');
            var expectedAnswer = parseInt(document.getElementById('captchaExpected').value);

            captchaInput.addEventListener('input', function() {
                var userAnswer = parseInt(this.value);

                if (this.value === '') {
                    this.classList.remove('valid', 'invalid');
                    submitBtn.disabled = true;
                    submitBtn.classList.remove('enabled');
                } else if (userAnswer === expectedAnswer) {
                    this.classList.remove('invalid');
                    this.classList.add('valid');
                    submitBtn.disabled = false;
                    submitBtn.classList.add('enabled');
                } else {
                    this.classList.remove('valid');
                    this.classList.add('invalid');
                    submitBtn.disabled = true;
                    submitBtn.classList.remove('enabled');
                }
            });

            // Contact form submission
            document.getElementById('contactForm').addEventListener('submit', function(e) {
                e.preventDefault();

                // Double vérification du captcha
                var userAnswer = parseInt(captchaInput.value);
                if (userAnswer !== expectedAnswer) {
                    showNotification('error', 'Erreur', 'Veuillez répondre correctement à la question anti-robot.');
                    return;
                }

                // Préparer les données
                var formData = new FormData(this);

                // Désactiver le bouton pendant l'envoi
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Envoi en cours...';

                // Envoyer au backend
                fetch('<?= APP_URL ?>/api/contact.php', {
                    method: 'POST',
                    body: formData
                })
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (data.success) {
                        showNotification('success', 'Message envoyé !', data.message || 'Nous vous répondrons dans les plus brefs délais.');
                        document.getElementById('contactForm').reset();
                        captchaInput.classList.remove('valid');
                        submitBtn.disabled = true;
                        // Recharger la page pour générer un nouveau captcha
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotification('error', 'Erreur', data.message || 'Une erreur est survenue.');
                        submitBtn.disabled = false;
                    }
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le message';
                })
                .catch(function(error) {
                    console.error('Erreur:', error);
                    showNotification('error', 'Erreur', 'Impossible d\'envoyer le message. Veuillez réessayer.');
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Envoyer le message';
                    submitBtn.disabled = false;
                });
            });

            // Close video modal on escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeVideo();
                }
            });

            // Close video modal on background click
            document.getElementById('videoModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeVideo();
                }
            });
        });

        // Video player functions
        function playVideo(url, title) {
            var videoId = extractYouTubeId(url);
            if (videoId) {
                var iframe = '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>';
                document.getElementById('videoContainer').innerHTML = iframe;
                document.getElementById('videoModal').classList.add('active');
                document.body.style.overflow = 'hidden';
            } else {
                // Fallback for other video types
                window.open(url, '_blank');
            }
        }

        function closeVideo() {
            document.getElementById('videoModal').classList.remove('active');
            document.getElementById('videoContainer').innerHTML = '';
            document.body.style.overflow = '';
        }

        function extractYouTubeId(url) {
            var regExp = /^.*(youtu.be\/|v\/|u\/\w\/|embed\/|watch\?v=|\&v=)([^#\&\?]*).*/;
            var match = url.match(regExp);
            return (match && match[2].length == 11) ? match[2] : null;
        }

        // Notification Modal Functions
        function showNotification(type, title, message) {
            var modal = document.getElementById('notificationModal');
            var icon = document.getElementById('notificationIcon');
            var titleEl = document.getElementById('notificationTitle');
            var messageEl = document.getElementById('notificationMessage');

            // Set icon based on type
            icon.className = 'notification-icon ' + type;
            switch(type) {
                case 'success':
                    icon.innerHTML = '<i class="fas fa-check-circle"></i>';
                    break;
                case 'error':
                    icon.innerHTML = '<i class="fas fa-times-circle"></i>';
                    break;
                case 'warning':
                    icon.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
                    break;
                case 'info':
                    icon.innerHTML = '<i class="fas fa-info-circle"></i>';
                    break;
            }

            titleEl.textContent = title;
            messageEl.textContent = message;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNotification() {
            var modal = document.getElementById('notificationModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }

        // Confirmation Modal Functions
        var confirmationCallback = null;

        function showConfirmation(title, message, callback) {
            var modal = document.getElementById('confirmationModal');
            var titleEl = document.getElementById('confirmationTitle');
            var messageEl = document.getElementById('confirmationMessage');

            titleEl.textContent = title;
            messageEl.textContent = message;
            confirmationCallback = callback;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeConfirmation(confirmed) {
            var modal = document.getElementById('confirmationModal');
            modal.classList.remove('active');
            document.body.style.overflow = '';

            if (confirmationCallback) {
                confirmationCallback(confirmed);
                confirmationCallback = null;
            }
        }

        // Close modals on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeNotification();
                closeConfirmation(false);
            }
        });

        // Close modals on background click
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotification();
            }
        });

        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeConfirmation(false);
            }
        });
    </script>
</body>
</html>
