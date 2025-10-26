<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet" />
  <style>
    :root{
      --bg:#fff5f8;          /* very light pink background */
      --card:#ffffff;        /* card background */
      --ink:#4a0e2e;         /* dark rose text */
      --muted:#6b7280;       /* secondary gray text */
      --brand:#f06292;       /* main pink */
      --brand-2:#ec407a;     /* deeper pink for contrast */
      --warning:#f59e0b;     /* amber warning */
      --danger:#e11d48;      /* rose red for danger */
      --lav:#d63384;         /* vibrant pink accent */
      --success:#10b981;     /* green for success */
      --info:#0ea5e9;        /* blue for info */
      --shadow:0 10px 30px rgba(236,64,122,.1);
      --radius:1.25rem;
      --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    body{ 
      background: linear-gradient(180deg,#fff0f6 0%, #fff5f8 40%, #fff5f8 100%);
      color:var(--ink);
      font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
    }

    /* Shell layout */
    .app-shell{
      display:grid; 
      grid-template-columns: 280px 1fr; 
      min-height:100vh; 
      gap:24px; 
      padding:24px;
      max-width: 1920px;
      margin: 0 auto;
    }
    @media (max-width: 992px){ 
      .app-shell{ 
        grid-template-columns: 1fr; 
        padding:16px;
        gap:16px;
      } 
    }

    /* Enhanced Sidebar */
    .sidebar {
      background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
      color: #fff;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      position: relative;
      overflow: hidden;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.1);
    }
    
    .sidebar::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 120px;
      background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, transparent 100%);
      z-index: 0;
    }
    
    .sidebar .brand { 
      font-weight:800; 
      color:#fff; 
      font-size: 1.5rem;
      position: relative;
      z-index: 1;
    }
    
    .sidebar .nav-link { 
      color:#ffe6ef; 
      border-radius:12px; 
      padding:14px 16px; 
      font-weight:600;
      transition: var(--transition);
      position: relative;
      z-index: 1;
      margin-bottom: 4px;
    }
    
    .sidebar .nav-link.active, 
    .sidebar .nav-link:hover { 
      background: rgba(255,255,255,0.15); 
      color:#fff; 
      transform: translateX(8px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .sidebar .icon { 
      width:40px; 
      height:40px; 
      border-radius:12px; 
      display:grid; 
      place-items:center; 
      background: rgba(255,255,255,.2); 
      margin-right:12px;
      transition: var(--transition);
    }
    
    .sidebar .nav-link.active .icon,
    .sidebar .nav-link:hover .icon {
      background: rgba(255,255,255,.3);
      transform: scale(1.1);
    }

    /* Enhanced Topbar */
    .topbar{ 
      background: var(--card); 
      border-radius: var(--radius); 
      box-shadow: var(--shadow); 
      padding: 16px 24px; 
      display:flex; 
      align-items:center; 
      gap:16px;
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255,255,255,0.8);
    }
    
    .topbar .search{ flex:1; max-width: 480px }
    .form-control.search-input{ 
      border:none; 
      background:#f8fafc; 
      border-radius:14px; 
      padding:12px 16px;
      transition: var(--transition);
      border: 1px solid transparent;
    }
    
    .form-control.search-input:focus {
      background: white;
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
      transform: translateY(-2px);
    }

    /* Enhanced Tabs */
    .page-tabs{ display:flex; align-items:center; gap:20px; }
    .page-tabs .btn-tab{ 
      background:transparent; 
      border:none; 
      padding:12px 8px; 
      font-weight:700; 
      color:var(--muted);
      transition: var(--transition);
      position: relative;
    }
    
    .page-tabs .btn-tab.active{ 
      color: var(--ink); 
    }
    
    .page-tabs .btn-tab.active::after{ 
      content:""; 
      position:absolute; 
      inset:auto 0 -8px 0; 
      margin:auto; 
      width:60%; 
      height:3px; 
      background: var(--brand); 
      border-radius:99px;
      box-shadow: 0 2px 8px rgba(240, 98, 146, 0.3);
    }
    
    .page-tabs .btn-tab:hover {
      color: var(--ink);
      transform: translateY(-2px);
    }

    /* Enhanced Cards */
    .card-soft{ 
      background:var(--card); 
      border:none; 
      border-radius:var(--radius); 
      box-shadow: var(--shadow);
      transition: var(--transition);
      border: 1px solid rgba(255,255,255,0.8);
      overflow: hidden;
    }
    
    .card-soft:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 40px rgba(236,64,122,.15);
    }

    /* Enhanced KPI Cards */
    .kpi{ 
      display:flex; 
      align-items:center; 
      gap:20px; 
      padding:20px;
      position: relative;
    }
    
    .kpi::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--brand), var(--lav));
      opacity: 0;
      transition: var(--transition);
    }
    
    .kpi:hover::before {
      opacity: 1;
    }
    
    .kpi .bubble{ 
      width:60px; 
      height:60px; 
      border-radius:16px; 
      display:grid; 
      place-items:center; 
      font-size:24px;
      transition: var(--transition);
    }
    
    .kpi:hover .bubble {
      transform: scale(1.1) rotate(5deg);
    }
    
    .kpi small{ 
      color:var(--muted); 
      font-weight:700; 
      text-transform:uppercase; 
      letter-spacing:.5px;
      font-size: 0.75rem;
    }
    
    .kpi .stat-value {
      font-size: 2.25rem;
      font-weight: 800;
      line-height: 1;
      margin: 8px 0 4px;
      background: linear-gradient(135deg, var(--ink), var(--lav));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .badge-dot{ 
      display:inline-flex; 
      align-items:center; 
      gap:6px; 
      font-weight:700; 
      color:var(--muted);
      font-size: 0.75rem;
    }
    
    .badge-dot::before{ 
      content:""; 
      width:8px; 
      height:8px; 
      border-radius:50%; 
      background: currentColor;
      animation: pulse 2s infinite;
    }

    @keyframes pulse {
      0% { transform: scale(1); opacity: 1; }
      50% { transform: scale(1.2); opacity: 0.7; }
      100% { transform: scale(1); opacity: 1; }
    }

    /* Enhanced Progress Meter */
    .meter-wrap{ display:flex; align-items:center; gap:16px }
    .meter{ 
      --p:70; 
      width:80px; 
      height:80px; 
      border-radius:50%; 
      background: conic-gradient(var(--brand) calc(var(--p)*1%), #e8eef6 0); 
      display:grid; 
      place-items:center;
      position: relative;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    
    .meter .hole{ 
      width:64px; 
      height:64px; 
      background:#fff; 
      border-radius:50%; 
      display:grid; 
      place-items:center; 
      font-weight:800;
      box-shadow: inset 0 2px 4px rgba(0,0,0,0.1);
    }

    /* Enhanced Appointment Cards */
    .appt{ 
      border:1px solid #f1f5f9; 
      border-radius: 20px; 
      padding:20px; 
      transition:var(--transition); 
      background:#fff;
      position: relative;
      overflow: hidden;
    }
    
    .appt::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, var(--brand), var(--lav));
      transform: scaleX(0);
      transform-origin: left;
      transition: var(--transition);
    }
    
    .appt:hover{ 
      transform: translateY(-8px); 
      box-shadow: 0 20px 40px rgba(0,0,0,0.1);
      border-color: var(--brand-light);
    }
    
    .appt:hover::before {
      transform: scaleX(1);
    }
    
    .appt .avatar{ 
      width:50px; 
      height:50px; 
      border-radius:14px; 
      object-fit:cover;
      border: 2px solid #f1f5f9;
      transition: var(--transition);
    }
    
    .appt:hover .avatar {
      border-color: var(--brand);
      transform: scale(1.05);
    }
    
    .appt .chips{ display:flex; gap:8px; flex-wrap:wrap }
    .chip{ 
      font-size:.75rem; 
      font-weight:800; 
      padding:6px 10px; 
      border-radius:999px;
      transition: var(--transition);
    }
    
    .chip.confirmed{ background:#e8faf3; color:#0d9f6e }
    .chip.arrived{ background:#eef2ff; color:#635bff }
    .chip.pending{ background:#fff7e6; color:#c27400 }
    .chip.ongoing{ background:#eaf6ff; color:#1b74d1 }
    .chip.cancelled{ background:#ffeceb; color:#cc2e2e }
    .chip.completed{ background:#f3e8ff; color:#7c3aed }

    .appt .btn{ border-radius:12px; font-weight:700; transition: var(--transition); }
    .btn-plain{ background:#f8fafc; border:none; color:var(--ink) }
    .btn-plain:hover {
      background: var(--brand);
      color: white;
      transform: translateY(-2px);
    }

    /* Enhanced Section Titles */
    .section-title{ 
      font-weight:800; 
      font-size:1.25rem; 
      margin-bottom:8px;
      background: linear-gradient(135deg, var(--ink), var(--lav));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    
    .subtle{ color:var(--muted); font-weight: 600; }

    /* Enhanced Legend */
    .legend{ display:flex; flex-wrap:wrap; gap:12px; font-size:.8rem; }
    .legend .legend-item{ 
      display:inline-flex; 
      align-items:center; 
      gap:6px; 
      color:var(--muted);
      transition: var(--transition);
      padding: 4px 8px;
      border-radius: 8px;
    }
    
    .legend .legend-item:hover {
      background: #f8fafc;
      color: var(--ink);
    }
    
    .legend .dot{ width:12px; height:12px; border-radius:50%; transition: var(--transition); }
    .legend .legend-item:hover .dot {
      transform: scale(1.2);
    }
    
    .dot.no-show{ background:#9aa5b1 }
    .dot.completed{ background:#16a34a }
    .dot.pending{ background:#f59e0b }
    .dot.ongoing{ background:#3b82f6 }
    .dot.confirmed{ background:#10b981 }
    .dot.arrived{ background:#8b5cf6 }
    .dot.cancelled{ background:#ef4444 }

    /* Enhanced Toolbar */
    .toolbar{ display:flex; align-items:center; gap:12px; flex-wrap: wrap; }
    .toolbar .form-select, .toolbar .form-control{ 
      border-radius:12px; 
      border: 1px solid #e2e8f0;
      transition: var(--transition);
    }
    
    .toolbar .form-select:focus, .toolbar .form-control:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px rgba(240, 98, 146, 0.1);
    }
    
    .btn-brand{ 
      background: linear-gradient(135deg, var(--brand), var(--lav)); 
      border:none; 
      color:white; 
      font-weight:800; 
      border-radius:14px; 
      padding:12px 20px;
      transition: var(--transition);
      box-shadow: 0 4px 12px rgba(240, 98, 146, 0.3);
    }
    
    .btn-brand:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px rgba(240, 98, 146, 0.4);
      background: linear-gradient(135deg, var(--lav), var(--brand));
    }

    /* Stats Grid Enhancement */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 20px;
    }

    /* Chart Container */
    .chart-container {
      position: relative;
      height: 200px;
      margin-top: 20px;
    }

    /* Loading Animation */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .fade-in {
      animation: fadeInUp 0.6s ease-out forwards;
    }

    /* Notification Badge */
    .notification-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      background: var(--danger);
      color: white;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.7rem;
      font-weight: 800;
      animation: pulse 2s infinite;
    }

    /* Responsive Improvements */
    @media (max-width: 768px) {
      .app-shell {
        gap: 16px;
        padding: 12px;
      }
      
      .topbar {
        flex-direction: column;
        align-items: stretch;
        gap: 12px;
      }
      
      .topbar .search {
        max-width: 100%;
      }
      
      .toolbar {
        justify-content: center;
      }
      
      .stats-grid {
        grid-template-columns: 1fr;
      }
    }

    /* Scrollbar Styling */
    ::-webkit-scrollbar {
      width: 8px;
    }

    ::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb {
      background: var(--brand);
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: var(--lav);
    }
  </style>
</head>
<body>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar p-4">
      <div class="d-flex align-items-center mb-5">
        <div class="icon me-3"><i class="fa-solid fa-user-shield"></i></div>
        <div class="brand h4 mb-0">VetCareQR</div>
      </div>
      <nav class="nav flex-column gap-2">
        <a class="nav-link d-flex align-items-center active" href="#">
          <span class="icon"><i class="fa-solid fa-gauge-high"></i></span>
          <span>Dashboard</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-user-doctor"></i></span>
          <span>Veterinarians</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-calendar-check"></i></span>
          <span>Appointments</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-users"></i></span>
          <span>Pet Owners</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-paw"></i></span>
          <span>Pets</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-chart-line"></i></span>
          <span>Analytics</span>
        </a>
        <a class="nav-link d-flex align-items-center" href="#">
          <span class="icon"><i class="fa-solid fa-gear"></i></span>
          <span>Settings</span>
        </a>
      </nav>
      
      <div class="mt-auto pt-4">
        <div class="admin-profile d-flex align-items-center p-3 rounded-3" style="background: rgba(255,255,255,0.1);">
          <img src="https://i.pravatar.cc/80?img=12" class="rounded-circle me-3" width="50" height="50" alt="Admin" />
          <div class="flex-grow-1">
            <div class="fw-bold text-white">Dr. Victoria Chen</div>
            <small class="text-white-50">Administrator</small>
          </div>
          <a href="#" class="text-white-50"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="d-flex flex-column gap-4">
      <!-- Topbar -->
      <div class="topbar">
        <div class="d-flex align-items-center">
          <h1 class="h4 mb-0 fw-bold">Admin Dashboard</h1>
          <span class="badge bg-light text-dark ms-3">Veterinarian Management</span>
        </div>
        
        <div class="search ms-auto">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0 text-muted">
              <i class="fa-solid fa-magnifying-glass"></i>
            </span>
            <input class="form-control search-input" placeholder="Search veterinarians, appointments, pets..." />
          </div>
        </div>
        
        <div class="position-relative">
          <button class="btn btn-light rounded-circle position-relative">
            <i class="fa-regular fa-bell"></i>
            <span class="notification-badge">3</span>
          </button>
        </div>
        
        <div class="dropdown">
          <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <img src="https://i.pravatar.cc/64?img=12" class="rounded-circle" width="40" height="40" alt="Admin" />
            <span class="fw-bold d-none d-md-inline">Dr. Victoria Chen</span>
            <i class="fa-solid fa-chevron-down text-muted"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#"><i class="fa-solid fa-user me-2"></i>Profile</a></li>
            <li><a class="dropdown-item" href="#"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="#"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
          </ul>
        </div>
      </div>

      <!-- Tabs & Toolbar -->
      <div class="card-soft p-4 d-flex flex-wrap justify-content-between align-items-center gap-3">
        <div class="page-tabs">
          <button class="btn-tab active">Overview</button>
          <button class="btn-tab">Veterinarians</button>
          <button class="btn-tab">Appointments</button>
          <button class="btn-tab">Reports</button>
        </div>
        <div class="toolbar">
          <div class="input-group" style="width: 180px">
            <span class="input-group-text bg-transparent border-end-0"><i class="fa-solid fa-calendar"></i></span>
            <input type="date" class="form-control border-start-0" id="datePicker" />
          </div>
          <select class="form-select" style="width: 200px">
            <option>All Veterinarians</option>
            <option>Dr. Aman Sharma</option>
            <option>Dr. Maria Santos</option>
            <option>Dr. James Wilson</option>
          </select>
          <button class="btn-brand" id="addVeterinarian">
            <i class="fa-solid fa-user-plus me-2"></i>Add Veterinarian
          </button>
        </div>
      </div>

      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="card-soft fade-in" style="animation-delay: 0.1s">
          <div class="kpi">
            <div class="bubble" style="background:#eaf2ff;color:#1b74d1">
              <i class="fa-solid fa-user-doctor"></i>
            </div>
            <div class="flex-grow-1">
              <small>Total Veterinarians</small>
              <div class="d-flex align-items-end gap-2">
                <div class="stat-value">24</div>
                <span class="badge-dot" style="color:#10b981">+2 this month</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card-soft fade-in" style="animation-delay: 0.2s">
          <div class="kpi">
            <div class="bubble" style="background:#e8faf3;color:#0d9f6e">
              <i class="fa-solid fa-calendar-check"></i>
            </div>
            <div class="flex-grow-1">
              <small>Appointments Today</small>
              <div class="d-flex align-items-end gap-2">
                <div class="stat-value">18</div>
                <span class="badge-dot" style="color:#f59e0b">3 pending</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card-soft fade-in" style="animation-delay: 0.3s">
          <div class="kpi">
            <div class="bubble" style="background:#fff0f5;color:#c2417a">
              <i class="fa-solid fa-paw"></i>
            </div>
            <div class="flex-grow-1">
              <small>Active Pets</small>
              <div class="d-flex align-items-end gap-2">
                <div class="stat-value">142</div>
                <span class="badge-dot" style="color:#8b5cf6">12 new</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card-soft fade-in" style="animation-delay: 0.4s">
          <div class="kpi">
            <div class="bubble" style="background:#fff7e6;color:#b45309">
              <i class="fa-solid fa-clock-rotate-left"></i>
            </div>
            <div class="flex-grow-1">
              <small>Pending Tasks</small>
              <div class="d-flex align-items-end gap-2">
                <div class="stat-value">7</div>
                <span class="badge-dot" style="color:#ef4444">2 urgent</span>
              </div>
            </div>
          </div>
        </div>
        
        <div class="card-soft p-4 fade-in" style="animation-delay: 0.5s">
          <div class="d-flex align-items-center justify-content-between">
            <div>
              <div class="small text-uppercase text-600 subtle">System Load</div>
              <div class="muted">Current Performance</div>
            </div>
            <div class="meter-wrap">
              <div class="meter" id="systemLoad" style="--p:65">
                <div class="hole"><span>65%</span></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Content Area -->
      <div class="row g-4">
        <!-- Recent Activity -->
        <div class="col-12 col-lg-8">
          <div class="card-soft p-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <div class="section-title">Recent Veterinarian Activity</div>
              <a href="#" class="text-decoration-none fw-bold" style="color: var(--brand);">View All</a>
            </div>
            
            <div class="row g-4">
              <!-- Veterinarian Card -->
              <div class="col-12 col-md-6">
                <div class="appt">
                  <div class="d-flex align-items-center mb-3">
                    <img class="avatar me-3" src="https://images.unsplash.com/photo-1612349317150-e413f6a5b16d?w=200&q=80" alt="Dr. Sharma"/>
                    <div class="flex-grow-1">
                      <div class="fw-bold">Dr. Aman Sharma</div>
                      <div class="small muted">Cardiology Specialist</div>
                    </div>
                    <span class="chip confirmed">Active</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                    <div><i class="fa-regular fa-calendar me-1"></i>Today's Appointments</div>
                    <div class="fw-bold">8</div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-plain btn-sm flex-grow-1">View Schedule</button>
                    <button class="btn btn-dark btn-sm">Message</button>
                  </div>
                </div>
              </div>
              
              <div class="col-12 col-md-6">
                <div class="appt">
                  <div class="d-flex align-items-center mb-3">
                    <img class="avatar me-3" src="https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=200&q=80" alt="Dr. Santos"/>
                    <div class="flex-grow-1">
                      <div class="fw-bold">Dr. Maria Santos</div>
                      <div class="small muted">Surgery Department</div>
                    </div>
                    <span class="chip ongoing">In Surgery</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                    <div><i class="fa-regular fa-calendar me-1"></i>Today's Appointments</div>
                    <div class="fw-bold">5</div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-plain btn-sm flex-grow-1">View Schedule</button>
                    <button class="btn btn-dark btn-sm">Message</button>
                  </div>
                </div>
              </div>
              
              <div class="col-12 col-md-6">
                <div class="appt">
                  <div class="d-flex align-items-center mb-3">
                    <img class="avatar me-3" src="https://images.unsplash.com/photo-1582750433449-648ed127bb54?w=200&q=80" alt="Dr. Wilson"/>
                    <div class="flex-grow-1">
                      <div class="fw-bold">Dr. James Wilson</div>
                      <div class="small muted">Dermatology</div>
                    </div>
                    <span class="chip pending">On Leave</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                    <div><i class="fa-regular fa-calendar me-1"></i>Today's Appointments</div>
                    <div class="fw-bold">0</div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-plain btn-sm flex-grow-1">View Schedule</button>
                    <button class="btn btn-dark btn-sm">Message</button>
                  </div>
                </div>
              </div>
              
              <div class="col-12 col-md-6">
                <div class="appt">
                  <div class="d-flex align-items-center mb-3">
                    <img class="avatar me-3" src="https://images.unsplash.com/photo-1594824947933-d0501ba2fe65?w=200&q=80" alt="Dr. Chen"/>
                    <div class="flex-grow-1">
                      <div class="fw-bold">Dr. Lisa Chen</div>
                      <div class="small muted">Internal Medicine</div>
                    </div>
                    <span class="chip confirmed">Active</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center text-muted small mb-3">
                    <div><i class="fa-regular fa-calendar me-1"></i>Today's Appointments</div>
                    <div class="fw-bold">6</div>
                  </div>
                  <div class="d-flex gap-2">
                    <button class="btn btn-plain btn-sm flex-grow-1">View Schedule</button>
                    <button class="btn btn-dark btn-sm">Message</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Quick Stats & Actions -->
        <div class="col-12 col-lg-4">
          <div class="card-soft p-4 mb-4">
            <div class="section-title mb-3">Quick Actions</div>
            <div class="d-grid gap-2">
              <button class="btn btn-outline-primary d-flex align-items-center justify-content-between p-3">
                <span>Manage Veterinarians</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
              <button class="btn btn-outline-success d-flex align-items-center justify-content-between p-3">
                <span>View Reports</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
              <button class="btn btn-outline-warning d-flex align-items-center justify-content-between p-3">
                <span>System Settings</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
              <button class="btn btn-outline-info d-flex align-items-center justify-content-between p-3">
                <span>Send Announcement</span>
                <i class="fa-solid fa-arrow-right"></i>
              </button>
            </div>
          </div>
          
          <div class="card-soft p-4">
            <div class="section-title mb-3">Today's Overview</div>
            <div class="legend mb-3">
              <span class="legend-item"><span class="dot confirmed"></span>Confirmed</span>
              <span class="legend-item"><span class="dot pending"></span>Pending</span>
              <span class="legend-item"><span class="dot ongoing"></span>Ongoing</span>
              <span class="legend-item"><span class="dot completed"></span>Completed</span>
            </div>
            
            <div class="chart-container">
              <canvas id="appointmentChart"></canvas>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // Initialize date picker with today's date
    const datePicker = document.getElementById('datePicker');
    const today = new Date();
    datePicker.valueAsDate = today;

    // Set system load meter
    function setSystemLoad(p) {
      const el = document.getElementById('systemLoad');
      el.style.setProperty('--p', p);
      el.querySelector('.hole span').textContent = p + '%';
    }
    setSystemLoad(65);

    // Initialize chart
    document.addEventListener('DOMContentLoaded', function() {
      const ctx = document.getElementById('appointmentChart').getContext('2d');
      const appointmentChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: ['Confirmed', 'Pending', 'Ongoing', 'Completed'],
          datasets: [{
            data: [45, 15, 20, 20],
            backgroundColor: [
              '#10b981',
              '#f59e0b',
              '#3b82f6',
              '#8b5cf6'
            ],
            borderWidth: 0,
            borderRadius: 8
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '70%',
          plugins: {
            legend: {
              display: false
            },
            tooltip: {
              callbacks: {
                label: function(context) {
                  return `${context.label}: ${context.raw}%`;
                }
              }
            }
          }
        }
      });
    });

    // Add loading animation to cards
    document.querySelectorAll('.card-soft').forEach((card, index) => {
      card.style.animationDelay = `${index * 0.1}s`;
    });

    // Add interactivity to buttons
    document.querySelectorAll('.btn').forEach(button => {
      button.addEventListener('click', function(e) {
        if (!this.classList.contains('dropdown-toggle')) {
          // Add ripple effect
          const ripple = document.createElement('span');
          const rect = this.getBoundingClientRect();
          const size = Math.max(rect.width, rect.height);
          const x = e.clientX - rect.left - size / 2;
          const y = e.clientY - rect.top - size / 2;
          
          ripple.style.cssText = `
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.6);
            transform: scale(0);
            animation: ripple 0.6s linear;
            pointer-events: none;
            width: ${size}px;
            height: ${size}px;
            left: ${x}px;
            top: ${y}px;
          `;
          
          this.style.position = 'relative';
          this.style.overflow = 'hidden';
          this.appendChild(ripple);
          
          setTimeout(() => {
            ripple.remove();
          }, 600);
        }
      });
    });

    // Add CSS for ripple effect
    const style = document.createElement('style');
    style.textContent = `
      @keyframes ripple {
        to {
          transform: scale(4);
          opacity: 0;
        }
      }
    `;
    document.head.appendChild(style);
  </script>
</body>
</html>
