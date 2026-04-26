<?php
/**
 * calendar.php - Project Calendar View
 * Displays project dates in an interactive calendar
 */

$pageTitle = 'Calendar';
$currentPage = 'calendar';

require_once 'partials/init.php';

$userRole = $_SESSION['user_role'] ?? 'viewer';
$userId = $_SESSION['user_id'] ?? 0;

// Load user preferences
require_once __DIR__ . '/includes/preferences.php';
$preferences = get_dashboard_preferences($userId);

// Get all projects with dates for calendar
$projects = [];
try {
    $stmt = $pdo->query("
        SELECT id, name, start_date, end_date, status, client_name, total_budget 
        FROM projects 
        WHERE start_date IS NOT NULL OR end_date IS NOT NULL
        ORDER BY start_date ASC
    ");
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $projects = [];
}

// Build calendar events array
$events = [];
$colors = [
    'Active' => '#22C55E',    // green
    'Planned' => '#3B82F6',   // blue
    'On Hold' => '#F59E0B',   // yellow
    'Complete' => '#8B5CF6'   // purple
];

foreach ($projects as $project) {
    // Start date event
    if ($project['start_date']) {
        $events[] = [
            'id' => $project['id'] . '_start',
            'title' => '📅 Start: ' . htmlspecialchars($project['name']),
            'start' => $project['start_date'],
            'color' => $colors[$project['status']] ?? '#6B7280',
            'extendedProps' => [
                'type' => 'start',
                'project_id' => $project['id'],
                'project_name' => $project['name'],
                'status' => $project['status'],
                'client' => $project['client_name'],
                'budget' => $project['total_budget']
            ]
        ];
    }
    
    // End date event
    if ($project['end_date']) {
        $events[] = [
            'id' => $project['id'] . '_end',
            'title' => '🏁 End: ' . htmlspecialchars($project['name']),
            'start' => $project['end_date'],
            'color' => $colors[$project['status']] ?? '#6B7280',
            'extendedProps' => [
                'type' => 'end',
                'project_id' => $project['id'],
                'project_name' => $project['name'],
                'status' => $project['status'],
                'client' => $project['client_name'],
                'budget' => $project['total_budget']
            ]
        ];
    }
}

$eventsJson = json_encode($events);

require_once 'partials/header.php';
?>

<div class="page-header">
  <h1>Project Calendar</h1>
  <p class="text-secondary">View project timelines and deadlines</p>
</div>

<!-- Calendar Controls -->
<div class="card" style="margin-bottom: 24px;">
  <div style="padding: 16px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
    <button id="btnToday" class="btn btn-secondary">Today</button>
    <button id="btnPrev" class="btn btn-secondary"><i class="fa-solid fa-chevron-left"></i></button>
    <button id="btnNext" class="btn btn-secondary"><i class="fa-solid fa-chevron-right"></i></button>
    <h2 id="calendarTitle" style="margin: 0; flex: 1; min-width: 150px;"></h2>
    <select id="calendarView" class="form-control" style="width: auto;">
      <option value="dayGridMonth">Month</option>
      <option value="timeGridWeek">Week</option>
      <option value="listMonth">List</option>
    </select>
  </div>
</div>

<!-- Legend -->
<div class="card" style="margin-bottom: 24px;">
  <div style="padding: 12px 16px;">
    <span style="margin-right: 16px; font-weight: 500;">Legend:</span>
    <span style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px;">
      <span style="width: 12px; height: 12px; border-radius: 2px; background: #22C55E;"></span> Active
    </span>
    <span style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px;">
      <span style="width: 12px; height: 12px; border-radius: 2px; background: #3B82F6;"></span> Planned
    </span>
    <span style="display: inline-flex; align-items: center; gap: 6px; margin-right: 16px;">
      <span style="width: 12px; height: 12px; border-radius: 2px; background: #F59E0B;"></span> On Hold
    </span>
    <span style="display: inline-flex; align-items: center; gap: 6px;">
      <span style="width: 12px; height: 12px; border-radius: 2px; background: #8B5CF6;"></span> Complete
    </span>
  </div>
</div>

<!-- Calendar -->
<div class="card">
  <div id="calendar" style="padding: 16px;"></div>
</div>

<!-- Project Details Modal -->
<div id="projectModal" class="modal" style="display: none;">
  <div class="modal-backdrop"></div>
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3 id="modalTitle">Project Details</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <div class="modal-body" id="modalBody">
      <!-- Content populated by JS -->
    </div>
  </div>
</div>

<!-- FullCalendar CSS -->
<link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">

<style>
#calendar {
    min-height: 600px;
}

