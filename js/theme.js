/**
 * Infinity Builders - Theme Toggle Script
 * Handles dark/light mode switching
 */

(function() {
    'use strict';

    const ThemeManager = {
        STORAGE_KEY: 'infinity-theme',
        
        init: function() {
            this.setupToggle();
        },
        
        setTheme: function(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            localStorage.setItem(this.STORAGE_KEY, theme);
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
            // If coming from 'system' preference, resolve first
            if (current === 'system') {
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                this.setTheme(prefersDark ? 'light' : 'dark');
            } else {
                this.setTheme(current === 'dark' ? 'light' : 'dark');
            }
        },
        
        setupToggle: function() {
            const toggle = document.querySelector('.theme-toggle');
            if (toggle) {
                toggle.addEventListener('click', () => this.toggle());
            }
            
            // Update position on resize
            window.addEventListener('resize', () => this.updateTogglePosition());
            
            // Initial position update
            this.updateTogglePosition();
        }
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => ThemeManager.init());
    } else {
        ThemeManager.init();
    }

    // Expose globally for debugging
    window.ThemeManager = ThemeManager;

})();
