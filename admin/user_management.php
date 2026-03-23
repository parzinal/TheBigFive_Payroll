<?php
/**
 * Account Management Page
 * Manage all system accounts (Admin, Staff)
 */

// Set page title
$page_title = 'Account Management';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include account logs helper
require_once '../config/account_logs_helper.php';

// Include sidebar
require_once 'include/sidebar.php';

// Check for success message
$success = $_GET['success'] ?? '';

// Handle AJAX update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    header('Content-Type: application/json');
    
    // CSRF check
    if (!validateCSRFToken()) {
        echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page.']);
        exit;
    }
    
    try {
        $pdo = getDBConnection();
        
        $user_id = $_POST['user_id'];
        $username = strtolower(trim($_POST['username']));
        $full_name = trim($_POST['full_name']);
        $email = strtolower(trim($_POST['email']));
        $role = $_POST['role'];
        $status = $_POST['status'];
        
        // Validation
        $errors = [];
        
        if (empty($username)) {
            $errors[] = "Username is required";
        } elseif (strlen($username) < 3) {
            $errors[] = "Username must be at least 3 characters";
        } elseif (!preg_match('/^[a-z0-9_]+$/', $username)) {
            $errors[] = "Username can only contain lowercase letters, numbers, and underscores";
        }
        
        if (empty($full_name)) {
            $errors[] = "Full name is required";
        } elseif (strlen($full_name) < 2) {
            $errors[] = "Full name must be at least 2 characters";
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Valid email is required";
        }
        
        // Check if username already exists for another user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
            $stmt->execute([$username, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Username already exists";
            }
        }
        
        // Check if email already exists for another user
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $user_id]);
            if ($stmt->fetch()) {
                $errors[] = "Email already exists";
            }
        }
        
        if (!empty($errors)) {
            echo json_encode(['success' => false, 'message' => implode(", ", $errors)]);
            exit();
        }
        
        // Update user
        $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, email = ?, role = ?, status = ?, updated_at = NOW() WHERE id = ?");
        
        if ($stmt->execute([$username, $full_name, $email, $role, $status, $user_id])) {
            // Log the action
            logUpdateAction(
                $_SESSION['user_id'],
                $_SESSION['username'],
                'User Account',
                "{$full_name} ({$username})",
                "Role: {$role}, Status: {$status}",
                $pdo
            );
            
            echo json_encode(['success' => true, 'message' => 'User updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit();
}

// Fetch all users from database
try {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, status, created_at FROM users ORDER BY created_at DESC");
    $stmt->execute();
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Error fetching users: " . $e->getMessage();
    $users = [];
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Account Management</h1>
                        <div class="page-breadcrumb">
                            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Accounts</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Manage system user accounts and permissions</p>
            </div>
            <div class="page-header-right">
                <div class="page-header-actions">
                    <button class="btn-header-primary" onclick="window.location.href='add_user.php'">
                        <i class="fas fa-user-plus"></i>
                        Add New Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">All Accounts</h3>
            <div class="card-tools">
                <div class="filter-buttons">
                    <button class="filter-btn active" data-filter="all">
                        <i class="fas fa-users"></i> All
                    </button>
                    <button class="filter-btn" data-filter="admin">
                        <i class="fas fa-user-shield"></i> Admins
                    </button>
                    <button class="filter-btn" data-filter="staff">
                        <i class="fas fa-user-tie"></i> Staff
                    </button>
                </div>
                <input type="text" id="searchUsers" class="user-search-input" placeholder="Search users...">
            </div>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="data-table" id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Created Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($users) > 0): ?>
                            <?php foreach ($users as $user): ?>
                                <tr data-role="<?php echo $user['role']; ?>">
                                    <td><?php echo htmlspecialchars($user['id']); ?></td>
                                    <td>
                                        <div class="user-info">
                                            <i class="fas fa-user-circle"></i>
                                            <?php echo htmlspecialchars($user['username']); ?>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <?php
                                        $roleClass = '';
                                        $roleIcon = '';
                                        switch ($user['role']) {
                                            case 'admin':
                                                $roleClass = 'badge-danger';
                                                $roleIcon = 'fa-user-shield';
                                                break;
                                            case 'staff':
                                                $roleClass = 'badge-warning';
                                                $roleIcon = 'fa-user-tie';
                                                break;
                                            case 'user':
                                                $roleClass = 'badge-info';
                                                $roleIcon = 'fa-user';
                                                break;
                                            default:
                                                $roleClass = 'badge-secondary';
                                                $roleIcon = 'fa-user';
                                        }
                                        ?>
                                        <span class="badge <?php echo $roleClass; ?>">
                                            <i class="fas <?php echo $roleIcon; ?>"></i>
                                            <?php echo ucfirst($user['role']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($user['status'] == 'active'): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">
                                                <i class="fas fa-ban"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" title="Edit User" 
                                                    onclick="openEditModal(
                                                        <?php echo $user['id']; ?>,
                                                        '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>',
                                                        '<?php echo htmlspecialchars($user['full_name'], ENT_QUOTES); ?>',
                                                        '<?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?>',
                                                        '<?php echo $user['role']; ?>',
                                                        '<?php echo $user['status']; ?>'
                                                    )">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" title="Delete User" 
                                                    onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">
                                    <div class="empty-state">
                                        <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 15px;"></i>
                                        <p style="color: #999;">No users found</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">User Statistics</h3>
        </div>
        <div class="card-body" style="padding: 24px;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <?php
                $adminCount = count(array_filter($users, function($u) { return $u['role'] == 'admin'; }));
                $staffCount = count(array_filter($users, function($u) { return $u['role'] == 'staff'; }));
                $activeCount = count(array_filter($users, function($u) { return $u['status'] == 'active'; }));
                ?>
                
                <div class="stat-card stat-admin">
                    <i class="fas fa-user-shield stat-icon"></i>
                    <h4>Administrators</h4>
                    <p class="stat-value"><?php echo $adminCount; ?></p>
                </div>
                
                <div class="stat-card stat-staff">
                    <i class="fas fa-user-tie stat-icon"></i>
                    <h4>Staff Members</h4>
                    <p class="stat-value"><?php echo $staffCount; ?></p>
                </div>
                
                <div class="stat-card stat-active">
                    <i class="fas fa-check-circle stat-icon"></i>
                    <h4>Active Accounts</h4>
                    <p class="stat-value"><?php echo $activeCount; ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content" style="position: relative;">
        <!-- Loading Overlay -->
        <div id="editModalOverlay" class="modal-loading-overlay">
            <div class="spinner"></div>
            <span class="loading-text">Updating user...</span>
        </div>
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit User</h3>
            <button class="modal-close" onclick="closeEditModal()">&times;</button>
        </div>
        <form id="editUserForm" onsubmit="updateUser(event)">
            <input type="hidden" id="edit_user_id" name="user_id">
            
            <div class="modal-body">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_username" class="form-label">
                            <i class="fas fa-user-tag"></i> Username <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="edit_username" 
                               name="username" 
                               class="form-input" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="edit_full_name" class="form-label">
                            <i class="fas fa-user"></i> Full Name <span class="required">*</span>
                        </label>
                        <input type="text" 
                               id="edit_full_name" 
                               name="full_name" 
                               class="form-input" 
                               required>
                    </div>

                    <div class="form-group full-width">
                        <label for="edit_email" class="form-label">
                            <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                        </label>
                        <input type="email" 
                               id="edit_email" 
                               name="email" 
                               class="form-input" 
                               required>
                    </div>

                    <div class="form-group">
                        <label for="edit_role" class="form-label">
                            <i class="fas fa-shield-alt"></i> Role <span class="required">*</span>
                        </label>
                        <select id="edit_role" name="role" class="form-input" required>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="edit_status" class="form-label">
                            <i class="fas fa-info-circle"></i> Status <span class="required">*</span>
                        </label>
                        <select id="edit_status" name="status" class="form-input" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn btn-primary" id="btnUpdateUser" disabled>
                    <i class="fas fa-save"></i> Update User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Confirmation Modal -->
<div id="deleteConfirmModal" class="modal">
    <div class="modal-content delete-modal-content">
        <div class="delete-modal-body">
            <div class="delete-warning-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h2 class="delete-modal-title">Delete User</h2>
            <p class="delete-modal-message">
                Are you sure you want to permanently delete<br>
                <span id="deleteUserNameDisplay" class="user-name-highlight"></span>?
            </p>
            <p class="delete-modal-warning">This action cannot be undone.</p>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                <i class="fas fa-times"></i> Cancel
            </button>
            <button type="button" class="btn btn-danger" onclick="confirmDeleteAction()">
                <i class="fas fa-check"></i> Confirm
            </button>
        </div>
    </div>
</div>

<!-- Notification Toast -->
<div id="notification" class="notification"></div>

<style>
/* Field validation styles */
.form-input.input-error {
    border-color: #EF4444 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.1) !important;
}
.form-input.input-success {
    border-color: #10B981 !important;
    box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1) !important;
}
.field-error-msg {
    color: #EF4444;
    font-size: 12px;
    margin-top: 4px;
    display: none;
    align-items: center;
    gap: 4px;
}
.field-error-msg.show {
    display: flex;
}
.field-error-msg i {
    font-size: 11px;
}
.btn:disabled, .btn[disabled] {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* Modern Flat Design - User Management */
.main-content {
    background: #F9FAFB;
    min-height: 100vh;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
    flex-wrap: wrap;
    gap: 15px;
}

.page-title {
    font-size: 28px;
    font-weight: 700;
    color: #111827;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.page-title i {
    color: #2563EB;
    font-size: 32px;
}

.page-actions {
    display: flex;
    gap: 10px;
}

.btn {
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #2563EB;
    color: white;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.2);
}

.btn-primary:hover {
    background: #1d4ed8;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-primary i {
    font-size: 16px;
}

.card {
    background: #FFFFFF;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border: 1px solid #E5E7EB;
    overflow: hidden;
    margin-bottom: 24px;
}

.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid #E5E7EB;
    background: #FFFFFF;
    flex-wrap: wrap;
    gap: 15px;
}

