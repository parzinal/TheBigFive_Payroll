<?php
/**
 * Admin Sidebar Component
 * Reusable sidebar for all admin pages
 */
?>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-brand">
            <div class="brand-logo">
                <i class="fas fa-user-circle"></i>
            </div>
            <div class="brand-text">
                <span class="brand-title"><?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Admin'); ?></span>
                <span class="brand-subtitle"><?php echo ucfirst($_SESSION['role'] ?? 'Administrator'); ?></span>
            </div>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a href="dashboard.php" class="menu-link">
                    <i class="fas fa-home menu-icon"></i>
                    <span class="menu-text">Dashboard</span>
                </a>
            </li>
            
            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <i class="fas fa-users menu-icon"></i>
                    <span class="menu-text">Employees</span>
                    <i class="fas fa-chevron-right submenu-arrow"></i>
                </div>
                <ul class="submenu">
                    <li class="menu-item">
                        <a href="employee_list.php" class="menu-link">
                            <i class="fas fa-list menu-icon"></i>
                            <span class="menu-text">All Employees</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="Add_emplooyees.php" class="menu-link">
                            <i class="fas fa-user-plus menu-icon"></i>
                            <span class="menu-text">Add Employee</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="employee_positions.php" class="menu-link">
                            <i class="fas fa-briefcase menu-icon"></i>
                            <span class="menu-text">Positions</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="menu-item has-submenu">
                <div class="menu-link">
                    <i class="fas fa-money-bill-wave menu-icon"></i>
                    <span class="menu-text">Payroll</span>
                    <i class="fas fa-chevron-right submenu-arrow"></i>
                </div>
                <ul class="submenu">
                    <li class="menu-item">
                        <a href="payroll_list.php" class="menu-link">
                            <i class="fas fa-list menu-icon"></i>
                            <span class="menu-text">Payroll List</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="Generatepayroll.php" class="menu-link">
                            <i class="fas fa-calculator menu-icon"></i>
                            <span class="menu-text">Generate Payroll</span>
                        </a>
                    </li>
                    <li class="menu-item">
                        <a href="payslip_history.php" class="menu-link">
                            <i class="fas fa-receipt menu-icon"></i>
                            <span class="menu-text">Payslip History</span>
                        </a>
                    </li>
                </ul>
            </li>
            
            <li class="menu-item">
                <a href="user_management.php" class="menu-link">
                    <i class="fas fa-user-shield menu-icon"></i>
                    <span class="menu-text">Account Management</span>
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
            
            <li class="menu-item">
                <a href="settings.php" class="menu-link">
                    <i class="fas fa-cog menu-icon"></i>
                    <span class="menu-text">Settings</span>
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
    
    // Restore submenu states immediately (only if sidebar is expanded)
    if (sidebarState !== 'collapsed') {
        const savedSubmenuStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
        Object.keys(savedSubmenuStates).forEach(menuText => {
            if (savedSubmenuStates[menuText] === true) {
                const menuItems = document.querySelectorAll('.has-submenu');
                menuItems.forEach(item => {
                    const menuLink = item.querySelector('.menu-link .menu-text');
                    if (menuLink && menuLink.textContent.trim() === menuText) {
                        item.classList.add('open');
                    }
                });
            }
        });
    }
    
    // Set active menu item based on current page immediately
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.menu-link[href]');
    
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPage || href.includes(currentPage))) {
            link.classList.add('active');
            
            // If it's in a submenu, ensure the submenu is expanded
            const parentSubmenu = link.closest('.submenu');
            if (parentSubmenu) {
                const parentItem = parentSubmenu.closest('.has-submenu');
                if (parentItem && sidebarState !== 'collapsed') {
                    parentItem.classList.add('open');
                    
                    // Update localStorage
                    const menuText = parentItem.querySelector('.menu-link .menu-text');
                    if (menuText) {
                        const submenuStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
                        submenuStates[menuText.textContent.trim()] = true;
                        localStorage.setItem('submenuStates', JSON.stringify(submenuStates));
                    }
                }
            }
        }
    });
    
    // Mark sidebar as loaded to enable transitions only for user interactions
    setTimeout(function() {
        sidebar.classList.add('loaded');
    }, 50);
})();
</script>
