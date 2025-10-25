// Appointment Management Functions
function confirmAppointment(appointmentId) {
    if (confirm('Are you sure you want to confirm this appointment?')) {
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `appointment_id=${appointmentId}&status=confirmed`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error confirming appointment: ' + data.error);
            }
        })
        .catch(error => {
            alert('Network error: Could not confirm appointment.');
            console.error('Error:', error);
        });
    }
}

function cancelAppointment(appointmentId) {
    if (confirm('Are you sure you want to cancel this appointment?')) {
        fetch('update_appointment_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `appointment_id=${appointmentId}&status=cancelled`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error cancelling appointment: ' + data.error);
            }
        })
        .catch(error => {
            alert('Network error: Could not cancel appointment.');
            console.error('Error:', error);
        });
    }
}

function viewAppointmentDetails(appointmentId) {
    // You can implement a modal to show detailed appointment information
    alert('Viewing appointment details for ID: ' + appointmentId);
    // This could open a modal with full appointment information
}