.card-title {
    font-size: 18px;
    font-weight: 600;
    color: #111827;
    margin: 0;
}

.card-tools {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.filter-buttons {
    display: flex;
    gap: 8px;
    background: #F9FAFB;
    padding: 4px;
    border-radius: 8px;
    border: 1px solid #E5E7EB;
}

.filter-btn {
    padding: 8px 16px;
    border: none;
    background: transparent;
    color: #6B7280;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.filter-btn i {
    font-size: 14px;
}

.filter-btn:hover {
    background: #E5E7EB;
    color: #374151;
}

.filter-btn.active {
    background: #2563EB;
    color: white;
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
}

.user-search-input {
    padding: 10px 16px;
    border: 1px solid #E5E7EB;
    border-radius: 8px;
    font-size: 14px;
    width: 250px;
    transition: all 0.2s ease;
    background: #FFFFFF;
}

.user-search-input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.card-body {
    padding: 0;
}

.table-responsive {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    background: white;
}

.data-table thead {
    background: #F9FAFB;
}

.data-table th {
    padding: 16px 20px;
    text-align: left;
    font-weight: 600;
    color: #374151;
    border-bottom: 1px solid #E5E7EB;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.data-table td {
    padding: 16px 20px;
    border-bottom: 1px solid #F3F4F6;
    color: #1F2937;
}

.data-table tbody tr {
    transition: all 0.15s ease;
}

.data-table tbody tr:hover {
    background-color: #F9FAFB;
}

.user-info {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: 500;
}

.user-info i {
    font-size: 20px;
    color: #9CA3AF;
}

.badge {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    letter-spacing: 0.3px;
}

.badge i {
    font-size: 12px;
}

.badge-danger {
    background: #2563EB;
    color: white;
}

.badge-warning {
    background: #F59E0B;
    color: white;
}

.badge-info {
    background: #2563EB;
    color: white;
}

.badge-success {
    background: #10B981;
    color: white;
}

.badge-secondary {
    background: #6B7280;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-icon {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    justify-content: center;
}

.btn-icon i {
    font-size: 14px;
}

.btn-edit {
    background: #2563EB;
    color: white;
}

.btn-edit:hover {
    background: #1d4ed8;
    box-shadow: 0 2px 8px rgba(37, 99, 235, 0.3);
}

.btn-delete {
    background: #EF4444;
    color: white;
}

.btn-delete:hover {
    background: #dc2626;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.empty-state {
    padding: 60px 40px;
    text-align: center;
}

.empty-state i {
    color: #D1D5DB;
}

.empty-state p {
    color: #9CA3AF;
    font-size: 16px;
    margin-top: 15px;
}

.stat-card {
    padding: 24px;
    border-radius: 12px;
    color: white;
    text-align: center;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    transition: all 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-card .stat-icon {
    font-size: 36px;
    margin-bottom: 12px;
    opacity: 0.95;
}

.stat-card h4 {
    font-size: 13px;
    font-weight: 600;
    margin: 12px 0;
    opacity: 0.9;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-card .stat-value {
    font-size: 32px;
    font-weight: 700;
    margin: 12px 0 0 0;
}

.stat-admin {
    background: #2563EB;
}

.stat-staff {
    background: #2563EB;
}

.stat-users {
    background: #F59E0B;
}

.stat-active {
    background: #10B981;
}

.alert {
    padding: 14px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 500;
    border: 1px solid;
}

.alert i {
    font-size: 18px;
}

.alert-danger {
    background-color: #FEE2E2;
    color: #991B1B;
    border-color: #FECACA;
}

.alert-success {
    background-color: #D1FAE5;
    color: #065F46;
    border-color: #A7F3D0;
}

.text-center {
    text-align: center;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-content {
    background-color: #FFFFFF;
    margin: 5% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
}

@keyframes slideDown {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    padding: 20px 24px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 32px;
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: background-color 0.2s;
}

.modal-close:hover {
    background-color: rgba(255, 255, 255, 0.1);
}

.modal-body {
    padding: 24px;
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.form-grid .full-width {
    grid-column: span 2;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-label {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
    font-weight: 500;
    color: #374151;
    font-size: 14px;
}

.form-label i {
    color: #6B7280;
    font-size: 14px;
}

.required {
    color: #EF4444;
    font-weight: bold;
}

.form-input,
select.form-input {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #D1D5DB;
    border-radius: 8px;
    font-size: 14px;
    color: #111827;
    background-color: #FFFFFF;
    transition: all 0.2s ease;
    font-family: inherit;
}

.form-input:focus,
select.form-input:focus {
    outline: none;
    border-color: #2563EB;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid #E5E7EB;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    background: #F9FAFB;
    border-radius: 0 0 12px 12px;
}

.btn-secondary {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #D1D5DB;
}

.btn-secondary:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

/* Notification Toast */
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 16px 24px;
    border-radius: 8px;
    color: white;
    font-weight: 500;
    display: none;
    align-items: center;
    gap: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    z-index: 1001;
    animation: slideInRight 0.3s ease;
}

@keyframes slideInRight {
    from {
        transform: translateX(400px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.notification.success {
    background: #10B981;
}

.notification.error {
    background: #EF4444;
}

.notification i {
    font-size: 20px;
}

/* Delete Confirmation Modal Styles */
.delete-modal-content {
    background-color: #FFFFFF;
    margin: 5% auto;
    border-radius: 12px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideDown 0.3s ease;
    overflow: hidden;
}

.delete-modal-body {
    padding: 40px 32px;
    text-align: center;
    background: #FFFFFF;
}

.delete-warning-icon {
    width: 80px;
    height: 80px;
    background: #FEE2E2;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 24px;
    font-size: 40px;
    color: #EF4444;
}

.delete-modal-title {
    font-size: 24px;
    font-weight: 700;
    color: #111827;
    margin: 0 0 16px 0;
}

.delete-modal-message {
    font-size: 15px;
    color: #6B7280;
    margin: 0 0 12px 0;
    line-height: 1.5;
}

.user-name-highlight {
    font-weight: 600;
    color: #111827;
    display: inline-block;
    max-width: 100%;
    word-break: break-word;
}

.delete-modal-warning {
    font-size: 13px;
    color: #9CA3AF;
    margin: 0;
    font-style: italic;
}

.delete-modal-footer {
    display: flex;
    justify-content: center;
    gap: 12px;
    padding: 20px 32px;
    border-top: 1px solid #E5E7EB;
    background: #F9FAFB;
}

.btn-secondary {
    background: #FFFFFF;
    color: #374151;
    border: 1px solid #D1D5DB;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
}

.btn-secondary:hover {
    background: #F9FAFB;
    border-color: #9CA3AF;
}

.btn-danger {
    background: #EF4444;
    color: white;
    box-shadow: 0 1px 3px rgba(239, 68, 68, 0.2);
}

.btn-danger:hover {
    background: #DC2626;
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

/* Responsive Modal */
@media (max-width: 768px) {
    .modal-content {
        width: 95%;
        margin: 10% auto;
    }
    
    .form-grid {
        grid-template-columns: 1fr;
    }
    
    .form-grid .full-width {
        grid-column: span 1;
    }
    
    .modal-footer {
        flex-direction: column;
    }
    
    .modal-footer .btn {
        width: 100%;
        justify-content: center;
    }
}

/* Modal Loading Overlay */
.modal-loading-overlay {
    display: none;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(255, 255, 255, 0.85);
    backdrop-filter: blur(2px);
    z-index: 10;
    border-radius: 12px;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 16px;
}

.modal-loading-overlay.active {
    display: flex;
}

.modal-loading-overlay .spinner {
    width: 48px;
    height: 48px;
    border: 4px solid #E5E7EB;
    border-top: 4px solid #2563EB;
    border-radius: 50%;
    animation: overlaySpinner 0.8s linear infinite;
}

.modal-loading-overlay .loading-text {
    font-size: 15px;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.3px;
}

@keyframes overlaySpinner {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}
</style>

<script>
// Email to lowercase in edit modal
document.addEventListener('DOMContentLoaded', function() {
    const editEmailInput = document.getElementById('edit_email');
    if (editEmailInput) {
        editEmailInput.addEventListener('input', function() {
            this.value = this.value.toLowerCase();
        });
    }
});

// Auto-hide success message after 5 seconds
if (document.querySelector('.alert-success')) {
    setTimeout(function() {
        const successAlert = document.querySelector('.alert-success');
        if (successAlert) {
            successAlert.style.transition = 'opacity 0.5s ease';
            successAlert.style.opacity = '0';
            setTimeout(function() {
                successAlert.remove();
            }, 500);
        }
    }, 5000);
}

// Search functionality
document.getElementById('searchUsers').addEventListener('keyup', function() {
    filterTable();
});

// Filter buttons functionality
document.querySelectorAll('.filter-btn').forEach(button => {
    button.addEventListener('click', function() {
        // Remove active class from all buttons
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        
        // Add active class to clicked button
        this.classList.add('active');
        
        // Filter table
        filterTable();
    });
});

function filterTable() {
    // Ensure original order is tagged on first run
    (function tagOriginalRowOrder() {
        const tbody = document.querySelector('#usersTable tbody');
        if (!tbody) return;
        const rows = Array.from(tbody.querySelectorAll('tr'));
        rows.forEach((r, i) => {
            if (typeof r.dataset.originalIndex === 'undefined') r.dataset.originalIndex = i;
        });
    })();

    let searchValue = document.getElementById('searchUsers').value.trim().toLowerCase();
    let activeFilter = document.querySelector('.filter-btn.active').getAttribute('data-filter');
    let table = document.getElementById('usersTable');
    let tbody = table.getElementsByTagName('tbody')[0];
    let rows = Array.from(tbody.getElementsByTagName('tr'));

    // If search is empty, restore original order and show by filter
    if (!searchValue) {
        rows.sort((a, b) => (parseInt(a.dataset.originalIndex || 0) - parseInt(b.dataset.originalIndex || 0)));
        rows.forEach(row => {
            let role = row.getAttribute('data-role');
            if (activeFilter === 'all' || role === activeFilter) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
            tbody.appendChild(row);
        });
        return;
    }

    const startsWithMatches = [];
    const containsMatches = [];

    rows.forEach(row => {
        const role = row.getAttribute('data-role');
        if (!(activeFilter === 'all' || role === activeFilter)) {
            row.style.display = 'none';
            return;
        }

        // Full Name is the 3rd column (td index 2)
        const tds = row.getElementsByTagName('td');
        const nameText = (tds[2] && tds[2].textContent) ? tds[2].textContent.toLowerCase().trim() : '';

        const parts = nameText.split(/\s+/).filter(Boolean);
        const isStart = parts.some(p => p.startsWith(searchValue));

        if (isStart) {
            startsWithMatches.push(row);
        } else if (nameText.indexOf(searchValue) > -1) {
            containsMatches.push(row);
        } else {
            row.style.display = 'none';
        }
    });

    // Append matches: startsWith first, then contains
    startsWithMatches.forEach(r => { r.style.display = ''; tbody.appendChild(r); });
    containsMatches.forEach(r => { r.style.display = ''; tbody.appendChild(r); });
}

// Store delete context
let pendingDeleteId = null;
let pendingDeleteUsername = null;

// Delete confirmation - Show Modal
function confirmDelete(userId, username) {
    pendingDeleteId = userId;
    pendingDeleteUsername = username;
    document.getElementById('deleteUserNameDisplay').textContent = username;
    document.getElementById('deleteConfirmModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close Delete Modal
function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    pendingDeleteId = null;
    pendingDeleteUsername = null;
}

// Confirm delete action (C2: uses POST + CSRF instead of GET)
function confirmDeleteAction() {
    if (!pendingDeleteId) return;
    const btn = document.querySelector('#deleteConfirmModal .btn-danger');
    if (btn) { btn.disabled = true; btn.textContent = 'Deleting...'; }

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    fetch('delete_user.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken
        },
        body: JSON.stringify({ id: pendingDeleteId })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            window.location.href = 'user_management.php?success=' + encodeURIComponent(data.message);
        } else {
            alert(data.message || 'Delete failed');
            if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
        }
    })
    .catch(() => {
        alert('Network error. Please try again.');
        if (btn) { btn.disabled = false; btn.textContent = 'Delete'; }
    });
}

// Close modal when clicking outside
window.onclick = function(event) {
    const deleteModal = document.getElementById('deleteConfirmModal');
    if (event.target === deleteModal) {
        closeDeleteModal();
    }
}

// Open Edit Modal
let editOriginalValues = {};

function showEditFieldError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('input-error');
    input.classList.remove('input-success');
    let errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error-msg';
        errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span></span>';
        input.insertAdjacentElement('afterend', errEl);
    }
    errEl.querySelector('span').textContent = message;
    errEl.classList.add('show');
}

function clearEditFieldError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    const errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (errEl) errEl.classList.remove('show');
}

function markEditFieldValid(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    input.classList.add('input-success');
    clearEditFieldError(inputId);
}

function checkEditChanges() {
    const username = document.getElementById('edit_username').value.trim();
    const fullName = document.getElementById('edit_full_name').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    const role = document.getElementById('edit_role').value;
    const status = document.getElementById('edit_status').value;
    const changed = (username !== editOriginalValues.username ||
                     fullName !== editOriginalValues.full_name ||
                     email !== editOriginalValues.email ||
                     role !== editOriginalValues.role ||
                     status !== editOriginalValues.status);
    const btn = document.getElementById('btnUpdateUser');
    if (btn) btn.disabled = !changed;
    return changed;
}

['edit_username', 'edit_full_name', 'edit_email', 'edit_role', 'edit_status'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', checkEditChanges);
        el.addEventListener('change', checkEditChanges);
    }
});

const editUsernameInput = document.getElementById('edit_username');
if (editUsernameInput) {
    editUsernameInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
        const val = this.value.trim();
        if (val.length === 0) { clearEditFieldError('edit_username'); }
        else if (val.length < 3) { showEditFieldError('edit_username', 'Username must be at least 3 characters'); }
        else if (!/^[a-z0-9_]+$/.test(val)) { showEditFieldError('edit_username', 'Only lowercase letters, numbers, and underscores allowed'); }
        else { markEditFieldValid('edit_username'); }
        checkEditChanges();
    });
}

