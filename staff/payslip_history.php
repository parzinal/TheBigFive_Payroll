<?php
header('Location: payslip_history_v2.php');
exit;

/**
 * Staff Payslip History Page
 * View employee payslip history
 */

$page_title = 'Payslip History';
require_once 'include/header.php';
require_once '../config/database.php';

$pdo = getDBConnection();

// Get employees with payslip count
try {
    $stmt = $pdo->query("
        SELECT 
            e.id,
            e.employee_code,
            e.full_name,
            e.position,
            e.department,
            e.status,
            COUNT(pc.id) as payslip_count,
            MAX(pc.created_at) as last_payslip_date,
            SUM(pc.net_pay) as total_net_pay
        FROM employees e
        LEFT JOIN payroll_computations pc ON e.id = pc.employee_id 
            AND pc.status IN ('computed', 'approved', 'paid')
        WHERE e.status IN ('active', 'inactive')
        GROUP BY e.id, e.employee_code, e.full_name, e.position, e.department, e.status
        ORDER BY e.full_name ASC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total stats
    $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT employee_id) as total_employees_with_payslips,
            COUNT(*) as total_payslips,
            SUM(net_pay) as total_amount_paid
        FROM payroll_computations
        WHERE status IN ('computed', 'approved', 'paid')
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// O1: Removed duplicate require_once 'include/header.php' that was here
require_once 'include/sidebar.php';
?>

<div class="main-content">
    <!-- Modern Page Header -->
    <div class="page-header-modern">
        <div class="header-left">
            <div class="page-icon-modern">
                <i class="fas fa-receipt"></i>
            </div>
            <div>
                <h1 class="page-title-modern">Payslip History</h1>
                <p class="page-subtitle-modern">View and print employee payslip records</p>
            </div>
        </div>
        <div class="header-right">
            <div class="search-filter-bar">
                <div class="search-box-modern">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchEmployee" placeholder="Search employee..." class="search-input-modern">
                </div>
                <select id="filterStatus" class="filter-select-modern">
                    <option value="all">All Employees</option>
                    <option value="with-payslips">With Payslips</option>
                    <option value="no-payslips">No Payslips</option>
                </select>
                <button id="sortToggle" class="sort-toggle-btn" onclick="toggleSort()" title="Toggle sort order">
                    <i class="fas fa-sort-alpha-down"></i>
                    <span id="sortLabel">A-Z</span>
                </button>
            </div>
        </div>
    </div>

    <div class="content-wrapper">
        <!-- Stats Row -->
        <div class="stats-grid-modern">
            <div class="stat-card-modern stat-primary">
                <div class="stat-icon-modern">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value-modern"><?php echo number_format($stats['total_employees_with_payslips'] ?? 0); ?></div>
                    <div class="stat-label-modern">Employees with Payslips</div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> Active
                    </div>
                </div>
            </div>

            <div class="stat-card-modern stat-success">
                <div class="stat-icon-modern">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value-modern"><?php echo number_format($stats['total_payslips'] ?? 0); ?></div>
                    <div class="stat-label-modern">Total Payslips Generated</div>
                    <div class="stat-trend">
                        <i class="fas fa-check-circle"></i> All Time
                    </div>
                </div>
            </div>

            <div class="stat-card-modern stat-info">
                <div class="stat-icon-modern">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-content">
                    <div class="stat-value-modern">₱<?php echo number_format($stats['total_amount_paid'] ?? 0, 2); ?></div>
                    <div class="stat-label-modern">Total Amount Paid</div>
                    <div class="stat-trend">
                        <i class="fas fa-wallet"></i> Disbursed
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee Cards -->
        <div class="section-card-modern">
            <div class="section-header-modern">
                <div>
                    <h2 class="section-title-modern">
                        <i class="fas fa-users"></i>
                        Employee Payslips
                    </h2>
                    <p class="section-subtitle-modern">Click on an employee card to view all payslip records</p>
                </div>
                <div class="section-actions">
                    <span class="employee-count-badge" id="employeeCount"><?php echo count($employees); ?> Employees</span>
                </div>
            </div>
            
            <?php if (count($employees) > 0): ?>
                <div class="employee-payslip-cards-modern" id="employeeCardsContainer">
                    <?php foreach ($employees as $employee): ?>
                        <div class="employee-payslip-card-modern" 
                             data-employee-name="<?php echo htmlspecialchars(strtolower($employee['full_name']), ENT_QUOTES, 'UTF-8'); ?>"
                             data-has-payslips="<?php echo $employee['payslip_count'] > 0 ? 'yes' : 'no'; ?>">
                            <div class="card-header-modern">
                                <div class="employee-avatar-modern <?php echo $employee['payslip_count'] > 0 ? 'has-payslips' : 'no-payslips'; ?>">
                                    <?php
                                    $initials = '';
                                    $nameParts = explode(' ', $employee['full_name']);
                                    foreach ($nameParts as $part) {
                                        $initials .= strtoupper(substr($part, 0, 1));
                                        if (strlen($initials) >= 2) break;
                                    }
                                    echo htmlspecialchars($initials);
                                    ?>
                                </div>
                                <div class="employee-info-modern">
                                    <h3 class="employee-name-modern"><?php echo htmlspecialchars($employee['full_name']); ?></h3>
                                    <p class="employee-code-modern">
                                        <i class="fas fa-id-card"></i>
                                        <?php echo htmlspecialchars($employee['employee_code']); ?>
                                    </p>
                                    <div class="employee-meta-modern">
                                        <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($employee['position'] ?? 'N/A'); ?></span>
                                        <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($employee['department'] ?? 'N/A'); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-body-modern">
                                <div class="payslip-stats-grid">
                                    <div class="stat-box">
                                        <div class="stat-box-icon">
                                            <i class="fas fa-file-alt"></i>
                                        </div>
                                        <div>
                                            <div class="stat-box-value"><?php echo number_format($employee['payslip_count']); ?></div>
                                            <div class="stat-box-label">Payslips</div>
                                        </div>
                                    </div>
                                    
                                    <?php if ($employee['last_payslip_date']): ?>
                                    <div class="stat-box">
                                        <div class="stat-box-icon">
                                            <i class="fas fa-calendar-check"></i>
                                        </div>
                                        <div>
                                            <div class="stat-box-value"><?php echo date('M d, Y', strtotime($employee['last_payslip_date'])); ?></div>
                                            <div class="stat-box-label">Last Generated</div>
                                        </div>
                                    </div>
                                    
                                    <div class="stat-box stat-box-highlight">
                                        <div class="stat-box-icon">
                                            <i class="fas fa-peso-sign"></i>
                                        </div>
                                        <div>
                                            <div class="stat-box-value">₱<?php echo number_format($employee['total_net_pay'], 2); ?></div>
                                            <div class="stat-box-label">Total Paid</div>
                                        </div>
                                    </div>
                                    <?php else: ?>
                                    <div class="no-data-badge">
                                        <i class="fas fa-info-circle"></i>
                                        No payslip records available
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer-modern">
                                <?php if ($employee['payslip_count'] > 0): ?>
                                    <button class="btn-modern btn-modern-primary" 
                                            onclick="viewEmployeePayslips(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name'], ENT_QUOTES); ?>')">
                                        <i class="fas fa-eye"></i>
                                        <span>View All Payslips</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                <?php else: ?>
                                    <button class="btn-modern btn-modern-disabled" disabled>
                                        <i class="fas fa-inbox"></i>
                                        <span>No Payslips Yet</span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data-modern">
                    <div class="no-data-icon">
                        <i class="fas fa-inbox"></i>
                    </div>
                    <h3>No Payslips Found</h3>
                    <p>No payslips have been generated yet for any employees.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Payslips List Modal -->
<div id="payslipsListModal" class="modal-payslips">
    <div class="modal-payslips-content">
        <div class="modal-payslips-header">
            <h2 id="modalEmployeeName">Employee Payslips</h2>
            <button class="modal-close" onclick="closePayslipsListModal()">&times;</button>
        </div>
        <div class="modal-payslips-body" id="payslipsListContainer">
            <div class="loading-spinner">
                <i class="fas fa-spinner fa-spin"></i> Loading payslips...
            </div>
        </div>
    </div>
</div>

<!-- Single Payslip Receipt Modal -->
<div id="payslipReceiptModal" class="modal-receipt">
    <div class="modal-receipt-overlay" onclick="closePayslipReceiptModal()"></div>
    <div class="modal-receipt-content">
        <button class="receipt-close-btn" onclick="closePayslipReceiptModal()">
            <i class="fas fa-times"></i>
        </button>
        <button class="receipt-print-btn" onclick="printPayslip()">
            <i class="fas fa-print"></i> Print
        </button>
        <div id="payslipReceiptContainer"></div>
    </div>
</div>

<style>
/* Modern Page Header */
.page-header-modern {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    padding: 40px;
    border-radius: 20px;
    margin-bottom: 30px;
    box-shadow: 0 10px 40px rgba(37, 99, 235, 0.3);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    position: relative;
    overflow: hidden;
    animation: slideInDown 0.5s ease-out;
}

.page-header-modern::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
    animation: rotate 20s linear infinite;
}

