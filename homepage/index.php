<?php session_start(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Villaflores Gaming Cafe — Official Landing Page</title>
    <link rel="stylesheet" href="../style.css">
</head>
<body>

    <div class="page-wrapper">
        <div class="site-container">

            <!-- HEADER / NAVIGATION -->
            <header class="site-header">
                <img src="../images/logo.png" alt="Villaflores Gaming Cafe Logo" class="header-logo">
                <nav class="header-nav">
                    <a href="#home" class="nav-link">Home</a>
                    <a href="#about" class="nav-link">About</a>
                    <a href="#experience" class="nav-link">Experience</a>
                    <a href="#rates" class="nav-link">Rates</a>
                    <a href="#community" class="nav-link">Community</a>
                </nav>
                <div class="header-actions">
                    <?php if (!empty($_SESSION['user_id'])): ?>
                        <span class="header-greeting">Hi, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                    <?php endif; ?>
                    <div class="login-stack">
                        <?php if (!empty($_SESSION['user_id'])): ?>
                            <a href="../logout/logout.php" class="btn-login">Logout</a>
                        <?php else: ?>
                            <a href="../login/login.php" class="btn-login">Login</a>
                        <?php endif; ?>
                        <a href="../admin/admin_login.php" class="admin-login-link">Admin login</a>
                    </div>
                    <a href="../booking/booking.php" class="btn-book">Book a Seat</a>
                </div>
            </header>

            <div class="header-divider"></div>

            <!-- SECTION 1 — HERO -->
            <section class="hero-section brand-grid" id="home">
                <div class="hero-grid">
                    <div class="hero-content">
                        <h1 class="hero-title">
                            PLAY.<br>
                            <span class="text-cerulean">CONNECT.</span><br>
                            <span class="text-cerulean">LEVEL UP.</span>
                        </h1>
                        <p class="hero-description">
                            Villaflores Gaming Cafe is your neighborhood spot for high-performance PCs, fast internet, and good company — a comfortable place where gamers and friends gather to play.
                        </p>

                        <div class="hero-actions">
                            <a href="#rates" class="btn-primary-pink">View Rates</a>
                            <a href="#about" class="btn-secondary-outline">Explore the Cafe</a>
                        </div>

                        <div class="hero-stats">
                            <div class="stat-item">
                                <div class="stat-value">30+</div>
                                <div class="stat-label">Gaming pc’s</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">100+</div>
                                <div class="stat-label">Gaming cafe’s</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">24/7</div>
                                <div class="stat-label">Open everyday</div>
                            </div>
                        </div>
                    </div>

                    <div class="hero-media">
                        <div class="hero-image-box">
                            <img src="https://images.unsplash.com/photo-1633545505446-586bf83717f0?w=1400&h=1000&fit=crop&auto=format" alt="Gaming PC setup" class="hero-main-img">
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 2 — ABOUT -->
            <section class="about-section" id="about">
                <div class="about-grid">
                    <div class="about-images-col">
                        <img src="https://images.unsplash.com/photo-1696710257827-75e2e5954059?w=900&h=1200&fit=crop&auto=format" alt="Gaming rig close up" class="about-img-left">
                        <img src="https://images.unsplash.com/photo-1633545486613-feaf749f7805?w=900&h=1100&fit=crop&auto=format" alt="Ambient gaming station" class="about-img-right">
                    </div>

                    <div class="about-content">
                        <h2 class="section-title">A COMFORTABLE GAMING CAFE BUILT FOR GAMERS</h2>
                        <p class="about-description">
                            Our mission at Villaflores Gaming Cafe is to provide an exciting, comfortable, and welcoming space where gamers and friends can play, connect, and relax. We deliver quality gaming experiences, refreshing refreshments, and friendly service while building a strong gaming community.
                        </p>

                        <div class="about-bullets-grid">
                            <div class="about-bullet-item">
                                <span class="diamond diamond-pink"></span>
                                <span class="bullet-text">High-performance gaming PCs</span>
                            </div>
                            <div class="about-bullet-item">
                                <span class="diamond diamond-pink"></span>
                                <span class="bullet-text">Fast & stable fiber internet</span>
                            </div>
                            <div class="about-bullet-item">
                                <span class="diamond diamond-pink"></span>
                                <span class="bullet-text">Comfortable gaming stations</span>
                            </div>
                            <div class="about-bullet-item">
                                <span class="diamond diamond-pink"></span>
                                <span class="bullet-text">Friendly local community</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- SECTION 3 — FEATURES / EXPERIENCE -->
            <section class="features-section brand-grid" id="experience">
                <div class="center-header">
                    <h2 class="section-title features-headline">EVERYTHING YOU NEED TO PLAY AT YOUR BEST</h2>
                </div>

                <div class="features-cards-grid">
                    <div class="feature-card">
                        <div class="feature-card-header">
                            <span class="feature-badge cerulean">01</span>
                        </div>
                        <h3 class="feature-card-title">PERFORMANCE<br>PCs</h3>
                        <p class="feature-card-desc">Latest-gen GPUs, high-refresh monitors, and mechanical peripherals on every station.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-card-header">
                            <span class="feature-badge pink">02</span>
                        </div>
                        <h3 class="feature-card-title">FAST INTERNET</h3>
                        <p class="feature-card-desc">Low-latency fiber connection tuned for competitive online play, downloads, and streaming.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-card-header">
                            <span class="feature-badge cerulean">03</span>
                        </div>
                        <h3 class="feature-card-title">COMFY STATIONS</h3>
                        <p class="feature-card-desc">Ergonomic gaming chairs and roomy desks designed for long, comfortable sessions.</p>
                    </div>

                    <div class="feature-card">
                        <div class="feature-card-header">
                            <span class="feature-badge pink">04</span>
                        </div>
                        <h3 class="feature-card-title">COMPETITIVE<br>SCENE</h3>
                        <p class="feature-card-desc">A friendly, competitive environment with room for casual matches and local tournaments.</p>
                    </div>
                </div>
            </section>

            <!-- SECTION 4 — ENVIRONMENT / SETUP -->
            <section class="environment-section">
                <div class="env-layout-grid">
                    <div class="env-banner-top">
                        <img src="https://images.unsplash.com/photo-1725273578052-14575f0daa01?w=1400&h=900&fit=crop&auto=format" alt="Gaming cafe lounge" class="env-img-top">
                    </div>

                    <div class="env-info-card">
                        <p class="env-info-text">
                            Clean desks, ambient lighting, and quality peripherals — a gaming area designed to be comfortable for hours, without the overwhelming clutter. Grab a drink, settle in, and play.
                        </p>
                        <div class="env-stats-row">
                            <div>
                                <div class="env-stat-val text-cerulean">240Hz</div>
                                <div class="env-stat-lbl">Displays</div>
                            </div>
                            <div>
                                <div class="env-stat-val text-pink">Cozy</div>
                                <div class="env-stat-lbl">Lounge Area</div>
                            </div>
                            <div>
                                <div class="env-stat-val text-cerulean">Snacks</div>
                                <div class="env-stat-lbl">& Drinks</div>
                            </div>
                        </div>
                    </div>

                    <div class="env-title-card">
                        <h2 class="env-bottom-title">A SPACE MADE TO MAKE YOU WANT TO STAY</h2>
                    </div>

                    <div class="env-banner-bottom">
                        <img src="https://images.unsplash.com/photo-1633545486613-feaf749f7805?w=1400&h=900&fit=crop&auto=format" alt="Gaming station setup" class="env-img-bottom">
                    </div>
                </div>
            </section>

            <!-- SECTION 5 — PRICING / RATES -->
            <section class="pricing-section brand-grid" id="rates">
                <div class="center-header">
                    <h2 class="section-title">SIMPLE, FLEXIBLE PRICING</h2>
                    <p class="pricing-header-desc">Straightforward hourly rates and value packages for longer sessions.</p>
                </div>

                <div class="pricing-grid">
                    <div class="pricing-card">
                        <h3 class="plan-title">HOURLY GAMING</h3>
                        <div class="price-row">
                            <span class="price-amount text-cerulean">₱50</span>
                            <span class="price-period">/ hour</span>
                        </div>
                        <div class="pricing-divider"></div>
                        <ul class="plan-feature-list">
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Pay as you play</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Any open station</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Full-speed internet</span>
                            </li>
                        </ul>
                        <a href="../booking/booking.php?plan=Hourly%20Gaming" class="btn-plan btn-plan-outline">CHOOSE PLAN</a>
                    </div>

                    <div class="pricing-card featured-card">
                        <span class="popular-badge">Most Popular</span>
                        <h3 class="plan-title">EXTENDED GAMING</h3>
                        <div class="price-row">
                            <span class="price-amount text-pink">₱200</span>
                            <span class="price-period">/ 5 hours</span>
                        </div>
                        <div class="pricing-divider"></div>
                        <ul class="plan-feature-list">
                            <li class="plan-feature-row">
                                <span class="diamond diamond-pink"></span>
                                <span>Best value for long sessions</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-pink"></span>
                                <span>Reserved station</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-pink"></span>
                                <span>Free refreshment</span>
                            </li>
                        </ul>
                        <a href="../booking/booking.php?plan=Extended%20Gaming" class="btn-plan btn-plan-solid">CHOOSE PLAN</a>
                    </div>

                    <div class="pricing-card">
                        <h3 class="plan-title">GAMING PACKAGE</h3>
                        <div class="price-row">
                            <span class="price-amount text-cerulean">₱180</span>
                            <span class="price-period">/ 3 players</span>
                        </div>
                        <div class="pricing-divider"></div>
                        <ul class="plan-feature-list">
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Ideal for friends</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Adjacent stations</span>
                            </li>
                            <li class="plan-feature-row">
                                <span class="diamond diamond-cerulean"></span>
                                <span>Group discount</span>
                            </li>
                        </ul>
                        <a href="../booking/booking.php?plan=Gaming%20Package" class="btn-plan btn-plan-outline">CHOOSE PLAN</a>
                    </div>
                </div>
            </section>

            <!-- SECTION 6 — COMMUNITY -->
            <section class="community-section" id="community">
                <div class="community-grid">
                    <div class="community-text-col">
                        <h2 class="section-title">A PLACE FOR GAMERS</h2>
                        <p class="community-desc">
                            Meet fellow players, team up for ranked matches, or join a casual weekend session. Villaflores is where the local gaming community comes together to play, connect, and relax.
                        </p>
                        <div class="community-badges-row">
                            <a href="../reviews/reviews.php" class="community-badge-box pink">
                                <div class="badge-lead text-pink">RATE US</div>
                                <div class="badge-sub">GIVE US A REVIEW!</div>
                            </a>
                            <a href="../tournaments/tournaments.php" class="community-badge-box cerulean">
                                <div class="badge-lead text-cerulean">LOCAL</div>
                                <div class="badge-sub">TOURNAMENTS</div>
                            </a>
                        </div>
                    </div>

                    <div class="community-images-col">
                        <img src="https://images.unsplash.com/photo-1659535915214-e7cbac112038?w=900&h=1100&fit=crop&auto=format" alt="Gamers playing together" class="comm-img-tall">
                        <img src="https://images.unsplash.com/photo-1758179765254-5d2058fd1ff7?w=900&h=700&fit=crop&auto=format" alt="Two players at monitor" class="comm-img-sm">
                        <img src="https://images.unsplash.com/photo-1548214079-50eccb4e0a32?w=900&h=700&fit=crop&auto=format" alt="Mechanical keyboard player" class="comm-img-sm">
                    </div>
                </div>
            </section>

            <!-- SECTION 7 — FOOTER -->
            <footer class="site-footer">
                <div class="footer-grid">
                    <div>
                        <img src="../images/logo.png" alt="Villaflores Gaming Cafe Logo" class="footer-logo">
                        <p class="footer-brand-text">
                            Play. Connect. Relax. Your neighborhood gaming cafe and community hub.
                        </p>
                        <div class="footer-tagline-diamonds">
                            <span>Play</span>
                            <span class="diamond diamond-cerulean"></span>
                            <span>Connect</span>
                            <span class="diamond diamond-pink"></span>
                            <span>Relax</span>
                        </div>
                    </div>

                    <div>
                        <h4 class="footer-col-head text-pink">LOCATION</h4>
                        <div class="footer-lines-list">
                            <div class="footer-line-text">Purok 5</div>
                            <div class="footer-line-text">Tanjay City</div>
                        </div>
                    </div>

                    <div>
                        <h4 class="footer-col-head text-cerulean">OPENING HOURS</h4>
                        <div class="footer-lines-list">
                            <div class="footer-line-text">24/7</div>
                        </div>
                    </div>

                    <div>
                        <h4 class="footer-col-head text-pink">CONTACT</h4>
                        <div class="footer-lines-list">
                            <div class="footer-line-text">0935 908 9023</div>
                            <div class="footer-line-text">Villaflores@gmail.com</div>
                            <div class="footer-line-text">Villaflores Gaming</div>
                        </div>
                    </div>
                </div>

                <div class="footer-bottom-bar">
                    <span>© 2026 Villaflores Gaming Cafe. All rights reserved.</span>
                </div>
            </footer>

        </div>
    </div>

</body>
</html>