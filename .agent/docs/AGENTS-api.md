# Infinity Builders — API Guide

> Guide for AI agents working on API endpoints, REST patterns, and AJAX integration.

---

## API Endpoints

| Endpoint | Methods | Description |
|----------|---------|-------------|
| `api/users.php` | GET | List all users (id, username, email, role) |
| `api/vendors.php` | GET | List all vendors (id, name, email) |
| `api/tasks.php` | GET, POST, PUT, DELETE | Full CRUD for project tasks |
| `api/documents.php` | GET, POST, DELETE | Upload, list, delete project documents |
| `api/notifications.php` | GET | Fetch notifications for admin/PM |
| `api/search.php` | GET | Global search across all entities |
| `api/project_team.php` | GET, POST, DELETE | Manage team members per project |
| `api/project_vendors.php` | GET, POST, DELETE | Assign vendors to projects |
| `api/permits.php` | GET, POST, PUT, DELETE | Full CRUD for project permits |
| `api/inspections.php` | GET, POST, PUT, DELETE | Full CRUD for project inspections |

**Base URL**: `/api/`

---

## Common API Pattern

Every API endpoint follows this structure:

```php
<?php
// Bootstrap - loads session and $pdo
require_once '../partials/init.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['user_role'] ?? 'viewer';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        handleGet($pdo, $userId, $userRole);
        break;
    case 'POST':
        handlePost($pdo, $userId, $userRole);
        break;
    case 'PUT':
    case 'PATCH':
        handlePut($pdo, $userId, $userRole);
        break;
    case 'DELETE':
        handleDelete($pdo, $userId, $userRole);
        break;
    default:
        http_response_code(405);
        echo json_encode(['error' => 'Method Not Allowed']);
}
```

---

## Response Formats

### Success Response

```json
{
  "success": true,
  "tasks": [
    { "id": 1, "title": "Install electrical", "status": "pending" }
  ]
}
```

### Success with ID

```json
{
  "success": true,
  "task_id": 42,
  "message": "Task created successfully"
}
```

### List with Stats

```json
{
  "tasks": [...],
  "stats": { "total": 10, "pending": 5, "in_progress": 3, "completed": 2 }
}
```

### Search Response

```json
{
  "results": {
    "projects": [...],
    "vendors": [...],
    "users": [...],
    "tasks": [...]
  },
  "total": 15,
  "query": "kitchen"
}
```

### Error Response

```json
{ "error": "Unauthorized" }
```

---

## HTTP Status Codes

| Code | Meaning | Usage |
|------|---------|-------|
| 200 | OK | Successful GET, PUT, DELETE |
| 201 | Created | Successful POST (create) |
| 400 | Bad Request | Missing required fields |
| 401 | Unauthorized | No session / not logged in |
| 403 | Forbidden | User lacks required role |
| 404 | Not Found | Resource not found |
| 405 | Method Not Allowed | Invalid HTTP method |
| 500 | Internal Server Error | Database failures |

---

## Error Handling

```php
// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Permission check
if (!$canManage) {
    http_response_code(403);
    echo json_encode(['error' => 'Permission denied']);
    return;
}

// Validation
if (!$projectId || !$title) {
    http_response_code(400);
    echo json_encode(['error' => 'Project ID and title required']);
    return;
}

// Database errors
try {
    // ... database operations
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create task']);
}
```

---

## API Examples

### GET - List Resources

```php
function handleGet($pdo, $userId, $userRole) {
    $projectId = $_GET['project_id'] ?? null;
    
    if ($projectId) {
        $stmt = $pdo->prepare("
            SELECT * FROM project_tasks 
            WHERE project_id = ? 
            ORDER BY created_at DESC
        ");
        $stmt->execute([$projectId]);
    } else {
        $stmt = $pdo->query("SELECT * FROM project_tasks ORDER BY created_at DESC");
    }
    
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'tasks' => $tasks
    ]);
}
```

### POST - Create Resource

```php
function handlePost($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $projectId = $data['project_id'] ?? null;
    $title = $data['title'] ?? null;
    
    if (!$projectId || !$title) {
        http_response_code(400);
        echo json_encode(['error' => 'Project ID and title required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO project_tasks (project_id, title, description, priority, assigned_to, due_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $projectId,
        $title,
        $data['description'] ?? '',
        $data['priority'] ?? 'medium',
        $data['assigned_to'] ?? null,
        $data['due_date'] ?? null,
        $userId
    ]);
    
    $taskId = $pdo->lastInsertId();
    audit_log('create', 'project_tasks', $taskId, $title, null, $userId);
    
    echo json_encode([
        'success' => true,
        'task_id' => $taskId,
        'message' => 'Task created successfully'
    ]);
}
```

