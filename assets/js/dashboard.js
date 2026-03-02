/**
 * Dashboard JavaScript - Modern Sidebar System
 * Handles sidebar expand/collapse, dark mode, header dropdowns, and interactive features
 */

/**
 * CSRF Protection Helper
 * Reads the CSRF token from the <meta name="csrf-token"> tag.
 * Automatically injects it into FormData objects and fetch headers.
 */
function getCSRFToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * Wrap the native fetch to auto-inject CSRF token on POST/PUT/DELETE requests.
 * - For FormData bodies: appends 'csrf_token' field
 * - For all mutating methods: adds X-CSRF-Token header
 */
(function() {
    const originalFetch = window.fetch;
    window.fetch = function(url, options = {}) {
        const method = (options.method || 'GET').toUpperCase();

        if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
            const token = getCSRFToken();

            // Inject into FormData body
            if (token && options.body instanceof FormData) {
                if (!options.body.has('csrf_token')) {
                    options.body.append('csrf_token', token);
                }
            }

            // Always add the header as well
            if (token) {
                options.headers = options.headers || {};
                if (options.headers instanceof Headers) {
                    if (!options.headers.has('X-CSRF-Token')) {
                        options.headers.set('X-CSRF-Token', token);
                    }
                } else {
                    if (!options.headers['X-CSRF-Token']) {
                        options.headers['X-CSRF-Token'] = token;
                    }
                }
            }
        }

        return originalFetch.call(this, url, options);
    };
})();

