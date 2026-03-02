<?php
/**
 * Account Logs Page (Staff)
 * View personal account activity logs
 */

// Set page title
$page_title = 'My Account Logs';

// Include header
require_once 'include/header.php';

// Include database connection
require_once '../config/database.php';

// Include sidebar
require_once 'include/sidebar.php';

// Pagination settings
$records_per_page = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filter settings
$filter_date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$filter_date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Fetch logs from database - only for current user
try {
    $pdo = getDBConnection();
    
    // Build query with filters - always filter by current user
    $where_conditions = ["account_logs.user_id = ?"];
    $params = [$_SESSION['user_id']];
    
    if (!empty($filter_date_from)) {
        $where_conditions[] = "DATE(account_logs.created_at) >= ?";
        $params[] = $filter_date_from;
    }
    
    if (!empty($filter_date_to)) {
        $where_conditions[] = "DATE(account_logs.created_at) <= ?";
        $params[] = $filter_date_to;
    }
    
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    
    // Get total count for pagination
    $count_query = "SELECT COUNT(*) FROM account_logs {$where_clause}";
    $count_stmt = $pdo->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = $count_stmt->fetchColumn();
    $total_pages = ceil($total_records / $records_per_page);
    
    // Fetch logs
    $query = "SELECT account_logs.*, users.full_name 
              FROM account_logs 
              LEFT JOIN users ON account_logs.user_id = users.id 
              {$where_clause} 
              ORDER BY account_logs.created_at DESC 
              LIMIT {$records_per_page} OFFSET {$offset}";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();
    
    // Get activity stats for current user
    $stats_query = "SELECT 
                        COALESCE(COUNT(*), 0) as total_activities,
                        COALESCE(SUM(CASE WHEN action_type = 'login' THEN 1 ELSE 0 END), 0) as total_logins,
                        COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END), 0) as today_activities,
                        MAX(created_at) as last_activity
                    FROM account_logs 
                    WHERE user_id = ?";
    $stats_stmt = $pdo->prepare($stats_query);
    $stats_stmt->execute([$_SESSION['user_id']]);
    $stats = $stats_stmt->fetch();
    
    // Ensure stats are never null
    if (!$stats) {
        $stats = ['total_activities' => 0, 'total_logins' => 0, 'today_activities' => 0, 'last_activity' => null];
    }
    
} catch (PDOException $e) {
    $error = "Error fetching logs: " . $e->getMessage();
    $logs = [];
    $total_records = 0;
    $total_pages = 0;
    $stats = ['total_activities' => 0, 'total_logins' => 0, 'today_activities' => 0, 'last_activity' => null];
}

