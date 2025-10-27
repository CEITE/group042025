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

  /* Enhanced HERO with Sliding Background Animation */
  .hero{
    position: relative;
    padding: 80px 0 100px;
    overflow: hidden;
  }
  .hero-slideshow {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 0;
  }
  .slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    transition: opacity 1.5s ease-in-out;
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
  }
  .slide.active {
    opacity: 1;
  }
  .slide-1 {
    background-image: linear-gradient(rgba(255, 214, 231, 0.6), rgba(255, 216, 236, 0.6)), 
                     url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2043&q=80');
  }
  .slide-2 {
    background-image: linear-gradient(rgba(255, 214, 231, 0.6), rgba(255, 216, 236, 0.6)), 
                     url('https://images.unsplash.com/photo-1543466835-00a7907e9de1?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1974&q=80');
  }
  .slide-3 {
    background-image: linear-gradient(rgba(255, 214, 231, 0.6), rgba(255, 216, 236, 0.6)), 
                     url('https://images.unsplash.com/photo-1554456854-55a089fd4cb2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
  }
  .slide-4 {
    background-image: linear-gradient(rgba(255, 214, 231, 0.6), rgba(255, 216, 236, 0.6)), 
                     url('https://images.unsplash.com/photo-1583337130417-3346a1be7dee?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1964&q=80');
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
    z-index: 1;
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
  
  /* Role Selection Modal - FIXED SCROLLING */
  .role-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 248, 252, 0.95);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    overflow-y: auto; /* ADDED THIS */
    padding: 20px 0; /* ADDED THIS */
  }
  .role-modal.active {
    opacity: 1;
    visibility: visible;
  }
  .role-container {
    background: white;
    border-radius: 24px;
    padding: 3rem;
    box-shadow: 0 20px 60px rgba(191, 59, 120, 0.2);
    max-width: 800px;
    width: 90%;
    text-align: center;
    margin: auto; /* ADDED THIS */
  }
  .role-title {
    font-weight: 800;
    color: var(--pink-dark);
    margin-bottom: 1rem;
    font-size: 2.5rem;
  }
  .role-subtitle {
    color: #5d6370;
    margin-bottom: 3rem;
    font-size: 1.2rem;
  }
  .role-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 2rem;
    margin-bottom: 3rem;
  }
  .role-card {
    background: #fff;
    border: 2px solid #f1e6f0;
    border-radius: 20px;
    padding: 2.5rem 1.5rem;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
  }
  .role-card:hover {
    transform: translateY(-10px);
    border-color: var(--pink-dark);
    box-shadow: 0 15px 40px rgba(191, 59, 120, 0.15);
  }
  .role-card.active {
    border-color: var(--pink-dark);
    background: linear-gradient(135deg, #fff8fc, #ffeaf3);
  }
  .role-icon {
    width: 80px;
    height: 80px;
    background: rgba(191, 59, 120, 0.1);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1.5rem;
    color: var(--pink-dark);
    font-size: 2.5rem;
    transition: all 0.3s ease;
  }
  .role-card:hover .role-icon {
    background: var(--pink-dark);
    color: white;
    transform: scale(1.1);
  }
  .role-card h4 {
    color: var(--ink);
    margin-bottom: 1rem;
    font-weight: 700;
  }
  .role-card p {
    color: #5d6370;
    line-height: 1.6;
    margin-bottom: 1.5rem;
  }
  .role-features {
    list-style: none;
    padding: 0;
    text-align: left;
  }
  .role-features li {
    padding: 0.5rem 0;
    color: #5d6370;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }
  .role-features li i {
    color: var(--pink-dark);
    font-size: 0.9rem;
  }
  .role-continue-btn {
    background: var(--pink-dark);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 1rem 3rem;
    font-weight: 600;
    font-size: 1.1rem;
    transition: all 0.3s ease;
    opacity: 0.7;
    cursor: not-allowed;
  }
  .role-continue-btn.active {
    opacity: 1;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(191, 59, 120, 0.4);
  }
  .role-continue-btn.active:hover {
    background: var(--pink-darker);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(191, 59, 120, 0.5);
  }

  /* Verification Modal - FIXED SCROLLING */
  .verification-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 248, 252, 0.95);
    backdrop-filter: blur(10px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
    overflow-y: auto; /* ADDED THIS */
    padding: 20px 0; /* ADDED THIS */
  }
  .verification-modal.active {
    opacity: 1;
    visibility: visible;
  }
  .verification-container {
    background: white;
    border-radius: 24px;
    padding: 3rem;
    box-shadow: 0 20px 60px rgba(191, 59, 120, 0.2);
    max-width: 500px;
    width: 90%;
    text-align: center;
    margin: auto; /* ADDED THIS */
  }
  .verification-title {
    font-weight: 800;
    color: var(--pink-dark);
    margin-bottom: 1rem;
    font-size: 2rem;
  }
  .verification-subtitle {
    color: #5d6370;
    margin-bottom: 2rem;
    font-size: 1.1rem;
  }
  .form-group {
    margin-bottom: 1.5rem;
    text-align: left;
  }
  .form-label {
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 0.5rem;
    display: block;
  }
  .form-control {
    width: 100%;
    padding: 0.75rem 1rem;
    border: 2px solid #e5e7eb;
    border-radius: 12px;
    font-size: 1rem;
    transition: all 0.3s ease;
  }
  .form-control:focus {
    border-color: var(--pink-dark);
    box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.1);
    outline: none;
  }
  .verification-badge {
    background: rgba(191, 59, 120, 0.1);
    color: var(--pink-dark);
    padding: 0.75rem 1.5rem;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    margin-bottom: 2rem;
    font-weight: 600;
  }
  .btn-group {
    display: flex;
    gap: 1rem;
    margin-top: 2rem;
  }
  .btn-back {
    background: #f3f4f6;
    color: #374151;
    border: none;
    border-radius: 50px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    flex: 1;
  }
  .btn-back:hover {
    background: #e5e7eb;
  }
  .btn-verify {
    background: var(--pink-dark);
    color: white;
    border: none;
    border-radius: 50px;
    padding: 0.75rem 2rem;
    font-weight: 600;
    transition: all 0.3s ease;
    flex: 1;
    box-shadow: 0 4px 14px rgba(191, 59, 120, 0.4);
  }
  .btn-verify:hover {
    background: var(--pink-darker);
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(191, 59, 120, 0.5);
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
    .role-cards {
      grid-template-columns: 1fr;
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
    .section {
      padding: 60px 0;
    }
    .role-container {
      padding: 2rem;
    }
    .role-title {
      font-size: 2rem;
    }
    .verification-container {
      padding: 2rem;
    }
    .btn-group {
      flex-direction: column;
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

  .text-pink-dark {
    color: var(--pink-dark) !important;
  }
</style>
</head>
<body>

<!-- Role Selection Modal -->
<div class="role-modal" id="roleModal">
  <div class="role-container">
    <h1 class="role-title">Welcome to VetCareQR</h1>
    <p class="role-subtitle">Please select your role to continue</p>
    
    <div class="role-cards">
      <div class="role-card" onclick="selectRole('user')">
        <div class="role-icon">
          <i class="bi bi-person"></i>
        </div>
        <h4>Pet Owner</h4>
        <p>Manage your pet's health records, track vaccinations, and access medical history</p>
        <ul class="role-features">
          <li><i class="bi bi-check-circle"></i> Manage pet profiles</li>
          <li><i class="bi bi-check-circle"></i> Track medical records</li>
          <li><i class="bi bi-check-circle"></i> QR code access</li>
          <li><i class="bi bi-check-circle"></i> Appointment scheduling</li>
        </ul>
      </div>
      
      <div class="role-card" onclick="selectRole('veterinarian')">
        <div class="role-icon">
          <i class="bi bi-heart-pulse"></i>
        </div>
        <h4>Veterinarian</h4>
        <p>Access patient records, update medical information, and provide professional care</p>
        <ul class="role-features">
          <li><i class="bi bi-check-circle"></i> Patient record access</li>
          <li><i class="bi bi-check-circle"></i> Medical updates</li>
          <li><i class="bi bi-check-circle"></i> Treatment planning</li>
          <li><i class="bi bi-check-circle"></i> Professional dashboard</li>
        </ul>
      </div>
      
      <div class="role-card" onclick="selectRole('admin')">
        <div class="role-icon">
          <i class="bi bi-shield-check"></i>
        </div>
        <h4>Administrator</h4>
        <p>Manage system users, monitor platform activity, and maintain system integrity</p>
        <ul class="role-features">
          <li><i class="bi bi-check-circle"></i> User management</li>
          <li><i class="bi bi-check-circle"></i> System monitoring</li>
          <li><i class="bi bi-check-circle"></i> Data analytics</li>
          <li><i class="bi bi-check-circle"></i> Platform settings</li>
        </ul>
      </div>
    </div>
    
    <button class="role-continue-btn" id="continueBtn" onclick="handleContinue()">
      Continue to Login
    </button>
  </div>
</div>

<!-- Verification Modal -->
<div class="verification-modal" id="verificationModal">
  <div class="verification-container">
    <div class="verification-badge" id="verificationBadge">
      <i class="bi bi-shield-check"></i>
      <span id="verificationRole">Veterinarian Verification</span>
    </div>
    <h2 class="verification-title" id="verificationTitle">Professional Verification Required</h2>
    <p class="verification-subtitle" id="verificationSubtitle">Please provide your credentials to verify your identity as a veterinarian</p>
    
    <form id="verificationForm" onsubmit="handleVerification(event)">
      <div class="form-group">
        <label for="licenseNumber" class="form-label">License Number</label>
        <input type="text" class="form-control" id="licenseNumber" placeholder="Enter your professional license number" required>
      </div>
      
      <div class="form-group">
        <label for="clinicName" class="form-label">Clinic/Hospital Name</label>
        <input type="text" class="form-control" id="clinicName" placeholder="Enter your clinic or hospital name" required>
      </div>
      
      <div class="form-group" id="adminCodeGroup" style="display: none;">
        <label for="adminCode" class="form-label">Administrator Access Code</label>
        <input type="password" class="form-control" id="adminCode" placeholder="Enter administrator access code" required>
      </div>
      
      <div class="btn-group">
        <button type="button" class="btn btn-back" onclick="backToRoleSelection()">
          <i class="bi bi-arrow-left me-2"></i>Back
        </button>
        <button type="submit" class="btn btn-verify">
          <i class="bi bi-shield-check me-2"></i>Verify & Continue
        </button>
      </div>
    </form>
  </div>
</div>

<!-- Rest of your HTML content remains the same -->
<nav class="navbar navbar-expand-lg sticky-top">
  <div class="container">
    <a class="navbar-brand" href="#">
      <i class="bi bi-qr-code me-2"></i>VetCareQR
    </a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
        <li class="nav-item"><a class="nav-link" href="#research">Research</a></li>
        <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
        <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
        <li class="nav-item ms-2"><a class="btn btn-outline-pink mt-1 mt-lg-0" href="#" onclick="showRoleModal()">Login</a></li>
        <li class="nav-item ms-2"><a class="btn btn-pink mt-1 mt-lg-0" href="#">Get Started</a></li>
      </ul>
    </div>
  </div>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-slideshow">
    <div class="slide slide-1 active"></div>
    <div class="slide slide-2"></div>
    <div class="slide slide-3"></div>
    <div class="slide slide-4"></div>
  </div>
  <div class="container">
    <div class="row align-items-center">
      <!-- LEFT: copy & CTA -->
      <div class="col-lg-6 hero-content" data-aos="fade-right">
        <span class="hero-badge">Modern Pet Healthcare</span>
        <h1>Smart Pet Healthcare Management with <span style="color:#bf3b78">QR Technology</span></h1>
        <p class="hero-subtitle">VetCareQR revolutionizes pet healthcare with QR-based medical records, predictive analytics, and real-time risk monitoring for healthier pet communities.</p>

        <div class="cta-group">
          <a class="btn btn-pink" href="#">Get Started Free</a>
          <a class="btn btn-outline-pink" href="#" onclick="showRoleModal()">Login to Your Account</a>
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
              <i class="bi bi-heart-pulse"></i>
            </div>
            <span>Health Monitoring</span>
          </div>
        </div>
      </div>

      <!-- RIGHT: Feature illustration -->
      <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
        <div class="text-center">
          <div class="feature-icon-wrapper" style="width: 120px; height: 120px; margin: 0 auto 2rem;">
            <i class="bi bi-phone" style="font-size: 3rem;"></i>
          </div>
          <h3 style="color: var(--pink-dark); margin-bottom: 1rem;">Scan. Access. Care.</h3>
          <p class="hero-subtitle">Instant access to pet medical records through QR technology for faster, smarter healthcare decisions.</p>
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
            <i class="bi bi-clipboard-data"></i>
          </div>
          <h4>Comprehensive Health Tracking</h4>
          <p>Monitor your pet's complete health history, medications, and treatment plans in one secure, accessible platform.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script>
  // Initialize AOS
  document.addEventListener('DOMContentLoaded', function() {
    AOS.init({
      duration: 800,
      once: true,
      offset: 100
    });

    // Hero Slideshow
    let currentSlide = 0;
    const slides = document.querySelectorAll('.slide');
    
    function showSlide(n) {
      slides.forEach(slide => slide.classList.remove('active'));
      currentSlide = (n + slides.length) % slides.length;
      slides[currentSlide].classList.add('active');
    }
    
    function nextSlide() {
      showSlide(currentSlide + 1);
    }
    
    setInterval(nextSlide, 5000);
  });

  // Role Selection Functions - REMOVED body overflow hidden
  let selectedRole = null;

  function showRoleModal() {
    document.getElementById('roleModal').classList.add('active');
    // REMOVED: document.body.style.overflow = 'hidden';
    
    // Reset selection
    selectedRole = null;
    document.getElementById('continueBtn').classList.remove('active');
    
    // Remove active class from all cards
    document.querySelectorAll('.role-card').forEach(card => {
      card.classList.remove('active');
    });
  }

  function selectRole(role) {
    // Remove active class from all cards
    document.querySelectorAll('.role-card').forEach(card => {
      card.classList.remove('active');
    });
    
    // Add active class to clicked card
    event.currentTarget.classList.add('active');
    
    // Set selected role
    selectedRole = role;
    
    // Enable continue button
    document.getElementById('continueBtn').classList.add('active');
  }

  function handleContinue() {
    if (!selectedRole) {
      alert('Please select a role first!');
      return;
    }
    
    // Hide role modal
    document.getElementById('roleModal').classList.remove('active');
    
    if (selectedRole === 'user') {
      // Redirect pet owners directly
      alert('Redirecting to user login...');
      return;
    }
    
    // Show verification modal for professionals
    showVerificationModal();
  }

  function showVerificationModal() {
    const roleTitle = document.getElementById('verificationTitle');
    const roleSubtitle = document.getElementById('verificationSubtitle');
    const roleBadge = document.getElementById('verificationRole');
    const adminCodeGroup = document.getElementById('adminCodeGroup');
    
    if (selectedRole === 'veterinarian') {
      roleTitle.textContent = 'Professional Verification Required';
      roleSubtitle.textContent = 'Please provide your credentials to verify your identity as a veterinarian';
      roleBadge.textContent = 'Veterinarian Verification';
      adminCodeGroup.style.display = 'none';
    } else if (selectedRole === 'admin') {
      roleTitle.textContent = 'Administrator Access';
      roleSubtitle.textContent = 'Please provide administrator credentials to access the system';
      roleBadge.textContent = 'Administrator Verification';
      adminCodeGroup.style.display = 'block';
    }
    
    document.getElementById('verificationModal').classList.add('active');
  }

  function handleVerification(event) {
    event.preventDefault();
    alert('Verification successful! Redirecting to dashboard...');
    hideVerificationModal();
  }

  function backToRoleSelection() {
    document.getElementById('verificationModal').classList.remove('active');
    showRoleModal();
  }

  function hideVerificationModal() {
    document.getElementById('verificationModal').classList.remove('active');
    // REMOVED: document.body.style.overflow = 'auto';
  }

  // Close modals when clicking outside
  document.addEventListener('click', function(event) {
    const roleModal = document.getElementById('roleModal');
    const verificationModal = document.getElementById('verificationModal');
    
    if (event.target === roleModal) {
      roleModal.classList.remove('active');
    }
    
    if (event.target === verificationModal) {
      verificationModal.classList.remove('active');
    }
  });
</script>
</body>
</html>
