# Infinity Builders — AGENTS.md

> Documentation for AI agents working on this codebase.

## Project Overview

- **Location**: `C:\xampp\htdocs\Infinity Builders\public_html\`
- **Type**: PHP/MySQL admin portal for construction company (USA)
- **Tech Stack**: Vanilla PHP, PDO, MySQL, Vanilla JavaScript
- **Framework**: None — server-side rendering with PHP partials
- **GitHub**: https://github.com/FRAD4/Infinity_Builders
- **Owner**: Franco Dall'Oglio (FRAD4)

## Architecture Summary

```
Infinity Builders/
├── .agent/                        # AI agent documentation
│   ├── docs/
│   │   ├── AGENTS.md              # This file (entry point)
│   │   ├── AGENTS-ui.md
│   │   ├── AGENTS-security.md
│   │   ├── AGENTS-functionality.md
│   │   ├── AGENTS-api.md
│   │   ├── AGENTS-db.md
│   │   └── AGENTS-testing.md
│   └── skills/                    # Future: AI agent skills
│
├── public_html/                   # Application source
│   ├── config/                    # Configuration files
│   ├── exports/                   # CSV export scripts
│   ├── testing/                   # Test suites
│   ├── api/                       # REST API endpoints
│   ├── assets/                    # Static assets
│   ├── css/                       # Stylesheets
│   ├── js/                        # JavaScript
│   ├── includes/                  # Helper functions
│   ├── migrations/                # Database migrations
│   ├── partials/                  # UI components
│   ├── scripts/                   # Utility scripts
│   ├── uploads/                   # User uploads
│   ├── vendor/                    # Dependencies (PHPMailer)
│   └── *.php                      # Main pages (dashboard, projects, etc.)
│
└── AGENTS.md                      # Agent documentation entry point
```

## How to Choose What to Work On

> **Note**: All domain guides are located in `.agent/docs/`

| If you need to... | Go to... |
|-------------------|----------|
| Change UI, CSS, colors, responsive design | **.agent/docs/AGENTS-ui.md** |
| Add authentication, fix security issues | **.agent/docs/AGENTS-security.md** |
| Add/modify CRUD operations, forms, helpers | **.agent/docs/AGENTS-functionality.md** |
| Create or modify API endpoints | **.agent/docs/AGENTS-api.md** |
| Change database schema, queries, migrations | **.agent/docs/AGENTS-db.md** |
| Run or write tests | **.agent/docs/AGENTS-testing.md** |

> **Tip**: Start here. This file tells you which domain guide to read.

---

## Conventions (All Domains)

### File Organization

- **Pages**: Root-level PHP files (`dashboard.php`, `projects.php`)
- **API**: `/api/` folder, return JSON
- **Helpers**: `/includes/` folder, `function_name()` style
- **Partials**: `/partials/` folder, included via `require_once`

### Database Access

```php
// Use the global $pdo from config.php
global $pdo;

// Always use prepared statements
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Security Requirements

- **ALL** forms must include CSRF token:
  ```php
  <input type="hidden" name="csrf_token" value="<?php echo csrf_token_generate(); ?>">
  ```
- **ALL** user output must use `htmlspecialchars()`:
  ```php
  echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
  ```
- **NEVER** use string concatenation in SQL — use prepared statements

### Page Structure Pattern

```php
<?php
// 1. Metadata
$pageTitle = 'Page Name';
$currentPage = 'page-name';

// 2. Bootstrap (auth + DB)
require_once 'partials/init.php';

// 3. Access control (if needed)
require_role('admin'); // or check $_SESSION['user_role']

// 4. CSRF
$csrf_token = csrf_token_generate();

// 5. Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ... handle CRUD
}

// 6. Load data for display
$items = load_items($pdo);

// 7. Render
require_once 'partials/header.php';
?>
<!-- HTML, modals, JS -->
<?php require_once 'partials/footer.php'; ?>
```

### Naming Conventions

- **Tables**: `snake_case` (e.g., `project_tasks`, `vendor_payments`)
- **PHP Functions**: `snake_case` (e.g., `csrf_token_generate`)
- **JavaScript Functions**: `camelCase` (e.g., `toggleSidebar`)
- **CSS Classes**: `kebab-case` (e.g., `.btn-primary`, `.sidebar-open`)
- **Roles**: lowercase (e.g., `admin`, `pm`, `estimator`)

