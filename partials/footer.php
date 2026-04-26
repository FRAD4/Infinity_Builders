<?php
/**
 * footer.php - Shared footer
 * Infinity Builders Design System
 */
?>
 
</main><!-- end .main-content -->

<!-- Loading Overlay -->
<div id="loadingOverlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner"></div>
</div>

<!-- Sidebar Toggle Script -->
<script>
function toggleSidebar() {
    document.querySelector('.sidebar').classList.toggle('open');
    document.querySelector('.sidebar-overlay').classList.toggle('active');
}

// Close sidebar when pressing Escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.querySelector('.sidebar').classList.remove('open');
        document.querySelector('.sidebar-overlay').classList.remove('active');
        closeGlobalSearch();
    }
});

// Global Search
let searchTimeout = null;

function openGlobalSearch() {
    document.getElementById('globalSearchOverlay').classList.add('active');
    document.getElementById('globalSearchInput').focus();
}

function closeGlobalSearch(event) {
    if (event && event.target !== event.currentTarget) return;
    document.getElementById('globalSearchOverlay').classList.remove('active');
    document.getElementById('globalSearchInput').value = '';
    document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-hint"><p><i class="fa-solid fa-keyboard"></i> Type to search (Ctrl+K to open)</p></div>';
}

// Search on input
document.getElementById('globalSearchInput').addEventListener('input', function(e) {
    clearTimeout(searchTimeout);
    const query = e.target.value.trim();
    
    if (query.length < 2) {
        document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-hint"><p><i class="fa-solid fa-keyboard"></i> Type to search (Ctrl+K to open)</p></div>';
        return;
    }
    
    document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-loading"><i class="fa-solid fa-spinner fa-spin"></i> Searching...</div>';
    
    searchTimeout = setTimeout(() => {
        fetch('api/search.php?q=' + encodeURIComponent(query))
            .then(r => r.text())
            .then(text => {
                try {
                    const data = JSON.parse(text);
                    if (data.error) {
                        document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-empty">Error: ' + data.error + '</div>';
                    } else {
                        renderSearchResults(data);
                    }
                } catch(e) {
                    document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-empty">Parse error: ' + text.substring(0, 100) + '</div>';
                }
            })
            .catch(err => {
                document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-empty">Network error: ' + err.message + '</div>';
            });
    }, 300);
});