// Action type badge colors and icons
function getActionTypeInfo($action_type) {
    $info = [
        'login' => ['class' => 'badge-success', 'icon' => 'fa-sign-in-alt'],
        'logout' => ['class' => 'badge-secondary', 'icon' => 'fa-sign-out-alt'],
        'profile_update' => ['class' => 'badge-info', 'icon' => 'fa-user-edit'],
        'password_change' => ['class' => 'badge-warning', 'icon' => 'fa-key'],
        'create' => ['class' => 'badge-primary', 'icon' => 'fa-plus-circle'],
        'update' => ['class' => 'badge-info', 'icon' => 'fa-edit'],
        'delete' => ['class' => 'badge-danger', 'icon' => 'fa-trash-alt'],
        'other' => ['class' => 'badge-secondary', 'icon' => 'fa-ellipsis-h']
    ];
    return $info[$action_type] ?? ['class' => 'badge-secondary', 'icon' => 'fa-circle'];
}
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-left">
                    <div class="page-title-row">
                        <div class="page-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="page-title-text">
                            <h1>My Account Activity</h1>
                            <div class="page-breadcrumb">
                                <a href="dashboard_staff.php"><i class="fas fa-home"></i> Home</a>
                                <span class="page-breadcrumb-separator">/</span>
                                <span>Account Logs</span>
                            </div>
                        </div>
                    </div>
                    <p class="page-subtitle">Monitor your account security and activity history</p>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card stat-primary">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Total Activities</p>
                    <h3 class="stat-value"><?php echo number_format($stats['total_activities'] ?? 0); ?></h3>
                </div>
            </div>
            
            <div class="stat-card stat-success">
                <div class="stat-icon">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Total Logins</p>
                    <h3 class="stat-value"><?php echo number_format($stats['total_logins'] ?? 0); ?></h3>
                </div>
            </div>
            
            <div class="stat-card stat-info">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Today's Activity</p>
                    <h3 class="stat-value"><?php echo number_format($stats['today_activities'] ?? 0); ?></h3>
                </div>
            </div>
            
            <div class="stat-card stat-warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <p class="stat-label">Last Activity</p>
                    <h3 class="stat-value">
                        <?php 
                        if ($stats['last_activity']) {
                            $last = new DateTime($stats['last_activity']);
                            $now = new DateTime();
                            $diff = $now->diff($last);
                            
                            if ($diff->days > 0) {
                                echo $diff->days . 'd ago';
                            } elseif ($diff->h > 0) {
                                echo $diff->h . 'h ago';
                            } else {
                                echo $diff->i . 'm ago';
                            }
                        } else {
                            echo 'Never';
                        }
                        ?>
                    </h3>
                </div>
            </div>
        </div>
        
        <div class="old-page-header-modern" style="display:none">
        <!-- Page Header with Stats -->
        <div class="page-header-modern">
            <div class="header-content">
                <div class="header-title-section">
                    <div class="icon-wrapper">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <div>
                        <h1>My Account Activity</h1>
                        <p class="subtitle">Monitor your account security and activity history</p>
                    </div>
                </div>
            </div>
            
            <!-- Stats Cards -->
            <div class="stats-grid">
                <div class="stat-card stat-primary">
                    <div class="stat-icon">
                        <i class="fas fa-history"></i>
                    </div>
                    <div class="stat-details">
                        <p class="stat-label">Total Activities</p>
                        <h3 class="stat-value"><?php echo number_format($stats['total_activities'] ?? 0); ?></h3>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">
                        <i class="fas fa-sign-in-alt"></i>
                    </div>
                    <div class="stat-details">
                        <p class="stat-label">Total Logins</p>
                        <h3 class="stat-value"><?php echo number_format($stats['total_logins'] ?? 0); ?></h3>
                    </div>
                </div>
                
                <div class="stat-card stat-info">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day"></i>
                    </div>
                    <div class="stat-details">
                        <p class="stat-label">Today's Activity</p>
                        <h3 class="stat-value"><?php echo number_format($stats['today_activities'] ?? 0); ?></h3>
                    </div>
                </div>
                
                <div class="stat-card stat-warning">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-details">
                        <p class="stat-label">Last Activity</p>
                        <h3 class="stat-value">
                            <?php 
                            if ($stats['last_activity']) {
                                $last = new DateTime($stats['last_activity']);
                                $now = new DateTime();
                                $diff = $now->diff($last);
                                
                                if ($diff->days > 0) {
                                    echo $diff->days . 'd ago';
                                } elseif ($diff->h > 0) {
                                    echo $diff->h . 'h ago';
                                } else {
                                    echo $diff->i . 'm ago';
                                }
                            } else {
                                echo 'Never';
                            }
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger-modern">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Filters Card -->
        <div class="filter-card">
            <div class="filter-header">
                <h5><i class="fas fa-filter"></i> Filter Activity</h5>
                <button class="btn-icon" onclick="document.getElementById('filterForm').classList.toggle('show')">
                    <i class="fas fa-chevron-down"></i>
                </button>
            </div>
            <form method="GET" action="account_logs.php" id="filterForm" class="filter-form">
                <div class="filter-grid">
                    <div class="form-group">
                        <label for="date_from"><i class="fas fa-calendar-alt"></i> Date From</label>
                        <input type="date" name="date_from" id="date_from" class="form-input-modern" value="<?php echo htmlspecialchars($filter_date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to"><i class="fas fa-calendar-alt"></i> Date To</label>
                        <input type="date" name="date_to" id="date_to" class="form-input-modern" value="<?php echo htmlspecialchars($filter_date_to); ?>">
                    </div>
                </div>
                
                <div class="filter-actions">
                    <button type="submit" class="btn-primary-modern">
                        <i class="fas fa-search"></i> Apply Filter
                    </button>
                    <a href="account_logs.php" class="btn-secondary-modern">
                        <i class="fas fa-redo"></i> Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- Activity Timeline -->
        <div class="logs-card">
            <div class="logs-header">
                <h5><i class="fas fa-stream"></i> Activity Timeline</h5>
                <span class="badge-count"><?php echo number_format($total_records); ?> records</span>
            </div>
            <div class="logs-body">
                <?php if (empty($logs)): ?>
                    <div class="empty-state">
                        <i class="fas fa-clipboard-list"></i>
                        <h3>No Activities Found</h3>
                        <p>Your account activity will appear here</p>
                    </div>
                <?php else: ?>
                    <div class="activity-timeline">
                        <?php foreach ($logs as $log): 
                            $info = getActionTypeInfo($log['action_type']);
                            $date = new DateTime($log['created_at']);
                        ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?php echo $info['class']; ?>">
                                    <i class="fas <?php echo $info['icon']; ?>"></i>
                                </div>
                                <div class="timeline-content">
                                    <div class="timeline-header">
                                        <div class="timeline-title">
                                            <h4><?php echo htmlspecialchars($log['action']); ?></h4>
                                            <span class="badge-modern <?php echo $info['class']; ?>">
                                                <?php echo strtoupper(str_replace('_', ' ', $log['action_type'])); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-time">
                                            <i class="far fa-clock"></i>
                                            <?php echo $date->format('M d, Y h:i A'); ?>
                                        </div>
                                    </div>
                                    <?php if ($log['description']): ?>
                                        <p class="timeline-description">
                                            <?php echo htmlspecialchars($log['description']); ?>
                                        </p>
                                    <?php endif; ?>
                                    <div class="timeline-meta">
                                        <span class="meta-item">
                                            <i class="fas fa-network-wired"></i>
                                            <?php echo htmlspecialchars($log['ip_address'] ?? 'Unknown'); ?>
                                        </span>
                                        <?php if ($log['user_agent']): ?>
                                            <span class="meta-item" title="<?php echo htmlspecialchars($log['user_agent']); ?>">
                                                <i class="fas fa-desktop"></i>
                                                <?php 
                                                // Parse user agent to show browser/device
                                                $ua = $log['user_agent'];
                                                if (strpos($ua, 'Chrome') !== false) echo 'Chrome';
                                                elseif (strpos($ua, 'Firefox') !== false) echo 'Firefox';
                                                elseif (strpos($ua, 'Safari') !== false) echo 'Safari';
                                                elseif (strpos($ua, 'Edge') !== false) echo 'Edge';
                                                else echo 'Browser';
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <nav class="pagination-modern">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page - 1); ?><?php echo $filter_date_from ? '&date_from=' . $filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to=' . $filter_date_to : ''; ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            
                            for ($i = $start_page; $i <= $end_page; $i++):
                            ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $filter_date_from ? '&date_from=' . $filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to=' . $filter_date_to : ''; ?>">
                                        <?php echo $i; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?php echo ($page + 1); ?><?php echo $filter_date_from ? '&date_from=' . $filter_date_from : ''; ?><?php echo $filter_date_to ? '&date_to=' . $filter_date_to : ''; ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>

