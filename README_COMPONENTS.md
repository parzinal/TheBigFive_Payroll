# Reusable Sidebar and Header Components

## Overview
This payroll system includes reusable, expandable/collapsible sidebar and header components for all user roles (Admin, Staff, and User/Employee).

## Features
- ✅ Expandable and collapsible sidebar
- ✅ Responsive design (mobile-friendly)
- ✅ Role-based navigation menus
- ✅ Persistent sidebar state (uses localStorage)
- ✅ Submenu support with expand/collapse
- ✅ Active page highlighting
- ✅ Smooth animations and transitions
- ✅ User information display
- ✅ Logout functionality

## File Structure

```
TheBigFive_Payroll/
├── assets/
│   ├── css/
│   │   └── dashboard.css        # Main styles for sidebar and header
│   └── js/
│       └── dashboard.js         # JavaScript for sidebar functionality
│
├── admin/
│   ├── include/
│   │   ├── header.php          # Admin header component
│   │   ├── sidebar.php         # Admin sidebar component
│   │   └── footer.php          # Admin footer component
│   └── dashboard.php           # Example dashboard page
│
├── staff/
│   ├── include/
│   │   ├── header.php          # Staff header component
│   │   ├── sidebar.php         # Staff sidebar component
│   │   └── footer.php          # Staff footer component
│   └── dashboard_staff.php     # Example dashboard page
│
├── user/
│   ├── include/
│   │   ├── header.php          # User header component
│   │   ├── sidebar.php         # User sidebar component
│   │   └── footer.php          # User footer component
│   └── dashboard_user.php      # Example dashboard page
│
└── logout.php                  # Logout handler
```

## Usage

### Creating a New Page

To use the sidebar and header components in any page, follow this pattern:

```php
<?php
// Set page title (optional)
$page_title = 'Your Page Title';

// Include header
require_once 'include/header.php';

// Include sidebar
require_once 'include/sidebar.php';
?>

<!-- Your page content goes here -->
<div class="main-content">
    <div class="card">
        <h2 class="card-header">Page Content</h2>
        <p>Your content here...</p>
    </div>
</div>

<?php
// Include footer
require_once 'include/footer.php';
?>
```

### Session Requirements

The header components check for user authentication. Make sure your login system sets these session variables:

```php
$_SESSION['user_id']   // User ID
$_SESSION['name']      // User's full name
$_SESSION['role']      // User role: 'admin', 'staff', or 'user'
```

## Sidebar Features

### Expand/Collapse
- Click the hamburger menu icon (☰) in the header to toggle the sidebar
- The sidebar state is saved in localStorage and persists across page reloads
- When collapsed, only icons are shown with tooltips on hover

### Submenus
- Menu items with submenus show a chevron arrow (›)
- Click on a menu item to expand/collapse its submenu
- Submenus are automatically hidden when the sidebar is collapsed

### Active Page Highlighting
- The current page is automatically highlighted in the menu
- If the current page is in a submenu, the submenu expands automatically

### Mobile Responsiveness
- On screens smaller than 768px, the sidebar becomes a slide-in menu
- Click the hamburger icon to open the sidebar
- Click outside the sidebar to close it

## Customization

### Adding New Menu Items

Edit the appropriate sidebar file (`admin/include/sidebar.php`, `staff/include/sidebar.php`, or `user/include/sidebar.php`):

```php
<li class="menu-item">
    <a href="your_page.php" class="menu-link">
        <i class="fas fa-icon-name menu-icon"></i>
        <span class="menu-text">Menu Item</span>
    </a>
</li>
```

### Adding Submenus

```php
<li class="menu-item">
    <a href="#" class="menu-link">
        <i class="fas fa-icon-name menu-icon"></i>
        <span class="menu-text">Parent Menu</span>
        <i class="fas fa-chevron-right arrow"></i>
    </a>
    <ul class="submenu">
        <li class="menu-item">
            <a href="submenu_page.php" class="menu-link">
                <i class="fas fa-icon-name menu-icon"></i>
                <span class="menu-text">Submenu Item</span>
            </a>
        </li>
    </ul>
</li>
```

### Changing Colors

Edit `assets/css/dashboard.css` and modify the CSS variables:

```css
:root {
    --sidebar-width: 260px;
    --primary-color: #2c3e50;      /* Sidebar background */
    --secondary-color: #34495e;     /* Hover color */
    --accent-color: #3498db;        /* Active item color */
    --hover-color: #2980b9;         /* Button hover */
    /* ... other variables */
}
```

## Role-Based Navigation

Each role has different menu items tailored to their permissions:

### Admin
- Full system access
- Employee management
- Payroll generation
- Reports and analytics
- System settings

### Staff
- Employee viewing
- Attendance management
- Leave request handling
- Limited payroll access

### User/Employee
- Personal profile
- Payslip viewing
- Attendance records
- Leave requests
- Document access

## Browser Compatibility

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)

## Dependencies

- Font Awesome 6.4.0 (for icons) - loaded from CDN
- No jQuery required - uses vanilla JavaScript

## Notes

- The sidebar state is saved per browser/device using localStorage
- Session validation is performed on every page load
- All file paths are relative to the role directory (admin/, staff/, user/)
- Make sure to set `$page_title` before including the header for custom page titles
