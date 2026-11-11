// Dashboard JavaScript
// Real-time patient tracking with auto-refresh
function initDashboard() {
    // Set up auto-refresh for patient tracking
    setInterval(refreshPatientTracking, 30000); // Refresh every 30 seconds
}

// Refresh patient tracking data
function refreshPatientTracking() {
    // In a real scenario, you would fetch updated data via AJAX
    // For now, just reload the table dynamically
    const xhr = new XMLHttpRequest();
    xhr.open('GET', '../api/get_patients.php', true);
    xhr.onload = function() {
        if (xhr.status === 200) {
            // Update the table with new data
            console.log('Patient data refreshed');
        }
    };
    xhr.send();
}

// Sort patients by triage level
function sortByTriageLevel() {
    const tbody = document.querySelector('.table tbody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr'));
    
    // Triage priority
    const triagePriority = {
        'red': 1,
        'yellow': 2,
        'green': 3,
        'black': 4,
        'emergency': 1,
        'urgent': 2,
        'non-urgent': 3
    };
    
    rows.sort((a, b) => {
        const aTriageClass = a.className;
        const bTriageClass = b.className;
        
        const aLevel = Object.keys(triagePriority).find(level => aTriageClass.includes(level)) || 'green';
        const bLevel = Object.keys(triagePriority).find(level => bTriageClass.includes(level)) || 'green';
        
        return triagePriority[aLevel] - triagePriority[bLevel];
    });
    
    rows.forEach(row => tbody.appendChild(row));
}

// Highlight long waiting times
function highlightLongWaitTimes() {
    const waitTimes = document.querySelectorAll('.wait-time');
    
    waitTimes.forEach(element => {
        const time = parseInt(element.textContent);
        if (time > 60) {
            element.classList.add('wait-long');
        }
    });
}

// Update bed status with animation
function updateBedStatus() {
    const bedCards = document.querySelectorAll('.bed-card');
    
    bedCards.forEach(card => {
        const status = card.querySelector('.bed-status').textContent.toLowerCase();
        
        if (status === 'occupied') {
            card.style.animation = 'pulse 2s infinite';
        }
    });
}

function showDashboardPanel(panelId) {
    const panels = document.querySelectorAll('.dashboard-panel');
    const dashboardSections = document.querySelectorAll('.dashboard-only-section');
    const tabLinks = document.querySelectorAll('[data-dashboard-tab]');
    const mainContent = document.querySelector('.main-content');

    if (!panels.length) return;

    tabLinks.forEach(link => {
        link.classList.toggle('active', link.dataset.dashboardTab === panelId);
    });

    if (mainContent) {
        mainContent.dataset.activeView = panelId;
    }

    if (panelId === 'dashboard') {
        panels.forEach(panel => panel.style.display = 'none');
        dashboardSections.forEach(section => section.style.display = '');
        return;
    }

    dashboardSections.forEach(section => section.style.display = 'none');
    panels.forEach(panel => {
        panel.style.display = panel.id === panelId ? '' : 'none';
    });
}

function showPanelFromUrl() {
    const params = new URLSearchParams(window.location.search);
    const panelId = params.get('view') || (window.location.hash ? window.location.hash.substring(1) : 'dashboard');
    showDashboardPanel(document.getElementById(panelId) ? panelId : 'dashboard');
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initDashboard();
    sortByTriageLevel();
    highlightLongWaitTimes();
    updateBedStatus();

    showPanelFromUrl();
});

window.addEventListener('hashchange', showPanelFromUrl);
window.addEventListener('popstate', showPanelFromUrl);

// Sidebar navigation active state
document.querySelectorAll('.menu a').forEach(link => {
    if (link.href === window.location.href || link.href === `${window.location.origin}${window.location.pathname}${window.location.hash}`) {
        link.classList.add('active');
    }
    
    link.addEventListener('click', function(event) {
        const tab = this.dataset.dashboardTab;

        if (this.getAttribute('href') === '#') {
            event.preventDefault();
        }

        if (tab && window.location.pathname.endsWith('/dashboard.php')) {
            event.preventDefault();
            showDashboardPanel(tab);
            if (tab === 'dashboard') {
                history.pushState(null, '', 'dashboard.php');
            } else {
                history.pushState(null, '', `dashboard.php?view=${tab}`);
            }
        }
    });
});
