<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>About VetCareQR — Municipal Pet Healthcare System</title>
<meta name="description" content="Learn about VetCareQR, a web-based medical record system with QR code integration and predictive analytics for municipal pet healthcare.">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<style>
  :root{
    --pink:#ffd6e7;
    --pink-2:#f7c5e0;
    --pink-dark:#bf3b78;
    --ink:#2a2e34;
    --hi:#ff6b6b;     /* high risk */
    --med:#ffa94d;    /* medium risk */
    --low:#74c69d;    /* low risk */
    --muted:#e9ecef;  /* no data */
  }
  body{font-family: system-ui, "Segoe UI", Roboto, Arial, sans-serif; color:var(--ink); background:#fff;}
  .navbar{background:#fff8fc;border-bottom:1px solid #f1e6f0; padding: 0.8rem 0;}
  .navbar .nav-link{color:var(--pink-dark);font-weight:600; transition: all 0.3s ease;}
  .navbar .nav-link:hover{color: #8c2859;}
  .navbar .nav-link.active{color: #8c2859; background: rgba(191, 59, 120, 0.1); border-radius: 4px;}

  /* Page Header */
  .page-header{
    background: radial-gradient(1200px 600px at 15% 20%, #ffe7f2 0%, #ffd8ec 40%, var(--pink) 100%);
    padding: 80px 0 60px;
    position: relative;
    overflow: hidden;
  }
  .page-header::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -20%;
    width: 300px;
    height: 300px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    z-index: 0;
  }
  
  /* Content Sections */
  .section{padding:64px 0;}
  .section-title {
    text-align: center;
    margin-bottom: 50px;
  }
  .section-title h2 {
    font-size: 2.2rem;
    color: var(--ink);
    margin-bottom: 15px;
    position: relative;
    display: inline-block;
    font-weight: 700;
  }
  .section-title h2::after {
    content: '';
    position: absolute;
    bottom: -10px;
    left: 50%;
    transform: translateX(-50%);
    width: 70px;
    height: 4px;
    background: var(--pink-dark);
    border-radius: 2px;
  }
  
  /* About Content */
  .about-content {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 40px;
  }
  .about-text {
    flex: 1;
    min-width: 300px;
  }
  .about-text h3 {
    font-size: 1.8rem;
    margin-bottom: 20px;
    color: var(--ink);
  }
  .about-text p {
    margin-bottom: 15px;
    font-size: 1.1rem;
    line-height: 1.7;
  }
  .about-image {
    flex: 1;
    min-width: 300px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
  }
  .about-image img {
    width: 100%;
    height: auto;
    display: block;
    transition: transform 0.5s;
  }
  .about-image:hover img {
    transform: scale(1.03);
  }
  
  /* Feature Cards */
  .feature-card {
    transition: all 0.3s ease;
    height: 100%;
    border: none;
    border-radius: 16px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 5px 15px rgba(0,0,0,0.05);
    padding: 30px;
  }
  .feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 10px 25px rgba(0,0,0,0.1);
  }
  .feature-icon {
    font-size: 2.5rem;
    margin-bottom: 1rem;
    color: var(--pink-dark);
  }
  
  /* Team Section */
  .team-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 30px;
  }
  .team-member {
    background: white;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    transition: transform 0.3s, box-shadow 0.3s;
    text-align: center;
  }
  .team-member:hover {
    transform: translateY(-10px);
    box-shadow: 0 15px 30px rgba(0, 0, 0, 0.12);
  }
  .team-img {
    height: 250px;
    overflow: hidden;
    background: #f8f9fa;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .team-img i {
    font-size: 4rem;
    color: var(--pink-dark);
  }
  .team-info {
    padding: 20px;
  }
  .team-info h3 {
    margin-bottom: 5px;
    color: var(--ink);
  }
  .team-info p {
    color: var(--pink-dark);
    margin-bottom: 15px;
  }
  
  /* Technology Section */
  .tech-section {
    background: linear-gradient(135deg, var(--pink-dark), #8c2859);
    color: white;
  }
  .tech-content {
    text-align: center;
    max-width: 800px;
    margin: 0 auto;
  }
  .tech-content p {
    font-size: 1.2rem;
    margin-bottom: 30px;
  }
  .tech-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 30px;
    margin-top: 50px;
  }
  .tech-card {
    background: rgba(255, 255, 255, 0.1);
    padding: 30px;
    border-radius: 10px;
    text-align: center;
    backdrop-filter: blur(5px);
    transition: transform 0.3s;
  }
  .tech-card:hover {
    transform: translateY(-5px);
  }
  .tech-icon {
    font-size: 2.5rem;
    color: white;
    margin-bottom: 20px;
  }
  .tech-card h3 {
    margin-bottom: 15px;
  }
  
  /* Buttons */
  .btn{border-radius:999px; padding:.7rem 1.5rem; font-weight:700; transition: all 0.3s ease;}
  .btn-pink{background:var(--pink-dark); color:#fff; border:none;}
  .btn-pink:hover{background:#8c2859; transform: translateY(-2px);}
  .btn-outline-pink{color:var(--pink-dark);border:2px solid var(--pink-dark);background:transparent;}
  .btn-outline-pink:hover{background:var(--pink-dark);color:#fff;}
  
  /* Footer */
  footer {
    background: #fff8fc;
    border-top: 1px solid #f1e6f0;
    padding: 40px 0;
  }
  
  /* Animation for page elements */
  .fade-in {
    animation: fadeIn 1s ease forwards;
  }
  @keyframes fadeIn {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
  }
  
  /* Responsive adjustments */
  @media (max-width: 768px) {
    .page-header {
      padding: 60px 0 40px;
    }
    .section {
      padding: 48px 0;
    }
  }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand fw-bold" href="front_page.php">
      <i class="bi bi-qr-code me-2"></i>VetCareQR
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="front_page.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="about.html">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#research">Research</a></li>
        <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2"><a class="btn btn-outline-pink mt-1 mt-lg-0" href="login.php">Login</a></li>
        <li class="nav-item ms-2"><a class="btn btn-pink mt-1 mt-lg-0" href="register.php">Register</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- Page Header -->
<section class="page-header">
  <div class="container text-center">
    <h1 class="display-4 fw-bold mb-3 fade-in">About VetCareQR</h1>
    <p class="lead fade-in">Revolutionizing municipal pet healthcare through innovative QR code technology and predictive analytics</p>
  </div>
</section>

<!-- Mission Section -->
<section class="section">
  <div class="container">
    <div class="section-title">
      <h2>Our Mission</h2>
    </div>
    <div class="about-content">
      <div class="about-text">
        <h3>Transforming Veterinary Healthcare</h3>
        <p>VetCareQR is a comprehensive web-based medical record system designed to enhance municipal pet healthcare through QR code integration and machine learning-powered predictive features.</p>
        <p>Our system addresses the challenges faced by traditional veterinary services, including misplaced records, incomplete medical histories, and difficulties in tracking vaccinations across different clinics.</p>
        <p>By combining cutting-edge technology with user-centered design, VetCareQR empowers veterinarians, local government units, and pet owners to deliver better, more proactive pet healthcare.</p>
        <a href="index.html#features" class="btn btn-pink mt-3">Explore Features</a>
      </div>
      <div class="about-image">
        <img src="https://images.unsplash.com/photo-1583337130417-3346a1be7dee?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=600&h=400&q=80" alt="Veterinary care">
      </div>
    </div>
  </div>
</section>

<!-- Features Section -->
<section class="section bg-light">
  <div class="container">
    <div class="section-title">
      <h2>Key Features</h2>
    </div>
    <div class="row g-4">
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-qr-code"></i>
          </div>
          <h4>QR Code Integration</h4>
          <p>Instant access to pet medical records through scannable QR codes, enabling quick retrieval during emergencies or routine check-ups.</p>
        </div>
      </div>
      
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-graph-up"></i>
          </div>
          <h4>Predictive Analytics</h4>
          <p>Machine learning algorithms that forecast potential health risks and vaccination needs based on historical data.</p>
        </div>
      </div>
      
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-database"></i>
          </div>
          <h4>Centralized Records</h4>
          <p>Secure cloud-based storage for all pet medical records, accessible to authorized veterinarians and pet owners.</p>
        </div>
      </div>
      
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-bell"></i>
          </div>
          <h4>Automated Reminders</h4>
          <p>Timely notifications for vaccinations, treatments, and follow-up appointments to ensure proactive pet care.</p>
        </div>
      </div>
      
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-map"></i>
          </div>
          <h4>Community Risk Monitoring</h4>
          <p>Track disease outbreaks and risk levels across different barangays to protect the entire community.</p>
        </div>
      </div>
      
      <div class="col-md-4 fade-in">
        <div class="feature-card">
          <div class="feature-icon">
            <i class="bi bi-phone"></i>
          </div>
          <h4>Responsive Design</h4>
          <p>Access the system from any device - desktop, tablet, or smartphone - for convenient pet healthcare management.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Team Section -->
<section class="section">
  <div class="container">
    <div class="section-title">
      <h2>Our Team</h2>
    </div>
    <div class="team-grid">
      <div class="team-member fade-in">
        <div class="team-img">
          <i class="bi bi-person-circle"></i>
        </div>
        <div class="team-info">
          <h3>Aira L. Alimorom</h3>
          <p>Project Developer</p>
        </div>
      </div>
      
      <div class="team-member fade-in">
        <div class="team-img">
          <i class="bi bi-person-circle"></i>
        </div>
        <div class="team-info">
          <h3>Regina R. Narbarte</h3>
          <p>Project Developer</p>
        </div>
      </div>
      
      <div class="team-member fade-in">
        <div class="team-img">
          <i class="bi bi-person-circle"></i>
        </div>
        <div class="team-info">
          <h3>Prof. Alexander G. Avendaño</h3>
          <p>Project Advisor</p>
        </div>
      </div>
    </div>
    
    <div class="text-center mt-5">
      <p class="lead">Bachelor of Information and Technology</p>
      <p>Asia Technological School of Science and Arts</p>
    </div>
  </div>
</section>

<!-- Technology Section -->
<section class="tech-section section">
  <div class="container">
    <div class="section-title">
      <h2>Our Technology</h2>
    </div>
    <div class="tech-content">
      <p>VetCareQR leverages cutting-edge technologies to deliver a robust and user-friendly pet healthcare management system.</p>
      
      <div class="tech-grid">
        <div class="tech-card fade-in">
          <div class="tech-icon">
            <i class="bi bi-code-slash"></i>
          </div>
          <h3>Web-Based Platform</h3>
          <p>Built with modern web technologies for accessibility and ease of use.</p>
        </div>
        
        <div class="tech-card fade-in">
          <div class="tech-icon">
            <i class="bi bi-diagram-3"></i>
          </div>
          <h3>Machine Learning</h3>
          <p>Random Forest and Decision Tree algorithms for accurate health predictions.</p>
        </div>
        
        <div class="tech-card fade-in">
          <div class="tech-icon">
            <i class="bi bi-hdd-stack"></i>
          </div>
          <h3>MySQL Database</h3>
          <p>Secure and efficient data storage for pet medical records.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- Call to Action -->
<section class="section">
  <div class="container text-center">
    <h2 class="mb-4 fade-in">Ready to experience VetCareQR?</h2>
    <p class="mb-4 lead fade-in">Join us in revolutionizing municipal pet healthcare with our innovative system.</p>
    <div class="d-flex flex-wrap justify-content-center gap-3">
      <a href="register.php" class="btn btn-pink btn-lg fade-in">Get Started</a>
      <a href="index.html#contact" class="btn btn-outline-pink btn-lg fade-in">Contact Us</a>
    </div>
  </div>
</section>

<footer class="py-5">
  <div class="container text-center">
    <p class="mb-2"><i class="bi bi-qr-code me-1"></i><strong>VetCareQR</strong> — Santa Rosa Municipal Veterinary Services</p>
    <p class="mb-2">Asia Technological School of Science and Arts</p>
    <small class="text-muted">© 2025 All rights reserved</small>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Enable smooth scrolling for navigation links
  document.addEventListener('DOMContentLoaded', function () {
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
    
    // Add intersection observer for fade-in animations
    const fadeElements = document.querySelectorAll('.fade-in');
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.style.animationPlayState = 'running';
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.1 });
    
    fadeElements.forEach(el => {
      el.style.animationPlayState = 'paused';
      observer.observe(el);
    });
  });
</script>
</body>
</html>