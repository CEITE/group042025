<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>VetCareQR — Veterinary Medical Records System</title>
<meta name="description" content="VetCareQR is a web-based veterinary medical record system with QR code integration for efficient pet healthcare management.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
<style>
  :root{
    --primary:#4f46e5;
    --primary-dark:#3730a3;
    --primary-light:#6366f1;
    --secondary:#10b981;
    --accent:#f59e0b;
    --danger:#ef4444;
    --ink:#1f2937;
    --light-bg:#f8fafc;
    --border:#e2e8f0;
  }
  
  body{font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif; color:var(--ink); background:#fff;}
  
  /* Improved Navigation */
  .navbar{
    background: rgba(255, 255, 255, 0.98);
    border-bottom: 1px solid var(--border); 
    padding: 1rem 0;
    backdrop-filter: blur(10px);
    transition: all 0.3s ease;
  }
  .navbar-brand {
    font-weight: 700;
    color: var(--primary);
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
    color: var(--primary);
  }

  /* Enhanced HERO */
  .hero{
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    padding: 100px 0;
    position: relative;
    overflow: hidden;
  }
  .hero::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 600px;
    height: 600px;
    background: linear-gradient(45deg, var(--primary-light) 0%, transparent 50%);
    border-radius: 50%;
    opacity: 0.1;
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
  }
  .hero-subtitle {
    font-size: 1.25rem;
    color: #64748b;
    margin-bottom: 2rem;
    line-height: 1.6;
  }
  .hero-badge {
    background: var(--primary);
    color: white;
    font-weight: 600;
    padding: 0.5rem 1.5rem;
    border-radius: 50px;
    display: inline-block;
    margin-bottom: 1.5rem;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
  }
  .cta-group {
    display: flex;
    gap: 1rem;
    flex-wrap: wrap;
    margin-bottom: 2rem;
  }
  .btn {
    border-radius: 8px;
    padding: 0.8rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    border: none;
  }
  .btn-primary{
    background: var(--primary);
    color: #fff;
    box-shadow: 0 4px 14px rgba(79, 70, 229, 0.4);
  }
  .btn-primary:hover{
    background: var(--primary-dark);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
  }
  .btn-outline-primary{
    color: var(--primary);
    border: 2px solid var(--primary);
    background: transparent;
  }
  .btn-outline-primary:hover{
    background: var(--primary);
    color: #fff;
    transform: translateY(-2px);
  }

  /* Stats Section */
  .stats-section {
    background: white;
    padding: 80px 0;
  }
  .stat-card {
    text-align: center;
    padding: 2rem;
    border-radius: 12px;
    background: var(--light-bg);
    transition: all 0.3s ease;
  }
  .stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
  }
  .stat-number {
    font-size: 3rem;
    font-weight: 800;
    color: var(--primary);
    margin-bottom: 0.5rem;
  }
  .stat-label {
    color: #64748b;
    font-weight: 600;
  }

  /* Feature cards */
  .feature-card {
    transition: all 0.3s ease;
    height: 100%;
    border: none;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    padding: 2rem;
    border: 1px solid var(--border);
  }
  .feature-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 20px 25px rgba(0, 0, 0, 0.1);
    border-color: var(--primary-light);
  }
  .feature-icon-wrapper {
    width: 60px;
    height: 60px;
    background: rgba(79, 70, 229, 0.1);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5rem;
    color: var(--primary);
    font-size: 1.5rem;
  }

  /* Demo Section */
  .demo-section {
    background: var(--light-bg);
    padding: 100px 0;
  }
  .demo-card {
    background: white;
    border-radius: 12px;
    padding: 2rem;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
    border: 1px solid var(--border);
  }

  /* sections */
  .section {
    padding: 100px 0;
  }
  .section-title {
    font-weight: 800;
    margin-bottom: 1rem;
    color: var(--ink);
    text-align: center;
  }
  .section-subtitle {
    color: #64748b;
    font-size: 1.1rem;
    margin-bottom: 3rem;
    text-align: center;
    max-width: 600px;
    margin-left: auto;
    margin-right: auto;
  }

  /* CTA Section */
  .cta-section {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: white;
    padding: 100px 0;
    text-align: center;
  }
  .cta-section .section-title {
    color: white;
  }
  .cta-section .section-subtitle {
    color: rgba(255, 255, 255, 0.9);
  }
  .btn-light {
    background: white;
    color: var(--primary);
    border-radius: 8px;
    padding: 0.8rem 2rem;
    font-weight: 600;
  }
  .btn-light:hover {
    background: #f1f5f9;
    transform: translateY(-2px);
  }

  /* Footer */
  footer {
    background: var(--ink);
    color: white;
    padding: 3rem 0 2rem;
  }
  footer a {
    color: #cbd5e1;
    text-decoration: none;
    transition: color 0.3s ease;
  }
  footer a:hover {
    color: white;
  }

  /* Scroll to top button */
  .scroll-top {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 50px;
    height: 50px;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    z-index: 1000;
    box-shadow: 0 4px 12px rgba(79, 70, 229, 0.3);
  }
  .scroll-top.active {
    opacity: 1;
    visibility: visible;
  }
  .scroll-top:hover {
    background: var(--primary-dark);
    transform: translateY(-3px);
  }

  /* Responsive adjustments */
  @media (max-width: 768px) {
    .hero {
      padding: 60px 0;
    }
    .hero h1 {
      font-size: 2.5rem;
    }
    .cta-group {
      flex-direction: column;
      align-items: flex-start;
    }
    .section {
      padding: 60px 0;
    }
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
        <li class="nav-item"><a class="nav-link" href="#demo">Demo</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2"><a class="btn btn-outline-primary mt-1 mt-lg-0" href="login.php">Login</a></li>
        <li class="nav-item ms-2"><a class="btn btn-primary mt-1 mt-lg-0" href="register.php">Get Started</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 hero-content" data-aos="fade-right">
        <span class="hero-badge">Modern Veterinary Management</span>
        <h1>Streamline Your Veterinary Practice with <span style="color:var(--primary)">QR Technology</span></h1>
        <p class="hero-subtitle">VetCareQR simplifies pet healthcare management with digital medical records, QR-based identification, and automated reminders for veterinary clinics and pet owners.</p>

        <div class="cta-group">
          <a class="btn btn-primary" href="register.php">Start Free Trial</a>
          <a class="btn btn-outline-primary" href="#demo">View Demo</a>
        </div>

        <div class="row mt-4">
          <div class="col-4">
            <div class="text-center">
              <i class="bi bi-shield-check fs-2 text-primary"></i>
              <p class="small mt-2">Secure Records</p>
            </div>
          </div>
          <div class="col-4">
            <div class="text-center">
              <i class="bi bi-clock fs-2 text-primary"></i>
              <p class="small mt-2">Time Saving</p>
            </div>
          </div>
          <div class="col-4">
            <div class="text-center">
              <i class="bi bi-graph-up fs-2 text-primary"></i>
              <p class="small mt-2">Smart Analytics</p>
            </div>
          </div>
        </div>
      </div>

      <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
        <div class="demo-card">
          <div class="text-center mb-4">
            <i class="bi bi-qr-code fs-1 text-primary"></i>
            <h4 class="mt-2">QR Pet ID Demo</h4>
          </div>
          <div class="text-center">
            <div style="background: #f8fafc; padding: 2rem; border-radius: 8px; display: inline-block;">
              <!-- Placeholder for QR code -->
              <div style="width: 200px; height: 200px; background: #e2e8f0; border-radius: 8px; display: flex; align-items: center; justify-content: center; color: #64748b;">
                <i class="bi bi-qr-code fs-1"></i>
              </div>
            </div>
            <p class="mt-3 text-muted">Scan to view sample pet medical record</p>
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
          <div class="stat-number">1,200+</div>
          <div class="stat-label">Pets Registered</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="100">
        <div class="stat-card">
          <div class="stat-number">98%</div>
          <div class="stat-label">Vaccination Rate</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="200">
        <div class="stat-card">
          <div class="stat-number">65%</div>
          <div class="stat-label">Time Saved</div>
        </div>
      </div>
      <div class="col-md-3 col-6" data-aos="fade-up" data-aos-delay="300">
        <div class="stat-card">
          <div class="stat-number">24/7</div>
          <div class="stat-label">Accessibility</div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Features section -->
<section id="features" class="section">
  <div class="container">
    <div class="text-center mb-5" data-aos="fade-up">
      <h2 class="section-title">Powerful Features for Modern Veterinary Care</h2>
      <p class="section-subtitle">Everything you need to manage pet healthcare efficiently</p>
    </div>
    <div class="row g-4">
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-qr-code-scan"></i>
          </div>
          <h4>QR Pet Identification</h4>
          <p>Instant access to medical records with unique QR codes. Perfect for emergencies and quick consultations.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-calendar-check"></i>
          </div>
          <h4>Automated Reminders</h4>
          <p>Never miss a vaccination or appointment with smart automated notifications for pet owners.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-file-medical"></i>
          </div>
          <h4>Digital Medical Records</h4>
          <p>Complete digital health history with treatment records, prescriptions, and lab results.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-graph-up"></i>
          </div>
          <h4>Health Analytics</h4>
          <p>Track pet health trends and generate reports for better healthcare decisions.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-phone"></i>
          </div>
          <h4>Mobile Access</h4>
          <p>Access records and manage appointments from any device, anywhere.</p>
        </div>
      </div>
      <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
        <div class="feature-card">
          <div class="feature-icon-wrapper">
            <i class="bi bi-shield-lock"></i>
          </div>
          <h4>Secure & Compliant</h4>
          <p>Bank-level security with full compliance to data protection regulations.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Demo Section -->
<section id="demo" class="section demo-section">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6" data-aos="fade-right">
        <h2 class="section-title text-start">See How It Works</h2>
        <p class="lead mb-4">Watch our quick demo to see how VetCareQR can transform your veterinary practice.</p>
        <ul class="list-unstyled">
          <li class="mb-3"><i class="bi bi-check-circle-fill text-primary me-2"></i>Quick pet registration</li>
          <li class="mb-3"><i class="bi bi-check-circle-fill text-primary me-2"></i>QR code generation</li>
          <li class="mb-3"><i class="bi bi-check-circle-fill text-primary me-2"></i>Medical record management</li>
          <li class="mb-3"><i class="bi bi-check-circle-fill text-primary me-2"></i>Appointment scheduling</li>
        </ul>
        <a href="#" class="btn btn-primary mt-3">Watch Full Demo</a>
      </div>
      <div class="col-lg-6" data-aos="fade-left">
        <div class="demo-card">
          <div class="text-center">
            <div style="background: #1f2937; border-radius: 8px; padding: 2rem; color: white;">
              <i class="bi bi-play-circle fs-1"></i>
              <h4 class="mt-2">Product Demo</h4>
              <p class="text-light">See VetCareQR in action</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- CTA Section -->
<section class="cta-section">
  <div class="container">
    <h2 class="section-title">Ready to Transform Your Veterinary Practice?</h2>
    <p class="section-subtitle">Join hundreds of veterinary professionals using VetCareQR to provide better care.</p>
    <div class="cta-group justify-content-center">
      <a class="btn btn-light btn-lg" href="register.php">Start Free Trial</a>
      <a class="btn btn-outline-light btn-lg" href="#contact">Schedule Demo</a>
    </div>
  </div>
</section>

<!-- Contact section -->
<section id="contact" class="section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-8 text-center" data-aos="fade-up">
        <h2 class="section-title">Get In Touch</h2>
        <p class="section-subtitle">Have questions? We'd love to hear from you.</p>
        <div class="row mt-5">
          <div class="col-md-4 mb-4">
            <i class="bi bi-envelope fs-2 text-primary mb-3"></i>
            <h5>Email Us</h5>
            <p>support@vetcareqr.com</p>
          </div>
          <div class="col-md-4 mb-4">
            <i class="bi bi-telephone fs-2 text-primary mb-3"></i>
            <h5>Call Us</h5>
            <p>+1 (555) 123-4567</p>
          </div>
          <div class="col-md-4 mb-4">
            <i class="bi bi-chat-dots fs-2 text-primary mb-3"></i>
            <h5>Live Chat</h5>
            <p>Available 9AM-6PM EST</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<footer>
  <div class="container">
    <div class="row">
      <div class="col-lg-4 mb-4 mb-lg-0">
        <h5 class="mb-3"><i class="bi bi-qr-code me-2"></i>VetCareQR</h5>
        <p>Modern veterinary management system for the digital age.</p>
      </div>
      <div class="col-lg-2 mb-4 mb-lg-0">
        <h5 class="mb-3">Product</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="#features">Features</a></li>
          <li class="mb-2"><a href="#demo">Demo</a></li>
          <li class="mb-2"><a href="pricing.php">Pricing</a></li>
        </ul>
      </div>
      <div class="col-lg-2 mb-4 mb-lg-0">
        <h5 class="mb-3">Company</h5>
        <ul class="list-unstyled">
          <li class="mb-2"><a href="about.php">About</a></li>
          <li class="mb-2"><a href="#contact">Contact</a></li>
          <li class="mb-2"><a href="#">Blog</a></li>
        </ul>
      </div>
      <div class="col-lg-4">
        <h5 class="mb-3">Connect</h5>
        <div class="d-flex gap-3 mb-3">
          <a href="#"><i class="bi bi-facebook fs-5"></i></a>
          <a href="#"><i class="bi bi-twitter fs-5"></i></a>
          <a href="#"><i class="bi bi-linkedin fs-5"></i></a>
        </div>
        <p class="small">Subscribe to our newsletter</p>
        <div class="input-group">
          <input type="email" class="form-control" placeholder="Enter your email">
          <button class="btn btn-primary">Subscribe</button>
        </div>
      </div>
    </div>
    <hr class="my-4" style="border-color: #374151;">
    <div class="text-center">
      <small>© 2025 VetCareQR. All rights reserved.</small>
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
      document.querySelector('.navbar').style.background = 'rgba(255, 255, 255, 0.98)';
      document.querySelector('.navbar').style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.1)';
    } else {
      document.querySelector('.navbar').style.background = 'rgba(255, 255, 255, 0.98)';
      document.querySelector('.navbar').style.boxShadow = 'none';
    }
  });
</script>
</body>
</html>
