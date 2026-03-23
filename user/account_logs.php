<?php
/**
 * Account Logs Page (User)
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
                                <a href="dashboard_user.php"><i class="fas fa-home"></i> Home</a>
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
                    <button type="submit" class="btn-primary-modern" id="applyFilterBtn" disabled>
                        <i class="fas fa-search"></i> Apply Filter
                    </button>
                    <a href="account_logs.php" class="btn-secondary-modern" id="resetFilterBtn" style="pointer-events: none; opacity: 0.5;">
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

<link rel="stylesheet" href="../assets/css/account-logs.css">
<script src="../assets/js/account-logs.js"></script>