### PUT - Update Resource

```php
function handlePut($pdo, $userId, $userRole) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $id = $data['id'] ?? null;
    $status = $data['status'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'Task ID required']);
        return;
    }
    
    // Get old data for audit
    $stmt = $pdo->prepare("SELECT * FROM project_tasks WHERE id = ?");
    $stmt->execute([$id]);
    $old = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $updates = [];
    $params = [];
    
    if (isset($data['status'])) {
        $updates[] = 'status = ?';
        $params[] = $data['status'];
    }
    if (isset($data['title'])) {
        $updates[] = 'title = ?';
        $params[] = $data['title'];
    }
    
    if (!empty($updates)) {
        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE project_tasks SET " . implode(', ', $updates) . " WHERE id = ?");
        $stmt->execute($params);
        
        audit_log('update', 'project_tasks', $id, $old['title'], [
            'old' => $old,
            'new' => $data
        ], $userId);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Task updated'
    ]);
}
```

### DELETE - Delete Resource

```php
function handleDelete($pdo, $userId, $userRole) {
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    
    // Get name for audit
    $stmt = $pdo->prepare("SELECT title FROM project_tasks WHERE id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $stmt = $pdo->prepare("DELETE FROM project_tasks WHERE id = ?");
    $stmt->execute([$id]);
    
    audit_log('delete', 'project_tasks', $id, $task['title'] ?? 'Unknown', null, $userId);
    
    echo json_encode([
        'success' => true,
        'message' => 'Task deleted'
    ]);
}
```

---

## Role-Based Access in API

```php
// Only admin, pm, accounting, estimator can manage tasks
$canManage = in_array($userRole, ['admin', 'pm', 'accounting', 'estimator']);

// Only admin can delete
if ($userRole !== 'admin' && $method === 'DELETE') {
    http_response_code(403);
    echo json_encode(['error' => 'Only admins can delete']);
    return;
}
```

---

## Frontend Integration

### GET Request

```javascript
fetch('api/tasks.php?project_id=' + projectId)
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.error) {
            console.error(data.error);
            return;
        }
        renderTasks(data.tasks);
    });
```

### POST (Create)

```javascript
fetch('api/tasks.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        project_id: projectId,
        title: title,
        description: desc,
        priority: priority
    })
})
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            loadTasks(projectId); // Refresh list
        }
    });
```

### PUT (Update)

```javascript
fetch('api/tasks.php', {
    method: 'PUT',
    headers: {
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        id: taskId,
        status: newStatus
    })
});
```

### DELETE

```javascript
fetch('api/tasks.php?id=' + taskId, {
    method: 'DELETE'
});
```

---

## File Upload (documents.php)

```javascript
const formData = new FormData();
formData.append('project_id', projectId);
formData.append('label', label);
formData.append('file', fileInput.files[0]);

fetch('api/documents.php', {
    method: 'POST',
    body: formData
})
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            loadDocuments(projectId);
        }
    });
```

---

## Global Search Integration

```javascript
// Ctrl+K search
fetch('api/search.php?q=' + encodeURIComponent(query))
    .then(function(r) { return r.text(); })
    .then(function(text) {
        const data = JSON.parse(text);
        if (data.error) {
            // Handle error
        } else {
            renderSearchResults(data.results, data.total);
        }
    });
```

---

## Notifications Integration

```javascript
// Bell icon click
fetch('api/notifications.php')
    .then(function(r) { return r.json(); })
    .then(function(data) {
        notificationsData = data.notifications;
        renderNotificationsPanel(data.notifications, data.count);
    });
```

---

## Common Tasks

### Add New API Endpoint

1. Create `api/new_endpoint.php`
2. Add bootstrap and auth check
3. Implement handleGet, handlePost, etc.
4. Return JSON responses
5. Add frontend integration in JS

### Add New Field to Response

1. Add field to SQL SELECT
2. Include in JSON response array

### Add Role Restriction

1. Check `$userRole` at start of handler
2. Return 403 if not authorized

---

> **Last Updated**: 2026-04-03