<style>
/* Modern Account Logs Styles */
.content-wrapper {
    padding: 2rem;
    max-width: 1400px;
    margin: 0 auto;
}

.page-header-modern {
    margin-bottom: 2rem;
}

.header-content {
    margin-bottom: 1.5rem;
}

.header-title-section {
    display: flex;
    align-items: center;
    gap: 1rem;
}

.icon-wrapper {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 28px;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
}

.header-title-section h1 {
    font-size: 28px;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
}

.subtitle {
    color: #64748b;
    margin: 0.25rem 0 0 0;
    font-size: 14px;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    border-left: 4px solid;
    transition: transform 0.2s, box-shadow 0.2s;
}

.stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.stat-card.stat-primary { border-left-color: #3b82f6; }
.stat-card.stat-success { border-left-color: #10b981; }
.stat-card.stat-info { border-left-color: #06b6d4; }
.stat-card.stat-warning { border-left-color: #f59e0b; }

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
}

.stat-primary .stat-icon {
    background: #eff6ff;
    color: #3b82f6;
}

.stat-success .stat-icon {
    background: #d1fae5;
    color: #10b981;
}

.stat-info .stat-icon {
    background: #cffafe;
    color: #06b6d4;
}

.stat-warning .stat-icon {
    background: #fef3c7;
    color: #f59e0b;
}

.stat-details {
    flex: 1;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 0.25rem 0;
    font-weight: 500;
}

.stat-value {
    font-size: 24px;
    font-weight: 700;
    margin: 0;
    color: #1e293b;
}

/* Alert Modern */
.alert-danger-modern {
    background: #fee2e2;
    border: 1px solid #fecaca;
    border-left: 4px solid #ef4444;
    border-radius: 8px;
    padding: 1rem 1.25rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: #991b1b;
    margin-bottom: 1.5rem;
}

.alert-danger-modern i {
    font-size: 20px;
}

/* Filter Card */
.filter-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    margin-bottom: 2rem;
    overflow: hidden;
}

.filter-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.filter-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}

.filter-header h5 i {
    color: #3b82f6;
    margin-right: 0.5rem;
}

.btn-icon {
    background: none;
    border: none;
    padding: 0.5rem;
    cursor: pointer;
    color: #64748b;
    transition: color 0.2s;
}

.btn-icon:hover {
    color: #3b82f6;
}

.filter-form {
    padding: 1.5rem;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.25rem;
    margin-bottom: 1.25rem;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    font-size: 13px;
    font-weight: 600;
    color: #475569;
    margin-bottom: 0.5rem;
}

.form-group label i {
    color: #3b82f6;
    margin-right: 0.25rem;
}

.form-select-modern,
.form-input-modern {
    padding: 0.75rem 1rem;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
    background: white;
}

.form-select-modern:focus,
.form-input-modern:focus {
    outline: none;
    border-color: #3b82f6;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.filter-actions {
    display: flex;
    gap: 1rem;
}

.btn-primary-modern,
.btn-secondary-modern {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    transition: all 0.2s;
    border: none;
    text-decoration: none;
}

.btn-primary-modern {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
}

.btn-primary-modern:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
}

