# Infinity Builders — UI & CSS Guide

> Guide for AI agents working on UI, CSS, and visual components.

---

## CSS Architecture

### File Structure

```
public_html/
├── css/
│   └── style.css          # Main design system (2860 lines) — ACTIVE
├── assets/
│   └── app.css            # Legacy/dark theme (NOT USED)
└── js/
    └── theme.js           # Theme toggle logic
```

The active CSS is **`css/style.css`**. The `assets/app.css` file is legacy and not imported.

---

## Design Tokens (CSS Variables)

### Dark Mode (Default)

```css
:root {
    /* Backgrounds */
    --bg-primary: #0F172A;     /* Main bg - slate-900 */
    --bg-secondary: #1E293B;   /* Cards - slate-800 */
    --bg-card: #334155;       /* Elevated - slate-700 */
    --bg-input: #1E293B;      /* Inputs */
    --bg-hover: #475569;      /* Hover states */
    
    /* Text */
    --text-primary: #F8FAFC;  /* Almost white */
    --text-secondary: #94A3B8; /* Muted */
    --text-muted: #64748B;   /* Very muted */
    
    /* Brand */
    --primary: #F97316;       /* Orange accent */
    --primary-hover: #EA580C;
    --primary-light: rgba(249, 115, 22, 0.15);
    
    /* Status */
    --success: #22C55E;
    --warning: #F59E0B;
    --danger: #EF4444;
    --info: #3B82F6;
    
    /* Sidebar - ALWAYS DARK */
    --sidebar-bg: #1E293B;
    --sidebar-text: #F8FAFC;
    --sidebar-active: rgba(249, 115, 22, 0.15);
}
```

### Light Mode Override

```css
[data-theme="light"] {
    --bg-primary: #F1F5F9;      /* slate-100 */
    --bg-secondary: #E2E8F0;
    --bg-card: #FFFFFF;
    --text-primary: #0F172A;
    --border-color: #CBD5E1;
    /* Sidebar stays DARK in light mode! */
    --sidebar-bg: #1E293B;
}
```

---

## Dark Mode Implementation

### Three-Tier Priority System

1. **localStorage** (`infinity-theme`) — User's explicit choice saved
2. **Server preference** (`$_SESSION['theme_override']`) — From user settings
3. **System default** — `prefers-color-scheme: dark`

### In header.php

```php
// Server sends: 'system', 'dark', or 'light'
$data-theme="<?php echo $initialTheme; ?>"
```

### Client-side resolution (inline script in header.php)

```javascript
const savedTheme = localStorage.getItem('infinity-theme');

if (savedTheme && savedTheme !== 'system') {
    html.setAttribute('data-theme', savedTheme);
} else if (serverTheme === 'system') {
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    html.setAttribute('data-theme', prefersDark ? 'dark' : 'light');
}

// Listen for system changes
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', ...)
```

### In js/theme.js

```javascript
const ThemeManager = {
    STORAGE_KEY: 'infinity-theme',
    
    setTheme: function(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(this.STORAGE_KEY, theme);
    },
    
    toggle: function() { /* Toggle between dark/light */ }
};
```

---

## Responsive Breakpoints

| Breakpoint | Width | Adjustments |
|------------|-------|-------------|
| **Desktop** | > 1024px | Full layout, sidebar 260px |
| **Tablet** | 769px - 1024px | Sidebar 220px, 3-col grid |
| **Mobile** | ≤ 768px | Sidebar hidden (slide-out), fixed header |
| **Extra Small** | ≤ 480px | Single column, smaller text |

### Mobile Implementation

```css
@media (max-width: 768px) {
    .sidebar { transform: translateX(-100%); }
    .sidebar.open { transform: translateX(0); }
    .main-content { margin-left: 0; padding-top: 72px; }
    .main-header-wrapper { position: fixed; top: 0; z-index: 100; }
}
```

---

## Layout Structure

### Sidebar Layout (Desktop)

```css
.sidebar {
    width: 260px;
    height: 100vh;
    position: fixed;
    left: 0;
    top: 0;
    background: var(--sidebar-bg);
    z-index: 50;
}

.main-content {
    margin-left: 260px;
    min-height: 100vh;
}
```

### Fixed Header (Mobile)

