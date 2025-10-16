<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCareQR - Pet Health Dashboard</title>
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
        
        /* Enhanced Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #ffffff 0%, #fff8fc 100%);
            box-shadow: var(--shadow);
            padding: 1.5rem 1rem;
            display: flex;
            flex-direction: column;
            z-index: 100;
            transition: var(--transition);
            border-right: 1px solid #f1e6f0;
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            padding: 0 0.5rem 1.5rem;
            border-bottom: 1px solid rgba(191, 59, 120, 0.1);
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
            padding: 1.5rem 1rem;
            margin-bottom: 1.5rem;
            border-radius: var(--radius);
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
            box-shadow: var(--shadow);
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
            border: 1px solid transparent;
        }
        
        .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
            font-size: 1.1rem;
            transition: var(--transition);
        }
        
        .nav-link:hover {
            background: var(--primary-light);
            color: var(--primary-dark);
            border-color: var(--primary-light);
            transform: translateX(5px);
        }
        
        .nav-link:hover i {
            color: var(--primary-dark);
            transform: scale(1.1);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
            box-shadow: 0 4px 12px rgba(191, 59, 120, 0.3);
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
            background: linear-gradient(135deg, var(--danger), #c82333);
            color: white;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            transition: var(--transition);
            box-shadow: var(--shadow);
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #c82333, #a71e2a);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .logout-btn i {
            margin-right: 0.5rem;
        }
        
        /* Enhanced Main Content */
        .main-content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #f8fafc;
        }
        
        /* Enhanced Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }
        
        .header-title h1 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
            font-size: 1.8rem;
        }
        
        .header-title p {
            color: var(--gray);
            margin: 0;
            font-size: 1.1rem;
        }
        
        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }
        
        .search-box {
            position: relative;
            width: 320px;
        }
        
        .search-box input {
            width: 100%;
            padding: 0.85rem 1rem 0.85rem 2.5rem;
            border: 1px solid #e2e8f0;
            border-radius: var(--radius-sm);
            background: white;
            transition: var(--transition);
            font-size: 0.95rem;
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
            padding: 0.5rem 1rem;
            background: var(--primary-light);
            border-radius: var(--radius-sm);
        }
        
        .current-date {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .current-time {
            font-size: 0.9rem;
            color: var(--primary-dark);
            font-weight: 600;
        }
        
        /* Enhanced Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.75rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, rgba(191, 59, 120, 0.05), transparent);
            border-radius: 0 0 0 80px;
        }
        
        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.vaccination-due {
            border-left-color: var(--danger);
        }
        
        .stat-card.vaccination-due::before {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.05), transparent);
        }
        
        .stat-card.recent-visits {
            border-left-color: var(--secondary);
        }
        
        .stat-card.recent-visits::before {
            background: linear-gradient(135deg, rgba(74, 108, 247, 0.05), transparent);
        }
        
        .stat-card.vaccinated {
            border-left-color: var(--success);
        }
        
        .stat-card.vaccinated::before {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.05), transparent);
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
            box-shadow: var(--shadow);
        }
        
        .stat-card .stat-icon {
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
            color: var(--primary);
        }
        
        .stat-card.vaccinated .stat-icon {
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.15), rgba(46, 204, 113, 0.05));
            color: var(--success);
        }
        
        .stat-card.vaccination-due .stat-icon {
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.15), rgba(231, 76, 60, 0.05));
            color: var(--danger);
        }
        
        .stat-card.recent-visits .stat-icon {
            background: linear-gradient(135deg, rgba(74, 108, 247, 0.15), rgba(74, 108, 247, 0.05));
            color: var(--secondary);
        }
        
        .stat-value {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }
        
        .stat-label {
            color: var(--gray);
            font-weight: 600;
            font-size: 0.95rem;
        }
        
        /* Enhanced Alert Banner */
        .alert-vaccination {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            box-shadow: var(--shadow);
            border: none;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.02); }
            100% { transform: scale(1); }
        }
        
        .alert-vaccination i {
            font-size: 2rem;
            margin-right: 1.25rem;
        }
        
        .alert-vaccination .alert-heading {
            margin: 0 0 0.25rem;
            font-weight: 700;
            font-size: 1.2rem;
        }
        
        .alert-vaccination p {
            margin: 0;
            opacity: 0.9;
            font-size: 1rem;
        }
        
        /* Enhanced Sections */
        .section {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid #f1f5f9;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.75rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary-light);
        }
        
        .section-title {
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            font-size: 1.4rem;
        }
        
        .section-title i {
            margin-right: 0.75rem;
            color: var(--primary);
            font-size: 1.3rem;
        }
        
        .section-subtitle {
            color: var(--gray);
            margin: 0.5rem 0 0;
            font-size: 1rem;
        }
        
        /* Enhanced Recommendation Cards */
        .recommendation-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        .recommendation-card {
            border-radius: var(--radius);
            padding: 1.5rem;
            border-left: 4px solid;
            transition: var(--transition);
            background: var(--light);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .recommendation-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 60px;
            height: 60px;
            opacity: 0.1;
            border-radius: 0 0 0 60px;
        }
        
        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }
        
        .recommendation-card.high {
            border-left-color: var(--danger);
            background: linear-gradient(135deg, #fef5f5, #fdedec);
        }
        
        .recommendation-card.high::before {
            background: var(--danger);
        }
        
        .recommendation-card.medium {
            border-left-color: var(--warning);
            background: linear-gradient(135deg, #fef9e7, #fef5e7);
        }
        
        .recommendation-card.medium::before {
            background: var(--warning);
        }
        
        .recommendation-card.low {
            border-left-color: var(--success);
            background: linear-gradient(135deg, #f0fdf4, #eafaf1);
        }
        
        .recommendation-card.low::before {
            background: var(--success);
        }
        
        .recommendation-header {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .recommendation-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.2rem;
            box-shadow: var(--shadow);
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
            font-size: 1.1rem;
        }
        
        .recommendation-pet {
            font-size: 0.9rem;
            color: var(--gray);
            margin: 0;
        }
        
        .recommendation-content {
            margin-bottom: 1rem;
        }
        
        .recommendation-message {
            margin-bottom: 1rem;
            color: var(--dark);
            line-height: 1.5;
        }
        
        .recommendation-action {
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            color: var(--primary);
            font-weight: 600;
            text-decoration: none;
            transition: var(--transition);
        }
        
        .recommendation-action:hover {
            color: var(--primary-dark);
        }
        
        .recommendation-action i {
            margin-left: 0.5rem;
            transition: var(--transition);
        }
        
        .recommendation-action:hover i {
            transform: translateX(3px);
        }
        
        /* Enhanced Pet Cards */
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 1.75rem;
        }
        
        .pet-card {
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border: 1px solid #f1f5f9;
        }
        
        .pet-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }
        
        .pet-header {
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
            position: relative;
            overflow: hidden;
        }
        
        .pet-header::before {
            content: '';
            position: absolute;
            top: -20px;
            right: -20px;
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
        }
        
        .pet-info h3 {
            font-weight: 700;
            margin-bottom: 0.25rem;
            color: var(--dark);
            font-size: 1.3rem;
        }
        
        .pet-breed {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 0.75rem;
        }
        
        .pet-status {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            box-shadow: var(--shadow);
        }
        
        .status-good {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
            border: 1px solid rgba(46, 204, 113, 0.2);
        }
        
        .status-warning {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning);
            border: 1px solid rgba(243, 156, 18, 0.2);
        }
        
        .status-bad {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .pet-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            background: white;
            box-shadow: var(--shadow);
            color: var(--primary);
            border: 3px solid white;
        }
        
        .pet-body {
            padding: 1.5rem;
        }
        
        .pet-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .detail-item {
            display: flex;
            flex-direction: column;
        }
        
        .detail-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 0.25rem;
            font-weight: 500;
        }
        
        .detail-value {
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }
        
        .pet-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn {
            padding: 0.7rem 1.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
            border: none;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }
        
        .btn i {
            margin-right: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 12px rgba(191, 59, 120, 0.3);
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, var(--primary-dark), #7a2250);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(191, 59, 120, 0.4);
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary);
            color: var(--primary);
        }
        
        .btn-outline:hover {
            background: var(--primary-light);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(191, 59, 120, 0.2);
        }
        
        /* Enhanced QR Preview */
        .qr-preview {
            width: 100px;
            height: 100px;
            border: 2px solid #e2e8f0;
            border-radius: var(--radius-sm);
            padding: 0.5rem;
            background: white;
            box-shadow: var(--shadow);
            transition: var(--transition);
            cursor: pointer;
        }
        
        .qr-preview:hover {
            transform: scale(1.05);
            border-color: var(--primary);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* Enhanced Timeline */
        .vaccination-timeline {
            position: relative;
            padding-left: 2.5rem;
        }
        
        .vaccination-timeline::before {
            content: '';
            position: absolute;
            left: 1rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--primary-light), #e2e8f0);
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 1.75rem;
            padding: 1.5rem;
            background: var(--light);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            border: 1px solid #f1f5f9;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -2.5rem;
            top: 1.75rem;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: var(--primary);
            border: 3px solid white;
            box-shadow: 0 0 0 2px var(--primary-light);
        }
        
        .timeline-item.due::before {
            background: var(--danger);
            box-shadow: 0 0 0 2px rgba(231, 76, 60, 0.3);
        }
        
        .timeline-item.upcoming::before {
            background: var(--warning);
            box-shadow: 0 0 0 2px rgba(243, 156, 18, 0.3);
        }
        
        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .timeline-pet {
            font-weight: 700;
            color: var(--dark);
            font-size: 1.1rem;
        }
        
        .timeline-date {
            font-weight: 600;
            color: var(--danger);
            font-size: 0.95rem;
        }
        
        .timeline-item.upcoming .timeline-date {
            color: var(--warning);
        }
        
        .timeline-vaccines {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }
        
        .vaccine-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: rgba(231, 76, 60, 0.1);
            color: var(--danger);
            border: 1px solid rgba(231, 76, 60, 0.2);
        }
        
        .timeline-message {
            color: var(--gray);
            font-size: 0.95rem;
            line-height: 1.5;
        }
        
        /* Quick Action Section */
        .quick-action-section {
            text-align: center;
            padding: 2.5rem;
            background: linear-gradient(135deg, var(--primary-light), #ffe7f2);
            border-radius: var(--radius);
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            border: 1px solid #f1e6f0;
        }
        
        .quick-action-section h3 {
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 700;
        }
        
        .quick-action-section p {
            color: var(--gray);
            margin-bottom: 1.5rem;
            max-width: 500px;
            margin-left: auto;
            margin-right: auto;
            font-size: 1.05rem;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .pets-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            }
            
            .recommendation-grid {
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
            
            .main-content {
                padding: 1.5rem;
            }
            
            .dashboard-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1.5rem;
            }
            
            .header-actions {
                width: 100%;
                justify-content: space-between;
            }
            
            .search-box {
                width: 100%;
                max-width: 400px;
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
                width: 100%;
            }
            
            .pet-details {
                grid-template-columns: 1fr;
            }
        }
        
        /* Utility Classes */
        .text-primary { color: var(--primary) !important; }
        .text-success { color: var(--success) !important; }
        .text-warning { color: var(--warning) !important; }
        .text-danger { color: var(--danger) !important; }
        .text-dark { color: var(--dark) !important; }
        .text-gray { color: var(--gray) !important; }
        
        .bg-primary-light { background-color: var(--primary-light) !important; }
        .bg-success-light { background-color: var(--green-light) !important; }
        .bg-warning-light { background-color: var(--orange-light) !important; }
        .bg-danger-light { background-color: var(--red-light) !important; }
        
        .mb-0 { margin-bottom: 0 !important; }
        .mb-1 { margin-bottom: 0.5rem !important; }
        .mb-2 { margin-bottom: 1rem !important; }
        .mb-3 { margin-bottom: 1.5rem !important; }
        
        .mt-0 { margin-top: 0 !important; }
        .mt-1 { margin-top: 0.5rem !important; }
        .mt-2 { margin-top: 1rem !important; }
        .mt-3 { margin-top: 1.5rem !important; }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>
