<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>VetCareQR — Santa Rosa, Laguna</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<style>
  :root{
    --pink:#ffd6e7;
    --pink-2:#f7c5e0;
    --ink:#2a2e34;
    --hi:#ff6b6b;     /* high risk */
    --med:#ffa94d;    /* medium risk */
    --low:#74c69d;    /* low risk */
    --muted:#e9ecef;  /* no data */
  }
  body{font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif; color:var(--ink); background:#fff;}
  .navbar{background:#fff8fc;border-bottom:1px solid #f1e6f0;}
  .navbar .nav-link{color:#bf3b78;font-weight:600;}

  /* HERO */
  .hero{
    background: radial-gradient(1200px 600px at 15% 20%, #ffe7f2 0%, #ffd8ec 40%, var(--pink) 100%);
    padding: 48px 0 64px;
  }
  .hero-card{
    background:#fff; border:1px solid #f0ddea; border-radius:24px;
    box-shadow:0 10px 30px rgba(184, 71, 129, .08);
    padding:32px;
  }
  .hero h1{font-weight:800; letter-spacing:-.5px;}
  .lead{color:#5d6370;}
  .cta .btn{border-radius:999px; padding:.7rem 1.2rem; font-weight:700}
  .btn-pink{background:#ff4dac; color:#fff; border:none;}
  .btn-pink:hover{filter:brightness(.95);}
  .btn-outline-pink{color:#bf3b78;border:2px solid #bf3b78;background:transparent;}
  .btn-outline-pink:hover{background:#bf3b78;color:#fff;}

  /* SVG MAP WRAP (right side) */
  .map-wrap{
    background:#ffeaf3; border:1px solid #f3d5e7; border-radius:24px;
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.6), 0 10px 26px rgba(191,59,120,.08);
    padding:16px; aspect-ratio: 4/5;  /* tall like in the screenshot */
  }
  .map-wrap svg{width:100%; height:100%;}
  .b-label{
    font: 700 9px/1.1 system-ui, -apple-system, "Segoe UI", Arial, sans-serif;
    fill:#34495e; paint-order: stroke; stroke:#fff; stroke-width:3px; stroke-linejoin:round;
  }

  /* Legend */
  .legend{gap:12px}
  .legend .chip{width:12px;height:12px;border-radius:4px;margin-right:6px;}

  /* sections below hero (optional, keep minimal) */
  .section{padding:64px 0;}

  /* ===== Enhanced pulsing risk dots (no design change) ===== */
  .pulse-dot {
    stroke: #fff;
    stroke-width: 2;
    transform-origin: center;
    animation: fadeInScale 0.8s ease forwards;
    opacity: 0;
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

  /* Subtle hover glow for barangays */
  .map-wrap path:hover {
    filter: brightness(1.08) drop-shadow(0 0 5px rgba(0,0,0,0.25));
    cursor: pointer;
    transition: filter 0.25s ease;
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg">
  <div class="container">
    <a class="navbar-brand fw-bold" href="#">🐾 VetCareQR</a>
    <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
    <div id="nav" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2"><a class="btn btn-outline-pink" href="login.php">Login</a></li>
        <li class="nav-item ms-2"><a class="btn btn-pink" href="register.php">Register</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="row g-4 align-items-center">
      <!-- LEFT: copy & CTA -->
      <div class="col-lg-6">
        <div class="hero-card">
          <span class="badge rounded-pill text-bg-light border">Santa Rosa · Laguna</span>
          <h1 class="mt-3">Smart Pet Health in <span style="color:#bf3b78">Sta. Rosa</span></h1>
          <p class="lead">Municipal pet medical records with QR access and predictive insights for vaccination and illness risk.</p>

          <div class="cta d-flex flex-wrap gap-2">
            <a class="btn btn-pink" href="dashboard.php">Explore Demo</a>
            <a class="btn btn-outline-pink" href="#map">View Risk Map</a>
          </div>

          <!-- legend -->
          <div class="d-flex align-items-center legend mt-4">
            <div class="d-flex align-items-center"><span class="chip" style="background:var(--hi)"></span>High risk</div>
            <div class="d-flex align-items-center"><span class="chip" style="background:var(--med)"></span>Moderate</div>
            <div class="d-flex align-items-center"><span class="chip" style="background:var(--low)"></span>Low</div>
          </div>
        </div>
      </div>

      <!-- RIGHT: static SVG MAP -->
      <div class="col-lg-6">
        <div class="map-wrap" id="map">
          <!-- NOTE: Stylized placeholders sized like a “map chart”.
               Replace each <path> with real Sta. Rosa barangay paths when you have the exact SVG. -->
          <svg viewBox="0 0 320 420" xmlns="http://www.w3.org/2000/svg" aria-label="Sta. Rosa, Laguna barangay risk map">
            <defs>
              <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="6" stdDeviation="6" flood-color="#caa0b7" flood-opacity=".35"/>
              </filter>
            </defs>

            <!-- Base silhouette -->
            <path d="M120,20  210,40 260,95 300,180 280,240 250,300 210,360 150,395 100,380 60,330 40,260 55,180 80,120 Z"
              fill="#f1d9e7" filter="url(#softShadow)"/>

            <!-- Regions (placeholder shapes) -->
            <path id="b1"  d="M128,52 188,64 220,96 180,116 120,104 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Aplaya — Low Risk"/>
            <path id="b2"  d="M180,116 220,96 246,140 214,162 176,148 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Balibago — Moderate Risk"/>
            <path id="b3"  d="M120,104 180,116 176,148 132,168 96,146 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Caingin — Low Risk"/>
            <path id="b4"  d="M214,162 246,140 268,188 238,214 204,196 Z" fill="var(--hi)"  data-bs-toggle="tooltip" title="Labas — High Risk"/>
            <path id="b5"  d="M132,168 176,148 204,196 176,224 132,212 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Malusak — Moderate Risk"/>
            <path id="b6"  d="M96,146 132,168 132,212 90,206 76,172 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Dila — Low Risk"/>
            <path id="b7"  d="M204,196 238,214 230,254 196,264 176,224 Z" fill="var(--med)" data-bs-toggle="tooltip" title="Tagapo — Moderate Risk"/>
            <path id="b8"  d="M132,212 176,224 196,264 164,296 120,270 Z" fill="var(--low)" data-bs-toggle="tooltip" title="Market Area — Low Risk"/>
            <path id="b9"  d="M90,206 132,212 120,270 86,252 74,224 Z"  fill="var(--med)" data-bs-toggle="tooltip" title="Macabling — Moderate Risk"/>
            <path id="b10" d="M196,264 230,254 214,296 186,320 164,296 Z" fill="var(--hi)"  data-bs-toggle="tooltip" title="Sto. Domingo — High Risk"/>
            <path id="b11" d="M120,270 164,296 146,330 110,324 92,290 Z"  fill="var(--low)" data-bs-toggle="tooltip" title="Ibaba — Low Risk"/>
            <path id="b12" d="M86,252 120,270 92,290 72,270 68,246 Z"    fill="var(--low)" data-bs-toggle="tooltip" title="Pulong Sta. Cruz — Low Risk"/>

            <!-- Single, non-duplicated pulsing dots (classes control color & pulse) -->
            <circle cx="170" cy="90"  r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Aplaya — High Risk"/>
            <circle cx="205" cy="125" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Balibago — Moderate Risk"/>
            <circle cx="138" cy="135" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Caingin — Low Risk"/>
            <circle cx="218" cy="185" r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Labas — High Risk"/>
            <circle cx="152" cy="190" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Malusak — Moderate Risk"/>
            <circle cx="98"  cy="185" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Dila — Low Risk"/>
            <circle cx="196" cy="235" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Tagapo — Moderate Risk"/>
            <circle cx="142" cy="253" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Market Area — Low Risk"/>
            <circle cx="102" cy="247" r="8" class="pulse-dot pulse-med" data-bs-toggle="tooltip" title="Macabling — Moderate Risk"/>
            <circle cx="186" cy="293" r="8" class="pulse-dot pulse-hi"  data-bs-toggle="tooltip" title="Sto. Domingo — High Risk"/>
            <circle cx="120" cy="305" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Ibaba — Low Risk"/>
            <circle cx="88"  cy="265" r="8" class="pulse-dot pulse-low" data-bs-toggle="tooltip" title="Pulong Sta. Cruz — Low Risk"/>
          </svg>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- OPTIONAL lower sections just to keep page complete -->
<section id="features" class="section">
  <div class="container">
    <div class="row g-4">
      <div class="col-md-4"><div class="p-4 bg-light rounded-4 h-100">📌 QR-based pet identities</div></div>
      <div class="col-md-4"><div class="p-4 bg-light rounded-4 h-100">📈 Predictive vaccination reminders</div></div>
      <div class="col-md-4"><div class="p-4 bg-light rounded-4 h-100">🗺️ Community risk monitoring</div></div>
    </div>
  </div>
</section>

<section id="contact" class="section">
  <div class="container text-center">
    <h4 class="mb-3">Need help or a demo?</h4>
    <a class="btn btn-outline-pink" href="mailto:lgu-vet@example.com">Contact Municipal Vet Office</a>
  </div>
</section>

<footer class="py-4 text-center" style="background:#fff8fc;border-top:1px solid #f1e6f0">
  <small>© 2025 VetCareQR — Santa Rosa Municipal Veterinary Services</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Enable tooltips (including for SVG elements)
  document.addEventListener('DOMContentLoaded', function () {
    const tooltipTriggerList = Array.from(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));
  });
</script>
</body>
</html>
