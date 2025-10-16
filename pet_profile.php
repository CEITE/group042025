<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCare Pro - Medical Records Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #d81b60;
            --primary-light: #ff5c8d;
            --primary-dark: #a00037;
            --secondary: #6a1b9a;
            --secondary-light: #9c4dcc;
            --accent: #ec407a;
            --light: #fce4ec;
            --dark: #4a0072;
            --success: #00c853;
            --warning: #ffab00;
            --danger: #ff1744;
            --gray: #90a4ae;
            --light-gray: #f5f5f5;
            --card-shadow: 0 10px 20px rgba(0, 0, 0, 0.08), 0 6px 6px rgba(0, 0, 0, 0.05);
            --hover-shadow: 0 15px 30px rgba(0, 0, 0, 0.12), 0 10px 10px rgba(0, 0, 0, 0.08);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fdf2f8 0%, #f3e5f5 100%);
            color: var(--dark);
            display: flex;
            min-height: 100vh;
            line-height: 1.6;
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
            color: white;
            padding: 25px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 5px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 10;
        }
        
        .logo {
            padding: 0 25px 25px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .logo i {
            background: rgba(255, 255, 255, 0.2);
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        
        .logo h1 {
            font-size: 24px;
            font-weight: 600;
            margin-left: 15px;
            letter-spacing: 0.5px;
        }
        
        .user-info {
            padding: 20px 25px;
            display: flex;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
        }
        
        .user-info img {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-details .name {
            font-weight: 500;
            font-size: 16px;
        }
        
        .user-details .role {
            font-size: 13px;
            background: rgba(255, 255, 255, 0.2);
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-block;
            margin-top: 5px;
        }
        
        .nav-links {
            flex: 1;
        }
        
        .nav-links a {
            display: flex;
            align-items: center;
            padding: 16px 25px;
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
            position: relative;
            overflow: hidden;
        }
        
        .nav-links a:hover, .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }
        
        .nav-links a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: white;
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        
        .nav-links a.active::before {
            transform: scaleY(1);
        }
        
        .nav-links a i {
            margin-right: 15px;
            width: 20px;
            text-align: center;
            font-size: 18px;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            padding: 30px;
            overflow-y: auto;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 35px;
        }
        
        .header h2 {
            font-size: 28px;
            font-weight: 600;
            color: var(--dark);
            position: relative;
            display: inline-block;
        }
        
        .header h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 50px;
            height: 4px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: white;
            border-radius: 30px;
            padding: 10px 20px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        
        .search-box:focus-within {
            box-shadow: var(--hover-shadow);
            transform: translateY(-2px);
        }
        
        .search-box input {
            border: none;
            outline: none;
            padding: 5px 10px;
            font-size: 15px;
            width: 220px;
            background: transparent;
        }
        
        .search-box i {
            color: var(--gray);
        }
        
        .notifications {
            position: relative;
            cursor: pointer;
            background: white;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }
        
        .notifications:hover {
            transform: translateY(-3px);
            box-shadow: var(--hover-shadow);
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            font-size: 11px;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-welcome {
            font-weight: 500;
            color: var(--dark);
            background: white;
            padding: 10px 20px;
            border-radius: 30px;
            box-shadow: var(--card-shadow);
        }
        
        /* Dashboard Cards */
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 35px;
        }
        
        .card {
            background-color: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(to right, var(--primary), var(--secondary));
        }
        
        .stat-card {
            display: flex;
            align-items: center;
        }
        
        .stat-card .icon {
            width: 70px;
            height: 70px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 20px;
            font-size: 28px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .icon.records {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
        }
        
        .icon.patients {
            background: linear-gradient(135deg, var(--secondary), var(--secondary-light));
            color: white;
        }
        
        .icon.updated {
            background: linear-gradient(135deg, var(--success), #64dd17);
            color: white;
        }
        
        .icon.pending {
            background: linear-gradient(135deg, var(--warning), #ffd600);
            color: white;
        }
        
        .stat-card .info h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .stat-card .info p {
            color: var(--gray);
            font-size: 15px;
            font-weight: 500;
        }
        
        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            color: var(--dark);
        }
        
        .section-title h3 {
            position: relative;
            padding-bottom: 10px;
        }
        
        .section-title h3::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 40px;
            height: 3px;
            background: var(--primary);
            border-radius: 2px;
        }
        
        .section-title a {
            font-size: 14px;
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }
        
        .section-title a:hover {
            color: var(--secondary);
            transform: translateX(3px);
        }
        
        /* Medical Records Section */
        .medical-records-section {
            margin-bottom: 35px;
        }
        
        .records-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .records-table th,
        .records-table td {
            padding: 18px 15px;
            text-align: left;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .records-table th {
            font-weight: 600;
            color: var(--gray);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid rgba(0, 0, 0, 0.08);
        }
        
        .records-table tr {
            transition: all 0.3s ease;
        }
        
        .records-table tr:hover {
            background-color: rgba(216, 27, 96, 0.03);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .update-btn {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(216, 27, 96, 0.3);
        }
        
        .update-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 27, 96, 0.4);
        }
        
        .delete-btn {
            background: linear-gradient(135deg, var(--danger), #ff5252);
            color: white;
            border: none;
            padding: 10px 18px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 10px rgba(255, 23, 68, 0.3);
            margin-left: 8px;
        }
        
        .delete-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 23, 68, 0.4);
        }
        
        /* Analytics Section */
        .analytics-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
            margin-top: 35px;
        }
        
        .chart-container {
            position: relative;
            height: 320px;
            margin-top: 15px;
        }
        
        .analytics-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .analytics-header h3 {
            font-size: 18px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .analytics-filter {
            display: flex;
            gap: 8px;
            background: var(--light-gray);
            padding: 5px;
            border-radius: 10px;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            background: transparent;
            border: none;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .filter-btn.active {
            background: white;
            color: var(--primary);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            border-radius: 16px;
            width: 800px;
            max-width: 95%;
            max-height: 90%;
            overflow-y: auto;
            padding: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: modalAppear 0.3s ease-out;
        }
        
        @keyframes modalAppear {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.9);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--light-gray);
        }
        
        .modal-header h3 {
            color: var(--dark);
            font-size: 22px;
            font-weight: 600;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 26px;
            cursor: pointer;
            color: var(--gray);
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .close-btn:hover {
            background: var(--light-gray);
            color: var(--dark);
        }
        
        .form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            flex: 1;
            margin-bottom: 0;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
            color: var(--dark);
        }
        
        .form-control {
            width: 100%;
            padding: 12px 18px;
            border: 1px solid var(--light-gray);
            border-radius: 10px;
            font-family: 'Poppins', sans-serif;
            font-size: 15px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(216, 27, 96, 0.1);
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 30px;
        }
        
        .btn {
            padding: 12px 28px;
            border-radius: 10px;
            font-size: 15px;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-light));
            color: white;
            border: none;
            box-shadow: 0 4px 10px rgba(216, 27, 96, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(216, 27, 96, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 1px solid var(--light-gray);
        }
        
        .btn-secondary:hover {
            background: var(--light-gray);
        }
        
        /* Toast Notification */
        .toast {
            position: fixed;
            bottom: 30px;
            right: 30px;
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1001;
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }
        
        .toast.success {
            border-left: 4px solid var(--success);
        }
        
        .toast.error {
            border-left: 4px solid var(--danger);
        }
        
        .toast-icon {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 12px;
        }
        
        .toast.success .toast-icon {
            background: var(--success);
        }
        
        .toast.error .toast-icon {
            background: var(--danger);
        }
        
        /* Loading Spinner */
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Responsive */
        @media (max-width: 1200px) {
            .analytics-section {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 80px;
            }
            
            .logo h1, .nav-links a span, .user-details {
                display: none;
            }
            
            .logo {
                justify-content: center;
                padding: 0 15px 25px;
            }
            
            .user-info {
                justify-content: center;
                padding: 20px 15px;
            }
            
            .user-info img {
                margin-right: 0;
            }
            
            .nav-links a {
                justify-content: center;
                padding: 16px 15px;
            }
            
            .nav-links a i {
                margin-right: 0;
            }
            
            .search-box input {
                width: 150px;
            }
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
                gap: 20px;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .analytics-filter {
                flex-wrap: wrap;
            }
            
            .modal-content {
                width: 95%;
                padding: 20px;
            }
            
            .form-row {
                flex-direction: column;
                gap: 0;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .toast {
                left: 20px;
                right: 20px;
                bottom: 20px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="logo">
            <i class="fas fa-paw"></i>
            <h1>VetCare Pro</h1>
        </div>
        
        <div class="user-info">
            <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?ixlib=rb-4.0.3&ixid=MnwxMjA3fDB8MHxwaG90by1wYWdlfHx8fGVufDB8fHx8&auto=format&fit=crop&w=200&q=80" alt="Dr. Emily Smith">
            <div class="user-details">
                <div class="name">Dr. Emily Smith</div>
                <div class="role">Veterinarian</div>
            </div>
        </div>
        
        <div class="nav-links">
            <a href="#" class="active"><i class="fas fa-th-large"></i> <span>Dashboard</span></a>
            <a href="#"><i class="fas fa-paw"></i> <span>Patients</span></a>
            <a href="#"><i class="fas fa-users"></i> <span>Clients</span></a>
            <a href="#"><i class="fas fa-file-medical"></i> <span>Medical Records</span></a>
            <a href="#"><i class="fas fa-chart-line"></i> <span>Analytics</span></a>
            <a href="#"><i class="fas fa-cog"></i> <span>Settings</span></a>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="header">
            <h2>Medical Records Dashboard</h2>
            <div class="header-actions">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search patients, records...">
                    <i class="fas fa-search"></i>
                </div>
                <div class="notifications">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="user-welcome">Welcome, Dr. Smith!</div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="dashboard-cards">
            <div class="card stat-card">
                <div class="icon records"><i class="fas fa-file-medical"></i></div>
                <div class="info">
                    <h3 id="totalRecords">0</h3>
                    <p>Total Records</p>
                </div>
            </div>
            <div class="card stat-card">
                <div class="icon patients"><i class="fas fa-paw"></i></div>
                <div class="info">
                    <h3 id="activePatients">0</h3>
                    <p>Active Patients</p>
                </div>
            </div>
            <div class="card stat-card">
                <div class="icon updated"><i class="fas fa-check-circle"></i></div>
                <div class="info">
                    <h3 id="updatedThisWeek">0</h3>
                    <p>Updated This Week</p>
                </div>
            </div>
            <div class="card stat-card">
                <div class="icon pending"><i class="fas fa-clock"></i></div>
                <div class="info">
                    <h3 id="pendingUpdates">0</h3>
                    <p>Pending Updates</p>
                </div>
            </div>
        </div>

        <!-- Medical Records Section -->
        <div class="card medical-records-section">
            <div class="section-title">
                <h3>Medical Records</h3>
                <div>
                    <button class="update-btn" onclick="openAddModal()">
                        <i class="fas fa-plus"></i> Add New Record
                    </button>
                    <a href="#">View All Records <i class="fas fa-arrow-right"></i></a>
                </div>
            </div>
            <table class="records-table">
                <thead>
                    <tr>
                        <th>Pet Name</th>
                        <th>Owner</th>
                        <th>Species</th>
                        <th>Last Service</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="recordsTableBody">
                    <!-- Records will be loaded here dynamically -->
                </tbody>
            </table>
        </div>

        <!-- Analytics Section -->
        <div class="analytics-section">
            <div class="card">
                <div class="analytics-header">
                    <h3>Patient Visits Trend</h3>
                    <div class="analytics-filter">
                        <button class="filter-btn active">Monthly</button>
                        <button class="filter-btn">Quarterly</button>
                        <button class="filter-btn">Yearly</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="visitTrendChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="analytics-header">
                    <h3>Common Health Issues</h3>
                    <div class="analytics-filter">
                        <button class="filter-btn active">Dogs</button>
                        <button class="filter-btn">Cats</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="healthIssuesChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="analytics-header">
                    <h3>Record Update Frequency</h3>
                    <div class="analytics-filter">
                        <button class="filter-btn active">Weekly</button>
                        <button class="filter-btn">Monthly</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="updateFrequencyChart"></canvas>
                </div>
            </div>
            <div class="card">
                <div class="analytics-header">
                    <h3>Patient Distribution</h3>
                    <div class="analytics-filter">
                        <button class="filter-btn active">By Species</button>
                        <button class="filter-btn">By Age</button>
                    </div>
                </div>
                <div class="chart-container">
                    <canvas id="patientDistributionChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Medical Record Modal -->
    <div id="updateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle">Update Medical Record</h3>
                <button class="close-btn" onclick="closeUpdateModal()">&times;</button>
            </div>
            <form id="medicalRecordForm">
                <input type="hidden" id="record_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="owner_id">Owner ID</label>
                        <input type="number" id="owner_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="owner_name">Owner Name</label>
                        <input type="text" id="owner_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="pet_id">Pet ID</label>
                        <input type="number" id="pet_id" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="pet_name">Pet Name</label>
                        <input type="text" id="pet_name" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="species">Species</label>
                        <select id="species" class="form-control" required>
                            <option value="">Select Species</option>
                            <option value="Dog">Dog</option>
                            <option value="Cat">Cat</option>
                            <option value="Bird">Bird</option>
                            <option value="Small Animal">Small Animal</option>
                            <option value="Reptile">Reptile</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="breed">Breed</label>
                        <input type="text" id="breed" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="color">Color</label>
                        <input type="text" id="color" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="sex">Sex</label>
                        <select id="sex" class="form-control">
                            <option value="">Select Sex</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="text" id="age" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="weight">Weight</label>
                        <input type="text" id="weight" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="weight_date">Weight Date</label>
                        <input type="date" id="weight_date" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select id="status" class="form-control">
                            <option value="">Select Status</option>
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                            <option value="Deceased">Deceased</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="microchip">Microchip</label>
                        <input type="text" id="microchip" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_date">Service Date</label>
                        <input type="date" id="service_date" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="service_time">Service Time</label>
                        <input type="time" id="service_time" class="form-control">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="service_type">Service Type</label>
                        <select id="service_type" class="form-control">
                            <option value="">Select Service Type</option>
                            <option value="Vaccination">Vaccination</option>
                            <option value="Checkup">Checkup</option>
                            <option value="Surgery">Surgery</option>
                            <option value="Dental">Dental</option>
                            <option value="Grooming">Grooming</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="veterinarian">Veterinarian</label>
                        <input type="text" id="veterinarian" class="form-control">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="service_description">Service Description</label>
                    <textarea id="service_description" class="form-control" placeholder="Enter service description..."></textarea>
                </div>
                
                <div class="form-group">
                    <label for="notes">Notes</label>
                    <textarea id="notes" class="form-control" placeholder="Enter any additional notes..."></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeUpdateModal()">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveRecordBtn" onclick="saveMedicalRecord()">
                        <span id="saveBtnText">Save Record</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast">
        <div class="toast-icon">
            <i class="fas fa-check"></i>
        </div>
        <div class="toast-message" id="toastMessage">Record saved successfully!</div>
    </div>

    <script>
        // API endpoints - replace with your actual backend endpoints
        const API_BASE = 'http://localhost/vetcare/api'; // Change to your API URL
        const ENDPOINTS = {
            records: `${API_BASE}/medical-records.php`,
            record: (id) => `${API_BASE}/medical-records.php?id=${id}`
        };

        // Show toast notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastMessage = document.getElementById('toastMessage');
            
            toastMessage.textContent = message;
            toast.className = `toast ${type}`;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Load records from database
        async function loadRecords() {
            try {
                const response = await fetch(ENDPOINTS.records);
                if (!response.ok) throw new Error('Failed to fetch records');
                
                const records = await response.json();
                displayRecords(records);
                updateStats(records);
            } catch (error) {
                console.error('Error loading records:', error);
                showToast('Error loading records', 'error');
                
                // Fallback to sample data for demo
                loadSampleData();
            }
        }

        // Display records in the table
        function displayRecords(records) {
            const tableBody = document.getElementById('recordsTableBody');
            tableBody.innerHTML = '';
            
            records.forEach(record => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${record.pet_name || 'N/A'}</td>
                    <td>${record.owner_name || 'N/A'}</td>
                    <td>${record.species || 'N/A'}</td>
                    <td>${record.service_date || 'N/A'}</td>
                    <td><span class="status ${record.status || 'Active'}">${record.status || 'Active'}</span></td>
                    <td>
                        <button class="update-btn" onclick="openUpdateModal(${record.record_id})">Update</button>
                        <button class="delete-btn" onclick="deleteRecord(${record.record_id})">Delete</button>
                    </td>
                `;
                tableBody.appendChild(row);
            });
        }

        // Update dashboard statistics
        function updateStats(records) {
            document.getElementById('totalRecords').textContent = records.length;
            
            const uniquePets = [...new Set(records.map(r => r.pet_id))].length;
            document.getElementById('activePatients').textContent = uniquePets;
            
            // Calculate records updated this week
            const oneWeekAgo = new Date();
            oneWeekAgo.setDate(oneWeekAgo.getDate() - 7);
            const updatedThisWeek = records.filter(r => {
                const serviceDate = new Date(r.service_date || r.generated_date);
                return serviceDate >= oneWeekAgo;
            }).length;
            document.getElementById('updatedThisWeek').textContent = updatedThisWeek;
            
            // Calculate pending updates (records without recent service)
            const pendingUpdates = records.filter(r => !r.service_date || new Date(r.service_date) < oneWeekAgo).length;
            document.getElementById('pendingUpdates').textContent = pendingUpdates;
        }

        // Open modal for updating a record
        async function openUpdateModal(recordId) {
            try {
                const response = await fetch(ENDPOINTS.record(recordId));
                if (!response.ok) throw new Error('Failed to fetch record');
                
                const record = await response.json();
                populateForm(record);
                document.getElementById('modalTitle').textContent = 'Update Medical Record';
                document.getElementById('updateModal').style.display = 'flex';
            } catch (error) {
                console.error('Error loading record:', error);
                showToast('Error loading record', 'error');
            }
        }

        // Open modal for adding a new record
        function openAddModal() {
            document.getElementById('modalTitle').textContent = 'Add New Medical Record';
            document.getElementById('medicalRecordForm').reset();
            document.getElementById('record_id').value = '';
            document.getElementById('updateModal').style.display = 'flex';
        }

        // Populate form with record data
        function populateForm(record) {
            document.getElementById('record_id').value = record.record_id || '';
            document.getElementById('owner_id').value = record.owner_id || '';
            document.getElementById('owner_name').value = record.owner_name || '';
            document.getElementById('pet_id').value = record.pet_id || '';
            document.getElementById('pet_name').value = record.pet_name || '';
            document.getElementById('species').value = record.species || '';
            document.getElementById('breed').value = record.breed || '';
            document.getElementById('color').value = record.color || '';
            document.getElementById('sex').value = record.sex || '';
            document.getElementById('dob').value = record.dob || '';
            document.getElementById('age').value = record.age || '';
            document.getElementById('weight').value = record.weight || '';
            document.getElementById('weight_date').value = record.weight_date || '';
            document.getElementById('status').value = record.status || '';
            document.getElementById('microchip').value = record.microchip || '';
            document.getElementById('service_date').value = record.service_date || '';
            document.getElementById('service_time').value = record.service_time || '';
            document.getElementById('service_type').value = record.service_type || '';
            document.getElementById('veterinarian').value = record.veterinarian || '';
            document.getElementById('service_description').value = record.service_description || '';
            document.getElementById('notes').value = record.notes || '';
        }

        // Close the modal
        function closeUpdateModal() {
            document.getElementById('updateModal').style.display = 'none';
        }

        // Save medical record (both new and updates)
        async function saveMedicalRecord() {
            const saveBtn = document.getElementById('saveRecordBtn');
            const saveBtnText = document.getElementById('saveBtnText');
            
            // Show loading state
            saveBtn.disabled = true;
            saveBtnText.innerHTML = '<div class="loading"></div> Saving...';
            
            try {
                const formData = new FormData();
                const recordId = document.getElementById('record_id').value;
                
                // Collect all form data
                const recordData = {
                    owner_id: document.getElementById('owner_id').value,
                    owner_name: document.getElementById('owner_name').value,
                    pet_id: document.getElementById('pet_id').value,
                    pet_name: document.getElementById('pet_name').value,
                    species: document.getElementById('species').value,
                    breed: document.getElementById('breed').value,
                    color: document.getElementById('color').value,
                    sex: document.getElementById('sex').value,
                    dob: document.getElementById('dob').value,
                    age: document.getElementById('age').value,
                    weight: document.getElementById('weight').value,
                    weight_date: document.getElementById('weight_date').value,
                    status: document.getElementById('status').value,
                    microchip: document.getElementById('microchip').value,
                    service_date: document.getElementById('service_date').value,
                    service_time: document.getElementById('service_time').value,
                    service_type: document.getElementById('service_type').value,
                    veterinarian: document.getElementById('veterinarian').value,
                    service_description: document.getElementById('service_description').value,
                    notes: document.getElementById('notes').value,
                    generated_date: new Date().toISOString().split('T')[0]
                };
                
                const url = recordId ? ENDPOINTS.record(recordId) : ENDPOINTS.records;
                const method = recordId ? 'PUT' : 'POST';
                
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(recordData)
                });
                
                if (!response.ok) throw new Error('Failed to save record');
                
                showToast(recordId ? 'Medical record updated successfully!' : 'Medical record added successfully!');
                closeUpdateModal();
                loadRecords();
                
            } catch (error) {
                console.error('Error saving record:', error);
                showToast('Error saving record', 'error');
            } finally {
                // Reset button state
                saveBtn.disabled = false;
                saveBtnText.textContent = 'Save Record';
            }
        }

        // Delete a record
        async function deleteRecord(recordId) {
            if (confirm('Are you sure you want to delete this medical record?')) {
                try {
                    const response = await fetch(ENDPOINTS.record(recordId), {
                        method: 'DELETE'
                    });
                    
                    if (!response.ok) throw new Error('Failed to delete record');
                    
                    showToast('Medical record deleted successfully!');
                    loadRecords();
                } catch (error) {
                    console.error('Error deleting record:', error);
                    showToast('Error deleting record', 'error');
                }
            }
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll('#recordsTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });

        // Sample data for demo purposes
        function loadSampleData() {
            const sampleRecords = [
                {
                    record_id: 1,
                    owner_id: 101,
                    owner_name: "Sarah Johnson",
                    pet_id: 201,
                    pet_name: "Max (Golden Retriever)",
                    species: "Dog",
                    breed: "Golden Retriever",
                    color: "Golden",
                    sex: "Male",
                    dob: "2018-05-15",
                    age: "5 years",
                    weight: "65 lbs",
                    status: "Active",
                    service_date: "2023-06-15",
                    service_type: "Vaccination",
                    veterinarian: "Dr. Smith"
                },
                {
                    record_id: 2,
                    owner_id: 102,
                    owner_name: "Michael Brown",
                    pet_id: 202,
                    pet_name: "Whiskers (Siamese)",
                    species: "Cat",
                    breed: "Siamese",
                    color: "Cream",
                    sex: "Female",
                    dob: "2020-03-10",
                    age: "3 years",
                    weight: "8 lbs",
                    status: "Active",
                    service_date: "2023-06-10",
                    service_type: "Dental",
                    veterinarian: "Dr. Smith"
                }
            ];
            
            displayRecords(sampleRecords);
            updateStats(sampleRecords);
        }

        // Initialize the application
        function init() {
            loadRecords();
            
            // Close modal when clicking outside
            window.onclick = function(event) {
                const modal = document.getElementById('updateModal');
                if (event.target === modal) {
                    closeUpdateModal();
                }
            }
            
            // Initialize charts
            initializeCharts();
        }

        // Initialize charts
        function initializeCharts() {
            // Visit Trend Chart
            const visitCtx = document.getElementById('visitTrendChart').getContext('2d');
            new Chart(visitCtx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                    datasets: [{
                        label: 'Patient Visits',
                        data: [120, 145, 138, 155, 162, 150, 168, 190, 185, 170, 165, 195],
                        borderColor: '#d81b60',
                        backgroundColor: 'rgba(216, 27, 96, 0.1)',
                        tension: 0.3,
                        fill: true,
                        borderWidth: 3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'top' }
                    }
                }
            });
            
            // Other charts would be initialized similarly
        }

        // Add CSS for status badges
        const style = document.createElement('style');
        style.textContent = `
            .status {
                padding: 4px 12px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 500;
            }
            .status.Active {
                background: rgba(0, 200, 83, 0.1);
                color: #00c853;
            }
            .status.Inactive {
                background: rgba(255, 171, 0, 0.1);
                color: #ffab00;
            }
            .status.Deceased {
                background: rgba(255, 23, 68, 0.1);
                color: #ff1744;
            }
        `;
        document.head.appendChild(style);

        // Start the application
        init();
    </script>
</body>
</html>