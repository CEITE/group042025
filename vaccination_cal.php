<?php
// vaccination_calendar.php – Calendar-based vaccine forecast using FullCalendar.js
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Vaccination Calendar | VetCareQR</title>
  <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
  <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <style>
    body { background-color: #f8f9fa; }
    .fc .fc-daygrid-event { font-size: 0.9rem; padding: 2px 4px; }
  </style>
</head>
<body>
<div class="container mt-4">
  <h3 class="mb-4">🗓️ Pet Vaccination Forecast Calendar</h3>
  <div id='calendar'></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var calendarEl = document.getElementById('calendar');

  var calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    headerToolbar: {
      left: 'prev,next today',
      center: 'title',
      right: 'dayGridMonth,listMonth'
    },
    events: [
      {
        title: 'Lucky - Rabies (Completed)',
        start: '2024-11-12',
        color: '#198754' // green
      },
      {
        title: 'Max - Parvo (Overdue)',
        start: '2024-10-10',
        color: '#dc3545' // red
      },
      {
        title: 'Luna - Deworming (Due Soon)',
        start: '2025-08-01',
        color: '#ffc107' // yellow
      }
    ]
  });

  calendar.render();
});
</script>
</body>
</html>
