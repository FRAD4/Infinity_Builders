# Infinity Builders — Database Guide

> Guide for AI agents working on database schema, migrations, queries, and relationships.

---

## Database Configuration

### Files

| File | Purpose |
|------|---------|
| `config.php` | Main config (uses `$pdo` global) |
| `config.local.php` | Local overrides with `define()` |

### Connection Pattern

```php
$pdo = new PDO(
    "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
    $db_user,
    $db_pass
);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

### Usage in Code

```php
global $pdo;

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
```

---

## Database Tables

| Table | Purpose |
|-------|---------|
| `users` | User accounts with roles |
| `projects` | Construction projects (with city, project_type, project_manager, etc.) |
| `permits` | Permit tracking per project |
| `inspections` | Inspections linked to permits |
| `vendors` | Vendor/subcontractor directory |
| `vendor_payments` | Payment records to vendors |
| `project_tasks` | Task management per project |
| `project_documents` | Document uploads per project |
| `audit_log` | Activity tracking |
| `emails_log` | Email sending history |
| `user_preferences` | Dashboard personalization |

---

## Table Schemas

### users

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'pm', 'accounting', 'estimator', 'viewer') NOT NULL DEFAULT 'viewer',
    password_algo ENUM('sha256', 'bcrypt', 'sha256_migration_pending') DEFAULT 'bcrypt',
    password_hash VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### projects

```sql
CREATE TABLE projects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    city VARCHAR(100),
    project_type VARCHAR(100),
    address VARCHAR(500),
    phone VARCHAR(50),
    email VARCHAR(255),
    scope_of_work TEXT,
    project_manager VARCHAR(255),
    client_name VARCHAR(255),
    total_budget DECIMAL(12,2),
    invoice_number VARCHAR(100),
    invoice_path VARCHAR(500),
    start_date DATE,
    end_date DATE,
    notes TEXT,
    status ENUM('Signed','Starting Soon','Active','Waiting on Permit','Waiting on Materials','On Hold','Completed','Cancelled') DEFAULT 'Signed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### permits

```sql
CREATE TABLE permits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    permit_type VARCHAR(100) NOT NULL,
    permit_required ENUM('yes','no') DEFAULT 'yes',
    description VARCHAR(255),
    status ENUM('pending_submission','waiting_approval','approved','rejected','expired') DEFAULT 'pending_submission',
    submitted_by VARCHAR(255),
    permit_number VARCHAR(100),
    corrections_required ENUM('yes','no') DEFAULT 'no',
    corrections_due_date DATE,
    submitted_date DATE,
    approved_date DATE,
    expiry_date DATE,
    notes TEXT,
    created_at DATETIME,
    updated_at DATETIME,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_id (project_id),
    INDEX idx_status (status)
);
```

### inspections

```sql
CREATE TABLE inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    permit_id INT,
    inspection_type VARCHAR(100),
    city VARCHAR(100),
    requested_by VARCHAR(255),
    date_requested DATE,
    scheduled_date DATE,
    status ENUM('not_scheduled','requested','scheduled','completed','passed','failed','reinspection_needed') DEFAULT 'not_scheduled',
    inspector_notes TEXT,
    reinspection_needed ENUM('yes','no') DEFAULT 'no',
    reinspection_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (permit_id) REFERENCES permits(id) ON DELETE SET NULL,
    INDEX idx_project_id (project_id),
    INDEX idx_permit_id (permit_id),
    INDEX idx_status (status)
);
```
    client_name VARCHAR(255),
    total_budget DECIMAL(15,2),
    start_date DATE,
    end_date DATE,
    notes TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### vendors

```sql
CREATE TABLE vendors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    email VARCHAR(255),
    phone VARCHAR(50),
    type VARCHAR(100),
    trade VARCHAR(100),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

### vendor_payments

```sql
CREATE TABLE vendor_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    vendor_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    paid_date DATE NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (vendor_id) REFERENCES vendors(id) ON DELETE CASCADE
);
```

### project_tasks

```sql
CREATE TABLE project_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    status ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    assigned_to INT,
    due_date DATE,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_to) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);
```

### project_documents

```sql
CREATE TABLE project_documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    label VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(100),
    file_size INT,
    uploaded_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);
```

### audit_log

```sql
CREATE TABLE audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    username VARCHAR(100),
    action_type ENUM('create', 'update', 'delete', 'login', 'logout') NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    entity_name VARCHAR(255),
    changes JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);
```

### emails_log

```sql
CREATE TABLE emails_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500),
    body TEXT,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### user_preferences