document.addEventListener('DOMContentLoaded', function() {
    
    // Get elements
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    const mobileToggleBtn = document.getElementById('mobileSidebarToggle');
    // Dark mode removed
    const searchInput = document.querySelector('.search-input');
    
    // Enable smooth transitions after initial load (prevents bouncing effect)
    if (sidebar && !sidebar.classList.contains('loaded')) {
        setTimeout(function() {
            sidebar.classList.add('loaded');
        }, 100);
    }
    
    // Header elements
    const notificationsBtn = document.getElementById('notificationsBtn');
    const userMenuBtn = document.getElementById('userMenuBtn');
    const notificationsMenu = document.querySelector('.notifications-menu');
    const userMenu = document.querySelector('.user-menu');
    
    // Check for saved states in localStorage
    const sidebarState = localStorage.getItem('sidebarState') || 'expanded';
    // State restoration is now handled inline in sidebar.php for instant application
    // This prevents the visual "reload" effect when navigating between pages
    
    // Dark mode functionality removed
    
    // Header hamburger toggle (works on desktop and mobile)
    if (mobileToggleBtn && sidebar) {
        mobileToggleBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (window.innerWidth <= 768) {
                // On mobile, toggle active class
                sidebar.classList.toggle('active');
            } else {
                // On desktop, toggle collapsed state
                sidebar.classList.toggle('collapsed');
                const newState = sidebar.classList.contains('collapsed') ? 'collapsed' : 'expanded';
                localStorage.setItem('sidebarState', newState);
                
                // Close all submenus when toggling to/from collapsed
                document.querySelectorAll('.has-submenu.open').forEach(item => {
                    item.classList.remove('open');
                });
                
                // When expanding, restore saved submenu states
                if (newState === 'expanded') {
                    const savedStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
                    const activePage = window.location.pathname.split('/').pop();
                    document.querySelectorAll('.has-submenu').forEach(menuItem => {
                        const menuText = menuItem.querySelector('.menu-link .menu-text').textContent.trim();
                        const hasActiveChild = menuItem.querySelector('.submenu .menu-link.active');
                        const hasActiveHref = menuItem.querySelector('.submenu .menu-link[href="' + activePage + '"]');
                        if (hasActiveChild || hasActiveHref || savedStates[menuText]) {
                            menuItem.classList.add('open');
                        }
                    });
                }
            }
        });
    }
    
    // Dark mode toggle removed
    
    // Header Notifications Dropdown
    if (notificationsBtn && notificationsMenu) {
        notificationsBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            notificationsMenu.classList.toggle('show');
            // Close user menu if open
            if (userMenu) userMenu.classList.remove('show');
        });
    }
    
    // Header User Menu Dropdown
    if (userMenuBtn && userMenu) {
        userMenuBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            userMenu.classList.toggle('show');
            // Close notifications menu if open
            if (notificationsMenu) notificationsMenu.classList.remove('show');
        });
    }
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        // Check if click is outside notifications
        if (notificationsMenu) {
            const isClickInsideNotifications = notificationsMenu.contains(e.target);
            const isClickOnNotificationsBtn = notificationsBtn && notificationsBtn.contains(e.target);
            
            if (!isClickInsideNotifications && !isClickOnNotificationsBtn) {
                notificationsMenu.classList.remove('show');
            }
        }
        
        // Check if click is outside user menu
        if (userMenu) {
            const isClickInsideUserMenu = userMenu.contains(e.target);
            const isClickOnUserMenuBtn = userMenuBtn && userMenuBtn.contains(e.target);
            
            if (!isClickInsideUserMenu && !isClickOnUserMenuBtn) {
                userMenu.classList.remove('show');
            }
        }
        
        // Close mobile sidebar when clicking outside
        if (window.innerWidth <= 768) {
            if (sidebar && !sidebar.contains(e.target) && !mobileToggleBtn.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        }
        
        // Close collapsed sidebar submenus when clicking outside
        if (sidebar && sidebar.classList.contains('collapsed')) {
            const openSubmenus = document.querySelectorAll('.sidebar.collapsed .has-submenu.open');
            openSubmenus.forEach(submenu => {
                if (!submenu.contains(e.target)) {
                    submenu.classList.remove('open');
                }
            });
        }
    });
    
    // Mark all notifications as read
    const markAllReadBtn = document.querySelector('.mark-all-read');
    if (markAllReadBtn) {
        markAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            markAllAsRead();
        });
    }
    
    // Delete all read notifications
    const deleteAllReadBtn = document.querySelector('.delete-all-read-btn');
    if (deleteAllReadBtn) {
        deleteAllReadBtn.addEventListener('click', function(e) {
            e.preventDefault();
            deleteAllReadNotifications();
        });
    }
    
    // Load notifications on page load
    fetchNotifications();
    
    // Reload notifications when dropdown is opened
    if (notificationsBtn && notificationsMenu) {
        const originalNotificationClick = notificationsBtn.onclick;
        notificationsBtn.addEventListener('click', function() {
            if (!notificationsMenu.classList.contains('show')) {
                fetchNotifications();
            }
        });
    }
    
    // Auto-refresh notifications every 30 seconds (polling)
    setInterval(function() {
        fetchNotifications(10, false); // Fetch 10 notifications, include read ones
    }, 30000); // 30 seconds
    
    // Handle submenu toggle - now handles div.menu-link clicks
    const hasSubmenuItems = document.querySelectorAll('.has-submenu > .menu-link');
    hasSubmenuItems.forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentItem = this.closest('.has-submenu');
            const wasOpen = parentItem.classList.contains('open');
            const menuText = this.querySelector('.menu-text').textContent.trim();
            const isCollapsed = sidebar.classList.contains('collapsed');
            
            if (isCollapsed) {
                // In collapsed mode: close all other submenus first
                document.querySelectorAll('.has-submenu').forEach(menuItem => {
                    if (menuItem !== parentItem) {
                        menuItem.classList.remove('open');
                    }
                });
                
                // Toggle current submenu
                parentItem.classList.toggle('open');
                
                // Position the floating submenu
                if (!wasOpen) {
                    const submenu = parentItem.querySelector('.submenu');
                    const rect = parentItem.getBoundingClientRect();
                    submenu.style.top = rect.top + 'px';
                }
            } else {
                // In expanded mode: normal toggle behavior
                parentItem.classList.toggle('open');
                
                // Save submenu state to localStorage
                const submenuStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
                submenuStates[menuText] = !wasOpen;
                localStorage.setItem('submenuStates', JSON.stringify(submenuStates));
            }
        });
    });
    
    // Set active menu item based on current page
    // Note: Initial active state is set inline in sidebar.php for instant rendering
    // This section handles ensuring parent submenu opens if child is active
    const currentPage = window.location.pathname.split('/').pop();
    const menuLinks = document.querySelectorAll('.menu-link[href]');
    
    // Restore saved submenu states from localStorage
    const savedSubmenuStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
    
    menuLinks.forEach(link => {
        const href = link.getAttribute('href');
        if (href && (href === currentPage || href.includes(currentPage))) {
            // Active class already applied inline, just ensure parent is properly configured
            const parentSubmenu = link.closest('.submenu');
            if (parentSubmenu) {
                const parentItem = parentSubmenu.closest('.has-submenu');
                if (parentItem) {
                    // Only auto-open submenu in expanded mode
                    // In collapsed mode, submenus should only open on explicit click
                    if (!sidebar.classList.contains('collapsed')) {
                        parentItem.classList.add('open');
                    }
                    
                    // Save this state to localStorage (for when sidebar is expanded)
                    const menuText = parentItem.querySelector('.menu-link .menu-text').textContent.trim();
                    savedSubmenuStates[menuText] = true;
                    localStorage.setItem('submenuStates', JSON.stringify(savedSubmenuStates));
                }
            }
        }
    });
    
    // Apply saved submenu states to all submenus that don't have active children
    // Only in expanded mode — in collapsed mode, submenus should only open on explicit click
    if (!sidebar.classList.contains('collapsed')) {
        document.querySelectorAll('.has-submenu').forEach(menuItem => {
            const menuText = menuItem.querySelector('.menu-link .menu-text').textContent.trim();
            const hasActiveChild = menuItem.querySelector('.submenu .menu-link.active');
            
            // Only restore saved state if there's no active child (active child takes priority)
            if (!hasActiveChild && savedSubmenuStates[menuText]) {
                menuItem.classList.add('open');
            }
        });
    }
    
    // Keep submenu open when clicking submenu items (prevent parent toggle)
    const submenuLinks = document.querySelectorAll('.submenu .menu-link[href]');
    submenuLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            // Don't prevent default - let the link navigate
            // But save the submenu state before navigation
            const parentSubmenu = this.closest('.submenu');
            if (parentSubmenu) {
                const parentItem = parentSubmenu.closest('.has-submenu');
                if (parentItem) {
                    const menuText = parentItem.querySelector('.menu-link .menu-text').textContent.trim();
                    const currentStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
                    currentStates[menuText] = true; // Keep it open
                    localStorage.setItem('submenuStates', JSON.stringify(currentStates));
                }
            }
        });
    });
    
    // Sidebar Search functionality
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const menuItems = document.querySelectorAll('.menu-item');
            
            menuItems.forEach(item => {
                const menuText = item.querySelector('.menu-text');
                if (menuText) {
                    const text = menuText.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                        
                        // If it's a submenu item, show parent menu
                        const parentSubmenu = item.closest('.submenu');
                        if (parentSubmenu) {
                            const parentItem = parentSubmenu.closest('.has-submenu');
                            if (parentItem) {
                                parentItem.style.display = '';
                                parentItem.classList.add('open');
                            }
                        }
                    } else {
                        // Don't hide parent menu if it has visible children
                        const isParent = item.classList.contains('has-submenu');
                        if (!isParent) {
                            item.style.display = 'none';
                        }
                    }
                }
            });
            
            // If search is empty, show all items
            if (searchTerm === '') {
                menuItems.forEach(item => {
                    item.style.display = '';
                });
                // Restore saved submenu states instead of closing all
                const savedSubmenuStates = JSON.parse(localStorage.getItem('submenuStates') || '{}');
                document.querySelectorAll('.has-submenu').forEach(menuItem => {
                    const menuText = menuItem.querySelector('.menu-link .menu-text').textContent.trim();
                    if (savedSubmenuStates[menuText]) {
                        menuItem.classList.add('open');
                    } else {
                        menuItem.classList.remove('open');
                    }
                });
            }
        });
    }
    
    // Header Search functionality
    const headerSearchInput = document.querySelector('.header-search-input');
    if (headerSearchInput) {
        headerSearchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const searchTerm = this.value.trim();
                if (searchTerm) {
                    // You can implement global search functionality here
                    console.log('Searching for:', searchTerm);
                    showNotification('Search feature coming soon!', 'info');
                }
            }
        });
    }
    
    // Handle window resize
    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768) {
                if (sidebar) sidebar.classList.remove('active');
            }
            
            // Close all dropdowns on resize
            if (notificationsMenu) notificationsMenu.classList.remove('show');
            if (userMenu) userMenu.classList.remove('show');
            
            // Reposition floating submenus in collapsed mode
            if (sidebar && sidebar.classList.contains('collapsed')) {
                document.querySelectorAll('.has-submenu.open').forEach(item => {
                    const submenu = item.querySelector('.submenu');
                    const rect = item.getBoundingClientRect();
                    if (submenu) {
                        submenu.style.top = rect.top + 'px';
                    }
                });
            }
        }, 250);
    });
    
    // Add tooltips for collapsed sidebar (works for both <a> and <div> menu-links)
    const menuLinksForTooltip = document.querySelectorAll('.menu-link');
    menuLinksForTooltip.forEach(link => {
        const menuText = link.querySelector('.menu-text');
        if (menuText) {
            link.setAttribute('data-tooltip', menuText.textContent.trim());
        }
    });
    
    // Handle scroll to reposition floating submenus in collapsed mode
    let scrollTimer;
    window.addEventListener('scroll', function() {
        if (sidebar && sidebar.classList.contains('collapsed')) {
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                document.querySelectorAll('.has-submenu.open').forEach(item => {
                    const submenu = item.querySelector('.submenu');
                    const rect = item.getBoundingClientRect();
                    if (submenu) {
                        submenu.style.top = rect.top + 'px';
                    }
                });
            }, 50);
        }
    }, { passive: true });
    
    // Smooth scroll to top button (if exists)
    const scrollToTopBtn = document.getElementById('scrollToTop');
    if (scrollToTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollToTopBtn.style.display = 'block';
            } else {
                scrollToTopBtn.style.display = 'none';
            }
        });
        
        scrollToTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
    
    // Close dropdowns on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            if (notificationsMenu) notificationsMenu.classList.remove('show');
            if (userMenu) userMenu.classList.remove('show');
        }
    });
});

