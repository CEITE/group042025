<?php
session_start();
include("conn.php");

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check database connection
if (!$conn) {
    die("Database connection failed: " . htmlspecialchars($conn->connect_error));
}

// Simple session check - same as vet_dashboard.php
if (!isset($_SESSION['user_id'])) {
    header("Location: login_vet.php");
    exit();
}

// Check if user has the correct role - use same logic as vet_dashboard.php
if ($_SESSION['role'] !== 'vet') {
    session_unset();
    session_destroy();
    header("Location: login_vet.php");
    exit();
}

// Basic session security - same as vet_dashboard.php
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) {
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

$vet_id = $_SESSION['user_id'];

// Fetch vet info
$stmt = $conn->prepare("SELECT name, role, email, profile_picture FROM users WHERE user_id = ?");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("i", $vet_id);
if (!$stmt->execute()) {
    die("Execute failed: " . $stmt->error);
}
$vet = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$vet) {
    die("Vet not found!");
}

// Set default profile picture
$profile_picture = !empty($vet['profile_picture']) ? $vet['profile_picture'] : "https://i.pravatar.cc/100?u=" . urlencode($vet['name']);

// Check if recipient_id column exists
$check_recipient = $conn->query("SHOW COLUMNS FROM email_logs LIKE 'recipient_id'");
$has_recipient_column = ($check_recipient->num_rows > 0);

