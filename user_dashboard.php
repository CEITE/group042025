<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PetMedQR - Pet Medical Records & QR Generator</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcode-generator/1.4.4/qrcode.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #bf3b78;
            --primary-light: #ffd6e7;
            --primary-dark: #8c2859;
            --secondary: #4a6cf7;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #2a2e34;
            --light: #f8f9fa;
            --gray: #6c757d;
            --radius: 16px;
            --radius-sm: 8px;
            --shadow: 0 4px 12px rgba(0,0,0,0.08);
            --shadow-lg: 0 10px 25px rgba(0,0,0,0.15);
            --transition: all 0.3s ease;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: #f5f7fb;
            color: var(--dark);
            line-height: 1.6;
        }
        
        /* Layout */
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: white;
            box-shadow: var(--shadow);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 0 0.5rem 1.5rem;
            border-bottom: 1px solid rgba(0,0,0,0.05);
            margin-bottom: 1.5rem;
        }
        
        .sidebar-brand i {
            font-size: 1.8rem;
            color: var(--primary);
            margin-right: 0.75rem;
        }
        
        .sidebar-brand h2 {
            font-weight: 800;
            font-size: 1.4rem;
            margin: 0;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .user-profile {
            text-align: center;
            padding: 1rem 0.5rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            background: var(--primary-light);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin: 0 auto 0.75rem;
            border: 3px solid white;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-name {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .user-role {
            font-size: 0.85rem;
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        .sidebar-nav {
            flex: 1;
        }
        
        .nav-item {
            margin-bottom: 0.5rem;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            padding: 0.85rem 1rem;
            border-radius: var(--radius-sm);
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .nav-link:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .sidebar-footer {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(0,0,0,0.05);
        }
        
        .logout-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            padding: 0.85rem;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            transition: var(--transition);
        }
        
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        
        .logout-btn i {
            margin-right: 0.5rem;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .header-title h1 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .header-title p {
            color: var(--gray);
            margin: 0;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .search-box {
            position: relative;
            width: 300px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            background: white;
            transition: var(--transition);
        }
        
        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(191, 59, 120, 0.1);
            outline: none;
        }
        
        .search-box i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--gray);
        }
        
        .date-display {
            text-align: right;
        }
        
        .current-date {
            font-weight: 600;
            color: var(--dark);
        }
        
        .current-time {
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.vaccination-due {
            border-left-color: var(--danger);
        }
        
        .stat-card.recent-visits {
            border-left-color: var(--secondary);
        }
        
        .stat-card.vaccinated {
            border-left-color: var(--success);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.5rem;
        }
        
        .stat-card .stat-icon {
            background: var(--primary-light);
            color: var(--primary);
        }
        
        .stat-card.vaccinated .stat-icon {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .stat-card.vaccination-due .stat-icon {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .stat-card.recent-visits .stat-icon {
            background: rgba(74, 108, 247, 0.15);
            color: var(--secondary);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: var(--gray);
            font-weight: 600;
        }
        
        /* Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: var(--radius);
            padding: 1.25rem 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow);
        }
        
        .alert-banner i {
            font-size: 1.8rem;
            margin-right: 1rem;
        }
        
        .alert-content h4 {
            margin: 0 0 0.25rem;
            font-weight: 700;
        }
        
        .alert-content p {
            margin: 0;
            opacity: 0.9;
        }
        
        /* Wellness Section */
        .section {
            background: white;
            border-radius: var(--radius);
            padding: 1.75rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .section-title {
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: var(--primary);
        }
        
        .section-subtitle {
            color: var(--gray);
            margin: 0;
        }
        
        /* Recommendation Cards */
        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.25rem;
        }
        
        .recommendation-card {
            border-radius: var(--radius-sm);
            padding: 1.25rem;
            border-left: 4px solid;
            transition: var(--transition);
            background: var(--light);
        }
        
        .recommendation-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }
        
        .recommendation-card.high {
            border-left-color: var(--danger);
        }
        
        .recommendation-card.medium {
            border-left-color: var(--warning);
        }
        
        .recommendation-card.low {
            border-left-color: var(--success);
        }
        
        .recommendation-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .recommendation-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 0.75rem;
            font-size: 1.1rem;
        }
        
        .recommendation-card.high .recommendation-icon {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .recommendation-card.medium .recommendation-icon {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning);
        }
        
        .recommendation-card.low .recommendation-icon {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .recommendation-title {
            font-weight: 700;
            margin: 0 0 0.25rem;
            font-size: 1.05rem;
        }
        
        .recommendation-pet {
            font-size: 0.85rem;
            color: var(--gray);
            margin: 0;
        }
        
        .recommendation-content {
            margin-bottom: 1rem;
        }
        
        .recommendation-message {
            margin-bottom: 0.75rem;
            color: var(--dark);
        }
        
        .recommendation-action {
            display: inline-flex;
            align-items: center;
            font-size: 0.85rem;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
        }
        
        .recommendation-action i {
            margin-left: 0.5rem;
            transition: var(--transition);
        }
        
        .recommendation-action:hover i {
            transform: translateX(3px);
        }
        
        /* Pet Cards */
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }
        
        .pet-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .pet-header {
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
        }
        
        .pet-info h3 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .pet-breed {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }
        
        .pet-status {
            display: inline-flex;
            align-items: center;
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-good {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }
        
        .status-warning {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning);
        }
        
        .status-bad {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .pet-avatar {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            background: white;
            box-shadow: var(--shadow);
            color: var(--primary);
        }
        
        .pet-body {
            padding: 1.25rem;
        }
        
        .pet-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.8rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark);
        }
        
        .pet-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.6rem 1.25rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }
        
        .btn-outline {
            background: transparent;
            border: 1px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
        }
        
        /* QR Code Section */
        .qr-section {
            text-align: center;
            padding: 2rem;
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
            border-radius: var(--radius);
            margin-bottom: 2rem;
        }
        
        .qr-section h3 {
            margin-bottom: 1rem;
            color: var(--dark);
        }
        
        .qr-section p {
            color: var(--gray);
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
        }
        
        /* Vaccination Timeline */
        .timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e2e8f0;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding: 1.25rem;
            background: var(--light);
            border-radius: var(--radius-sm);
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2rem;
            top: 1.5rem;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .timeline-item.due::before {
            background: var(--danger);
        }
        
        .timeline-item.upcoming::before {
            background: var(--warning);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }
        
        .timeline-pet {
            font-weight: 700;
            color: var(--dark);
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--danger);
        }
        
        .timeline-item.upcoming .timeline-date {
            color: var(--warning);
        }
        
        .timeline-vaccines {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }
        
        .vaccine-badge {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }
        
        .timeline-message {
            color: var(--gray);
            font-size: 0.9rem;
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .pets-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }
        
        @media (max-width: 992px) {
            .dashboard-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .sidebar-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }
            
            .nav-item {
                flex: 1;
                min-width: 140px;
            }
            
            .main-content {
                padding: 1.5rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .pets-grid {
                grid-template-columns: 1fr;
            }
            
            .recommendation-grid {
                grid-template-columns: 1fr;
            }
            
            .header-actions {
                flex-direction: column;
                gap: 1rem;
            }
            
            .search-box {
                width: 100%;
            }
            
            .date-display {
                text-align: left;
            }
        }
        
        /* Utilities */
        .text-primary { color: var(--primary) !important; }
        .text-success { color: var(--success) !important; }
        .text-warning { color: var(--warning) !important; }
        .text-danger { color: var(--danger) !important; }
        .text-dark { color: var(--dark) !important; }
        .text-gray { color: var(--gray) !important; }
        
        .mb-0 { margin-bottom: 0 !important; }
        .mb-1 { margin-bottom: 0.5rem !important; }
        .mb-2 { margin-bottom: 1rem !important; }
        .mb-3 { margin-bottom: 1.5rem !important; }
        
        .mt-0 { margin-top: 0 !important; }
        .mt-1 { margin-top: 0.5rem !important; }
        .mt-2 { margin-top: 1rem !important; }
        .mt-3 { margin-top: 1.5rem !important; }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-paw"></i>
                <h2>VetCareQR</h2>
            </div>
            
            <div class="user-profile">
                <div class="user-avatar">
                    <img src="https://i.pravatar.cc/150?img=32" alt="User Avatar">
                </div>
                <h3 class="user-name">Maria Santos</h3>
                <p class="user-role">Pet Owner</p>
            </div>
            
            <div class="sidebar-nav">
                <div class="nav-item">
                    <a href="#" class="nav-link active">
                        <i class="fas fa-gauge-high"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-paw"></i>
                        <span>My Pets</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-qrcode"></i>
                        <span>QR Codes</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-plus-circle"></i>
                        <span>Register Pet</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Appointments</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-file-medical"></i>
                        <span>Medical Records</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </a>
                </div>
                <div class="nav-item">
                    <a href="#" class="nav-link">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </a>
                </div>
            </div>
            
            <div class="sidebar-footer">
                <button class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </button>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="dashboard-header">
                <div class="header-title">
                    <h1>Good Morning, Maria! 👋</h1>
                    <p>Here's your pet health overview for today</p>
                </div>
                
                <div class="header-actions">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search pets, vaccines, vets...">
                    </div>
                    
                    <div class="date-display">
                        <div class="current-date">Monday, October 16, 2023</div>
                        <div class="current-time">10:30 AM</div>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-paw"></i>
                    </div>
                    <div class="stat-value">3</div>
                    <div class="stat-label">Registered Pets</div>
                </div>
                
                <div class="stat-card vaccinated">
                    <div class="stat-icon">
                        <i class="fas fa-syringe"></i>
                    </div>
                    <div class="stat-value">2</div>
                    <div class="stat-label">Vaccinated Pets</div>
                </div>
                
                <div class="stat-card vaccination-due">
                    <div class="stat-icon">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stat-value">1</div>
                    <div class="stat-label">Vaccination Due</div>
                </div>
                
                <div class="stat-card recent-visits">
                    <div class="stat-icon">
                        <i class="fas fa-stethoscope"></i>
                    </div>
                    <div class="stat-value">5</div>
                    <div class="stat-label">Recent Visits</div>
                </div>
            </div>
            
            <!-- Alert Banner -->
            <div class="alert-banner">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="alert-content">
                    <h4>Vaccination Alert!</h4>
                    <p>1 of your pets needs vaccination updates. Check the timeline below for details.</p>
                </div>
            </div>
            
            <!-- Wellness Recommendations -->
            <div class="section">
                <div class="section-header">
                    <div>
                        <h2 class="section-title"><i class="fas fa-heartbeat"></i> Health & Wellness Recommendations</h2>
                        <p class="section-subtitle">AI-powered insights for your pets' wellbeing</p>
                    </div>
                </div>
                
                <div class="recommendation-grid">
                    <div class="recommendation-card high">
                        <div class="recommendation-header">
                            <div class="recommendation-icon">
                                <i class="fas fa-syringe"></i>
                            </div>
                            <div>
                                <h3 class="recommendation-title">Vaccination Required</h3>
                                <p class="recommendation-pet">For: Buddy</p>
                            </div>
                        </div>
                        <div class="recommendation-content">
                            <p class="recommendation-message">Rabies and DHPP vaccinations are overdue. Schedule an appointment soon.</p>
                            <a href="#" class="recommendation-action">
                                Schedule vet appointment
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="recommendation-card medium">
                        <div class="recommendation-header">
                            <div class="recommendation-icon">
                                <i class="fas fa-weight-scale"></i>
                            </div>
                            <div>
                                <h3 class="recommendation-title">Weight Management</h3>
                                <p class="recommendation-pet">For: Luna</p>
                            </div>
                        </div>
                        <div class="recommendation-content">
                            <p class="recommendation-message">Your cat's weight may need adjustment for optimal health.</p>
                            <a href="#" class="recommendation-action">
                                Consult vet about diet
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                    
                    <div class="recommendation-card low">
                        <div class="recommendation-header">
                            <div class="recommendation-icon">
                                <i class="fas fa-sun"></i>
                            </div>
                            <div>
                                <h3 class="recommendation-title">Seasonal Alert</h3>
                                <p class="recommendation-pet">For: All Pets</p>
                            </div>
                        </div>
                        <div class="recommendation-content">
                            <p class="recommendation-message">Warmer months increase risk of parasites and heat-related issues.</p>
                            <a href="#" class="recommendation-action">
                                Update parasite prevention
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vaccination Timeline -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Vaccination Schedule</h2>
                </div>
                
                <div class="timeline">
                    <div class="timeline-item due">
                        <div class="timeline-header">
                            <div class="timeline-pet">Buddy (Dog)</div>
                            <div class="timeline-date">Due: Oct 10, 2023</div>
                        </div>
                        <div class="timeline-vaccines">
                            <span class="vaccine-badge">Rabies</span>
                            <span class="vaccine-badge">DHPP</span>
                        </div>
                        <p class="timeline-message">Vaccination overdue. Please schedule an appointment.</p>
                    </div>
                    
                    <div class="timeline-item upcoming">
                        <div class="timeline-header">
                            <div class="timeline-pet">Luna (Cat)</div>
                            <div class="timeline-date">Due: Nov 15, 2023</div>
                        </div>
                        <div class="timeline-vaccines">
                            <span class="vaccine-badge">FVRCP</span>
                        </div>
                        <p class="timeline-message">Next vaccination due in 30 days.</p>
                    </div>
                    
                    <div class="timeline-item">
                        <div class="timeline-header">
                            <div class="timeline-pet">Max (Dog)</div>
                            <div class="timeline-date">Due: Jan 5, 2024</div>
                        </div>
                        <div class="timeline-vaccines">
                            <span class="vaccine-badge">Bordetella</span>
                        </div>
                        <p class="timeline-message">Next vaccination due in 3 months.</p>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="qr-section">
                <h3>Manage Your Pets with Ease</h3>
                <p>Register your pets to track their medical records, generate QR codes, and receive personalized health recommendations.</p>
                <button class="btn btn-primary">
                    <i class="fas fa-plus-circle"></i>
                    Add New Pet
                </button>
            </div>
            
            <!-- Pets Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title"><i class="fas fa-paw"></i> Your Pets</h2>
                    <button class="btn btn-outline">
                        <i class="fas fa-plus"></i>
                        Add Pet
                    </button>
                </div>
                
                <div class="pets-grid">
                    <!-- Pet Card 1 -->
                    <div class="pet-card">
                        <div class="pet-header">
                            <div class="pet-info">
                                <h3>Buddy</h3>
                                <p class="pet-breed">Golden Retriever • 4 years</p>
                                <span class="pet-status status-bad">Vaccination Due</span>
                            </div>
                            <div class="pet-avatar">
                                <i class="fas fa-dog"></i>
                            </div>
                        </div>
                        <div class="pet-body">
                            <div class="pet-details">
                                <div class="detail-item">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value">Male</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Weight</span>
                                    <span class="detail-value">32 kg</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Visit</span>
                                    <span class="detail-value">Aug 15, 2023</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Vet</span>
                                    <span class="detail-value">Dr. Smith</span>
                                </div>
                            </div>
                            <div class="pet-actions">
                                <button class="btn btn-primary">
                                    <i class="fas fa-file-medical"></i>
                                    Records
                                </button>
                                <button class="btn btn-outline">
                                    <i class="fas fa-qrcode"></i>
                                    QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pet Card 2 -->
                    <div class="pet-card">
                        <div class="pet-header">
                            <div class="pet-info">
                                <h3>Luna</h3>
                                <p class="pet-breed">Siamese • 2 years</p>
                                <span class="pet-status status-good">Good Health</span>
                            </div>
                            <div class="pet-avatar">
                                <i class="fas fa-cat"></i>
                            </div>
                        </div>
                        <div class="pet-body">
                            <div class="pet-details">
                                <div class="detail-item">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value">Female</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Weight</span>
                                    <span class="detail-value">4.2 kg</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Visit</span>
                                    <span class="detail-value">Sep 5, 2023</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Vet</span>
                                    <span class="detail-value">Dr. Johnson</span>
                                </div>
                            </div>
                            <div class="pet-actions">
                                <button class="btn btn-primary">
                                    <i class="fas fa-file-medical"></i>
                                    Records
                                </button>
                                <button class="btn btn-outline">
                                    <i class="fas fa-qrcode"></i>
                                    QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pet Card 3 -->
                    <div class="pet-card">
                        <div class="pet-header">
                            <div class="pet-info">
                                <h3>Max</h3>
                                <p class="pet-breed">Labrador • 6 years</p>
                                <span class="pet-status status-good">Good Health</span>
                            </div>
                            <div class="pet-avatar">
                                <i class="fas fa-dog"></i>
                            </div>
                        </div>
                        <div class="pet-body">
                            <div class="pet-details">
                                <div class="detail-item">
                                    <span class="detail-label">Gender</span>
                                    <span class="detail-value">Male</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Weight</span>
                                    <span class="detail-value">28 kg</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Last Visit</span>
                                    <span class="detail-value">Jul 22, 2023</span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Vet</span>
                                    <span class="detail-value">Dr. Smith</span>
                                </div>
                            </div>
                            <div class="pet-actions">
                                <button class="btn btn-primary">
                                    <i class="fas fa-file-medical"></i>
                                    Records
                                </button>
                                <button class="btn btn-outline">
                                    <i class="fas fa-qrcode"></i>
                                    QR Code
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            document.querySelector('.current-date').textContent = now.toLocaleDateString('en-US', options);
            document.querySelector('.current-time').textContent = now.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateDateTime();
            setInterval(updateDateTime, 60000);
            
            // Add animation to cards on scroll
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };
            
            const observer = new IntersectionObserver(function(entries) {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = 1;
                        entry.target.style.transform = 'translateY(0)';
                    }
                });
            }, observerOptions);
            
            // Observe cards for animation
            document.querySelectorAll('.stat-card, .pet-card, .recommendation-card').forEach(card => {
                card.style.opacity = 0;
                card.style.transform = 'translateY(20px)';
                card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