// Utility function to show notifications
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 16px 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 10000;
        display: flex;
        align-items: center;
        gap: 10px;
        animation: slideInRight 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        max-width: 400px;
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                document.body.removeChild(notification);
            }
        }, 300);
    }, 3000);
}

// ===== Notification API Functions =====

/**
 * Get the base path for API calls based on current directory
 */
function getApiBasePath() {
    const currentPath = window.location.pathname;
    
    if (currentPath.includes('/admin/')) {
        return 'notifications_api.php';
    } else if (currentPath.includes('/staff/')) {
        return 'notifications_api.php';
    } else if (currentPath.includes('/user/')) {
        return 'notifications_api.php';
    }
    
    // Default fallback
    console.warn('Could not detect role directory, using admin path');
    return 'admin/notifications_api.php';
}

/**
 * Fetch notifications from the server
 */
async function fetchNotifications(limit = 10, unreadOnly = false) {
    const notificationsList = document.querySelector('.notifications-list');
    
    try {
        const apiPath = getApiBasePath();
        const url = `${apiPath}?action=fetch&limit=${limit}&unread_only=${unreadOnly}`;
        
        console.log('Fetching notifications from:', url);
        
        // Add timeout to fetch request (5 seconds)
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);
        
        const response = await fetch(url, { signal: controller.signal });
        clearTimeout(timeoutId);
        
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Response data:', data);
        
        if (data.success) {
            renderNotifications(data.notifications);
            updateBadgeCount(data.unread_count);
        } else {
            console.error('Failed to fetch notifications:', data.message);
            showErrorState(notificationsList, data.message || 'Failed to load notifications');
        }
    } catch (error) {
        console.error('Error fetching notifications:', error);
        
        // Show error state to user
        if (error.name === 'AbortError') {
            showErrorState(notificationsList, 'Request timeout. Database might be slow.');
        } else if (error.message.includes('JSON')) {
            showErrorState(notificationsList, 'Invalid response from server. Check if notifications table exists.');
        } else {
            showErrorState(notificationsList, 'Unable to load notifications. Please check console for details.');
        }
        
        // Set badge to 0 on error
        updateBadgeCount(0);
    }
}

