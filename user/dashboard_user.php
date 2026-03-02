<?php
/**
 * User Dashboard
 * Main dashboard page for regular users
 */

// Set page title
$page_title = 'Dashboard';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Fetch user-specific dashboard data
try {
    $pdo = getDBConnection();
    
    // Get user account info
    $stmt = $pdo->prepare("SELECT id, username, email, full_name, role, created_at, last_login FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userInfo = $stmt->fetch();
    
    // Get recent account activity
    $stmt = $pdo->prepare("SELECT action_type, description, ip_address, created_at 
                           FROM account_logs 
                           WHERE user_id = ? 
                           ORDER BY created_at DESC 
                           LIMIT 5");
    $stmt->execute([$_SESSION['user_id']]);
    $recentActivity = $stmt->fetchAll();
    
    // Get activity stats
    $stmt = $pdo->prepare("SELECT 
                              COALESCE(COUNT(*), 0) as total_activities,
                              COALESCE(SUM(CASE WHEN action_type = 'login' THEN 1 ELSE 0 END), 0) as total_logins,
                              COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0) as today_activities,
                              MAX(created_at) as last_activity
                           FROM account_logs 
                           WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $activityStats = $stmt->fetch();
    
    if (!$activityStats) {
        $activityStats = ['total_activities' => 0, 'total_logins' => 0, 'today_activities' => 0, 'last_activity' => null];
    }
    
    // Get unread notification count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $unreadNotifications = $stmt->fetch()['count'] ?? 0;
    
} catch (PDOException $e) {
    $userInfo = [];
    $recentActivity = [];
    $activityStats = ['total_activities' => 0, 'total_logins' => 0, 'today_activities' => 0, 'last_activity' => null];
    $unreadNotifications = 0;
}
?>

<div class="main-content">
    <!-- Page Header -->
    <div class="page-header">
        <div class="page-header-content">
            <div class="page-header-left">
                <div class="page-title-row">
                    <div class="page-icon">
                        <i class="fas fa-home"></i>
                    </div>
                    <div class="page-title-text">
                        <h1>Dashboard</h1>
                        <div class="page-breadcrumb">
                            <i class="fas fa-home"></i>
                            <span>Home</span>
                            <span class="page-breadcrumb-separator">/</span>
                            <span>Dashboard</span>
                        </div>
                    </div>
                </div>
                <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?>! Here's your account overview.</p>
            </div>
            <div class="page-header-right">
                <div class="page-stat-badge">
                    <i class="fas fa-calendar-day"></i>
                    <span><?php echo date('l, F j, Y'); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="stats-grid">
        <div class="stat-card stat-primary">
            <div class="stat-icon">
                <i class="fas fa-clipboard-list"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Total Activities</p>
                <h3 class="stat-value"><?php echo number_format($activityStats['total_activities']); ?></h3>
                <span class="stat-badge badge-info">All Time</span>
            </div>
        </div>

        <div class="stat-card stat-success">
            <div class="stat-icon">
                <i class="fas fa-sign-in-alt"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Total Logins</p>
                <h3 class="stat-value"><?php echo number_format($activityStats['total_logins']); ?></h3>
                <span class="stat-badge badge-success">Login History</span>
            </div>
        </div>

        <div class="stat-card stat-info">
            <div class="stat-icon">
                <i class="fas fa-calendar-check"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Today's Activities</p>
                <h3 class="stat-value"><?php echo number_format($activityStats['today_activities']); ?></h3>
                <span class="stat-badge badge-primary">Today</span>
            </div>
        </div>

        <div class="stat-card stat-warning">
            <div class="stat-icon">
                <i class="fas fa-bell"></i>
            </div>
            <div class="stat-details">
                <p class="stat-label">Unread Notifications</p>
                <h3 class="stat-value"><?php echo number_format($unreadNotifications); ?></h3>
                <span class="stat-badge badge-warning">Pending</span>
            </div>
        </div>
    </div>

    <!-- Main Content Grid -->
    <div class="dashboard-grid">
        <!-- Account Information -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-user-circle"></i> Account Information
                </h3>
                <a href="profile.php" class="card-link">Edit Profile</a>
            </div>
            <div class="card-body">
                <?php if (!empty($userInfo)): ?>
                    <div class="info-list">
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-user"></i> Full Name</span>
                            <span class="info-value"><?php echo htmlspecialchars($userInfo['full_name']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-at"></i> Username</span>
                            <span class="info-value"><?php echo htmlspecialchars($userInfo['username']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-envelope"></i> Email</span>
                            <span class="info-value"><?php echo htmlspecialchars($userInfo['email']); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-shield-alt"></i> Role</span>
                            <span class="info-value"><span class="badge badge-primary"><?php echo ucfirst($userInfo['role']); ?></span></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-calendar-plus"></i> Member Since</span>
                            <span class="info-value"><?php echo !empty($userInfo['created_at']) ? date('M d, Y', strtotime($userInfo['created_at'])) : 'N/A'; ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label"><i class="fas fa-clock"></i> Last Login</span>
                            <span class="info-value"><?php echo !empty($userInfo['last_login']) ? date('M d, Y h:i A', strtotime($userInfo['last_login'])) : 'N/A'; ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="empty-state-sm">
                        <i class="fas fa-user"></i>
                        <p>Unable to load account information</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-history"></i> Recent Activity
                </h3>
                <a href="account_logs.php" class="card-link">View All</a>
            </div>
            <div class="card-body">
                <?php if (count($recentActivity) > 0): ?>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon activity-icon-<?php 
                                    echo match($activity['action_type']) {
                                        'login' => 'success',
                                        'logout' => 'secondary',
                                        'profile_update' => 'info',
                                        'password_change' => 'warning',
                                        default => 'primary'
                                    };
                                ?>">
                                    <i class="fas <?php 
                                        echo match($activity['action_type']) {
                                            'login' => 'fa-sign-in-alt',
                                            'logout' => 'fa-sign-out-alt',
                                            'profile_update' => 'fa-user-edit',
                                            'password_change' => 'fa-key',
                                            default => 'fa-circle'
                                        };
                                    ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <p class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></p>
                                    <p class="activity-meta">
                                        <span class="badge badge-sm badge-<?php 
                                            echo match($activity['action_type']) {
                                                'login' => 'success',
                                                'logout' => 'secondary',
                                                'profile_update' => 'info',
                                                'password_change' => 'warning',
                                                default => 'primary'
                                            };
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $activity['action_type'])); ?>
                                        </span>
                                        <span class="activity-time">
                                            <?php echo date('M d, Y h:i A', strtotime($activity['created_at'])); ?>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state-sm">
                        <i class="fas fa-history"></i>
                        <p>No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card card-full" style="margin-top: 24px;">
        <div class="card-header">
            <h3 class="card-title">
                <i class="fas fa-bolt"></i> Quick Actions
            </h3>
        </div>
        <div class="card-body">
            <div class="actions-grid">
                <a href="profile.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <i class="fas fa-user-edit"></i>
                    </div>
                    <h4>Edit Profile</h4>
                    <p>Update your personal information</p>
                </a>
                
                <a href="profile.php#passwordForm" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                        <i class="fas fa-key"></i>
                    </div>
                    <h4>Change Password</h4>
                    <p>Update your account password</p>
                </a>
                
                <a href="account_logs.php" class="action-card">
                    <div class="action-icon" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <h4>Account Logs</h4>
                    <p>View your activity history</p>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
/* Info List Styles */
.info-list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    background: #f8fafc;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.info-label {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 8px;
}

.info-label i {
    width: 16px;
    text-align: center;
    color: #94a3b8;
}

.info-value {
    font-size: 14px;
    color: #1e293b;
    font-weight: 600;
}

/* Actions Grid */
.actions-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.action-card {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 28px 20px;
    background: #f8fafc;
    border-radius: 12px;
    border: 1px solid #e2e8f0;
    text-decoration: none;
    color: inherit;
    transition: all 0.3s ease;
    text-align: center;
}

.action-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
    border-color: #3b82f6;
}

.action-icon {
    width: 56px;
    height: 56px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
    color: white;
    font-size: 22px;
}

.action-card h4 {
    margin: 0 0 6px;
    font-size: 15px;
    font-weight: 700;
    color: #1e293b;
}

.action-card p {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

/* Responsive */
@media (max-width: 768px) {
    .actions-grid {
        grid-template-columns: 1fr;
    }
    
    .info-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 4px;
    }
}

@media (max-width: 480px) {
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Include footer
require_once 'include/footer.php';
?>
