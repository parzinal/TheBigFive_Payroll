<?php
/**
 * Admin Header Component
 * Reusable header for all admin pages
 */

// Require authentication helper
require_once __DIR__ . '/../../config/auth.php';

// Require admin role - will redirect if not authenticated or wrong role
requireAuth('admin');

// Get user information
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin';
$user_role = 'Administrator';
$user_initial = strtoupper(substr($user_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>TheBigFive Payroll - Admin</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php echo csrfMetaTag(); ?>
</head>
<body>

<header class="header">
    <div class="header-container">
        <div class="header-left">
            <button class="mobile-toggle-btn" id="mobileSidebarToggle" title="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="header-brand-section">
                <img src="../assets/images/blue.png" alt="TheBigFive Logo" class="header-logo">
                <span class="header-system-name">TheBigFive Payroll System</span>
            </div>
        </div>
        
        <div class="header-right">
            <!-- Notifications -->
            <div class="header-item notifications-dropdown">
                <button class="header-btn" id="notificationsBtn">
                    <i class="fas fa-bell"></i>
                    <span class="badge" style="display: none;">0</span>
                </button>
                <div class="dropdown-menu notifications-menu">
                    <div class="dropdown-header">
                        <h4>Notifications</h4>
                        <div class="notification-actions">
                            <a href="#" class="mark-all-read">Mark all as read</a>
                            <button class="delete-all-read-btn" title="Delete all read notifications">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </div>
                    </div>
                    <div class="dropdown-body notifications-list">
                        <!-- Notifications will be loaded dynamically -->
                        <div class="loading-notifications">
                            <i class="fas fa-spinner fa-spin" style="font-size: 24px; color: #6b7280;"></i>
                            <p style="color: #6b7280; font-size: 14px; margin-top: 8px;">Loading...</p>
                        </div>
                    </div>
                    
                </div>
            </div>
            
            <!-- User Profile -->
            <div class="header-item user-dropdown">
                <button class="header-btn user-btn" id="userMenuBtn">
                    <div class="user-avatar"><?php echo $user_initial; ?></div>
                    <div class="user-info">
                        <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        <span class="user-role"><?php echo htmlspecialchars($user_role); ?></span>
                    </div>
                    <i class="fas fa-chevron-down"></i>
                </button>
                <div class="dropdown-menu user-menu">
                    <div class="dropdown-header">
                        <div class="user-avatar-large"><?php echo $user_initial; ?></div>
                        <div>
                            <p class="user-name-large"><?php echo htmlspecialchars($user_name); ?></p>
                            <p class="user-role-small"><?php echo htmlspecialchars($user_role); ?></p>
                        </div>
                    </div>
                    <div class="dropdown-body">
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user"></i>
                            <span>My Profile</span>
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cog"></i>
                            <span>Settings</span>
                        </a>
                    </div>
                    <div class="dropdown-footer">
                        <a href="javascript:void(0)" onclick="openLogoutModal()" class="dropdown-item logout-item">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