### Git Workflow

1. **Branch**: `feature/description` or `fix/issue-description`
2. **Commit**: Conventional commits (`feat:`, `fix:`, `refactor:`, `docs:`)
3. **Push**: `git push -u origin branch-name`
4. **PR**: Draft PR, describe changes, link issues

---

## Domain-Specific Guides

Each domain has its own detailed guide:

| Guide | Covers |
|-------|--------|
| [AGENTS-ui.md](AGENTS-ui.md) | CSS architecture, dark mode, responsive, components |
| [AGENTS-security.md](AGENTS-security.md) | Auth, RBAC, CSRF, XSS, SQLi, password hashing |
| [AGENTS-functionality.md](AGENTS-functionality.md) | CRUD patterns, forms, helpers, validation |
| [AGENTS-api.md](AGENTS-api.md) | REST endpoints, JSON format, AJAX integration |
| [AGENTS-db.md](AGENTS-db.md) | Schema, migrations, queries, relationships |
| [AGENTS-testing.md](AGENTS-testing.md) | Test suites, running tests, what to test |

---

## Quick Reference

### Common Tasks

| Task | How |
|------|-----|
| Add new form | Create modal in page, POST to self, handle in POST block |
| Add new API endpoint | Create `api/endpoint.php`, use session auth pattern |
| Add database column | Create migration in `migrations/`, run manually |
| Add helper function | Add to appropriate file in `includes/` |
| Add CSS class | Add to `css/style.css` in logical section |
| Add role check | Use `has_role('role')` or `require_role('role')` |

### Helper Functions Available

| File | Functions |
|------|-----------|
| `security.php` | `csrf_token_generate()`, `require_role()`, `hash_password()`, `is_admin()` |
| `sanitize.php` | `sanitize_input()`, `sanitize_post()`, `sanitize_get()` |
| `audit.php` | `audit_log()`, `get_audit_logs()` |
| `email.php` | `send_email()`, `log_email()` |
| `preferences.php` | `get_user_preference()`, `set_user_preferences()` |

### Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts with roles |
| `projects` | Construction projects (with city, project_type, project_manager, etc.) |
| `permits` | Permit tracking per project |
| `inspections` | Inspections linked to permits |
| `vendors` | Vendor/subcontractor directory |
| `vendor_payments` | Payment records |
| `project_tasks` | Task management |
| `project_documents` | Document uploads |
| `audit_log` | Activity tracking |
| `emails_log` | Email history |
| `user_preferences` | Dashboard personalization |

### Project Statuses

| Status | Description |
|--------|-------------|
| `Signed` | Project just signed/contracted |
| `Starting Soon` | About to begin |
| `Active` | In progress |
| `Waiting on Permit` | Waiting for permit approval |
| `Waiting on Materials` | Waiting for materials |
| `On Hold` | Paused |
| `Completed` | Finished |
| `Cancelled` | Cancelled |

### Project New Fields

| Field | Description |
|-------|-------------|
| `city` | City (Phoenix, Scottsdale, Tempe, Chandler, etc.) |
| `project_type` | Type (Kitchen, Bathroom, Addition, Roofing, Flooring, etc.) |
| `address` | Property address |
| `phone` | Client phone |
| `email` | Client email |
| `project_manager` | PM (Regev Cohen, Yossi Dror, Carmel Cohen, etc.) |
| `scope_of_work` | Detailed scope description |
| `invoice_number` | Invoice reference |
| `invoice_path` | Invoice PDF path |

### User Roles

| Role | Permissions |
|------|-------------|
| `admin` | Full access, user management, all features |
| `pm` | Projects, reports, tasks, documents, team |
| `accounting` | Financial reports, payments, tasks |
| `estimator` | Tasks, documents, limited reports |
| `viewer` | Read-only access |

---

## Environment Setup

### Local Development (XAMPP)

```bash
# Database: infinity_builders (MySQL)
# Config: public_html/config/config.local.php uses XAMPP MySQL (root, no password)
# Access: http://localhost/Infinity%20Builders/public_html/
```

