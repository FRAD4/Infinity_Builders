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
                console.log('Search response:', text);
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
    if (data.results.projects.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Projects</div>';
        data.results.projects.forEach(p => {
            html += '<a href="' + p.url + '" class="global-search-item"><div class="global-search-item-icon projects"><i class="fa-solid fa-folder-open"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + p.name + '</div><div class="global-search-item-meta">' + (p.client || '') + ' • ' + p.status + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Vendors
    if (data.results.vendors.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Vendors</div>';
        data.results.vendors.forEach(v => {
            html += '<a href="' + v.url + '" class="global-search-item"><div class="global-search-item-icon vendors"><i class="fa-solid fa-users"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + v.name + '</div><div class="global-search-item-meta">' + (v.type || '') + ' • ' + (v.trade || '') + '</div></div></a>';
        });
        html += '</div>';
    }
    
    // Users
    if (data.results.users.length > 0) {
        html += '<div class="global-search-section"><div class="global-search-section-title">Users</div>';
        data.results.users.forEach(u => {
            html += '<a href="' + u.url + '" class="global-search-item"><div class="global-search-item-icon users"><i class="fa-solid fa-user"></i></div><div class="global-search-item-content"><div class="global-search-item-title">' + u.name + '</div><div class="global-search-item-meta">' + u.role + ' • ' + (u.email || '') + '</div></div></a>';
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
</script>

<!-- App JS -->
<script src="js/theme.js"></script>

<!-- Loading State Handler -->
<script>
(function() {
    // Show loading overlay on form submit
    document.querySelectorAll('form').forEach(function(form) {
        form.addEventListener('submit', function(e) {
            // Only show if not already handled
            if (!form.dataset.loading) {
                form.dataset.loading = 'true';
                const btn = form.querySelector('button[type="submit"]');
                if (btn && !btn.classList.contains('no-loading')) {
                    btn.classList.add('loading');
                }
            }
        });
    });
    
    // Global loading functions
    window.showLoading = function() {
        document.getElementById('loadingOverlay').style.display = 'flex';
    };
    
    window.hideLoading = function() {
        document.getElementById('loadingOverlay').style.display = 'none';
        document.querySelectorAll('.loading').forEach(function(el) {
            el.classList.remove('loading');
        });
    };
    
    // ==========================================
    // MOBILE SWIPE GESTURE FOR SIDEBAR
    // ==========================================
    var touchStartX = 0;
    var touchEndX = 0;
    var sidebar = document.querySelector('.sidebar');
    var overlay = document.querySelector('.sidebar-overlay');
    
    if (sidebar && overlay) {
        // Detect swipe on the whole document
        document.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
        }, { passive: true });
        
        document.addEventListener('touchend', function(e) {
            touchEndX = e.changedTouches[0].screenX;
            handleSwipe();
        }, { passive: true });
        
        function handleSwipe() {
            var swipeThreshold = 50;
            var diff = touchEndX - touchStartX;
            
            // Swipe right to open sidebar
            if (diff > swipeThreshold && touchStartX < 50) {
                sidebar.classList.add('open');
                overlay.classList.add('active');
            }
            // Swipe left to close sidebar
            else if (diff < -swipeThreshold) {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            }
        }
        
        // Close on overlay click
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('open');
            overlay.classList.remove('active');
        });
    }
})();
</script>

</body>
</html>