@keyframes slideInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes rotate {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.header-left {
    display: flex;
    align-items: center;
    gap: 20px;
    color: white;
}

.page-icon-modern {
    width: 70px;
    height: 70px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    backdrop-filter: blur(10px);
    animation: bounce 2s ease-in-out infinite;
    position: relative;
    z-index: 1;
}

@keyframes bounce {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-10px);
    }
}

.page-title-modern {
    font-size: 32px;
    font-weight: 700;
    margin: 0;
    color: white;
}

.page-subtitle-modern {
    font-size: 15px;
    margin: 5px 0 0 0;
    color: rgba(255, 255, 255, 0.9);
}

.header-right {
    display: flex;
    gap: 15px;
}

.search-filter-bar {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.search-box-modern {
    position: relative;
    display: flex;
    align-items: center;
}

.search-box-modern i {
    position: absolute;
    left: 15px;
    color: #64748b;
    font-size: 16px;
}

.search-input-modern {
    padding: 12px 15px 12px 45px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
    min-width: 280px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.search-input-modern:focus {
    outline: none;
    border-color: white;
    background: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.search-input-modern::placeholder {
    color: #94a3b8;
}

.filter-select-modern {
    padding: 12px 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.filter-select-modern:focus {
    outline: none;
    border-color: white;
    background: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.filter-select-modern:hover {
    border-color: rgba(255, 255, 255, 0.6);
}

.sort-toggle-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 20px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 12px;
    font-size: 14px;
    background: rgba(255, 255, 255, 0.95);
    color: #1e293b;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.sort-toggle-btn:hover {
    border-color: white;
    background: white;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
    transform: translateY(-2px);
}

.sort-toggle-btn i {
    font-size: 16px;
    transition: transform 0.3s ease;
}

.sort-toggle-btn.desc i {
    transform: rotate(180deg);
}

.sort-toggle-btn #sortLabel {
    font-size: 13px;
    letter-spacing: 0.5px;
}

/* Modern Stats Grid */
.stats-grid-modern {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card-modern {
    background: white;
    border-radius: 16px;
    padding: 25px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    animation: fadeInUp 0.6s ease-out backwards;
}

.stat-card-modern:nth-child(1) { animation-delay: 0.1s; }
.stat-card-modern:nth-child(2) { animation-delay: 0.2s; }
.stat-card-modern:nth-child(3) { animation-delay: 0.3s; }

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.stat-card-modern::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, var(--stat-color-1), var(--stat-color-2));
}

.stat-card-modern.stat-primary {
    --stat-color-1: #2563EB;
    --stat-color-2: #1d4ed8;
}

.stat-card-modern.stat-success {
    --stat-color-1: #10b981;
    --stat-color-2: #059669;
}

.stat-card-modern.stat-info {
    --stat-color-1: #06b6d4;
    --stat-color-2: #0891b2;
}

.stat-card-modern:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.stat-icon-modern {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    background: linear-gradient(135deg, var(--stat-color-1), var(--stat-color-2));
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.stat-card-modern:hover .stat-icon-modern {
    transform: scale(1.1) rotate(-5deg);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.stat-icon-modern i {
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.05);
    }
}

.stat-content {
    flex: 1;
}

.stat-value-modern {
    font-size: 32px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 8px;
    background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.stat-label-modern {
    font-size: 14px;
    color: #64748b;
    font-weight: 500;
    margin-bottom: 8px;
}

.stat-trend {
    font-size: 13px;
    color: #10b981;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 5px;
    animation: fadeIn 0.8s ease-out;
}

.stat-trend i {
    animation: pulse 2s ease-in-out infinite;
}

/* Modern Section Card */
.section-card-modern {
    background: white;
    border-radius: 20px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    animation: fadeInUp 0.7s ease-out backwards;
    animation-delay: 0.4s;
    transition: box-shadow 0.3s ease;
}

.section-card-modern:hover {
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

.section-header-modern {
    padding: 30px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
}

.section-title-modern {
    font-size: 22px;
    font-weight: 700;
    color: #1e293b;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 12px;
}

.section-subtitle-modern {
    font-size: 14px;
    color: #64748b;
    margin: 8px 0 0 0;
}

.section-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.employee-count-badge {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    padding: 10px 20px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    animation: fadeIn 0.5s ease-out;
}

.employee-count-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(37, 99, 235, 0.4);
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

/* Modern Employee Cards */
.employee-payslip-cards-modern {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
    gap: 25px;
    padding: 30px;
}

.employee-payslip-card-modern {
    background: white;
    border: 2px solid #e2e8f0;
    border-radius: 16px;
    transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    display: flex;
    flex-direction: column;
    animation: fadeInUp 0.6s ease-out backwards;
    position: relative;
}

.employee-payslip-card-modern::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 4px;
    background: linear-gradient(90deg, #2563EB, #1d4ed8);
    transform: scaleX(0);
    transform-origin: left;
    transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.employee-payslip-card-modern:hover::after {
    transform: scaleX(1);
}

.employee-payslip-card-modern:hover {
    border-color: #2563EB;
    transform: translateY(-8px);
    box-shadow: 0 20px 40px rgba(37, 99, 235, 0.2);
}

.card-header-modern {
    padding: 25px;
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    display: flex;
    align-items: center;
    gap: 20px;
}

.employee-avatar-modern {
    width: 70px;
    height: 70px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    font-weight: 700;
    color: white;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

.employee-avatar-modern:hover {
    transform: scale(1.1) rotate(5deg);
}

.employee-avatar-modern.has-payslips {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    position: relative;
    overflow: hidden;
}

.employee-avatar-modern.has-payslips::after {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
        45deg,
        transparent,
        rgba(255, 255, 255, 0.1),
        transparent
    );
    animation: shimmer 3s infinite;
}

@keyframes shimmer {
    0% {
        transform: translateX(-100%) translateY(-100%) rotate(45deg);
    }
    100% {
        transform: translateX(100%) translateY(100%) rotate(45deg);
    }
}

.employee-avatar-modern.no-payslips {
    background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
}

.employee-info-modern {
    flex: 1;
}

.employee-name-modern {
    font-size: 18px;
    font-weight: 700;
    color: #1e293b;
    margin: 0 0 8px 0;
}

.employee-code-modern {
    font-size: 13px;
    color: #64748b;
    margin: 0 0 10px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.employee-meta-modern {
    display: flex;
    flex-direction: column;
    gap: 5px;
    font-size: 12px;
    color: #94a3b8;
}

.employee-meta-modern span {
    display: flex;
    align-items: center;
    gap: 6px;
}

.card-body-modern {
    padding: 25px;
}

.payslip-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 15px;
}

.stat-box {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 15px;
    display: flex;
    align-items: center;
    gap: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.stat-box::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(37, 99, 235, 0.1), transparent);
    transition: left 0.5s ease;
}

.stat-box:hover::before {
    left: 100%;
}

.stat-box:hover {
    background: white;
    border-color: #2563EB;
    transform: translateX(5px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.15);
}

.stat-box-highlight {
    background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
    border-color: #10b981;
}

.stat-box-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    color: #2563EB;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.stat-box-highlight .stat-box-icon {
    color: #10b981;
}

.stat-box-value {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.2;
}

.stat-box-label {
    font-size: 11px;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.no-data-badge {
    grid-column: 1 / -1;
    text-align: center;
    padding: 20px;
    color: #94a3b8;
    font-size: 13px;
    font-style: italic;
}

.card-footer-modern {
    padding: 0 25px 25px 25px;
}

.btn-modern {
    width: 100%;
    padding: 14px 24px;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
}

.btn-modern::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 50%;
    width: 0;
    height: 0;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.2);
    transform: translate(-50%, -50%);
    transition: width 0.6s, height 0.6s;
}

.btn-modern:hover::before {
    width: 300px;
    height: 300px;
}

.btn-modern-primary {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-modern-primary:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.4);
}

.btn-modern-primary:active {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

.btn-modern-primary i.fa-arrow-right {
    transition: transform 0.3s ease;
}

.btn-modern-primary:hover i.fa-arrow-right {
    transform: translateX(5px);
}

.btn-modern-disabled {
    background: #f1f5f9;
    color: #94a3b8;
    cursor: not-allowed;
}

.no-data-modern {
    text-align: center;
    padding: 80px 40px;
    animation: fadeInUp 0.6s ease-out;
}

.no-data-icon {
    font-size: 80px;
    color: #cbd5e1;
    margin-bottom: 20px;
    animation: float 3s ease-in-out infinite;
}

@keyframes float {
    0%, 100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-15px);
    }
}