```sql
CREATE TABLE user_preferences (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_user_preference (user_id, preference_key),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## Table Relationships

```
users (1) ──────< (N) audit_log
users (1) ──────< (N) user_preferences
users (1) ──────< (N) project_tasks (created_by)
users (1) ──────< (N) project_tasks (assigned_to)
users (1) ──────< (N) project_documents (uploaded_by)

projects (1) ───< (N) project_tasks
projects (1) ───< (N) project_documents

vendors (1) ────< (N) vendor_payments
```

---

## Indexes

### From Migrations

```sql
-- users
CREATE INDEX idx_password_algo ON users(password_algo);

-- emails_log
CREATE INDEX idx_status ON emails_log(status);
CREATE INDEX idx_created_at ON emails_log(created_at);

-- project_tasks
CREATE INDEX idx_project_id ON project_tasks(project_id);
CREATE INDEX idx_status ON project_tasks(status);
CREATE INDEX idx_assigned_to ON project_tasks(assigned_to);
CREATE INDEX idx_due_date ON project_tasks(due_date);

-- project_documents
CREATE INDEX idx_project_id ON project_documents(project_id);

-- user_preferences
CREATE UNIQUE INDEX uk_user_preference ON user_preferences(user_id, preference_key);

-- audit_log
CREATE INDEX idx_action ON audit_log(action_type);
CREATE INDEX idx_entity ON audit_log(entity_type);
CREATE INDEX idx_created ON audit_log(created_at);
```

---

## Migrations

### Location

```
public_html/migrations/
```

### Existing Migrations

| File | Purpose |
|------|---------|
| `2026_03_22_security.sql` | Adds role, password_algo, password_hash to users |
| `2026_03_22_emails_log.sql` | Creates emails_log table |
| `2026_03_23_project_tasks.sql` | Creates project_tasks table |
| `2026_03_23_project_documents.sql` | Creates project_documents table |
| `2026_03_23_user_preferences.sql` | Creates user_preferences table |

### Running Migrations Manually

```bash
# From XAMPP MySQL
mysql -u root infinity_builders < public_html/migrations/2026_03_23_project_tasks.sql
```

### Creating a New Migration

1. Create file in `migrations/` with format: `YYYY_MM_DD_description.sql`
2. Add table creation or ALTER statements
3. Document in this guide

---

## Common Queries

### Select with Join

```php
$stmt = $pdo->prepare("
    SELECT p.*, u.username as created_by_name
    FROM projects p
    LEFT JOIN users u ON p.created_by = u.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$project = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Aggregation

```php
$stmt = $pdo->query("
    SELECT status, COUNT(*) as count 
    FROM projects 
    GROUP BY status
");
$stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
```

### Search with LIKE

```php
$sql = "SELECT * FROM projects WHERE name LIKE ?";
$param = '%' . $search . '%';
$stmt = $pdo->prepare($sql);
$stmt->execute([$param]);
```

### IN Clause

```php
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$sql = "SELECT * FROM projects WHERE id IN ($placeholders)";
$stmt = $pdo->prepare($sql);
$stmt->execute($ids);
```

---

## User Preferences

### Available Keys

| Key | Description | Values |
|-----|-------------|--------|
| `dashboard_layout` | Widget arrangement | 'default', 'compact', 'expanded' |
| `dashboard_theme` | Color scheme | 'system', 'light', 'dark' |
| `show_charts` | Display charts | 1 or 0 |
| `show_recent_projects` | Recent projects widget | 1 or 0 |
| `show_recent_vendors` | Recent vendors widget | 1 or 0 |
| `show_recent_payments` | Recent payments widget | 1 or 0 |
| `default_page` | Landing page | 'dashboard', 'projects', 'vendors', 'reports' |
| `notifications_enabled` | In-app notifications | 1 or 0 |
| `email_alerts` | Email notifications | 1 or 0 |

---

## Common Tasks

### Add New Table

1. Create migration in `migrations/`
2. Add schema to this guide
3. Add indexes for common queries
4. Add foreign keys with ON DELETE CASCADE

### Add Column

```sql
ALTER TABLE table_name ADD COLUMN new_column VARCHAR(255) AFTER existing_column;
```

### Add Index

```sql
CREATE INDEX idx_column ON table_name(column);
```

---

## Known Issues

1. **Dual config files**: Both config.php and config.local.php exist with similar functionality
2. **No migration runner**: Must run migrations manually via MySQL CLI
3. **Core tables not in migrations**: users, projects, vendors created manually, not in migrations/

---

> **Last Updated**: 2026-04-03
