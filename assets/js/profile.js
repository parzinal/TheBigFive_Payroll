/**
 * Profile Page JavaScript
 * Shared by admin/profile.php, staff/profile.php, user/profile.php
 *
 * REQUIRED: Each page must define `originalValues` before loading this script:
 *   <script>
 *     const originalValues = {
 *       full_name: <?php echo json_encode($user['full_name'] ?? ''); ?>,
 *       username:  <?php echo json_encode($user['username'] ?? ''); ?>,
 *       email:     <?php echo json_encode($user['email'] ?? ''); ?>
 *     };
 *   </script>
 */

// ============================================================
// Utility: show/clear field errors
// ============================================================
function showFieldError(inputId, message) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.add('input-error');
    input.classList.remove('input-success');
    let errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.className = 'field-error-msg';
        errEl.innerHTML = '<i class="fas fa-exclamation-circle"></i> <span></span>';
        const wrapper = input.closest('.password-input-wrapper');
        (wrapper || input).insertAdjacentElement('afterend', errEl);
    }
    errEl.querySelector('span').textContent = message;
    errEl.classList.add('show');
}

function clearFieldError(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    const errEl = input.closest('.form-group').querySelector('.field-error-msg');
    if (errEl) errEl.classList.remove('show');
}

function markFieldValid(inputId) {
    const input = document.getElementById(inputId);
    if (!input) return;
    input.classList.remove('input-error');
    input.classList.add('input-success');
    clearFieldError(inputId);
}

// ============================================================
// Profile form: change tracking + validation
// ============================================================
function checkProfileChanges() {
    const fullName = document.getElementById('full_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();

    const changed = (fullName !== originalValues.full_name ||
                     username !== originalValues.username ||
                     email !== originalValues.email);

    const btn = document.getElementById('btnUpdateProfile');
    if (btn) btn.disabled = !changed;
    return changed;
}

// Attach change tracking to profile fields
['full_name', 'username', 'email'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() { checkProfileChanges(); });
        el.addEventListener('change', function() { checkProfileChanges(); });
    }
});

