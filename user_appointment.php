<?php
session_start();
require_once 'db_connection.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pet_name = $_POST['pet_name'];
    $pet_type = $_POST['pet_type'];
    $appointment_date = $_POST['appointment_date'];
    $appointment_time = $_POST['appointment_time'];
    $service_type = $_POST['service_type'];
    $reason = $_POST['reason'];
    $user_id = $_SESSION['user_id'];
    
    // Validate date and time
    $appointment_datetime = $appointment_date . ' ' . $appointment_time;
    $current_datetime = date('Y-m-d H:i:s');
    
    if (strtotime($appointment_datetime) <= strtotime($current_datetime)) {
        $error = "Appointment date and time must be in the future.";
    } else {
        // Insert appointment
        $stmt = $pdo->prepare("INSERT INTO appointments (user_id, pet_name, pet_type, appointment_date, appointment_time, service_type, reason, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')");
        
        if ($stmt->execute([$user_id, $pet_name, $pet_type, $appointment_date, $appointment_time, $service_type, $reason])) {
            $success = "Appointment scheduled successfully! You will be notified when confirmed.";
            
            // Create notification for vet
            createVetNotification($pdo, "New appointment request from " . $_SESSION['user_name'] . " for " . $pet_name);
        } else {
            $error = "Failed to schedule appointment. Please try again.";
        }
    }
}

// Function to create vet notification
function createVetNotification($pdo, $message) {
    $stmt = $pdo->prepare("INSERT INTO vet_notifications (message, is_read, created_at) VALUES (?, 0, NOW())");
    $stmt->execute([$message]);
}

// Get user's appointments
$stmt = $pdo->prepare("SELECT * FROM appointments WHERE user_id = ? ORDER BY appointment_date DESC, appointment_time DESC");
$stmt->execute([$_SESSION['user_id']]);
$appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Appointment</title>
    <style>
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; }
        button:hover { background: #0056b3; }
        .alert { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .appointment-list { margin-top: 30px; }
        .appointment-item { border: 1px solid #ddd; padding: 15px; margin: 10px 0; border-radius: 4px; }
        .status-pending { color: #856404; background: #fff3cd; }
        .status-confirmed { color: #155724; background: #d4edda; }
        .status-cancelled { color: #721c24; background: #f8d7da; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Schedule Vet Appointment</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert error"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="pet_name">Pet Name *</label>
                <input type="text" id="pet_name" name="pet_name" required>
            </div>
            
            <div class="form-group">
                <label for="pet_type">Pet Type *</label>
                <select id="pet_type" name="pet_type" required>
                    <option value="">Select Pet Type</option>
                    <option value="dog">Dog</option>
                    <option value="cat">Cat</option>
                    <option value="bird">Bird</option>
                    <option value="rabbit">Rabbit</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="appointment_date">Appointment Date *</label>
                <input type="date" id="appointment_date" name="appointment_date" min="<?php echo date('Y-m-d'); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="appointment_time">Appointment Time *</label>
                <input type="time" id="appointment_time" name="appointment_time" required>
            </div>
            
            <div class="form-group">
                <label for="service_type">Service Type *</label>
                <select id="service_type" name="service_type" required>
                    <option value="">Select Service</option>
                    <option value="checkup">Regular Checkup</option>
                    <option value="vaccination">Vaccination</option>
                    <option value="grooming">Grooming</option>
                    <option value="surgery">Surgery</option>
                    <option value="emergency">Emergency</option>
                    <option value="other">Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="reason">Reason for Visit *</label>
                <textarea id="reason" name="reason" rows="4" required placeholder="Please describe the reason for the appointment..."></textarea>
            </div>
            
            <button type="submit">Schedule Appointment</button>
        </form>

        <div class="appointment-list">
            <h2>Your Appointments</h2>
            <?php if (empty($appointments)): ?>
                <p>No appointments scheduled.</p>
            <?php else: ?>
                <?php foreach ($appointments as $appointment): ?>
                    <div class="appointment-item status-<?php echo $appointment['status']; ?>">
                        <h3><?php echo htmlspecialchars($appointment['pet_name']); ?> (<?php echo ucfirst($appointment['pet_type']); ?>)</h3>
                        <p><strong>Date:</strong> <?php echo date('M j, Y', strtotime($appointment['appointment_date'])); ?></p>
                        <p><strong>Time:</strong> <?php echo date('g:i A', strtotime($appointment['appointment_time'])); ?></p>
                        <p><strong>Service:</strong> <?php echo ucfirst($appointment['service_type']); ?></p>
                        <p><strong>Status:</strong> <span class="status-<?php echo $appointment['status']; ?>"><?php echo ucfirst($appointment['status']); ?></span></p>
                        <p><strong>Reason:</strong> <?php echo htmlspecialchars($appointment['reason']); ?></p>
                        
                        <div class="appointment-actions">
                            <?php if ($appointment['status'] == 'pending'): ?>
                                <button onclick="cancelAppointment(<?php echo $appointment['id']; ?>)">Cancel Appointment</button>
                            <?php elseif ($appointment['status'] == 'confirmed'): ?>
                                <button onclick="viewAppointmentDetails(<?php echo $appointment['id']; ?>)">View Details</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
    // Appointment Management Functions
    function confirmAppointment(appointmentId) {
        if (confirm('Are you sure you want to confirm this appointment?')) {
            updateAppointmentStatus(appointmentId, 'confirmed');
        }
    }

    function cancelAppointment(appointmentId) {
        if (confirm('Are you sure you want to cancel this appointment?')) {
            updateAppointmentStatus(appointmentId, 'cancelled');
        }
    }

    function updateAppointmentStatus(appointmentId, status) {
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `appointment_id=${appointmentId}&status=${status}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Create notification for vet when appointment is cancelled
                if (status === 'cancelled') {
                    createVetNotification(`Appointment #${appointmentId} has been cancelled by user.`);
                }
                location.reload();
            } else {
                alert('Error: ' + data.error);
            }
        })
        .catch(error => {
            alert('Network error: Could not update appointment.');
            console.error('Error:', error);
        });
    }

    function createVetNotification(message) {
        // Send notification to vet dashboard
        fetch('create_vet_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `message=${encodeURIComponent(message)}`
        })
        .catch(error => console.error('Notification error:', error));
    }

    function viewAppointmentDetails(appointmentId) {
        // You can implement a modal to show detailed appointment information
        fetch('get_appointment_details.php?id=' + appointmentId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const appointment = data.appointment;
                    const details = `
                        Pet: ${appointment.pet_name} (${appointment.pet_type})
                        Date: ${appointment.appointment_date}
                        Time: ${appointment.appointment_time}
                        Service: ${appointment.service_type}
                        Status: ${appointment.status}
                        Reason: ${appointment.reason}
                        ${appointment.vet_notes ? `Vet Notes: ${appointment.vet_notes}` : ''}
                    `;
                    alert('Appointment Details:\n' + details);
                } else {
                    alert('Error loading appointment details.');
                }
            })
            .catch(error => {
                alert('Error loading appointment details.');
                console.error('Error:', error);
            });
    }

    // Set minimum time for appointment (current time + 1 hour)
    document.addEventListener('DOMContentLoaded', function() {
        const now = new Date();
        now.setHours(now.getHours() + 1);
        const timeInput = document.getElementById('appointment_time');
        const minTime = now.toTimeString().slice(0, 5);
        timeInput.min = minTime;
    });
    </script>
</body>
</html>
