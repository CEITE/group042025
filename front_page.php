<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>VetCare — Pet Healthcare System with QR Integration</title>
    <meta name="description" content="VetCare is a web-based medical record system with QR code integration and predictive analytics for pet healthcare management.">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --ink: #2a2e34;
            --ink-light: #5d6370;
            --white: #ffffff;
            --bg-light: #f0f9ff;
            --hi: #ef4444;     /* high risk */
            --med: #f59e0b;    /* medium risk */
            --low: #10b981;    /* low risk */
            --muted: #e9ecef;  /* no data */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 20px 60px rgba(14, 165, 233, 0.15);
            --border-radius: 16px;
            --border-radius-lg: 24px;
            --primary-gradient: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body{
            font-family: 'Inter', system-ui, "Segoe UI", Roboto, Arial, sans-serif;
            color: var(--ink);
            background: var(--white);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: var(--bg-light);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--primary-dark);
        }

        /* Improved Navigation */
        .navbar{
            background: rgba(240, 249, 255, 0.98);
            border-bottom: 1px solid rgba(224, 242, 254, 0.8); 
            padding: 1rem 0;
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 20px rgba(14, 165, 233, 0.08);
            z-index: 1000;
        }

        .navbar.scrolled {
            padding: 0.7rem 0;
            background: rgba(240, 249, 255, 0.95);
            box-shadow: 0 4px 30px rgba(14, 165, 233, 0.1);
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--primary);
            font-size: 1.6rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
        }

        .navbar-brand:hover {
            transform: translateY(-1px);
        }

        .navbar .nav-link{
            color: var(--ink);
            font-weight: 500; 
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 1.2rem !important;
            font-size: 0.95rem;
        }

        .navbar .nav-link:hover,
        .navbar .nav-link.active {
            color: var(--primary);
        }

        .navbar .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
            border-radius: 2px;
        }

        .navbar .nav-link:hover::after,
        .navbar .nav-link.active::after {
            width: 80%;
        }

        /* Enhanced HERO with Advanced Animations */
        .hero{
            position: relative;
            padding: 120px 0 150px;
            overflow: hidden;
            background: linear-gradient(135deg, var(--bg-light) 0%, #e0f2fe 100%);
        }

        .hero::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: var(--primary-gradient);
            border-radius: 50%;
            opacity: 0.1;
            animation: float 6s ease-in-out infinite;
        }

        .hero::after {
            content: "";
            position: absolute;
            bottom: -30%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: var(--primary-gradient);
            border-radius: 50%;
            opacity: 0.08;
            animation: float 8s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(5deg); }
        }

        /* Enhanced Slideshow */
        .hero-slideshow {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            overflow: hidden;
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
            transform: scale(1.1);
            filter: brightness(0.6);
        }

        .slide.active {
            opacity: 1;
            transform: scale(1);
            filter: brightness(0.7);
        }

        .slide-1 {
            background-image: url('images/background1.jpeg');
        }

        .slide-2 {
            background-image: url('images/background2.jpeg');
        }

        .slide-3 {
            background-image: url('images/background3.jpeg');
        }

        .slide-4 {
            background-image: url('images/background4.png');
        }

        .slide-nav {
            position: absolute;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 10px;
            z-index: 3;
        }

        .slide-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.5);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .slide-dot.active {
            background: var(--white);
            transform: scale(1.2);
        }

        .slide-arrow {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: var(--white);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 3;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .slide-arrow:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-50%) scale(1.1);
        }

        .slide-arrow.prev {
            left: 20px;
        }

        .slide-arrow.next {
            right: 20px;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero h1 {
            font-weight: 800; 
            letter-spacing: -0.025em;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--white);
            font-size: 3.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.5);
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--white);
            margin-bottom: 2.5rem;
            line-height: 1.7;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
            font-weight: 400;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.95);
            color: var(--primary);
            font-weight: 600;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(14, 165, 233, 0.2);
            box-shadow: var(--shadow-sm);
            backdrop-filter: blur(10px);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .cta-group {
            display: flex;
            gap: 1.2rem;
            flex-wrap: wrap;
            margin-bottom: 3rem;
        }

        .btn {
            border-radius: 50px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: none;
            font-size: 1rem;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary{
            background: var(--primary-gradient);
            color: var(--white);
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
        }

        .btn-primary:hover{
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(14, 165, 233, 0.4);
        }

        .btn-outline-primary{
            color: var(--white);
            border: 2px solid var(--white);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .btn-outline-primary:hover{
            background: var(--white);
            color: var(--primary);
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 255, 255, 0.2);
        }

        /* Feature Highlights */
        .feature-highlights {
            display: flex;
            gap: 1.5rem;
            margin-top: 3rem;
            flex-wrap: wrap;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            background: rgba(255, 255, 255, 0.85);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            backdrop-filter: blur(10px);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }

        .feature-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .feature-icon {
            width: 50px;
            height: 50px;
            background: var(--primary-gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .feature-item:hover .feature-icon {
            transform: rotate(10deg) scale(1.1);
        }

        /* Enhanced Role Selection Modal */
        .role-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(240, 249, 255, 0.98);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            padding: 20px 0;
        }

        .role-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .role-container {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 4rem;
            box-shadow: var(--shadow-lg);
            max-width: 900px;
            width: 95%;
            text-align: center;
            margin: auto;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
            overflow: hidden;
        }

        .role-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .role-title {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 2.8rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .role-subtitle {
            color: var(--ink-light);
            margin-bottom: 3rem;
            font-size: 1.3rem;
            font-weight: 400;
        }

        .role-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .role-card {
            background: var(--white);
            border: 2px solid #e0f2fe;
            border-radius: var(--border-radius-lg);
            padding: 3rem 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--primary);
            box-shadow: var(--shadow-lg);
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-card.active {
            border-color: var(--primary);
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .role-card.active::before {
            transform: scaleX(1);
        }

        .role-icon {
            width: 90px;
            height: 90px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--primary);
            font-size: 2.8rem;
            transition: all 0.4s ease;
            position: relative;
        }

        .role-icon::after {
            content: '';
            position: absolute;
            inset: -5px;
            background: var(--primary-gradient);
            border-radius: 25px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .role-card:hover .role-icon {
            background: var(--primary-gradient);
            color: var(--white);
            transform: scale(1.1) rotate(5deg);
        }

        .role-card:hover .role-icon::after {
            opacity: 1;
            animation: pulse-ring 1.5s ease-in-out infinite;
        }

        @keyframes pulse-ring {
            0% { transform: scale(0.8); opacity: 1; }
            100% { transform: scale(1.2); opacity: 0; }
        }

        .role-card h4 {
            color: var(--ink);
            margin-bottom: 1.2rem;
            font-weight: 700;
            font-size: 1.4rem;
        }

        .role-card p {
            color: var(--ink-light);
            line-height: 1.7;
            margin-bottom: 2rem;
            font-size: 1rem;
        }

        .role-features {
            list-style: none;
            padding: 0;
            text-align: left;
        }

        .role-features li {
            padding: 0.6rem 0;
            color: var(--ink-light);
            display: flex;
            align-items: center;
            gap: 0.8rem;
            transition: all 0.3s ease;
        }

        .role-features li:hover {
            color: var(--primary);
            transform: translateX(5px);
        }

        .role-features li i {
            color: var(--primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .role-features li:hover i {
            transform: scale(1.2);
        }

        .role-continue-btn {
            background: var(--primary-gradient);
            color: var(--white);
            border: none;
            border-radius: 50px;
            padding: 1.2rem 4rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.7;
            cursor: not-allowed;
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
            position: relative;
            overflow: hidden;
        }

        .role-continue-btn.active {
            opacity: 1;
            cursor: pointer;
        }

        .role-continue-btn.active:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(14, 165, 233, 0.4);
        }

        /* Enhanced Verification Modal */
        .verification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(240, 249, 255, 0.98);
            backdrop-filter: blur(20px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            overflow-y: auto;
            padding: 20px 0;
        }

        .verification-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .verification-container {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 4rem;
            box-shadow: var(--shadow-lg);
            max-width: 550px;
            width: 95%;
            text-align: center;
            margin: auto;
            border: 1px solid rgba(255, 255, 255, 0.8);
            position: relative;
        }

        .verification-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .verification-title {
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 1rem;
            font-size: 2.2rem;
            background: var(--primary-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .verification-subtitle {
            color: var(--ink-light);
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 2rem;
            text-align: left;
        }

        .form-label {
            font-weight: 600;
            color: var(--ink);
            margin-bottom: 0.8rem;
            display: block;
            font-size: 1rem;
        }

        .form-control {
            width: 100%;
            padding: 1rem 1.5rem;
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        .verification-badge {
            background: rgba(14, 165, 233, 0.1);
            color: var(--primary);
            padding: 1rem 2rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            border: 1px solid rgba(14, 165, 233, 0.2);
        }

        .btn-group {
            display: flex;
            gap: 1.2rem;
            margin-top: 2.5rem;
        }

        .btn-back {
            background: #f8f9fa;
            color: var(--ink);
            border: 2px solid #e9ecef;
            border-radius: 50px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-back:hover {
            background: #e9ecef;
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .btn-verify {
            background: var(--primary-gradient);
            color: var(--white);
            border: none;
            border-radius: 50px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            box-shadow: 0 8px 25px rgba(14, 165, 233, 0.3);
        }

        .btn-verify:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(14, 165, 233, 0.4);
        }

        /* Enhanced Sections */
        .section {
            padding: 120px 0;
            position: relative;
        }

        .section-title {
            font-weight: 800;
            margin-bottom: 1.2rem;
            color: var(--ink);
            font-size: 2.8rem;
            text-align: center;
            background: linear-gradient(135deg, var(--ink) 0%, var(--primary) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .section-subtitle {
            color: var(--ink-light);
            font-size: 1.2rem;
            margin-bottom: 4rem;
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.7;
        }

        /* Enhanced Feature Cards */
        .feature-card {
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            height: 100%;
            border: none;
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            background: var(--white);
            box-shadow: var(--shadow-sm);
            padding: 3rem 2rem;
            position: relative;
            border: 1px solid rgba(255, 255, 255, 0.8);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: var(--shadow-lg);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(14, 165, 233, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--primary);
            font-size: 2.5rem;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon-wrapper {
            background: var(--primary-gradient);
            color: var(--white);
            transform: scale(1.1) rotate(5deg);
        }

        /* Enhanced Research Section */
        .research-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #fff 100%);
            position: relative;
        }

        .research-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
        }

        .research-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--primary);
            box-shadow: var(--shadow-sm);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .research-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(14, 165, 233, 0.05), transparent);
            transition: left 0.5s;
        }

        .research-card:hover::before {
            left: 100%;
        }

        .research-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .research-card h5 {
            color: var(--primary);
            margin-bottom: 1.2rem;
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* Enhanced Team Section */
        .team-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
        }

        .team-card {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
            transition: all 0.4s ease;
            height: 100%;
            position: relative;
            text-align: center;
        }

        .team-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .team-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .team-card:hover::before {
            transform: scaleX(1);
        }

        .team-img {
            width: 160px;
            height: 160px;
            border-radius: 50%;
            object-fit: cover;
            margin: 2rem auto 1.5rem;
            border: 4px solid var(--white);
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }

        .team-card:hover .team-img {
            transform: scale(1.1);
            border-color: var(--primary);
        }

        .team-content {
            padding: 0 2rem 2.5rem;
        }

        .team-content h4 {
            color: var(--ink);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }

        .team-role {
            color: var(--primary);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        /* FAQ Section Styles */
        .faq-section {
            background: linear-gradient(135deg, #f8fbff 0%, #f0f9ff 100%);
            position: relative;
        }

        .faq-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
        }

        .faq-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .faq-item {
            background: var(--white);
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(14, 165, 233, 0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .faq-item:hover {
            box-shadow: var(--shadow-md);
            border-color: rgba(14, 165, 233, 0.2);
        }

        .faq-question {
            padding: 2rem;
            background: var(--white);
            border: none;
            width: 100%;
            text-align: left;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--ink);
            transition: all 0.3s ease;
        }

        .faq-question:hover {
            background: rgba(14, 165, 233, 0.02);
        }

        .faq-question.active {
            background: rgba(14, 165, 233, 0.05);
            color: var(--primary);
        }

        .faq-icon {
            width: 32px;
            height: 32px;
            background: var(--primary-gradient);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 0.9rem;
            transition: all 0.3s ease;
            flex-shrink: 0;
            margin-left: 1rem;
        }

        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 2rem;
            max-height: 0;
            overflow: hidden;
            transition: all 0.4s ease;
            background: var(--white);
            border-top: 1px solid transparent;
        }

        .faq-answer.active {
            padding: 0 2rem 2rem;
            max-height: 500px;
            border-top-color: rgba(14, 165, 233, 0.1);
        }

        .faq-answer-content {
            color: var(--ink-light);
            line-height: 1.7;
        }

        .faq-answer-content ul {
            padding-left: 1.5rem;
            margin: 1rem 0;
        }

        .faq-answer-content li {
            margin-bottom: 0.5rem;
            color: var(--ink-light);
        }

        .faq-categories {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 3rem;
            flex-wrap: wrap;
        }

        .faq-category-btn {
            background: var(--white);
            border: 2px solid #e0f2fe;
            border-radius: 50px;
            padding: 0.8rem 2rem;
            font-weight: 600;
            color: var(--ink-light);
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .faq-category-btn.active,
        .faq-category-btn:hover {
            background: var(--primary-gradient);
            color: var(--white);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-sm);
        }

        .faq-contact-cta {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            text-align: center;
            margin-top: 4rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(14, 165, 233, 0.1);
        }

        .faq-contact-cta h4 {
            color: var(--ink);
            margin-bottom: 1rem;
            font-weight: 700;
        }

        .faq-contact-cta p {
            color: var(--ink-light);
            margin-bottom: 2rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Enhanced Contact Section */
        .contact-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            position: relative;
        }

        .contact-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
        }

        .contact-info {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            box-shadow: var(--shadow-sm);
            height: 100%;
        }

        .contact-item {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            background: rgba(14, 165, 233, 0.05);
            transform: translateX(5px);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: var(--primary-gradient);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            flex-shrink: 0;
        }

        .contact-form {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 3rem;
            box-shadow: var(--shadow-sm);
        }

        /* About Section */
        .about-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #fff 100%);
        }

        .about-content {
            background: var(--white);
            border-radius: var(--border-radius-lg);
            padding: 4rem;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .about-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-gradient);
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .stat-item {
            text-align: center;
            padding: 2rem;
            background: rgba(14, 165, 233, 0.05);
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(14, 165, 233, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--ink-light);
            font-weight: 600;
        }

        /* Enhanced Footer */
        footer {
            background: linear-gradient(135deg, #2a2e34 0%, #1a1e23 100%);
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding: 4rem 0 2rem;
            color: var(--white);
            position: relative;
        }

        footer::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary-gradient);
        }

        footer .text-muted {
            color: #a0a4a8 !important;
        }

        .text-primary {
            color: var(--primary) !important;
        }

        /* Enhanced Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: var(--shadow-lg);
            border: none;
            font-size: 1.2rem;
        }

        .scroll-top.active {
            opacity: 1;
            visibility: visible;
        }

        .scroll-top:hover {
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 15px 40px rgba(14, 165, 233, 0.4);
        }

        /* Form Controls */
        .form-control {
            border: 2px solid #f0f0f0;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
            box-shadow: var(--shadow-sm);
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .hero h1 {
                font-size: 3rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
        }

        @media (max-width: 992px) {
            .hero {
                padding: 100px 0 120px;
            }
            
            .feature-highlights {
                flex-direction: column;
                gap: 1rem;
            }
            
            .role-cards {
                grid-template-columns: 1fr;
            }
            
            .hero h1 {
                font-size: 2.5rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .slide-arrow {
                width: 40px;
                height: 40px;
                font-size: 1.2rem;
            }
            
            .faq-categories {
                flex-direction: column;
                align-items: center;
            }
            
            .faq-category-btn {
                width: 200px;
                justify-content: center;
            }
        }

        @media (max-width: 768px) {
            .hero {
                padding: 80px 0 100px;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .cta-group {
                flex-direction: column;
                align-items: center;
            }
            
            .section {
                padding: 80px 0;
            }
            
            .role-container,
            .verification-container {
                padding: 2.5rem 2rem;
            }
            
            .role-title {
                font-size: 2.2rem;
            }
            
            .section-title {
                font-size: 2rem;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .feature-item {
                padding: 1rem;
            }
            
            .about-content,
            .contact-info,
            .contact-form {
                padding: 2rem;
            }
            
            .slide-nav {
                bottom: 20px;
            }
            
            .slide-arrow {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }
            
            .slide-arrow.prev {
                left: 10px;
            }
            
            .slide-arrow.next {
                right: 10px;
            }
            
            .faq-question {
                padding: 1.5rem;
                font-size: 1rem;
            }
            
            .faq-answer.active {
                padding: 0 1.5rem 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .hero h1 {
                font-size: 1.8rem;
            }
            
            .hero-subtitle {
                font-size: 1.1rem;
            }
            
            .section-title {
                font-size: 1.8rem;
            }
            
            .role-title,
            .verification-title {
                font-size: 1.8rem;
            }
            
            .role-container,
            .verification-container {
                padding: 2rem 1.5rem;
            }
            
            .stats {
                grid-template-columns: 1fr;
            }
            
            .faq-category-btn {
                width: 100%;
                max-width: 280px;
            }
        }

        /* Animation for page elements */
        [data-aos] {
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .aos-fade-in {
            opacity: 0;
            transform: translateY(30px);
        }

        .aos-fade-in.aos-animate {
            opacity: 1;
            transform: translateY(0);
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>

<!-- Role Selection Modal -->
<div class="role-modal" id="roleModal">
    <div class="role-container">
        <h1 class="role-title">Welcome to VetCare</h1>
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

<!-- Navigation -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="bi bi-qr-code"></i>VetCare
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <li class="nav-item"><a class="nav-link active" href="#">Home</a></li>
                <li class="nav-item"><a class="nav-link" href="#about">About</a></li>
                <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                <li class="nav-item"><a class="nav-link" href="#research">Research</a></li>
                <li class="nav-item"><a class="nav-link" href="#team">Team</a></li>
                <li class="nav-item"><a class="nav-link" href="#faq">FAQ</a></li>
                <li class="nav-item"><a class="nav-link" href="#contact">Contact</a></li>
                <li class="nav-item ms-2"><a class="btn btn-outline-primary mt-1 mt-lg-0" href="#" onclick="showRoleModal()">Login</a></li>
                <li class="nav-item ms-2"><a class="btn btn-primary mt-1 mt-lg-0" href="#">Get Started</a></li>
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
        
        <!-- Navigation arrows -->
        <button class="slide-arrow prev" onclick="prevSlide()">
            <i class="bi bi-chevron-left"></i>
        </button>
        <button class="slide-arrow next" onclick="nextSlide()">
            <i class="bi bi-chevron-right"></i>
        </button>
        
        <!-- Navigation dots -->
        <div class="slide-nav" id="slideNav">
            <div class="slide-dot active" onclick="goToSlide(0)"></div>
            <div class="slide-dot" onclick="goToSlide(1)"></div>
            <div class="slide-dot" onclick="goToSlide(2)"></div>
            <div class="slide-dot" onclick="goToSlide(3)"></div>
        </div>
    </div>
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content" data-aos="fade-right">
                <span class="hero-badge"><i class="bi bi-star-fill me-2"></i>Modern Pet Healthcare</span>
                <h1>Smart Pet Healthcare Management with <span class="text-primary">QR Technology</span></h1>
                <p class="hero-subtitle">VetCare revolutionizes pet healthcare with QR-based medical records, predictive analytics, and real-time risk monitoring for healthier pet communities.</p>

                <div class="cta-group">
                    <a class="btn btn-primary" href="#">Get Started Free</a>
                    <a class="btn btn-outline-primary" href="#" onclick="showRoleModal()">Login to Your Account</a>
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

            <div class="col-lg-6" data-aos="fade-left" data-aos-delay="200">
                <div class="text-center">
                    <div class="feature-icon-wrapper" style="width: 140px; height: 140px; margin: 0 auto 2rem;">
                        <i class="bi bi-phone" style="font-size: 3.5rem;"></i>
                    </div>
                    <h3 style="color: var(--white); margin-bottom: 1rem;">Scan. Access. Care.</h3>
                    <p class="hero-subtitle">Instant access to pet medical records through QR technology for faster, smarter healthcare decisions.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- About Section -->
<section id="about" class="section about-section">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6" data-aos="fade-right">
                <h2 class="section-title text-start">About VetCare</h2>
                <p class="lead mb-4">Transforming pet healthcare through innovative technology and compassionate care.</p>
                <p class="mb-4">VetCare is a comprehensive pet healthcare management system that leverages QR code technology and predictive analytics to provide seamless medical record access and proactive health monitoring.</p>
                <p class="mb-5">Our mission is to bridge the gap between pet owners, veterinarians, and emergency care providers through instant, secure access to vital medical information when it matters most.</p>
                
                <div class="stats">
                    <div class="stat-item" data-aos="fade-up" data-aos-delay="100">
                        <div class="stat-number">10K+</div>
                        <div class="stat-label">Pets Protected</div>
                    </div>
                    <div class="stat-item" data-aos="fade-up" data-aos-delay="200">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Veterinarians</div>
                    </div>
                    <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                        <div class="stat-number">99.9%</div>
                        <div class="stat-label">Uptime</div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6" data-aos="fade-left">
                <div class="about-content">
                    <h4 class="mb-4">Why Choose VetCare?</h4>
                    <div class="research-card mb-3">
                        <h5><i class="bi bi-lightning-fill text-warning me-2"></i>Instant Access</h5>
                        <p class="mb-0">QR codes provide immediate access to medical records during emergencies.</p>
                    </div>
                    <div class="research-card mb-3">
                        <h5><i class="bi bi-shield-check text-primary me-2"></i>Secure & Private</h5>
                        <p class="mb-0">Military-grade encryption ensures your pet's data remains confidential.</p>
                    </div>
                    <div class="research-card">
                        <h5><i class="bi bi-graph-up-arrow text-success me-2"></i>Smart Analytics</h5>
                        <p class="mb-0">Predictive algorithms help prevent health issues before they occur.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features section -->
<section id="features" class="section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">How VetCare Works</h2>
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
                    <h4>Identify Pet Health Issues with a Photo</h4>
                    <p>Get instant insights on potential skin conditions, eye problems, and more with our AI-driven image analysis.</p>
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
                    <h5>Data Security & Privacy</h5>
                    <p>Implemented robust encryption protocols and access controls to ensure pet medical data remains secure while maintaining accessibility for authorized veterinarians.</p>
                </div>
            </div>
            <div class="col-md-6" data-aos="fade-up" data-aos-delay="400">
                <div class="research-card">
                    <h5>Predictive Analytics</h5>
                    <p>Our system analyzes vaccination schedules, breed-specific health risks, and regional disease patterns to provide proactive healthcare recommendations.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Team Section -->
<section id="team" class="section team-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Meet Our Team</h2>
            <p class="section-subtitle">The passionate professionals dedicated to revolutionizing pet healthcare</p>
        </div>
        
        <div class="row g-4">
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                <div class="team-card">
                    <img src="images/aira.jpg" alt="Ms. Aira L. Alimorom" class="team-img">
                    <div class="team-content">
                        <h4>Ms. Aira L. Alimorom</h4>
                        <p class="team-role">Project Manager and Programmer</p>
                        <p>Lead developer and project coordinator with expertise in system architecture and QR technology implementation.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="team-card">
                    <img src="images/alex.jpg" alt="Prof. Alexander G. Avendaño" class="team-img">
                    <div class="team-content">
                        <h4>Prof. Alexander G. Avendaño</h4>
                        <p class="team-role">Advisory</p>
                        <p>Provides strategic guidance and industry expertise to ensure project success and innovation.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="team-card">
                    <img src="images/red.jpg" alt="Ms. Regina R. Narbarte" class="team-img">
                    <div class="team-content">
                        <h4>Ms. Regina R. Narbarte</h4>
                        <p class="team-role">Document Lead</p>
                        <p>Oversees project documentation, research compilation, and ensures comprehensive project records.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- FAQ Section -->
<section id="faq" class="section faq-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Frequently Asked Questions</h2>
            <p class="section-subtitle">Find answers to common questions about using VetCare for your pet's healthcare needs</p>
        </div>

        <div class="faq-categories" data-aos="fade-up" data-aos-delay="100">
            <button class="faq-category-btn active" data-category="all">
                <i class="bi bi-grid-fill"></i>All Questions
            </button>
            <button class="faq-category-btn" data-category="getting-started">
                <i class="bi bi-rocket-takeoff"></i>Getting Started
            </button>
            <button class="faq-category-btn" data-category="qr-codes">
                <i class="bi bi-qr-code"></i>QR Codes
            </button>
            <button class="faq-category-btn" data-category="privacy">
                <i class="bi bi-shield-check"></i>Privacy & Security
            </button>
        </div>

        <div class="faq-container">
            <!-- Getting Started FAQs -->
            <div class="faq-item" data-category="getting-started" data-aos="fade-up" data-aos-delay="200">
                <button class="faq-question">
                    How do I create an account for my pet?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Creating an account for your pet is simple:</p>
                        <ul>
                            <li>Click "Get Started Free" on our homepage</li>
                            <li>Select "Pet Owner" as your role</li>
                            <li>Fill in your basic information</li>
                            <li>Add your pet's details (name, breed, age, etc.)</li>
                            <li>Upload any existing medical records</li>
                            <li>You'll receive your pet's unique QR code</li>
                        </ul>
                        <p>The entire process takes less than 5 minutes!</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="getting-started" data-aos="fade-up" data-aos-delay="250">
                <button class="faq-question">
                    What information do I need to set up my pet's profile?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>To create a comprehensive pet profile, we recommend having:</p>
                        <ul>
                            <li>Basic information: Name, breed, age, weight, gender</li>
                            <li>Medical history: Previous illnesses, surgeries, chronic conditions</li>
                            <li>Vaccination records: Dates and types of vaccinations</li>
                            <li>Medication details: Current medications and dosages</li>
                            <li>Allergy information: Any known allergies or sensitivities</li>
                            <li>Emergency contact: Your veterinarian's contact information</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- QR Code FAQs -->
            <div class="faq-item" data-category="qr-codes" data-aos="fade-up" data-aos-delay="300">
                <button class="faq-question">
                    How do the QR codes work for pet identification?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Our QR code system provides instant access to your pet's medical information:</p>
                        <ul>
                            <li>Each pet receives a unique, secure QR code</li>
                            <li>The QR code can be printed and attached to your pet's collar</li>
                            <li>When scanned by a veterinarian or emergency responder, it shows critical medical information</li>
                            <li>Access is secure - only authorized professionals can view full medical history</li>
                            <li>You can update information anytime, and the QR code automatically reflects changes</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="qr-codes" data-aos="fade-up" data-aos-delay="350">
                <button class="faq-question">
                    What happens if my pet's QR code gets lost or damaged?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Don't worry! We've got you covered:</p>
                        <ul>
                            <li>You can instantly deactivate the lost QR code from your dashboard</li>
                            <li>Generate a new QR code immediately - it's free and takes seconds</li>
                            <li>The old QR code will no longer work once deactivated</li>
                            <li>We recommend printing multiple copies and keeping spares</li>
                            <li>Consider using waterproof QR code tags for durability</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Privacy & Security FAQs -->
            <div class="faq-item" data-category="privacy" data-aos="fade-up" data-aos-delay="400">
                <button class="faq-question">
                    Is my pet's medical information secure and private?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Yes, security and privacy are our top priorities:</p>
                        <ul>
                            <li>All data is encrypted using military-grade encryption</li>
                            <li>We comply with healthcare data protection regulations</li>
                            <li>Only authorized veterinarians can access full medical records</li>
                            <li>Emergency responders see only critical information (allergies, medications, etc.)</li>
                            <li>You control what information is visible to whom</li>
                            <li>Regular security audits and updates ensure ongoing protection</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="privacy" data-aos="fade-up" data-aos-delay="450">
                <button class="faq-question">
                    Who can access my pet's medical information?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Access is carefully controlled through a tiered system:</p>
                        <ul>
                            <li><strong>You (Owner):</strong> Full access to all information</li>
                            <li><strong>Primary Veterinarian:</strong> Full medical record access</li>
                            <li><strong>Emergency Clinics:</strong> Critical information only during emergencies</li>
                            <li><strong>Pet Sitters/Walkers:</strong> Basic info and emergency contacts only (if you choose to share)</li>
                            <li><strong>General Public:</strong> No access - QR scanning requires professional verification</li>
                        </ul>
                        <p>You can modify these permissions anytime in your settings.</p>
                    </div>
                </div>
            </div>

            <!-- Additional FAQs -->
            <div class="faq-item" data-category="getting-started" data-aos="fade-up" data-aos-delay="500">
                <button class="faq-question">
                    How much does VetCare cost?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>VetCare offers flexible pricing options:</p>
                        <ul>
                            <li><strong>Basic Plan:</strong> Free - Includes one pet profile, QR code, and basic health tracking</li>
                            <li><strong>Premium Plan:</strong> $9.99/month - Unlimited pets, advanced analytics, priority support</li>
                            <li><strong>Veterinarian Plan:</strong> $49.99/month - Clinic management, multiple staff accounts</li>
                            <li><strong>Enterprise Plan:</strong> Custom pricing - For large clinics and hospitals</li>
                        </ul>
                        <p>All plans include our core features: QR codes, emergency access, and secure data storage.</p>
                    </div>
                </div>
            </div>

            <div class="faq-item" data-category="qr-codes" data-aos="fade-up" data-aos-delay="550">
                <button class="faq-question">
                    Can multiple pets use the same account?
                    <div class="faq-icon">
                        <i class="bi bi-chevron-down"></i>
                    </div>
                </button>
                <div class="faq-answer">
                    <div class="faq-answer-content">
                        <p>Absolutely! VetCare is designed for multi-pet households:</p>
                        <ul>
                            <li>Free accounts support up to 2 pets</li>
                            <li>Premium accounts support unlimited pets</li>
                            <li>Each pet gets their own unique QR code</li>
                            <li>Manage all pets from a single dashboard</li>
                            <li>Set different privacy settings for each pet</li>
                            <li>Track medications and appointments separately</li>
                        </ul>
                        <p>Perfect for families with multiple furry friends!</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="faq-contact-cta" data-aos="fade-up" data-aos-delay="600">
            <h4>Still have questions?</h4>
            <p>Can't find the answer you're looking for? Our support team is here to help you get the most out of VetCare.</p>
            <a href="#contact" class="btn btn-primary">
                <i class="bi bi-envelope me-2"></i>Contact Support
            </a>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="section contact-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Have questions about VetCare? We'd love to hear from you.</p>
        </div>
        
        <div class="row">
            <div class="col-lg-4 mb-4" data-aos="fade-right">
                <div class="contact-info">
                    <h4 class="mb-4">Contact Information</h4>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-envelope"></i>
                        </div>
                        <div>
                            <h5>Email Us</h5>
                            <p class="text-muted mb-0">support@vetcare.com</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-telephone"></i>
                        </div>
                        <div>
                            <h5>Call Us</h5>
                            <p class="text-muted mb-0">+1 (555) 123-4567</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-clock"></i>
                        </div>
                        <div>
                            <h5>Business Hours</h5>
                            <p class="text-muted mb-0">Monday - Friday: 9:00 AM - 6:00 PM EST</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="bi bi-geo-alt"></i>
                        </div>
                        <div>
                            <h5>Visit Us</h5>
                            <p class="text-muted mb-0">123 Pet Care Ave, Suite 100<br>New York, NY 10001</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8" data-aos="fade-left">
                <div class="contact-form">
                    <h4 class="mb-4">Send us a Message</h4>
                    <form>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Full Name</label>
                                <input type="text" class="form-control" placeholder="Your name" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email Address</label>
                                <input type="email" class="form-control" placeholder="Your email" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Subject</label>
                                <input type="text" class="form-control" placeholder="Subject of your message" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Message</label>
                                <textarea class="form-control" rows="5" placeholder="How can we help you?" required></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="bi bi-send me-2"></i>Send Message
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer>
    <div class="container">
        <div class="row">
            <div class="col-lg-4 mb-4 mb-lg-0">
                <h4 class="mb-3"><i class="bi bi-qr-code me-2"></i>VetCare</h4>
                <p class="text-muted">Revolutionizing pet healthcare through QR technology and predictive analytics for a healthier pet community.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="text-primary"><i class="bi bi-facebook" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-primary"><i class="bi bi-twitter" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-primary"><i class="bi bi-instagram" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-primary"><i class="bi bi-linkedin" style="font-size: 1.2rem;"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#about" class="text-muted text-decoration-none">About</a></li>
                    <li class="mb-2"><a href="#features" class="text-muted text-decoration-none">Features</a></li>
                    <li class="mb-2"><a href="#research" class="text-muted text-decoration-none">Research</a></li>
                    <li class="mb-2"><a href="#faq" class="text-muted text-decoration-none">FAQ</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Support</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Help Center</a></li>
                    <li class="mb-2"><a href="#faq" class="text-muted text-decoration-none">FAQs</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-4">
                <h5 class="mb-3">Newsletter</h5>
                <p class="text-muted mb-3">Subscribe to get updates on new features and pet healthcare tips.</p>
                <div class="input-group">
                    <input type="email" class="form-control" placeholder="Your email address">
                    <button class="btn btn-primary" type="button">Subscribe</button>
                </div>
            </div>
        </div>
        <hr class="my-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-muted mb-0">© 2023 VetCare. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-0">Made with <i class="bi bi-heart-fill text-primary"></i> for pets everywhere</p>
            </div>
        </div>
    </div>
</footer>

<!-- Scroll to top button -->
<div class="scroll-top" id="scrollTop">
    <i class="bi bi-chevron-up"></i>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script>
    // Initialize AOS
    document.addEventListener('DOMContentLoaded', function() {
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });

        // Hero Slideshow
        let currentSlide = 0;
        const slides = document.querySelectorAll('.slide');
        const dots = document.querySelectorAll('.slide-dot');
        
        function showSlide(n) {
            slides.forEach(slide => slide.classList.remove('active'));
            dots.forEach(dot => dot.classList.remove('active'));
            currentSlide = (n + slides.length) % slides.length;
            slides[currentSlide].classList.add('active');
            dots[currentSlide].classList.add('active');
        }
        
        function nextSlide() {
            showSlide(currentSlide + 1);
        }
        
        function prevSlide() {
            showSlide(currentSlide - 1);
        }
        
        function goToSlide(n) {
            showSlide(n);
        }
        
        // Auto-advance slides
        setInterval(nextSlide, 5000);

        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 100) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }

            // Scroll to top button
            const scrollTopBtn = document.getElementById('scrollTop');
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.add('active');
            } else {
                scrollTopBtn.classList.remove('active');
            }
        });

        // Scroll to top functionality
        document.getElementById('scrollTop').addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });

        // Smooth scrolling for anchor links
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

        // FAQ Functionality
        // FAQ Accordion
        const faqQuestions = document.querySelectorAll('.faq-question');
        
        faqQuestions.forEach(question => {
            question.addEventListener('click', function() {
                const answer = this.nextElementSibling;
                const isActive = this.classList.contains('active');
                
                // Close all other FAQs
                faqQuestions.forEach(q => {
                    q.classList.remove('active');
                    q.nextElementSibling.classList.remove('active');
                });
                
                // Toggle current FAQ
                if (!isActive) {
                    this.classList.add('active');
                    answer.classList.add('active');
                }
            });
        });

        // FAQ Category Filtering
        const categoryButtons = document.querySelectorAll('.faq-category-btn');
        const faqItems = document.querySelectorAll('.faq-item');

        categoryButtons.forEach(button => {
            button.addEventListener('click', function() {
                const category = this.getAttribute('data-category');
                
                // Update active category button
                categoryButtons.forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');
                
                // Filter FAQ items
                faqItems.forEach(item => {
                    const itemCategory = item.getAttribute('data-category');
                    
                    if (category === 'all' || itemCategory === category) {
                        item.style.display = 'block';
                        // Add slight delay for smooth animation
                        setTimeout(() => {
                            item.style.opacity = '1';
                            item.style.transform = 'translateY(0)';
                        }, 50);
                    } else {
                        item.style.opacity = '0';
                        item.style.transform = 'translateY(10px)';
                        setTimeout(() => {
                            item.style.display = 'none';
                        }, 300);
                    }
                });
            });
        });
    });

    // Role Selection Functions
    let selectedRole = null;

    function showRoleModal() {
        document.getElementById('roleModal').classList.add('active');
        
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
            // Redirect pet owners to login.php
            window.location.href = 'login.php';
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
        
        // Get form values
        const licenseNumber = document.getElementById('licenseNumber').value;
        const clinicName = document.getElementById('clinicName').value;
        const adminCode = document.getElementById('adminCode') ? document.getElementById('adminCode').value : '';
        
        // Basic validation
        if (!licenseNumber || !clinicName) {
            alert('Please fill in all required fields.');
            return;
        }
        
        if (selectedRole === 'admin' && !adminCode) {
            alert('Please enter the administrator access code.');
            return;
        }
        
        // Redirect based on role after verification
        if (selectedRole === 'veterinarian') {
            // Redirect to veterinarian login
            window.location.href = 'login_vet.php';
        } else if (selectedRole === 'admin') {
            // Redirect to admin login
            window.location.href = 'login_admin.php';
        }
    }

    function backToRoleSelection() {
        document.getElementById('verificationModal').classList.remove('active');
        showRoleModal();
    }

    function hideVerificationModal() {
        document.getElementById('verificationModal').classList.remove('active');
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