.fc {
    --fc-border-color: var(--border-color);
    --fc-button-text-color: var(--text-primary);
    --fc-button-bg-color: var(--primary);
    --fc-button-border-color: var(--primary);
    --fc-button-hover-bg-color: var(--primary-hover);
    --fc-button-hover-border-color: var(--primary-hover);
    --fc-button-active-bg-color: var(--primary-hover);
    --fc-button-active-border-color: var(--primary-hover);
    --fc-today-bg-color: rgba(59, 130, 246, 0.1);
    --fc-page-bg-color: var(--bg-primary);
    --fc-neutral-bg-color: var(--bg-elevated);
    --fc-list-event-hover-bg-color: var(--bg-elevated);
}

.fc .fc-toolbar-title {
    font-size: 1.25rem;
    font-weight: 600;
}

.fc .fc-button {
    padding: 6px 12px;
    font-size: 0.875rem;
    font-weight: 500;
}

.fc .fc-daygrid-day-number,
.fc .fc-col-header-cell-cushion {
    color: var(--text-primary);
    text-decoration: none;
}

.fc .fc-event {
    cursor: pointer;
    padding: 4px 8px;
    font-size: 0.8rem;
    border-radius: 4px;
    border: none;
}

.fc .fc-event:hover {
    opacity: 0.9;
}

.fc .fc-daygrid-event-dot {
    display: none;
}

.fc .fc-list-event-title a {
    color: var(--text-primary);
}
</style>

<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
<script>
let calendar;

document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const isDark = document.documentElement.getAttribute('data-theme') !== 'light';
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: false,
        events: <?php echo $eventsJson; ?>,
        eventClick: function(info) {
            showProjectModal(info.event);
        },
        eventDidMount: function(info) {
            // Add tooltip
            info.el.title = info.event.title;
        },
        datesSet: function(info) {
            var calendarTitle = document.getElementById('calendarTitle');
            if (calendarTitle) calendarTitle.textContent = info.view.title;
        },
        height: 'auto',
        dayMaxEvents: 3,
        nowIndicator: true,
        selectable: true,
        editable: false
    });
    
    calendar.render();
    
    // Navigation buttons
    var btnToday = document.getElementById('btnToday');
    var btnPrev = document.getElementById('btnPrev');
    var btnNext = document.getElementById('btnNext');
    var calendarView = document.getElementById('calendarView');
    
    if (btnToday) btnToday.addEventListener('click', function() {
        calendar.today();
    });
    
    if (btnPrev) btnPrev.addEventListener('click', function() {
        calendar.prev();
    });
    
    if (btnNext) btnNext.addEventListener('click', function() {
        calendar.next();
    });
    
    // View selector
    if (calendarView) calendarView.addEventListener('change', function(e) {
        calendar.changeView(e.target.value);
    });
});

function showProjectModal(event) {
    const props = event.extendedProps;
    const modal = document.getElementById('projectModal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');
    
    const typeLabel = props.type === 'start' ? '📅 Start Date' : '🏁 End Date';
    const date = event.startStr;
    
    if (modalTitle) modalTitle.textContent = props.project_name || 'Project Details';
    if (modalBody) modalBody.innerHTML = `
        <div style="display: grid; gap: 16px;">
            <div>
                <label style="color: var(--text-secondary); font-size: 0.875rem;">Event Type</label>
                <div style="font-weight: 500;">${typeLabel}</div>
            </div>
            <div>
                <label style="color: var(--text-secondary); font-size: 0.875rem;">Date</label>
                <div style="font-weight: 500;">${formatDate(date)}</div>
            </div>
            <div>
                <label style="color: var(--text-secondary); font-size: 0.875rem;">Status</label>
                <div>
                    <span class="badge badge-${(props.status || 'planned').toLowerCase().replace(' ', '-')}">${props.status || 'Planned'}</span>
                </div>
            </div>
            <div>
                <label style="color: var(--text-secondary); font-size: 0.875rem;">Client</label>
                <div>${props.client || 'N/A'}</div>
            </div>
            <div>
                <label style="color: var(--text-secondary); font-size: 0.875rem;">Budget</label>
                <div>${props.budget ? '$' + parseFloat(props.budget).toLocaleString() : 'N/A'}</div>
            </div>
            <div style="margin-top: 8px;">
                <a href="projects.php?open=${props.project_id}" class="btn btn-primary" style="display: inline-block; text-align: center;">View Project</a>
            </div>
        </div>
    `;
    
    if (modal) modal.style.display = 'flex';
}

function formatDate(dateStr) {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

function closeModal() {
    var projectModal = document.getElementById('projectModal');
    if (projectModal) projectModal.style.display = 'none';
}

// Close modal on backdrop click
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-backdrop')) {
        closeModal();
    }
});

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php require_once 'partials/footer.php'; ?>