<div class="dashboard-container">
    <!-- Enhanced Sidebar -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <i class="fa-solid fa-paw"></i>
            <h2>VetCareQR</h2>
        </div>
        
        <div class="user-profile">
            <div class="user-avatar">
                <img src="https://i.pravatar.cc/150?u=<?php echo urlencode($user['name']); ?>" alt="User Avatar">
            </div>
            <h3 class="user-name"><?php echo htmlspecialchars($user['name']); ?></h3>
            <p class="user-role"><?php echo htmlspecialchars($user['role']); ?></p>
        </div>
        
        <div class="sidebar-nav">
            <div class="nav-item">
                <a href="user_dashboard.php" class="nav-link active">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Dashboard</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="user_pet_profile.php" class="nav-link">
                    <i class="fa-solid fa-paw"></i>
                    <span>My Pets</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="qr_code.php" class="nav-link">
                    <i class="fa-solid fa-qrcode"></i>
                    <span>QR Codes</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="register_pet.php" class="nav-link">
                    <i class="fa-solid fa-plus-circle"></i>
                    <span>Register Pet</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fa-solid fa-calendar-alt"></i>
                    <span>Appointments</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fa-solid fa-file-medical"></i>
                    <span>Medical Records</span>
                </a>
            </div>
            <div class="nav-item">
                <a href="#" class="nav-link">
                    <i class="fa-solid fa-bell"></i>
                    <span>Notifications</span>
                </a>
            </div>
        </div>
        
        <div class="sidebar-footer">
            <a href="logout.php" class="logout-btn">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Enhanced Main Content -->
    <div class="main-content">
        <!-- Enhanced Header -->
        <div class="dashboard-header fade-in">
            <div class="header-title">
                <h1>Good Morning, <span id="ownerName"><?php echo htmlspecialchars($user['name']); ?></span> 👋</h1>
                <p>Here's your pet health overview for today</p>
            </div>
            
            <div class="header-actions">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" placeholder="Search pets, vaccines, vets...">
                </div>
                
                <div class="date-display">
                    <div class="current-date" id="currentDate"></div>
                    <div class="current-time" id="currentTime"></div>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- VACCINATION ALERTS -->
        <?php if ($petsNeedingVaccination > 0): ?>
            <div class="alert-vaccination alert-dismissible fade show fade-in" role="alert">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="flex-grow-1">
                    <h6 class="alert-heading">Vaccination Alert!</h6>
                    <p class="mb-0"><?php echo $petsNeedingVaccination; ?> of your pets need vaccination updates.</p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Enhanced Stats Cards -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fa-solid fa-paw"></i>
                </div>
                <div class="stat-value"><?php echo $totalPets; ?></div>
                <div class="stat-label">Registered Pets</div>
            </div>
            
            <div class="stat-card vaccinated">
                <div class="stat-icon">
                    <i class="fa-solid fa-syringe"></i>
                </div>
                <div class="stat-value"><?php echo $vaccinatedPets; ?></div>
                <div class="stat-label">Vaccinated Pets</div>
            </div>
            
            <div class="stat-card vaccination-due">
                <div class="stat-icon">
                    <i class="fa-solid fa-bell"></i>
                </div>
                <div class="stat-value"><?php echo $petsNeedingVaccination; ?></div>
                <div class="stat-label">Vaccination Due</div>
            </div>
            
            <div class="stat-card recent-visits">
                <div class="stat-icon">
                    <i class="fa-solid fa-stethoscope"></i>
                </div>
                <div class="stat-value"><?php echo $recentVisits; ?></div>
                <div class="stat-label">Recent Visits</div>
            </div>
        </div>

        <!-- WELLNESS RECOMMENDATIONS SECTION -->
        <?php if (!empty($pets)): ?>
        <div class="section fade-in">
            <div class="section-header">
                <div>
                    <h2 class="section-title"><i class="fas fa-heartbeat"></i> Health & Wellness Recommendations</h2>
                    <p class="section-subtitle">AI-powered insights for your pets' wellbeing</p>
                </div>
            </div>
            
            <div class="recommendation-grid">
                <?php foreach ($pets as $petId => $pet): ?>
                    <?php $recommendations = $wellnessRecommendations[$petId] ?? []; ?>
                    <?php if (!empty($recommendations)): ?>
                        <?php foreach ($recommendations as $rec): ?>
                            <div class="recommendation-card recommendation-<?php echo $rec['priority']; ?>">
                                <div class="recommendation-header">
                                    <div class="recommendation-icon">
                                        <i class="fas <?php echo $rec['icon']; ?>"></i>
                                    </div>
                                    <div>
                                        <h3 class="recommendation-title"><?php echo $rec['title']; ?></h3>
                                        <p class="recommendation-pet">For: <?php echo htmlspecialchars($pet['pet_name']); ?></p>
                                    </div>
                                </div>
                                <div class="recommendation-content">
                                    <p class="recommendation-message"><?php echo $rec['message']; ?></p>
                                    <a href="#" class="recommendation-action">
                                        <?php echo $rec['action']; ?>
                                        <i class="fas fa-arrow-right"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- VACCINATION PREDICTION TIMELINE -->
        <?php if (!empty($pets)): ?>
        <div class="section fade-in">
            <div class="section-header">
                <h2 class="section-title"><i class="fas fa-calendar-alt"></i> Vaccination Schedule</h2>
            </div>
            
            <div class="vaccination-timeline">
                <?php foreach ($pets as $petId => $pet): ?>
                    <?php $prediction = $vaccinationPredictions[$petId]; ?>
                    <div class="timeline-item <?php echo $prediction['needs_vaccination'] ? 'due' : 'upcoming'; ?>">
                        <div class="timeline-header">
                            <div class="timeline-pet"><?php echo htmlspecialchars($pet['pet_name']); ?></div>
                            <div class="timeline-date">
                                <?php if ($prediction['next_vaccination_date']): ?>
                                    <?php echo $prediction['needs_vaccination'] ? 'Overdue since' : 'Next due'; ?>: 
                                    <?php echo date('M j, Y', strtotime($prediction['next_vaccination_date'])); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="timeline-vaccines">
                            <?php if (!empty($prediction['recommended_vaccines'])): ?>
                                <?php foreach ($prediction['recommended_vaccines'] as $vaccine): ?>
                                    <span class="vaccine-badge"><?php echo $vaccine; ?></span>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <p class="timeline-message"><?php echo $prediction['message']; ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Quick Action Section -->
        <div class="quick-action-section fade-in">
            <h3><i class="fa-solid fa-paw me-2"></i>Manage Your Pets</h3>
            <p>Register your pets to track their medical records, generate QR codes, and receive personalized health recommendations.</p>
            <a href="register_pet.php" class="btn btn-primary btn-lg">
                <i class="fa-solid fa-plus-circle me-2"></i> Add New Pet
            </a>
        </div>

        <!-- Pets Section -->
        <div class="section fade-in">
            <div class="section-header">
                <h2 class="section-title"><i class="fa-solid fa-paw"></i> Your Pets</h2>
                <a href="register_pet.php" class="btn btn-outline">
                    <i class="fa-solid fa-plus me-2"></i> Add Pet
                </a>
            </div>
            
            <?php if (empty($pets)): ?>
                <div class="text-center py-5">
                    <i class="fa-solid fa-paw fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Pets Registered</h4>
                    <p class="text-muted mb-4">You haven't added any pets yet. Register your first pet to get started!</p>
                    <a href="register_pet.php" class="btn btn-primary btn-lg">
                        <i class="fa-solid fa-plus me-2"></i> Add Your First Pet
                    </a>
                </div>
            <?php else: ?>
                <div class="pets-grid">
                    <?php foreach ($pets as $pet): ?>
                        <?php
                        $hasVaccination = false;
                        $hasRecentVisit = false;
                        $prediction = $vaccinationPredictions[$pet['pet_id']];
                        
                        foreach ($pet['records'] as $record) {
                            if (!empty($record['service_type']) && stripos($record['service_type'], 'vaccin') !== false) {
                                $hasVaccination = true;
                            }
                            if (!empty($record['service_date']) && $record['service_date'] >= $thirtyDaysAgo) {
                                $hasRecentVisit = true;
                            }
                        }
                        
                        // Enhanced health status with vaccination prediction
                        if ($prediction['needs_vaccination']) {
                            $healthStatus = 'Vaccination Due';
                            $statusClass = 'status-bad';
                        } else {
                            $healthStatus = $hasVaccination ? 'Good Health' : 'Needs Vaccination';
                            $statusClass = $hasVaccination ? 'status-good' : 'status-warning';
                            if (!$hasVaccination && !$hasRecentVisit) {
                                $healthStatus = 'Needs Checkup';
                                $statusClass = 'status-bad';
                            }
                        }
                        ?>
                        <div class="pet-card">
                            <div class="pet-header">
                                <div class="pet-info">
                                    <h3><?php echo htmlspecialchars($pet['pet_name']); ?></h3>
                                    <p class="pet-breed"><?php echo htmlspecialchars($pet['species']) . " • " . htmlspecialchars($pet['breed']); ?></p>
                                    <span class="pet-status <?php echo $statusClass; ?>"><?php echo $healthStatus; ?></span>
                                </div>
                                <div class="pet-avatar">
                                    <i class="fa-solid <?php echo strtolower($pet['species']) == 'dog' ? 'fa-dog' : 'fa-cat'; ?>"></i>
                                </div>
                            </div>
                            <div class="pet-body">
                                <!-- Vaccination Alert Badge -->
                                <?php if ($prediction['needs_vaccination']): ?>
                                    <div class="alert alert-warning d-flex align-items-center mb-3 py-2">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <small class="flex-grow-1">Vaccination due: <?php echo implode(', ', $prediction['recommended_vaccines']); ?></small>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-flex align-items-center mb-3">
                                    <div id="qrcode-<?php echo $pet['pet_id']; ?>" class="qr-preview me-3"></div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <div class="detail-item">
                                                    <span class="detail-label">Age</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($pet['age']); ?> years</span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">Gender</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($pet['gender']) ?: 'Not specified'; ?></span>
                                                </div>
                                            </div>
                                            <div class="text-end">
                                                <div class="detail-item">
                                                    <span class="detail-label">Registered</span>
                                                    <span class="detail-value"><?php echo date('M j, Y', strtotime($pet['date_registered'])); ?></span>
                                                </div>
                                                <div class="detail-item">
                                                    <span class="detail-label">Pet ID</span>
                                                    <span class="detail-value"><?php echo htmlspecialchars($pet['pet_id']); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- QR Data Preview -->
                                <div id="qr-data-<?php echo $pet['pet_id']; ?>" class="qr-data-preview mb-3" style="display: none;"></div>
                                
                                <?php if (!empty($pet['records'])): ?>
                                    <div class="table-responsive mt-3">
                                        <table class="table table-sm table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Date</th>
                                                    <th>Service Type</th>
                                                    <th>Description</th>
                                                    <th>Veterinarian</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($pet['records'] as $record): ?>
                                                    <?php if (!empty($record['service_date']) && $record['service_date'] !== '0000-00-00'): ?>
                                                        <tr>
                                                            <td><?php echo date('M j, Y', strtotime($record['service_date'])); ?></td>
                                                            <td>
                                                                <?php if (!empty($record['service_type'])): ?>
                                                                    <?php 
                                                                    $badgeClass = 'badge bg-primary';
                                                                    if (stripos($record['service_type'], 'vaccin') !== false) {
                                                                        $badgeClass = 'badge bg-success';
                                                                    } elseif (stripos($record['service_type'], 'check') !== false) {
                                                                        $badgeClass = 'badge bg-warning';
                                                                    }
                                                                    ?>
                                                                    <span class="<?php echo $badgeClass; ?>"><?php echo htmlspecialchars($record['service_type']); ?></span>
                                                                <?php else: ?>
                                                                    -
                                                                <?php endif; ?>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($record['service_description'] ?? '-'); ?></td>
                                                            <td><?php echo htmlspecialchars($record['veterinarian'] ?? '-'); ?></td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <div class="alert alert-info text-center">
                                        <i class="fas fa-info-circle me-2"></i> No medical records found for <?php echo htmlspecialchars($pet['pet_name']); ?>.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="pet-actions mt-3">
                                    <button class="btn btn-primary">
                                        <i class="fas fa-file-medical me-2"></i> Records
                                    </button>
                                    <button class="btn btn-outline" onclick="downloadQRCode(<?php echo $pet['pet_id']; ?>)">
                                        <i class="fas fa-download me-2"></i> QR Code
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- QR Code Modal -->
<div class="modal fade" id="qrModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="qrModalTitle">QR Code</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div id="modalQrContainer" class="mb-3"></div>
                <div id="modalQrData" class="qr-data-preview mb-3"></div>
                <p class="text-muted">Scan this QR code to view medical records</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="downloadModalQr">
                    <i class="fas fa-download me-1"></i> Download
                </button>
                <button type="button" class="btn btn-info" onclick="toggleQrData()">
                    <i class="fas fa-eye me-1"></i> View Data
                </button>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript remains the same as in your original code -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Your existing JavaScript code remains unchanged
    // ... (all the JavaScript functions from your original code)
</script>
</body>
</html>