```css
.main-header-wrapper {
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 100;
    background: var(--bg-primary);
}
```

---

## Utility Classes

### Flexbox

```css
.flex, .flex-col, .items-center, .justify-between, .justify-center
.gap-1, .gap-2, .gap-3, .gap-4, .gap-6
```

### Spacing

```css
.mt-1, .mt-2, .mt-3, .mt-4, .mb-1... 
.p-2, .p-3, .p-4, .p-6
```

### Typography

```css
.text-sm, .text-base, .text-lg, .text-xl, .text-2xl, .text-3xl
```

### Display

```css
.hidden, .visible, .invisible
.w-full, .h-full
.rounded, .rounded-lg, .rounded-full
```

---

## Component Patterns

### Buttons

```css
.btn {
    padding: 0.5rem 1rem;
    border-radius: 0.375rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: var(--primary);
    color: white;
}

.btn-primary:hover {
    background: var(--primary-hover);
}

.btn-secondary {
    background: var(--bg-card);
    color: var(--text-primary);
}
```

### Cards

```css
.card {
    background: var(--bg-secondary);
    border-radius: 0.5rem;
    padding: 1.5rem;
    box-shadow: var(--shadow-md);
}
```

### Tables

```css
table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 0.75rem 1rem;
    text-align: left;
    border-bottom: 1px solid var(--border-color);
}
```

### Modals

```css
.modal {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 200;
    align-items: center;
    justify-content: center;
}

.modal.active {
    display: flex;
}

.modal-content {
    background: var(--bg-secondary);
    border-radius: 0.5rem;
    max-width: 500px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}
```

### Badges

```css
.badge {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 500;
}

.badge-success { background: var(--success); color: white; }
.badge-warning { background: var(--warning); color: black; }
.badge-danger { background: var(--danger); color: white; }
.badge-info { background: var(--info); color: white; }
```

---

## JavaScript Patterns

### IIFE Wrapper

All JavaScript is wrapped in IIFEs to avoid global pollution:

```javascript
(function() {
    'use strict';
    // ...code
})();
```

### Global Functions (in footer.php)

| Function | Purpose |
|----------|---------|
| `toggleSidebar()` | Mobile menu toggle |
| `openGlobalSearch()` / `closeGlobalSearch()` | Ctrl+K search modal |
| `toggleNotifications()` | Bell icon panel |
| `showLoading()` / `hideLoading()` | Form submission spinners |

### Touch Gestures (Mobile)

Swipe from left edge to open sidebar:

```javascript
// In footer.php - touch gesture detection
let touchStartX = 0;
document.addEventListener('touchstart', e => {
    touchStartX = e.touches[0].clientX;
});

document.addEventListener('touchend', e => {
    const touchEndX = e.changedTouches[0].clientX;
    const diff = touchEndX - touchStartX;
    
    // Swipe right from left edge
    if (touchStartX < 30 && diff > 50) {
        openSidebar();
    }
});
```

---

## Partial Components

### init.php

```php
<?php
// Session bootstrap + auth check
require_once __DIR__ . '/../config.php';
// Loads $pdo global
// Checks $_SESSION['user_id']
```

### header.php

- Sidebar navigation with role-based links
- Main header with search, notifications, user menu
- Theme initialization script

### footer.php

- Global JavaScript
- Search modal markup
- Notifications panel markup
- Loading overlay

---

## Common Tasks

### Adding a New CSS Class

Add to `css/style.css` in the appropriate section:
- Buttons → lines 342-412
- Cards → lines 454-536
- Tables → lines 606-882
- Modals → lines 1810-2368

### Adding a New Component

1. Create modal in the page (between header and footer)
2. Add CSS to style.css
3. Add JavaScript in page's inline script tag

### Modifying Colors

Update CSS variables in `:root` (lines 13-67) and `[data-theme="light"]` (lines 72-101).

---

## Gotchas

1. **Sidebar always dark**: `--sidebar-bg` stays `#1E293B` in both light and dark modes
2. **Mobile touch-action**: Add `touch-action: none` to interactive elements for better gesture handling
3. **CSS order matters**: Later rules override earlier ones — put overrides after defaults
4. **No CSS frameworks**: Pure CSS with Tailwind-inspired utilities — don't add Bootstrap or Tailwind

---

> **Last Updated**: 2026-04-03