/**
 * Show error state in notifications list
 */
function showErrorState(container, message) {
    if (!container) return;
    
    container.innerHTML = `
        <div class="empty-notifications">
            <i class="fas fa-exclamation-circle" style="font-size: 48px; color: #ef4444; margin-bottom: 12px;"></i>
            <p style="color: #6b7280; font-size: 14px; margin-bottom: 12px;">${message}</p>
            <button onclick="fetchNotifications()" style="padding: 8px 16px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 600;">
                <i class="fas fa-redo"></i> Retry
            </button>
        </div>
    `;
}

/**
 * Render notifications in the dropdown
 */
function renderNotifications(notifications) {
    const notificationsList = document.querySelector('.notifications-list');
    if (!notificationsList) return;
    
    if (notifications.length === 0) {
        notificationsList.innerHTML = `
            <div class="empty-notifications">
                <i class="fas fa-bell-slash" style="font-size: 48px; color: #d1d5db; margin-bottom: 12px;"></i>
                <p style="color: #6b7280; font-size: 14px;">No notifications</p>
            </div>
        `;
        return;
    }
    
    notificationsList.innerHTML = notifications.map(notification => {
        const icon = notification.icon || 'fa-bell';
        const typeClass = notification.type || 'info';
        const unreadClass = notification.is_read == 0 ? 'unread' : '';
        const timeAgo = formatTimeAgo(notification.created_at);
        const exactTime = formatExactTime(notification.created_at);
        const link = notification.link || '#';
        
        return `
            <div class="notification-item ${unreadClass}" data-id="${notification.id}" data-link="${link}">
                <div class="notification-icon ${typeClass}">
                    <i class="fas ${icon}"></i>
                </div>
                <div class="notification-content">
                    <div class="notification-title">${notification.title}</div>
                    <div class="notification-message">${notification.message}</div>
                    <div class="notification-time">
                        <span class="notification-time-relative">${timeAgo}</span>
                        <span class="notification-time-exact">${exactTime}</span>
                    </div>
                </div>
                <button class="notification-delete" onclick="deleteNotification(${notification.id}, event)">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        `;
    }).join('');
    
    // Add click handlers to notification items
    document.querySelectorAll('.notification-item').forEach(item => {
        item.addEventListener('click', function(e) {
            if (!e.target.closest('.notification-delete')) {
                const notificationId = this.getAttribute('data-id');
                const link = this.getAttribute('data-link');
                markAsRead(notificationId, link);
            }
        });
    });
}

