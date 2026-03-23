/* ============================================================
 * Account Logs Scripts
 * Shared by admin/account_logs.php, staff/account_logs.php, user/account_logs.php
 * The user_id filter is only present on admin page; guarded with if-check.
 * ============================================================ */

(function() {
    const applyFilterBtn = document.getElementById('applyFilterBtn');
    const resetFilterBtn = document.getElementById('resetFilterBtn');
    const userSelect = document.getElementById('user_id');
    const dateFrom = document.getElementById('date_from');
    const dateTo = document.getElementById('date_to');

    function checkFilters() {
        const hasFilters = (userSelect && userSelect.value !== '') || 
                          (dateFrom && dateFrom.value !== '') || 
                          (dateTo && dateTo.value !== '');
        if (applyFilterBtn) {
            applyFilterBtn.disabled = !hasFilters;
        }
        if (resetFilterBtn) {
            resetFilterBtn.style.display = hasFilters ? 'inline-flex' : 'none';
        }
    }

    if (userSelect) {
        userSelect.addEventListener('change', checkFilters);
    }
    if (dateFrom) {
        dateFrom.addEventListener('change', checkFilters);
    }
    if (dateTo) {
        dateTo.addEventListener('change', checkFilters);
    }

    checkFilters();
})();