// Handle sending new message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $recipient_id = $_POST['recipient_id'];
    $subject = trim($_POST['subject']);
    $message = trim($_POST['message']);
    
    if (empty($subject) || empty($message)) {
        $_SESSION['error'] = "Subject and message are required.";
    } else {
        if ($has_recipient_column) {
            // New structure with recipient_id
            $insert_stmt = $conn->prepare("
                INSERT INTO email_logs (vet_id, sent_by, recipient_id, subject, message, email_type, is_read, status) 
                VALUES (?, ?, ?, ?, ?, 'message', 0, 'sent')
            ");
            if ($insert_stmt) {
                $insert_stmt->bind_param("iiiss", $vet_id, $vet_id, $recipient_id, $subject, $message);
            }
        } else {
            // Fallback to current structure (vet_id acts as both vet_id and recipient)
            $insert_stmt = $conn->prepare("
                INSERT INTO email_logs (vet_id, sent_by, subject, message, email_type, is_read, status) 
                VALUES (?, ?, ?, ?, 'message', 0, 'sent')
            ");
            if ($insert_stmt) {
                $insert_stmt->bind_param("iiss", $vet_id, $vet_id, $subject, $message);
            }
        }
        
        if ($insert_stmt && $insert_stmt->execute()) {
            $_SESSION['success'] = "Message sent successfully!";
        } else {
            $_SESSION['error'] = "Error sending message: " . ($insert_stmt ? $insert_stmt->error : $conn->error);
        }
        if ($insert_stmt) $insert_stmt->close();
    }
    
    header("Location: vet_messages.php");
    exit();
}

// Handle mark as read
if (isset($_GET['mark_read'])) {
    $email_id = $_GET['mark_read'];
    $mark_stmt = $conn->prepare("UPDATE email_logs SET is_read = 1 WHERE id = ? AND vet_id = ?");
    if ($mark_stmt) {
        $mark_stmt->bind_param("ii", $email_id, $vet_id);
        $mark_stmt->execute();
        $mark_stmt->close();
        $_SESSION['success'] = "Message marked as read!";
    }
    header("Location: vet_messages.php");
    exit();
}

// Handle view message (get full message content)
if (isset($_GET['view_message'])) {
    $email_id = $_GET['view_message'];
    
    // Mark as read when viewing
    $mark_stmt = $conn->prepare("UPDATE email_logs SET is_read = 1 WHERE id = ? AND vet_id = ?");
    if ($mark_stmt) {
        $mark_stmt->bind_param("ii", $email_id, $vet_id);
        $mark_stmt->execute();
        $mark_stmt->close();
    }
    
    // Get the full message details
    if ($has_recipient_column) {
        $view_stmt = $conn->prepare("
            SELECT el.*, 
                   u.name as sender_name,
                   u.role as sender_role,
                   u.email as sender_email,
                   r.name as recipient_name,
                   r.role as recipient_role,
                   r.email as recipient_email
            FROM email_logs el 
            LEFT JOIN users u ON el.sent_by = u.user_id 
            LEFT JOIN users r ON el.recipient_id = r.user_id
            WHERE el.id = ? AND el.vet_id = ?
        ");
    } else {
        $view_stmt = $conn->prepare("
            SELECT el.*, 
                   u.name as sender_name,
                   u.role as sender_role,
                   u.email as sender_email
            FROM email_logs el 
            LEFT JOIN users u ON el.sent_by = u.user_id 
            WHERE el.id = ? AND el.vet_id = ?
        ");
    }
    
    if ($view_stmt) {
        $view_stmt->bind_param("ii", $email_id, $vet_id);
        $view_stmt->execute();
        $message_details = $view_stmt->get_result()->fetch_assoc();
        $view_stmt->close();
        
        if ($message_details) {
            // Return JSON response for AJAX
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $message_details
            ]);
            exit();
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['success' => false]);
    exit();
}

// Handle delete message
if (isset($_GET['delete'])) {
    $email_id = $_GET['delete'];
    $delete_stmt = $conn->prepare("DELETE FROM email_logs WHERE id = ? AND vet_id = ?");
    if ($delete_stmt) {
        $delete_stmt->bind_param("ii", $email_id, $vet_id);
        $delete_stmt->execute();
        $delete_stmt->close();
        $_SESSION['success'] = "Message deleted successfully!";
    }
    header("Location: vet_messages.php");
    exit();
}

// Fetch all messages for this vet
if ($has_recipient_column) {
    // With recipient_id column
    $messages_stmt = $conn->prepare("
        SELECT el.*, 
               u.name as sender_name,
               u.role as sender_role,
               r.name as recipient_name,
               r.role as recipient_role
        FROM email_logs el 
        LEFT JOIN users u ON el.sent_by = u.user_id 
        LEFT JOIN users r ON el.recipient_id = r.user_id
        WHERE el.vet_id = ? 
        ORDER BY el.sent_at DESC
    ");
} else {
    // Current structure without recipient_id
    $messages_stmt = $conn->prepare("
        SELECT el.*, 
               u.name as sender_name,
               u.role as sender_role
        FROM email_logs el 
        LEFT JOIN users u ON el.sent_by = u.user_id 
        WHERE el.vet_id = ? 
        ORDER BY el.sent_at DESC
    ");
}

if (!$messages_stmt) {
    die("Prepare failed: " . $conn->error);
}
$messages_stmt->bind_param("i", $vet_id);
$messages_stmt->execute();
$messages = $messages_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$messages_stmt->close();

// Fetch unread count
$unread_stmt = $conn->prepare("SELECT COUNT(*) as unread_count FROM email_logs WHERE vet_id = ? AND is_read = 0");
if ($unread_stmt) {
    $unread_stmt->bind_param("i", $vet_id);
    $unread_stmt->execute();
    $unread_result = $unread_stmt->get_result()->fetch_assoc();
    $unread_count = $unread_result['unread_count'];
    $unread_stmt->close();
} else {
    $unread_count = 0;
}

// Fetch users for sending messages (admins and other vets)
$users_stmt = $conn->prepare("
    SELECT user_id, name, email, role 
    FROM users 
    WHERE role IN ('admin', 'vet') AND user_id != ?
    ORDER BY role, name
");
if ($users_stmt) {
    $users_stmt->bind_param("i", $vet_id);
    $users_stmt->execute();
    $users = $users_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $users_stmt->close();
} else {
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages - Vet Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #0ea5e9;
            --primary-dark: #0284c7;
            --primary-light: #e0f2fe;
            --secondary: #8b5cf6;
            --light: #f0f9ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --gray: #6b7280;
            --radius: 16px;
            --shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        body {
            font-family: 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--light) 0%, var(--primary-light) 100%);
            margin: 0;
            color: var(--dark);
            min-height: 100vh;
        }
        
        .wrapper {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 260px;
            background: var(--primary-light);
            padding: 2rem 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
        }
        
        .sidebar .brand {
            font-weight: 800;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 2rem;
            color: var(--primary-dark);
        }
        
        .sidebar .profile {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .sidebar .profile img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            margin-bottom: .5rem;
            border: 3px solid var(--primary);
            object-fit: cover;
        }
        
        .sidebar a {
            display: flex;
            align-items: center;
            padding: 12px 14px;
            border-radius: 12px;
            margin: .3rem 0;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            transition: .2s;
        }
        
        .sidebar a .icon {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            background: rgba(255,255,255,.6);
            margin-right: 10px;
        }
        
        .sidebar a.active, .sidebar a:hover {
            background: var(--light);
            color: var(--primary-dark);
        }
        
        .sidebar .logout {
            margin-top: auto;
            font-weight: 600;
            color: #fff;
            background: linear-gradient(135deg, var(--danger), #e74c3c);
            text-align: center;
            padding: 10px;
            border-radius: 10px;
            border: none;
        }

        .main-content {
            flex: 1;
            padding: 1.5rem 2rem;
            overflow-y: auto;
        }
        
        .topbar {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-custom {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
            border: none;
        }
        
        .message-item {
            border-left: 4px solid var(--primary);
            padding: 1rem;
            margin-bottom: 1rem;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .message-item.unread {
            background: var(--primary-light);
            border-left-color: var(--primary-dark);
        }
        
        .message-item:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .message-item.read {
            opacity: 0.8;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, var(--danger), #c0392b);
        }
        
        .badge-vet {
            background: linear-gradient(135deg, var(--primary), #2980b9);
        }
        
        .badge-owner {
            background: linear-gradient(135deg, var(--success), #27ae60);
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--gray);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }
        
        .message-preview {
            color: var(--gray);
            line-height: 1.4;
        }
        
        .message-content {
            white-space: pre-wrap;
            line-height: 1.6;
            padding: 1rem;
            background: var(--light);
            border-radius: 8px;
            margin-top: 1rem;
        }
        
        .message-header {
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }
        
        @media (max-width: 768px) {
            .wrapper {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                padding: 1rem;
            }
            
            .topbar {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<div class="wrapper">
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="brand">
            <i class="fa-solid fa-paw"></i> BrightView<br>Veterinary Clinic
        </div>
        <div class="profile">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" 
                 alt="Vet"
                 onerror="this.src='https://i.pravatar.cc/100?u=<?php echo urlencode($vet['name']); ?>'">
            <h6>Dr. <?php echo htmlspecialchars($vet['name']); ?></h6>
            <small class="text-muted"><?php echo htmlspecialchars($vet['role']); ?></small>
        </div>

        <a href="vet_dashboard.php">
            <div class="icon"><i class="fa-solid fa-gauge"></i></div> Dashboard
        </a>
        <a href="vet_appointments.php">
            <div class="icon"><i class="fa-solid fa-calendar-check"></i></div> Appointments
        </a>
        <a href="vet_patients.php">
            <div class="icon"><i class="fa-solid fa-dog"></i></div> Patients
        </a>
        <a href="vet_records.php">
            <div class="icon"><i class="fa-solid fa-file-medical"></i></div> Medical Records
        </a>
        <a href="vet_messages.php" class="active">
            <div class="icon"><i class="fa-solid fa-envelope"></i></div> Messages
        </a>
        <a href="vet_settings.php">
            <div class="icon"><i class="fa-solid fa-gear"></i></div> Settings
        </a>
        <a href="logout.php" class="logout">
            <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
        </a>
    </div>

    <div class="main-content">
        <!-- Topbar -->
        <div class="topbar">
            <div>
                <h5 class="mb-0">Messages</h5>
                <small class="text-muted">Manage your communications</small>
            </div>
            <div class="d-flex align-items-center gap-3">
                <div class="text-end">
                    <strong id="currentDate"></strong><br>
                    <small id="currentTime"></small>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Messages List -->
            <div class="col-lg-8">
                <div class="card-custom">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h4 class="mb-0"><i class="fas fa-inbox me-2"></i>Message Inbox</h4>
                        <span class="badge bg-primary"><?php echo count($messages); ?> total</span>
                    </div>
                    
                    <?php if (empty($messages)): ?>
                        <div class="empty-state">
                            <i class="fas fa-envelope-open fa-2x mb-3"></i>
                            <h5>No Messages</h5>
                            <p class="text-muted">You don't have any messages yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="messages-list">
                            <?php foreach ($messages as $message): ?>
                                <div class="message-item <?php echo ($message['is_read'] == 0) ? 'unread' : 'read'; ?>" 
                                     onclick="viewMessage(<?php echo $message['id']; ?>)">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="flex-grow-1">
                                            <div class="d-flex align-items-center mb-2">
                                                <h6 class="mb-0 me-2"><?php echo htmlspecialchars($message['subject']); ?></h6>
                                                <?php if ($message['is_read'] == 0): ?>
                                                    <span class="badge bg-danger">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="message-preview mb-2">
                                                <?php echo htmlspecialchars(substr($message['message'], 0, 150)); ?>
                                                <?php echo strlen($message['message']) > 150 ? '...' : ''; ?>
                                            </p>
                                            <div class="d-flex align-items-center gap-3 flex-wrap">
                                                <small class="text-muted">
                                                    <strong>From:</strong> 
                                                    <?php echo htmlspecialchars($message['sender_name'] ?? 'System'); ?>
                                                    <span class="badge badge-<?php echo $message['sender_role'] ?? 'admin'; ?> ms-2">
                                                        <?php echo ucfirst($message['sender_role'] ?? 'Admin'); ?>
                                                    </span>
                                                </small>
                                                <?php if ($has_recipient_column && isset($message['recipient_name'])): ?>
                                                    <small class="text-muted">
                                                        <strong>To:</strong> 
                                                        <?php echo htmlspecialchars($message['recipient_name'] ?? 'You'); ?>
                                                    </small>
                                                <?php endif; ?>
                                                <small class="text-muted">
                                                    <i class="far fa-clock me-1"></i>
                                                    <?php echo date('M j, Y g:i A', strtotime($message['sent_at'])); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                    type="button" 
                                                    data-bs-toggle="dropdown"
                                                    onclick="event.stopPropagation()">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <?php if ($message['is_read'] == 0): ?>
                                                    <li>
                                                        <a class="dropdown-item" href="vet_messages.php?mark_read=<?php echo $message['id']; ?>">
                                                            <i class="fas fa-check me-2"></i>Mark as Read
                                                        </a>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" 
                                                       href="vet_messages.php?delete=<?php echo $message['id']; ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this message?')">
                                                        <i class="fas fa-trash me-2"></i>Delete
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Compose Message -->
            <div class="col-lg-4">
                <div class="card-custom">
                    <h5 class="mb-3"><i class="fas fa-edit me-2"></i>Compose Message</h5>
                    <form method="POST" action="vet_messages.php">
                        <div class="mb-3">
                            <label for="recipient_id" class="form-label">Recipient</label>
                            <select class="form-select" id="recipient_id" name="recipient_id" required>
                                <option value="">Select Recipient</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['user_id']; ?>">
                                        <?php echo htmlspecialchars($user['name']); ?> (<?php echo ucfirst($user['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required 
                                   placeholder="Enter message subject...">
                        </div>
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="6" required 
                                      placeholder="Type your message here..."></textarea>
                        </div>
                        <button type="submit" name="send_message" class="btn btn-primary w-100">
                            <i class="fas fa-paper-plane me-2"></i>Send Message
                        </button>
                    </form>
                </div>

                <!-- Quick Stats -->
                <div class="card-custom mt-4">
                    <h5 class="mb-3"><i class="fas fa-chart-bar me-2"></i>Message Stats</h5>
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-primary"><?php echo count($messages); ?></h4>
                                <small>Total Messages</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-warning"><?php echo $unread_count; ?></h4>
                                <small>Unread</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-success"><?php echo count($users); ?></h4>
                                <small>Contacts</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 border rounded">
                                <h4 class="mb-0 text-info">Today</h4>
                                <small><?php echo date('M j'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Message View Modal -->
<div class="modal fade" id="messageViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="messageModalTitle">Message Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="messageModalContent">
                    <div class="message-header">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <h6 id="messageSubject" class="mb-1"></h6>
                                <div class="d-flex align-items-center gap-3 flex-wrap">
                                    <small class="text-muted">
                                        <strong>From:</strong> 
                                        <span id="messageSender"></span>
                                        <span id="messageSenderBadge" class="badge ms-2"></span>
                                    </small>
                                    <small class="text-muted">
                                        <strong>To:</strong> 
                                        <span id="messageRecipient">You</span>
                                    </small>
                                    <small class="text-muted">
                                        <i class="far fa-clock me-1"></i>
                                        <span id="messageDate"></span>
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="message-content" id="messageFullContent">
                        <!-- Message content will be loaded here -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" onclick="replyToMessage()">
                    <i class="fas fa-reply me-2"></i>Reply
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        updateDateTime();
        setInterval(updateDateTime, 60000);
    });

    function updateDateTime() {
        const now = new Date();
        const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
        document.getElementById('currentDate').textContent = now.toLocaleDateString('en-US', options);
        document.getElementById('currentTime').textContent = now.toLocaleTimeString('en-US');
    }

    function viewMessage(messageId) {
        // Show loading state
        document.getElementById('messageModalTitle').textContent = 'Loading Message...';
        document.getElementById('messageFullContent').innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Loading message...</p></div>';
        
        // Show modal immediately
        const modal = new bootstrap.Modal(document.getElementById('messageViewModal'));
        modal.show();
        
        // Fetch message details via AJAX
        fetch(`vet_messages.php?view_message=${messageId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.message) {
                    const message = data.message;
                    
                    // Update modal content
                    document.getElementById('messageModalTitle').textContent = 'Message Details';
                    document.getElementById('messageSubject').textContent = message.subject;
                    document.getElementById('messageSender').textContent = message.sender_name || 'System';
                    document.getElementById('messageDate').textContent = new Date(message.sent_at).toLocaleString();
                    document.getElementById('messageFullContent').textContent = message.message;
                    
                    // Update sender badge
                    const senderBadge = document.getElementById('messageSenderBadge');
                    senderBadge.textContent = message.sender_role ? message.sender_role.charAt(0).toUpperCase() + message.sender_role.slice(1) : 'Admin';
                    senderBadge.className = 'badge ms-2 badge-' + (message.sender_role || 'admin');
                    
                    // Update recipient if available
                    if (message.recipient_name) {
                        document.getElementById('messageRecipient').textContent = message.recipient_name;
                    }
                    
                    // Remove "New" badge from the message item in the list
                    const messageItem = document.querySelector(`.message-item[onclick="viewMessage(${messageId})"]`);
                    if (messageItem) {
                        messageItem.classList.remove('unread');
                        messageItem.classList.add('read');
                        const newBadge = messageItem.querySelector('.badge.bg-danger');
                        if (newBadge) {
                            newBadge.remove();
                        }
                    }
                } else {
                    document.getElementById('messageModalTitle').textContent = 'Error';
                    document.getElementById('messageFullContent').innerHTML = '<div class="alert alert-danger">Failed to load message. Please try again.</div>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('messageModalTitle').textContent = 'Error';
                document.getElementById('messageFullContent').innerHTML = '<div class="alert alert-danger">An error occurred while loading the message.</div>';
            });
    }

    function replyToMessage() {
        const modal = bootstrap.Modal.getInstance(document.getElementById('messageViewModal'));
        modal.hide();
        
        // Get message details for reply
        const subject = document.getElementById('messageSubject').textContent;
        const sender = document.getElementById('messageSender').textContent;
        
        // Set the recipient (the original sender)
        // This would need additional logic to get the sender's user_id
        // For now, we'll just focus the compose form
        document.getElementById('subject').value = 'Re: ' + subject;
        document.getElementById('message').focus();
        
        // Scroll to compose form
        document.querySelector('.card-custom form').scrollIntoView({ 
            behavior: 'smooth' 
        });
    }

    // Auto-close alerts after 5 seconds
    setTimeout(() => {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);
</script>
</body>
</html>
