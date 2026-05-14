<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restaurant de L'Estuaire - Livraison de repas a Bafoussam</title>
    <meta name="description" content="Commandez vos plats preferes et faites-vous livrer rapidement a Bafoussam. Application mobile de livraison de repas.">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color: #1F2937; }
        a { text-decoration: none; color: inherit; }

        /* Navbar */
        .navbar {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            display: flex; justify-content: space-between; align-items: center;
            padding: 16px 40px;
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #f0f0f0;
        }
        .navbar-brand {
            display: flex; align-items: center; gap: 10px;
            font-size: 22px; font-weight: 800; color: #FF6B35;
        }
        .navbar-brand span { color: #1F2937; }
        .navbar-links { display: flex; gap: 32px; align-items: center; }
        .navbar-links a { font-size: 15px; font-weight: 600; color: #6B7280; transition: color 0.2s; }
        .navbar-links a:hover { color: #FF6B35; }
        .btn-login {
            padding: 10px 24px; border-radius: 12px;
            background: #FF6B35; color: #fff !important;
            font-weight: 700; font-size: 14px;
            transition: background 0.2s;
        }
        .btn-login:hover { background: #e5602e; }

        /* Mobile menu */
        .menu-toggle { display: none; background: none; border: none; font-size: 28px; cursor: pointer; color: #1F2937; }

        /* Hero */
        .hero {
            padding: 140px 40px 80px;
            background: linear-gradient(135deg, #FFF7ED 0%, #FEF3C7 50%, #FFF7ED 100%);
            text-align: center;
        }
        .hero-badge {
            display: inline-block;
            padding: 6px 16px; border-radius: 20px;
            background: #FF6B35; color: #fff;
            font-size: 13px; font-weight: 700;
            margin-bottom: 24px;
        }
        .hero h1 {
            font-size: 52px; font-weight: 900; line-height: 1.1;
            color: #1F2937; max-width: 700px; margin: 0 auto 20px;
        }
        .hero h1 .highlight { color: #FF6B35; }
        .hero p {
            font-size: 18px; color: #6B7280; max-width: 550px;
            margin: 0 auto 40px; line-height: 1.7;
        }
        .hero-buttons { display: flex; gap: 16px; justify-content: center; flex-wrap: wrap; }
        .btn-primary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 16px 32px; border-radius: 16px;
            background: #1F2937; color: #fff;
            font-size: 16px; font-weight: 700;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 14px rgba(0,0,0,0.15);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,0.2); }
        .btn-secondary {
            display: inline-flex; align-items: center; gap: 10px;
            padding: 16px 32px; border-radius: 16px;
            background: #fff; color: #1F2937;
            font-size: 16px; font-weight: 700;
            border: 2px solid #E5E7EB;
            transition: transform 0.2s, border-color 0.2s;
        }
        .btn-secondary:hover { transform: translateY(-2px); border-color: #FF6B35; }
        .store-icon { width: 24px; height: 24px; }

        /* Features */
        .features { padding: 100px 40px; background: #fff; }
        .features-header { text-align: center; margin-bottom: 60px; }
        .features-header h2 { font-size: 36px; font-weight: 800; margin-bottom: 12px; }
        .features-header p { font-size: 16px; color: #6B7280; }
        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px; max-width: 1100px; margin: 0 auto;
        }
        .feature-card {
            padding: 32px; border-radius: 20px;
            background: #F9FAFB; border: 1px solid #F3F4F6;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .feature-card:hover { transform: translateY(-4px); box-shadow: 0 12px 30px rgba(0,0,0,0.08); }
        .feature-icon {
            width: 56px; height: 56px; border-radius: 16px;
            display: flex; align-items: center; justify-content: center;
            font-size: 28px; margin-bottom: 20px;
        }
        .feature-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 8px; }
        .feature-card p { font-size: 14px; color: #6B7280; line-height: 1.6; }

        /* How it works */
        .how-it-works { padding: 100px 40px; background: #F9FAFB; }
        .how-it-works h2 { text-align: center; font-size: 36px; font-weight: 800; margin-bottom: 60px; }
        .steps {
            display: flex; justify-content: center; gap: 40px;
            max-width: 900px; margin: 0 auto; flex-wrap: wrap;
        }
        .step {
            text-align: center; flex: 1; min-width: 200px;
            position: relative;
        }
        .step-number {
            width: 64px; height: 64px; border-radius: 50%;
            background: #FF6B35; color: #fff;
            font-size: 28px; font-weight: 900;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 16px;
            box-shadow: 0 4px 14px rgba(255,107,53,0.3);
        }
        .step h3 { font-size: 16px; font-weight: 700; margin-bottom: 6px; }
        .step p { font-size: 14px; color: #6B7280; }

        /* Apps Section */
        .apps-section {
            padding: 100px 40px;
            background: linear-gradient(135deg, #1F2937 0%, #374151 100%);
            color: #fff; text-align: center;
        }
        .apps-section h2 { font-size: 36px; font-weight: 800; margin-bottom: 12px; }
        .apps-section > p { font-size: 16px; color: #9CA3AF; margin-bottom: 40px; }
        .apps-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px; max-width: 800px; margin: 0 auto;
        }
        .app-card {
            padding: 32px; border-radius: 20px;
            background: rgba(255,255,255,0.08);
            border: 1px solid rgba(255,255,255,0.1);
            text-align: left;
        }
        .app-card h3 { font-size: 22px; font-weight: 700; margin-bottom: 8px; }
        .app-card .app-desc { font-size: 14px; color: #9CA3AF; margin-bottom: 20px; }
        .app-card .app-badge {
            display: inline-block; padding: 4px 12px;
            border-radius: 8px; font-size: 12px; font-weight: 700;
            margin-bottom: 16px;
        }
        .badge-orange { background: rgba(255,107,53,0.2); color: #FF6B35; }
        .badge-green { background: rgba(16,185,129,0.2); color: #10B981; }
        .app-features { list-style: none; }
        .app-features li {
            padding: 8px 0; font-size: 14px; color: #D1D5DB;
            display: flex; align-items: center; gap: 10px;
        }
        .app-features li::before {
            content: ""; display: inline-block;
            width: 6px; height: 6px; border-radius: 50%;
            background: #FF6B35; flex-shrink: 0;
        }

        /* Stats */
        .stats { padding: 60px 40px; background: #FFF7ED; }
        .stats-grid { display: flex; justify-content: center; gap: 60px; flex-wrap: wrap; }
        .stat-item { text-align: center; }
        .stat-value { font-size: 42px; font-weight: 900; color: #FF6B35; }
        .stat-label { font-size: 14px; color: #6B7280; font-weight: 600; margin-top: 4px; }

        /* Footer */
        .footer { padding: 60px 40px 30px; background: #1F2937; color: #9CA3AF; }
        .footer-grid {
            display: grid; grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 40px; max-width: 1100px; margin: 0 auto 40px;
        }
        .footer-brand { font-size: 22px; font-weight: 800; color: #FF6B35; margin-bottom: 12px; }
        .footer-brand span { color: #fff; }
        .footer-desc { font-size: 14px; line-height: 1.6; }
        .footer-col h4 { color: #fff; font-size: 15px; font-weight: 700; margin-bottom: 16px; }
        .footer-col a { display: block; font-size: 14px; color: #9CA3AF; padding: 4px 0; transition: color 0.2s; }
        .footer-col a:hover { color: #FF6B35; }
        .footer-bottom {
            text-align: center; padding-top: 30px;
            border-top: 1px solid rgba(255,255,255,0.1);
            font-size: 13px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar { padding: 12px 20px; }
            .navbar-links { display: none; }
            .menu-toggle { display: block; }
            .navbar-links.active {
                display: flex; flex-direction: column;
                position: absolute; top: 60px; left: 0; right: 0;
                background: #fff; padding: 20px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            }
            .hero { padding: 120px 20px 60px; }
            .hero h1 { font-size: 32px; }
            .hero p { font-size: 16px; }
            .features, .apps-section, .how-it-works { padding: 60px 20px; }
            .features-header h2, .apps-section h2, .how-it-works h2 { font-size: 26px; }
            .footer-grid { grid-template-columns: 1fr; }
            .stats-grid { gap: 30px; }
            .stat-value { font-size: 32px; }
        }
    </style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar">
    <div class="navbar-brand">
        <svg width="32" height="32" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="10" fill="#FF6B35"/>
            <path d="M10 22L16 10L22 22" stroke="white" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="16" cy="18" r="2" fill="white"/>
        </svg>
        Restaurant de <span>L'Estuaire</span>
    </div>
    <button class="menu-toggle" onclick="document.querySelector('.navbar-links').classList.toggle('active')">&#9776;</button>
    <div class="navbar-links">
        <a href="#features">Fonctionnalites</a>
        <a href="#how">Comment ca marche</a>
        <a href="#apps">Nos Apps</a>
        <a href="/privacy-policy">Confidentialite</a>
        <a href="/admin/login" class="btn-login">Connexion Admin</a>
    </div>
</nav>

<!-- Hero -->
<section class="hero">
    <div class="hero-badge">Disponible a Bafoussam, Cameroun</div>
    <h1>Vos plats preferes, <span class="highlight">livres chez vous</span></h1>
    <p>Commandez aupres des meilleurs restaurants de Bafoussam et recevez votre repas en quelques minutes, directement a votre porte.</p>
    <div class="hero-buttons">
        <a href="#" class="btn-primary">
            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35C2.79 15.25 3.51 7.59 9.05 7.31c1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.09zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>
            App Store
        </a>
        <a href="#" class="btn-secondary">
            <svg class="store-icon" viewBox="0 0 24 24" fill="currentColor"><path d="M3.18 23.04c-.55-.31-.96-.87-.96-1.53L2.2 2.5c0-.66.42-1.23.97-1.54l9.83 11.04-9.82 11.04zm.69-23L15.64 8.8l-2.76 3.1L3.87.04zM21.54 10.5L18.5 8.84l-3.15 3.54 3.15 3.54 3.04-1.65c.87-.49.87-1.7 0-2.19v-.58zM3.87 23.97l11.77-8.77-2.76-3.1L3.87 23.97z"/></svg>
            Google Play
        </a>
    </div>
</section>

<!-- Features -->
<section class="features" id="features">
    <div class="features-header">
        <h2>Pourquoi choisir Restaurant de L'Estuaire ?</h2>
        <p>Une experience de livraison simple, rapide et fiable</p>
    </div>
    <div class="features-grid">
        <div class="feature-card">
            <div class="feature-icon" style="background:#FFF7ED;">&#127869;</div>
            <h3>Large choix de restaurants</h3>
            <p>Parcourez les meilleurs restaurants de Bafoussam, du fast-food aux plats africains traditionnels.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#ECFDF5;">&#128666;</div>
            <h3>Livraison rapide</h3>
            <p>Nos livreurs vous apportent votre commande en un temps record, directement a votre porte.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#EFF6FF;">&#128178;</div>
            <h3>Paiement mobile</h3>
            <p>Payez facilement via MTN Mobile Money, Orange Money ou en especes a la livraison.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#FEF3C7;">&#128205;</div>
            <h3>Suivi en temps reel</h3>
            <p>Suivez votre livreur en direct sur la carte et recevez des notifications a chaque etape.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#F3E8FF;">&#11088;</div>
            <h3>Avis et notes</h3>
            <p>Consultez les avis des autres clients et notez vos commandes pour ameliorer le service.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon" style="background:#FEE2E2;">&#127873;</div>
            <h3>Codes promo</h3>
            <p>Profitez de reductions exclusives avec nos codes promo et la livraison gratuite.</p>
        </div>
    </div>
</section>

<!-- How it works -->
<section class="how-it-works" id="how">
    <h2>Comment ca marche ?</h2>
    <div class="steps">
        <div class="step">
            <div class="step-number">1</div>
            <h3>Choisissez un restaurant</h3>
            <p>Parcourez les restaurants et selectionnez vos plats preferes</p>
        </div>
        <div class="step">
            <div class="step-number">2</div>
            <h3>Passez commande</h3>
            <p>Ajoutez au panier, choisissez l'adresse et le mode de paiement</p>
        </div>
        <div class="step">
            <div class="step-number">3</div>
            <h3>Suivez la livraison</h3>
            <p>Suivez votre livreur en temps reel sur la carte</p>
        </div>
        <div class="step">
            <div class="step-number">4</div>
            <h3>Bon appetit !</h3>
            <p>Recevez votre repas et notez votre experience</p>
        </div>
    </div>
</section>

<!-- Stats -->
<section class="stats">
    <div class="stats-grid">
        <div class="stat-item">
            <div class="stat-value">5+</div>
            <div class="stat-label">Restaurants partenaires</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">500 XAF</div>
            <div class="stat-label">Frais de livraison</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">20-40</div>
            <div class="stat-label">Minutes de livraison</div>
        </div>
        <div class="stat-item">
            <div class="stat-value">24/7</div>
            <div class="stat-label">Service disponible</div>
        </div>
    </div>
</section>

<!-- Apps -->
<section class="apps-section" id="apps">
    <h2>Deux applications, un seul ecosysteme</h2>
    <p>Que vous soyez client ou livreur, nous avons l'application qu'il vous faut</p>
    <div class="apps-grid">
        <div class="app-card">
            <div class="app-badge badge-orange">Application Client</div>
            <h3>Restaurant de L'Estuaire</h3>
            <p class="app-desc">Commandez vos repas preferes en quelques clics</p>
            <ul class="app-features">
                <li>Parcourir les restaurants et menus</li>
                <li>Commander et payer en ligne</li>
                <li>Suivi GPS du livreur en direct</li>
                <li>Historique et recommandes</li>
                <li>Gestion des adresses de livraison</li>
            </ul>
        </div>
        <div class="app-card">
            <div class="app-badge badge-green">Application Livreur</div>
            <h3>Express Estuaire</h3>
            <p class="app-desc">Livrez des commandes et gagnez de l'argent</p>
            <ul class="app-features">
                <li>Recevoir les commandes en temps reel</li>
                <li>Navigation GPS integree</li>
                <li>Gestion du statut de livraison</li>
                <li>Suivi des gains et statistiques</li>
                <li>Horaires flexibles</li>
            </ul>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="footer-grid">
        <div>
            <div class="footer-brand">Restaurant de <span>L'Estuaire</span></div>
            <p class="footer-desc">La plateforme de livraison de repas de reference a Bafoussam, Cameroun. Commandez, payez et recevez vos plats preferes en quelques minutes.</p>
        </div>
        <div class="footer-col">
            <h4>Navigation</h4>
            <a href="#features">Fonctionnalites</a>
            <a href="#how">Comment ca marche</a>
            <a href="#apps">Nos applications</a>
            <a href="/admin/login">Espace restaurant</a>
        </div>
        <div class="footer-col">
            <h4>Legal</h4>
            <a href="/privacy-policy">Politique de confidentialite</a>
            <a href="/privacy-policy-driver">Confidentialite livreurs</a>
            <a href="/terms-of-service">Conditions d'utilisation</a>
            <a href="/terms-of-service-driver">CGU livreurs</a>
            <a href="/delete-account">Supprimer mon compte</a>
        </div>
        <div class="footer-col">
            <h4>Contact</h4>
            <a href="mailto:contact@iues-insambot.com">contact@iues-insambot.com</a>
            <a href="tel:+237659339778">+237 659 339 778</a>
            <a>Bafoussam, Cameroun</a>
        </div>
    </div>
    <div class="footer-bottom">
        &copy; {{ date('Y') }} Restaurant de L'Estuaire. Tous droits reserves. | Chris Skyler
    </div>
</footer>

</body>
</html>
