<?php
/**
 * training.php - Training materials page for admin uploads
 */

$pageTitle = 'Training';
$currentPage = 'training';

require_once 'partials/init.php';

// Only admin can upload training materials
$userRole = $_SESSION['user_role'] ?? 'user';
if ($userRole !== 'admin') {
    http_response_code(403);
    die('Access denied. Only administrators can access this page.');
}

// Handle success/error messages from redirects
$message = '';
$messageType = '';
if (isset($_GET['message'])) {
    $messages = [
        'uploaded' => 'Training material uploaded successfully.',
        'updated' => 'Training material updated successfully.',
        'deleted' => 'Training material deleted successfully.',
        'error' => 'An error occurred. Please try again.'
    ];
    $message = $messages[$_GET['message']] ?? 'An error occurred.';
    $messageType = isset($_GET['error']) ? 'error' : 'success';
}

// Generate CSRF token
require_once 'includes/security.php';
$csrf_token = csrf_token_generate();

// Get all training materials
$materials = [];
try {
    $stmt = $pdo->query("
        SELECT t.*, u.username as created_by_name 
        FROM training_materials t 
        LEFT JOIN users u ON t.created_by = u.id 
        WHERE t.is_active = TRUE 
        ORDER BY t.created_at DESC
    ");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $materials = [];
}

// Group by type
$videos = array_filter($materials, fn($m) => $m['type'] === 'video');
$documents = array_filter($materials, fn($m) => $m['type'] === 'document');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Training - Infinity Builders</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <?php include 'partials/header.php'; ?>
    
    <div class="content-wrapper">
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="page-header">
            <div class="page-header-title">
                <h1><i class="fa-solid fa-graduation-cap"></i> Training Materials</h1>
                <p class="page-subtitle">Upload videos and documents to train your team</p>
            </div>
            <div class="page-header-actions">
                <button class="btn btn-primary" onclick="openUploadModal()">
                    <i class="fa-solid fa-plus"></i> Add New
                </button>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="tabs-container">
            <div class="tabs">
                <button class="tab active" data-tab="videos" onclick="switchTab('videos')">
                    <i class="fa-solid fa-video"></i> Videos 
                    <span class="tab-badge"><?php echo count($videos); ?></span>
                </button>
                <button class="tab" data-tab="documents" onclick="switchTab('documents')">
                    <i class="fa-solid fa-file-lines"></i> Documents 
                    <span class="tab-badge"><?php echo count($documents); ?></span>
                </button>
            </div>
            
            <!-- Videos List -->
            <div class="tab-content active" id="tab-videos">
                <?php if (empty($videos)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-video-slash"></i>
                        <h3>No videos yet</h3>
                        <p>Upload training videos to help your team learn</p>
                        <button class="btn btn-primary" onclick="openUploadModal()">
                            <i class="fa-solid fa-plus"></i> Upload Video
                        </button>
                    </div>
                <?php else: ?>
                    <div class="training-list">
                        <?php foreach ($videos as $video): ?>
                            <div class="training-list-item">
                                <div class="training-list-icon video" onclick="viewMaterial(<?php echo $video['id']; ?>, 'video')">
                                    <i class="fa-solid fa-play"></i>
                                </div>
                                <div class="training-list-content" onclick="viewMaterial(<?php echo $video['id']; ?>, 'video')">
                                    <h3><?php echo htmlspecialchars($video['title']); ?></h3>
                                    <?php if ($video['description']): ?>
                                        <p><?php echo htmlspecialchars($video['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="training-list-meta">
                                        <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($video['created_by_name']); ?></span>
                                        <span><i class="fa-solid fa-calendar"></i> <?php echo date('M j, Y', strtotime($video['created_at'])); ?></span>
                                        <?php if ($video['duration_seconds']): ?>
                                            <span><i class="fa-solid fa-clock"></i> <?php echo formatDuration($video['duration_seconds']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="training-list-actions">
                                    <button class="btn btn-sm btn-secondary" onclick="editMaterial(<?php echo $video['id']; ?>)" title="Edit">
                                        <i class="fa-solid fa-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $video['id']; ?>)" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Documents List -->
            <div class="tab-content" id="tab-documents">
                <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-file-xmark"></i>
                        <h3>No documents yet</h3>
                        <p>Upload training documents to help your team learn</p>
                        <button class="btn btn-primary" onclick="openUploadModal()">
                            <i class="fa-solid fa-plus"></i> Upload Document
                        </button>
                    </div>
                <?php else: ?>
                    <div class="training-list">
                        <?php foreach ($documents as $doc): ?>
                            <div class="training-list-item">
                                <div class="training-list-icon document" onclick="viewMaterial(<?php echo $doc['id']; ?>, 'document')">
                                    <i class="fa-solid fa-file-pdf"></i>
                                </div>
                                <div class="training-list-content" onclick="viewMaterial(<?php echo $doc['id']; ?>, 'document')">
                                    <h3><?php echo htmlspecialchars($doc['title']); ?></h3>
                                    <?php if ($doc['description']): ?>
                                        <p><?php echo htmlspecialchars($doc['description']); ?></p>
                                    <?php endif; ?>
                                    <div class="training-list-meta">
                                        <span><i class="fa-solid fa-user"></i> <?php echo htmlspecialchars($doc['created_by_name']); ?></span>
                                        <span><i class="fa-solid fa-calendar"></i> <?php echo date('M j, Y', strtotime($doc['created_at'])); ?></span>
                                        <span><i class="fa-solid fa-hard-drive"></i> <?php echo round($doc['file_size'] / 1024, 1); ?> KB</span>
                                    </div>
                                </div>
                                <div class="training-list-actions">
                                    <button class="btn btn-sm btn-secondary" onclick="editMaterial(<?php echo $doc['id']; ?>)" title="Edit">
                                        <i class="fa-solid fa-pencil"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteMaterial(<?php echo $doc['id']; ?>)" title="Delete">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upload/Edit Modal -->
    <div class="modal-overlay" id="uploadModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Training Material</h2>
                <button type="button" class="modal-close" onclick="closeUploadModal()">&times;</button>
            </div>
            <form id="trainingForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" id="materialId" name="id" value="">
                    
                    <div class="form-group">
                        <label for="materialTitle">Title *</label>
                        <input type="text" id="materialTitle" name="title" required placeholder="e.g., How to approve permits">
                    </div>
                    
                    <div class="form-group">
                        <label for="materialDescription">Description</label>
                        <textarea id="materialDescription" name="description" rows="3" placeholder="Brief description of this training material..."></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label>Type *</label>
                        <div class="radio-group">
                            <label class="radio-label">
                                <input type="radio" name="type" value="document" checked onchange="toggleDuration()">
                                <span><i class="fa-solid fa-file-lines"></i> Document</span>
                            </label>
                            <label class="radio-label">
                                <input type="radio" name="type" value="video" onchange="toggleDuration()">
                                <span><i class="fa-solid fa-video"></i> Video</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group" id="durationGroup" style="display: none;">
                        <label for="durationSeconds">Duration (seconds)</label>
                        <input type="number" id="durationSeconds" name="duration_seconds" min="0" placeholder="e.g., 300 for 5 minutes">
                    </div>
                    
                    <div class="form-group">
                        <label for="materialFile">File *</label>
                        <input type="file" id="materialFile" name="file" required accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.mp4,.webm,.mov,.avi">
                        <small class="form-hint">PDF, Word, Excel, PowerPoint or video files (max 100MB)</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeUploadModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Upload</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- View Modal -->
    <div class="modal-overlay" id="viewModal">
        <div class="modal-content modal-large">
            <div class="modal-header">
                <h2 id="viewTitle">Training Material</h2>
                <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="viewContent"></div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-content modal-small">
            <div class="modal-header">
                <h2>Delete Training Material</h2>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this training material? This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>
    
    <?php include 'partials/footer.php'; ?>
    
    <script>
        // Store materials data
        const materials = <?php echo json_encode(array_values($materials)); ?>;
        
        const uploadModal = document.getElementById('uploadModal');
        const viewModal = document.getElementById('viewModal');
        const deleteModal = document.getElementById('deleteModal');
        const form = document.getElementById('trainingForm');
        
        let currentDeleteId = null;
        
        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            
            document.querySelector(`.tab[data-tab="${tabName}"]`).classList.add('active');
            document.getElementById('tab-' + tabName).classList.add('active');
        }
        
        function toggleDuration() {
            const type = document.querySelector('input[name="type"]:checked').value;
            document.getElementById('durationGroup').style.display = type === 'video' ? 'block' : 'none';
        }
        
        function openUploadModal() {
            document.getElementById('modalTitle').textContent = 'Add Training Material';
            document.getElementById('submitBtn').textContent = 'Upload';
            form.reset();
            document.getElementById('materialId').value = '';
            document.getElementById('materialFile').required = true;
            toggleDuration();
            uploadModal.classList.add('active');
        }
        
        function closeUploadModal() {
            uploadModal.classList.remove('active');
        }
        
        function editMaterial(id) {
            const material = materials.find(m => m.id == id);
            if (!material) return;
            
            document.getElementById('modalTitle').textContent = 'Edit Training Material';
            document.getElementById('submitBtn').textContent = 'Save Changes';
            document.getElementById('materialId').value = id;
            document.getElementById('materialFile').required = false;
            document.getElementById('materialTitle').value = material.title;
            document.getElementById('materialDescription').value = material.description || '';
            document.querySelector(`input[name="type"][value="${material.type}"]`).checked = true;
            
            if (material.duration_seconds) {
                document.getElementById('durationSeconds').value = material.duration_seconds;
            }
            
            toggleDuration();
            uploadModal.classList.add('active');
        }
        
        function viewMaterial(id, type) {
            const material = materials.find(m => m.id == id);
            if (!material) return;
            
            document.getElementById('viewTitle').textContent = material.title;
            const viewContent = document.getElementById('viewContent');
            
            if (type === 'video') {
                viewContent.innerHTML = `
                    <div class="video-wrapper">
                        <video id="trainingVideo" controls playsinline style="width: 100%; border-radius: 8px; background: #000;">
                            <source src="${material.file_path}" type="video/mp4">
                            Your browser does not support video playback.
                        </video>
                    </div>
                    ${material.description ? `<p class="view-description">${material.description}</p>` : ''}
                `;
                
                const video = document.getElementById('trainingVideo');
                video.addEventListener('loadeddata', () => console.log('Video loaded'));
                video.addEventListener('error', (e) => console.error('Video error:', video.error));
            } else {
                viewContent.innerHTML = `
                    <iframe src="${material.file_path}" style="width: 100%; height: 60vh; border: none; border-radius: 8px;"></iframe>
                    ${material.description ? `<p class="view-description">${material.description}</p>` : ''}
                `;
            }
            
            viewModal.classList.add('active');
        }
        
        function closeViewModal() {
            const viewContent = document.getElementById('viewContent');
            viewContent.innerHTML = '';
            viewModal.classList.remove('active');
        }
        
        function deleteMaterial(id) {
            currentDeleteId = id;
            deleteModal.classList.add('active');
        }
        
        function closeDeleteModal() {
            currentDeleteId = null;
            deleteModal.classList.remove('active');
        }
        
        async function confirmDelete() {
            if (!currentDeleteId) return;
            
            try {
                const response = await fetch('api/training.php?id=' + currentDeleteId, {
                    method: 'DELETE',
                    headers: { 'X-CSRF-Token': '<?php echo $csrf_token; ?>' }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Failed to delete material'));
                }
            } catch (e) {
                alert('Error deleting material');
            }
            
            closeDeleteModal();
        }
        
        // Form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const submitBtn = document.getElementById('submitBtn');
            const originalBtnText = submitBtn.textContent;
            submitBtn.disabled = true;
            submitBtn.textContent = 'Uploading...';
            
            const formData = new FormData(form);
            
            try {
                const response = await fetch('api/training.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-CSRF-Token': formData.get('csrf_token')
                    }
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const isEdit = formData.get('id');
                    window.location.href = 'training.php?message=' + (isEdit ? 'updated' : 'uploaded');
                } else {
                    alert('Error: ' + (data.error || 'Failed to upload material'));
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalBtnText;
                }
            } catch (e) {
                alert('Error uploading material');
                submitBtn.disabled = false;
                submitBtn.textContent = originalBtnText;
            }
        });
        
        // Close modals on escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeUploadModal();
                closeViewModal();
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>

<?php
function formatDuration($seconds) {
    if (!$seconds) return '';
    $minutes = floor($seconds / 60);
    $secs = $seconds % 60;
    return sprintf('%d:%02d', $minutes, $secs);
}
