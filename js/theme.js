/**
 * Infinity Builders - Theme Toggle Script
 * Handles dark/light mode switching
 */

(function() {
    'use strict';

    // Theme toggle functionality
    const ThemeManager = {
        STORAGE_KEY: 'infinity-theme',
        
        init: function() {
            // Check for saved preference or system preference
            const savedTheme = localStorage.getItem(this.STORAGE_KEY);
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            // Set initial theme
            const theme = savedTheme || (prefersDark ? 'dark' : 'light');
            this.setTheme(theme);
            
            // Setup toggle button
            this.setupToggle();
            
            // Listen for system preference changes
            window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
                if (!localStorage.getItem(this.STORAGE_KEY)) {
                    this.setTheme(e.matches ? 'dark' : 'light');
                }
            });
        },
        
        setTheme: function(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem(this.STORAGE_KEY, theme);
            
            // Update toggle UI (responsive - different positions)
            this.updateTogglePosition();
        },
        
        updateTogglePosition: function() {
            const toggle = document.querySelector('.theme-toggle');
            if (toggle) {
                const knob = toggle.querySelector('.theme-toggle-knob');
                if (knob) {
                    const isMobile = window.innerWidth <= 768;
                    const currentTheme = document.documentElement.getAttribute('data-theme');
                    const offset = isMobile ? '22px' : '28px';
                    knob.style.transform = currentTheme === 'light' ? 'translateX(' + offset + ')' : '';
                }
            }
        },
        
        toggle: function() {
            const current = document.documentElement.getAttribute('data-theme');
            const next = current === 'dark' ? 'light' : 'dark';
            this.setTheme(next);
        },
        
        setupToggle: function() {
            const toggle = document.querySelector('.theme-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => this.toggle());
            }
            
            // Update position on resize
            window.addEventListener('resize', () => this.updateTogglePosition());
        }
    };

    // Sidebar mobile toggle
    const SidebarManager = {
        init: function() {
            this.setupMobileToggle();
            this.setupActiveLinks();
        },
        
        setupMobileToggle: function() {
            // Mobile toggle is handled by inline onclick="toggleSidebar()"
            // This function just handles active links
        },
        
        setupActiveLinks: function() {
            const currentPath = window.location.pathname;
            const navItems = document.querySelectorAll('.nav-item');
            
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && currentPath.includes(href.replace('.php', ''))) {
                    item.classList.add('active');
                }
            });
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            ThemeManager.init();
            SidebarManager.init();
        });
    } else {
        ThemeManager.init();
        SidebarManager.init();
    }

    // Expose globally for debugging
    window.ThemeManager = ThemeManager;
    window.SidebarManager = SidebarManager;

})();
