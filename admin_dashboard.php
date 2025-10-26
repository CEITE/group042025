

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>VetCareQR — Admin Appointments</title>
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
  --shadow:0 10px 30px rgba(236,64,122,.1);
  --radius:1.25rem;
}

body{ 
  background: linear-gradient(180deg,#fff0f6 0%, #fff5f8 40%, #fff5f8 100%);
  color:var(--ink);
}


    /* Shell layout */
    .app-shell{display:grid; grid-template-columns: 260px 1fr; min-height:100vh; gap:20px; padding:24px}
    @media (max-width: 992px){ .app-shell{ grid-template-columns: 1fr; padding:12px } }

    
    /* Sidebar */
.sidebar {
  background: linear-gradient(180deg, var(--brand) 0%, var(--lav) 100%);
  color: #fff;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  position: relative;
  overflow: hidden;
}
.sidebar .brand { font-weight:800; color:#fff; }
.sidebar .nav-link { color:#ffe6ef; border-radius:12px; padding:12px 14px; font-weight:600; }
.sidebar .nav-link.active, 
.sidebar .nav-link:hover { background: rgba(255,255,255,.15); color:#fff; }
.sidebar .icon { 
  width:36px; 
  height:36px; 
  border-radius:12px; 
  display:grid; 
  place-items:center; 
  background: rgba(255,255,255,.2); 
  margin-right:10px 
}


    /* Topbar */
    .topbar{ background: var(--card); border-radius: var(--radius); box-shadow: var(--shadow); padding: 14px 18px; display:flex; align-items:center; gap:14px }
    .topbar .search{ flex:1; max-width: 480px }
    .form-control.search-input{ border:none; background:#f0f4f9; border-radius:12px; padding:10px 14px }

    /* Tabs + bar */
    .page-tabs{ display:flex; align-items:center; gap:16px; }
    .page-tabs .btn-tab{ background:transparent; border:none; padding:10px 6px; font-weight:700; color:var(--muted) }
    .page-tabs .btn-tab.active{ color: var(--ink); position:relative }
    .page-tabs .btn-tab.active::after{ content:""; position:absolute; inset:auto 0 -8px 0; margin:auto; width:58%; height:3px; background: var(--brand); border-radius:99px }

    .toolbar{ display:flex; align-items:center; gap:10px; }
    .toolbar .form-select, .toolbar .form-control{ border-radius:12px; }
    .btn-brand{ background: #ffb020; border:none; color:#0b1220; font-weight:800; border-radius:12px; padding:10px 16px }

    /* Cards */
    .card-soft{ background:var(--card); border:none; border-radius:var(--radius); box-shadow: var(--shadow) }

    /* KPI cards */
    .kpi{ display:flex; align-items:center; gap:16px; padding:16px }
    .kpi .bubble{ width:42px; height:42px; border-radius:14px; display:grid; place-items:center; font-size:18px }
    .kpi small{ color:var(--muted); font-weight:700; text-transform:uppercase; letter-spacing:.5px }
    .badge-dot{ display:inline-flex; align-items:center; gap:6px; font-weight:700; color:var(--muted) }
    .badge-dot::before{ content:""; width:8px; height:8px; border-radius:50%; background: currentColor }

    /* Booking meter */
    .meter-wrap{ display:flex; align-items:center; gap:14px }
    .meter{ --p:70; width:58px; height:58px; border-radius:50%; background: conic-gradient(var(--brand) calc(var(--p)*1%), #e8eef6 0); display:grid; place-items:center }
    .meter .hole{ width:44px; height:44px; background:#fff; border-radius:50%; display:grid; place-items:center; font-weight:800 }

    /* Section titles */
    .section-title{ font-weight:800; font-size:1.05rem; margin-bottom:8px }
    .subtle{ color:var(--muted) }

    /* Appointment cards */
    .appt{ border:1px solid #eef2f7; border-radius: 18px; padding:14px; transition:transform .2s ease, box-shadow .2s ease; background:#fff }
    .appt:hover{ transform: translateY(-2px); box-shadow: var(--shadow) }
    .appt .avatar{ width:42px; height:42px; border-radius:14px; object-fit:cover }
    .appt .chips{ display:flex; gap:6px; flex-wrap:wrap }
    .chip{ font-size:.72rem; font-weight:800; padding:6px 8px; border-radius:999px }
    .chip.confirmed{ background:#e8faf3; color:#0d9f6e }
    .chip.arrived{ background:#eef2ff; color:#635bff }
    .chip.pending{ background:#fff7e6; color:#c27400 }
    .chip.ongoing{ background:#eaf6ff; color:#1b74d1 }
    .chip.cancelled{ background:#ffeceb; color:#cc2e2e }

    .appt .btn{ border-radius:10px; font-weight:700 }
    .btn-plain{ background:#f0f4f9; border:none; color:#0b1220 }

    /* Today timeline legend */
    .legend{ display:flex; flex-wrap:wrap; gap:10px; font-size:.78rem; }
    .legend .legend-item{ display:inline-flex; align-items:center; gap:6px; color:var(--muted) }
    .legend .dot{ width:10px; height:10px; border-radius:50% }
    .dot.no-show{ background:#9aa5b1 }
    .dot.completed{ background:#16a34a }
    .dot.pending{ background:#f59e0b }
    .dot.ongoing{ background:#3b82f6 }
    .dot.confirmed{ background:#10b981 }
    .dot.arrived{ background:#8b5cf6 }
    .dot.cancelled{ background:#ef4444 }

    /* Small helpers */
    .text-600{ font-weight:600 }
    .text-800{ font-weight:800 }
    .muted{ color:var(--muted) }
  </style>
</head>
<body>
  <div class="app-shell">
    <!-- Sidebar -->
    <aside class="sidebar p-3">
      <div class="d-flex align-items-center mb-4">
        <div class="icon me-2"><i class="fa-solid fa-paw"></i></div>
        <div class="brand h4 mb-0">VetCareQR</div>
      </div>
      <nav class="nav flex-column gap-1">
        <a class="nav-link d-flex align-items-center active" href="#"><span class="icon"><i class="fa-solid fa-gauge"></i></span>Dashboard</a>
        <a class="nav-link d-flex align-items-center" href="#"><span class="icon"><i class="fa-solid fa-calendar-check"></i></span>Appointments</a>
        <a class="nav-link d-flex align-items-center" href="#"><span class="icon"><i class="fa-solid fa-users"></i></span>Customers</a>
        <a class="nav-link d-flex align-items-center" href="#"><span class="icon"><i class="fa-solid fa-chart-line"></i></span>Reports</a>
        <a class="nav-link d-flex align-items-center" href="#"><span class="icon"><i class="fa-solid fa-gear"></i></span>Settings</a>
      </nav>
    </aside>

    <!-- Main -->
    <main class="d-flex flex-column gap-3">
      <!-- Topbar -->
      <div class="topbar">
        <div class="dropdown">
          <button class="btn btn-light dropdown-toggle fw-bold" data-bs-toggle="dropdown"><i class="fa-solid fa-clinic-medical me-2"></i>Four Paws Vet Clinic</button>
          <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Four Paws Vet Clinic</a></li>
            <li><a class="dropdown-item" href="#">PetCare Laguna</a></li>
          </ul>
        </div>
        <div class="search ms-auto">
          <div class="input-group">
            <span class="input-group-text bg-transparent border-0"><i class="fa-solid fa-magnifying-glass"></i></span>
            <input class="form-control search-input" placeholder="Search appointments, owners, pets…" />
          </div>
        </div>
        <button class="btn btn-light rounded-circle"><i class="fa-regular fa-bell"></i></button>
        <div class="dropdown">
          <button class="btn btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
            <img src="https://i.pravatar.cc/64?img=12" class="rounded-circle" width="32" height="32" alt="admin" />
            <span class="fw-bold">Hello, Vicky</span>
          </button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#">Profile</a></li>
            <li><a class="dropdown-item" href="admin-logout.php">Logout</a></li>
          </ul>
        </div>
      </div>

      <!-- Tabs & Toolbar -->
      <div class="card-soft p-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div class="page-tabs">
          <button class="btn-tab active">Appointments</button>
          <button class="btn-tab">Calendar</button>
          <button class="btn-tab">Reminders</button>
        </div>
        <div class="toolbar">
          <button class="btn btn-light" id="prevDay"><i class="fa-solid fa-chevron-left"></i></button>
          <input type="date" class="form-control" id="datePicker" style="width: 180px"/>
          <button class="btn btn-light" id="nextDay"><i class="fa-solid fa-chevron-right"></i></button>
          <select class="form-select" style="width: 210px">
            <option>Dr. Aman Sharma</option>
            <option>Dr. Maria Santos</option>
            <option>Any Veterinarian</option>
          </select>
          <button class="btn-brand" id="addAppointment"><i class="fa-solid fa-plus me-2"></i>Add Appointment</button>
        </div>
      </div>

      <!-- KPIs Row -->
      <div class="row g-3">
        <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
          <div class="card-soft">
            <div class="kpi">
              <div class="bubble" style="background:#eaf2ff;color:#1b74d1"><i class="fa-solid fa-calendar-days"></i></div>
              <div class="flex-grow-1">
                <small>Total Appointments</small>
                <div class="d-flex align-items-end gap-2">
                  <h3 class="mb-0 text-800">11</h3>
                  <span class="badge-dot" style="color:#9aa5b1">Today</span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
          <div class="card-soft">
            <div class="kpi">
              <div class="bubble" style="background:#e8faf3;color:#0d9f6e"><i class="fa-solid fa-video"></i></div>
              <div class="flex-grow-1">
                <small>Online Consultation</small>
                <div class="d-flex align-items-end gap-2"><h3 class="mb-0 text-800">10</h3><span class="badge-dot" style="color:#9aa5b1">Today</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
          <div class="card-soft">
            <div class="kpi">
              <div class="bubble" style="background:#fff0f5;color:#c2417a"><i class="fa-solid fa-stethoscope"></i></div>
              <div class="flex-grow-1">
                <small>Clinic Visits</small>
                <div class="d-flex align-items-end gap-2"><h3 class="mb-0 text-800">02</h3><span class="badge-dot" style="color:#9aa5b1">Today</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
          <div class="card-soft">
            <div class="kpi">
              <div class="bubble" style="background:#fff7e6;color:#b45309"><i class="fa-solid fa-hourglass-half"></i></div>
              <div class="flex-grow-1">
                <small>Pending Approval</small>
                <div class="d-flex align-items-end gap-2"><h3 class="mb-0 text-800">02</h3><span class="badge-dot" style="color:#9aa5b1">Today</span></div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
          <div class="card-soft p-3">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="small text-uppercase text-600 subtle">Booking Meter</div>
                <div class="muted">Occupancy</div>
              </div>
              <div class="meter-wrap">
                <div class="meter" id="bookingMeter" style="--p:70">
                  <div class="hole"><span>70%</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Appointment Lists -->
      <div class="row g-3">
        <div class="col-12">
          <div class="card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="section-title">Upcoming Appointments</div>
              <div class="subtle">(5)</div>
            </div>
            <div class="row g-3">
              <!-- appt item -->
              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=200&q=80" alt="Ben"/>
                    <div>
                      <div class="fw-bold">Ben <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip confirmed">Confirmed</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>12:30 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">View Details</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1558944351-c0ff1b0b6cf2?w=200&q=80" alt="Alex"/>
                    <div>
                      <div class="fw-bold">Alex <span class="muted">· Clinic Visit</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip arrived">Arrived</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>01:00 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-outline-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1530281691997-4a9f2663a6a6?w=200&q=80" alt="Monti"/>
                    <div>
                      <div class="fw-bold">Monti <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip ongoing">Ongoing</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>01:30 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1505252585461-04db1eb84625?w=200&q=80" alt="Ben"/>
                    <div>
                      <div class="fw-bold">Ben <span class="muted">· Clinic Visit</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip arrived">Arrived</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>02:00 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-outline-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="section-title">Live Appointments</div>
              <div class="subtle">(3)</div>
            </div>
            <div class="row g-3">
              <!-- Copy of items with different badges -->
              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1574158622682-e40e69881006?w=200&q=80" alt="Ben"/>
                    <div>
                      <div class="fw-bold">Ben <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip pending">Pending Approval</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>02:30 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">Approve</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1555685812-4b943f1cb0eb?w=200&q=80" alt="Alex"/>
                    <div>
                      <div class="fw-bold">Alex <span class="muted">· Clinic Visit</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip pending">Pending Approval</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>02:50 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">Approve</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1556784344-ad913c73c7f5?w=200&q=80" alt="Ben2"/>
                    <div>
                      <div class="fw-bold">Ben <span class="muted">· Clinic Visit</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip pending">Pending Approval</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>02:00 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">Approve</button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>

        <div class="col-12">
          <div class="card-soft p-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <div class="section-title">Today</div>
              <div class="legend">
                <span class="legend-item"><span class="dot no-show"></span>No Show</span>
                <span class="legend-item"><span class="dot completed"></span>Completed</span>
                <span class="legend-item"><span class="dot pending"></span>Pending Approval</span>
                <span class="legend-item"><span class="dot ongoing"></span>Ongoing</span>
                <span class="legend-item"><span class="dot confirmed"></span>Confirmed</span>
                <span class="legend-item"><span class="dot arrived"></span>Arrived</span>
                <span class="legend-item"><span class="dot cancelled"></span>Cancelled</span>
              </div>
            </div>

            <div class="row g-3">
              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1543466835-00a7907e9de1?w=200&q=80" alt="Alex"/>
                    <div>
                      <div class="fw-bold">Alex <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip">No Show</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>10:30 AM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-outline-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1530281691997-4a9f2663a6a6?w=200&q=80" alt="Monti"/>
                    <div>
                      <div class="fw-bold">Monti <span class="muted">· Clinic Visit</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip" style="background:#e9d5ff;color:#6b21a8">Completed</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>12:00 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-outline-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1525253013412-55c1a69a5738?w=200&q=80" alt="Mads"/>
                    <div>
                      <div class="fw-bold">Mads <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip ongoing">Ongoing</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>01:10 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

              <div class="col-12 col-md-6 col-xl-4 col-xxl-3">
                <div class="appt">
                  <div class="d-flex align-items-center mb-2">
                    <img class="avatar me-2" src="https://images.unsplash.com/photo-1560807707-8cc77767d783?w=200&q=80" alt="Pluto"/>
                    <div>
                      <div class="fw-bold">Pluto <span class="muted">· Online Consultation</span></div>
                      <div class="small muted">Owner: Shilpa Singh</div>
                    </div>
                    <span class="ms-auto chip cancelled">Cancelled</span>
                  </div>
                  <div class="d-flex justify-content-between align-items-center mt-2">
                    <div class="muted small"><i class="fa-regular fa-clock me-1"></i>02:10 PM</div>
                    <div class="d-flex gap-2">
                      <button class="btn btn-plain btn-sm">Reschedule</button>
                      <button class="btn btn-outline-dark btn-sm">Attend</button>
                    </div>
                  </div>
                </div>
              </div>

            </div>
          </div>
        </div>
      </div>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Date toolbar controls
    const datePicker = document.getElementById('datePicker');
    const today = new Date();
    datePicker.valueAsDate = today;

    document.getElementById('prevDay').addEventListener('click', ()=>{
      const d = new Date(datePicker.value); d.setDate(d.getDate()-1); datePicker.valueAsDate = d;
    });
    document.getElementById('nextDay').addEventListener('click', ()=>{
      const d = new Date(datePicker.value); d.setDate(d.getDate()+1); datePicker.valueAsDate = d;
    });

    // Example: set booking meter dynamically
    function setOccupancy(p){
      const el = document.getElementById('bookingMeter');
      el.style.setProperty('--p', p);
      el.querySelector('.hole span').textContent = p + '%';
    }
    // setOccupancy(82); // demo

    // Hook these buttons to your modals / routes later
    document.getElementById('addAppointment').addEventListener('click', ()=>{
      alert('Open your Add Appointment modal here.');
    });
  </script>
</body>
</html>
