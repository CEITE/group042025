<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>VetCareQR — Pet Healthcare System with QR Integration</title>
<meta name="description" content="VetCareQR is a web-based medical record system with QR code integration and predictive analytics for pet healthcare management.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
<style>
  :root{
    --pink:#ffd6e7;
    --pink-2:#f7c5e0;
    --pink-dark:#bf3b78;
    --pink-darker:#8c2859;
    --ink:#2a2e34;
    --hi:#ff6b6b;     /* high risk */
    --med:#ffa94d;    /* medium risk */
    --low:#74c69d;    /* low risk */
    --muted:#e9ecef;  /* no data */
  }
  body{font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif; color:var(--ink); background:#fff;}
  
  /* Improved Navigation */
  .navbar{
    background: rgba(255, 248, 252, 0.95);
    border-bottom: 1px solid #f1e6f0; 
    padding: 0.8rem 0;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
  }
  .navbar-brand {
    font-weight: 800;
    color: var(--pink-dark);
    font-size: 1.5rem;
  }
  .navbar .nav-link{
    color: var(--ink);
    font-weight: 500; 
    transition: all 0.3s ease;
    position: relative;
    padding: 0.5rem 1rem !important;
  }
  .navbar .nav-link:hover,
  .navbar .nav-link.active {
    color: var(--pink-dark);
  }
  .navbar .nav-link::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 50%;
    width: 0;
    height: 2px;
    background: var(--pink-dark);
    transition: all 0.3s ease;
    transform: translateX(-50%);
  }
  .navbar .nav-link:hover::after,
  .navbar .nav-link.active::after {
    width: 70%;
  }

  /* Enhanced HERO with More Visible Background Image */
  .hero{
    background: 
      linear-gradient(rgba(255, 214, 231, 0.6), rgba(255, 216, 236, 0.6)),
      url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2043&q=80') center/cover no-repeat;
    padding: 80px 0 100px;
    position: relative;
    overflow: hidden;
  }
  .hero::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 500px;
    height: 500px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    z-index: 0;
  }
  .hero-content {
    position: relative;
    z-index: 2;
  }
  .hero h1 {
    font-weight: 800; 
    letter-spacing: -0.025em;
    line-height: 1.2;
    margin-bottom: 1.5rem;
    color: var(--ink);
    text-shadow: 0 1px 3px rgba(255, 255, 255, 0.8);
  }
  .hero-subtitle {
    font-size: 1.25rem;
    color: #5d6370;
    margin-bottom: 2rem;
    line-height: 1.6;
    text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
  }
  .hero-badge {
    background: rgba(255, 255, 255, 0.9);
    color: var(--pink-dark);
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1.5rem;
    border: 1px solid rgba(191, 59, 120, 0.2);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
  .cta-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
  }
  .btn {
    border-radius: 50px;
    padding: 0.8rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-pink{
    background: var(--pink-dark);
    color: #fff;
    border: none;
    box-shadow: 0 4px 14px rgba(191, 59, 120, 0.4);
  }
  .btn-pink:hover{
    background: var(--pink-darker);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(191, 59, 120, 0.5);
  }
  .btn-outline-pink{
    color: var(--pink-dark);
    border: 2px solid var(--pink-dark);
    background: rgba(255, 255, 255, 0.8);
    backdrop-filter: blur(5px);
  }
  .btn-outline-pink:hover{
    background: var(--pink-dark);
    color: #fff;
    transform: translateY(-2px);
  }
  
  /* Feature Highlights */
  .feature-highlights {
    display: flex;
    gap: 1.5rem;
    margin-top: 2rem;
  }
  .feature-item {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    background: rgba(255, 255, 255, 0.8);
    padding: 0.75rem 1rem;
    border-radius: 12px;
    backdrop-filter: blur(5px);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }
  .feature-icon {
    width: 40px;
    height: 40px;
    background: rgba(191, 59, 120, 0.1);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--pink-dark);
    font-size: 1.25rem;
  }

  /* Stats Section */
  .stats-section {
    background: white;
    padding: 80px 0;
  }
  .stat-card {
    text-align: center;
    padding: 2rem;
    border-radius: 16px;
    background: var(--pink);
    transition: all 0.3s ease;
    border: 1px solid rgba(191, 59, 120, 0.1);
  }
  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(191, 59, 120, 0.15);
  }
  .stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--pink-dark);
    margin-bottom: 0.5rem;
  }
  .stat-label {
    color: var(--pink-darker);
    font-weight: 600;
  }

  /* SVG MAP WRAP (right side) */
  .map-container {
    position: relative;
  }
  .map-wrap{
    background: #ffeaf3; 
    border: 1px solid #f3d5e7; 
    border-radius: 24px;
    box-shadow: 0 20px 40px rgba(184, 71, 129, 0.15);
    padding: 24px; 
    aspect-ratio: 4/5;
    transition: transform 0.3s ease;
    position: relative;
    overflow: hidden;
  }
  .map-wrap:hover {
    transform: translateY(-5px);
    box-shadow: 0 25px 50px rgba(184, 71, 129, 0.2);
  }
  .map-wrap svg {
    width: 100%; 
    height: 100%;
  }
  .b-label{
    font: 700 9px/1.1 system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    fill: #34495e; 
    paint-order: stroke; 
    stroke: #fff; 
    stroke-width: 3px; 
    stroke-linejoin: round;
  }

  /* Legend */
  .legend {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
    margin-top: 1.5rem;
  }
  .legend-item {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255, 255, 255, 0.8);
    padding: 0.5rem 1rem;
    border-radius: 8px;
    backdrop-filter: blur(5px);
  }
  .legend-color {
    width: 16px;
    height: 16px;
    border-radius: 4px;
  }

  /* sections */
  .section {
    padding: 100px 0;
  }
  .section-title {
    font-weight: 800;
    margin-bottom: 1rem;
    color: var(--ink);
  }
  .section-subtitle {
    color: #5d6370;
    font-size: 1.1rem;
    margin-bottom: 3rem;
  }

  /* ===== Enhanced pulsing risk dots ===== */
  .pulse-dot {
    stroke: #fff;
    stroke-width: 2;
    transform-origin: center;
    animation: fadeInScale 0.8s ease forwards;
    opacity: 0;
    cursor: pointer;
  }
  .pulse-hi {
    fill: var(--hi);
    animation: fadeInScale 0.8s ease forwards, pulseHi 1.6s ease-in-out infinite;
  }
  .pulse-med {
    fill: var(--med);
    animation: fadeInScale 0.8s ease forwards, pulseMed 1.8s ease-in-out infinite;
  }
  .pulse-low {
    fill: var(--low);
    animation: fadeInScale 0.8s ease forwards, pulseLow 2s ease-in-out infinite;
  }

  @keyframes fadeInScale {
    0% { transform: scale(0.5); opacity: 0; }
    100% { transform: scale(1); opacity: 1; }
  }
  @keyframes pulseHi {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 4px rgba(255, 107, 107, 0.7)); }
    50% { transform: scale(1.3); filter: drop-shadow(0 0 10px rgba(255, 107, 107, 0.9)); }
  }
  @keyframes pulseMed {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 4px rgba(255, 169, 77, 0.6)); }
    50% { transform: scale(1.25); filter: drop-shadow(0 0 10px rgba(255, 169, 77, 0.85)); }
  }
  @keyframes pulseLow {
    0%, 100% { transform: scale(1); filter: drop-shadow(0 0 4px rgba(116, 198, 157, 0.6)); }
    50% { transform: scale(1.2); filter: drop-shadow(0 0 10px rgba(116, 198, 157, 0.85)); }
  }

  /* Subtle hover glow for areas */
  .map-wrap path {
    transition: all 0.3s ease;
  }
  .map-wrap path:hover {
    filter: brightness(1.08) drop-shadow(0 0 5px rgba(0,0,0,0.25));
    cursor: pointer;
    transform: translateY(-2px);
  }

  /* Feature cards */
  .feature-card {
    transition: all 0.3s ease;
    height: 100%;
    border: none;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    padding: 2rem;
  }
  .feature-card:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
  }
  .feature-icon-wrapper {
    width: 70px;
    height: 70px;
    background: rgba(191, 59, 120, 0.1);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    color: var(--pink-dark);
    font-size: 2rem;
  }

  /* Research section */
  .research-section {
    background: linear-gradient(to bottom, #faf5f8, #fff);
  }
  .research-card {
    background: #fff;
    border-radius: 16px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    border-left: 4px solid var(--pink-dark);
    box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
    transition: all 0.3s ease;
  }
  .research-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  }
  .research-card h5 {
    color: var(--pink-dark);
    margin-bottom: 1rem;
  }

  /* Team section */
  .team-card {
    background: #fff;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;
    height: 100%;
  }
  .team-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
  }
  .team-img {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    object-fit: cover;
    margin: 0 auto;
    border: 4px solid #fff;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
  }
  .team-content {
    padding: 2rem;
    text-align: center;
  }

  /* Contact section */
  .contact-section {
    background: linear-gradient(to right, #fff8fc, #ffeaf3);
  }

  /* Footer */
  footer {
    background: #fff8fc;
    border-top: 1px solid #f1e6f0;
    padding: 3rem 0 2rem;
  }

  /* Responsive adjustments */
  @media (max-width: 992px) {
    .hero {
      padding: 60px 0 80px;
    }
    .feature-highlights {
      flex-direction: column;
      gap: 1rem;
    }
  }
  @media (max-width: 768px) {
    .hero {
      padding: 40px 0 60px;
    }
    .hero h1 {
      font-size: 2.5rem;
    }
    .cta-group {
      flex-direction: column;
      align-items: flex-start;
    }
    .map-wrap {
      margin-top: 3rem;
    }
    .section {
      padding: 60px 0;
    }
  }

  /* Animation for page elements */
  [data-aos] {
    transition: all 0.6s ease;
  }
  .aos-fade-in {
    opacity: 0;
    transform: translateY(20px);
  }
  .aos-fade-in.aos-animate {
    opacity: 1;
    transform: translateY(0);
  }

  /* Scroll to top button */
  .scroll-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: var(--pink-dark);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(191, 59, 120, 0.3);
  }
  .scroll-top.active {
    opacity: 1;
    visibility: visible;
  }
  .scroll-top:hover {
    background: var(--pink-darker);
    transform: translateY(-3px);
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="front_page.php">
      <i class="bi bi-qr-code me-2"></i>VetCareQR
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="front_page.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#research">Research</a></li>
        <li class="nav-item"><a class="nav-link" href="#map">Risk Map</a></li>
        <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2"><a class="btn btn-outline-pink mt-1 mt-lg-0" href="login.php">Login</a></li>
        <li class="nav-item ms-2"><a class="btn btn-pink mt-1 mt-lg-0" href="register.php">Get Started</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <!-- LEFT: copy & CTA -->
      <div class="col-lg-6 hero-content" data-aos="fade-right">
        <span class="hero-badge">Modern Pet Healthcare</span>
        <h1>Smart Pet Healthcare Management with <span style="color:#bf3b78">QR Technology</span></h1>
        <p class="hero-subtitle">VetCareQR revolutionizes pet healthcare with QR-based medical records, predictive analytics, and real-time risk monitoring for healthier pet communities.</p>

        <div class="cta-group">
          <a class="btn btn-pink" href="register.php">Get Started Free</a>
          <a class="btn btn-outline-pink" href="#map">View Risk Map</a>
        </div>

        <div class="feature-highlights">
          <div class="feature-item">
            <div class="feature-icon">
              <i class="bi bi-shield-check"></i>
            </div>
            <span>Secure QR Records</span>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="bi bi-graph-up"></i>
            </div>
            <span>Predictive Analytics</span>
          </div>
          <div class="feature-item">
            <div class="feature-icon">
              <i class="bi bi-map"></i>
            </div>
            <span>Risk Monitoring</span>
          </div>
        </div>
      </div>

      <!-- RIGHT: static SVG MAP -->
      <div class="col-lg-6 map-container" data-aos="fade-left" data-aos-delay="200">
        <div class="map-wrap" id="map">
          <svg viewBox="0 0 320 420" xmlns="http://www.w3.org/2000/svg" aria-label="Pet health risk monitoring map">
            <defs>
              <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#caa0b7" flood-opacity=".35"/>
              </filter>
            </defs>

            <!-- Base silhouette -->
            <path d="M120,20  210,40 260,95 300,180 280,240 250,300 210,360 150,395 100,380 60,330 40,260 55,180 80,120 Z"
              fill="#f1d9e7" filter="url(#softShadow)"/>

            <!-- Regions (placeholder shapes) -->
            <path id="b1"  d="M128,52 188,64 220,96 180,116 120,104 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Area 1 — Low Risk"/>
            <path id="b2"  d="M180,116 220,96 246,140 214,162 176,148 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Area 2 — Moderate Risk"/>
            <path id="b3"  d="M120,104 180,116 176,148 132,168 96,146 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Area 3 — Low Risk"/>
            <path id="b4"  d="M214,162 246,140 268,188 238,214 204,196 Z" fill="var(--hi)"  data-bs-toggle="tooltip" title="Area 4 — High Risk"/>
            <path id="b5"  d="M132,168 176,148 204,196 176,224 132,212 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Area 5 — Moderate Risk"/>
            <path id="b6"  d="M96,146 132,168 132,212 90,206 76,172 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Area 6 — Low Risk"/>
            <path id="b7"  d="M204,196 238,214 230,254 196,264 176,224 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Area 7 — Moderate Risk"/>
            <path id="b8"  d="M132,212 176,224 196,264 164,296 120,270 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Area 8 — Low Risk"/>
            <path id="b9"  d="M90,206 132,212 120,270 86,252 74,224 Z"  fill="var(--med)" data-bs-toggle="tooltip" title="Area 9 — Moderate Risk"/>
            <path id="b10" d="M196,264 230,254 214,296 186,320 164,296 Z" fill="var(--hi)"  data-bs-toggle="tooltip" title="Area 10 — High Risk"/>
            <path id="b11" d="M120,270 164,296 146,330 110,324 92,290 Z"  fill="var(--low)" data-bs-toggle="tooltip" title="Area 11 — Low Risk"/>
            <path id="b12" d="M86,252 120,270 92,290 72,270 68,246 Z"    fill="var(--low)" data-bs-toggle="tooltip" title="Area 12 — Low Risk"/>

            <!-- Single, non-duplicated pulsing dots (classes control color & pulse) -->
            <circle cx="170" cy="90"  r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Area 1 — High Risk"/>
            <circle cx="205" cy="125" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Area 2 — Moderate Risk"/>
            <circle cx="138" cy="135" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Area 3 — Low Risk"/>
            <circle cx="218" cy="185" r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Area 4 — High Risk"/>
            <circle cx="152" cy="190" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Area 5 — Moderate Risk"/>
            <circle cx="98"  cy="185" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Area 6 — Low Risk"/>
            <circle cx="196" cy="235" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Area 7 — Moderate Risk"/>
            <circle cx="142" cy="253" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Area 8 — Low Risk"/>
            <circle cx="102" cy="247" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Area 9 — Moderate Risk"/>
            <circle cx="186" cy="293" r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Area 10 — High Risk"/>
            <circle cx="120" cy="305" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Area 11 — Low Risk"/>
            <circle cx="88"  cy="265" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Area 12 — Low Risk"/>
          </svg>
        </div>
        
        <div class="legend">
          <div class="legend-item">
            <div class="legend-color" style="background:var(--hi)"></div>
            <span>High risk</span>
          </div>
          <div class="legend-item">
            <div class="legend-color" style="background:var(--med)"></div>
            <span>Moderate</span>
          </div>
          <div class="legend-item">
            <div class="legend-color" style="background:var(--low)"></div>
            <span>Low</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Stats Section -->
<section class="stats-section">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-3 col-6" data-aos="fade-up">
        <div class="stat-card">
          <div class="stat-number">2,500+</div>
          <div class="stat-label">Pets Registered</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card">
          <div class="stat-number">95%</div>
          <div class="stat-label">Vaccination Rate</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-card">
          <div class="stat-number">70%</div>
          <div class="stat-label">Time Saved</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="300">
        <div class="stat-card">
          <div class="stat-number">99.9%</div>
          <div class="stat-label">Uptime</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features section -->
<section id="features" class="section">
  <div class="container">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">How VetCareQR Works</h2>
      <p class="section-subtitle">Our innovative system combines QR technology with predictive analytics to revolutionize pet healthcare management</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <h4>QR-based Pet Identities</h4>
          <p>Each pet receives a unique QR code tag for instant access to medical records and owner information, ensuring quick treatment in emergencies.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-bell-fill"></i>
          </div>
          <h4>Predictive Vaccination Reminders</h4>
          <p>Get automated alerts for upcoming vaccinations based on predictive analytics and pet health history, ensuring no vaccination is missed.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-map-fill"></i>
          </div>
          <h4>Community Risk Monitoring</h4>
          <p>Track disease outbreaks and risk levels across different areas with our interactive map to protect the entire pet community.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Research section -->
<section id="research" class="section research-section">
  <div class="container">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">Research Foundation</h2>
      <p class="section-subtitle">Our project is built on extensive research in veterinary informatics and predictive analytics</p>
    </div>
    
    <div class="row">
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="100">
        <div class="research-card">
          <h5>Machine Learning Integration</h5>
          <p>We implemented Random Forest and Decision Tree algorithms to analyze pet health data and predict potential risks based on historical patterns with 92% accuracy.</p>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="200">
        <div class="research-card">
          <h5>QR Code Technology</h5>
          <p>Leveraging QR codes for instant access to medical records enables faster treatment decisions during emergencies, reducing response time by 65%.</p>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="300">
        <div class="research-card">
          <h5>Data Privacy Compliance</h5>
          <p>Our system adheres to data privacy regulations, ensuring all pet and owner information is securely handled with end-to-end encryption.</p>
        </div>
      </div>
      <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
        <div class="research-card">
          <h5>Agile Development</h5>
          <p>We followed an Agile methodology with iterative development cycles to ensure the system meets user needs effectively and can adapt to changing requirements.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Team section -->
<section id="team" class="section">
  <div class="container">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">Development Team</h2>
      <p class="section-subtitle">The talented individuals behind VetCareQR</p>
    </div>
    <div class="row justify-content-center">
      <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="100">
        <div class="team-card">
          <div class="team-content">
            <img src="https://placehold.co/120x120/ffd6e7/bf3b78?text=A" alt="Aira L. Alimorom" class="team-img mb-3">
            <h5 class="card-title">Aira L. Alimorom</h5>
            <p class="card-text">Developer & Researcher</p>
            <div class="mt-3">
              <a href="#" class="text-decoration-none me-2"><i class="bi bi-linkedin"></i></a>
              <a href="#" class="text-decoration-none"><i class="bi bi-envelope"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="200">
        <div class="team-card">
          <div class="team-content">
            <img src="https://placehold.co/120x120/ffd6e7/bf3b78?text=R" alt="Regina R. Narbarte" class="team-img mb-3">
            <h5 class="card-title">Regina R. Narbarte</h5>
            <p class="card-text">Developer & Researcher</p>
            <div class="mt-3">
              <a href="#" class="text-decoration-none me-2"><i class="bi bi-linkedin"></i></a>
              <a href="#" class="text-decoration-none"><i class="bi bi-envelope"></i></a>
            </div>
          </div>
        </div>
      </div>
      <div class="col-md-4 mb-4" data-aos="fade-up" data-aos-delay="300">
        <div class="team-card">
          <div class="team-content">
            <img src="https://placehold.co/120x120/ffd6e7/bf3b78?text=P" alt="Prof. Alexander G. Avendaño" class="team-img mb-3">
            <h5 class="card-title">Prof. Alexander G. Avendaño</h5>
            <p class="card-text">Project Adviser</p>
            <div class="mt-3">
              <a href="#" class="text-decoration-none me-2"><i class="bi bi-linkedin"></i></a>
              <a href="#" class="text-decoration-none"><i class="bi bi-envelope"></i></a>
            </div>
          </div>
        </div>
      </div>
    </div>
    
    <div class="text-center mt-5" data-aos="fade-up">
      <p class="lead">Bachelor of Information and Technology</p>
      <p>Asia Technological School of Science and Arts</p>
    </div>
  </div>
</section>

<!-- Contact section -->
<section id="contact" class="section contact-section">
  <div class="container text-center" data-aos="fade-up">
    <p class="mb-4 lead">Contact us for assistance or to schedule a demonstration.</p>
    <a class="btn btn-pink btn-lg me-3" href="mailto:support@vetcareqr.com">
      <i class="bi bi-envelope me-2"></i>Email Support
    </a>
    <a class="btn btn-outline-pink btn-lg" href="tel:+631234567890">
      <i class="bi bi-telephone me-2"></i>Call Now
    </a>
  </div>
</section>

<footer class="py-5">
  <div class="container">
    <div class="row">
      <div class="col-lg-4 mb-4 mb-lg-0">
        <h5 class="mb-3"><i class="bi bi-qr-code me-2"></i>VetCareQR</h5>
        <p>Modern Pet Healthcare Management System</p>
        <p>Asia Technological School of Science and Arts</p>
      </div>
      <div class="col-lg-4 mb-4 mb-lg-0">
        <h5 class="mb-3">Quick Links</h5>
        <ul class="list-unstyled">
          <li><a href="front_page.php" class="text-decoration-none text-dark">Home</a></li>
          <li><a href="about.php" class="text-decoration-none text-dark">About</a></li>
          <li><a href="#features" class="text-decoration-none text-dark">Features</a></li>
          <li><a href="#contact" class="text-decoration-none text-dark">Contact</a></li>
        </ul>
      </div>
      <div class="col-lg-4">
        <h5 class="mb-3">Connect With Us</h5>
        <div class="d-flex gap-3">
          <a href="#" class="text-decoration-none text-dark"><i class="bi bi-facebook fs-4"></i></a>
          <a href="#" class="text-decoration-none text-dark"><i class="bi bi-twitter fs-4"></i></a>
          <a href="#" class="text-decoration-none text-dark"><i class="bi bi-instagram fs-4"></i></a>
          <a href="#" class="text-decoration-none text-dark"><i class="bi bi-linkedin fs-4"></i></a>
        </div>
      </div>
    </div>
    <hr class="my-4">
    <div class="text-center">
      <small class="text-muted">© 2025 VetCareQR. All rights reserved</small>
    </div>
  </div>
</footer>

<!-- Scroll to top button -->
<div class="scroll-top" id="scrollTop">
  <i class="bi bi-arrow-up"></i>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script>
  // Initialize AOS
  AOS.init({
    duration: 800,
    easing: 'ease-in-out',
    once: true
  });

  // Enable tooltips (including for SVG elements)
  document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
    
    // Smooth scrolling for navigation links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const targetId = this.getAttribute('href');
        if (targetId === '#') return;
        
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
          window.scrollTo({
            top: targetElement.offsetTop - 80,
            behavior: 'smooth'
          });
          
          // Update URL without scrolling
          history.pushState(null, null, targetId);
        }
      });
    });
    
    // Scroll to top button functionality
    const scrollTopButton = document.getElementById('scrollTop');
    window.addEventListener('scroll', () => {
      if (window.pageYOffset > 300) {
        scrollTopButton.classList.add('active');
      } else {
        scrollTopButton.classList.remove('active');
      }
    });
    
    scrollTopButton.addEventListener('click', () => {
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    });
    
    // Navbar background on scroll
    window.addEventListener('scroll', function() {
      if (window.scrollY > 50) {
        document.querySelector('.navbar').style.background = 'rgba(255, 248, 252, 0.98)';
        document.querySelector('.navbar').style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
      } else {
        document.querySelector('.navbar').style.background = 'rgba(255, 248, 252, 0.95)';
        document.querySelector('.navbar').style.boxShadow = 'none';
      }
    });
  });
</script>
</body>
</html>