### Configuration Files

| File | When to Use |
|------|-------------|
| `public_html/config/config.php` | Default (dev) |
| `public_html/config/config.local.php` | Local overrides — creates `define()` constants |
| `public_html/config/config.email.php` | SMTP settings for email sending |

---

## Important Patterns

### Modal-Based CRUD

All create/edit operations use **modals** within the page:

```php
<!-- Create Modal -->
<div id="createModal" class="modal">
  <form method="POST">
    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
    <input type="hidden" name="action" value="create_item">
    <!-- fields -->
  </form>
</div>
```

### Data Attributes for Edit

Row data is embedded in `data-*` attributes for JavaScript:

```php
<tr data-id="1" data-name="Project Name" data-status="active">
  <td>Project Name</td>
</tr>
```

```javascript
// In JS
const row = event.target.closest('tr');
const id = row.dataset.id;
const name = row.dataset.name;
```

### Dark Mode Implementation

Three-tier priority:
1. **localStorage** (`infinity-theme`) — user choice
2. **Session** (`$_SESSION['theme_override']`) — server preference
3. **System** — `prefers-color-scheme`

```php
// In header.php
$data-theme="<?php echo $initialTheme; ?>"
```

```css
/* CSS variables in style.css */
:root { --bg-primary: #0F172A; }
[data-theme="light"] { --bg-primary: #F1F5F9; }
```

---

## Gotchas & Common Issues

1. **Dual config files**: `config.php` and `config.local.php` both exist — prefer `config.local.php` for local dev
2. **Password migration**: Legacy SHA256 passwords still exist — migration happens on login (bcrypt upgrade)
3. **Session-based rate limiting**: Login rate limiting is session-based (resets on browser close)
4. **Sidebar always dark**: Sidebar stays `#1E293B` even in light mode
5. **No migration runner**: Run migrations manually: `mysql -u root infinity_builders < migrations/filename.sql`

---

## Testing

Run the test suite:

```bash
# From project root
php public_html/testing/full_test.php
```

Tests cover:
- CSRF protection
- Password hashing/verification
- RBAC functions
- Database state
- Login flow

---

## 2026-04 Updates (What's New)

### Tabla Projects (projects.php)
- **Nuevas columnas**: Money In, Money Out, Profit
- **Cálculos**: 
  - Money In = total_budget del proyecto
  - Money Out = SUM(vendor_payments.amount) por proyecto
  - Profit = Money In - Money Out
- **Non-Profitable warning**: Si money_out > (money_in * 0.6) → muestra ⚠️ en rojo
- **Columnas quitadas**: Client, Type, Start, End, Budget (ya no se muestran en la tabla)

### Vendors (vendors.php)
- **Nuevo tab**: "Projects & Bids" - muestra proyectos asignados con bid_amount
- **Editar bid**: Click en el bid amount para modificarlo
- **Total Bid / Total Paid**: Se muestran por proyecto

### client_payments (API + Tabla)
- **Tabla**: client_payments para registrar pagos del cliente
- **Campos**: id, project_id, amount, payment_date, description, created_at
- **API**: api/client_payments.php (GET, POST, PUT, DELETE)

### Shortcuts (header.php)
- **Desktop**: Botón + en navbar → dropdown con Add Project/Vendor/Permit/Inspection
- **Mobile**: Botón ⋮ (tres puntos) → menú con Search + shortcuts
- **Navegación**: Usan `navigateAndCreate(url)` → abre modal de crear automáticamente

### Tabla project_vendors
- **Campo**: bid_amount (DECIMAL) - monto acordado con el vendor por proyecto
- **Editado desde**: vendors.php (Projects & Bids tab) o projects.php (Vendors tab)

---

## Important Notes for Future Development

### Database
- Tabla principal: `projects`
- Pagos de vendors: `vendor_payments`
- Pagos del cliente: `client_payments` (nueva)
- Asignaciones vendor-proyecto: `project_vendors`
- Permisos: `permits`
- Inspecciones: `inspections`

---

> **Last Updated**: 2026-04-26
> **Maintained By**: Human developer (FRAD4)