const editEmailInput = document.getElementById('edit_email');
if (editEmailInput) {
    editEmailInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/\s/g, '');
        const val = this.value.trim();
        if (val.length === 0) { clearEditFieldError('edit_email'); }
        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) { showEditFieldError('edit_email', 'Please enter a valid email address'); }
        else { markEditFieldValid('edit_email'); }
        checkEditChanges();
    });
}

const editFullNameInput = document.getElementById('edit_full_name');
if (editFullNameInput) {
    editFullNameInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.length === 0) { clearEditFieldError('edit_full_name'); }
        else if (val.length < 2) { showEditFieldError('edit_full_name', 'Full name must be at least 2 characters'); }
        else { markEditFieldValid('edit_full_name'); }
        checkEditChanges();
    });
}

function openEditModal(id, username, fullName, email, role, status) {
    editOriginalValues = { username: username, full_name: fullName, email: email, role: role, status: status };
    document.getElementById('edit_user_id').value = id;
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_full_name').value = fullName;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_status').value = status;
    
    // Clear any previous field errors and reset button state
    ['edit_username', 'edit_full_name', 'edit_email'].forEach(function(fid) {
        clearEditFieldError(fid);
        const inp = document.getElementById(fid);
        if (inp) inp.classList.remove('input-success', 'input-error');
    });
    document.getElementById('btnUpdateUser').disabled = true;
    
    document.getElementById('editModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
}

