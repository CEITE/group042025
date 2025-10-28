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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root{
            --pink:#ffd6e7;
            --pink-2:#f7c5e0;
            --pink-dark:#bf3b78;
            --pink-darker:#8c2859;
            --pink-gradient: linear-gradient(135deg, #ff6b9d 0%, #bf3b78 100%);
            --ink:#2a2e34;
            --ink-light: #5d6370;
            --white: #ffffff;
            --bg-light: #fef9fb;
            --hi:#ff6b6b;     /* high risk */
            --med:#ffa94d;    /* medium risk */
            --low:#74c69d;    /* low risk */
            --muted:#e9ecef;  /* no data */
            --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 10px 30px rgba(0, 0, 0, 0.12);
            --shadow-lg: 0 20px 60px rgba(191, 59, 120, 0.15);
            --border-radius: 16px;
            --border-radius-lg: 24px;
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
            background: var(--pink-dark);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--pink-darker);
        }

        /* Improved Navigation */
        .navbar{
            background: rgba(255, 248, 252, 0.98);
            border-bottom: 1px solid rgba(241, 230, 240, 0.8); 
            padding: 1rem 0;
            backdrop-filter: blur(20px);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 1px 20px rgba(191, 59, 120, 0.08);
            z-index: 1000;
        }

        .navbar.scrolled {
            padding: 0.7rem 0;
            background: rgba(255, 248, 252, 0.95);
            box-shadow: 0 4px 30px rgba(191, 59, 120, 0.1);
        }

        .navbar-brand {
            font-weight: 800;
            color: var(--pink-dark);
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
            color: var(--pink-dark);
        }

        .navbar .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--pink-gradient);
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
            background: linear-gradient(135deg, var(--bg-light) 0%, #fff5f9 100%);
        }

        .hero::before {
            content: "";
            position: absolute;
            top: -50%;
            right: -10%;
            width: 600px;
            height: 600px;
            background: var(--pink-gradient);
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
            background: var(--pink-gradient);
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
            filter: brightness(0.5);
        }

        .slide.active {
            opacity: 1;
            transform: scale(1);
            filter: brightness(0.6);
        }

        .slide-1 {
            background-image: url('https://www.google.com/url?sa=i&url=https%3A%2F%2Fstock.adobe.com%2Fsearch%2Fimages%3Fk%3Dcats%2Band%2Bdogs&psig=AOvVaw1bHbcctifXyZ0jrr0XPjpu&ust=1761712049385000&source=images&cd=vfe&opi=89978449&ved=0CBUQjRxqFwoTCNCzkP6GxpADFQAAAAAdAAAAABAE');
        }

        .slide-2 {
            background-image: url('https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2043&q=80');
        }

        .slide-3 {
            background-image: url('https://images.unsplash.com/photo-1552053831-71594a27632d?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2062&q=80');
        }

        .slide-4 {
            background-image: url('https://images.unsplash.com/photo-1583337130417-3346a1be7dee?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=1964&q=80');
        }

        .slide-5 {
            background-image: url('https://images.unsplash.com/photo-1574145112594-3e0b8a483d0c?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80');
        }

        .slide-6 {
            background-image: url('https://images.unsplash.com/photo-1583511655826-05700d52f4c9?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2068&q=80');
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
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .hero-subtitle {
            font-size: 1.3rem;
            color: var(--white);
            margin-bottom: 2.5rem;
            line-height: 1.7;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            font-weight: 400;
        }

        .hero-badge {
            background: rgba(255, 255, 255, 0.95);
            color: var(--pink-dark);
            font-weight: 600;
            padding: 0.8rem 2rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(191, 59, 120, 0.2);
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

        .btn-pink{
            background: var(--pink-gradient);
            color: var(--white);
            box-shadow: 0 8px 25px rgba(191, 59, 120, 0.3);
        }

        .btn-pink:hover{
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(191, 59, 120, 0.4);
        }

        .btn-outline-pink{
            color: var(--white);
            border: 2px solid var(--white);
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
        }

        .btn-outline-pink:hover{
            background: var(--white);
            color: var(--pink-dark);
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
            background: var(--pink-gradient);
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
            background: rgba(255, 248, 252, 0.98);
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
            background: var(--pink-gradient);
        }

        .role-title {
            font-weight: 800;
            color: var(--pink-dark);
            margin-bottom: 1rem;
            font-size: 2.8rem;
            background: var(--pink-gradient);
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
            border: 2px solid #f8f0f5;
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
            background: var(--pink-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .role-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--pink-dark);
            box-shadow: var(--shadow-lg);
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-card.active {
            border-color: var(--pink-dark);
            background: linear-gradient(135deg, #fff8fc, #ffeaf3);
            transform: translateY(-5px);
            box-shadow: var(--shadow-md);
        }

        .role-card.active::before {
            transform: scaleX(1);
        }

        .role-icon {
            width: 90px;
            height: 90px;
            background: rgba(191, 59, 120, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--pink-dark);
            font-size: 2.8rem;
            transition: all 0.4s ease;
            position: relative;
        }

        .role-icon::after {
            content: '';
            position: absolute;
            inset: -5px;
            background: var(--pink-gradient);
            border-radius: 25px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .role-card:hover .role-icon {
            background: var(--pink-gradient);
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
            color: var(--pink-dark);
            transform: translateX(5px);
        }

        .role-features li i {
            color: var(--pink-dark);
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .role-features li:hover i {
            transform: scale(1.2);
        }

        .role-continue-btn {
            background: var(--pink-gradient);
            color: var(--white);
            border: none;
            border-radius: 50px;
            padding: 1.2rem 4rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            opacity: 0.7;
            cursor: not-allowed;
            box-shadow: 0 8px 25px rgba(191, 59, 120, 0.3);
            position: relative;
            overflow: hidden;
        }

        .role-continue-btn.active {
            opacity: 1;
            cursor: pointer;
        }

        .role-continue-btn.active:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(191, 59, 120, 0.4);
        }

        /* Enhanced Verification Modal */
        .verification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 248, 252, 0.98);
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
            background: var(--pink-gradient);
        }

        .verification-title {
            font-weight: 800;
            color: var(--pink-dark);
            margin-bottom: 1rem;
            font-size: 2.2rem;
            background: var(--pink-gradient);
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
            border-color: var(--pink-dark);
            box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        .verification-badge {
            background: rgba(191, 59, 120, 0.1);
            color: var(--pink-dark);
            padding: 1rem 2rem;
            border-radius: 50px;
            display: inline-flex;
            align-items: center;
            gap: 0.8rem;
            margin-bottom: 2.5rem;
            font-weight: 600;
            font-size: 1rem;
            border: 1px solid rgba(191, 59, 120, 0.2);
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
            background: var(--pink-gradient);
            color: var(--white);
            border: none;
            border-radius: 50px;
            padding: 1rem 2.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            box-shadow: 0 8px 25px rgba(191, 59, 120, 0.3);
        }

        .btn-verify:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(191, 59, 120, 0.4);
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
            background: linear-gradient(135deg, var(--ink) 0%, var(--pink-dark) 100%);
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
            background: var(--pink-gradient);
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
            background: rgba(191, 59, 120, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: var(--pink-dark);
            font-size: 2.5rem;
            transition: all 0.4s ease;
        }

        .feature-card:hover .feature-icon-wrapper {
            background: var(--pink-gradient);
            color: var(--white);
            transform: scale(1.1) rotate(5deg);
        }

        /* Enhanced Research Section */
        .research-section {
            background: linear-gradient(135deg, #faf5f8 0%, #fff 100%);
            position: relative;
        }

        .research-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--pink-gradient);
        }

        .research-card {
            background: var(--white);
            border-radius: var(--border-radius);
            padding: 2.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--pink-dark);
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
            background: linear-gradient(90deg, transparent, rgba(191, 59, 120, 0.05), transparent);
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
            color: var(--pink-dark);
            margin-bottom: 1.2rem;
            font-weight: 700;
            font-size: 1.3rem;
        }

        /* Enhanced Team Section */
        .team-section {
            background: linear-gradient(135deg, #fff8fc 0%, #fef5f9 100%);
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
            background: var(--pink-gradient);
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
            border-color: var(--pink-dark);
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
            color: var(--pink-dark);
            font-weight: 600;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        /* Enhanced Contact Section */
        .contact-section {
            background: linear-gradient(135deg, #fff8fc 0%, #ffeaf3 100%);
            position: relative;
        }

        .contact-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--pink-gradient);
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
            background: rgba(191, 59, 120, 0.05);
            transform: translateX(5px);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: var(--pink-gradient);
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
            background: linear-gradient(135deg, #faf5f8 0%, #fff 100%);
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
            background: var(--pink-gradient);
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
            background: rgba(191, 59, 120, 0.05);
            border-radius: var(--border-radius);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            background: rgba(191, 59, 120, 0.1);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--pink-dark);
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
            background: var(--pink-gradient);
        }

        footer .text-muted {
            color: #a0a4a8 !important;
        }

        footer .text-pink-dark {
            color: var(--pink) !important;
        }

        /* Enhanced Scroll to top button */
        .scroll-top {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--pink-gradient);
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
            box-shadow: 0 15px 40px rgba(191, 59, 120, 0.4);
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
            border-color: var(--pink-dark);
            box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.1);
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

        .text-pink-dark {
            color: var(--pink-dark) !important;
        }

        /* Loading Animation */
        .loading-spinner {
            display: none;
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--pink-dark);
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

<!-- Navigation -->
<nav class="navbar navbar-expand-lg sticky-top">
    <div class="container">
        <a class="navbar-brand" href="#">
            <i class="bi bi-qr-code"></i>VetCareQR
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
        <div class="slide slide-5"></div>
        <div class="slide slide-6"></div>
        
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
            <div class="slide-dot" onclick="goToSlide(4)"></div>
            <div class="slide-dot" onclick="goToSlide(5)"></div>
        </div>
    </div>
    
    <div class="container">
        <div class="row align-items-center">
            <div class="col-lg-6 hero-content" data-aos="fade-right">
                <span class="hero-badge"><i class="bi bi-star-fill me-2"></i>Modern Pet Healthcare</span>
                <h1>Smart Pet Healthcare Management with <span class="text-pink-dark">QR Technology</span></h1>
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
                <h2 class="section-title text-start">About VetCareQR</h2>
                <p class="lead mb-4">Transforming pet healthcare through innovative technology and compassionate care.</p>
                <p class="mb-4">VetCareQR is a comprehensive pet healthcare management system that leverages QR code technology and predictive analytics to provide seamless medical record access and proactive health monitoring.</p>
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
                    <h4 class="mb-4">Why Choose VetCareQR?</h4>
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
                    <img src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Dr. Sarah Johnson" class="team-img">
                    <div class="team-content">
                        <h4>Dr. Sarah Johnson</h4>
                        <p class="team-role">Lead Veterinarian & Project Manager</p>
                        <p>DVM with 12+ years of clinical experience and passion for veterinary technology innovation.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                <div class="team-card">
                    <img src="https://images.unsplash.com/photo-1560250097-0b93528c311a?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Michael Chen" class="team-img">
                    <div class="team-content">
                        <h4>Michael Chen</h4>
                        <p class="team-role">Data Scientist & ML Engineer</p>
                        <p>PhD in Computer Science specializing in predictive analytics and machine learning applications.</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                <div class="team-card">
                    <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=M3wxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8fA%3D%3D&auto=format&fit=crop&w=2070&q=80" alt="Emily Rodriguez" class="team-img">
                    <div class="team-content">
                        <h4>Emily Rodriguez</h4>
                        <p class="team-role">Full-Stack Developer</p>
                        <p>Software engineer with expertise in web applications, QR technology, and database management.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Contact Section -->
<section id="contact" class="section contact-section">
    <div class="container">
        <div class="text-center mb-5" data-aos="fade-up">
            <h2 class="section-title">Get In Touch</h2>
            <p class="section-subtitle">Have questions about VetCareQR? We'd love to hear from you.</p>
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
                            <p class="text-muted mb-0">support@vetcareqr.com</p>
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
                                <button type="submit" class="btn btn-pink w-100">
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
                <h4 class="mb-3"><i class="bi bi-qr-code me-2"></i>VetCareQR</h4>
                <p class="text-muted">Revolutionizing pet healthcare through QR technology and predictive analytics for a healthier pet community.</p>
                <div class="d-flex gap-3">
                    <a href="#" class="text-pink-dark"><i class="bi bi-facebook" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-pink-dark"><i class="bi bi-twitter" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-pink-dark"><i class="bi bi-instagram" style="font-size: 1.2rem;"></i></a>
                    <a href="#" class="text-pink-dark"><i class="bi bi-linkedin" style="font-size: 1.2rem;"></i></a>
                </div>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Quick Links</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Home</a></li>
                    <li class="mb-2"><a href="#about" class="text-muted text-decoration-none">About</a></li>
                    <li class="mb-2"><a href="#features" class="text-muted text-decoration-none">Features</a></li>
                    <li class="mb-2"><a href="#research" class="text-muted text-decoration-none">Research</a></li>
                </ul>
            </div>
            <div class="col-lg-2 col-md-4 mb-4 mb-md-0">
                <h5 class="mb-3">Support</h5>
                <ul class="list-unstyled">
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Help Center</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">FAQs</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Privacy Policy</a></li>
                    <li class="mb-2"><a href="#" class="text-muted text-decoration-none">Terms of Service</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-4">
                <h5 class="mb-3">Newsletter</h5>
                <p class="text-muted mb-3">Subscribe to get updates on new features and pet healthcare tips.</p>
                <div class="input-group">
                    <input type="email" class="form-control" placeholder="Your email address">
                    <button class="btn btn-pink" type="button">Subscribe</button>
                </div>
            </div>
        </div>
        <hr class="my-4">
        <div class="row align-items-center">
            <div class="col-md-6">
                <p class="text-muted mb-0">© 2023 VetCareQR. All rights reserved.</p>
            </div>
            <div class="col-md-6 text-md-end">
                <p class="text-muted mb-0">Made with <i class="bi bi-heart-fill text-pink-dark"></i> for pets everywhere</p>
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

