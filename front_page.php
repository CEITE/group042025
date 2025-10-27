<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VetCareQR - Pet Healthcare System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --pink-dark: #bf3b78;
            --pink-darker: #8c2859;
            --ink: #2a2e34;
        }
        
        body {
            font-family: system-ui, sans-serif;
        }
        
        .navbar-brand {
            font-weight: 800;
            color: var(--pink-dark);
        }
        
        .btn-pink {
            background: var(--pink-dark);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 0.8rem 2rem;
        }
        
        .btn-pink:hover {
            background: var(--pink-darker);
        }
        
        .btn-outline-pink {
            color: var(--pink-dark);
            border: 2px solid var(--pink-dark);
            border-radius: 50px;
            padding: 0.8rem 2rem;
        }
        
        .btn-outline-pink:hover {
            background: var(--pink-dark);
            color: white;
        }
        
        /* Modal Styles */
        .role-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 248, 252, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .role-modal.active {
            display: flex;
        }
        
        .role-container {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(191, 59, 120, 0.2);
            max-width: 800px;
            width: 90%;
        }
        
        .role-card {
            border: 2px solid #f1e6f0;
            border-radius: 20px;
            padding: 2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }
        
        .role-card:hover,
        .role-card.active {
            border-color: var(--pink-dark);
            background: #fff8fc;
        }
        
        .role-icon {
            width: 80px;
            height: 80px;
            background: rgba(191, 59, 120, 0.1);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            color: var(--pink-dark);
            font-size: 2rem;
        }
        
        .role-continue-btn {
            background: var(--pink-dark);
            color: white;
            border: none;
            border-radius: 50px;
            padding: 1rem 3rem;
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .role-continue-btn.active {
            opacity: 1;
            cursor: pointer;
        }
        
        .verification-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 248, 252, 0.95);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 10000;
        }
        
        .verification-modal.active {
            display: flex;
        }
        
        .verification-container {
            background: white;
            border-radius: 24px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(191, 59, 120, 0.2);
            max-width: 500px;
            width: 90%;
        }
        
        .section {
            padding: 100px 0;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg sticky-top bg-white">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="bi bi-qr-code me-2"></i>VetCareQR
            </a>
            <div class="navbar-nav ms-auto">
                <a class="btn btn-outline-pink me-2" href="#" onclick="showRoleModal()">Login</a>
                <a class="btn btn-pink" href="#">Get Started</a>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero section" style="background: linear-gradient(135deg, #fff8fc, #ffeaf3);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Smart Pet Healthcare Management</h1>
                    <p class="lead mb-4">Revolutionizing pet healthcare with QR technology and predictive analytics.</p>
                    <div class="d-flex gap-3">
                        <a class="btn btn-pink" href="#">Get Started Free</a>
                        <a class="btn btn-outline-pink" href="#" onclick="showRoleModal()">Login to Your Account</a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <div class="feature-icon-wrapper" style="width: 120px; height: 120px; background: rgba(191, 59, 120, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; margin: 0 auto 2rem;">
                        <i class="bi bi-phone" style="font-size: 3rem; color: #bf3b78;"></i>
                    </div>
                    <h3 style="color: #bf3b78;">Scan. Access. Care.</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Role Selection Modal -->
    <div class="role-modal" id="roleModal">
        <div class="role-container">
            <h1 class="text-center mb-4" style="color: #bf3b78;">Welcome to VetCareQR</h1>
            <p class="text-center mb-5">Please select your role to continue</p>
            
            <div class="row">
                <div class="col-md-4">
                    <div class="role-card" data-role="user" onclick="selectRole('user')">
                        <div class="role-icon">
                            <i class="bi bi-person"></i>
                        </div>
                        <h4 class="text-center">Pet Owner</h4>
                        <p class="text-center">Manage your pet's health records and medical history</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="role-card" data-role="veterinarian" onclick="selectRole('veterinarian')">
                        <div class="role-icon">
                            <i class="bi bi-heart-pulse"></i>
                        </div>
                        <h4 class="text-center">Veterinarian</h4>
                        <p class="text-center">Access patient records and provide professional care</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="role-card" data-role="admin" onclick="selectRole('admin')">
                        <div class="role-icon">
                            <i class="bi bi-shield-check"></i>
                        </div>
                        <h4 class="text-center">Administrator</h4>
                        <p class="text-center">Manage system users and platform settings</p>
                    </div>
                </div>
            </div>
            
            <div class="text-center mt-4">
                <button class="role-continue-btn" id="continueBtn" onclick="handleContinue()">
                    Continue to Login
                </button>
            </div>
        </div>
    </div>

    <!-- Verification Modal -->
    <div class="verification-modal" id="verificationModal">
        <div class="verification-container">
            <div class="text-center mb-4">
                <div style="background: rgba(191, 59, 120, 0.1); color: #bf3b78; padding: 0.75rem 1.5rem; border-radius: 50px; display: inline-flex; align-items: center; gap: 0.5rem;">
                    <i class="bi bi-shield-check"></i>
                    <span id="verificationRole">Veterinarian Verification</span>
                </div>
            </div>
            
            <h2 class="text-center mb-3" style="color: #bf3b78;" id="verificationTitle">Professional Verification Required</h2>
            <p class="text-center mb-4" id="verificationSubtitle">Please provide your credentials to verify your identity</p>
            
            <form id="verificationForm" onsubmit="handleVerification(event)">
                <div class="mb-3">
                    <label class="form-label">License Number</label>
                    <input type="text" class="form-control" placeholder="Enter your professional license number" required>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Clinic/Hospital Name</label>
                    <input type="text" class="form-control" placeholder="Enter your clinic or hospital name" required>
                </div>
                
                <div class="mb-3" id="adminCodeGroup" style="display: none;">
                    <label class="form-label">Administrator Access Code</label>
                    <input type="password" class="form-control" placeholder="Enter administrator access code" required>
                </div>
                
                <div class="d-flex gap-3 mt-4">
                    <button type="button" class="btn btn-secondary flex-fill" onclick="backToRoleSelection()">
                        <i class="bi bi-arrow-left me-2"></i>Back
                    </button>
                    <button type="submit" class="btn btn-pink flex-fill">
                        <i class="bi bi-shield-check me-2"></i>Verify & Continue
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Features Section -->
    <section id="features" class="section">
        <div class="container">
            <h2 class="text-center mb-5">How VetCareQR Works</h2>
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="feature-icon-wrapper" style="width: 70px; height: 70px; background: rgba(191, 59, 120, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="bi bi-qr-code-scan" style="font-size: 2rem; color: #bf3b78;"></i>
                    </div>
                    <h4>QR-based Pet Identities</h4>
                    <p>Unique QR codes for instant access to medical records.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon-wrapper" style="width: 70px; height: 70px; background: rgba(191, 59, 120, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="bi bi-bell-fill" style="font-size: 2rem; color: #bf3b78;"></i>
                    </div>
                    <h4>Vaccination Reminders</h4>
                    <p>Automated alerts for upcoming vaccinations.</p>
                </div>
                <div class="col-md-4 text-center">
                    <div class="feature-icon-wrapper" style="width: 70px; height: 70px; background: rgba(191, 59, 120, 0.1); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem;">
                        <i class="bi bi-clipboard-data" style="font-size: 2rem; color: #bf3b78;"></i>
                    </div>
                    <h4>Health Tracking</h4>
                    <p>Complete health history and treatment plans.</p>
                </div>
            </div>
        </div>
    </section>

    <script>
        // Global variable to track selected role
        let selectedRole = null;

        // Show role selection modal
        function showRoleModal() {
            console.log('Showing role modal');
            document.getElementById('roleModal').classList.add('active');
            document.body.style.overflow = 'hidden';
            
            // Reset selection
            selectedRole = null;
            document.getElementById('continueBtn').classList.remove('active');
            
            // Remove active class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('active');
            });
        }

        // Select role function
        function selectRole(role) {
            console.log('Role selected:', role);
            
            // Remove active class from all cards
            document.querySelectorAll('.role-card').forEach(card => {
                card.classList.remove('active');
            });
            
            // Add active class to selected card
            event.currentTarget.classList.add('active');
            
            // Set selected role
            selectedRole = role;
            
            // Enable continue button
            document.getElementById('continueBtn').classList.add('active');
        }

        // Handle continue button click
        function handleContinue() {
            console.log('Continue clicked, selected role:', selectedRole);
            
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

        // Show verification modal
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

        // Handle verification form submission
        function handleVerification(event) {
            event.preventDefault();
            alert('Verification successful! Redirecting to dashboard...');
            hideVerificationModal();
        }

        // Back to role selection
        function backToRoleSelection() {
            document.getElementById('verificationModal').classList.remove('active');
            showRoleModal();
        }

        // Hide verification modal
        function hideVerificationModal() {
            document.getElementById('verificationModal').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close modals when clicking outside
        document.addEventListener('click', function(event) {
            const roleModal = document.getElementById('roleModal');
            const verificationModal = document.getElementById('verificationModal');
            
            if (event.target === roleModal) {
                roleModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
            
            if (event.target === verificationModal) {
                verificationModal.classList.remove('active');
                document.body.style.overflow = 'auto';
            }
        });

        // Add some demo functionality to show it's working
        console.log('VetCareQR website loaded successfully!');
        console.log('Try clicking the Login button to see the role selection modal.');
    </script>
</body>
</html>