// ============================================================
// Username: enforce lowercase, no spaces, alphanumeric + underscore
// ============================================================
const usernameInput = document.getElementById('username');
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');

        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('username');
        } else if (val.length < 3) {
            showFieldError('username', 'Username must be at least 3 characters');
        } else if (!/^[a-z0-9_]+$/.test(val)) {
            showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed');
        } else {
            markFieldValid('username');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Email: force lowercase, validate format
// ============================================================
const emailInput = document.getElementById('email');
if (emailInput) {
    emailInput.addEventListener('input', function() {
        this.value = this.value.toLowerCase().replace(/\s/g, '');

        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('email');
        } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val)) {
            showFieldError('email', 'Please enter a valid email address');
        } else {
            markFieldValid('email');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Full name: basic validation
// ============================================================
const fullNameInput = document.getElementById('full_name');
if (fullNameInput) {
    fullNameInput.addEventListener('input', function() {
        const val = this.value.trim();
        if (val.length === 0) {
            clearFieldError('full_name');
        } else if (val.length < 2) {
            showFieldError('full_name', 'Full name must be at least 2 characters');
        } else {
            markFieldValid('full_name');
        }
        checkProfileChanges();
    });
}

// ============================================================
// Profile form: submit validation
// ============================================================
document.getElementById('profileForm').addEventListener('submit', function(e) {
    let hasError = false;

    const fullName = document.getElementById('full_name').value.trim();
    const username = document.getElementById('username').value.trim();
    const email = document.getElementById('email').value.trim();

    if (fullName.length < 2) {
        showFieldError('full_name', 'Full name must be at least 2 characters');
        hasError = true;
    }
    if (username.length < 3) {
        showFieldError('username', 'Username must be at least 3 characters');
        hasError = true;
    } else if (!/^[a-z0-9_]+$/.test(username)) {
        showFieldError('username', 'Only lowercase letters, numbers, and underscores allowed');
        hasError = true;
    }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError('email', 'Please enter a valid email address');
        hasError = true;
    }

    if (hasError) {
        e.preventDefault();
        return false;
    }
    // S4: Prevent double-submit
    const btn = this.querySelector('button[type="submit"], .btn-primary');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...'; }
});

// ============================================================
// Password form: change tracking + validation
// ============================================================
function checkPasswordChanges() {
    const current = document.getElementById('current_password').value;
    const newPw = document.getElementById('new_password').value;
    const confirm = document.getElementById('confirm_password').value;

    const hasContent = current.length > 0 || newPw.length > 0 || confirm.length > 0;

    document.getElementById('btnResetPassword').disabled = !hasContent;
    document.getElementById('btnChangePassword').disabled = !(current.length > 0 && newPw.length >= 6 && confirm.length > 0);
    return hasContent;
}

['current_password', 'new_password', 'confirm_password'].forEach(function(id) {
    const el = document.getElementById(id);
    if (el) {
        el.addEventListener('input', function() {
            checkPasswordChanges();
            validatePasswordField(id);
        });
    }
});

function validatePasswordField(fieldId) {
    if (fieldId === 'new_password') {
        const val = document.getElementById('new_password').value;
        if (val.length > 0 && val.length < 6) {
            showFieldError('new_password', 'Password must be at least 6 characters');
        } else if (val.length >= 6) {
            markFieldValid('new_password');
        } else {
            clearFieldError('new_password');
        }
        updatePasswordStrength(val);
    }
    if (fieldId === 'confirm_password') {
        const newPw = document.getElementById('new_password').value;
        const confirm = document.getElementById('confirm_password').value;
        if (confirm.length > 0 && confirm !== newPw) {
            showFieldError('confirm_password', 'Passwords do not match');
        } else if (confirm.length > 0 && confirm === newPw) {
            markFieldValid('confirm_password');
        } else {
            clearFieldError('confirm_password');
        }
    }
}

function updatePasswordStrength(password) {
    const bar = document.querySelector('.password-strength-bar');
    const text = document.querySelector('.password-strength-text');
    if (!bar || !text) return;

    let strength = 0;
    if (password.length >= 6) strength++;
    if (password.length >= 8) strength++;
    if (/[A-Z]/.test(password)) strength++;
    if (/[0-9]/.test(password)) strength++;
    if (/[^a-zA-Z0-9]/.test(password)) strength++;

    const levels = [
        { width: '0%', color: '#E5E7EB', label: '' },
        { width: '20%', color: '#EF4444', label: 'Very Weak' },
        { width: '40%', color: '#F59E0B', label: 'Weak' },
        { width: '60%', color: '#F59E0B', label: 'Fair' },
        { width: '80%', color: '#10B981', label: 'Strong' },
        { width: '100%', color: '#059669', label: 'Very Strong' }
    ];

    const level = levels[Math.min(strength, 5)];
    bar.style.width = level.width;
    bar.style.background = level.color;
    text.textContent = level.label;
    text.style.color = level.color;
}

function resetPasswordForm() {
    document.getElementById('passwordForm').reset();
    ['current_password', 'new_password', 'confirm_password'].forEach(clearFieldError);
    document.getElementById('btnResetPassword').disabled = true;
    document.getElementById('btnChangePassword').disabled = true;
    const bar = document.querySelector('.password-strength-bar');
    const text = document.querySelector('.password-strength-text');
    if (bar) { bar.style.width = '0%'; }
    if (text) { text.textContent = ''; }
}

// Password match validation on submit
document.getElementById('passwordForm').addEventListener('submit', function(e) {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = document.getElementById('confirm_password').value;
    let hasError = false;

    if (newPassword.length < 6) {
        showFieldError('new_password', 'Password must be at least 6 characters');
        hasError = true;
    }
    if (newPassword !== confirmPassword) {
        showFieldError('confirm_password', 'Passwords do not match');
        hasError = true;
    }

    if (hasError) {
        e.preventDefault();
        return false;
    }
    // S4: Prevent double-submit
    const btn = this.querySelector('button[type="submit"], #btnChangePassword');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing...'; }
});

// Toggle password visibility
function togglePassword(fieldId) {
    const field = document.getElementById(fieldId);
    const button = field.nextElementSibling;
    const icon = button.querySelector('i');

    if (field.type === 'password') {
        field.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        field.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

// O3: Auto-dismiss only success alerts after 5 seconds; keep errors visible
setTimeout(function() {
    const alerts = document.querySelectorAll('.alert-success');
    alerts.forEach(function(alert) {
        alert.style.transition = 'opacity 0.5s ease';
        alert.style.opacity = '0';
        setTimeout(function() {
            alert.remove();
        }, 500);
    });
}, 5000);