function renderSearchResults(data) {
    if (data.total === 0) {
        document.getElementById('globalSearchResults').innerHTML = '<div class="global-search-empty">No results found</div>';
        return;
    }
    
    let html = '';
    
    // Projects
    if (data.results.projects && data.results.projects.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Projects</div>';
        data.results.projects.forEach(p => {
            let meta = [];
            if (p.client) meta.push(p.client);
            meta.push(p.status);
            html += '<a href="' + p.url + '" class="global-search-item"><div class="global-search-item-icon projects"><i class="fa-solid fa-folder-open"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + p.name + '</div><div class="global-search-item-meta">' + meta.join(' • ') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Vendors
    if (data.results.vendors && data.results.vendors.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Vendors</div>';
        data.results.vendors.forEach(v => {
            let meta = [];
            if (v.type) meta.push(v.type);
            if (v.trade) meta.push(v.trade);
            if (v.email) meta.push(v.email);
            html += '<a href="' + v.url + '" class="global-search-item"><div class="global-search-item-icon vendors"><i class="fa-solid fa-users"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + v.name + '</div><div class="global-search-item-meta">' + (meta.length ? meta.join(' • ') : 'Vendor') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Users
    if (data.results.users && data.results.users.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Users</div>';
        data.results.users.forEach(u => {
            let meta = [];
            if (u.role) meta.push(u.role);
            if (u.email) meta.push(u.email);
            html += '<a href="' + u.url + '" class="global-search-item"><div class="global-search-item-icon users"><i class="fa-solid fa-user"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + u.full_name + '</div><div class="global-search-item-meta">' + (meta.length ? meta.join(' • ') : u.username) + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Tasks
    if (data.results.tasks && data.results.tasks.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Tasks</div>';
        data.results.tasks.forEach(t => {
            let meta = [];
            if (t.project_name) meta.push(t.project_name);
            if (t.status) meta.push(t.status);
            html += '<a href="' + t.url + '" class="global-search-item"><div class="global-search-item-icon tasks"><i class="fa-solid fa-check-square"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + t.title + '</div><div class="global-search-item-meta">' + (meta.length ? meta.join(' • ') : 'Task') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Permits
    if (data.results.permits && data.results.permits.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Permits</div>';
        data.results.permits.forEach(pm => {
            let meta = [];
            if (pm.project_name) meta.push(pm.project_name);
            if (pm.city) meta.push(pm.city);
            meta.push(pm.status);
            html += '<a href="' + pm.url + '" class="global-search-item"><div class="global-search-item-icon permits"><i class="fa-solid fa-file-contract"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + pm.permit_number + '</div><div class="global-search-item-meta">' + meta.join(' • ') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Inspections
    if (data.results.inspections && data.results.inspections.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Inspections</div>';
        data.results.inspections.forEach(i => {
            let meta = [];
            if (i.project_name) meta.push(i.project_name);
            if (i.city) meta.push(i.city);
            if (i.inspection_type) meta.push(i.inspection_type);
            meta.push(i.status);
            html += '<a href="' + i.url + '" class="global-search-item"><div class="global-search-item-icon inspections"><i class="fa-solid fa-clipboard-check"></i></div><div class="global-search-item-content"><div class="global-search-item-title">Inspection #' + i.id + '</div><div class="global-search-item-meta">' + meta.join(' • ') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    document.getElementById('globalSearchResults').innerHTML = html;
}

// Ctrl+K to open search
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        e.preventDefault();
        openGlobalSearch();
    }
});

// Notifications
let notificationsData = [];
let notificationsLoaded = false;

function toggleNotifications() {
    const panel = document.getElementById('notificationsPanel');
    panel.classList.toggle('active');
    
    if (!notificationsLoaded) {
        loadNotifications();
        notificationsLoaded = true;
    }
    
    // Close when clicking outside
    if (panel.classList.contains('active')) {
        setTimeout(() => {
            document.addEventListener('click', closeNotificationsOutside);
        }, 100);
    }
}

function closeNotificationsOutside(e) {
    const panel = document.getElementById('notificationsPanel');
    const btn = document.getElementById('notificationsBtn');
    if (!panel.contains(e.target) && !btn.contains(e.target)) {
        panel.classList.remove('active');
        document.removeEventListener('click', closeNotificationsOutside);
    }
}

function loadNotifications() {
    fetch('api/notifications.php')
        .then(r => r.json())
        .then(data => {
            notificationsData = data.notifications;
            renderNotifications(data.notifications, data.count);
        })
        .catch(err => {
            console.error('Failed to load notifications:', err);
        });
}

function renderNotifications(notifications, count) {
    const badge = document.getElementById('notificationsBadge');
    const list = document.getElementById('notificationsList');
    
    // Update badge
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
    
    // Render list
    if (notifications.length === 0) {
        list.innerHTML = '<div class="notifications-empty">No notifications</div>';
        return;
    }
    
    let html = '';
    notifications.forEach(n => {
        html += `
            <a href="${n.url}" class="notification-item ${n.type}">
                <div class="notification-icon ${n.type}">
                    <i class="fa-solid ${n.icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${n.title}</div>
                    <div class="notification-message">${n.message}</div>
                </div>
            </a>
        `;
    });
    
    list.innerHTML = html;
}

function markAllRead() {
    // For now, just refresh
    loadNotifications();
}

// Shortcuts Dropdown (Desktop)
function toggleShortcuts() {
    const dropdown = document.getElementById('shortcutsDropdown');
    dropdown.classList.toggle('show');
    
    if (dropdown.classList.contains('show')) {
        setTimeout(() => {
            document.addEventListener('click', closeShortcutsOutside);
        }, 100);
    }
}

function closeShortcutsOutside(e) {
    const dropdown = document.getElementById('shortcutsDropdown');
    const btn = document.getElementById('shortcutsBtn');
    if (!dropdown.contains(e.target) && !btn.contains(e.target)) {
        dropdown.classList.remove('show');
        document.removeEventListener('click', closeShortcutsOutside);
    }
}

function navigateTo(url) {
    window.location.href = url;
}

function navigateAndCreate(url) {
    window.location.href = url + '?create=1';
}

// Mobile Shortcuts FAB
function toggleMobileShortcuts() {
    const panel = document.getElementById('mobileShortcutsPanel');
    const fab = document.querySelector('.mobile-shortcuts-fab');
    panel.classList.toggle('show');
    
    if (panel.classList.contains('show')) {
        setTimeout(() => {
            document.addEventListener('click', closeMobileShortcutsOutside);
        }, 100);
    }
}

function closeMobileShortcutsOutside(e) {
    const panel = document.getElementById('mobileShortcutsPanel');
    const fab = document.querySelector('.mobile-shortcuts-fab');
    if (!panel.contains(e.target) && !fab.contains(e.target)) {
        panel.classList.remove('show');
        document.removeEventListener('click', closeMobileShortcutsOutside);
    }
}

// Mobile Search Input Handler
document.addEventListener('DOMContentLoaded', function() {
    const mobileSearchInput = document.getElementById('mobileSearchInput');
    if (mobileSearchInput) {
        mobileSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && this.value.trim()) {
                openGlobalSearch();
            }
        });
    }
});
</script>

<!-- App JS -->
<script src="js/theme.js"></script>

<!-- Loading State Handler -->
<script>
(function() {
    function init() {
        // Show loading overlay on form submit
        document.querySelectorAll('form').forEach(function(form) {
            form.addEventListener('submit', function(e) {
                if (!form.dataset.loading) {
                    form.dataset.loading = 'true';
                    var btn = form.querySelector('button[type="submit"]');
                    if (btn && !btn.classList.contains('no-loading')) {
                        btn.classList.add('loading');
                    }
                }
            });
        });
        
        // Global loading functions
        window.showLoading = function() {
            var el = document.getElementById('loadingOverlay');
            if (el) el.style.display = 'flex';
        };
        
        window.hideLoading = function() {
            var el = document.getElementById('loadingOverlay');
            if (el) el.style.display = 'none';
            document.querySelectorAll('.loading').forEach(function(el) {
                el.classList.remove('loading');
            });
        };
        
        // Mobile Swipe Gesture for Sidebar
        (function() {
            var touchStartX = 0;
            var touchEndX = 0;
            var touchStartY = 0;
            var touchMoved = false;
            
            var sidebar = document.querySelector('.sidebar');
            var overlay = document.querySelector('.sidebar-overlay');
            
            if (sidebar && overlay) {
                document.addEventListener('touchstart', function(e) {
                    if (e.touches && e.touches[0]) {
                        touchStartX = e.touches[0].clientX;
                        touchStartY = e.touches[0].clientY;
                        touchEndX = touchStartX;
                        touchMoved = false;
                    }
                }, { passive: true });
                
                document.addEventListener('touchmove', function(e) {
                    if (e.touches && e.touches[0]) {
                        touchEndX = e.touches[0].clientX;
                        var touchCurrentY = e.touches[0].clientY;
                        
                        if (Math.abs(touchEndX - touchStartX) > 10 || Math.abs(touchCurrentY - touchStartY) > 10) {
                            touchMoved = true;
                        }
                    }
                }, { passive: true });
                
                document.addEventListener('touchend', function(e) {
                    if (!touchMoved) return;
                    
                    var diffX = touchEndX - touchStartX;
                    var diffY = e.changedTouches && e.changedTouches[0] ? Math.abs(e.changedTouches[0].clientY - touchStartY) : 0;
                    var swipeThreshold = 50;
                    
                    if (diffY > Math.abs(diffX)) return;
                    
                    if (diffX > swipeThreshold && touchStartX < 100) {
                        sidebar.classList.add('open');
                        overlay.classList.add('active');
                    }
                    else if (diffX < -swipeThreshold) {
                        sidebar.classList.remove('open');
                        overlay.classList.remove('active');
                    }
                }, { passive: true });
                
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('open');
                    overlay.classList.remove('active');
                });
            }
        })();
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
</script>

</body>
</html>