.btn-secondary-modern {
    background: #f1f5f9;
    color: #475569;
}

.btn-secondary-modern:hover {
    background: #e2e8f0;
}

/* Logs Card */
.logs-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    overflow: hidden;
}

.logs-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.logs-header h5 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #1e293b;
}

.logs-header h5 i {
    color: #3b82f6;
    margin-right: 0.5rem;
}

.badge-count {
    background: #eff6ff;
    color: #3b82f6;
    padding: 0.375rem 0.75rem;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.logs-body {
    padding: 1.5rem;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 4rem 2rem;
    color: #64748b;
}

.empty-state i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 1rem;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 600;
    color: #475569;
    margin: 0 0 0.5rem 0;
}

.empty-state p {
    margin: 0;
    font-size: 14px;
}

/* Activity Timeline */
.activity-timeline {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
}

.timeline-item {
    display: flex;
    gap: 1rem;
    position: relative;
}

.timeline-item:not(:last-child)::before {
    content: '';
    position: absolute;
    left: 20px;
    top: 45px;
    bottom: -24px;
    width: 2px;
    background: linear-gradient(180deg, #e2e8f0 0%, transparent 100%);
}

.timeline-marker {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    font-size: 16px;
    z-index: 1;
}

.timeline-marker.badge-success {
    background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
    color: #059669;
}

.timeline-marker.badge-secondary {
    background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
    color: #64748b;
}

.timeline-marker.badge-info {
    background: linear-gradient(135deg, #cffafe 0%, #a5f3fc 100%);
    color: #0891b2;
}

.timeline-marker.badge-warning {
    background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
    color: #d97706;
}

.timeline-marker.badge-primary {
    background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
    color: #2563eb;
}

.timeline-marker.badge-danger {
    background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
    color: #dc2626;
}

.timeline-content {
    flex: 1;
    background: #f8fafc;
    border-radius: 12px;
    padding: 1.25rem;
    border: 1px solid #e2e8f0;
    transition: all 0.2s;
}

.timeline-content:hover {
    border-color: #cbd5e1;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.timeline-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 0.75rem;
    gap: 1rem;
}

.timeline-title {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

.timeline-title h4 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: #1e293b;
}

.badge-modern {
    padding: 0.25rem 0.75rem;
    border-radius: 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-modern.badge-success {
    background: #d1fae5;
    color: #059669;
}

.badge-modern.badge-secondary {
    background: #f1f5f9;
    color: #64748b;
}

.badge-modern.badge-info {
    background: #cffafe;
    color: #0891b2;
}

.badge-modern.badge-warning {
    background: #fef3c7;
    color: #d97706;
}

.badge-modern.badge-primary {
    background: #dbeafe;
    color: #2563eb;
}

.badge-modern.badge-danger {
    background: #fee2e2;
    color: #dc2626;
}

.timeline-time {
    font-size: 13px;
    color: #64748b;
    white-space: nowrap;
    display: flex;
    align-items: center;
    gap: 0.25rem;
}

.timeline-description {
    margin: 0 0 0.75rem 0;
    font-size: 14px;
    color: #475569;
    line-height: 1.6;
}

.timeline-meta {
    display: flex;
    gap: 1.5rem;
    flex-wrap: wrap;
}

.meta-item {
    font-size: 12px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.375rem;
}

.meta-item i {
    color: #94a3b8;
}

/* Pagination Modern */
.pagination-modern {
    margin-top: 2rem;
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 0.5rem;
    list-style: none;
    padding: 0;
    margin: 0;
}

.page-item {
    margin: 0;
}

.page-link {
    padding: 0.5rem 0.75rem;
    min-width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    color: #475569;
    text-decoration: none;
    font-weight: 600;
    font-size: 14px;
    transition: all 0.2s;
}

.page-link:hover {
    background: #f8fafc;
    border-color: #cbd5e1;
    color: #1e293b;
}

.page-item.active .page-link {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    border-color: #3b82f6;
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .content-wrapper {
        padding: 1rem;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .timeline-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .filter-actions {
        flex-direction: column;
    }
    
    .btn-primary-modern,
    .btn-secondary-modern {
        width: 100%;
        justify-content: center;
    }
}
</style>
