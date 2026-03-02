<?php
/**
 * Staff Footer Component
 * Reusable footer for all staff pages
 */
?>

<!-- Logout Confirmation Modal -->
<div id="logoutModal" class="logout-modal hidden">
    <div class="logout-modal-overlay" onclick="closeLogoutModal()"></div>
    <div class="logout-modal-content">
        <div class="logout-modal-header">
            <div class="logout-modal-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2>Confirm Logout</h2>
        </div>
        <div class="logout-modal-body">
            <p>Are you sure you want to logout from your account?</p>
        </div>
        <div class="logout-modal-footer">
            <button onclick="closeLogoutModal()" class="btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </button>
            <a href="../logout.php" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Yes, Logout
            </a>
        </div>
    </div>
</div>

<style>
.logout-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s ease;
}

.logout-modal.show {
    opacity: 1;
    visibility: visible;
}

.logout-modal.hidden {
    display: none;
}

.logout-modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    backdrop-filter: blur(4px);
}

.logout-modal-content {
    position: relative;
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 420px;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideIn 0.3s ease;
    overflow: hidden;
}

@keyframes slideIn {
    from {
        transform: translateY(-20px) scale(0.95);
        opacity: 0;
    }
    to {
        transform: translateY(0) scale(1);
        opacity: 1;
    }
}

.logout-modal-header {
    padding: 2rem;
    text-align: center;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.logout-modal-icon {
    width: 64px;
    height: 64px;
    margin: 0 auto 1rem;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
}

.logout-modal-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
}

.logout-modal-body {
    padding: 2rem;
    text-align: center;
}

.logout-modal-body p {
    margin: 0;
    font-size: 16px;
    color: #475569;
    line-height: 1.6;
}

.logout-modal-footer {
    padding: 1.5rem 2rem;
    background: #f8fafc;
    display: flex;
    gap: 1rem;
    justify-content: flex-end;
}

.btn-cancel,
.btn-logout {
    padding: 0.75rem 1.5rem;
    border-radius: 8px;
    font-weight: 600;
    font-size: 14px;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    border: none;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-cancel {
    background: #e2e8f0;
    color: #475569;
}

.btn-cancel:hover {
    background: #cbd5e1;
}

.btn-logout {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
}

.btn-logout:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
}

@media (max-width: 480px) {
    .logout-modal-content {
        width: 95%;
    }
    
    .logout-modal-footer {
        flex-direction: column;
    }
    
    .btn-cancel,
    .btn-logout {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function openLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.remove('hidden');
    setTimeout(() => modal.classList.add('show'), 10);
    document.body.style.overflow = 'hidden';
}

function closeLogoutModal() {
    const modal = document.getElementById('logoutModal');
    modal.classList.remove('show');
    setTimeout(() => {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }, 300);
}

// Close modal on ESC key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeLogoutModal();
    }
});
</script>

<!-- Main Content End -->
</div>

<script src="../assets/js/dashboard.js"></script>
</body>
</html>