.no-data-modern h3 {
    color: #475569;
    margin-bottom: 10px;
    font-size: 24px;
}

.no-data-modern p {
    color: #94a3b8;
    font-size: 16px;
}

/* Hidden class for filtering */
.hidden {
    display: none !important;
}

/* Stats Row */
.stats-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.stat-icon.bg-primary { background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%); }
.stat-icon.bg-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.stat-icon.bg-info { background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%); }

.stat-info {
    display: flex;
    flex-direction: column;
}

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
    font-weight: 500;
}

/* Employee Payslip Cards */
.employee-payslip-cards {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
}

.employee-payslip-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
    transition: all 0.3s ease;
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.employee-payslip-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.employee-avatar {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 700;
    margin: 0 auto;
}

.employee-info {
    text-align: center;
}

.employee-name {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 5px;
}

.employee-code {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 5px;
}

.employee-meta {
    font-size: 12px;
    color: #94a3b8;
}

.employee-stats {
    border-top: 1px solid #e2e8f0;
    border-bottom: 1px solid #e2e8f0;
    padding: 15px 0;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.stat-item {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
}

.stat-item .stat-label {
    color: #64748b;
}

.stat-item .stat-value {
    color: #1e293b;
    font-weight: 600;
}

.btn-view-payslips {
    width: 100%;
}

.no-payslips-message {
    text-align: center;
    padding: 60px 20px;
    color: #64748b;
}

.no-payslips-message i {
    font-size: 64px;
    color: #cbd5e1;
    margin-bottom: 20px;
}

.no-payslips-message h3 {
    color: #475569;
    margin-bottom: 10px;
}

/* Modals */
.modal-payslips,
.modal-receipt {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(4px);
}

.modal-payslips.active,
.modal-receipt.active {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-payslips-content {
    background: white;
    border-radius: 16px;
    width: 90%;
    max-width: 800px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-payslips-header {
    padding: 25px 30px;
    border-bottom: 2px solid #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    border-radius: 16px 16px 0 0;
    color: white;
}

.modal-payslips-header h2 {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
}

.modal-close {
    background: transparent;
    border: none;
    font-size: 32px;
    color: white;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    border-radius: 8px;
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
}

.modal-payslips-body {
    padding: 30px;
    overflow-y: auto;
    max-height: calc(90vh - 100px);
}

.loading-spinner {
    text-align: center;
    padding: 40px;
    color: #64748b;
    animation: fadeIn 0.3s ease-out;
}

.loading-spinner i {
    font-size: 32px;
    margin-bottom: 15px;
    display: inline-block;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

/* Payslip List */
.payslip-list-grid {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.payslip-list-item {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: all 0.3s ease;
}

.payslip-list-item:hover {
    background: white;
    border-color: #2563EB;
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
}

.payslip-list-info {
    flex: 1;
}

.payslip-list-period {
    font-size: 18px;
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 10px;
}

.payslip-list-meta {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
}

.payslip-list-meta-item {
    font-size: 13px;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 5px;
}

.payslip-list-meta-item i {
    color: #94a3b8;
}

.payslip-list-meta-item strong {
    color: #1e293b;
}

.btn-view-payslip {
    background: linear-gradient(135deg, #2563EB 0%, #1d4ed8 100%);
    color: white;
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
}

.btn-view-payslip:hover {
    background: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(37, 99, 235, 0.3);
}

/* Receipt Modal */
.modal-receipt-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.7);
}

.modal-receipt-content {
    position: relative;
    background: white;
    border-radius: 16px;
    max-width: 480px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
    z-index: 10001;
}

.receipt-close-btn,
.receipt-print-btn {
    position: sticky;
    top: 10px;
    right: 10px;
    background: rgba(15, 23, 42, 0.9);
    color: white;
    border: none;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: all 0.3s ease;
    margin-left: auto;
    margin-right: 10px;
    margin-bottom: -30px;
    font-size: 16px;
}

.receipt-print-btn {
    width: auto;
    padding: 10px 18px;
    border-radius: 20px;
    gap: 8px;
    margin-right: 60px;
}

.receipt-close-btn:hover,
.receipt-print-btn:hover {
    background: #1e293b;
    transform: scale(1.05);
}

/* Receipt Styling */
.receipt-payslip {
    font-family: 'Courier New', monospace;
    padding: 30px;
    background: white;
}

.receipt-header {
    text-align: center;
    border-bottom: 2px dashed #cbd5e1;
    padding-bottom: 20px;
    margin-bottom: 20px;
}

.receipt-company {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
    color: #1e293b;
}

.receipt-title {
    font-size: 14px;
    color: #64748b;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.receipt-section {
    margin-bottom: 20px;
}

.receipt-label {
    font-size: 11px;
    text-transform: uppercase;
    color: #94a3b8;
    font-weight: 700;
    letter-spacing: 1px;
    margin-bottom: 10px;
}

.receipt-info {
    background: #f8fafc;
    padding: 12px;
    border-radius: 6px;
}

.receipt-info-row {
    display: flex;
    justify-content: space-between;
    padding: 5px 0;
    font-size: 13px;
}

.receipt-info-label {
    color: #64748b;
}

.receipt-info-value {
    color: #1e293b;
    font-weight: 600;
}

.receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 13px;
}

.receipt-row-label {
    color: #64748b;
}

.receipt-row-value {
    color: #1e293b;
    font-weight: 600;
}

.receipt-divider {
    border: none;
    border-top: 1px dashed #cbd5e1;
    margin: 10px 0;
}

.receipt-total {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
    padding: 20px;
    border-radius: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
}

.receipt-total-label {
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 1px;
}

.receipt-total-value {
    font-size: 28px;
    font-weight: 700;
}

.receipt-footer {
    text-align: center;
    padding-top: 20px;
    border-top: 2px dashed #cbd5e1;
    margin-top: 20px;
}

.receipt-footer-text {
    font-size: 10px;
    color: #94a3b8;
    margin: 3px 0;
    line-height: 1.5;
}

.receipt-barcode {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px dashed #e2e8f0;
}

.receipt-barcode-lines {
    display: flex;
    justify-content: center;
    gap: 2px;
    margin-bottom: 8px;
}

.barcode-line {
    width: 2px;
    height: 40px;
    background: #1e293b;
}

.barcode-line:nth-child(2n) {
    width: 1px;
}

.barcode-line:nth-child(3n) {
    width: 3px;
}

.receipt-barcode-text {
    text-align: center;
    font-size: 10px;
    color: #94a3b8;
    letter-spacing: 2px;
}

/* Print Styles */
@media print {
    body * {
        visibility: hidden;
    }
    
    .receipt-payslip,
    .receipt-payslip * {
        visibility: visible;
    }
    
    .receipt-payslip {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        padding: 20px;
    }
    
    .receipt-close-btn,
    .receipt-print-btn {
        display: none !important;
    }
}

/* Responsive */
@media (max-width: 768px) {
    .page-header-modern {
        padding: 25px;
        border-radius: 16px;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-left {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .page-icon-modern {
        width: 60px;
        height: 60px;
        font-size: 28px;
    }
    
    .page-title-modern {
        font-size: 26px;
    }
    
    .search-filter-bar {
        width: 100%;
    }
    
    .search-input-modern {
        min-width: 100%;
    }
    
    .filter-select-modern {
        width: 100%;
    }
    
    .sort-toggle-btn {
        width: 100%;
        justify-content: center;
    }
    
    .stats-grid-modern {
        grid-template-columns: 1fr;
        gap: 15px;
    }
    
    .stat-card-modern {
        padding: 20px;
    }
    
    .stat-icon-modern {
        width: 60px;
        height: 60px;
        font-size: 24px;
    }
    
    .stat-value-modern {
        font-size: 26px;
    }
    
    .section-header-modern {
        padding: 20px;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .employee-payslip-cards-modern {
        grid-template-columns: 1fr;
        padding: 20px;
        gap: 20px;
    }
    
    .card-header-modern {
        padding: 20px;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    
    .employee-meta-modern {
        align-items: center;
    }
    
    .payslip-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .employee-payslip-cards {
        grid-template-columns: 1fr;
    }
    
    .stats-row {
        grid-template-columns: 1fr;
    }
    
    .modal-payslips-content {
        margin: 0;
        border-radius: 0;
        min-height: 100vh;
    }
    
    .modal-payslips-header {
        border-radius: 0;
    }
    
    .modal-receipt-content {
        max-width: 100%;
        border-radius: 0;
        max-height: 100vh;
    }
    
    .receipt-payslip {
        padding: 20px 15px;
    }
    
    .payslip-list-item {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .btn-view-payslip {
        width: 100%;
        justify-content: center;
    }
    
    .payslip-list-meta {
        flex-direction: column;
        gap: 8px;
    }
    
    .receipt-print-btn {
        margin-right: 10px;
    }
}
</style>

<script>
// Sort order state
let currentSortOrder = 'asc'; // Default is A-Z

// Toggle sort order
function toggleSort() {
    const container = document.getElementById('employeeCardsContainer');
    const cards = Array.from(document.querySelectorAll('.employee-payslip-card-modern'));
    const sortBtn = document.getElementById('sortToggle');
    const sortLabel = document.getElementById('sortLabel');
    const sortIcon = sortBtn.querySelector('i');
    
    // Toggle sort order
    currentSortOrder = currentSortOrder === 'asc' ? 'desc' : 'asc';
    
    // Sort cards
    cards.sort((a, b) => {
        const nameA = a.getAttribute('data-employee-name');
        const nameB = b.getAttribute('data-employee-name');
        
        if (currentSortOrder === 'asc') {
            return nameA.localeCompare(nameB);
        } else {
            return nameB.localeCompare(nameA);
        }
    });
    
    // Update UI
    if (currentSortOrder === 'desc') {
        sortBtn.classList.add('desc');
        sortIcon.className = 'fas fa-sort-alpha-up';
        sortLabel.textContent = 'Z-A';
    } else {
        sortBtn.classList.remove('desc');
        sortIcon.className = 'fas fa-sort-alpha-down';
        sortLabel.textContent = 'A-Z';
    }
    
    // Re-append cards in new order with animation
    cards.forEach((card, index) => {
        card.style.animation = 'none';
        setTimeout(() => {
            card.style.animationDelay = `${0.1 + (index * 0.03)}s`;
            card.style.animation = 'fadeInUp 0.4s ease-out backwards';
        }, 10);
        container.appendChild(card);
    });
}

// Show list of payslips for employee
function viewEmployeePayslips(employeeId, employeeName) {
    const modal = document.getElementById('payslipsListModal');
    const modalTitle = document.getElementById('modalEmployeeName');
    const container = document.getElementById('payslipsListContainer');
    
    modalTitle.textContent = employeeName + ' - Payslips';
    modal.classList.add('active');
    
    // Show loading
    container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading payslips...</div>';
    
    // Fetch payslips
    fetch(`../admin/get_employee_payslips.php?employee_id=${employeeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.payslips.length > 0) {
                let html = '<div class="payslip-list-grid">';
                
                data.payslips.forEach((payslip, index) => {
                    html += `
                        <div class="payslip-list-item">
                            <div class="payslip-list-info">
                                <h4 class="payslip-list-period">${payslip.period_name}</h4>
                                <div class="payslip-list-meta">
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-calendar"></i>
                                        Generated: ${formatDate(payslip.created_at)}
                                    </div>
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-money-bill-wave"></i>
                                        Net Pay: <strong>₱${parseFloat(payslip.net_pay || 0).toFixed(2)}</strong>
                                    </div>
                                    <div class="payslip-list-meta-item">
                                        <i class="fas fa-info-circle"></i>
                                        Status: <strong>${payslip.status.toUpperCase()}</strong>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-view-payslip" onclick='viewPayslipReceipt(${JSON.stringify(payslip).replace(/'/g, "&apos;")})'>
                                <i class="fas fa-receipt"></i> View Payslip
                            </button>
                        </div>
                    `;
                });
                
                html += '</div>';
                container.innerHTML = html;
            } else {
                container.innerHTML = `
                    <div class="loading-spinner">
                        <i class="fas fa-inbox"></i>
                        <p>No payslips found for this employee.</p>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            container.innerHTML = `
                <div class="loading-spinner">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p>Error loading payslips. Please try again.</p>
                </div>
            `;
        });
}

// Show single payslip in receipt format
function viewPayslipReceipt(payslip) {
    const modal = document.getElementById('payslipReceiptModal');
    const container = document.getElementById('payslipReceiptContainer');
    
    container.innerHTML = generateReceiptHTML(payslip);
    modal.classList.add('active');
}

// Close payslips list modal
function closePayslipsListModal() {
    const modal = document.getElementById('payslipsListModal');
    modal.classList.remove('active');
}

// Close payslip receipt modal
function closePayslipReceiptModal() {
    const modal = document.getElementById('payslipReceiptModal');
    modal.classList.remove('active');
}

// Print payslip
function printPayslip() {
    window.print();
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
}

function formatShortDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

// Generate receipt-style HTML
function generateReceiptHTML(payslip) {
    const receiptId = `PSL-${payslip.id}-${new Date(payslip.created_at).getTime()}`;
    
    return `
        <div class="receipt-payslip">
            <!-- Header -->
            <div class="receipt-header">
                <h1 class="receipt-company">TheBigFive</h1>
                <p class="receipt-title">Payroll Receipt</p>
            </div>
            
            <!-- Employee Info -->
            <div class="receipt-section">
                <div class="receipt-label">Employee Information</div>
                <div class="receipt-info">
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Name:</span>
                        <span class="receipt-info-value">${payslip.employee_name}</span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">ID:</span>
                        <span class="receipt-info-value">${payslip.employee_code}</span>
                    </div>
                    <div class="receipt-info-row">
                        <span class="receipt-info-label">Position:</span>
                        <span class="receipt-info-value">${payslip.position || 'N/A'}</span>
                    </div>
                </div>
            </div>
            
            <!-- Pay Period -->
            <div class="receipt-section">
                <div class="receipt-label">Pay Period</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Period:</span>
                    <span class="receipt-row-value">${payslip.period_name}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">From - To:</span>
                    <span class="receipt-row-value">${formatShortDate(payslip.start_date)} - ${formatShortDate(payslip.end_date)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Pay Date:</span>
                    <span class="receipt-row-value">${formatShortDate(payslip.pay_date)}</span>
                </div>
            </div>
            
            <!-- Work Summary -->
            <div class="receipt-section">
                <div class="receipt-label">Work Summary</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Days Worked:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_work_days || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Hours Worked:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_work_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Overtime Hours:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_ot_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Late Hours:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_late_hours || 0).toFixed(2)} hrs</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Absences:</span>
                    <span class="receipt-row-value">${parseFloat(payslip.total_absent_days || 0).toFixed(2)} days</span>
                </div>
            </div>
            
            <!-- Earnings -->
            <div class="receipt-section">
                <div class="receipt-label">Earnings</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Basic Pay:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.basic_pay || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Overtime Pay:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.ot_pay || 0).toFixed(2)}</span>
                </div>
                <hr class="receipt-divider">
                <div class="receipt-row" style="font-weight: 700;">
                    <span class="receipt-row-label">TOTAL EARNINGS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.total_earnings || 0).toFixed(2)}</span>
                </div>
            </div>
            
            <!-- Deductions -->
            <div class="receipt-section">
                <div class="receipt-label">Deductions</div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Late Deduction:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.late_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Undertime:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.undertime_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Absences:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.absent_deduction || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">SSS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.sss_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">PhilHealth:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.philhealth_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Pag-IBIG:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.pagibig_contribution || 0).toFixed(2)}</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-row-label">Withholding Tax:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.withholding_tax || 0).toFixed(2)}</span>
                </div>
                ${parseFloat(payslip.cash_advance || 0) > 0 ? `
                <div class="receipt-row">
                    <span class="receipt-row-label">Cash Advance:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.cash_advance || 0).toFixed(2)}</span>
                </div>
                ` : ''}
                <hr class="receipt-divider">
                <div class="receipt-row" style="font-weight: 700;">
                    <span class="receipt-row-label">TOTAL DEDUCTIONS:</span>
                    <span class="receipt-row-value">₱${parseFloat(payslip.total_deductions || 0).toFixed(2)}</span>
                </div>
            </div>
            
            <!-- Net Pay -->
            <div class="receipt-total">
                <div>
                    <div class="receipt-total-label">NET PAY</div>
                    <div style="font-size: 10px; opacity: 0.8; margin-top: 2px;">Amount to Receive</div>
                </div>
                <div class="receipt-total-value">₱${parseFloat(payslip.net_pay || 0).toFixed(2)}</div>
            </div>
            
            <!-- Footer -->
            <div class="receipt-footer">
                <p class="receipt-footer-text">This is a computer-generated payslip.</p>
                <p class="receipt-footer-text">No signature required.</p>
                <p class="receipt-footer-text" style="margin-top: 10px;">Generated: ${formatDate(payslip.created_at)}</p>
                
                <!-- Barcode -->
                <div class="receipt-barcode">
                    <div class="receipt-barcode-lines">
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                        <div class="barcode-line"></div>
                    </div>
                    <p class="receipt-barcode-text">${receiptId}</p>
                </div>
                
                <p class="receipt-footer-text" style="margin-top: 15px;">© ${new Date().getFullYear()} TheBigFive Payroll System</p>
                <p class="receipt-footer-text">Thank you!</p>
            </div>
        </div>
    `;
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const listModal = document.getElementById('payslipsListModal');
    if (event.target === listModal) {
        closePayslipsListModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closePayslipsListModal();
        closePayslipReceiptModal();
    }
});

// Search and Filter Functionality
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchEmployee');
    const filterSelect = document.getElementById('filterStatus');
    const employeeCards = document.querySelectorAll('.employee-payslip-card-modern');
    const employeeCountBadge = document.getElementById('employeeCount');
    
    // Apply staggered animation delays to employee cards
    employeeCards.forEach((card, index) => {
        card.style.animationDelay = `${0.1 + (index * 0.05)}s`;
    });
    
    function filterCards() {
        const searchTerm = searchInput.value.toLowerCase();
        const filterValue = filterSelect.value;
        let visibleCount = 0;
        
        employeeCards.forEach(card => {
            const employeeName = card.getAttribute('data-employee-name');
            const hasPayslips = card.getAttribute('data-has-payslips');
            
            // Check search match
            const matchesSearch = employeeName.includes(searchTerm);
            
            // Check filter match
            let matchesFilter = true;
            if (filterValue === 'with-payslips') {
                matchesFilter = hasPayslips === 'yes';
            } else if (filterValue === 'no-payslips') {
                matchesFilter = hasPayslips === 'no';
            }
            
            // Show/hide card
            if (matchesSearch && matchesFilter) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });
        
        // Update count badge with animation
        employeeCountBadge.style.animation = 'none';
        setTimeout(() => {
            employeeCountBadge.style.animation = 'fadeIn 0.3s ease-out';
            employeeCountBadge.textContent = `${visibleCount} Employee${visibleCount !== 1 ? 's' : ''}`;
        }, 10);
    }
    
    // Attach event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterCards);
    }
    
    if (filterSelect) {
        filterSelect.addEventListener('change', filterCards);
    }
});
</script>

<?php
require_once 'include/footer.php';
?>