/**
 * Mark a single notification as read
 */
async function markAsRead(notificationId, redirectLink = null) {
    try {
        const apiPath = getApiBasePath();
        const formData = new FormData();
        formData.append('action', 'mark_read');
        formData.append('notification_id', notificationId);
        
        const response = await fetch(apiPath, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Refresh notifications
            await fetchNotifications();
            
            // Redirect if link provided
            if (redirectLink && redirectLink !== '#' && redirectLink !== '') {
                window.location.href = redirectLink;
            }
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
    }
}

/**
 * Mark all notifications as read
 */
async function markAllAsRead() {
    try {
        const apiPath = getApiBasePath();
        const formData = new FormData();
        formData.append('action', 'mark_all_read');
        
        const response = await fetch(apiPath, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Silent success: refresh notifications without showing alerts
            await fetchNotifications();
        } else {
            // Log server-side failure silently and refresh list
            console.error('Failed to mark notifications as read:', data.message || data);
            await fetchNotifications();
        }
    } catch (error) {
        console.error('Error marking all as read:', error);
        // Swallow user-facing error alerts for this action
        // developer can see error in console
    }
}

/**
 * Delete all read notifications (only deletes notifications with is_read = 1)
 */
async function deleteAllReadNotifications() {
    try {
        const apiPath = getApiBasePath();
        const formData = new FormData();
        formData.append('action', 'delete_all_read');
        
        const response = await fetch(apiPath, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Silent success: refresh notifications without showing alerts
            await fetchNotifications();
        } else {
            // Log server-side failure silently and refresh list
            console.error('Failed to delete read notifications:', data.message || data);
            await fetchNotifications();
        }
    } catch (error) {
        console.error('Error deleting read notifications:', error);
        // Swallow user-facing error alerts for this action
    }
}

/**
 * Delete a notification
 */
async function deleteNotification(notificationId, event) {
    if (event) {
        event.stopPropagation();
    }
    
    try {
        const apiPath = getApiBasePath();
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('notification_id', notificationId);
        
        const response = await fetch(apiPath, {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Remove notification from DOM with animation
            const notificationItem = document.querySelector(`.notification-item[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.style.animation = 'slideUp 0.3s ease';
                setTimeout(() => {
                    fetchNotifications();
                }, 300);
            }
        } else {
            showNotification('Failed to delete notification', 'error');
        }
    } catch (error) {
        console.error('Error deleting notification:', error);
        showNotification('An error occurred', 'error');
    }
}

/**
 * Update the notification badge count
 */
function updateBadgeCount(count) {
    const notificationsBtn = document.getElementById('notificationsBtn');
    if (!notificationsBtn) return;
    
    let badge = notificationsBtn.querySelector('.badge');
    
    if (count > 0) {
        if (!badge) {
            badge = document.createElement('span');
            badge.className = 'badge';
            notificationsBtn.appendChild(badge);
        }
        badge.textContent = count > 99 ? '99+' : count;
        badge.style.display = 'flex';
    } else {
        if (badge) {
            badge.style.display = 'none';
        }
    }
}

/**
 * Format timestamp to exact time (e.g. "10:30 AM" or "Feb 26, 10:30 AM")
 */
function formatExactTime(timestamp) {
    const now = new Date();
    const notificationTime = new Date(timestamp);

    const timeStr = notificationTime.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true
    });

    // Same day: just show the time
    if (
        notificationTime.getFullYear() === now.getFullYear() &&
        notificationTime.getMonth() === now.getMonth() &&
        notificationTime.getDate() === now.getDate()
    ) {
        return timeStr;
    }

    // Different day: show date + time
    const dateStr = notificationTime.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: notificationTime.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
    return `${dateStr}, ${timeStr}`;
}

/**
 * Format timestamp to relative time
 */
function formatTimeAgo(timestamp) {
    const now = new Date();
    const notificationTime = new Date(timestamp);
    const diffInSeconds = Math.floor((now - notificationTime) / 1000);
    
    if (diffInSeconds < 60) {
        return 'Just now';
    } else if (diffInSeconds < 3600) {
        const minutes = Math.floor(diffInSeconds / 60);
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
    } else if (diffInSeconds < 86400) {
        const hours = Math.floor(diffInSeconds / 3600);
        return `${hours} ${hours === 1 ? 'hour' : 'hours'} ago`;
    } else if (diffInSeconds < 604800) {
        const days = Math.floor(diffInSeconds / 86400);
        return `${days} ${days === 1 ? 'day' : 'days'} ago`;
    } else {
        // Format as date
        const options = { month: 'short', day: 'numeric' };
        if (notificationTime.getFullYear() !== now.getFullYear()) {
            options.year = 'numeric';
        }
        return notificationTime.toLocaleDateString('en-US', options);
    }
}

// Add CSS animations
if (!document.getElementById('dashboardAnimations')) {
    const style = document.createElement('style');
    style.id = 'dashboardAnimations';
    style.textContent = `
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
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(400px);
                opacity: 0;
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideUp {
            from {
                opacity: 1;
                transform: translateY(0);
            }
            to {
                opacity: 0;
                transform: translateY(-20px);
            }
        }
    `;
    document.head.appendChild(style);
}

