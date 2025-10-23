// Toggle between summary and detailed views
document.addEventListener('DOMContentLoaded', function() {
    const summaryView = document.getElementById('summaryView');
    const detailedView = document.getElementById('detailedView');
    const toggleViewBtn = document.getElementById('toggleViewBtn');
    const showAllBtn = document.getElementById('showAllBtn');
    const backToSummaryBtn = document.getElementById('backToSummaryBtn');
    
    if (toggleViewBtn) {
        toggleViewBtn.addEventListener('click', function() {
            if (summaryView.style.display === 'none') {
                // Switch to summary view
                summaryView.style.display = 'block';
                detailedView.style.display = 'none';
                toggleViewBtn.innerHTML = '<i class="fas fa-list me-1"></i>View All Medical Records';
            } else {
                // Switch to detailed view
                summaryView.style.display = 'none';
                detailedView.style.display = 'block';
                toggleViewBtn.innerHTML = '<i class="fas fa-chart-bar me-1"></i>View Summary';
            }
        });
    }
    
    if (showAllBtn) {
        showAllBtn.addEventListener('click', function() {
            summaryView.style.display = 'none';
            detailedView.style.display = 'block';
            if (toggleViewBtn) {
                toggleViewBtn.innerHTML = '<i class="fas fa-chart-bar me-1"></i>View Summary';
            }
        });
    }
    
    if (backToSummaryBtn) {
        backToSummaryBtn.addEventListener('click', function() {
            summaryView.style.display = 'block';
            detailedView.style.display = 'none';
            if (toggleViewBtn) {
                toggleViewBtn.innerHTML = '<i class="fas fa-list me-1"></i>View All Medical Records';
            }
        });
    }
});