// Close Edit Modal
function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
    document.body.style.overflow = 'auto';
    document.getElementById('editUserForm').reset();
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target === modal) {
        closeEditModal();
    }
}

// Update User via AJAX
function updateUser(event) {
    event.preventDefault();
    
    // Client-side validation before submit
    let hasError = false;
    const username = document.getElementById('edit_username').value.trim();
    const fullName = document.getElementById('edit_full_name').value.trim();
    const email = document.getElementById('edit_email').value.trim();
    
    if (username.length < 3) { showEditFieldError('edit_username', 'Username must be at least 3 characters'); hasError = true; }
    else if (!/^[a-z0-9_]+$/.test(username)) { showEditFieldError('edit_username', 'Only lowercase letters, numbers, and underscores allowed'); hasError = true; }
    if (fullName.length < 2) { showEditFieldError('edit_full_name', 'Full name must be at least 2 characters'); hasError = true; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showEditFieldError('edit_email', 'Please enter a valid email address'); hasError = true; }
    if (hasError) return;
    
    const formData = new FormData(document.getElementById('editUserForm'));
    formData.append('action', 'update_user');
    
    // Show loading state on button
    const submitBtn = event.target.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
    submitBtn.disabled = true;
    
    // Show modal loading overlay
    const overlay = document.getElementById('editModalOverlay');
    overlay.classList.add('active');
    
    fetch('user_management.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        // Hide overlay
        overlay.classList.remove('active');
        
        if (data.success) {
            showNotification(data.message, 'success');
            closeEditModal();
            
            // Reload page after a short delay to show updated data
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification(data.message, 'error');
            // Restore button
            submitBtn.innerHTML = originalText;
            submitBtn.disabled = false;
        }
    })
    .catch(error => {
        // Hide overlay
        overlay.classList.remove('active');
        
        showNotification('An error occurred while updating the user', 'error');
        console.error('Error:', error);
        // Restore button
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
}

// Show Notification
function showNotification(message, type) {
    const notification = document.getElementById('notification');
    notification.className = 'notification ' + type;
    notification.innerHTML = '<i class="fas fa-' + (type === 'success' ? 'check-circle' : 'exclamation-circle') + '"></i> ' + message;
    notification.style.display = 'flex';
    
    setTimeout(() => {
        notification.style.display = 'none';
    }, 4000);
}
</script>

<?php
// Include footer
require_once 'include/footer.php';
?>
