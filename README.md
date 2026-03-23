# Infinity Builders — Construction Management System

![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?style=flat-square&logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat-square&logo=mysql&logoColor=white)
![HTML5](https://img.shields.io/badge/HTML5-E34F26?style=flat-square&logo=html5&logoColor=white)
![CSS3](https://img.shields.io/badge/CSS3-1572B6?style=flat-square&logo=css3&logoColor=white)
![JavaScript](https://img.shields.io/badge/JavaScript-ES6+-F7DF1E?style=flat-square&logo=javascript&logoColor=black)

---

## 🇺🇸 English

A comprehensive **construction company management system** built with vanilla PHP, MySQL, and modern CSS. Designed for USA-based construction companies to manage projects, vendors, users, and financial tracking in one unified dashboard.

### What it does
- **Project Management**: Track active, completed, and on-hold projects with budgets, timelines, and client info
- **Vendor Directory**: Manage vendor contacts, bids, and payment history
- **User Management**: Role-based access control (Admin, PM, Estimator, Accounting, Viewer)
- **Financial Tracking**: Payment records, budget utilization, and cost analysis
- **Analytics & Reports**: Interactive charts (Chart.js) for project status, budgets, and payment trends
- **Notifications**: Email alerts for project alerts and weekly summaries
- **Customization**: Personal dashboard layouts, theme preferences (dark/light/system), and default landing pages

### Design Highlights
- Custom design system with CSS variables and a cohesive dark/light theme
- Fully responsive layout with mobile sidebar navigation
- Real-time theme switching with system preference detection
- CSRF protection, prepared statements, and password hashing (bcrypt)

---

## 🇦🇷 Español

Sistema de gestión integral para empresas de construcción, desarrollado con **PHP vanilla, MySQL y CSS moderno**. Diseñado para компании estadounidenses del sector construcción para administrar proyectos, proveedores, usuarios y seguimiento financiero desde un único panel.

### Funcionalidades principales
- **Gestión de Proyectos**: Seguimiento de proyectos activos, completados y en pausa con presupuestos y cronogramas
- **Directorio de Proveedores**: Contactos, licitaciones e historial de pagos
- **Gestión de Usuarios**: Control de acceso por roles (Admin, PM, Estimator, Accounting, Viewer)
- **Seguimiento Financiero**: Registros de pagos, utilización de presupuestos y análisis de costos
- **Reportes y Analytics**: Gráficos interactivos (Chart.js) de estado de proyectos y tendencias
- **Notificaciones**: Alertas por email y resúmenes semanales
- **Personalización**: Layouts de dashboard, temas (oscuro/claro/sistema) y página de inicio configurable

---

## 📸 Screenshots

| Dark Mode | Light Mode |
|-----------|------------|
| ![Dashboard Dark](https://raw.githubusercontent.com/FRAD4/Infinity_Builders/main/.github/screenshots/dashboard-dark.png) | ![Dashboard Light](https://raw.githubusercontent.com/FRAD4/Infinity_Builders/main/.github/screenshots/dashboard-light.png) |

*Dashboard con estadísticas de proyectos, gráficos de presupuesto y actividad de pagos*

---

## 🛠 Tech Stack

| Layer | Technology |
|-------|------------|
| **Backend** | PHP 8.1+ (Vanilla, no framework) |
| **Database** | MySQL 8.0 |
| **Frontend** | HTML5, CSS3 (Custom Design System), Vanilla JavaScript ES6+ |
| **Charts** | Chart.js 4.x |
| **Icons** | Font Awesome 6.x |
| **Email** | PHPMailer |
| **Fonts** | Google Fonts (Inter) |

### Architecture Highlights
- **Security First**: CSRF tokens, bcrypt password hashing, SQL prepared statements, XSS sanitization
- **Modular Structure**: Includes, partials, and API endpoints for separation of concerns
- **Migration System**: Versioned SQL migrations for database schema management
- **No Build Step**: Pure PHP + static assets, deploy anywhere with a standard LAMP/LEMP stack

---

## ✨ Features

### Core Modules
- [x] **Dashboard** — Role-based stats and quick access widgets
- [x] **Projects** — Full CRUD with status, budget, timeline, and documents
- [x] **Calendar** — Project scheduling and milestone tracking
- [x] **Vendors** — Directory with contact info, trade, and bidding system
- [x] **Users & Roles** — RBAC with 5 permission levels
- [x] **Reports** — Interactive charts and data visualization
- [x] **Audit Log** — Activity tracking for compliance

### User Experience
- [x] **Dark/Light/System Theme** — Automatic detection and manual toggle
- [x] **Personalized Redirect** — Configurable default landing page per user
- [x] **Dashboard Widgets** — Toggle charts, recent items visibility
- [x] **Global Search** — Search across projects, vendors, and users
- [x] **Notifications Panel** — In-app notification center
- [x] **Mobile Responsive** — Optimized sidebar and layout for all devices

### Notifications
- [x] **Email Alerts** — Project status alerts (On Hold, past due, no budget)
- [x] **Weekly Summary** — Automated digest emails to admins
- [x] **Error Logging** — Email failure tracking in database

---

## 📁 Project Structure

```
public_html/
├── api/                    # REST API endpoints
│   ├── documents.php       # Document upload/download
│   ├── notifications.php   # Notifications API
│   ├── search.php         # Global search
│   └── tasks.php          # Task management
├── assets/                # Static assets
│   └── infinity-logo.webp
├── css/
│   └── style.css          # Main stylesheet (CSS variables, components)
├── includes/               # Shared backend logic
│   ├── audit.php          # Audit logging
│   ├── database.php       # DB connection helper
│   ├── email.php          # Email wrapper
│   ├── notifications.php  # Notification logic
│   ├── preferences.php    # User preferences
│   ├── sanitize.php       # Input sanitization
│   └── security.php       # CSRF, RBAC, password hashing
├── js/
│   └── theme.js           # Theme toggle logic
├── migrations/            # Database migrations
│   └── *.sql
├── partials/              # Shared UI components
│   ├── footer.php
│   ├── header.php         # Sidebar + theme init
│   └── init.php           # Session & auth check
├── scripts/               # Utility scripts
│   └── migrate_passwords.php
├── testing/               # Test suites
│   ├── full_test.php      # Security tests (27 tests)
│   └── phase3_test.php    # Feature tests (39 tests)
├── uploads/               # User-uploaded files
├── dashboard.php          # Main dashboard
├── projects.php           # Projects management
├── calendar.php           # Calendar view
├── vendors.php            # Vendor directory
├── reports.php            # Analytics & charts
├── users.php              # User management
├── audit.php              # Activity log
├── settings.php           # User preferences
├── index.php              # Login page
├── login.php              # Login handler
└── logout.php             # Session destroy
```

---

## 🚀 Setup

### Requirements
- PHP 8.1+
- MySQL 8.0+ / MariaDB 10.4+
- Apache or Nginx (mod_rewrite optional)
- XAMPP, WAMP, MAMP, or production LEMP stack

### Installation

1. **Clone the repository**
   ```bash
   git clone https://github.com/FRAD4/Infinity_Builders.git
   cd Infinity_Builders
   ```

2. **Configure database**
   ```bash
   # Create database
   mysql -u root -p
   CREATE DATABASE infinity_builders;
   EXIT;
   
   # Run migrations
   mysql -u root -p infinity_builders < migrations/*.sql
   ```

3. **Configure connection**
   ```bash
   cp config.local.php.example config.local.php
   # Edit config.local.php with your DB credentials
   ```

4. **Create email config**
   ```bash
   cp config.email.php.example config.email.php
   # Edit SMTP settings for email notifications
   ```

5. **Start development server**
   ```bash
   # If using XAMPP, move project to htdocs
   # Or use PHP built-in server:
   php -S localhost:8000
   
   # Default login: admin@infinity.com / admin123
   ```

---

## 📊 Development Roadmap

| Phase | Status | Description |
|-------|--------|-------------|
| Phase 1 | ✅ Done | Core structure, auth, basic CRUD |
| Phase 2 | ✅ Done | Security hardening (CSRF, hashing, RBAC) |
| Phase 3 | ✅ Done | Email system, notifications, logging |
| Phase 4 | ✅ Done | Dashboard personalization, charts, calendar |
| Phase 5 | 🔲 Planned | API documentation, REST API v2 |
| Phase 6 | 🔲 Planned | Advanced reporting, export to PDF |
| Phase 7 | 🔲 Planned | Multi-tenant / Organization support |

**Progress**: ~16 hours logged of ~110 estimated

---

## 🧪 Testing

Run the test suites to verify the application:

```bash
# Security & authentication tests
php testing/full_test.php

# Feature integration tests
php testing/phase3_test.php
```

Expected output: **66 tests, 100% passing**

---

## 📝 License

This project is proprietary and confidential.  
Copyright © 2026 Franco Dall'Oglio. All rights reserved.

---

## 👤 Author

**Franco Dall'Oglio**

- GitHub: [@FRAD4](https://github.com/FRAD4)
- LinkedIn: [franco-dalloglio](https://linkedin.com/in/franco-dalloglio)
- Email: franco.dalloglio16@gmail.com

---

*Built with ❤️ and ☕ — Zero frameworks, maximum learning.*
