<?php
/**
 * User Sidebar Component
 * Reusable sidebar for all user pages
 */
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="fas fa-user"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></span>
                <span class="brand-subtitle"><?php echo ucfirst($_SESSION['role'] ?? 'User'); ?></span>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="dashboard_user.php" class="menu-link">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="profile.php" class="menu-link">
                    <i class="fas fa-user-circle menu-icon"></i>
                    <span class="menu-text">My Profile</span>
                </a>
            </li>
            
            <li class="menu-item">
                <a href="account_logs.php" class="menu-link">
                    <i class="fas fa-clipboard-list menu-icon"></i>
                    <span class="menu-text">Account Logs</span>
                </a>
            </li>
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <div class="menu-item">
            <a href="javascript:void(0)" onclick="openLogoutModal()" class="menu-link">
                <i class="fas fa-sign-out-alt menu-icon"></i>
                <span class="menu-text">Logout</span>
            </a>
        </div>
    </div>
</aside>

<script>
// Immediately restore sidebar state to prevent visual "reload" effect
(function() {
    const sidebar = document.querySelector('.sidebar');
    if (!sidebar) return;
    
    // Restore sidebar collapsed/expanded state immediately
    const sidebarState = localStorage.getItem('sidebarState') || 'expanded';
    if (sidebarState === 'collapsed') {
        sidebar.classList.add('collapsed');
    }
    
    // Set active menu item based on current page immediately
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.menu-link[href]');
    
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPage || href.includes(currentPage))) {
            link.classList.add('active');
        }
    });
    
    // Mark sidebar as loaded to enable transitions only for user interactions
    setTimeout(function() {
        sidebar.classList.add('loaded');
    }, 50);
})();
</script>
