<?php
/**
 * Generate Payroll Page
 * Admin can create payroll for employees
 */

$page_title = 'Generate Payroll';
require_once 'include/header.php';
require_once 'include/sidebar.php';
require_once '../config/database.php';

// Get all active employees
$pdo = getDBConnection();
$stmt = $pdo->prepare("SELECT id, employee_code, full_name, position, department, basic_monthly_salary FROM employees WHERE status = 'active' ORDER BY full_name ASC");
$stmt->execute();
$employees = $stmt->fetchAll();

// Get payroll periods
$stmt = $pdo->prepare("SELECT id, period_name, start_date, end_date, pay_date FROM payroll_periods ORDER BY start_date DESC LIMIT 10");
$stmt->execute();
$payroll_periods = $stmt->fetchAll();
?>

<div class="main-content">
    <div class="content-wrapper">
        <!-- Page Header -->
        <div class="page-header">
            <div class="page-header-content">
                <div class="page-header-left">
                    <div class="page-title-row">
                        <div class="page-title-text">
                            <h1>Generate Payroll</h1>
                        </div>
                    </div>
                    <p class="page-subtitle">Create and manage employee payroll with DTR import</p>
                </div>
            </div>
        </div>

        <div class="payroll-container">
            <!-- Mode Toggle Buttons -->
            <div class="payroll-mode-toggle">
                <button type="button" id="btn_mode_import" class="mode-toggle-btn active" onclick="switchPayrollMode('import')">
                    <i class="fas fa-file-upload"></i> Import Excel
                </button>
                <button type="button" id="btn_mode_manual" class="mode-toggle-btn" onclick="switchPayrollMode('manual')">
                    <i class="fas fa-keyboard"></i> Manual Entry
                </button>
            </div>

            <!-- ===== IMPORT MODE SECTION ===== -->
            <div id="import_mode_section">
                <div class="card import-card-compact">
                    <div class="card-body" style="padding: 30px 40px;">
                        <div style="display: flex; gap: 15px; align-items: center; justify-content: center;">
                            <button type="button" id="btn_download_template" class="btn-template-small">
                                <i class="fas fa-download"></i> Download Template
                            </button>
                            <button type="button" id="btn_import_excel" class="btn-import-compact">
                                <i class="fas fa-file-upload"></i> Import Excel
                            </button>
                            <input type="file" id="dtr_excel_file" accept=".xlsx,.xls,.xlsm,.csv" style="display: none;">
                        </div>
                        
                        <!-- File Selected Preview -->
                        <div id="file_selected_preview" class="file-selected-preview" style="display: none; margin-top: 15px; justify-content: center;">
                            <i class="fas fa-file-excel"></i>
                            <span id="selected_file_name">filename.xlsx</span>
                            <button type="button" class="btn-remove-file" onclick="removeSelectedFile(event)">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        
                        <!-- Import Status / Error Display -->
                        <div id="import_status" class="import-status-compact" style="display: none;"></div>
                    </div>
                </div>

                <!-- After Import: Employee Info Input (Editable) -->
                <div id="imported_data_section" class="card imported-data-card" style="display: none;">
                <div class="card-header imported-header">
                    <h3><i class="fas fa-user-edit"></i> Imported Data - Review & Edit</h3>
                    <span class="imported-badge">From Excel</span>
                </div>
                <div class="card-body">
                    <!-- Editable Employee Info -->
                    <div class="employee-info-edit">
                        <div class="info-row">
                            <div class="info-field">
                                <label>Employee Name <span class="required">*</span></label>
                                <input type="text" id="imported_employee_name" class="form-control" placeholder="Enter employee name">
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-field">
                                <label>Basic Monthly Salary <span class="required">*</span></label>
                                <input type="number" id="imported_salary" class="form-control" placeholder="13000" step="0.01">
                            </div>
                            <div class="info-field">
                                <label>Period Start</label>
                                <input type="date" id="imported_period_start" class="form-control">
                            </div>
                            <div class="info-field">
                                <label>Period End</label>
                                <input type="date" id="imported_period_end" class="form-control">
                            </div>
                            <div class="info-field">
                                <label>Records Imported</label>
                                <input type="text" id="imported_records_count" class="form-control" readonly>
                            </div>
                        </div>
                        
                        <!-- Payroll Summary Fields -->
                        <div class="info-row payroll-summary-row">
                            <div class="info-field">
                                <label>Days Office (Worked)</label>
                                <input type="number" id="imported_days_office" class="form-control" value="0" readonly>
                            </div>
                            <div class="info-field">
                                <label>Gross Pay</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₱</span>
                                    <input type="text" id="imported_gross" class="form-control" value="0.00" readonly>
                                </div>
                            </div>
                            <div class="info-field">
                                <label>No. of Trainings</label>
                                <input type="number" id="imported_trainings_count" class="form-control" value="0" min="0" oninput="calculateTraineePayment()">
                            </div>
                            <div class="info-field">
                                <label>Payment Per Trainee</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₱</span>
                                    <input type="number" id="imported_payment_per_trainee" class="form-control" value="0" step="0.01" min="0" oninput="calculateTraineePayment()">
                                </div>
                            </div>
                            <div class="info-field">
                                <label>Total Cost Trainings</label>
                                <div class="input-with-prefix">
                                    <span class="prefix">₱</span>
                                    <input type="number" id="imported_trainings_cost" class="form-control" value="0" step="0.01" min="0" readonly>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Net Pay Summary -->
                        <div class="net-pay-summary">
                            <div class="net-pay-item">
                                <span class="label">Total Deductions:</span>
                                <span class="value deductions" id="summary_total_deductions">₱0.00</span>
                            </div>
                            <div class="net-pay-item">
                                <span class="label">OT Pay:</span>
                                <span class="value earnings" id="summary_ot_pay">₱0.00</span>
                            </div>
                            <div class="net-pay-item">
                                <span class="label">Trainee Payment:</span>
                                <span class="value earnings" id="summary_trainee_payment">₱0.00</span>
                            </div>
                            <div class="net-pay-item net-pay-final">
                                <span class="label">NET PAY:</span>
                                <span class="value" id="summary_net_pay">₱0.00</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Note: DTR records are displayed in the DTR Calculator table below -->
                    <div style="padding: 15px; background: #f0f9ff; border: 1px solid #bee3f8; border-radius: 8px; margin-top: 10px;">
                        <i class="fas fa-info-circle" style="color: #3182ce;"></i>
                        <span style="color: #2c5282; font-size: 14px;">Imported DTR data has been loaded into the DTR Calculator below. Review and edit as needed, then save.</span>
                    </div>
                    
                    </div>
                </div>
            </div>
            </div><!-- end import_mode_section -->

            <!-- ===== MANUAL MODE SECTION ===== -->
            <div id="manual_mode_section" style="display: none;">
            <div class="card manual-entry-card">
                <div class="card-header">
                    <h3><i class="fas fa-keyboard"></i> Manual Entry</h3>
                    <span class="manual-badge">Enter DTR Manually</span>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_select">Employee</label>
                            <select id="employee_select" name="employee_id" class="form-control">
                                <option value=""> Select Employee </option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['id']; ?>" 
                                            data-code="<?php echo htmlspecialchars($employee['employee_code']); ?>"
                                            data-name="<?php echo htmlspecialchars($employee['full_name']); ?>"
                                            data-position="<?php echo htmlspecialchars($employee['position']); ?>"
                                            data-department="<?php echo htmlspecialchars($employee['department']); ?>"
                                            data-salary="<?php echo $employee['basic_monthly_salary']; ?>">
                                        <?php echo htmlspecialchars($employee['full_name']) . ' (' . htmlspecialchars($employee['employee_code']) . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="payroll_period">Payroll Period</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px;">
                                <select id="payroll_year" class="form-control" onchange="updateCutoffOptions(); updatePayrollPeriod();">
                                    <option value="">Year</option>
                                    <?php 
                                    $currentYear = date('Y');
                                    for ($y = $currentYear - 1; $y <= $currentYear + 2; $y++) {
                                        $selected = ($y == $currentYear) ? 'selected' : '';
                                        echo "<option value='$y' $selected>$y</option>";
                                    }
                                    ?>
                                </select>
                                <select id="payroll_month" class="form-control" onchange="updateCutoffOptions(); updatePayrollPeriod();">
                                    <option value="">Month</option>
                                    <option value="1">January</option>
                                    <option value="2">February</option>
                                    <option value="3">March</option>
                                    <option value="4">April</option>
                                    <option value="5">May</option>
                                    <option value="6">June</option>
                                    <option value="7">July</option>
                                    <option value="8">August</option>
                                    <option value="9">September</option>
                                    <option value="10">October</option>
                                    <option value="11">November</option>
                                    <option value="12">December</option>
                                </select>
                                <select id="payroll_cutoff" class="form-control" onchange="updatePayrollPeriod()">
                                    <option value="">Cut-off</option>
                                    <option value="12">12th (1-15 period)</option>
                                    <option value="27">27th (16-end period)</option>
                                </select>
                            </div>
                            <input type="hidden" id="calculated_start_date" name="period_start_date">
                            <input type="hidden" id="calculated_end_date" name="period_end_date">
                            <input type="hidden" id="calculated_pay_date" name="calculated_pay_date">
                            <small style="display: block; margin-top: 5px; color: #666;">Select year, month, and pay date (12th for 1-15 period, 27th for 16-last day period)</small>
                        </div>
                    </div>
                </div>
            </div>
            </div><!-- end manual_mode_section -->

            <!-- Payslip Form Template -->
            <div id="payslip_form" class="payslip-form" style="display: none;">
                <form id="payroll_form" method="POST" action="process_payroll.php">
                    <input type="hidden" name="employee_id" id="form_employee_id">
                    <input type="hidden" name="payroll_period_id" id="form_payroll_period_id">

                        <!-- DTR Section -->
                        <div class="dtr-section">
                            <div class="dtr-header-tb5">
                                <div class="tb5-title">DTR CALCULATOR</div>
                                <button type="button" id="btn_dtr_full_view" class="btn-full-view" onclick="openDTRFullView()" title="View Full DTR Table">
                                    <i class="fas fa-expand-arrows-alt"></i> Full View
                                </button>
                            </div>
                            
                            <!-- TB5 Employee & Rate Info Row -->
                            <div class="tb5-info-row">
                                <div class="tb5-employee-info">
                                    <span class="tb5-label">EMPLOYEE NAME:</span>
                                    <span class="tb5-value" id="tb5_employee_name">-</span>
                                </div>
                                <div class="tb5-company">
                                    THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC.
                                </div>
                            </div>
                            
                            <!-- TB5 Rate Calculation Row -->
                            <div class="tb5-rate-row">
                                <div class="tb5-rate-item rate-input-item">
                                    <span class="tb5-rate-label">BASIC SALARY</span>
                                    <div class="rate-input-wrapper">
                                        <span class="peso-sign">₱</span>
                                        <input type="number" id="basic_monthly_salary" name="basic_monthly_salary" 
                                               class="rate-input tb5-basic-input" value="13000" step="100" 
                                               oninput="updateAllRates()">
                                    </div>
                                </div>
                                <div class="tb5-rate-item rate-input-item">
                                    <span class="tb5-rate-label">PER/DAY</span>
                                    <div class="rate-input-wrapper">
                                        <span class="peso-sign">₱</span>
                                        <input type="number" id="daily_rate" name="daily_rate_value" 
                                               class="rate-input" value="433.33" step="0.01" 
                                               oninput="updateRatesFromDaily()">
                                    </div>
                                </div>
                                <div class="tb5-rate-item">
                                    <span class="tb5-rate-label">PER/HOUR</span>
                                    <div class="computed-rate">
                                        <span>₱</span><span id="hourly_rate">54.17</span>
                                    </div>
                                    <input type="hidden" name="hourly_rate_value" id="hourly_rate_value" value="54.17">
                                </div>
                                <div class="tb5-rate-item">
                                    <span class="tb5-rate-label">PER/MIN</span>
                                    <div class="computed-rate">
                                        <span>₱</span><span id="minute_rate">0.9028</span>
                                    </div>
                                    <input type="hidden" name="minute_rate_value" id="minute_rate_value" value="0.9028">
                                </div>
                                <div class="tb5-rate-item rate-input-item">
                                    <span class="tb5-rate-label">OT RATE</span>
                                    <div class="rate-input-wrapper">
                                        <span class="peso-sign">₱</span>
                                        <input type="number" id="ot_rate" name="ot_rate_value" 
                                               class="rate-input" value="67.71" step="0.01" 
                                               oninput="updateOTRate()">
                                    </div>
                                </div>
                                <div class="tb5-rate-item">
                                    <span class="tb5-rate-label">LATE START</span>
                                    <input type="text" id="late_threshold" name="late_threshold" value="7:35" class="time-input time24" autocomplete="off" placeholder="7:35" maxlength="5" oninput="formatTime24(this)" onchange="recalculateAllRows()">
                                </div>
                                <div class="tb5-rate-item">
                                    <span class="tb5-rate-label">END TIME</span>
                                    <input type="text" id="end_threshold" name="end_threshold" value="17:00" class="time-input time24" autocomplete="off" placeholder="17:00" maxlength="5" oninput="formatTime24(this)" onchange="recalculateAllRows()">
                                </div>
                            </div>

                        <div class="dtr-table-wrapper">
                            <table class="dtr-table" id="main_dtr_table">
                                <thead>
                                    <tr>
                                        <th rowspan="3" class="th-date">MO/YR<br>DATE</th>
                                        <th rowspan="3" class="th-group th-am">AM IN</th>
                                        <th rowspan="3" class="th-group th-pm">PM OUT</th>
                                        <th rowspan="3" class="th-single th-absent-col">ABSENT</th>
                                        <th rowspan="3" class="th-single th-ot-col">OT<br>OUT</th>
                                        <th rowspan="3" class="th-calc">TOT.WORK<br>(in hours)</th>
                                        <th rowspan="3" class="th-calc">LATE<br>(in mins)</th>
                                        <th rowspan="3" class="th-calc">UNDERTIME<br>(in hours)</th>
                                        <th rowspan="3" class="th-calc">OT<br>(in hours)</th>
                                        <th rowspan="3" class="th-single">ABSENT<br>(in days)</th>
                                        <th rowspan="3" class="th-deduct">ABSENT<br>DEDUCT</th>
                                        <th rowspan="3" class="th-deduct">LATE<br>DEDUCT</th>
                                        <th rowspan="3" class="th-deduct">UNDERTIME<br>DEDUCT</th>
                                        <th rowspan="3" class="th-deduct">HALFDAY<br>DEDUCT</th>
                                        <th rowspan="3" class="th-pay">OT PAY</th>
                                        <th rowspan="3" class="th-calc">MINUS OT<br>TOTAL<br>DEDUCTIONS</th>
                                        <th colspan="3" class="th-group th-auto-calc">AUTOMATIC CALCULATIONS</th>
                                        <th rowspan="3" class="th-manual">Government<br>Benefits</th>
                                        <th rowspan="3" class="th-auto-salary">Net<br>Salary</th>
                                        <th rowspan="3" class="th-f1">F1*</th>
                                        <th rowspan="3" class="th-f2">F2*</th>
                                        <th rowspan="3" class="th-remarks">REMARKS</th>
                                        <th rowspan="3" class="th-action">ACTIONS</th>
                                    </tr>
                                    <tr>
                                        <th class="th-sub th-auto-calc">LATE/min</th>
                                        <th class="th-sub th-auto-calc">UNDERTIME</th>
                                        <th class="th-sub th-auto-calc">OT</th>
                                    </tr>
                                </thead>
                                <tbody id="dtr_rows">
                                    <!-- DTR rows will be generated here -->
                                </tbody>
                                <tfoot>
                                    <tr class="totals-row">
                                        <td colspan="5" class="totals-label">TOTALS:</td>
                                        <td id="total_work_mins">0</td>
                                        <td id="total_late_hours">0.00</td>
                                        <td id="total_undertime">0.00</td>
                                        <td id="total_ot_hours">0.00</td>
                                        <td id="total_absent_days">0</td>
                                        <td id="total_absent_deduct">0.00</td>
                                        <td id="total_late_deduct">0.00</td>
                                        <td id="total_undertime_deduct">0.00</td>
                                        <td id="total_halfday_deduct">0.00</td>
                                        <td id="total_ot_payment">0.00</td>
                                        <td id="total_net_deductions">0.00</td>
                                        <td id="total_late_min">0.00</td>
                                        <td id="total_undertime_calc">0.00</td>
                                        <td id="total_ot_calc">0.00</td>
                                        <td id="total_govt">0.00</td>
                                        <td id="total_salary">0.00</td>
                                        <td id="total_f1"></td>
                                        <td id="total_f2"></td>
                                        <td></td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        </div>

                        <!-- Save to Payroll Action Bar -->
                        <div class="dtr-save-bar" id="dtr_save_bar">
                            <div class="dtr-save-bar-left">
                                <span class="save-info-text">
                                    <i class="fas fa-info-circle"></i>
                                    Review all DTR entries above, then save to link this payroll record to the selected employee.
                                </span>
                            </div>
                            <div class="dtr-save-bar-right">
                                <button type="button" class="btn-recalculate" onclick="recalculateAllDTR()" title="Recalculate all rows" style="padding: 8px 16px; background: #17a2b8; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; margin-right: 8px;">
                                    <i class="fas fa-calculator"></i> Recalculate All
                                </button>
                                <button type="button" id="btn_save_main_dtr" class="btn-save-payroll" onclick="saveMainDTRToDatabase()">
                                    <i class="fas fa-save"></i> Save to Payroll List
                                </button>
                            </div>
                        </div>

                        <!-- Trainee Payment Section -->
                        <div class="trainee-payment-card" id="trainee_payment_card" style="margin-top: 20px;">
                            <div class="trainee-card-header">
                                <h3>
                                    <i class="fas fa-users"></i>
                                    Trainee Payment
                                </h3>
                                <span class="trainee-badge">Additional Earnings</span>
                            </div>
                            <div class="trainee-card-body">
                                <div class="trainee-info-text">
                                    <i class="fas fa-info-circle"></i>
                                    Enter trainee-related compensation that will be added to the net pay for this payroll period.
                                </div>
                                <div class="trainee-form-row">
                                    <div class="trainee-input-group">
                                        <label for="trainee_count_main">
                                            <i class="fas fa-user-graduate"></i>
                                            Number of Trainees
                                        </label>
                                        <input type="number" 
                                               id="trainee_count_main" 
                                               name="trainee_count_main" 
                                               class="trainee-input" 
                                               value="0" 
                                               min="0" 
                                               step="1"
                                               oninput="calculateMainTraineePayment()"
                                               placeholder="0">
                                    </div>
                                    
                                    <div class="trainee-input-group">
                                        <label for="trainee_payment_per_main">
                                            <i class="fas fa-peso-sign"></i>
                                            Payment Per Trainee
                                        </label>
                                        <div class="input-with-prefix">
                                            <span class="prefix">₱</span>
                                            <input type="number" 
                                                   id="trainee_payment_per_main" 
                                                   name="trainee_payment_per_main" 
                                                   class="trainee-input" 
                                                   value="0.00" 
                                                   min="0" 
                                                   step="0.01"
                                                   oninput="calculateMainTraineePayment()"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                    
                                    <div class="trainee-input-group trainee-total-group">
                                        <label for="trainee_total_main">
                                            <i class="fas fa-calculator"></i>
                                            Total Trainee Payment
                                        </label>
                                        <div class="trainee-total-display">
                                            <span class="currency-symbol">₱</span>
                                            <input type="number" 
                                                   id="trainee_total_main" 
                                                   name="trainee_total_main" 
                                                   class="trainee-input trainee-total-input" 
                                                   value="0.00" 
                                                   readonly
                                                   placeholder="0.00">
                                        </div>
                                        <small class="field-hint">Auto-calculated: Count × Payment</small>
                                    </div>
                                </div>
                                
                                <div class="trainee-impact-notice">
                                    <i class="fas fa-arrow-up"></i>
                                    This amount will be <strong>added</strong> to the final net pay calculation.
                                </div>
                            </div>
                        </div>
                        
                        <!-- End DTR Section -->
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.payroll-container {
    margin-top: 20px;
}

/* Compact Modern Import Card */
.import-card-compact {
    background: #fff;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.import-header-compact {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 20px;
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    border-radius: 8px 8px 0 0;
}

.import-header-compact .header-left {
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-header-compact h3 {
    color: #fff;
    font-size: 15px;
    margin: 0;
}

.import-header-compact i {
    color: #48bb78;
    font-size: 18px;
}

.btn-template-small {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    border: none;
    color: #fff;
    padding: 16px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(66, 153, 225, 0.4);
}

.btn-template-small:hover {
    background: linear-gradient(135deg, #3182ce 0%, #2c5aa0 100%);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(66, 153, 225, 0.5);
}

.btn-template-small i {
    font-size: 18px;
}

.import-body-compact {
    padding: 15px 20px;
}

.import-row {
    display: flex;
    gap: 15px;
    align-items: center;
}

.import-dropzone-compact {
    flex: 1;
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 15px 20px;
    background: #f8fafc;
    border: 2px dashed #cbd5e0;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.import-dropzone-compact:hover,
.import-dropzone-compact.dragover {
    border-color: #48bb78;
    background: #f0fff4;
}

.import-dropzone-compact i {
    font-size: 28px;
    color: #a0aec0;
}

.import-dropzone-compact.dragover i {
    color: #48bb78;
}

.dropzone-text {
    display: flex;
    flex-direction: column;
}

.dropzone-main {
    font-size: 13px;
    color: #4a5568;
}

.browse-link {
    color: #4299e1;
    text-decoration: underline;
    cursor: pointer;
}

.dropzone-hint {
    font-size: 11px;
    color: #a0aec0;
}

.btn-import-compact {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: #fff;
    border: none;
    padding: 16px 32px;
    border-radius: 12px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow: 0 4px 15px rgba(72, 187, 120, 0.4);
}

.btn-import-compact:hover {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(72, 187, 120, 0.5);
}

.btn-import-compact i {
    font-size: 18px;
}

.file-selected-preview {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 15px;
    background: #ebf8ff;
    border: 1px solid #90cdf4;
    border-radius: 6px;
    margin-top: 10px;
}

.file-selected-preview i {
    color: #38a169;
    font-size: 18px;
}

.file-selected-preview span {
    flex: 1;
    font-size: 13px;
    color: #2d3748;
}

.btn-remove-file {
    background: transparent;
    border: none;
    color: #e53e3e;
    cursor: pointer;
    padding: 5px;
}

.import-status-compact {
    margin-top: 10px;
    padding: 10px 15px;
    border-radius: 6px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-status-compact.success {
    background: #c6f6d5;
    color: #22543d;
    border: 1px solid #9ae6b4;
}

.import-status-compact.error {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #fc8181;
}

.import-status-compact.loading {
    background: #bee3f8;
    color: #2a4365;
    border: 1px solid #90cdf4;
}

.import-status-compact .error-details {
    margin-top: 5px;
    font-size: 11px;
    color: #9b2c2c;
    background: #fff5f5;
    padding: 8px;
    border-radius: 4px;
    white-space: pre-wrap;
}

/* Imported Data Section */
.imported-data-card {
    margin-top: 20px;
    border: 2px solid #48bb78;
}

.imported-header {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.imported-header h3 {
    color: #fff;
    font-size: 15px;
}

.imported-badge {
    background: rgba(255,255,255,0.2);
    color: #fff;
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
}

.employee-info-edit {
    margin-bottom: 20px;
}

.info-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 15px;
    margin-bottom: 15px;
}

.info-field label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: #4a5568;
    margin-bottom: 5px;
}

.info-field .required {
    color: #e53e3e;
}

.info-field .form-control {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 13px;
}

.info-field .form-control:focus {
    border-color: #48bb78;
    outline: none;
    box-shadow: 0 0 0 3px rgba(72, 187, 120, 0.1);
}

/* Payroll Summary Row */
.payroll-summary-row {
    background: linear-gradient(135deg, #e6fffa 0%, #f0fff4 100%);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    border: 1px solid #9ae6b4;
}

/* Trainee Payment Card */
.trainee-payment-card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    border: 2px solid #4299e1;
    overflow: hidden;
}

.trainee-card-header {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.trainee-card-header h3 {
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.trainee-card-header h3 i {
    font-size: 18px;
}

.trainee-badge {
    background: rgba(255,255,255,0.25);
    color: #fff;
    padding: 5px 12px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.trainee-card-body {
    padding: 20px;
}

.trainee-info-text {
    background: #ebf8ff;
    border-left: 4px solid #4299e1;
    padding: 12px 15px;
    margin-bottom: 20px;
    border-radius: 4px;
    font-size: 13px;
    color: #2c5282;
    display: flex;
    align-items: center;
    gap: 10px;
}

.trainee-info-text i {
    color: #4299e1;
    font-size: 16px;
}

.trainee-form-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.trainee-input-group {
    display: flex;
    flex-direction: column;
}

.trainee-input-group label {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 8px;
}

.trainee-input-group label i {
    color: #4299e1;
    font-size: 14px;
}

.trainee-input {
    padding: 10px 12px;
    border: 2px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.trainee-input:focus {
    border-color: #4299e1;
    outline: none;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.trainee-input::placeholder {
    color: #cbd5e0;
}

.trainee-total-group {
    position: relative;
}

.trainee-total-display {
    position: relative;
    display: flex;
    align-items: center;
}

.trainee-total-display .currency-symbol {
    position: absolute;
    left: 12px;
    color: #2d3748;
    font-weight: 700;
    font-size: 14px;
    z-index: 1;
}

.trainee-total-input {
    background: #f7fafc !important;
    border-color: #cbd5e0 !important;
    color: #2d3748;
    font-weight: 600;
    font-size: 16px;
    padding-left: 28px !important;
}

.field-hint {
    display: block;
    font-size: 11px;
    color: #718096;
    margin-top: 5px;
    font-style: italic;
}

.trainee-impact-notice {
    background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
    border: 1px solid #48bb78;
    border-radius: 6px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #22543d;
}

.trainee-impact-notice i {
    color: #38a169;
    font-size: 16px;
}

.trainee-impact-notice strong {
    color: #22543d;
    font-weight: 700;
}

/* Responsive Design for Trainee Form */
@media (max-width: 992px) {
    .trainee-form-row {
        grid-template-columns: 1fr;
        gap: 15px;
    }
}

@media (max-width: 768px) {
    .trainee-card-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
    
    .trainee-badge {
        align-self: flex-start;
    }
}

/* Payroll Summary Row */
.payroll-summary-row {
    background: linear-gradient(135deg, #e6fffa 0%, #f0fff4 100%);
    padding: 15px;
    border-radius: 8px;
    margin-top: 15px;
    border: 1px solid #9ae6b4;
}

.input-with-prefix {
    position: relative;
    display: flex;
    align-items: center;
}

.input-with-prefix .prefix {
    position: absolute;
    left: 10px;
    color: #718096;
    font-weight: 600;
    z-index: 1;
}

.input-with-prefix input {
    padding-left: 25px;
}

/* Net Pay Summary */
.net-pay-summary {
    display: flex;
    justify-content: flex-end;
    gap: 30px;
    padding: 20px;
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    border-radius: 12px;
    margin-top: 20px;
}

.net-pay-item {
    display: flex;
    align-items: center;
    gap: 10px;
}

.net-pay-item .label {
    color: rgba(255,255,255,0.7);
    font-size: 14px;
}

.net-pay-item .value {
    font-size: 18px;
    font-weight: 700;
    color: white;
}

.net-pay-item .value.deductions {
    color: #fc8181;
}

.net-pay-item .value.earnings {
    color: #68d391;
}

.net-pay-final {
    padding-left: 30px;
    border-left: 2px solid rgba(255,255,255,0.2);
}

.net-pay-final .label {
    font-size: 16px;
    font-weight: 600;
    color: white;
}

.net-pay-final .value {
    font-size: 28px;
    color: #ffd700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

/* TB5 DTR Table */
.tb5-dtr-container {
    background: #f8fafc;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 20px;
}

.tb5-dtr-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.tb5-dtr-header h4 {
    margin: 0;
    font-size: 14px;
    color: #2d3748;
}

.tb5-rate-display {
    display: flex;
    gap: 20px;
    font-size: 12px;
    color: #4a5568;
}

.tb5-rate-display span {
    background: #e2e8f0;
    padding: 4px 10px;
    border-radius: 4px;
}

.tb5-table-scroll {
    overflow-x: auto;
    max-height: 500px;
    overflow-y: auto;
}

.tb5-dtr-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    white-space: nowrap;
}

.tb5-dtr-table th,
.tb5-dtr-table td {
    padding: 6px 8px;
    border: 1px solid #cbd5e0;
    text-align: center;
}

.tb5-dtr-table thead th {
    position: sticky;
    top: 0;
    background: #2d3748;
    color: #fff;
    font-weight: 600;
    z-index: 10;
}

.th-orange { background: #ed8936 !important; }
.th-red { background: #e53e3e !important; }
.th-blue { background: #4299e1 !important; }
.th-yellow { background: #ecc94b !important; color: #2d3748 !important; }
.th-green { background: #48bb78 !important; }
.th-pink { background: #ed64a6 !important; }

.tb5-dtr-table tbody tr:nth-child(even) {
    background: #f7fafc;
}

.tb5-dtr-table tbody tr:hover {
    background: #e6fffa;
}

.tb5-dtr-table tbody input {
    width: 65px;
    padding: 3px 5px;
    border: 1px solid #e2e8f0;
    border-radius: 3px;
    font-size: 11px;
    text-align: center;
}

.tb5-dtr-table tbody input:focus {
    border-color: #4299e1;
    outline: none;
}

.tb5-dtr-table tbody input.time-input {
    width: 85px;
}

.tb5-dtr-table tbody input.absent-input {
    width: 30px;
}

.tb5-dtr-table tbody .calc-cell {
    background: #1a202c;
    color: #fff;
    font-weight: 500;
}

.tb5-dtr-table tbody .deduct-cell {
    background: #1a202c;
    color: #fc8181;
}

.tb5-dtr-table tbody .ot-cell {
    background: #1a202c;
    color: #68d391;
}

.totals-row {
    background: #4c51bf !important;
    color: #fff;
    font-weight: bold;
}

.totals-row td {
    background: #4c51bf;
}

.totals-label {
    text-align: right !important;
    font-size: 12px;
}

.total-cell {
    font-size: 12px;
}

.imported-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
}

.btn-primary-lg {
    background: linear-gradient(135deg, #4c51bf 0%, #434190 100%);
    color: #fff;
    padding: 12px 30px;
    font-size: 14px;
}

.btn-primary-lg:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(76, 81, 191, 0.3);
}

.btn-secondary {
    background: #718096;
    color: #fff;
    padding: 12px 20px;
}

/* Hide old styles - keep for reference */
.import-card-main { display: none; }
.import-header-main { display: none; }
.extracted-info-preview { display: none !important; }

.extracted-info-preview h4 {
    margin: 0 0 15px 0;
    color: #2d3748;
    font-size: 16px;
    border-bottom: 2px solid #e2e8f0;
    padding-bottom: 10px;
}

.extracted-info-preview h4 i {
    color: #48bb78;
    margin-right: 8px;
}

.extracted-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.extracted-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.extracted-item .label {
    font-size: 12px;
    color: #718096;
    font-weight: 500;
}

.extracted-item .value {
    font-size: 14px;
    color: #2d3748;
    font-weight: 600;
}

.extracted-item .auto-generated {
    font-size: 10px;
    color: #a0aec0;
    font-style: italic;
}

.extracted-info-preview .manual-note {
    margin-top: 15px;
    padding: 10px;
    background-color: #ebf8ff;
    border-radius: 6px;
    font-size: 13px;
    color: #3182ce;
}

.extracted-info-preview .manual-note i {
    margin-right: 6px;
}

/* OR Divider */
.or-divider {
    display: flex;
    align-items: center;
    margin: 25px 0;
}

.or-divider::before,
.or-divider::after {
    content: '';
    flex: 1;
    height: 2px;
    background: linear-gradient(to right, transparent, #e2e8f0, transparent);
}

.or-divider span {
    padding: 0 20px;
    color: #a0aec0;
    font-weight: 600;
    font-size: 14px;
}

/* Payroll Mode Toggle */
.payroll-mode-toggle {
    display: flex;
    gap: 0;
    margin-bottom: 20px;
    background: #e2e8f0;
    border-radius: 12px;
    padding: 4px;
    width: fit-content;
}

.mode-toggle-btn {
    padding: 12px 28px;
    border: none;
    border-radius: 10px;
    font-size: 15px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
    transition: all 0.3s ease;
    background: transparent;
    color: #718096;
}

.mode-toggle-btn:hover {
    color: #2d3748;
}

.mode-toggle-btn.active {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: #fff;
    box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
}

.mode-toggle-btn.active i {
    color: #fff;
}

/* Manual Entry Card */
.manual-entry-card {
    transition: all 0.3s ease;
}

.manual-entry-card .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.manual-badge {
    background: #e2e8f0;
    color: #718096;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.card {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
}

.card-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e0e0e0;
    background: #f8f9fa;
    border-radius: 8px 8px 0 0;
}

.card-header h3 {
    margin: 0;
    color: #333;
    font-size: 18px;
}

.card-header i {
    margin-right: 8px;
    color: #007bff;
}

.card-body {
    padding: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-group {
    display: flex;
    flex-direction: column;
}

.form-group label {
    margin-bottom: 8px;
    font-weight: 600;
    color: #333;
}

.required {
    color: #dc3545;
}

.form-control {
    padding: 10px 12px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* Payslip Form Styling */
.payslip-form {
    background: #ffffff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    padding: 30px;
    margin-top: 20px;
    border: 1px solid #e2e8f0;
}

.payslip-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 20px;
    border-bottom: 2px solid #2d3748;
    margin-bottom: 20px;
}

.company-info h2 {
    color: #2d3748;
    margin: 0 0 10px 0;
    font-size: 24px;
    font-weight: 700;
}

.company-info p {
    margin: 5px 0;
    color: #718096;
    font-size: 12px;
}

.payslip-title h1 {
    color: #2d3748;
    margin: 0;
    font-size: 36px;
    font-weight: 700;
}

.payslip-info-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
    margin-bottom: 20px;
}

.employee-info-section {
    background: #ffffff;
    padding: 15px;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.section-header {
    background: #2d3748;
    color: white;
    padding: 8px 12px;
    font-weight: 600;
    font-size: 12px;
    margin: -15px -15px 15px -15px;
    border-radius: 4px 4px 0 0;
}

.info-group {
    margin-bottom: 12px;
}

.info-group label {
    font-weight: 600;
    color: #4a5568;
    font-size: 13px;
    display: block;
    margin-bottom: 4px;
}

.info-value {
    color: #2d3748;
    font-size: 14px;
}

.pay-info-section {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.pay-info-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
}

.pay-info-item {
    background: #ffffff;
    padding: 10px;
    border-radius: 4px;
    text-align: center;
    border: 1px solid #e2e8f0;
}

.section-header-small {
    background: #2d3748;
    color: white;
    padding: 6px;
    font-weight: 600;
    font-size: 10px;
    margin: -10px -10px 8px -10px;
    border-radius: 4px 4px 0 0;
}

.pay-info-value {
    font-weight: 600;
    color: #2d3748;
    font-size: 14px;
}

.form-input {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 13px;
    text-align: center;
    transition: all 0.2s ease;
}

.form-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.payment-method {
    background: #ffffff;
    padding: 10px;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: 1px solid #e2e8f0;
}

.payment-method label {
    font-weight: 600;
    color: #2d3748;
    margin: 0;
}

.calculation-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.calculation-table th {
    background: #f7fafc;
    color: #2d3748;
    padding: 10px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    border-bottom: 2px solid #e2e8f0;
}

.calculation-table th.section-header {
    background: #2d3748;
    color: white;
    text-align: center;
    font-size: 12px;
}

.calculation-table td {
    padding: 10px;
    border-bottom: 1px solid #f1f3f5;
    font-size: 13px;
    color: #4a5568;
}

.calculation-table tbody tr {
    transition: all 0.2s ease;
}

.calculation-table tbody tr:hover {
    background: #f7fafc;
}

.table-input {
    width: 100%;
    padding: 6px 10px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 13px;
    text-align: right;
    transition: all 0.2s ease;
}

.table-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

.table-input[readonly] {
    background: #f7fafc;
    border-color: #e2e8f0;
}

/* Modern DTR Table Styles */
.dtr-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: 12px;
    min-width: 2400px;
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
}

.dtr-table thead {
    background: #f8f9fa;
}

.dtr-table th {
    padding: 10px 6px;
    font-weight: 600;
    text-align: center;
    color: #333;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    border: 1px solid #ddd;
}

.dtr-table th:last-child {
    border-right: 1px solid #ddd;
}

/* TB5 Color Scheme - Date/MO/YR */
.dtr-table .th-date {
    background: #ffffff;
    font-weight: 700;
    min-width: 75px;
}

/* TB5 Color Scheme - AM/PM Shift (Orange) */
.dtr-table .th-am,
.dtr-table .th-pm,
.dtr-table .th-group {
    background: #FFCC99;
    color: #333;
}

/* TB5 Color Scheme - Absent Column (Red/Pink) */
.dtr-table .th-absent-col {
    background: #FF9999;
    color: #333;
    min-width: 60px;
}

/* TB5 Color Scheme - OT Column (Blue) */
.dtr-table .th-ot-col {
    background: #99CCFF;
    color: #333;
    min-width: 70px;
}

/* TB5 Color Scheme - Halfday (Yellow) */
.dtr-table .th-halfday {
    background: #FFFF99;
    color: #333;
    min-width: 140px;
}

/* TB5 Color Scheme - Calculations (Green) */
.dtr-table .th-calc {
    background: #CCFFCC;
    color: #333;
    min-width: 75px;
}

/* TB5 Color Scheme - Deductions (Pink) */
.dtr-table .th-deduct {
    background: #FFCCCC;
    color: #333;
    min-width: 80px;
}

/* TB5 Color Scheme - OT Pay (Bright Green) */
.dtr-table .th-pay {
    background: #99FF99;
    color: #333;
    min-width: 80px;
}

.dtr-table .th-single {
    background: #ffffff;
    min-width: 65px;
}

.dtr-table .th-action {
    background: #ffffff;
    color: #6c757d;
    width: 50px;
}

/* New columns - Automatic Calculations */
.dtr-table .th-auto-calc {
    background: #E6F3FF;
    color: #333;
    min-width: 75px;
}

/* New columns - Manual (Gov't) */
.dtr-table .th-manual {
    background: #FFFFCC;
    color: #333;
    min-width: 75px;
}

/* New columns - Automatic Salary */
.dtr-table .th-auto-salary {
    background: #CCFFCC;
    color: #333;
    min-width: 85px;
}

/* New columns - F1 and F2 */
.dtr-table .th-f1,
.dtr-table .th-f2 {
    background: #ffffff;
    color: #333;
    min-width: 60px;
}

/* New columns - Remarks */
.dtr-table .th-remarks {
    background: #FFE6E6;
    color: #333;
    min-width: 150px;
}

.dtr-table .th-sub {
    font-size: 9px;
    padding: 6px 4px;
}

/* TB5 Subtext row (DATE, Column, (in mins), etc.) */
.dtr-table .th-subtext-row th {
    font-size: 8px;
    padding: 4px 3px;
    background: #f8f9fa;
    font-weight: 400;
    color: #666;
    border-top: none;
}

.dtr-table td {
    padding: 8px 4px;
    text-align: center;
    border-bottom: 1px solid #f1f3f5;
    border-right: 1px solid #f1f3f5;
    color: #495057;
    white-space: nowrap;
}

.dtr-table td:last-child {
    border-right: none;
}

.dtr-table tbody tr {
    transition: all 0.2s ease;
    background: #ffffff;
}

.dtr-table tbody tr:hover {
    background: #f8f9fa;
    box-shadow: 0 2px 4px rgba(0,0,0,0.04);
}

.dtr-table tbody tr:nth-child(even) {
    background: #fafbfc;
}

.dtr-table tbody tr:nth-child(even):hover {
    background: #f8f9fa;
}

.dtr-data-row {
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.totals-row {
    background: #2d3748 !important;
    color: #ffffff !important;
    font-weight: 600;
    font-size: 13px;
}

.totals-row td {
    padding: 14px 8px;
    border: none !important;
    color: #ffffff;
}

.totals-label {
    text-align: right !important;
    padding-right: 20px !important;
    font-weight: 700;
    letter-spacing: 1px;
}

.dtr-table input {
    width: 100%;
    min-width: 70px;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
    text-align: center;
    background: #ffffff;
    transition: all 0.2s ease;
}

.dtr-table input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

/* 24-hour Military Time Text Inputs - Modern Design */
.dtr-table input.time24 {
    width: 75px;
    min-width: 65px;
    font-size: 13px;
    font-weight: 600;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    text-align: center;
    padding: 7px 10px;
    letter-spacing: 1.2px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: linear-gradient(135deg, #ffffff 0%, #f7fafc 100%);
    color: #2d3748;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.8);
}

.dtr-table input.time24:hover:not(:disabled) {
    border-color: #cbd5e0;
    background: linear-gradient(135deg, #ffffff 0%, #edf2f7 100%);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.12), inset 0 1px 1px rgba(255, 255, 255, 0.9);
    transform: translateY(-1px) scale(1.02);
}

.dtr-table input.time24::placeholder {
    color: #a0aec0;
    font-style: italic;
    letter-spacing: 0.8px;
    font-weight: 400;
}

.dtr-table input.time24:focus {
    outline: none;
    border-color: #4299e1;
    background: linear-gradient(135deg, #ffffff 0%, #ebf8ff 100%);
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.2), 0 3px 8px rgba(66, 153, 225, 0.25), inset 0 1px 2px rgba(66, 153, 225, 0.1);
    transform: translateY(-1px) scale(1.02);
    animation: timePulse 2s ease-in-out infinite;
}

.dtr-table input.time24:disabled {
    background: #f7fafc;
    border-color: #e2e8f0;
    cursor: not-allowed;
    opacity: 0.5;
    box-shadow: none;
    transform: none;
}

/* Valid time input state in DTR table - subtle success indicator */
.dtr-table input.time24:valid:not(:placeholder-shown):not(:focus) {
    border-color: #48bb78;
    background: linear-gradient(135deg, #ffffff 0%, #f0fff4 100%);
    box-shadow: 0 1px 3px rgba(72, 187, 120, 0.12), inset 0 1px 1px rgba(72, 187, 120, 0.05);
}

.dtr-table input[type="time"] {
    width: 110px;
    min-width: 80px;
    font-size: 13px;
    font-family: 'Segoe UI', sans-serif;
    text-align: center;
    padding: 5px 8px;
}

.dtr-table input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

.dtr-table input[readonly] {
    background: #f7fafc;
    border-color: #e2e8f0;
    color: #4a5568;
    font-weight: 500;
}

/* Styling for new column inputs */
.dtr-table .dtr-auto-calc {
    background: #E6F3FF !important;
    font-weight: 500;
    min-width: 75px;
}

.dtr-table .dtr-manual {
    background: #FFFFCC !important;
    min-width: 75px;
}

.dtr-table .dtr-auto-salary {
    background: #CCFFCC !important;
    font-weight: 500;
    min-width: 85px;
}

.dtr-table .dtr-f-input {
    text-align: center;
    font-size: 11px;
    text-transform: uppercase;
    min-width: 60px;
}

.dtr-table .dtr-remarks-input {
    text-align: left;
    font-size: 11px;
    min-width: 150px;
}

/* DTR Save Action Bar */
.dtr-save-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    margin-top: 20px;
    padding: 16px 24px;
    background: linear-gradient(135deg, #1a202c 0%, #2d3748 100%);
    border-radius: 10px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.dtr-save-bar-left {
    flex: 1;
}

.save-info-text {
    color: #a0aec0;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.save-info-text i {
    color: #4299e1;
    font-size: 14px;
}

.dtr-save-bar-right {
    flex-shrink: 0;
}

.btn-save-payroll {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    color: #fff;
    border: none;
    padding: 14px 32px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    transition: all 0.2s ease;
    box-shadow: 0 4px 12px rgba(56, 161, 105, 0.4);
    letter-spacing: 0.3px;
}

.btn-save-payroll:hover {
    background: linear-gradient(135deg, #2f855a 0%, #276749 100%);
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(56, 161, 105, 0.5);
}

.btn-save-payroll:active {
    transform: translateY(0);
}

.btn-save-payroll:disabled {
    background: #718096;
    cursor: not-allowed;
    transform: none;
    box-shadow: none;
}

.payslip-footer {
    text-align: center;
    margin: 40px 0 20px 0;
    padding: 25px;
    background: #ffffff;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.schedule-info {
    background: #f7fafc;
    padding: 20px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 3px solid #2d3748;
}

.schedule-info h4 {
    margin: 0 0 15px 0;
    color: #2d3748;
    font-size: 14px;
    font-weight: 600;
}

.schedule-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    font-size: 13px;
    text-align: left;
}

.schedule-item {
    color: #4a5568;
}

.pay-date {
    color: #2d3748;
    font-weight: 600;
}

.contact-info {
    margin: 15px 0 5px 0;
    color: #718096;
    font-size: 13px;
}

.contact-details {
    margin: 5px 0;
    color: #2d3748;
    font-size: 14px;
}

.form-actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-top: 30px;
}

.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.btn:active {
    transform: translateY(0);
}

.btn-primary {
    background: #2d3748;
    color: white;
}

.btn-primary:hover {
    background: #1a202c;
}

.btn-secondary {
    background: #ffffff;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn-secondary:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

/* Modern Button Styles */
.btn-modern {
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.btn-add {
    background: #2d3748;
    color: #ffffff;
}

.btn-add:hover {
    background: #1a202c;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-clear {
    background: #ffffff;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn-clear:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.05);
}

.btn-delete-row {
    background: #ffffff;
    color: #e53e3e;
    border: 1px solid #feb2b2;
    padding: 4px 10px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    transition: all 0.2s ease;
}

.btn-delete-row:hover {
    background: #fff5f5;
    border-color: #fc8181;
    transform: scale(1.05);
}

.date-cell {
    font-weight: 600;
    background: #fafbfc !important;
    color: #2d3748;
}

/* DTR Table Cell Highlighting */
.calc-highlight {
    background-color: #d4edda !important; /* Light green for calculations */
}

.deduct-highlight {
    background-color: #ffe6e6 !important; /* Light pink for deductions */
}

.pay-highlight {
    background-color: #ccffcc !important; /* Green for OT pay */
}

.net-deduct-highlight {
    background-color: #fff3cd !important; /* Light yellow for net deductions */
    font-weight: 600;
}

.centered {
    text-align: center !important;
}

.actions-cell {
    text-align: center;
}

/* Rate Settings */
.rate-settings {
    background: #ffffff;
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.section-title {
    margin: 0 0 15px 0;
    font-size: 14px;
    font-weight: 600;
    color: #2d3748;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.rate-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.rate-item label {
    display: block;
    font-size: 12px;
    color: #718096;
    margin-bottom: 6px;
    font-weight: 500;
}

.rate-item input {
    width: 100%;
}

/* DTR Section */
.dtr-section {
    background: #ffffff;
    padding: 25px;
    margin-top: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

/* TB5-style Header */
.dtr-header-tb5 {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #1F4E79;
    color: white;
    padding: 12px 20px;
    border-radius: 6px 6px 0 0;
    margin: -25px -25px 0 -25px;
}

.tb5-title {
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 1px;
}

.tb5-period {
    font-style: italic;
    color: #99CCFF;
    font-size: 13px;
}

.btn-full-view {
    background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.btn-full-view:hover {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.btn-full-view i {
    font-size: 12px;
}

.tb5-info-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 20px;
    background: #FFFF00;
    margin: 0 -25px;
}

.tb5-employee-info {
    display: flex;
    align-items: center;
    gap: 10px;
}

.tb5-label {
    font-weight: 700;
    font-size: 12px;
}

.tb5-value {
    font-weight: 700;
    color: #FF0000;
    font-size: 14px;
}

.tb5-company {
    font-size: 10px;
    font-weight: 600;
    color: #333;
}

.tb5-rate-row {
    display: flex;
    gap: 20px;
    padding: 10px 20px;
    background: #f8f9fa;
    margin: 0 -25px 20px -25px;
    border-bottom: 2px solid #e2e8f0;
}

.tb5-rate-item {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.tb5-rate-label {
    font-size: 9px;
    color: #666;
    text-transform: uppercase;
}

.tb5-rate-value {
    font-size: 13px;
    font-weight: 600;
    color: #333;
}

.tb5-rate-value.tb5-basic {
    color: #FF0000;
    font-weight: 700;
    background: #00FF00;
    padding: 2px 8px;
    border-radius: 3px;
}

.section-main-title {
    font-size: 18px;
    font-weight: 700;
    color: #2d3748;
    margin: 0 0 20px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid #e2e8f0;
}

.dtr-controls {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.controls-left {
    display: flex;
    gap: 10px;
}

/* DTR Import Section */
.dtr-import-section {
    margin-bottom: 25px;
}

.import-card {
    background: #f8fafc;
    border: 2px dashed #cbd5e0;
    border-radius: 8px;
    overflow: hidden;
    transition: all 0.3s ease;
}

.import-card:hover {
    border-color: #4299e1;
}

.import-header {
    background: #2d3748;
    color: #ffffff;
    padding: 12px 20px;
    font-weight: 600;
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-header i {
    color: #48bb78;
    font-size: 18px;
}

.import-body {
    padding: 20px;
}

.import-dropzone {
    background: #ffffff;
    border: 2px dashed #e2e8f0;
    border-radius: 8px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s ease;
}

.import-dropzone:hover,
.import-dropzone.dragover {
    border-color: #4299e1;
    background: #ebf8ff;
}

.import-dropzone i {
    font-size: 48px;
    color: #a0aec0;
    margin-bottom: 15px;
}

.import-dropzone.dragover i {
    color: #4299e1;
}

.import-dropzone p {
    margin: 0 0 8px 0;
    color: #4a5568;
    font-size: 14px;
    font-weight: 500;
}

.import-hint {
    font-size: 12px;
    color: #a0aec0;
}

.import-actions {
    display: flex;
    gap: 10px;
    margin-top: 15px;
    justify-content: center;
}

.btn-import {
    background: #48bb78;
    color: #ffffff;
}

.btn-import:hover:not(:disabled) {
    background: #38a169;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.btn-import:disabled {
    background: #cbd5e0;
    cursor: not-allowed;
}

.btn-template {
    background: #ffffff;
    color: #4a5568;
    border: 1px solid #e2e8f0;
}

.btn-template:hover {
    background: #f7fafc;
    border-color: #cbd5e0;
}

.btn-load {
    background: #4299e1;
    color: #ffffff;
}

.btn-load:hover {
    background: #3182ce;
    transform: translateY(-1px);
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.import-status {
    margin-top: 15px;
    padding: 12px 15px;
    border-radius: 6px;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.import-status.success {
    background: #c6f6d5;
    color: #22543d;
    border: 1px solid #9ae6b4;
}

.import-status.error {
    background: #fed7d7;
    color: #742a2a;
    border: 1px solid #feb2b2;
}

.import-status.loading {
    background: #bee3f8;
    color: #2a4365;
    border: 1px solid #90cdf4;
}

.existing-dtr-notice {
    margin-top: 15px;
    padding: 12px 15px;
    background: #fefcbf;
    border: 1px solid #f6e05e;
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    color: #744210;
}

.existing-dtr-notice i {
    color: #d69e2e;
    font-size: 16px;
}

.file-selected {
    margin-top: 10px;
    padding: 10px 15px;
    background: #ebf8ff;
    border: 1px solid #90cdf4;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    color: #2a4365;
}

.file-selected i {
    color: #4299e1;
    margin-right: 8px;
}

.file-selected .remove-file {
    cursor: pointer;
    color: #e53e3e;
    font-size: 14px;
}

.file-selected .remove-file:hover {
    color: #c53030;
}

.dtr-table-wrapper {
    overflow-x: auto;
}

/* Deduction Calculation Section */
.deduction-calc-section {
    background: #ffffff;
    padding: 25px;
    margin-top: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.rate-calc-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
    font-size: 11px;
}

.rate-calc-table th,
.rate-calc-table td {
    border: 1px solid #2d3748;
    padding: 8px;
    text-align: center;
}

.rate-calc-table thead th {
    background: #f8f9fa;
    font-weight: 600;
    color: #2d3748;
}

.rate-header-row th {
    font-size: 13px;
    font-weight: bold;
}

.rate-label-row th,
.rate-type-row th {
    font-size: 10px;
    line-height: 1.2;
    padding: 6px 4px;
}

.rate-calc-table tbody td {
    background: white;
    font-size: 11px;
    padding: 6px;
}

.rate-calc-table tbody tr:first-child td {
    font-weight: 600;
    background: #f8f9fa;
}

/* Government Deductions Section */
.deductions-section {
    background: #ffffff;
    padding: 20px;
    margin-top: 30px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.gov-deductions-table {
    width: 100%;
    font-size: 13px;
}

.gov-deductions-table tr {
    border-bottom: 1px solid #f1f3f5;
}

.gov-deductions-table tr:last-child {
    border-bottom: none;
}

.gov-deductions-table td {
    padding: 12px 0;
    color: #4a5568;
}

.gov-deductions-table td:first-child {
    width: 60%;
}

.gov-deductions-table td:last-child {
    width: 40%;
    text-align: right;
}

.gov-total-row {
    border-top: 2px solid #2d3748 !important;
    font-weight: 700 !important;
}

.gov-total-row td {
    padding-top: 15px !important;
    font-size: 14px !important;
    color: #2d3748 !important;
}

.gov-total-row td:last-child {
    font-size: 16px !important;
    font-weight: 700 !important;
}

/* Final Pay Section */
.final-pay-section {
    margin-top: 30px;
    background: #ffffff;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #e2e8f0;
}

.final-pay-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    border-bottom: 1px solid #f1f3f5;
}

.final-pay-item:last-child {
    border-bottom: none;
}

.final-pay-item span {
    font-size: 14px;
    color: #4a5568;
    font-weight: 500;
}

.final-pay-item strong {
    font-size: 18px;
    color: #2d3748;
}

.net-pay-highlight {
    background: #2d3748;
    padding: 20px !important;
}

.net-pay-highlight span {
    color: #ffffff;
    font-size: 16px;
    font-weight: 700;
    letter-spacing: 1px;
}

.net-pay-highlight strong {
    color: #ffffff;
    font-size: 24px;
    font-weight: 700;
}
.row-counter {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: #f7fafc;
    border-radius: 6px;
    border: 1px solid #e2e8f0;
}

.counter-label {
    font-size: 13px;
    color: #718096;
    font-weight: 500;
}

.counter-value {
    font-size: 15px;
    font-weight: 700;
    color: #2d3748;
    min-width: 30px;
    text-align: center;
    padding: 2px 10px;
    background: #ffffff;
    border-radius: 4px;
    border: 1px solid #e2e8f0;
}

.dtr-row-actions {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 5px;
}

.dtr-date-input {
    width: 100%;
    padding: 6px 8px;
    border: 1px solid #e2e8f0;
    border-radius: 4px;
    font-size: 12px;
    text-align: center;
    background: #ffffff;
    transition: all 0.2s ease;
}

.dtr-date-input:focus {
    outline: none;
    border-color: #4299e1;
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
}

@media print {
    .btn-delete-row,
    #btn_add_row,
    #btn_clear_all,
    .row-counter,
    .th-action {
        display: none !important;
    }
    
    .card, .form-actions, .page-header {
        display: none !important;
    }
    
    .payslip-form {
        box-shadow: none;
        padding: 0;
    }
    
    .table-input {
        border: none;
        background: transparent;
    }
}

/* ============ TB5 Rate Input Section ============ */
.tb5-rate-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    padding: 15px 20px;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    margin: 0 -25px 20px -25px;
    border-bottom: 2px solid #dee2e6;
    align-items: flex-end;
}

.tb5-rate-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    min-width: 80px;
}

.tb5-rate-item.rate-input-item {
    min-width: 150px;
}

.tb5-rate-label {
    font-size: 9px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 4px;
    letter-spacing: 0.5px;
}

.rate-input-wrapper {
    display: flex;
    align-items: center;
    background: #00FF00;
    border-radius: 4px;
    padding: 2px 8px;
    border: 2px solid #28a745;
}

.rate-input-wrapper .peso-sign {
    font-weight: 700;
    color: #d63384;
    font-size: 14px;
}

.rate-input {
    width: 90px;
    border: none;
    background: transparent;
    font-weight: 700;
    color: #d63384;
    font-size: 14px;
    text-align: center;
}

.rate-input:focus {
    outline: none;
}

.rate-input.tb5-basic-input {
    width: 90px;
    border: none;
    background: transparent;
    font-weight: 700;
    color: #d63384;
    font-size: 14px;
    text-align: center;
}

.rate-input.tb5-basic-input:focus {
    outline: none;
}

.computed-rate {
    font-size: 13px;
    font-weight: 600;
    color: #333;
    background: #fff;
    padding: 4px 10px;
    border-radius: 4px;
    border: 1px solid #ddd;
}

.computed-rate.ot-rate {
    background: #e8f5e9;
    border-color: #81c784;
    color: #2e7d32;
}

.time-input {
    padding: 6px 10px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    width: 90px;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

.time-input:hover:not(:disabled) {
    border-color: #cbd5e0;
    background: linear-gradient(to bottom, #ffffff 0%, #edf2f7 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

.time-input:focus {
    outline: none;
    border-color: #4299e1;
    background: linear-gradient(to bottom, #ffffff 0%, #ebf8ff 100%);
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15), 0 2px 6px rgba(66, 153, 225, 0.2);
    transform: translateY(-1px);
}

/* Time Input Pulse Animation */
@keyframes timePulse {
    0%, 100% { box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15); }
    50% { box-shadow: 0 0 0 5px rgba(66, 153, 225, 0.25); }
}

input.time24:focus {
    animation: timePulse 2s ease-in-out infinite;
}

.time-input:focus {
    animation: timePulse 2s ease-in-out infinite;
}

/* 24-hour time input styling - Modern Design */
input.time24 {
    width: 70px;
    font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
    padding: 6px 10px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    background: linear-gradient(to bottom, #ffffff 0%, #f8fafc 100%);
    color: #2d3748;
    letter-spacing: 1px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
}

input.time24:hover:not(:disabled) {
    border-color: #cbd5e0;
    background: linear-gradient(to bottom, #ffffff 0%, #edf2f7 100%);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    transform: translateY(-1px);
}

input.time24:focus {
    outline: none;
    border-color: #4299e1;
    background: linear-gradient(to bottom, #ffffff 0%, #ebf8ff 100%);
    box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.15), 0 2px 6px rgba(66, 153, 225, 0.2);
    transform: translateY(-1px);
}

input.time24::placeholder {
    color: #a0aec0;
    font-size: 11px;
    font-weight: 400;
    letter-spacing: 0.5px;
}

input.time24:disabled {
    background: #f0f0f0;
    border-color: #e2e8f0;
    cursor: not-allowed;
    opacity: 0.6;
    box-shadow: none;
    transform: none;
}

/* Valid time input state - subtle success indicator */
input.time24:valid:not(:placeholder-shown):not(:focus) {
    border-color: #48bb78;
    background: linear-gradient(to bottom, #ffffff 0%, #f0fff4 100%);
    box-shadow: 0 1px 3px rgba(72, 187, 120, 0.1);
}

/* ============ Auto Deductions Summary ============ */
.auto-deductions-section {
    background: #fff;
    padding: 20px;
    margin-top: 20px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
}

.auto-deductions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 15px;
}

.deduct-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    border-radius: 8px;
    border: 1px solid #e2e8f0;
    background: #fff;
    transition: all 0.2s ease;
}

.deduct-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.deduct-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 8px;
    font-size: 18px;
}

.absent-card .deduct-icon { background: #ffebee; color: #c62828; }
.late-card .deduct-icon { background: #fff3e0; color: #ef6c00; }
.undertime-card .deduct-icon { background: #fff8e1; color: #f9a825; }
.halfday-card .deduct-icon { background: #e3f2fd; color: #1565c0; }
.ot-card .deduct-icon { background: #e8f5e9; color: #2e7d32; }
.total-card .deduct-icon { background: #f3e5f5; color: #7b1fa2; }

.deduct-info {
    flex: 1;
    display: flex;
    flex-direction: column;
}

.deduct-label {
    font-size: 11px;
    color: #666;
    text-transform: uppercase;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.deduct-count {
    font-size: 18px;
    font-weight: 700;
    color: #333;
}

.deduct-rate {
    font-size: 10px;
    color: #888;
}

.deduct-formula {
    font-size: 10px;
    color: #888;
    font-style: italic;
}

.deduct-amount {
    font-size: 14px;
    font-weight: 700;
    color: #c62828;
    white-space: nowrap;
}

.deduct-amount.positive {
    color: #2e7d32;
}

.deduct-amount.total {
    color: #7b1fa2;
    font-size: 16px;
}

.total-card {
    background: linear-gradient(135deg, #f3e5f5 0%, #e1bee7 100%);
    border-color: #ce93d8;
}

/* ============ Payroll Summary Section ============ */
.payroll-summary-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin: 20px 0;
}

.summary-column {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    border: 1px solid #e2e8f0;
}

.earnings-column {
    border-left: 4px solid #28a745;
}

.deductions-column {
    border-left: 4px solid #dc3545;
}

.column-title {
    font-size: 14px;
    font-weight: 700;
    margin: 0 0 15px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.earnings-column .column-title { color: #28a745; }
.deductions-column .column-title { color: #dc3545; }

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px dashed #eee;
    font-size: 13px;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row.total-row {
    margin-top: 10px;
    padding-top: 12px;
    border-top: 2px solid #e2e8f0;
    border-bottom: none;
}

.total-amount {
    font-size: 16px;
}

.positive { color: #28a745; }
.negative { color: #dc3545; }

/* Net Pay Box */
.net-pay-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #1e3a5f 0%, #2c5282 100%);
    padding: 25px 30px;
    border-radius: 12px;
    margin-top: 20px;
    box-shadow: 0 4px 20px rgba(30, 58, 95, 0.3);
}

.net-pay-label {
    display: flex;
    align-items: center;
    gap: 12px;
    color: rgba(255,255,255,0.9);
    font-size: 18px;
    font-weight: 600;
}

.net-pay-label i {
    font-size: 28px;
    color: #ffd700;
}

.net-pay-amount {
    font-size: 36px;
    font-weight: 800;
    color: #ffd700;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

@media (max-width: 768px) {
    .payroll-summary-grid {
        grid-template-columns: 1fr;
    }
    
    .auto-deductions-grid {
        grid-template-columns: 1fr;
    }
    
    .tb5-rate-row {
        justify-content: center;
    }
    
    .net-pay-box {
        flex-direction: column;
        text-align: center;
        gap: 10px;
    }
    
    .net-pay-amount {
        font-size: 28px;
    }
}

/* Success button style */
.btn-modern.btn-success-lg {
    background: linear-gradient(135deg, #38a169 0%, #2f855a 100%);
    color: white;
    padding: 12px 24px;
    font-size: 16px;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern.btn-success-lg:hover {
    background: linear-gradient(135deg, #2f855a 0%, #276749 100%);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(56, 161, 105, 0.3);
}

.btn-modern.btn-info {
    background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
    color: white;
    padding: 10px 20px;
    font-size: 14px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-modern.btn-info:hover {
    background: linear-gradient(135deg, #3182ce 0%, #2b6cb0 100%);
    transform: translateY(-1px);
}

/* Employee Cards Section */
.employee-cards-section {
    margin-top: 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.07);
}

.employee-cards-section .card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px 12px 0 0;
    color: white;
}

.employee-cards-section .card-header h3 {
    margin: 0;
    font-size: 18px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.employee-cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
    padding: 20px;
}

.employee-card {
    background: linear-gradient(145deg, #ffffff 0%, #f7fafc 100%);
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.employee-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0,0,0,0.12);
    border-color: #667eea;
}

.employee-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
}

.employee-card-header {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 15px;
}

.employee-avatar {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    font-weight: 600;
}

.employee-card-name {
    font-size: 16px;
    font-weight: 600;
    color: #2d3748;
    margin-bottom: 4px;
}

.employee-card-code {
    font-size: 12px;
    color: #718096;
    font-family: monospace;
}

.employee-card-details {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    font-size: 13px;
}

.employee-card-details .detail-item {
    display: flex;
    flex-direction: column;
}

.employee-card-details .detail-label {
    color: #a0aec0;
    font-size: 11px;
    text-transform: uppercase;
}

.employee-card-details .detail-value {
    color: #4a5568;
    font-weight: 500;
}

.employee-card-footer {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dtr-count-badge {
    background: #edf2f7;
    color: #4a5568;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.view-dtr-link {
    color: #667eea;
    font-size: 13px;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}

/* Full View Modal */
.modal-fullview {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0,0,0,0.7);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal-fullview-content {
    background: white;
    border-radius: 16px;
    width: 100%;
    max-width: 95vw;
    max-height: 95vh;
    display: flex;
    flex-direction: column;
    box-shadow: 0 25px 50px rgba(0,0,0,0.3);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-fullview-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 30px;
    background: linear-gradient(135deg, #2d3748 0%, #1a202c 100%);
    border-radius: 16px 16px 0 0;
    color: white;
}

.modal-fullview-header h2 {
    margin: 0;
    font-size: 22px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.modal-close-btn {
    background: rgba(255,255,255,0.1);
    border: none;
    color: white;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 18px;
    transition: all 0.3s ease;
}

.modal-close-btn:hover {
    background: rgba(255,255,255,0.2);
    transform: rotate(90deg);
}

.modal-fullview-body {
    flex: 1;
    overflow: auto;
    padding: 30px;
}

.modal-employee-info {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 25px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 12px;
}

.modal-employee-info .info-item {
    flex: 1;
    min-width: 150px;
    font-size: 14px;
}

.modal-employee-info .info-item strong {
    color: #718096;
}

.modal-table-container {
    overflow: auto;
    max-height: calc(95vh - 300px);
}

.modal-table-container .tb5-dtr-table {
    font-size: 13px;
}

.modal-fullview-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding: 20px 30px;
    border-top: 1px solid #e2e8f0;
    background: #f8fafc;
    border-radius: 0 0 16px 16px;
}

/* Month Selector */
.month-selector {
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.month-selector label {
    font-weight: 600;
    color: #4a5568;
}

.month-selector select {
    padding: 10px 20px;
    border: 2px solid #e2e8f0;
    border-radius: 8px;
    font-size: 14px;
    min-width: 200px;
    cursor: pointer;
}

.month-selector select:focus {
    outline: none;
    border-color: #667eea;
}

/* Employee DTR Content */
.employee-dtr-content {
    min-height: 300px;
}

.no-cards-message {
    text-align: center;
    padding: 60px 20px;
    color: #a0aec0;
}

.no-cards-message i {
    font-size: 48px;
    margin-bottom: 15px;
    display: block;
}

.loading-cards {
    text-align: center;
    padding: 40px;
    color: #718096;
}

.loading-cards i {
    font-size: 32px;
    margin-bottom: 10px;
    display: block;
}

/* Date Cell Display */
.date-cell {
    text-align: center;
    padding: 8px;
}

.date-display {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}

.date-day {
    font-size: 16px;
    font-weight: bold;
    line-height: 1;
    color: #2d3748;
}

.date-month {
    font-size: 9px;
    color: #718096;
    margin-top: 2px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* ============ Old Custom 24-Hour Time Picker - Disabled ============ 
   Now using HTML5 native type="time" inputs for better compatibility 
*/
/*
input.time24 {
    width: 70px;
    padding: 4px 6px;
    border: 1.5px solid #93c5fd;
    border-radius: 5px;
    font-size: 12px;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    text-align: center;
    letter-spacing: 1.5px;
    background: #f0f7ff;
    color: #1e40af;
    cursor: pointer;
    transition: border-color 0.2s, box-shadow 0.2s, background 0.2s;
}

input.time24:hover {
    border-color: #3b82f6;
    background: #e0efff;
}

input.time24:focus {
    border-color: #3b82f6;
    outline: none;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(59,130,246,0.18);
    color: #1e3a8a;
}

input.time24::placeholder {
    color: #60a5fa;
    letter-spacing: 1.5px;
    font-style: italic;
}

input.time24:disabled {
    background: #f0f0f0 !important;
    border-color: #d1d5db !important;
    color: #9ca3af !important;
    cursor: not-allowed !important;
    opacity: 0.6;
}
*/

/* Picker Overlay */
.tp-overlay {
    position: fixed;
    z-index: 99999;
    display: none;
}

.tp-overlay.open {
    display: block;
}

/* Picker Container */
.tp-picker {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 12px 40px rgba(0,0,0,0.18), 0 2px 8px rgba(0,0,0,0.08);
    width: 232px;
    overflow: hidden;
    animation: tpSlideIn 0.15s ease-out;
    border: 1px solid #e2e8f0;
}

@keyframes tpSlideIn {
    from { opacity: 0; transform: translateY(-6px) scale(0.97); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

/* Header with live preview */
.tp-header {
    background: linear-gradient(135deg, #3b82f6, #2563eb);
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 2px;
}

.tp-display {
    font-size: 28px;
    font-weight: 700;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    color: rgba(255,255,255,0.5);
    letter-spacing: 2px;
    cursor: pointer;
    padding: 2px 4px;
    border-radius: 6px;
    transition: all 0.2s;
    user-select: none;
}

.tp-display.active {
    color: #fff;
    background: rgba(255,255,255,0.15);
}

.tp-display:hover:not(.active) {
    color: rgba(255,255,255,0.75);
}

.tp-display-sep {
    font-size: 28px;
    font-weight: 700;
    color: rgba(255,255,255,0.5);
    margin: 0 1px;
    user-select: none;
}

/* Tab row */
.tp-tabs {
    display: flex;
    border-bottom: 2px solid #f1f5f9;
}

.tp-tab {
    flex: 1;
    text-align: center;
    padding: 8px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #94a3b8;
    cursor: pointer;
    transition: all 0.2s;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    user-select: none;
}

.tp-tab.active {
    color: #3b82f6;
    border-bottom-color: #3b82f6;
}

.tp-tab:hover:not(.active) {
    color: #64748b;
    background: #f8fafc;
}

/* Grid panels */
.tp-panel {
    display: none;
    padding: 8px;
}

.tp-panel.active {
    display: block;
}

/* Hour grid: 6 rows × 4 cols */
.tp-grid-hours {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 3px;
}

/* Minute grid: 4 rows × 3 cols */
.tp-grid-minutes {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 3px;
}

.tp-cell {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 32px;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    cursor: pointer;
    transition: all 0.12s;
    color: #334155;
    background: #f8fafc;
    user-select: none;
}

.tp-cell:hover {
    background: #dbeafe;
    color: #1e40af;
}

.tp-cell.selected {
    background: #3b82f6;
    color: #fff;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(59,130,246,0.35);
}

.tp-cell.highlight {
    background: #eff6ff;
    border: 1px solid #bfdbfe;
}

/* Minute fine-tune row */
.tp-fine-tune {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 6px 8px 2px;
}

.tp-fine-btn {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 1px solid #e2e8f0;
    background: #f8fafc;
    color: #475569;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.15s;
    user-select: none;
}

.tp-fine-btn:hover {
    background: #3b82f6;
    color: #fff;
    border-color: #3b82f6;
}

.tp-fine-label {
    font-size: 11px;
    color: #94a3b8;
    font-weight: 500;
}

/* Footer actions */
.tp-footer {
    display: flex;
    justify-content: flex-end;
    gap: 6px;
    padding: 6px 10px 10px;
}

.tp-btn {
    padding: 5px 14px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    border: none;
    transition: all 0.15s;
    font-family: 'Inter', 'Segoe UI', sans-serif;
}

.tp-btn-clear {
    background: #f1f5f9;
    color: #64748b;
}

.tp-btn-clear:hover {
    background: #e2e8f0;
    color: #334155;
}

.tp-btn-ok {
    background: #3b82f6;
    color: #fff;
}

.tp-btn-ok:hover {
    background: #2563eb;
}

</style>

<script>
// ================================================================
// PAYROLL MODE TOGGLE - Import Excel vs Manual Entry
// ================================================================
let currentPayrollMode = 'import'; // 'import' or 'manual'

function switchPayrollMode(mode) {
    if (mode === currentPayrollMode) return;
    currentPayrollMode = mode;
    
    const importSection = document.getElementById('import_mode_section');
    const manualSection = document.getElementById('manual_mode_section');
    const importBtn = document.getElementById('btn_mode_import');
    const manualBtn = document.getElementById('btn_mode_manual');
    const payslipForm = document.getElementById('payslip_form');
    
    if (mode === 'import') {
        // Show import, hide manual
        importSection.style.display = 'block';
        manualSection.style.display = 'none';
        importBtn.classList.add('active');
        manualBtn.classList.remove('active');
        
        // Reset manual selections
        const empSelect = document.getElementById('employee_select');
        if (empSelect) empSelect.value = '';
        
        // Hide DTR calculator unless import data exists
        const importedSection = document.getElementById('imported_data_section');
        if (!importedSection || importedSection.style.display === 'none') {
            payslipForm.style.display = 'none';
        }
    } else {
        // Show manual, hide import
        importSection.style.display = 'none';
        manualSection.style.display = 'block';
        importBtn.classList.remove('active');
        manualBtn.classList.add('active');
        
        // Hide DTR calculator until employee selected
        payslipForm.style.display = 'none';
        
        // Clear any imported data display
        const importedSection = document.getElementById('imported_data_section');
        if (importedSection) importedSection.style.display = 'none';
        clearImportedData();
    }
}

// ================================================================
// CUSTOM 24-HOUR TIME PICKER - Step-based: pick hour → pick minute
// ================================================================
(function() {
    'use strict';

    let activeInput = null;
    let pickerHour = null;
    let pickerMinute = null;
    let currentStep = 'hour'; // 'hour' or 'minute'

    // Build picker DOM
    const overlay = document.createElement('div');
    overlay.className = 'tp-overlay';
    overlay.innerHTML = `
        <div class="tp-picker">
            <div class="tp-header">
                <span class="tp-display active" id="tp-disp-h" title="Click to change hour">--</span>
                <span class="tp-display-sep">:</span>
                <span class="tp-display" id="tp-disp-m" title="Click to change minute">--</span>
            </div>
            <div class="tp-tabs">
                <div class="tp-tab active" data-step="hour">Hour</div>
                <div class="tp-tab" data-step="minute">Minute</div>
            </div>
            <div class="tp-panel active" id="tp-panel-hour">
                <div class="tp-grid-hours" id="tp-grid-h"></div>
            </div>
            <div class="tp-panel" id="tp-panel-minute">
                <div class="tp-grid-minutes" id="tp-grid-m"></div>
                <div class="tp-fine-tune">
                    <div class="tp-fine-btn" id="tp-min-down" title="−1 minute">−</div>
                    <span class="tp-fine-label">fine‑tune</span>
                    <div class="tp-fine-btn" id="tp-min-up" title="+1 minute">+</div>
                </div>
            </div>
            <div class="tp-footer">
                <button type="button" class="tp-btn tp-btn-clear" id="tp-clear">Clear</button>
                <button type="button" class="tp-btn tp-btn-ok" id="tp-ok">OK</button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);

    const picker   = overlay.querySelector('.tp-picker');
    const dispH    = overlay.querySelector('#tp-disp-h');
    const dispM    = overlay.querySelector('#tp-disp-m');
    const gridH    = overlay.querySelector('#tp-grid-h');
    const gridM    = overlay.querySelector('#tp-grid-m');
    const panelH   = overlay.querySelector('#tp-panel-hour');
    const panelM   = overlay.querySelector('#tp-panel-minute');
    const tabs     = overlay.querySelectorAll('.tp-tab');
    const btnClear = overlay.querySelector('#tp-clear');
    const btnOk    = overlay.querySelector('#tp-ok');
    const btnMinUp = overlay.querySelector('#tp-min-up');
    const btnMinDn = overlay.querySelector('#tp-min-down');

    // Build hour grid (0-23)
    for (let h = 0; h < 24; h++) {
        const cell = document.createElement('div');
        cell.className = 'tp-cell';
        cell.textContent = String(h).padStart(2, '0');
        cell.dataset.val = h;
        cell.addEventListener('click', function(e) {
            e.stopPropagation();
            pickHour(parseInt(this.dataset.val));
        });
        gridH.appendChild(cell);
    }

    // Build minute grid (0, 5, 10 ... 55)
    for (let m = 0; m < 60; m += 5) {
        const cell = document.createElement('div');
        cell.className = 'tp-cell';
        cell.textContent = String(m).padStart(2, '0');
        cell.dataset.val = m;
        cell.addEventListener('click', function(e) {
            e.stopPropagation();
            pickMinute(parseInt(this.dataset.val));
        });
        gridM.appendChild(cell);
    }

    // ---- Interactions ----

    function pickHour(h) {
        pickerHour = h;
        updateDisplay();
        highlightGrid();
        // Auto-advance to minute step
        switchStep('minute');
    }

    function pickMinute(m) {
        pickerMinute = m;
        updateDisplay();
        highlightGrid();
    }

    function switchStep(step) {
        currentStep = step;
        tabs.forEach(t => t.classList.toggle('active', t.dataset.step === step));
        panelH.classList.toggle('active', step === 'hour');
        panelM.classList.toggle('active', step === 'minute');
        dispH.classList.toggle('active', step === 'hour');
        dispM.classList.toggle('active', step === 'minute');
    }

    function updateDisplay() {
        dispH.textContent = pickerHour !== null ? String(pickerHour).padStart(2, '0') : '--';
        dispM.textContent = pickerMinute !== null ? String(pickerMinute).padStart(2, '0') : '--';
    }

    function highlightGrid() {
        gridH.querySelectorAll('.tp-cell').forEach(c => {
            c.classList.toggle('selected', parseInt(c.dataset.val) === pickerHour);
        });
        gridM.querySelectorAll('.tp-cell').forEach(c => {
            // Highlight exact 5-min match as selected; highlight nearest for non-5-min values
            const val = parseInt(c.dataset.val);
            if (pickerMinute !== null) {
                c.classList.toggle('selected', val === pickerMinute);
                // If minute is between two 5-min marks, show nearest as highlight
                c.classList.toggle('highlight', !c.classList.contains('selected') && 
                    pickerMinute % 5 !== 0 && Math.abs(val - pickerMinute) <= 2);
            } else {
                c.classList.remove('selected', 'highlight');
            }
        });
    }

    // Tab clicks
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.stopPropagation();
            switchStep(this.dataset.step);
        });
    });

    // Header display clicks (jump to that step)
    dispH.addEventListener('click', function(e) { e.stopPropagation(); switchStep('hour'); });
    dispM.addEventListener('click', function(e) { e.stopPropagation(); switchStep('minute'); });

    // Fine-tune ±1 minute
    btnMinUp.addEventListener('click', function(e) {
        e.stopPropagation();
        if (pickerMinute === null) pickerMinute = 0;
        pickerMinute = (pickerMinute + 1) % 60;
        updateDisplay();
        highlightGrid();
    });

    btnMinDn.addEventListener('click', function(e) {
        e.stopPropagation();
        if (pickerMinute === null) pickerMinute = 0;
        pickerMinute = (pickerMinute - 1 + 60) % 60;
        updateDisplay();
        highlightGrid();
    });

    // OK button → apply and close
    btnOk.addEventListener('click', function(e) {
        e.stopPropagation();
        applyAndClose();
    });

    // Clear button
    btnClear.addEventListener('click', function(e) {
        e.stopPropagation();
        if (activeInput) {
            activeInput.value = '';
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closePicker();
    });

    function applyAndClose() {
        if (activeInput && pickerHour !== null) {
            if (pickerMinute === null) pickerMinute = 0;
            activeInput.value = String(pickerHour).padStart(2, '0') + ':' + String(pickerMinute).padStart(2, '0');
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
        }
        closePicker();
    }

    // ---- Open / Close ----

    function openPicker(input) {
        if (activeInput === input && overlay.classList.contains('open')) {
            closePicker();
            return;
        }

        activeInput = input;
        pickerHour = null;
        pickerMinute = null;

        // Parse existing value
        const val = input.value.trim();
        if (val && /^\d{1,2}:\d{2}$/.test(val)) {
            const p = val.split(':');
            pickerHour = parseInt(p[0]);
            pickerMinute = parseInt(p[1]);
        }

        updateDisplay();
        highlightGrid();
        switchStep('hour');

        // Position near input
        const rect = input.getBoundingClientRect();
        const pickerW = 232;
        const pickerH = 360;

        let top = rect.bottom + 4;
        let left = rect.left;

        // Flip up if not enough space below
        if (top + pickerH > window.innerHeight - 10) {
            top = rect.top - pickerH - 4;
        }
        // Shift left if overflowing right
        if (left + pickerW > window.innerWidth - 10) {
            left = window.innerWidth - pickerW - 10;
        }
        // Don't go off-screen left
        if (left < 5) left = 5;

        overlay.style.top = top + 'px';
        overlay.style.left = left + 'px';
        overlay.classList.add('open');
    }

    function closePicker() {
        overlay.classList.remove('open');
        activeInput = null;
    }

    // ---- Input handling (direct typing) ----

    function handleTime24Input(e) {
        const input = e.target;
        const cursorPos = input.selectionStart;
        const prevVal = input.value;
        
        // Remove all non-digit characters except colon
        let rawDigits = input.value.replace(/[^\d]/g, '');
        if (rawDigits.length > 4) rawDigits = rawDigits.substring(0, 4);
        
        // Auto-insert colon after 2 digits
        let formatted = '';
        if (rawDigits.length <= 2) {
            formatted = rawDigits;
            // Auto-add colon when exactly 2 digits are entered
            if (rawDigits.length === 2 && !prevVal.includes(':')) {
                formatted = rawDigits + ':';
            }
        } else {
            // 3 or 4 digits: insert colon after first 2
            formatted = rawDigits.substring(0, 2) + ':' + rawDigits.substring(2);
        }
        
        input.value = formatted;
        
        // Maintain cursor position intelligently
        let newCursorPos = cursorPos;
        // If colon was just auto-added, move cursor after it
        if (formatted.length > prevVal.length && formatted.includes(':') && !prevVal.includes(':')) {
            newCursorPos = formatted.indexOf(':') + 1;
        } else if (cursorPos <= formatted.length) {
            // Adjust cursor if it would be on the colon
            if (formatted[cursorPos - 1] === ':' && formatted[cursorPos]) {
                newCursorPos = cursorPos;
            }
        }
        
        // Set cursor position
        setTimeout(() => {
            input.setSelectionRange(newCursorPos, newCursorPos);
        }, 0);
    }

    function handleTime24Blur(e) {
        const input = e.target;
        let val = input.value.trim();
        if (!val) return;

        const digitsOnly = val.replace(/[^\d]/g, '');
        if (digitsOnly.length === 1) val = '0' + digitsOnly + ':00';
        else if (digitsOnly.length === 2) val = digitsOnly + ':00';
        else if (digitsOnly.length === 3) val = '0' + digitsOnly[0] + ':' + digitsOnly.substring(1);
        else if (digitsOnly.length === 4) val = digitsOnly.substring(0, 2) + ':' + digitsOnly.substring(2);

        const match = val.match(/^(\d{1,2}):(\d{2})$/);
        if (match) {
            let h = parseInt(match[1]), m = parseInt(match[2]);
            if (h > 23) h = 23;
            if (m > 59) m = 59;
            input.value = String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } else {
            input.value = '';
        }
    }

    function handleTime24Keydown(e) {
        const input = e.target;
        if ([8, 9, 27, 13, 46, 37, 38, 39, 40].includes(e.keyCode)) {
            if (e.keyCode === 27) closePicker();
            if (e.keyCode === 13) { handleTime24Blur(e); closePicker(); }
            return;
        }
        if (e.ctrlKey && [65, 67, 86, 88].includes(e.keyCode)) return;
        if (e.key === ':') { if (input.value.includes(':')) e.preventDefault(); return; }
        if (e.key < '0' || e.key > '9') { e.preventDefault(); return; }
        const digits = input.value.replace(/[^\d]/g, '');
        if (digits.length >= 4 && input.selectionStart === input.selectionEnd) {
            e.preventDefault();
        }
    }

    // ---- Event Delegation ----

    // Custom time picker disabled - using HTML5 native time inputs
    // Keeping the picker code for potential future use with text inputs
    /*
    document.addEventListener('click', function(e) {
        const input = e.target.closest('input.time24');
        if (input) {
            openPicker(input);
            return;
        }
        if (!e.target.closest('.tp-picker') && !e.target.closest('.tp-overlay')) {
            closePicker();
        }
    });

    document.addEventListener('input', function(e) {
        if (e.target.matches('input.time24')) handleTime24Input(e);
    });

    document.addEventListener('keydown', function(e) {
        if (e.target.matches('input.time24')) handleTime24Keydown(e);
    });

    document.addEventListener('blur', function(e) {
        if (e.target.matches('input.time24')) handleTime24Blur(e);
    }, true);
    */

    window.initTime24Inputs = function() { /* event delegation handles everything */ };

    document.addEventListener('scroll', function() {
        if (overlay.classList.contains('open')) closePicker();
    }, true);

})();
// ================================================================
// END OF CUSTOM 24-HOUR TIME PICKER
// ================================================================

// Generate 31 DTR rows automatically
function generate31DTRRows() {
    const dtrRows = document.getElementById('dtr_rows');
    dtrRows.innerHTML = '';
    
    // Always generate 31 rows
    for (let day = 1; day <= 31; day++) {
        addDTRRow(day, null);
    }
    
    updateRowCount();
    attachDTRListeners();
}

// Generate DTR rows for the selected payroll period
function generateDTRRows(startDate, endDate) {
    const dtrRows = document.getElementById('dtr_rows');
    dtrRows.innerHTML = '';
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    
    let rowNum = 1;
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        const dateStr = date.toISOString().split('T')[0];
        addDTRRow(rowNum, dateStr);
        rowNum++;
    }
    
    updateRowCount();
    attachDTRListeners();
}

// Add a single DTR row
function addDTRRow(rowNum = null, dateStr = null) {
    const dtrRows = document.getElementById('dtr_rows');
    
    // Get next row number if not provided
    if (rowNum === null) {
        const existingRows = dtrRows.querySelectorAll('tr');
        rowNum = existingRows.length + 1;
    }
    
    // Format date display
    let dateDisplay = '';
    let monthName = '';
    let dayNum = '';
    if (dateStr) {
        const date = new Date(dateStr);
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        monthName = months[date.getMonth()];
        dayNum = date.getDate();
        dateDisplay = `<div class="date-day">${dayNum}</div><div class="date-month">${monthName}</div>`;
    }
    
    const row = document.createElement('tr');
    row.setAttribute('data-row', rowNum);
    row.classList.add('dtr-data-row');
    const _govtVal = rowNum === 1 ? '317.50' : rowNum === 2 ? '125.00' : rowNum === 3 ? '100.00' : '';
    const _remarkVal = rowNum === 1 ? 'SSS' : rowNum === 2 ? 'PHILHEALTH' : rowNum === 3 ? 'PAGIBIG' : '';
    row.innerHTML = `
        <td class="date-cell">
            <input type="hidden" name="dtr_date_${rowNum}" data-row="${rowNum}" value="${dateStr || ''}">
            ${dateStr ? `<div class="date-display">${dateDisplay}</div>` : '<div class="date-display"><div class="date-day">-</div><div class="date-month">-</div></div>'}
        </td>
        <td><input type="text" name="am_in_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="8:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td><input type="text" name="pm_out_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="17:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td class="centered"><input type="checkbox" name="absent_${rowNum}" data-row="${rowNum}" class="dtr-absent"></td>
        <td><input type="text" name="ot_out_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="18:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td><input type="number" name="work_hours_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="0" step="1" title="Total work minutes"></td>
        <td><input type="number" name="late_mins_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="0.00" step="0.01" title="Late hours"></td>
        <td><input type="number" name="undertime_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="0.00" step="0.01" title="Undertime hours"></td>
        <td><input type="number" name="ot_hours_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="0.00" step="0.01" title="OT hours"></td>
        <td><input type="number" name="absent_day_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="0" step="0.5" title="Absent days"></td>
        <td><input type="number" name="absent_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Absent deduction"></td>
        <td><input type="number" name="late_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Late deduction"></td>
        <td><input type="number" name="undertime_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Undertime deduction"></td>
        <td><input type="number" name="halfday_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Halfday deduction (auto-calculated when work hours < 4)"></td>
        <td><input type="number" name="ot_pay_${rowNum}" data-row="${rowNum}" class="dtr-pay pay-highlight" readonly value="0.00" step="0.01" title="OT payment"></td>
        <td><input type="number" name="net_deduct_${rowNum}" data-row="${rowNum}" class="dtr-calc net-deduct-highlight" readonly value="0.00" step="0.01" title="Total deductions minus OT pay"></td>
        <td><input type="number" name="late_min_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="Late in minutes calculation"></td>
        <td><input type="number" name="undertime_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="Undertime calculation"></td>
        <td><input type="number" name="ot_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="OT calculation"></td>
        <td><input type="number" name="govt_${rowNum}" data-row="${rowNum}" class="dtr-manual" value="${_govtVal}" step="0.01" title="Manual Gov't deduction"></td>
        <td><input type="number" name="auto_salary_${rowNum}" data-row="${rowNum}" class="dtr-auto-salary" readonly value="" step="0.01" title="Net Salary"></td>
        <td><input type="text" name="f1_${rowNum}" data-row="${rowNum}" class="dtr-f-input" maxlength="10" title="F1 marker"></td>
        <td><input type="text" name="f2_${rowNum}" data-row="${rowNum}" class="dtr-f-input" maxlength="10" title="F2 marker"></td>
        <td><input type="text" name="remarks_${rowNum}" data-row="${rowNum}" class="dtr-remarks-input" value="${_remarkVal}" placeholder="Remarks" title="Remarks"></td>
        <td class="actions-cell">
            <button type="button" class="btn-delete-row" onclick="deleteDTRRow(this)" title="Delete">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    dtrRows.appendChild(row);
    
    updateRowCount();
    attachDTRListeners();
    
    // Validate the row after creation (for default values)
    validateRowInputs(rowNum);
    
    // Recalculate totals after adding row
    calculateTotals();
}

// Delete a DTR row
function deleteDTRRow(button) {
    if (confirm('Are you sure you want to delete this row?')) {
        const row = button.closest('tr');
        row.remove();
        
        // Renumber remaining rows
        renumberRows();
        updateRowCount();
        calculateTotals();
    }
}

// Renumber rows after deletion
function renumberRows() {
    const rows = document.querySelectorAll('#dtr_rows tr');
    rows.forEach((row, index) => {
        const newRowNum = index + 1;
        row.setAttribute('data-row', newRowNum);
        
        // Update all input names and data attributes
        row.querySelectorAll('input, button').forEach(input => {
            const oldName = input.getAttribute('name');
            if (oldName) {
                const baseName = oldName.replace(/_\d+$/, '');
                input.setAttribute('name', baseName + '_' + newRowNum);
                input.setAttribute('data-row', newRowNum);
            }
        });
    });
}

// Update row count display
function updateRowCount() {
    const rowCount = document.querySelectorAll('#dtr_rows tr').length;
    const countDisplay = document.getElementById('row_count');
    if (countDisplay) {
        countDisplay.textContent = rowCount;
    }
}

// Calculate time difference in hours
function calculateHours(timeIn, timeOut) {
    if (!timeIn || !timeOut) return 0;
    const [inHour, inMin] = timeIn.split(':').map(Number);
    const [outHour, outMin] = timeOut.split(':').map(Number);
    
    let hours = outHour - inHour;
    let minutes = outMin - inMin;
    
    if (minutes < 0) {
        hours -= 1;
        minutes += 60;
    }
    
    return hours + (minutes / 60);
}

// Calculate late minutes using configurable threshold (TB5: K3 = grace end time)
// Late = arrival - grace_end_time, only when arrival > grace_end_time
function calculateLateMinutes(amIn) {
    if (!amIn) return 0;
    const actualStart = parseTimeToMinutes(amIn);
    if (actualStart === null) return 0;
    
    // Read grace/threshold from UI input (default: 07:35 per TB5 K3)
    const thresholdInput = document.getElementById('late_threshold');
    let graceEndMins = 7 * 60 + 35; // Default: 7:35 AM
    if (thresholdInput && thresholdInput.value) {
        const parsed = parseTimeToMinutes(thresholdInput.value);
        if (parsed !== null) graceEndMins = parsed;
    }
    
    if (actualStart > graceEndMins) {
        return actualStart - graceEndMins;
    }
    return 0;
}

// Calculate undertime minutes based on scheduled end time (TB5: L3 = closing time)
// Undertime = scheduled_end - actual_pm_out (only when left early)
function calculateUndertimeMinutes(pmOut) {
    if (!pmOut) return 0;
    const pmOutMins = parseTimeToMinutes(pmOut);
    if (pmOutMins === null) return 0;
    
    // Read end threshold from UI input (default: 17:00 per TB5 L3)
    const endInput = document.getElementById('end_threshold');
    let schedEndMins = 17 * 60; // Default: 5:00 PM
    if (endInput && endInput.value) {
        const parsed = parseTimeToMinutes(endInput.value);
        if (parsed !== null) schedEndMins = parsed;
    }
    
    if (pmOutMins < schedEndMins) {
        return schedEndMins - pmOutMins;
    }
    return 0;
}

// Calculate OT minutes based on scheduled end time (TB5: OT = otOut - closing)
// OT counted only when otOut exceeds closing time
function calculateOTMinutes(otOutTime) {
    if (!otOutTime) return 0;
    const otMins = parseTimeToMinutes(otOutTime);
    if (otMins === null) return 0;
    
    // Read end threshold from UI input (default: 17:00)
    const endInput = document.getElementById('end_threshold');
    let schedEndMins = 17 * 60; // Default: 5:00 PM
    if (endInput && endInput.value) {
        const parsed = parseTimeToMinutes(endInput.value);
        if (parsed !== null) schedEndMins = parsed;
    }
    
    if (otMins > schedEndMins) {
        return otMins - schedEndMins;
    }
    return 0;
}

// Calculate row DTR values
function calculateRowDTR(rowNum) {
    // Use global TB5 rates if available, otherwise use form fields
    let hourlyRate, dailyRate, perMin, otRate;
    
    // Always respect the manually entered OT rate if provided
    const manualOtRate = parseFloat(document.getElementById('ot_rate')?.value) || 0;

    if (window.dtrRates && window.dtrRates.perMin > 0) {
        hourlyRate = window.dtrRates.perHour;
        dailyRate = window.dtrRates.perDay;
        perMin = window.dtrRates.perMin;
        // OT pay = OT rate × OT hours (use input directly)
        otRate = manualOtRate;
    } else {
        hourlyRate = parseFloat(document.getElementById('hourly_rate')?.value) || 0;
        otRate = manualOtRate;
        dailyRate = parseFloat(document.getElementById('daily_rate')?.value) || 0;
        perMin = hourlyRate / 60;
    }
    
    // Check if inputs exist (row might have been deleted)
    const amInInput = document.querySelector(`input[name="am_in_${rowNum}"]`);
    if (!amInInput) {
        console.warn(`calculateRowDTR(${rowNum}): am_in input not found`);
        return;
    }
    
    const amIn = amInInput.value;
    const pmOut = document.querySelector(`input[name="pm_out_${rowNum}"]`)?.value || '';
    const otOut = document.querySelector(`input[name="ot_out_${rowNum}"]`)?.value || '';
    const isAbsent = document.querySelector(`input[name="absent_${rowNum}"]`)?.checked || false;
    
    // Debug logging for first 3 rows with data
    if (amIn && rowNum <= 6) {
        console.log(`calcRow(${rowNum}): amIn='${amIn}' pmOut='${pmOut}' absent=${isAbsent} rates: perDay=${dailyRate} perHour=${hourlyRate} perMin=${perMin}`);
    }
    
    let workHours = 0;
    let lateMinutes = 0;
    let undertimeHours = 0;
    let otHours = 0;
    let absentDay = isAbsent ? 1 : 0;
    
    if (!isAbsent) {
        // Calculate work hours from AM IN to PM OUT
        workHours = calculateHours(amIn, pmOut);
        
        // Debug: log calculated hours for rows with data
        if (amIn && rowNum <= 6) {
            console.log(`calcRow(${rowNum}): workHours=${workHours}`);
        }
        
        // Calculate late minutes (TB5: arrival - grace_end_time)
        lateMinutes = calculateLateMinutes(amIn);
        
        // Calculate undertime (TB5: scheduled_end - actual_pm_out, separate from late)
        const utMinutes = calculateUndertimeMinutes(pmOut);
        undertimeHours = utMinutes / 60;
        
        // Calculate OT hours (TB5: otOut - closing_time)
        const otMinutes = calculateOTMinutes(otOut);
        otHours = otMinutes / 60;
    }
    
    // Calculate deductions and payments (TB5 format)
    const lateDeduct = lateMinutes * perMin;  // TB5: LATE/MIN DEDUCT = late mins * per min rate
    const undertimeDeduct = undertimeHours * hourlyRate;
    const absentDeduct = absentDay * dailyRate;
    const otPay = otHours * otRate;
    
    // Calculate halfday deduction (auto-detect if work hours < 4)
    // If employee works less than 4 hours (half day), deduct half of daily rate
    let halfdayDeduct = 0;
    if (!isAbsent && workHours > 0 && workHours < 4) {
        halfdayDeduct = dailyRate / 2;
    }
    
    // Update row fields
    // Work time in HOURS (matching DB field total_work_hours & professor's Excel)
    // Late in MINUTES (matching DB field late_minutes & professor's Excel)
    
    document.querySelector(`input[name="work_hours_${rowNum}"]`).value = workHours.toFixed(2); // Store as HOURS
    document.querySelector(`input[name="late_mins_${rowNum}"]`).value = Math.round(lateMinutes); // Store as MINUTES
    document.querySelector(`input[name="undertime_${rowNum}"]`).value = undertimeHours.toFixed(2);
    document.querySelector(`input[name="ot_hours_${rowNum}"]`).value = otHours.toFixed(2);
    document.querySelector(`input[name="absent_day_${rowNum}"]`).value = absentDay;
    
    // TB5 deductions (no ₱ prefix, just numbers)
    const absentDeductInput = document.querySelector(`input[name="absent_deduct_${rowNum}"]`);
    if (absentDeductInput) absentDeductInput.value = absentDeduct.toFixed(2);
    document.querySelector(`input[name="late_deduct_${rowNum}"]`).value = lateDeduct.toFixed(2);
    document.querySelector(`input[name="undertime_deduct_${rowNum}"]`).value = undertimeDeduct.toFixed(2);
    
    // Halfday deduction (auto-calculated)
    const halfdayDeductInput = document.querySelector(`input[name="halfday_deduct_${rowNum}"]`);
    if (halfdayDeductInput) halfdayDeductInput.value = halfdayDeduct.toFixed(2);
    
    document.querySelector(`input[name="ot_pay_${rowNum}"]`).value = otPay.toFixed(2);
    
    // Calculate net deductions (Total Deductions - OT Pay)
    const totalDeductions = absentDeduct + lateDeduct + undertimeDeduct + halfdayDeduct;
    const netDeduct = totalDeductions - otPay;
    const netDeductInput = document.querySelector(`input[name="net_deduct_${rowNum}"]`);
    if (netDeductInput) netDeductInput.value = netDeduct.toFixed(2);
    
    // New automatic calculations columns
    const lateMinCalcInput = document.querySelector(`input[name="late_min_calc_${rowNum}"]`);
    if (lateMinCalcInput) lateMinCalcInput.value = lateMinutes.toFixed(2);
    
    const undertimeCalcInput = document.querySelector(`input[name="undertime_calc_${rowNum}"]`);
    if (undertimeCalcInput) undertimeCalcInput.value = undertimeHours.toFixed(2);
    
    const otCalcInput = document.querySelector(`input[name="ot_calc_${rowNum}"]`);
    if (otCalcInput) otCalcInput.value = otHours.toFixed(2);
    
    // Calculate automatic salary: Daily rate - net deductions (if not absent)
    // Only populate when there is actual time data or the row is marked absent
    const hasTimeData = amIn || pmOut || otOut;
    const autoSalary = isAbsent ? 0 : (dailyRate - netDeduct);
    const autoSalaryInput = document.querySelector(`input[name="auto_salary_${rowNum}"]`);
    if (autoSalaryInput) {
        if (isAbsent || hasTimeData) {
            autoSalaryInput.value = autoSalary.toFixed(2);
        } else {
            autoSalaryInput.value = '';
        }
    }
    
    // Recalculate totals
    calculateTotals();
}

// Count working days excluding Sundays in a date range
function countWorkingDays(startDate, endDate) {
    if (!startDate || !endDate) return 0;
    
    const start = new Date(startDate);
    const end = new Date(endDate);
    let workingDays = 0;
    
    for (let date = new Date(start); date <= end; date.setDate(date.getDate() + 1)) {
        // 0 = Sunday, 6 = Saturday
        if (date.getDay() !== 0) { // Exclude Sundays
            workingDays++;
        }
    }
    
    return workingDays;
}

// Calculate trainee payment (number of trainees × payment per trainee)
function calculateTraineePayment() {
    const trainingsCount = parseFloat(document.getElementById('imported_trainings_count')?.value) || 0;
    const paymentPerTrainee = parseFloat(document.getElementById('imported_payment_per_trainee')?.value) || 0;
    const totalTraineeCost = trainingsCount * paymentPerTrainee;
    
    const trainingsCostField = document.getElementById('imported_trainings_cost');
    if (trainingsCostField) {
        trainingsCostField.value = totalTraineeCost.toFixed(2);
    }
    
    // Recalculate totals to update net pay
    calculateTotals();
}

// Calculate main trainee payment from separate form
function calculateMainTraineePayment() {
    const traineeCount = parseFloat(document.getElementById('trainee_count_main')?.value) || 0;
    const paymentPerTrainee = parseFloat(document.getElementById('trainee_payment_per_main')?.value) || 0;
    const totalPayment = traineeCount * paymentPerTrainee;
    
    const totalField = document.getElementById('trainee_total_main');
    if (totalField) {
        totalField.value = totalPayment.toFixed(2);
    }
    
    // Also update the imported section fields to sync data
    const importedCountField = document.getElementById('imported_trainings_count');
    const importedPaymentField = document.getElementById('imported_payment_per_trainee');
    const importedTotalField = document.getElementById('imported_trainings_cost');
    
    if (importedCountField) importedCountField.value = traineeCount;
    if (importedPaymentField) importedPaymentField.value = paymentPerTrainee.toFixed(2);
    if (importedTotalField) importedTotalField.value = totalPayment.toFixed(2);
    
    // Recalculate totals to update net pay (use calculateTotals for main DTR table)
    calculateTotals();
}

// Calculate all totals
function calculateTotals() {
    console.log('calculateTotals() called');
    let totalWorkHours = 0;
    let totalLateMins = 0;
    let totalUndertime = 0;
    let totalOTHours = 0;
    let totalAbsentDays = 0;
    let totalAbsentDeduct = 0;
    let totalLateDeduct = 0;
    let totalUndertimeDeduct = 0;
    let totalHalfdayDeduct = 0;
    let totalOTPay = 0;
    
    // New columns totals
    let totalLateMinCalc = 0;
    let totalUndertimeCalc = 0;
    let totalOTCalc = 0;
    let totalGovt = 0;
    let totalSalary = 0;
    
    // Sum all rows
    const rowCount = document.querySelectorAll('#dtr_rows tr').length;
    console.log('Processing', rowCount, 'rows for totals calculation');
    
    document.querySelectorAll('#dtr_rows tr').forEach(row => {
        const rowNum = row.getAttribute('data-row');
        const workHoursInput = document.querySelector(`input[name="work_hours_${rowNum}"]`);
        
        // Check if inputs exist (row might be in process of being deleted)
        if (!workHoursInput) return;
        
        totalWorkHours += parseFloat(workHoursInput.value) || 0;
        totalLateMins += parseFloat(document.querySelector(`input[name="late_mins_${rowNum}"]`).value) || 0;
        totalUndertime += parseFloat(document.querySelector(`input[name="undertime_${rowNum}"]`).value) || 0;
        totalOTHours += parseFloat(document.querySelector(`input[name="ot_hours_${rowNum}"]`).value) || 0;
        totalAbsentDays += parseFloat(document.querySelector(`input[name="absent_day_${rowNum}"]`).value) || 0;
        totalAbsentDeduct += parseFloat(document.querySelector(`input[name="absent_deduct_${rowNum}"]`).value) || 0;
        totalLateDeduct += parseFloat(document.querySelector(`input[name="late_deduct_${rowNum}"]`).value) || 0;
        totalUndertimeDeduct += parseFloat(document.querySelector(`input[name="undertime_deduct_${rowNum}"]`).value) || 0;
        
        // Add halfday deduction
        const halfdayDeductInput = document.querySelector(`input[name="halfday_deduct_${rowNum}"]`);
        if (halfdayDeductInput) totalHalfdayDeduct += parseFloat(halfdayDeductInput.value) || 0;
        
        totalOTPay += parseFloat(document.querySelector(`input[name="ot_pay_${rowNum}"]`).value) || 0;
        
        // New columns
        const lateMinCalcInput = document.querySelector(`input[name="late_min_calc_${rowNum}"]`);
        if (lateMinCalcInput) totalLateMinCalc += parseFloat(lateMinCalcInput.value) || 0;
        
        const undertimeCalcInput = document.querySelector(`input[name="undertime_calc_${rowNum}"]`);
        if (undertimeCalcInput) totalUndertimeCalc += parseFloat(undertimeCalcInput.value) || 0;
        
        const otCalcInput = document.querySelector(`input[name="ot_calc_${rowNum}"]`);
        if (otCalcInput) totalOTCalc += parseFloat(otCalcInput.value) || 0;
        
        const govtInput = document.querySelector(`input[name="govt_${rowNum}"]`);
        if (govtInput) totalGovt += parseFloat(govtInput.value) || 0;
        
        const salaryInput = document.querySelector(`input[name="auto_salary_${rowNum}"]`);
        if (salaryInput) totalSalary += parseFloat(salaryInput.value) || 0;
    });
    
    // Calculate total net deductions (include halfday deduction)
    const totalNetDeduct = (totalAbsentDeduct + totalLateDeduct + totalUndertimeDeduct + totalHalfdayDeduct) - totalOTPay;
    
    // Update totals row - values already in correct units (hours/minutes)
    const workHrsEl = document.getElementById('total_work_hours');
    const workMinsEl = document.getElementById('total_work_mins');
    const lateMinsEl = document.getElementById('total_late_mins');
    const lateHoursEl = document.getElementById('total_late_hours');
    
    // Work hours display (stored as HOURS)
    if (workMinsEl) {
        workMinsEl.textContent = totalWorkHours.toFixed(2);
    }
    if (workHrsEl) {
        workHrsEl.textContent = totalWorkHours.toFixed(2);
    }
    
    // Late display (stored as MINUTES)
    if (lateHoursEl) {
        lateHoursEl.textContent = Math.round(totalLateMins);
    }
    if (lateMinsEl) {
        lateMinsEl.textContent = Math.round(totalLateMins);
    }
    
    // Update all totals with safety checks
    const updateElement = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    
    updateElement('total_undertime', totalUndertime.toFixed(2));
    updateElement('total_ot_hours', totalOTHours.toFixed(2));
    updateElement('total_absent_days', totalAbsentDays);
    updateElement('total_absent_deduct', totalAbsentDeduct.toFixed(2));
    updateElement('total_late_deduct', totalLateDeduct.toFixed(2));
    updateElement('total_undertime_deduct', totalUndertimeDeduct.toFixed(2));
    updateElement('total_halfday_deduct', totalHalfdayDeduct.toFixed(2));
    updateElement('total_ot_payment', totalOTPay.toFixed(2));
    
    // Update net deductions total
    const netDeductEl = document.getElementById('total_net_deductions');
    if (netDeductEl) netDeductEl.textContent = totalNetDeduct.toFixed(2);
    
    // Update new columns totals
    const totalLateMinEl = document.getElementById('total_late_min');
    if (totalLateMinEl) totalLateMinEl.textContent = totalLateMinCalc.toFixed(2);
    
    const totalUndertimeCalcEl = document.getElementById('total_undertime_calc');
    if (totalUndertimeCalcEl) totalUndertimeCalcEl.textContent = totalUndertimeCalc.toFixed(2);
    
    const totalOTCalcEl = document.getElementById('total_ot_calc');
    if (totalOTCalcEl) totalOTCalcEl.textContent = totalOTCalc.toFixed(2);
    
    const totalGovtEl = document.getElementById('total_govt');
    if (totalGovtEl) totalGovtEl.textContent = totalGovt.toFixed(2);
    
    // Read trainee payment to add to total salary footer
    const traineePaymentForFooter = parseFloat(document.getElementById('trainee_total_main')?.value) || 
                                     parseFloat(document.getElementById('imported_trainings_cost')?.value) || 0;
    
    // Add trainee payment to total salary for footer display
    const totalSalaryWithTrainee = totalSalary + traineePaymentForFooter;
    
    const totalSalaryEl = document.getElementById('total_salary');
    if (totalSalaryEl) totalSalaryEl.textContent = totalSalaryWithTrainee.toFixed(2);
    
    console.log('✓ Totals updated:');
    console.log('  - WorkHours:', totalWorkHours.toFixed(2));
    console.log('  - Late Mins:', totalLateMins);
    console.log('  - Undertime:', totalUndertime.toFixed(2));
    console.log('  - OT Hours:', totalOTHours.toFixed(2));
    console.log('  - Absent Days:', totalAbsentDays);
    console.log('  - Total Deductions:', (totalAbsentDeduct + totalLateDeduct + totalUndertimeDeduct + totalHalfdayDeduct).toFixed(2));
    console.log('  - OT Pay:', totalOTPay.toFixed(2));
    console.log('  - Govt Benefits:', totalGovt.toFixed(2));
    console.log('  - Net Salary (from rows):', totalSalary.toFixed(2));
    console.log('  - Trainee Payment:', traineePaymentForFooter.toFixed(2));
    console.log('  - Total Net Salary (with trainee):', totalSalaryWithTrainee.toFixed(2));
    
    // ── UPDATE NET PAY SUMMARY ──
    // Read basic salary (from imported section or rate fields)
    const grossPay = parseFloat(document.getElementById('imported_salary')?.value) || 0;
    const sssDeduct = parseFloat(document.getElementById('imported_sss')?.value) || 0;
    const philhealthDeduct = parseFloat(document.getElementById('imported_philhealth')?.value) || 0;
    const pagibigDeduct = parseFloat(document.getElementById('imported_pagibig')?.value) || 0;
    const govtDeductions = sssDeduct + philhealthDeduct + pagibigDeduct;
    
    // Read trainee payment from the main trainee form (most reliable source)
    const traineeTotal = parseFloat(document.getElementById('trainee_total_main')?.value) || 
                         parseFloat(document.getElementById('imported_trainings_cost')?.value) || 0;
    
    console.log('Net Pay Calculation - TraineeTotal:', traineeTotal, 'GrossPay:', grossPay, 'GovtDeductions:', govtDeductions);
    
    const totalDTRDeductions = totalAbsentDeduct + totalLateDeduct + totalUndertimeDeduct + totalHalfdayDeduct;
    
    // Check if there's any actual DTR data with values (not just empty rows)
    const hasDTRData = totalWorkHours > 0 || totalDTRDeductions > 0 || totalOTPay > 0;
    
    // Calculate net pay:
    // - If there's DTR data: use gross pay and apply all deductions/additions + trainee payment
    // - If there's no DTR data but only trainee payment: net pay = trainee payment only
    let netPay;
    if (hasDTRData) {
        // Standard calculation: Gross Pay - Deductions + Earnings
        netPay = grossPay - totalDTRDeductions - govtDeductions + totalOTPay + traineeTotal;
        console.log('NetPay (with DTR):', netPay, '= GrossPay', grossPay, '- DTRDeduct', totalDTRDeductions, '- GovtDeduct', govtDeductions, '+ OTPay', totalOTPay, '+ Trainee', traineeTotal);
    } else {
        // No DTR data, only trainee payment
        netPay = traineeTotal;
        console.log('NetPay (no DTR, trainee only):', netPay);
    }
    
    // Update summary display elements
    const setTotal = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    setTotal('summary_total_deductions', '-₱' + (totalDTRDeductions + govtDeductions).toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_ot_pay', '+₱' + totalOTPay.toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_trainee_payment', '+₱' + traineeTotal.toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_net_pay', '₱' + netPay.toLocaleString('en-PH', {minimumFractionDigits: 2}));
}

// Recalculate all DTR rows (manual trigger button)
function recalculateAllDTR() {
    console.log('Manual recalculate all triggered');
    updateAllRates(); // Ensure rates are current
    
    const dtrRows = document.getElementById('dtr_rows');
    if (!dtrRows) return;
    
    let count = 0;
    dtrRows.querySelectorAll('tr[data-row]').forEach(row => {
        const rn = parseInt(row.getAttribute('data-row'));
        try {
            calculateRowDTR(rn);
            count++;
        } catch(e) {
            console.error('Recalc error row', rn, ':', e);
        }
    });
    calculateTotals();
    console.log(`Recalculated ${count} rows`);
}

// Attach event listeners to DTR inputs
function attachDTRListeners() {
    document.querySelectorAll('.dtr-input, .dtr-absent, .dtr-date-input').forEach(input => {
        // Remove existing listener to avoid duplicates
        input.removeEventListener('change', handleDTRInputChange);
        input.addEventListener('change', handleDTRInputChange);
    });
    
    // Attach listeners to manual govt input fields
    document.querySelectorAll('.dtr-manual').forEach(input => {
        input.removeEventListener('input', handleManualInputChange);
        input.addEventListener('input', handleManualInputChange);
    });
    
    // Rate changes recalculate all
    document.querySelectorAll('#hourly_rate, #ot_rate, #daily_rate').forEach(input => {
        input.removeEventListener('input', handleRateChange);
        input.addEventListener('input', handleRateChange);
    });
    
    // Absent checkbox listeners to disable/enable time inputs
    document.querySelectorAll('.dtr-absent').forEach(checkbox => {
        checkbox.removeEventListener('change', handleAbsentCheckboxChange);
        checkbox.addEventListener('change', handleAbsentCheckboxChange);
    });
    
    // Time input validation - halfday vs full day logic
    document.querySelectorAll('.dtr-input.time24').forEach(input => {
        input.removeEventListener('input', handleTimeInputValidation);
        input.addEventListener('input', handleTimeInputValidation);
    });
}

// Handler functions to avoid duplicate listeners
function handleDTRInputChange() {
    const rowNum = this.getAttribute('data-row');
    calculateRowDTR(rowNum);
}

function handleManualInputChange() {
    calculateTotals();
}

function handleRateChange() {
    document.querySelectorAll('#dtr_rows tr').forEach(row => {
        const rowNum = row.getAttribute('data-row');
        calculateRowDTR(rowNum);
    });
}

// Handler for absent checkbox - disable/enable time inputs
function handleAbsentCheckboxChange() {
    const rowNum = this.getAttribute('data-row');
    const isAbsent = this.checked;
    const row = this.closest('tr');
    
    if (!row) return;
    
    // Find all time input fields in this row (now using text inputs with class time24)
    const timeInputs = row.querySelectorAll('input.time24');
    
    timeInputs.forEach(input => {
        if (isAbsent) {
            // Disable and clear time inputs when marked as absent
            input.disabled = true;
            input.value = '';
            input.style.backgroundColor = '#f0f0f0';
            input.style.cursor = 'not-allowed';
        } else {
            // Enable time inputs when not absent
            input.disabled = false;
            input.style.backgroundColor = '';
            input.style.cursor = '';
        }
    });
    
    // Recalculate the row
    calculateRowDTR(rowNum);
}

// Handler for time input changes
function handleTimeInputValidation() {
    const row = this.closest('tr');
    if (!row) return;
    
    const rowNum = this.getAttribute('data-row');
    
    // Check if absent checkbox is checked
    const absentCheckbox = row.querySelector(`input[name="absent_${rowNum}"]`);
    if (absentCheckbox && absentCheckbox.checked) {
        return; // Skip validation if absent
    }
}

// Validate a specific row's inputs (used after row creation or import)
function validateRowInputs(rowNum) {
    const row = document.querySelector(`tr[data-row="${rowNum}"]`);
    if (!row) return;
    
    // Get time inputs
    const amIn = row.querySelector(`input[name="am_in_${rowNum}"]`);
    const pmOut = row.querySelector(`input[name="pm_out_${rowNum}"]`);
    const absentCheckbox = row.querySelector(`input[name="absent_${rowNum}"]`);
    
    // If absent is checked, disable all time inputs
    if (absentCheckbox && absentCheckbox.checked) {
        const timeInputs = row.querySelectorAll('input.time24');
        timeInputs.forEach(input => {
            input.disabled = true;
            input.value = '';
            input.style.backgroundColor = '#f0f0f0';
            input.style.cursor = 'not-allowed';
        });
        return;
    }
}

// Load employee data when selected
document.getElementById('employee_select').addEventListener('change', function() {
    const employeeId = this.value;
    
    if (!employeeId) {
        document.getElementById('payslip_form').style.display = 'none';
        return;
    }
    
    const selectedOption = this.options[this.selectedIndex];
    const employeeName = selectedOption.getAttribute('data-name');
    const employeeCode = selectedOption.getAttribute('data-code');
    const position = selectedOption.getAttribute('data-position');
    const department = selectedOption.getAttribute('data-department');
    const salary = selectedOption.getAttribute('data-salary');
    
    // Load additional employee data via AJAX
    fetch('get_employee_details.php?id=' + employeeId)
        .then(response => response.json())
        .then(data => {
            // Update employee information (removed - display elements no longer exist)
            // document.getElementById('display_full_name').textContent = employeeName;
            // document.getElementById('display_address').textContent = data.address || '123 Any Court Road, London W1T 1JY, UK';
            // document.getElementById('display_phone').textContent = data.phone || '+44 00 0000 0000';
            // document.getElementById('display_email').textContent = data.email || 'name@provider.com';
            // document.getElementById('employee_number').value = employeeCode;
            
            // Set hidden form values
            document.getElementById('form_employee_id').value = employeeId;
            
            // Set rate fields
            if (data.per_hour_rate) document.getElementById('hourly_rate').value = data.per_hour_rate;
            if (data.overtime_rate) document.getElementById('ot_rate').value = data.overtime_rate;
            if (data.per_day_rate) document.getElementById('daily_rate').value = data.per_day_rate;
            
            // Update TB5 header with employee name and salary
            const tb5NameEl = document.getElementById('tb5_employee_name');
            if (tb5NameEl) tb5NameEl.textContent = employeeName || '-';
            
            // Update basic salary field if available
            const basicSalaryField = document.getElementById('basic_monthly_salary');
            if (basicSalaryField && salary) {
                basicSalaryField.value = salary;
                // Trigger rate calculations
                updateAllRates();
            }
            
            // Show the form
            document.getElementById('payslip_form').style.display = 'block';
            
            // Auto-generate 31 DTR rows
            generate31DTRRows();
            
            // Generate DTR rows if period is already selected (for date population)
            const periodSelect = document.getElementById('payroll_period');
            if (periodSelect.value) {
                const periodOption = periodSelect.options[periodSelect.selectedIndex];
                const startDate = periodOption.getAttribute('data-start');
                const endDate = periodOption.getAttribute('data-end');
                if (startDate && endDate) {
                    generateDTRRows(startDate, endDate);
                }
            }
        })
        .catch(error => {
            console.error('Error:', error);
            // Show form anyway with basic data (removed - display elements no longer exist)
            // document.getElementById('display_full_name').textContent = employeeName;
            // document.getElementById('display_address').textContent = '123 Any Court Road, London W1T 1JY, UK';
            // document.getElementById('display_phone').textContent = '+44 00 0000 0000';
            // document.getElementById('display_email').textContent = 'name@provider.com';
            // document.getElementById('employee_number').value = employeeCode;
            document.getElementById('form_employee_id').value = employeeId;
            
            // Update TB5 header with employee name even on error
            const tb5NameEl = document.getElementById('tb5_employee_name');
            if (tb5NameEl) tb5NameEl.textContent = employeeName || '-';
            
            // Update basic salary if available
            const basicSalaryField = document.getElementById('basic_monthly_salary');
            if (basicSalaryField && salary) {
                basicSalaryField.value = salary;
                updateAllRates();
            }
            
            document.getElementById('payslip_form').style.display = 'block';
            
            // Auto-generate 31 DTR rows even on error
            generate31DTRRows();
        });
});

// Calculate period from selected date (cut-offs: 12th and 27th)
function calculatePeriodFromDate(dateStr) {
    if (!dateStr) return null;
    
    const date = new Date(dateStr);
    const year = date.getFullYear();
    const month = date.getMonth(); // 0-indexed
    const day = date.getDate();
    
    let startDate, endDate, payDate;
    
    // Determine which cut-off period
    if (day <= 15) {
        // First period: 1st to 15th, pay date is 12th
        startDate = new Date(year, month, 1);
        endDate = new Date(year, month, 15);
        payDate = new Date(year, month, 12);
    } else {
        // Second period: 16th to end of month, pay date is 27th
        startDate = new Date(year, month, 16);
        endDate = new Date(year, month + 1, 0); // Last day of current month
        payDate = new Date(year, month, 27);
    }
    
    // Format dates as YYYY-MM-DD
    const formatDate = (d) => d.toISOString().split('T')[0];
    
    return {
        start: formatDate(startDate),
        end: formatDate(endDate),
        pay: formatDate(payDate),
        startObj: startDate,
        endObj: endDate,
        payObj: payDate
    };
}

// Update cutoff dropdown options to show actual last day of selected month
function updateCutoffOptions() {
    const year = document.getElementById('payroll_year').value;
    const month = document.getElementById('payroll_month').value;
    const cutoffSelect = document.getElementById('payroll_cutoff');
    
    if (!year || !month) {
        // Reset to default if year or month not selected
        cutoffSelect.innerHTML = `
            <option value="">Cut-off</option>
            <option value="12">12th (1-15 period)</option>
            <option value="27">27th (16-end period)</option>
        `;
        return;
    }
    
    // Calculate last day of selected month
    const monthIndex = parseInt(month) - 1;
    const yearInt = parseInt(year);
    const lastDay = new Date(yearInt, monthIndex + 1, 0).getDate();
    
    // Remember current selection
    const currentValue = cutoffSelect.value;
    
    // Update options with dynamic last day
    cutoffSelect.innerHTML = `
        <option value="">Cut-off</option>
        <option value="12">12th (1-15 period)</option>
        <option value="27">27th (16-${lastDay} period)</option>
    `;
    
    // Restore selection if it was previously set
    if (currentValue) {
        cutoffSelect.value = currentValue;
    }
}

// Update pay period information based on year, month, and cutoff dropdowns
function updatePayrollPeriod() {
    const year = document.getElementById('payroll_year').value;
    const month = document.getElementById('payroll_month').value;
    const cutoff = document.getElementById('payroll_cutoff').value;
    
    if (!year || !month || !cutoff) {
        return;
    }
    
    // Convert month to 0-indexed for Date object
    const monthIndex = parseInt(month) - 1;
    const yearInt = parseInt(year);
    const cutoffDay = parseInt(cutoff);
    
    let startDate, endDate, payDate;
    
    // Determine period based on cutoff
    if (cutoffDay === 12) {
        // First period: 1st to 15th, pay date is 12th
        startDate = new Date(yearInt, monthIndex, 1);
        endDate = new Date(yearInt, monthIndex, 15);
        payDate = new Date(yearInt, monthIndex, 12);
    } else if (cutoffDay === 27) {
        // Second period: 16th to end of month, pay date is 27th
        startDate = new Date(yearInt, monthIndex, 16);
        endDate = new Date(yearInt, monthIndex + 1, 0); // Last day of current month
        payDate = new Date(yearInt, monthIndex, 27);
    }
    
    // Format dates as YYYY-MM-DD
    const formatDate = (d) => d.toISOString().split('T')[0];
    
    // Update hidden fields
    document.getElementById('calculated_start_date').value = formatDate(startDate);
    document.getElementById('calculated_end_date').value = formatDate(endDate);
    document.getElementById('calculated_pay_date').value = formatDate(payDate);
    
    // Update display fields with proper formatting
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June',
                        'July', 'August', 'September', 'October', 'November', 'December'];
    
    const payDateDisplay = `${monthNames[monthIndex]} ${cutoffDay}, ${yearInt}`;
    const periodDisplay = cutoffDay === 12 ? 
        `${monthNames[monthIndex]} 1-15, ${yearInt}` : 
        `${monthNames[monthIndex]} 16-${endDate.getDate()}, ${yearInt}`;
    
    // Display elements removed - commenting out to prevent errors
    // document.getElementById('display_pay_date').textContent = payDateDisplay;
    // document.getElementById('display_period').textContent = periodDisplay;
    
    // Generate DTR rows if employee is selected
    const employeeSelect = document.getElementById('employee_select');
    if (employeeSelect.value) {
        generateDTRRows(formatDate(startDate), formatDate(endDate));
    }
}

// Reset form
if (document.getElementById('btn_reset')) {
    document.getElementById('btn_reset').addEventListener('click', function() {
        if (confirm('Are you sure you want to reset the form?')) {
            document.getElementById('payroll_form').reset();
            document.getElementById('dtr_rows').innerHTML = '';
            updateRowCount();
            calculateTotals();
        }
    });
}

// Form submission
document.getElementById('payroll_form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('process_payroll.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Payroll saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while saving the payroll.');
    });
});

// ==========================================
// DTR EXCEL IMPORT FUNCTIONALITY
// ==========================================

let selectedFile = null;

// Initialize import functionality on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.getElementById('dtr_excel_file');
    const importExcelBtn = document.getElementById('btn_import_excel');
    const templateBtn = document.getElementById('btn_download_template');
    
    // Import Excel button click - opens file dialog
    if (importExcelBtn) {
        importExcelBtn.addEventListener('click', function() {
            fileInput.click();
        });
    }
    
    // File input change handler - auto-import when file is selected
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                handleFileSelect(this.files[0]);
                // Auto-import after file selection
                setTimeout(() => {
                    importDTRFromExcel();
                }, 300);
            }
        });
    }
    
    // Download template button
    if (templateBtn) {
        templateBtn.addEventListener('click', function() {
            downloadDTRTemplate();
        });
    }
    
    // Generate Payslip button
    const generateBtn = document.getElementById('btn_generate_payslip');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            generatePayslipFromTB5();
        });
    }
    
    // Clear & Start Over button
    const clearBtn = document.getElementById('btn_clear_import');
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            clearImportedData();
        });
    }
    
    // Save DTR button
    const saveBtn = document.getElementById('btn_save_dtr');
    if (saveBtn) {
        saveBtn.addEventListener('click', function() {
            saveDTRToDatabase();
        });
    }
    
    // Full View button
    const fullViewBtn = document.getElementById('btn_full_view');
    if (fullViewBtn) {
        fullViewBtn.addEventListener('click', function() {
            openFullViewModal();
        });
    }
    
    // Refresh cards button
    const refreshCardsBtn = document.getElementById('btn_refresh_cards');
    if (refreshCardsBtn) {
        refreshCardsBtn.addEventListener('click', function() {
            loadEmployeeDTRCards();
        });
    }
    
    // Salary change recalculates rates
    const salaryInput = document.getElementById('imported_salary');
    if (salaryInput) {
        salaryInput.addEventListener('change', function() {
            updateTB5Rates(parseFloat(this.value) || 0);
        });
    }
    
    // Sync trainee form with imported section on page load
    syncTraineeFormFromImported();
    
    // Add change listeners to imported trainee fields to sync with main form
    const importedTrainingsCount = document.getElementById('imported_trainings_count');
    const importedPaymentPerTrainee = document.getElementById('imported_payment_per_trainee');
    
    if (importedTrainingsCount) {
        importedTrainingsCount.addEventListener('input', syncMainFormFromImported);
    }
    if (importedPaymentPerTrainee) {
        importedPaymentPerTrainee.addEventListener('input', syncMainFormFromImported);
    }
});

// Sync main trainee form from imported section
function syncMainFormFromImported() {
    const importedCount = parseFloat(document.getElementById('imported_trainings_count')?.value) || 0;
    const importedPayment = parseFloat(document.getElementById('imported_payment_per_trainee')?.value) || 0;
    
    const mainCountField = document.getElementById('trainee_count_main');
    const mainPaymentField = document.getElementById('trainee_payment_per_main');
    const mainTotalField = document.getElementById('trainee_total_main');
    
    if (mainCountField) mainCountField.value = importedCount;
    if (mainPaymentField) mainPaymentField.value = importedPayment.toFixed(2);
    if (mainTotalField) mainTotalField.value = (importedCount * importedPayment).toFixed(2);
    
    // Recalculate totals to update net pay display
    calculateTotals();
}

// Sync trainee form from imported section on page load
function syncTraineeFormFromImported() {
    syncMainFormFromImported();
}

// Generate 31 empty TB5 rows
function generateTB5Rows(startDate = null) {
    const tbody = document.getElementById('tb5_dtr_body');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    let baseDate = startDate ? new Date(startDate) : new Date();
    baseDate.setDate(1); // Start from day 1
    
    for (let day = 1; day <= 31; day++) {
        const dateStr = formatDateForInput(baseDate.getFullYear(), baseDate.getMonth() + 1, day);
        const row = createTB5Row(day, dateStr);
        tbody.appendChild(row);
        // Validate the row after creation (for default values)
        validateTB5RowInputs(day);
    }
    
    attachTB5Listeners();
}

// Format date for input YYYY-MM-DD
function formatDateForInput(year, month, day) {
    return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

// Create a single TB5 row
function createTB5Row(dayNum, dateStr = '') {
    const row = document.createElement('tr');
    row.setAttribute('data-day', dayNum);
    row.innerHTML = `
        <td><input type="date" name="tb5_date_${dayNum}" value="${dateStr}" class="tb5-date"></td>
        <td><input type="text" name="tb5_am_in_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="8:00" maxlength="5" value="8:00" oninput="formatTime24(this)"></td>
        <td><input type="text" name="tb5_am_out_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="12:00" maxlength="5" value="12:00" oninput="formatTime24(this)"></td>
        <td><input type="text" name="tb5_pm_in_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="13:00" maxlength="5" value="13:00" oninput="formatTime24(this)"></td>
        <td><input type="text" name="tb5_pm_out_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="17:00" maxlength="5" value="17:00" oninput="formatTime24(this)"></td>
        <td><input type="checkbox" name="tb5_absent_${dayNum}" class="absent-input tb5-absent"></td>
        <td><input type="text" name="tb5_ot_out_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="18:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td><input type="text" name="tb5_half_in_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="8:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td><input type="text" name="tb5_half_out_${dayNum}" class="time-input tb5-input time24" autocomplete="off" placeholder="12:00" maxlength="5" oninput="formatTime24(this)"></td>
        <td class="calc-cell"><span class="tb5-work-mins" data-day="${dayNum}">0</span></td>
        <td class="calc-cell"><span class="tb5-late-hrs" data-day="${dayNum}">0.00</span></td>
        <td class="calc-cell"><span class="tb5-ut-hrs" data-day="${dayNum}">0.00</span></td>
        <td class="calc-cell"><span class="tb5-ot-hrs" data-day="${dayNum}">0.00</span></td>
        <td class="deduct-cell"><span class="tb5-absent-ded" data-day="${dayNum}">0.00</span></td>
        <td class="deduct-cell"><span class="tb5-late-ded" data-day="${dayNum}">0.00</span></td>
        <td class="deduct-cell"><span class="tb5-ut-ded" data-day="${dayNum}">0.00</span></td>
        <td class="deduct-cell"><span class="tb5-half-ded" data-day="${dayNum}">0.00</span></td>
        <td class="ot-cell"><span class="tb5-ot-pay" data-day="${dayNum}">0.00</span></td>
        <td><input type="text" name="tb5_remarks_${dayNum}" class="tb5-input" placeholder="" style="width:60px;"></td>
    `;
    return row;
}

// Attach event listeners to TB5 inputs
function attachTB5Listeners() {
    const inputs = document.querySelectorAll('.tb5-input, .tb5-absent, .tb5-date');
    inputs.forEach(input => {
        input.addEventListener('change', function() {
            const row = this.closest('tr');
            const day = row.getAttribute('data-day');
            calculateTB5Row(day);
            updateTB5Totals();
        });
    });
    
    // Absent checkbox validation for TB5
    document.querySelectorAll('.tb5-absent').forEach(checkbox => {
        checkbox.removeEventListener('change', handleTB5AbsentChange);
        checkbox.addEventListener('change', handleTB5AbsentChange);
    });
    
    // Time input validation for TB5 - halfday vs full day logic
    document.querySelectorAll('.tb5-input.time24').forEach(input => {
        input.removeEventListener('input', handleTB5TimeInputValidation);
        input.addEventListener('input', handleTB5TimeInputValidation);
    });
}

// Handler for TB5 absent checkbox
function handleTB5AbsentChange() {
    const row = this.closest('tr');
    const day = row.getAttribute('data-day');
    const isAbsent = this.checked;
    
    if (!row) return;
    
    // Find all time input fields in this TB5 row
    const timeInputs = row.querySelectorAll('input.time24');
    
    timeInputs.forEach(input => {
        if (isAbsent) {
            input.disabled = true;
            input.value = '';
            input.style.backgroundColor = '#f0f0f0';
            input.style.cursor = 'not-allowed';
        } else {
            input.disabled = false;
            input.style.backgroundColor = '';
            input.style.cursor = '';
        }
    });
    
    calculateTB5Row(day);
    updateTB5Totals();
}

// Handler for TB5 time input validation
function handleTB5TimeInputValidation() {
    const row = this.closest('tr');
    if (!row) return;
    
    const day = row.getAttribute('data-day');
    
    // Get all time inputs
    const amIn = row.querySelector(`input[name="tb5_am_in_${day}"]`);
    const amOut = row.querySelector(`input[name="tb5_am_out_${day}"]`);
    const pmIn = row.querySelector(`input[name="tb5_pm_in_${day}"]`);
    const pmOut = row.querySelector(`input[name="tb5_pm_out_${day}"]`);
    const halfdayIn = row.querySelector(`input[name="tb5_half_in_${day}"]`);
    const halfdayOut = row.querySelector(`input[name="tb5_half_out_${day}"]`);
    
    // Check if absent checkbox is checked
    const absentCheckbox = row.querySelector(`input[name="tb5_absent_${day}"]`);
    if (absentCheckbox && absentCheckbox.checked) {
        return; // Skip validation if absent
    }
    
    // Check if any AM/PM field has value
    const hasAMPM = (amIn && amIn.value) || (amOut && amOut.value) || 
                    (pmIn && pmIn.value) || (pmOut && pmOut.value);
    
    // Check if any halfday field has value
    const hasHalfday = (halfdayIn && halfdayIn.value) || (halfdayOut && halfdayOut.value);
    
    // Disable halfday inputs if AM/PM has values
    if (hasAMPM && halfdayIn && halfdayOut) {
        halfdayIn.disabled = true;
        halfdayOut.disabled = true;
        halfdayIn.value = '';
        halfdayOut.value = '';
        halfdayIn.style.backgroundColor = '#f0f0f0';
        halfdayOut.style.backgroundColor = '#f0f0f0';
        halfdayIn.style.cursor = 'not-allowed';
        halfdayOut.style.cursor = 'not-allowed';
        halfdayIn.title = 'Cannot use halfday when full day schedule is entered';
        halfdayOut.title = 'Cannot use halfday when full day schedule is entered';
    } else if (!hasAMPM && halfdayIn && halfdayOut) {
        halfdayIn.disabled = false;
        halfdayOut.disabled = false;
        halfdayIn.style.backgroundColor = '';
        halfdayOut.style.backgroundColor = '';
        halfdayIn.style.cursor = '';
        halfdayOut.style.cursor = '';
        halfdayIn.title = '';
        halfdayOut.title = '';
    }
    
    // Disable AM/PM inputs if halfday has values
    if (hasHalfday && amIn && amOut && pmIn && pmOut) {
        amIn.disabled = true;
        amOut.disabled = true;
        pmIn.disabled = true;
        pmOut.disabled = true;
        amIn.value = '';
        amOut.value = '';
        pmIn.value = '';
        pmOut.value = '';
        amIn.style.backgroundColor = '#f0f0f0';
        amOut.style.backgroundColor = '#f0f0f0';
        pmIn.style.backgroundColor = '#f0f0f0';
        pmOut.style.backgroundColor = '#f0f0f0';
        amIn.style.cursor = 'not-allowed';
        amOut.style.cursor = 'not-allowed';
        pmIn.style.cursor = 'not-allowed';
        pmOut.style.cursor = 'not-allowed';
        amIn.title = 'Cannot use full day when halfday schedule is entered';
        amOut.title = 'Cannot use full day when halfday schedule is entered';
        pmIn.title = 'Cannot use full day when halfday schedule is entered';
        pmOut.title = 'Cannot use full day when halfday schedule is entered';
    } else if (!hasHalfday && amIn && amOut && pmIn && pmOut) {
        amIn.disabled = false;
        amOut.disabled = false;
        pmIn.disabled = false;
        pmOut.disabled = false;
        amIn.style.backgroundColor = '';
        amOut.style.backgroundColor = '';
        pmIn.style.backgroundColor = '';
        pmOut.style.backgroundColor = '';
        amIn.style.cursor = '';
        amOut.style.cursor = '';
        pmIn.style.cursor = '';
        pmOut.style.cursor = '';
        amIn.title = '';
        amOut.title = '';
        pmIn.title = '';
        pmOut.title = '';
    }
}

// Validate a specific TB5 row's inputs (used after row creation or import)
function validateTB5RowInputs(day) {
    const row = document.querySelector(`tr[data-day="${day}"]`);
    if (!row) return;
    
    // Get all time inputs
    const amIn = row.querySelector(`input[name="tb5_am_in_${day}"]`);
    const amOut = row.querySelector(`input[name="tb5_am_out_${day}"]`);
    const pmIn = row.querySelector(`input[name="tb5_pm_in_${day}"]`);
    const pmOut = row.querySelector(`input[name="tb5_pm_out_${day}"]`);
    const halfdayIn = row.querySelector(`input[name="tb5_half_in_${day}"]`);
    const halfdayOut = row.querySelector(`input[name="tb5_half_out_${day}"]`);
    const absentCheckbox = row.querySelector(`input[name="tb5_absent_${day}"]`);
    
    // If absent is checked, disable all time inputs
    if (absentCheckbox && absentCheckbox.checked) {
        const timeInputs = row.querySelectorAll('input.time24');
        timeInputs.forEach(input => {
            input.disabled = true;
            input.value = '';
            input.style.backgroundColor = '#f0f0f0';
            input.style.cursor = 'not-allowed';
        });
        return;
    }
    
    // Check if any AM/PM field has value
    const hasAMPM = (amIn && amIn.value) || (amOut && amOut.value) || 
                    (pmIn && pmIn.value) || (pmOut && pmOut.value);
    
    // Check if any halfday field has value
    const hasHalfday = (halfdayIn && halfdayIn.value) || (halfdayOut && halfdayOut.value);
    
    // Disable halfday inputs if AM/PM has values
    if (hasAMPM && halfdayIn && halfdayOut) {
        halfdayIn.disabled = true;
        halfdayOut.disabled = true;
        halfdayIn.value = '';
        halfdayOut.value = '';
        halfdayIn.style.backgroundColor = '#f0f0f0';
        halfdayOut.style.backgroundColor = '#f0f0f0';
        halfdayIn.style.cursor = 'not-allowed';
        halfdayOut.style.cursor = 'not-allowed';
        halfdayIn.title = 'Cannot use halfday when full day schedule is entered';
        halfdayOut.title = 'Cannot use halfday when full day schedule is entered';
    }
    
    // Disable AM/PM inputs if halfday has values
    if (hasHalfday && amIn && amOut && pmIn && pmOut) {
        amIn.disabled = true;
        amOut.disabled = true;
        pmIn.disabled = true;
        pmOut.disabled = true;
        amIn.value = '';
        amOut.value = '';
        pmIn.value = '';
        pmOut.value = '';
        amIn.style.backgroundColor = '#f0f0f0';
        amOut.style.backgroundColor = '#f0f0f0';
        pmIn.style.backgroundColor = '#f0f0f0';
        pmOut.style.backgroundColor = '#f0f0f0';
        amIn.style.cursor = 'not-allowed';
        amOut.style.cursor = 'not-allowed';
        pmIn.style.cursor = 'not-allowed';
        pmOut.style.cursor = 'not-allowed';
        amIn.title = 'Cannot use full day when halfday schedule is entered';
        amOut.title = 'Cannot use full day when halfday schedule is entered';
        pmIn.title = 'Cannot use full day when halfday schedule is entered';
        pmOut.title = 'Cannot use full day when halfday schedule is entered';
    }
}

// Calculate a single TB5 row
function calculateTB5Row(day) {
    const salary = parseFloat(document.getElementById('imported_salary')?.value) || 13000;
    // Use custom daily rate input if set, otherwise calculate based on working days
    const dailyRateInput = document.getElementById('daily_rate');
    let dailyRate;
    
    if (dailyRateInput && dailyRateInput.value) {
        dailyRate = parseFloat(dailyRateInput.value);
    } else {
        // Calculate based on working days (excluding Sundays) in the period
        const periodStart = document.getElementById('imported_period_start')?.value;
        const periodEnd = document.getElementById('imported_period_end')?.value;
        const workingDays = countWorkingDays(periodStart, periodEnd) || 15; // Fallback to 15
        dailyRate = salary / workingDays;
    }
    
    const hourlyRate = dailyRate / 8;
    
    // Helper to safely set textContent
    const setCell = (selector, value) => {
        const el = document.querySelector(selector);
        if (el) el.textContent = value;
    };
    
    const isAbsent = document.querySelector(`input[name="tb5_absent_${day}"]`)?.checked || false;
    
    if (isAbsent) {
        // Full day absent
        setCell(`.tb5-work-mins[data-day="${day}"]`, '0');
        setCell(`.tb5-late-hrs[data-day="${day}"]`, '0.00');
        setCell(`.tb5-ut-hrs[data-day="${day}"]`, '0.00');
        setCell(`.tb5-ot-hrs[data-day="${day}"]`, '0.00');
        setCell(`.tb5-absent-ded[data-day="${day}"]`, dailyRate.toFixed(2));
        setCell(`.tb5-late-ded[data-day="${day}"]`, '0.00');
        setCell(`.tb5-ut-ded[data-day="${day}"]`, '0.00');
        setCell(`.tb5-half-ded[data-day="${day}"]`, '0.00');
        setCell(`.tb5-ot-pay[data-day="${day}"]`, '0.00');
        return;
    }
    
    // Parse times
    const amIn = parseTimeToMinutes(document.querySelector(`input[name="tb5_am_in_${day}"]`)?.value);
    const amOut = parseTimeToMinutes(document.querySelector(`input[name="tb5_am_out_${day}"]`)?.value);
    const pmIn = parseTimeToMinutes(document.querySelector(`input[name="tb5_pm_in_${day}"]`)?.value);
    const pmOut = parseTimeToMinutes(document.querySelector(`input[name="tb5_pm_out_${day}"]`)?.value);
    const otOut = parseTimeToMinutes(document.querySelector(`input[name="tb5_ot_out_${day}"]`)?.value);
    
    // Read configurable thresholds from UI inputs
    const thresholdInput = document.getElementById('late_threshold');
    let graceEndMins = 7 * 60 + 35; // Default: 7:35 AM (TB5 K3)
    if (thresholdInput && thresholdInput.value) {
        const parsed = parseTimeToMinutes(thresholdInput.value);
        if (parsed !== null) graceEndMins = parsed;
    }
    
    const endInput = document.getElementById('end_threshold');
    let schedPmOut = 17 * 60; // Default: 5:00 PM (TB5 L3)
    if (endInput && endInput.value) {
        const parsed = parseTimeToMinutes(endInput.value);
        if (parsed !== null) schedPmOut = parsed;
    }
    
    // Derive schedule from thresholds
    const schedAmIn = graceEndMins - 5; // Scheduled AM start = 5 min before grace end
    const schedAmOut = 12 * 60; // 12:00 PM (lunch start)
    const schedPmIn = 13 * 60; // 1:00 PM (lunch end)
    
    let workMins = 0;
    let lateMins = 0;
    let utMins = 0;
    let otMins = 0;
    
    // Calculate AM work
    if (amIn !== null && amOut !== null) {
        const actualAmIn = Math.max(amIn, schedAmIn);
        const actualAmOut = Math.min(amOut, schedAmOut);
        workMins += Math.max(0, actualAmOut - actualAmIn);
        
        // Late: arrival after grace end time (TB5 K3)
        if (amIn > graceEndMins) {
            lateMins += amIn - graceEndMins;
        }
        if (amOut < schedAmOut) {
            utMins += schedAmOut - amOut;
        }
    }
    
    // Calculate PM work
    if (pmIn !== null && pmOut !== null) {
        const actualPmIn = Math.max(pmIn, schedPmIn);
        const actualPmOut = Math.min(pmOut, schedPmOut);
        workMins += Math.max(0, actualPmOut - actualPmIn);
        
        if (pmIn > schedPmIn) {
            lateMins += pmIn - schedPmIn;
        }
        if (pmOut < schedPmOut) {
            utMins += schedPmOut - pmOut;
        }
    }
    
    // Calculate OT (TB5: OT = otOut - closing time)
    if (otOut !== null && otOut > schedPmOut) {
        otMins = otOut - schedPmOut;
    }
    
    // Calculate half-day deduction (tb5_half_in / tb5_half_out inputs)
    const halfIn = parseTimeToMinutes(document.querySelector(`input[name="tb5_half_in_${day}"]`)?.value);
    const halfOut = parseTimeToMinutes(document.querySelector(`input[name="tb5_half_out_${day}"]`)?.value);
    let halfDed = 0;
    if (halfIn !== null && halfOut !== null && halfOut > halfIn) {
        const halfHrs = (halfOut - halfIn) / 60;
        if (halfHrs > 0 && halfHrs <= 4) {
            halfDed = dailyRate / 2;
        }
    }
    
    const lateHrs = lateMins / 60;
    const utHrs = utMins / 60;
    const otHrs = otMins / 60;
    
    // OT pay = OT rate × OT hours (direct multiplication, no hidden multiplier)
    const manualOtRate = parseFloat(document.getElementById('ot_rate')?.value) || 0;

    // Calculate deductions
    const lateDed = lateHrs * hourlyRate;
    const utDed = utHrs * hourlyRate;
    const otPay = otHrs * manualOtRate;
    
    // Update display using helper
    setCell(`.tb5-work-mins[data-day="${day}"]`, workMins);
    setCell(`.tb5-late-hrs[data-day="${day}"]`, lateHrs.toFixed(2));
    setCell(`.tb5-ut-hrs[data-day="${day}"]`, utHrs.toFixed(2));
    setCell(`.tb5-ot-hrs[data-day="${day}"]`, otHrs.toFixed(2));
    setCell(`.tb5-absent-ded[data-day="${day}"]`, '0.00');
    setCell(`.tb5-late-ded[data-day="${day}"]`, lateDed.toFixed(2));
    setCell(`.tb5-ut-ded[data-day="${day}"]`, utDed.toFixed(2));
    setCell(`.tb5-half-ded[data-day="${day}"]`, halfDed.toFixed(2));
    setCell(`.tb5-ot-pay[data-day="${day}"]`, otPay.toFixed(2));
}

// Parse time string to minutes (expects 24h "HH:MM" format, with 12h AM/PM fallback)
function parseTimeToMinutes(timeStr) {
    if (!timeStr || timeStr.trim() === '') return null;
    
    timeStr = timeStr.trim();
    
    // Handle 24-hour format (HH:MM)
    let match = timeStr.match(/^(\d{1,2}):(\d{2})$/);
    if (match) {
        return parseInt(match[1]) * 60 + parseInt(match[2]);
    }
    
    // Fallback: Handle 12-hour format with AM/PM (for legacy data)
    match = timeStr.match(/^(\d{1,2}):(\d{2})\s*(AM|PM)$/i);
    if (match) {
        let hours = parseInt(match[1]);
        const mins = parseInt(match[2]);
        const period = match[3].toUpperCase();
        
        if (period === 'PM' && hours !== 12) hours += 12;
        if (period === 'AM' && hours === 12) hours = 0;
        
        return hours * 60 + mins;
    }
    
    return null;
}

// Update TB5 totals
function updateTB5Totals() {
    let totalWorkMins = 0;
    let totalLateHrs = 0;
    let totalUtHrs = 0;
    let totalOtHrs = 0;
    let totalAbsentDed = 0;
    let totalLateDed = 0;
    let totalUtDed = 0;
    let totalHalfDed = 0;
    let totalOtPay = 0;
    let daysWorked = 0;
    let absentDays = 0;
    
    for (let day = 1; day <= 31; day++) {
        const workMins = parseInt(document.querySelector(`.tb5-work-mins[data-day="${day}"]`)?.textContent || 0);
        const isAbsent = document.querySelector(`input[name="tb5_absent_${day}"]`)?.checked || false;
        const dateInput = document.querySelector(`input[name="tb5_date_${day}"]`);
        const hasDate = dateInput && dateInput.value;
        
        if (hasDate) {
            if (workMins > 0 && !isAbsent) {
                daysWorked++;
            }
            if (isAbsent) {
                absentDays++;
            }
        }
        
        totalWorkMins += workMins;
        totalLateHrs += parseFloat(document.querySelector(`.tb5-late-hrs[data-day="${day}"]`)?.textContent || 0);
        totalUtHrs += parseFloat(document.querySelector(`.tb5-ut-hrs[data-day="${day}"]`)?.textContent || 0);
        totalOtHrs += parseFloat(document.querySelector(`.tb5-ot-hrs[data-day="${day}"]`)?.textContent || 0);
        totalAbsentDed += parseFloat(document.querySelector(`.tb5-absent-ded[data-day="${day}"]`)?.textContent || 0);
        totalLateDed += parseFloat(document.querySelector(`.tb5-late-ded[data-day="${day}"]`)?.textContent || 0);
        totalUtDed += parseFloat(document.querySelector(`.tb5-ut-ded[data-day="${day}"]`)?.textContent || 0);
        totalHalfDed += parseFloat(document.querySelector(`.tb5-half-ded[data-day="${day}"]`)?.textContent || 0);
        totalOtPay += parseFloat(document.querySelector(`.tb5-ot-pay[data-day="${day}"]`)?.textContent || 0);
    }
    
    // Helper to safely set totals
    const setTotal = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };
    
    setTotal('total_work_mins', totalWorkMins);
    setTotal('total_late_hrs', totalLateHrs.toFixed(2));
    setTotal('total_ut_hrs', totalUtHrs.toFixed(2));
    setTotal('total_ot_hrs', totalOtHrs.toFixed(2));
    setTotal('total_absent_ded', totalAbsentDed.toFixed(2));
    setTotal('total_late_ded', totalLateDed.toFixed(2));
    setTotal('total_ut_ded', totalUtDed.toFixed(2));
    setTotal('total_half_ded', totalHalfDed.toFixed(2));
    setTotal('total_ot_pay', totalOtPay.toFixed(2));
    
    // Update payroll summary fields (TB5: Net = Monthly Salary - Deductions + OT + Trainee Payment)
    const salary = parseFloat(document.getElementById('imported_salary')?.value) || 0;
    
    // Calculate daily rate based on working days (excluding Sundays) in the period
    const periodStart = document.getElementById('imported_period_start')?.value;
    const periodEnd = document.getElementById('imported_period_end')?.value;
    const workingDays = countWorkingDays(periodStart, periodEnd) || 15; // Fallback to 15 if no dates
    const dailyRate = salary / workingDays; // Divide by working days excluding Sundays
    
    const grossPay = salary; // TB5: Base is full monthly salary
    const totalDeductions = totalAbsentDed + totalLateDed + totalUtDed + totalHalfDed;
    const trainingsCost = parseFloat(document.getElementById('imported_trainings_cost')?.value) || 0;
    const netPay = grossPay - totalDeductions + totalOtPay + trainingsCost; // Add trainee payment to net pay
    
    // Set summary values
    const daysOfficeEl = document.getElementById('imported_days_office');
    if (daysOfficeEl) daysOfficeEl.value = daysWorked;
    
    const grossEl = document.getElementById('imported_gross');
    if (grossEl) grossEl.value = grossPay.toFixed(2);
    
    setTotal('summary_total_deductions', '-₱' + totalDeductions.toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_ot_pay', '+₱' + totalOtPay.toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_trainee_payment', '+₱' + trainingsCost.toLocaleString('en-PH', {minimumFractionDigits: 2}));
    setTotal('summary_net_pay', '₱' + netPay.toLocaleString('en-PH', {minimumFractionDigits: 2}));
}

// Update TB5 rates display
function updateTB5Rates(salary) {
    // Check if we have manually entered rates in the main input fields
    const dailyInput = document.getElementById('daily_rate');
    const hourlyInput = document.getElementById('hourly_rate');
    
    let dailyRate, hourlyRate;
    
    // If daily rate input exists and has a value, use it; otherwise calculate from salary
    if (dailyInput && dailyInput.value) {
        dailyRate = parseFloat(dailyInput.value);
        hourlyRate = dailyRate / 8;
    } else {
        dailyRate = salary / 15; // 15 days per cut-off computation
        hourlyRate = dailyRate / 8;
    }
    
    const dailyEl = document.getElementById('tb5_daily_rate');
    const hourlyEl = document.getElementById('tb5_hourly_rate');
    if (dailyEl) dailyEl.textContent = dailyRate.toFixed(2);
    if (hourlyEl) hourlyEl.textContent = hourlyRate.toFixed(2);
    
    // Recalculate all rows if table body exists
    const tbody = document.getElementById('tb5_dtr_body');
    if (tbody && tbody.children.length > 0) {
        for (let day = 1; day <= 31; day++) {
            calculateTB5Row(day);
        }
        updateTB5Totals();
    }
}

// Clear imported data and reset
function clearImportedData() {
    document.getElementById('imported_data_section').style.display = 'none';
    document.getElementById('file_selected_preview').style.display = 'none';
    document.getElementById('import_status').style.display = 'none';
    
    // Clear inputs
    // Clear inputs using helper
    const clearInput = (id) => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    };
    clearInput('imported_employee_name');
    clearInput('imported_salary');
    clearInput('imported_period_start');
    clearInput('imported_period_end');
    clearInput('imported_records_count');
    
    // Clear TB5 table
    const tbody = document.getElementById('tb5_dtr_body');
    if (tbody) tbody.innerHTML = '';
    
    selectedFile = null;
}

// Generate payslip from TB5 data
function generatePayslipFromTB5() {
    const employeeName = document.getElementById('imported_employee_name').value;
    const salary = parseFloat(document.getElementById('imported_salary').value) || 0;
    
    if (!employeeName) {
        alert('Please enter employee name');
        return;
    }
    if (!salary) {
        alert('Please enter basic monthly salary');
        return;
    }
    
    // Collect TB5 data
    const totalAbsentDed = parseFloat(document.getElementById('total_absent_ded').textContent) || 0;
    const totalLateDed = parseFloat(document.getElementById('total_late_ded').textContent) || 0;
    const totalUtDed = parseFloat(document.getElementById('total_ut_ded').textContent) || 0;
    const totalHalfDed = parseFloat(document.getElementById('total_half_ded').textContent) || 0;
    const totalOtPay = parseFloat(document.getElementById('total_ot_pay').textContent) || 0;
    
    const totalDeductions = totalAbsentDed + totalLateDed + totalUtDed + totalHalfDed;
    const netPay = salary - totalDeductions + totalOtPay;
    
    alert(`Payslip Summary for ${employeeName}:\n\nBasic Salary: ₱${salary.toFixed(2)}\nTotal Deductions: ₱${totalDeductions.toFixed(2)}\nOT Pay: ₱${totalOtPay.toFixed(2)}\n\nNet Pay: ₱${netPay.toFixed(2)}`);
}

// ================================
// SAVE DTR TO DATABASE
// ================================
function saveDTRToDatabase() {
    const employeeName = document.getElementById('imported_employee_name')?.value?.trim();
    const salary = parseFloat(document.getElementById('imported_salary')?.value) || 0;
    const periodStart = document.getElementById('imported_period_start')?.value;
    const periodEnd = document.getElementById('imported_period_end')?.value;
    
    // New payroll summary fields
    const daysOffice = parseInt(document.getElementById('imported_days_office')?.value) || 0;
    const grossPay = parseFloat(document.getElementById('imported_gross')?.value) || 0;
    const trainingsCount = parseInt(document.getElementById('imported_trainings_count')?.value) || 0;
    const trainingsCost = parseFloat(document.getElementById('imported_trainings_cost')?.value) || 0;
    
    if (!employeeName) {
        alert('Please enter employee name');
        return;
    }
    
    if (!salary || salary <= 0) {
        alert('Please enter a valid basic monthly salary');
        return;
    }
    
    // Collect DTR data from TB5 table
    const dtrRecords = [];
    for (let day = 1; day <= 31; day++) {
        const dateInput = document.querySelector(`input[name="tb5_date_${day}"]`);
        if (!dateInput || !dateInput.value) continue;

        // Per-row computed values come from <span> elements
        const workMins  = parseFloat(document.querySelector(`.tb5-work-mins[data-day="${day}"]`)?.textContent) || 0;
        const lateHrs   = parseFloat(document.querySelector(`.tb5-late-hrs[data-day="${day}"]`)?.textContent)  || 0;
        const utHrs     = parseFloat(document.querySelector(`.tb5-ut-hrs[data-day="${day}"]`)?.textContent)    || 0;
        const otHrs     = parseFloat(document.querySelector(`.tb5-ot-hrs[data-day="${day}"]`)?.textContent)    || 0;

        const record = {
            dtr_date:          dateInput.value,
            am_in:             document.querySelector(`input[name="tb5_am_in_${day}"]`)?.value  || '',
            am_out:            document.querySelector(`input[name="tb5_am_out_${day}"]`)?.value || '',
            pm_in:             document.querySelector(`input[name="tb5_pm_in_${day}"]`)?.value  || '',
            pm_out:            document.querySelector(`input[name="tb5_pm_out_${day}"]`)?.value || '',
            is_absent:         document.querySelector(`input[name="tb5_absent_${day}"]`)?.checked ? 1 : 0,
            ot_out:            document.querySelector(`input[name="tb5_ot_out_${day}"]`)?.value  || '',
            half_in:           document.querySelector(`input[name="tb5_half_in_${day}"]`)?.value || '',
            half_out:          document.querySelector(`input[name="tb5_half_out_${day}"]`)?.value || '',
            remarks:           document.querySelector(`input[name="tb5_remarks_${day}"]`)?.value || '',
            // Computed fields from span cells
            total_work_hours:  parseFloat((workMins / 60).toFixed(4)),
            late_minutes:      Math.round(lateHrs * 60),
            undertime_hours:   utHrs,
            daily_ot_hours:    otHrs
        };
        
        // Only add records with actual date
        if (record.dtr_date) {
            dtrRecords.push(record);
        }
    }
    
    if (dtrRecords.length === 0) {
        alert('No DTR records to save. Please add at least one day.');
        return;
    }

    // Collect summary totals from TB5 total row spans
    const totalLateHours    = parseFloat(document.getElementById('total_late_hrs')?.textContent)    || 0;
    const totalUndertimeHrs = parseFloat(document.getElementById('total_ut_hrs')?.textContent)      || 0;
    const totalOtHours      = parseFloat(document.getElementById('total_ot_hrs')?.textContent)      || 0;
    const totalAbsentDays   = dtrRecords.filter(r => r.is_absent).length;
    const totalAbsentDeduct = parseFloat(document.getElementById('total_absent_ded')?.textContent)  || 0;
    const totalLateDeduct   = parseFloat(document.getElementById('total_late_ded')?.textContent)    || 0;
    const totalUtDeduct     = parseFloat(document.getElementById('total_ut_ded')?.textContent)      || 0;
    const totalHalfDeduct   = parseFloat(document.getElementById('total_half_ded')?.textContent)    || 0;
    const totalOtPayAmt     = parseFloat(document.getElementById('total_ot_pay')?.textContent)      || 0;
    
    // Show loading
    const saveBtn = document.getElementById('btn_save_dtr');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'save_dtr');
    formData.append('employee_name', employeeName);
    formData.append('salary', salary);
    formData.append('period_start', periodStart);
    formData.append('period_end', periodEnd);
    formData.append('days_office', daysOffice);
    formData.append('gross_pay', grossPay);
    formData.append('trainings_count', trainingsCount);
    formData.append('payment_per_trainee', parseFloat(document.getElementById('imported_payment_per_trainee')?.value) || 0);
    formData.append('trainings_cost', trainingsCost);
    // Summary totals for payroll_computations
    formData.append('total_late_hours',      totalLateHours);
    formData.append('total_undertime_hours', totalUndertimeHrs);
    formData.append('total_ot_hours',        totalOtHours);
    formData.append('total_absent_days',     totalAbsentDays);
    formData.append('total_absent_deduct',   totalAbsentDeduct);
    formData.append('total_late_deduct',     totalLateDeduct);
    formData.append('total_ut_deduct',       totalUtDeduct);
    formData.append('total_half_deduct',     totalHalfDeduct);
    formData.append('total_ot_pay',          totalOtPayAmt);
    // TB5 has no govt deduction rows — default to 0
    formData.append('sss_contribution',       0);
    formData.append('philhealth_contribution', 0);
    formData.append('pagibig_contribution',   0);
    formData.append('dtr_records', JSON.stringify(dtrRecords));

    fetch('save_dtr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Show brief success message and redirect to Payroll List
            alert(`DTR saved successfully!\n\nEmployee: ${data.employee_name}\nRecords Saved: ${data.records_count}`);
            
            // Redirect to Payroll List page
            window.location.href = 'payroll_list.php';
        } else {
            alert('Error saving DTR: ' + (data.message || 'Unknown error'));
        }
    })
    .catch(error => {
        console.error('Save error:', error);
        alert('Error saving DTR: ' + error.message);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

// ================================
// SAVE MAIN DTR TABLE TO DATABASE
// ================================
function saveMainDTRToDatabase() {
    // Get employee info based on current mode
    let employeeId = '';
    let employeeName = '';
    let salary = 0;
    let periodStart = '';
    let periodEnd = '';

    if (currentPayrollMode === 'manual') {
        // Manual mode: get from dropdowns
        const employeeSelect = document.getElementById('employee_select');
        employeeId = document.getElementById('form_employee_id')?.value || '';
        employeeName = employeeSelect
            ? (employeeSelect.options[employeeSelect.selectedIndex]?.getAttribute('data-name') || '')
            : '';
        salary = parseFloat(document.getElementById('basic_monthly_salary')?.value) || 0;
        periodStart = document.getElementById('calculated_start_date')?.value || '';
        periodEnd   = document.getElementById('calculated_end_date')?.value   || '';
    } else {
        // Import mode: get from imported data fields
        employeeName = document.getElementById('imported_employee_name')?.value || '';
        salary = parseFloat(document.getElementById('imported_salary')?.value) || 
                 parseFloat(document.getElementById('basic_monthly_salary')?.value) || 0;
        periodStart = document.getElementById('imported_period_start')?.value || '';
        periodEnd   = document.getElementById('imported_period_end')?.value   || '';
        employeeId = document.getElementById('form_employee_id')?.value || 'imported';
    }

    // Validation
    if (currentPayrollMode === 'manual' && (!employeeId || !employeeName)) {
        alert('Please select an employee first.');
        document.getElementById('employee_select')?.focus();
        return;
    }
    if (currentPayrollMode === 'import' && !employeeName) {
        alert('Please import an Excel file first or enter the employee name.');
        return;
    }
    if (!salary || salary <= 0) {
        alert('Please enter a valid basic monthly salary.');
        document.getElementById('basic_monthly_salary')?.focus();
        return;
    }
    if (!periodStart || !periodEnd) {
        alert('Please select a payroll period (year, month, and cut-off) first.');
        return;
    }

    // Collect all DTR row data
    const dtrRows = document.querySelectorAll('#dtr_rows tr[data-row]');
    if (dtrRows.length === 0) {
        alert('No DTR records to save. Please select a payroll period to generate the date rows first.');
        return;
    }

    const dtrRecords = [];
    dtrRows.forEach(row => {
        const rowNum = row.getAttribute('data-row');
        const dateInput = row.querySelector(`input[name="dtr_date_${rowNum}"]`);
        if (!dateInput || !dateInput.value) return;

        dtrRecords.push({
            dtr_date:       dateInput.value,
            am_in:          row.querySelector(`input[name="am_in_${rowNum}"]`)?.value        || '',
            am_out:         row.querySelector(`input[name="am_out_${rowNum}"]`)?.value       || '',
            pm_in:          row.querySelector(`input[name="pm_in_${rowNum}"]`)?.value        || '',
            pm_out:         row.querySelector(`input[name="pm_out_${rowNum}"]`)?.value       || '',
            is_absent:      row.querySelector(`input[name="absent_${rowNum}"]`)?.checked ? 1 : 0,
            ot_out:         row.querySelector(`input[name="ot_out_${rowNum}"]`)?.value       || '',
            half_in:        row.querySelector(`input[name="halfday_in_${rowNum}"]`)?.value   || '',
            half_out:       row.querySelector(`input[name="halfday_out_${rowNum}"]`)?.value  || '',
            total_work_hours: parseFloat(row.querySelector(`input[name="work_hours_${rowNum}"]`)?.value) || 0,
            late_minutes:   parseFloat(row.querySelector(`input[name="late_mins_${rowNum}"]`)?.value)    || 0,
            undertime_hours:parseFloat(row.querySelector(`input[name="undertime_${rowNum}"]`)?.value)    || 0,
            daily_ot_hours: parseFloat(row.querySelector(`input[name="ot_hours_${rowNum}"]`)?.value)     || 0,
            absent_deduct:  parseFloat(row.querySelector(`input[name="absent_deduct_${rowNum}"]`)?.value)|| 0,
            late_deduct:    parseFloat(row.querySelector(`input[name="late_deduct_${rowNum}"]`)?.value)  || 0,
            undertime_deduct:parseFloat(row.querySelector(`input[name="undertime_deduct_${rowNum}"]`)?.value)||0,
            halfday_deduct: parseFloat(row.querySelector(`input[name="halfday_deduct_${rowNum}"]`)?.value)||0,
            ot_pay:         parseFloat(row.querySelector(`input[name="ot_pay_${rowNum}"]`)?.value)       || 0,
            govt_deduct:    parseFloat(row.querySelector(`input[name="govt_${rowNum}"]`)?.value)         || 0,
            auto_salary:    parseFloat(row.querySelector(`input[name="auto_salary_${rowNum}"]`)?.value)  || 0,
            remarks:        row.querySelector(`input[name="remarks_${rowNum}"]`)?.value                  || ''
        });
    });

    if (dtrRecords.length === 0) {
        alert('No valid DTR date rows found. Please make sure period dates are set.');
        return;
    }

    // Extract SSS, PhilHealth, Pagibig from govt_deduct rows matched by remarks
    let sssContribution      = 0;
    let philhealthContrib    = 0;
    let pagibigContrib       = 0;
    dtrRecords.forEach(r => {
        const rem = (r.remarks || '').toUpperCase().trim();
        if (rem === 'SSS')        sssContribution   = r.govt_deduct;
        else if (rem === 'PHILHEALTH') philhealthContrib = r.govt_deduct;
        else if (rem === 'PAGIBIG')    pagibigContrib    = r.govt_deduct;
    });

    // Collect computed totals from the totals footer row
    const totalWorkMins     = parseFloat(document.getElementById('total_work_mins')?.textContent)      || 0;
    const totalLateHours    = parseFloat(document.getElementById('total_late_hours')?.textContent)     || 0;
    const totalUndertime    = parseFloat(document.getElementById('total_undertime')?.textContent)      || 0;
    const totalOtHours      = parseFloat(document.getElementById('total_ot_hours')?.textContent)       || 0;
    const totalAbsentDays   = parseFloat(document.getElementById('total_absent_days')?.textContent)    || 0;
    const totalAbsentDeduct = parseFloat(document.getElementById('total_absent_deduct')?.textContent)  || 0;
    const totalLateDeduct   = parseFloat(document.getElementById('total_late_deduct')?.textContent)    || 0;
    const totalUtDeduct     = parseFloat(document.getElementById('total_undertime_deduct')?.textContent)||0;
    const totalHalfDeduct   = parseFloat(document.getElementById('total_halfday_deduct')?.textContent) || 0;
    const totalOtPay        = parseFloat(document.getElementById('total_ot_payment')?.textContent)     || 0;
    const totalSalary       = parseFloat(document.getElementById('total_salary')?.textContent)         || 0;
    const daysWorked        = dtrRecords.filter(r => !r.is_absent).length;

    // Show loading state
    const saveBtn = document.getElementById('btn_save_main_dtr');
    const originalHTML = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    // Build form data
    const formData = new FormData();
    formData.append('employee_id',      employeeId);
    formData.append('employee_name',    employeeName);
    formData.append('salary',           salary);
    formData.append('period_start',     periodStart);
    formData.append('period_end',       periodEnd);
    formData.append('days_office',      daysWorked);
    formData.append('gross_pay',        totalSalary);
    formData.append('trainings_count',  0);
    formData.append('payment_per_trainee', 0);
    formData.append('trainings_cost',   0);
    // Totals for payroll_computations
    formData.append('total_late_hours',     totalLateHours);
    formData.append('total_undertime_hours',totalUndertime);
    formData.append('total_ot_hours',       totalOtHours);
    formData.append('total_absent_days',    totalAbsentDays);
    formData.append('total_absent_deduct',  totalAbsentDeduct);
    formData.append('total_late_deduct',    totalLateDeduct);
    formData.append('total_ut_deduct',      totalUtDeduct);
    formData.append('total_half_deduct',    totalHalfDeduct);
    formData.append('total_ot_pay',         totalOtPay);
    formData.append('sss_contribution',      sssContribution);
    formData.append('philhealth_contribution', philhealthContrib);
    formData.append('pagibig_contribution',  pagibigContrib);
    formData.append('dtr_records',  JSON.stringify(dtrRecords));

    fetch('save_dtr.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Success toast-style notification then redirect
            const bar = document.getElementById('dtr_save_bar');
            if (bar) {
                bar.innerHTML = `<div style="width:100%;text-align:center;color:#68d391;font-size:15px;font-weight:600;">
                    <i class="fas fa-check-circle"></i>
                    Saved successfully! (${data.records_count} records for <strong>${data.employee_name}</strong>)
                    &nbsp;&nbsp;<span style="color:#a0aec0;font-weight:400;">Redirecting to Payroll List&hellip;</span>
                </div>`;
            }
            setTimeout(() => { window.location.href = 'payroll_list.php'; }, 1800);
        } else {
            alert('Error saving: ' + (data.message || 'Unknown error'));
            saveBtn.innerHTML = originalHTML;
            saveBtn.disabled = false;
        }
    })
    .catch(err => {
        console.error('Save error:', err);
        alert('Error saving: ' + err.message);
        saveBtn.innerHTML = originalHTML;
        saveBtn.disabled = false;
    });
}

// ================================
// FULL VIEW MODAL
// ================================
function openFullViewModal() {
    const modal = document.getElementById('fullViewModal');
    if (!modal) return;
    
    // Copy employee info to modal
    const setModalText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    };
    
    setModalText('modal_employee_name', document.getElementById('imported_employee_name')?.value);
    setModalText('modal_salary', parseFloat(document.getElementById('imported_salary')?.value || 0).toLocaleString('en-PH', {minimumFractionDigits: 2}));
    
    const periodStart = document.getElementById('imported_period_start')?.value;
    const periodEnd = document.getElementById('imported_period_end')?.value;
    setModalText('modal_period', periodStart && periodEnd ? `${periodStart} to ${periodEnd}` : '-');
    
    // Clone the TB5 table
    const originalTable = document.getElementById('tb5_dtr_table');
    const modalContainer = document.getElementById('modal_dtr_table_container');
    
    if (originalTable && modalContainer) {
        const clonedTable = originalTable.cloneNode(true);
        clonedTable.id = 'modal_tb5_dtr_table';
        modalContainer.innerHTML = '';
        modalContainer.appendChild(clonedTable);
    }
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

// Open DTR Full View from DTR Calculator section
function openDTRFullView() {
    const modal = document.getElementById('fullViewModal');
    if (!modal) return;
    
    // Copy employee info to modal
    const setModalText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value || '-';
    };
    
    // Get employee name from TB5 display or selected employee
    const empName = document.getElementById('tb5_employee_name')?.textContent || 
                   document.getElementById('employee')?.options[document.getElementById('employee')?.selectedIndex]?.text || 
                   'No Employee Selected';
    const empSelect = document.getElementById('employee');
    const selectedOption = empSelect?.options[empSelect.selectedIndex];
    
    setModalText('modal_employee_name', empName);
    setModalText('modal_emp_code', selectedOption?.getAttribute('data-code') || '-');
    setModalText('modal_position', selectedOption?.getAttribute('data-position') || '-');
    setModalText('modal_department', selectedOption?.getAttribute('data-department') || '-');
    
    const basicSalary = document.getElementById('basic_monthly_salary')?.value || '0';
    setModalText('modal_salary', parseFloat(basicSalary).toLocaleString('en-PH', {minimumFractionDigits: 2}));
    
    // Get period from payroll period inputs
    const year = document.getElementById('payroll_year')?.value;
    const month = document.getElementById('payroll_month')?.value;
    const cutoff = document.getElementById('payroll_cutoff')?.value;
    const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
    const monthName = month ? monthNames[parseInt(month) - 1] : '';
    const cutoffText = cutoff === '12' ? '1-15' : cutoff === '27' ? '16-End' : '';
    setModalText('modal_period', year && month && cutoff ? `${monthName} ${year} (${cutoffText})` : 'Select Period');
    
    // Clone the main DTR table
    const originalTable = document.getElementById('main_dtr_table');
    const modalContainer = document.getElementById('modal_dtr_table_container');
    
    if (originalTable && modalContainer) {
        const clonedTable = originalTable.cloneNode(true);
        clonedTable.id = 'modal_main_dtr_table';
        clonedTable.style.fontSize = '11px'; // Smaller font for modal view
        modalContainer.innerHTML = '';
        modalContainer.appendChild(clonedTable);
    }
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeFullViewModal() {
    const modal = document.getElementById('fullViewModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
}

function printDTR() {
    const printContent = document.getElementById('modal_dtr_table_container').innerHTML;
    const employeeName = document.getElementById('modal_employee_name')?.textContent || 'Employee';
    
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>DTR - ${employeeName}</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                table { border-collapse: collapse; width: 100%; font-size: 11px; }
                th, td { border: 1px solid #333; padding: 6px; text-align: center; }
                th { background: #f0f0f0; }
                .th-orange { background: #ffd700; }
                .th-red { background: #ff6b6b; color: white; }
                .th-blue { background: #4299e1; color: white; }
                .th-yellow { background: #f6e05e; }
                .th-green { background: #68d391; }
                .th-pink { background: #fc8181; }
                h1 { text-align: center; margin-bottom: 20px; }
                @media print { body { -webkit-print-color-adjust: exact; } }
            </style>
        </head>
        <body>
            <h1>Daily Time Record - ${employeeName}</h1>
            ${printContent}
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.print();
}

// ================================
// EMPLOYEE DTR CARDS
// ================================
function loadEmployeeDTRCards() {
    const container = document.getElementById('employee_cards_container');
    if (!container) return;
    
    container.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i><br>Loading employees...</div>';
    
    fetch('get_employee_dtr_cards.php')
    .then(response => response.json())
    .then(data => {
        if (data.success && data.employees.length > 0) {
            container.innerHTML = '';
            data.employees.forEach(emp => {
                const card = createEmployeeCard(emp);
                container.appendChild(card);
            });
        } else {
            container.innerHTML = '<div class="no-cards-message"><i class="fas fa-users"></i><br>No employees with DTR records found.<br><small>Import and save DTR data to see employee cards here.</small></div>';
        }
    })
    .catch(error => {
        console.error('Error loading cards:', error);
        container.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading employees</div>';
    });
}

function createEmployeeCard(emp) {
    const card = document.createElement('div');
    card.className = 'employee-card';
    card.onclick = () => openEmployeeDTRModal(emp.id, emp.full_name);
    
    const initials = (emp.full_name || 'U').split(' ').map(n => n[0]).join('').substring(0, 2).toUpperCase();
    
    card.innerHTML = `
        <div class="employee-card-header">
            <div class="employee-avatar">${initials}</div>
            <div>
                <div class="employee-card-name">${emp.full_name || 'Unknown'}</div>
                <div class="employee-card-code">${emp.employee_code || '-'}</div>
            </div>
        </div>
        <div class="employee-card-details">
            <div class="detail-item">
                <span class="detail-label">Position</span>
                <span class="detail-value">${emp.position || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Department</span>
                <span class="detail-value">${emp.department || '-'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Salary</span>
                <span class="detail-value">₱${parseFloat(emp.basic_monthly_salary || 0).toLocaleString()}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">DTR Records</span>
                <span class="detail-value">${emp.dtr_count || 0}</span>
            </div>
        </div>
        <div class="employee-card-footer">
            <span class="dtr-count-badge">${emp.dtr_count || 0} records</span>
            <span class="view-dtr-link"><i class="fas fa-eye"></i> View DTR</span>
        </div>
    `;
    
    return card;
}

// ================================
// EMPLOYEE DTR MODAL
// ================================
let currentEmployeeId = null;

function openEmployeeDTRModal(employeeId, employeeName) {
    currentEmployeeId = employeeId;
    const modal = document.getElementById('employeeDTRModal');
    if (!modal) return;
    
    document.getElementById('emp_modal_name').textContent = employeeName + ' - DTR';
    
    // Load available months
    loadAvailableMonths(employeeId);
    
    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeEmployeeDTRModal() {
    const modal = document.getElementById('employeeDTRModal');
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    currentEmployeeId = null;
}

function loadAvailableMonths(employeeId) {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    
    select.innerHTML = '<option value="">Loading...</option>';
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i></div>';
    
    fetch(`get_employee_dtr_months.php?employee_id=${employeeId}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.months.length > 0) {
            select.innerHTML = '<option value="">-- Select Month --</option>';
            data.months.forEach(month => {
                const opt = document.createElement('option');
                opt.value = month.month_key;
                opt.textContent = month.month_name + ' (' + month.record_count + ' records)';
                select.appendChild(opt);
            });
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a month to view DTR records</div>';
        } else {
            select.innerHTML = '<option value="">No records found</option>';
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-times"></i><br>No DTR records found for this employee</div>';
        }
    })
    .catch(error => {
        console.error('Error loading months:', error);
        select.innerHTML = '<option value="">Error loading</option>';
    });
}

function loadEmployeeDTRByMonth() {
    const select = document.getElementById('dtr_month_select');
    const content = document.getElementById('employee_dtr_content');
    const monthKey = select.value;
    
    if (!monthKey || !currentEmployeeId) {
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-calendar-alt"></i><br>Select a month to view DTR records</div>';
        return;
    }
    
    content.innerHTML = '<div class="loading-cards"><i class="fas fa-spinner fa-spin"></i><br>Loading DTR...</div>';
    
    fetch(`get_employee_dtr_data.php?employee_id=${currentEmployeeId}&month=${monthKey}`)
    .then(response => response.json())
    .then(data => {
        if (data.success && data.records.length > 0) {
            content.innerHTML = buildDTRTable(data.records, data.employee_info);
        } else {
            content.innerHTML = '<div class="no-cards-message"><i class="fas fa-file-alt"></i><br>No records for this month</div>';
        }
    })
    .catch(error => {
        console.error('Error loading DTR:', error);
        content.innerHTML = '<div class="no-cards-message"><i class="fas fa-exclamation-circle"></i><br>Error loading DTR records</div>';
    });
}

function buildDTRTable(records, empInfo) {
    let html = `
        <div class="modal-employee-info">
            <div class="info-item"><strong>Employee:</strong> ${empInfo.full_name}</div>
            <div class="info-item"><strong>Code:</strong> ${empInfo.employee_code}</div>
            <div class="info-item"><strong>Position:</strong> ${empInfo.position || '-'}</div>
            <div class="info-item"><strong>Department:</strong> ${empInfo.department || '-'}</div>
        </div>
        <div class="modal-table-container">
            <table class="tb5-dtr-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="th-orange">AM In</th>
                        <th class="th-orange">PM Out</th>
                        <th class="th-red">Absent</th>
                        <th class="th-blue">OT Out</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
    `;
    
    records.forEach(rec => {
        const isAbsent = rec.is_absent == 1;
        html += `
            <tr class="${isAbsent ? 'absent-row' : ''}">
                <td>${rec.dtr_date}</td>
                <td>${rec.am_time_in || '-'}</td>
                <td>${rec.pm_time_out || '-'}</td>
                <td>${isAbsent ? '<i class="fas fa-times-circle" style="color:red;"></i>' : '-'}</td>
                <td>${rec.ot_time_out || '-'}</td>
                <td>${rec.remarks || '-'}</td>
            </tr>
        `;
    });
    
    html += '</tbody></table></div>';
    return html;
}

// Start manual entry - requires employee and period selection
function startManualEntry() {
    const employeeSelect = document.getElementById('employee_select');
    const periodSelect = document.getElementById('payroll_period');
    
    if (!employeeSelect.value) {
        alert('Please select an employee first.');
        employeeSelect.focus();
        return;
    }
    
    if (!periodSelect.value) {
        alert('Please select a payroll period first.');
        periodSelect.focus();
        return;
    }
    
    const selectedEmployee = employeeSelect.options[employeeSelect.selectedIndex];
    const selectedPeriod = periodSelect.options[periodSelect.selectedIndex];
    
    // Set hidden form fields
    document.getElementById('form_employee_id').value = employeeSelect.value;
    document.getElementById('form_payroll_period_id').value = periodSelect.value;
    
    // Set employee display fields if they exist
    const empInfo = {
        employee_code: selectedEmployee.dataset.code || '',
        full_name: selectedEmployee.dataset.name || '',
        position: selectedEmployee.dataset.position || '',
        department: selectedEmployee.dataset.department || '',
        basic_monthly_salary: selectedEmployee.dataset.salary || 0
    };
    
    const empCodeDisplay = document.getElementById('display_emp_code');
    const empNameDisplay = document.getElementById('display_emp_name');
    const empPositionDisplay = document.getElementById('display_position');
    const empDeptDisplay = document.getElementById('display_department');
    const basicSalaryField = document.getElementById('basic_monthly_salary');
    
    if (empCodeDisplay) empCodeDisplay.textContent = empInfo.employee_code;
    if (empNameDisplay) empNameDisplay.textContent = empInfo.full_name;
    if (empPositionDisplay) empPositionDisplay.textContent = empInfo.position;
    if (empDeptDisplay) empDeptDisplay.textContent = empInfo.department;
    if (basicSalaryField) basicSalaryField.value = empInfo.basic_monthly_salary;
    
    // Update TB5 header display
    updateTB5Header(empInfo.full_name, empInfo.basic_monthly_salary, selectedPeriod);
    
    // Generate DTR rows for the period dates
    generateDTRRowsForPeriod(selectedPeriod);
    
    // Show the payslip form
    document.getElementById('payslip_form').style.display = 'block';
    
    // Scroll to the form
    document.getElementById('payslip_form').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// Generate DTR rows for the selected period
function generateDTRRowsForPeriod(periodOption) {
    const startDate = new Date(periodOption.dataset.start);
    const endDate = new Date(periodOption.dataset.end);
    
    const dtrRows = document.getElementById('dtr_rows');
    if (!dtrRows) return;
    
    dtrRows.innerHTML = '';
    
    let rowNum = 1;
    let currentDate = new Date(startDate);
    
    while (currentDate <= endDate) {
        const dateStr = currentDate.toISOString().split('T')[0];
        const row = createDTRRowFromData(rowNum, { dtr_date: dateStr });
        dtrRows.appendChild(row);
        rowNum++;
        currentDate.setDate(currentDate.getDate() + 1);
    }
    
    updateRowCount();
    attachDTRListeners();
}

// Handle file selection
function handleFileSelect(file) {
    const validExtensions = ['.xlsx', '.xls', '.xlsm', '.csv'];
    const fileName = file.name.toLowerCase();
    const isValid = validExtensions.some(ext => fileName.endsWith(ext));
    
    if (!isValid) {
        showImportStatus('error', 'Invalid file type. Please select an Excel or CSV file (.xlsx, .xls, .xlsm, .csv)');
        return;
    }
    
    selectedFile = file;
    
    // Show file preview in compact UI
    const preview = document.getElementById('file_selected_preview');
    if (preview) {
        const fileIcon = fileName.endsWith('.csv') ? 'fa-file-csv' : 'fa-file-excel';
        const fileSize = (file.size / 1024).toFixed(1) + ' KB';
        preview.innerHTML = `
            <i class="fas ${fileIcon}"></i>
            <span>${file.name} (${fileSize})</span>
            <button type="button" class="btn-remove-file" onclick="removeSelectedFile(event)">
                <i class="fas fa-times"></i>
            </button>
        `;
        preview.style.display = 'flex';
    }
    
    hideImportStatus();
}

// Remove selected file
function removeSelectedFile(e) {
    if (e) e.stopPropagation();
    selectedFile = null;
    
    const fileInput = document.getElementById('dtr_excel_file');
    if (fileInput) fileInput.value = '';
    
    // Hide file preview
    const preview = document.getElementById('file_selected_preview');
    if (preview) preview.style.display = 'none';
    
    hideImportStatus();
}

// Show import status message
function showImportStatus(type, message) {
    const statusEl = document.getElementById('import_status');
    if (!statusEl) return;
    statusEl.className = 'import-status-compact ' + type;
    statusEl.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'spinner fa-spin'}"></i> ${message}`;
    statusEl.style.display = 'flex';
}

// Hide import status
function hideImportStatus() {
    const statusEl = document.getElementById('import_status');
    if (statusEl) statusEl.style.display = 'none';
}

// Import DTR from Excel - Primary method (extracts employee info from file)
function importDTRFromExcel() {
    if (!selectedFile) {
        showImportStatus('error', 'Please select an Excel file first.');
        return;
    }
    
    showImportStatus('loading', 'Importing DTR data from Excel...');
    
    const formData = new FormData();
    formData.append('excel_file', selectedFile);
    
    // Optional: if manual selection was made, include it
    const employeeSelect = document.getElementById('employee_select');
    const periodSelect = document.getElementById('payroll_period');
    if (employeeSelect && employeeSelect.value) formData.append('employee_id', employeeSelect.value);
    if (periodSelect && periodSelect.value) formData.append('payroll_period_id', periodSelect.value);
    
    fetch('import_dtr.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        // Get response text first to handle HTML error pages
        return response.text().then(text => {
            console.log('=== RAW SERVER RESPONSE ===');
            console.log('Status:', response.status);
            console.log('Content-Type:', response.headers.get('Content-Type'));
            console.log('First 500 chars:', text.substring(0, 500));
            console.log('Last 200 chars:', text.substring(Math.max(0, text.length - 200)));
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
            }
            try {
                const json = JSON.parse(text);
                console.log('Parsed JSON successfully:', json);
                return json;
            } catch (e) {
                // Response is not JSON - likely an HTML error page or PHP error
                console.error('JSON parse error:', e);
                console.error('Full response text:', text);
                
                // Try to extract useful error info from HTML
                const match = text.match(/<b>(.+?)<\/b>/);
                const errorHint = match ? match[1] : 'Unknown error';
                
                throw new Error(`Server error: ${errorHint}\n\nCheck browser console for full response.`);
            }
        });
    })
    .then(data => {
        if (data.success) {
            console.log('Import successful, records:', data.records_count);
            
            // Debug: Show time values from imported data  
            if (data.dtr_data && data.dtr_data.length > 0) {
                const sample = data.dtr_data[0];
                console.log('First record time values:', {
                    date: sample.dtr_date,
                    am_in: sample.am_time_in,
                    am_out: sample.am_time_out,
                    pm_in: sample.pm_time_in,
                    pm_out: sample.pm_time_out
                });
            }
            if (data.debug_time_values) {
                console.log('Server time debug:', data.debug_time_values);
            }
            
            // Show preview mode message - data NOT saved yet
            const empInfo = data.employee_info || {};
            const isExisting = empInfo.is_existing || false;
            const empName = empInfo.full_name || 'Unknown';
            
            let statusMsg = `Extracted ${data.records_count} DTR records for "${empName}". `;
            if (isExisting) {
                statusMsg += '<strong>Employee found!</strong> Saving will UPDATE existing records.';
            } else {
                statusMsg += '<strong>New employee.</strong> Saving will CREATE new records.';
            }
            statusMsg += '<br><em>Click "Save to Payroll List" to save to database.</em>';
            
            showImportStatus('success', statusMsg);
            
            // Set schedule thresholds from Excel (late start, end time) BEFORE calculations
            if (data.schedule_thresholds) {
                const lateThInput = document.getElementById('late_threshold');
                const endThInput = document.getElementById('end_threshold');
                if (data.schedule_thresholds.late_threshold && lateThInput) {
                    lateThInput.value = data.schedule_thresholds.late_threshold;
                    console.log('Set late threshold from Excel:', data.schedule_thresholds.late_threshold);
                }
                if (data.schedule_thresholds.end_time && endThInput) {
                    endThInput.value = data.schedule_thresholds.end_time;
                    console.log('Set end time from Excel:', data.schedule_thresholds.end_time);
                }
            }
            
            // Show the imported data section (employee info review)
            showImportedDataSection(data);
            
            // Populate the MAIN DTR Calculator table with imported data
            populateMainDTRFromImport(data);
            
            // Populate trainee payment from imported trainee summary
            const traineeSummary = data.trainee_summary || {};
            const importedCount = traineeSummary.total_count || 0;
            const importedPayPerUnit = traineeSummary.pay_per_unit || 0;
            const importedTotal = traineeSummary.total_cost || 0;
            
            if (importedTotal > 0 || importedCount > 0) {
                // Set imported section fields
                const impCountEl = document.getElementById('imported_trainings_count');
                const impPayEl = document.getElementById('imported_payment_per_trainee');
                const impCostEl = document.getElementById('imported_trainings_cost');
                if (impCountEl) impCountEl.value = importedCount;
                if (impPayEl) impPayEl.value = importedPayPerUnit.toFixed(2);
                if (impCostEl) impCostEl.value = importedTotal.toFixed(2);
                
                // Sync to main trainee form
                const mainCountEl = document.getElementById('trainee_count_main');
                const mainPayEl = document.getElementById('trainee_payment_per_main');
                const mainTotalEl = document.getElementById('trainee_total_main');
                if (mainCountEl) mainCountEl.value = importedCount;
                if (mainPayEl) mainPayEl.value = importedPayPerUnit.toFixed(2);
                if (mainTotalEl) mainTotalEl.value = importedTotal.toFixed(2);
                
                console.log('Trainee data populated from import:', importedCount, 'trainees @', importedPayPerUnit, '= total', importedTotal);
            }
            
            // Scroll to the DTR Calculator
            const dtrSection = document.querySelector('.dtr-section');
            if (dtrSection) {
                setTimeout(() => dtrSection.scrollIntoView({ behavior: 'smooth', block: 'start' }), 300);
            }
        } else {
            let errorMsg = 'Import failed: ' + (data.message || 'Unknown error');
            
            // Add debug information if available
            if (data.debug_info) {
                errorMsg += `<div class="debug-info" style="margin-top: 10px; padding: 12px; background: #f8f9fa; border-radius: 4px; font-size: 12px; font-family: monospace; word-break: break-all; max-height: 400px; overflow-y: auto; line-height: 1.6; white-space: pre-wrap;">
                    <strong>Debug Info:</strong><br>${data.debug_info.replace(/\s*\|\s*/g, '<br>')}
                </div>`;
            }
            
            // Add column map if available
            if (data.column_map && Object.keys(data.column_map).length > 0) {
                errorMsg += `<div class="debug-info" style="margin-top: 5px; padding: 10px; background: #f8f9fa; border-radius: 4px; font-size: 12px; font-family: monospace;">
                    <strong>Column Map:</strong> ${JSON.stringify(data.column_map)}
                </div>`;
            }
            
            if (data.error_details) {
                errorMsg += `<div class="error-details">${data.error_details}</div>`;
            }
            
            showImportStatusHTML('error', errorMsg);
        }
    })
    .catch(error => {
        console.error('Import error:', error);
        showImportStatusHTML('error', `Import failed: ${error.message}<div class="error-details">Check console for details. The Excel file may not match TB5 format.</div>`);
    });
}

// Show import status with HTML support
function showImportStatusHTML(type, htmlContent) {
    const statusEl = document.getElementById('import_status');
    if (!statusEl) return;
    statusEl.className = 'import-status-compact ' + type;
    statusEl.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'spinner fa-spin'}"></i> <div>${htmlContent}</div>`;
    statusEl.style.display = 'flex';
}

// Show imported data section with employee info and TB5 table
function showImportedDataSection(data) {
    const section = document.getElementById('imported_data_section');
    if (!section) {
        console.error('imported_data_section element not found');
        return;
    }
    section.style.display = 'block';
    
    const empInfo = data.employee_info || {};
    const periodInfo = data.period_info || {};
    
    // Helper to safely set input values
    const setInput = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.value = value;
    };
    
    // Populate editable fields
    setInput('imported_employee_name', empInfo.full_name || '');
    setInput('imported_salary', empInfo.basic_monthly_salary || '13000');
    setInput('imported_period_start', periodInfo.start_date || '');
    setInput('imported_period_end', periodInfo.end_date || '');
    setInput('imported_records_count', data.records_count || '0');
    
    // Update rate displays
    const salary = parseFloat(empInfo.basic_monthly_salary) || 13000;
    updateTB5Rates(salary);
}

// Populate the MAIN DTR Calculator table with imported data
function populateMainDTRFromImport(data) {
    const dtrData = data.dtr_data || [];
    const empInfo = data.employee_info || {};
    const periodInfo = data.period_info || {};
    
    console.log('populateMainDTRFromImport called with', dtrData.length, 'records');
    if (dtrData.length > 0) {
        console.log('Sample record:', JSON.stringify(dtrData[0]));
        // Detailed time debug
        dtrData.slice(0, 3).forEach((rec, i) => {
            console.log(`Record ${i}: date=${rec.dtr_date} am_in=${rec.am_time_in} pm_out=${rec.pm_time_out} work_hrs=${rec.total_work_hours}`);
        });
    }
    
    // Helper function to set time values after element is appended to DOM
    function setTimeAfterAppend(rowNum, field, value) {
        if (!value && value !== 0) return;
        
        const normalized = normalizeTimeValue(value);
        if (!normalized) return;
        
        const input = document.querySelector(`input[name="${field}_${rowNum}"]`);
        if (input) {
            input.value = normalized;
            if (rowNum <= 3) {
                console.log(`Set ${field}_${rowNum} = "${normalized}" (from "${value}")`);
            }
        } else {
            console.warn(`Input not found: ${field}_${rowNum}`);
        }
    }
    
    // Show the payslip form (DTR Calculator section)
    const payslipForm = document.getElementById('payslip_form');
    if (payslipForm) payslipForm.style.display = 'block';
    
    // Set employee name in DTR Calculator header
    const tb5NameEl = document.getElementById('tb5_employee_name');
    if (tb5NameEl) tb5NameEl.textContent = empInfo.full_name || '-';
    
    // Set basic salary (DON'T call updateAllRates yet - wait until rows exist)
    const basicSalaryField = document.getElementById('basic_monthly_salary');
    if (basicSalaryField && empInfo.basic_monthly_salary) {
        basicSalaryField.value = empInfo.basic_monthly_salary;
    }
    
    // Map imported data by INDEX (sequential order) rather than day number
    // This ensures Excel row 6 → DTR row 1, Excel row 7 → DTR row 2, etc.
    // This is more reliable than matching by day number which breaks across months
    const dataByIndex = {};
    if (Array.isArray(dtrData)) {
        dtrData.forEach((record, index) => {
            dataByIndex[index + 1] = record;  // 1-indexed to match row numbers
        });
    }
    
    console.log('Data mapped by index:', Object.keys(dataByIndex).length, 'rows -', Object.keys(dataByIndex).join(','));
    
    // Clear existing DTR rows and generate fresh ones
    const dtrRows = document.getElementById('dtr_rows');
    if (!dtrRows) {
        console.error('dtr_rows element not found');
        return;
    }
    dtrRows.innerHTML = '';
    
    // Determine date range from period info or imported data
    let startDate = periodInfo.start_date ? new Date(periodInfo.start_date + 'T00:00:00') : null;
    let endDate = periodInfo.end_date ? new Date(periodInfo.end_date + 'T00:00:00') : null;
    
    // If no period info, try to infer from DTR data
    if (!startDate && dtrData.length > 0) {
        const dates = dtrData.filter(r => r.dtr_date).map(r => new Date(r.dtr_date + 'T00:00:00'));
        if (dates.length > 0) {
            startDate = new Date(Math.min(...dates));
            endDate = new Date(Math.max(...dates));
        }
    }
    
    // Generate rows using createDTRRowFromData (embeds values directly in HTML - reliable)
    let rowNum = 1;
    let populatedCount = 0;
    
    if (startDate && endDate) {
        for (let date = new Date(startDate); date <= endDate; date.setDate(date.getDate() + 1)) {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            const dateStr = `${y}-${m}-${d}`;
            
            // Use imported data by ROW INDEX (not day number) for reliable sequential mapping
            const record = dataByIndex[rowNum] || { dtr_date: dateStr };
            const row = createDTRRowFromData(rowNum, record);
            dtrRows.appendChild(row);
            
            // CRITICAL: Set time values AFTER appendChild for HTML5 time inputs
            if (dataByIndex[rowNum]) {
                const rec = dataByIndex[rowNum];
                setTimeAfterAppend(rowNum, 'am_in', rec.am_time_in);
                setTimeAfterAppend(rowNum, 'pm_out', rec.pm_time_out);
                setTimeAfterAppend(rowNum, 'ot_out', rec.ot_time_out);
                setTimeAfterAppend(rowNum, 'halfday_in', rec.halfday_in);
                setTimeAfterAppend(rowNum, 'halfday_out', rec.halfday_out);
                populatedCount++;
            }
            rowNum++;
        }
    } else {
        // Fallback: generate 31 rows using sequential index
        for (let i = 1; i <= 31; i++) {
            const record = dataByIndex[i] || { dtr_date: '' };
            const row = createDTRRowFromData(i, record);
            dtrRows.appendChild(row);
            
            // Set time values after append
            if (dataByIndex[i]) {
                const rec = dataByIndex[i];
                setTimeAfterAppend(i, 'am_in', rec.am_time_in);
                setTimeAfterAppend(i, 'pm_out', rec.pm_time_out);
                setTimeAfterAppend(i, 'ot_out', rec.ot_time_out);
                setTimeAfterAppend(i, 'halfday_in', rec.halfday_in);
                setTimeAfterAppend(i, 'halfday_out', rec.halfday_out);
                populatedCount++;
            }
        }
        rowNum = 32;
    }
    
    updateRowCount();
    attachDTRListeners();
    
    // NOW compute rates (after rows exist) - this sets window.dtrRates
    updateAllRates();
    
    // Recalculate all rows using requestAnimationFrame to ensure DOM is rendered
    requestAnimationFrame(function() {
        console.log('Running RAF recalculation...');
        
        // Ensure rates are set before calculating
        if (!window.dtrRates || !window.dtrRates.perMin) {
            console.log('Re-creating dtrRates before recalculation...');
            updateAllRates();
        }
        
        const allRows = dtrRows.querySelectorAll('tr[data-row]');
        let recalculated = 0;
        allRows.forEach(row => {
            const rn = parseInt(row.getAttribute('data-row'));
            try {
                calculateRowDTR(rn);
                recalculated++;
            } catch(e) {
                console.error('Error calculating row', rn, ':', e);
            }
        });
        calculateTotals();
        console.log('RAF recalculation done:', recalculated, 'rows');
        
        // Double-check with setTimeout for extra safety
        setTimeout(function() {
            console.log('Running delayed recalculation...');
            try {
                // Make sure rates are still valid
                if (!window.dtrRates || !window.dtrRates.perMin) {
                    updateAllRates();
                }
                
                const rows = dtrRows.querySelectorAll('tr[data-row]');
                let recalcCount = 0;
                rows.forEach(row => {
                    const rn = parseInt(row.getAttribute('data-row'));
                    const amIn = document.querySelector(`input[name="am_in_${rn}"]`);
                    const workHrs = document.querySelector(`input[name="work_hours_${rn}"]`);
                    if (amIn && amIn.value) {
                        const beforeVal = workHrs?.value;
                        calculateRowDTR(rn);
                        const afterVal = workHrs?.value;
                        if (beforeVal !== afterVal) {
                            console.log(`Row ${rn}: amIn=${amIn.value}, workHrs ${beforeVal} → ${afterVal}`);
                        }
                        recalcCount++;
                    }
                });
                if (recalcCount > 0) calculateTotals();
                console.log(`Delayed recalc complete: ${recalcCount} rows with data`);
            } catch(e) {
                console.error('Delayed recalculation error:', e);
            }
        }, 500);
    });
    
    console.log('Populated main DTR Calculator with', rowNum - 1, 'rows (' + populatedCount + ' had imported data from', Object.keys(dataByIndex).length, 'records)');
}

// Populate TB5 table from imported DTR data (legacy)
function populateTB5FromImport(dtrData) {
    const tbody = document.getElementById('tb5_dtr_body');
    if (!tbody) {
        console.error('tb5_dtr_body element not found');
        return;
    }
    tbody.innerHTML = '';
    
    // Generate 31 rows first
    for (let day = 1; day <= 31; day++) {
        const row = createTB5Row(day, '');
        tbody.appendChild(row);
    }
    
    // Handle empty or invalid DTR data
    if (!dtrData || !Array.isArray(dtrData)) {
        console.warn('No DTR data to populate or data is not an array');
        dtrData = [];
    }
    
    // Map imported data by day number
    const dataByDay = {};
    dtrData.forEach(record => {
        if (record.dtr_date) {
            const date = new Date(record.dtr_date);
            const dayNum = date.getDate();
            dataByDay[dayNum] = record;
        }
    });
    
    // Populate rows with imported data
    for (let day = 1; day <= 31; day++) {
        const record = dataByDay[day];
        if (record) {
            // Set date
            if (record.dtr_date) {
                const dateInput = document.querySelector(`input[name="tb5_date_${day}"]`);
                if (dateInput) dateInput.value = record.dtr_date;
            }
            
            // Set times (convert from 24h to 12h for display)
            setTB5TimeInput(`tb5_am_in_${day}`, record.am_time_in);
            setTB5TimeInput(`tb5_am_out_${day}`, record.am_time_out);
            setTB5TimeInput(`tb5_pm_in_${day}`, record.pm_time_in);
            setTB5TimeInput(`tb5_pm_out_${day}`, record.pm_time_out);
            setTB5TimeInput(`tb5_ot_out_${day}`, record.ot_time_out);
            setTB5TimeInput(`tb5_half_in_${day}`, record.halfday_in);
            setTB5TimeInput(`tb5_half_out_${day}`, record.halfday_out);
            
            // Set absent checkbox
            if (record.is_absent) {
                const absentCheckbox = document.querySelector(`input[name="tb5_absent_${day}"]`);
                if (absentCheckbox) absentCheckbox.checked = true;
            }
        }
    }
    
    attachTB5Listeners();
    
    // Calculate all rows
    for (let day = 1; day <= 31; day++) {
        calculateTB5Row(day);
    }
    updateTB5Totals();
}

// Normalize time value from various formats (Excel decimal, HH:MM, H:MM AM/PM, etc.)
function normalizeTimeValue(value) {
    if (!value && value !== 0) return '';
    
    // Handle numeric/decimal values (Excel time format: 0.3333 = 8:00 AM)
    if (typeof value === 'number' || (typeof value === 'string' && !isNaN(parseFloat(value)) && value.indexOf(':') === -1)) {
        const numVal = parseFloat(value);
        
        // Standard Excel time fraction (0 < val < 1)
        if (numVal > 0 && numVal < 1) {
            const totalSeconds = Math.round(numVal * 86400);
            const hours = Math.floor(totalSeconds / 3600);
            const minutes = Math.floor((totalSeconds % 3600) / 60);
            return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
        }
        
        // Date+time serial (e.g., 45678.333 = date + 8:00 AM) - extract time portion
        if (numVal >= 1 && numVal < 100000) {
            const timeFraction = numVal - Math.floor(numVal);
            if (timeFraction > 0) {
                const totalSeconds = Math.round(timeFraction * 86400);
                const hours = Math.floor(totalSeconds / 3600);
                const minutes = Math.floor((totalSeconds % 3600) / 60);
                if (hours >= 0 && hours <= 23) {
                    return String(hours).padStart(2, '0') + ':' + String(minutes).padStart(2, '0');
                }
            }
        }
        
        // Integer hour (e.g., 8, 13, 17)
        if (numVal >= 1 && numVal <= 23 && numVal === Math.floor(numVal)) {
            return String(Math.floor(numVal)).padStart(2, '0') + ':00';
        }
    }
    
    // Convert to string for pattern matching
    const strVal = String(value).trim();
    if (!strVal || strVal === '0') return '';
    
    // Already in HH:MM or H:MM:SS format
    let match = strVal.match(/^(\d{1,2}):(\d{2})(?::\d{2})?$/);
    if (match) {
        const h = parseInt(match[1]);
        const m = parseInt(match[2]);
        if (h >= 0 && h <= 23 && m >= 0 && m <= 59) {
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
    }
    
    // AM/PM format: "8:00 AM", "8:05:00 AM", "12:30 PM"
    match = strVal.match(/^(\d{1,2}):?(\d{2})?(?::\d{2})?\s*(AM|PM)$/i);
    if (match) {
        let h = parseInt(match[1]);
        const m = match[2] ? parseInt(match[2]) : 0;
        const period = match[3].toUpperCase();
        
        if (period === 'PM' && h !== 12) h += 12;
        else if (period === 'AM' && h === 12) h = 0;
        
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    
    // Date+time string with AM/PM: "1/0/1900 8:00:00 AM" or "2/26/2026 8:00 AM"
    match = strVal.match(/(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)/i);
    if (match) {
        let h = parseInt(match[1]);
        const m = parseInt(match[2]);
        const period = match[3].toUpperCase();
        
        if (period === 'PM' && h !== 12) h += 12;
        else if (period === 'AM' && h === 12) h = 0;
        
        return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
    }
    
    // 24-hour time embedded in string: "Date 08:00"
    match = strVal.match(/\b(\d{1,2}):(\d{2})\b/);
    if (match) {
        const h = parseInt(match[1]);
        const m = parseInt(match[2]);
        if (h >= 0 && h <= 23 && m >= 0 && m <= 59) {
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
    }
    
    // Dot-separated: "8.00" or "8.30"
    match = strVal.match(/^(\d{1,2})\.(\d{2})$/);
    if (match) {
        const h = parseInt(match[1]);
        const m = parseInt(match[2]);
        if (h >= 0 && h <= 23 && m >= 0 && m <= 59) {
            return String(h).padStart(2, '0') + ':' + String(m).padStart(2, '0');
        }
    }
    
    // Just hour number: "8" or "17"
    match = strVal.match(/^(\d{1,2})$/);
    if (match) {
        const h = parseInt(match[1]);
        if (h >= 1 && h <= 23) {
            return String(h).padStart(2, '0') + ':00';
        }
    }
    
    // Return original if no pattern matched
    return strVal;
}

// Auto-format 24-hour military time as user types (HH:MM format)
function formatTime24(input) {
    // Get current value, remove non-digits except colon
    let value = input.value.replace(/[^\d:]/g, '');
    
    // Check if user already typed a colon
    const hasColon = value.includes(':');
    
    if (hasColon) {
        // Already has colon - just validate
        let parts = value.split(':');
        let hours = parts[0] || '';
        let mins = parts[1] || '';
        
        // Remove leading zeros from hours (allow single digit like 8:00)
        hours = hours.replace(/^0+/, '') || '0';
        
        // Validate hours (0-23)
        let h = parseInt(hours) || 0;
        if (h > 23) hours = '23';
        
        // Validate minutes (00-59), keep 2 digits
        if (mins.length >= 2) {
            let m = parseInt(mins.substring(0, 2)) || 0;
            if (m > 59) mins = '59';
            else mins = mins.substring(0, 2);
        }
        
        value = hours + ':' + mins;
    } else {
        // No colon yet - auto-insert after hours portion
        let digits = value.replace(/:/g, '');
        
        // Limit to 4 digits
        if (digits.length > 4) {
            digits = digits.substring(0, 4);
        }
        
        // Auto-insert colon: after 2+ digits, treat first 1-2 as hours
        if (digits.length >= 3) {
            // Check if first digit is > 2, treat as single-digit hour
            let firstDigit = parseInt(digits[0]);
            let hours, mins;
            
            if (firstDigit > 2 || (firstDigit === 2 && parseInt(digits[1]) > 3)) {
                // Single digit hour (3-9, or 24+)
                hours = digits.substring(0, 1);
                mins = digits.substring(1, 3);
            } else {
                // Double digit hour (0-23)
                hours = digits.substring(0, 2);
                mins = digits.substring(2, 4);
            }
            
            // Validate hours
            let h = parseInt(hours) || 0;
            if (h > 23) hours = '23';
            
            // Validate minutes
            if (mins.length >= 2) {
                let m = parseInt(mins) || 0;
                if (m > 59) mins = '59';
            }
            
            value = hours + ':' + mins;
        } else {
            value = digits;
        }
    }
    
    // Update the input
    if (input.value !== value) {
        input.value = value;
    }
    
    // Trigger calculation if complete time entered (H:MM or HH:MM)
    if (value.match(/^\d{1,2}:\d{2}$/)) {
        const rowNum = input.getAttribute('data-row');
        if (rowNum) {
            calculateRowDTR(rowNum);
        }
    }
}

// Set TB5 time input value in 24h format
function setTB5TimeInput(inputName, value) {
    const input = document.querySelector(`input[name="${inputName}"]`);
    if (!input) return;
    
    // Use normalizeTimeValue to handle all Excel formats
    const normalized = normalizeTimeValue(value);
    input.value = normalized;
}

// Show extracted employee info preview
function showExtractedInfoPreview(data) {
    const preview = document.getElementById('extracted_info_preview');
    if (!preview) return;
    
    const empInfo = data.employee_info || {};
    const periodInfo = data.period_info || {};
    
    document.getElementById('preview_employee_name').textContent = empInfo.full_name || '-';
    document.getElementById('preview_employee_code').textContent = empInfo.employee_code || '-';
    
    const salaryEl = document.getElementById('preview_salary');
    if (salaryEl) {
        salaryEl.textContent = empInfo.basic_monthly_salary ? 
            '₱' + parseFloat(empInfo.basic_monthly_salary).toLocaleString('en-PH', {minimumFractionDigits: 2}) : '-';
    }
    
    document.getElementById('preview_period').textContent = periodInfo.period_name || 
        (periodInfo.start_date ? periodInfo.start_date + ' to ' + periodInfo.end_date : '-');
    document.getElementById('preview_records_count').textContent = data.records_count || '0';
    
    preview.style.display = 'block';
    
    // Update TB5 header display
    const periodOption = {
        dataset: {
            start: periodInfo.start_date || '',
            end: periodInfo.end_date || ''
        }
    };
    updateTB5Header(empInfo.full_name, empInfo.basic_monthly_salary, periodOption);
}

// Set employee info after import
function setEmployeeFromImport(employeeInfo, periodInfo) {
    // Set hidden form fields
    document.getElementById('form_employee_id').value = employeeInfo.id || '';
    document.getElementById('form_payroll_period_id').value = periodInfo.id || '';
    
    // Update employee select if exists
    const empSelect = document.getElementById('employee_select');
    if (empSelect && employeeInfo.id) {
        empSelect.value = employeeInfo.id;
    }
    
    // Update period select if exists
    const periodSelect = document.getElementById('payroll_period');
    if (periodSelect && periodInfo.id) {
        periodSelect.value = periodInfo.id;
    }
    
    // Update any employee info display fields in the form
    const empCodeDisplay = document.getElementById('display_emp_code');
    const empNameDisplay = document.getElementById('display_emp_name');
    const empPositionDisplay = document.getElementById('display_position');
    const empDeptDisplay = document.getElementById('display_department');
    const basicSalaryField = document.getElementById('basic_monthly_salary');
    
    if (empCodeDisplay) empCodeDisplay.textContent = employeeInfo.employee_code || '';
    if (empNameDisplay) empNameDisplay.textContent = employeeInfo.full_name || '';
    if (empPositionDisplay) empPositionDisplay.textContent = employeeInfo.position || '';
    if (empDeptDisplay) empDeptDisplay.textContent = employeeInfo.department || '';
    if (basicSalaryField) basicSalaryField.value = employeeInfo.basic_monthly_salary || 0;
}

// Populate DTR table from data
function populateDTRFromData(dtrData) {
    const dtrRows = document.getElementById('dtr_rows');
    dtrRows.innerHTML = '';
    
    let rowNum = 1;
    dtrData.forEach(record => {
        const row = createDTRRowFromData(rowNum, record);
        dtrRows.appendChild(row);
        rowNum++;
    });
    
    updateRowCount();
    attachDTRListeners();
    
    // Recalculate all rows
    dtrData.forEach((record, index) => {
        calculateRowDTR(index + 1);
    });
}

// Create a DTR row from imported data
function createDTRRowFromData(rowNum, data) {
    // Debug: Log raw input data for first few rows
    if (rowNum <= 3) {
        console.log(`createDTRRowFromData Row ${rowNum} RAW DATA:`, {
            dtr_date: data.dtr_date,
            am_time_in: data.am_time_in,
            am_time_out: data.am_time_out,
            pm_time_in: data.pm_time_in,
            pm_time_out: data.pm_time_out,
            typeof_am_in: typeof data.am_time_in,
            typeof_pm_in: typeof data.pm_time_in
        });
    }
    
    const dateStr = data.dtr_date;
    let dateDisplay = '';
    if (dateStr) {
        // Parse date from string to avoid timezone issues
        const parts = dateStr.split('-');
        const y = parseInt(parts[0]);
        const m = parseInt(parts[1]);
        const d = parseInt(parts[2]);
        const months = ['January', 'February', 'March', 'April', 'May', 'June',
                       'July', 'August', 'September', 'October', 'November', 'December'];
        const monthName = months[m - 1] || '';
        dateDisplay = `<div class="date-day">${d}</div><div class="date-month">${monthName}</div>`;
    }
    
    const isAbsent = data.is_absent ? true : false;
    const disabledAttr = isAbsent ? 'disabled' : '';
    const disabledStyle = isAbsent ? 'style="background: #f0f0f0; cursor: not-allowed;"' : '';
    
    // Normalize time values from Excel format to HH:MM
    const amIn = normalizeTimeValue(data.am_time_in);
    const pmOut = normalizeTimeValue(data.pm_time_out);
    const otOut = normalizeTimeValue(data.ot_time_out);
    
    // Debug: Log original vs normalized time values for first few rows
    if (rowNum <= 3) {
        console.log(`Row ${rowNum} NORMALIZED:`, {
            amIn, pmOut,
            lengths: {
                amIn: amIn?.length,
                pmOut: pmOut?.length
            }
        });
    }
    
    // Pre-computed values from server (fallback if JS recalculation fails)
    const serverWorkHours = parseFloat(data.total_work_hours) || 0;
    const serverLateMins = parseFloat(data.late_minutes) || 0;
    const serverUndertime = parseFloat(data.undertime_hours) || 0;
    const serverOT = parseFloat(data.daily_ot_hours) || 0;
    
    // Government deductions for first 3 rows (same as addDTRRow)
    const _govtVal = rowNum === 1 ? '317.50' : rowNum === 2 ? '125.00' : rowNum === 3 ? '100.00' : '';
    const _remarkVal = rowNum === 1 ? 'SSS' : rowNum === 2 ? 'PHILHEALTH' : rowNum === 3 ? 'PAGIBIG' : '';
    
    const row = document.createElement('tr');
    row.setAttribute('data-row', rowNum);
    row.classList.add('dtr-data-row');
    
    // Create HTML without time values (will be set programmatically)
    row.innerHTML = `
        <td class="date-cell">
            <input type="hidden" name="dtr_date_${rowNum}" data-row="${rowNum}" value="${dateStr || ''}">
            ${dateStr ? `<div class="date-display">${dateDisplay}</div>` : '<div class="date-display"><div class="date-day">-</div><div class="date-month">-</div></div>'}
        </td>
        <td><input type="text" name="am_in_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="8:00" maxlength="5" oninput="formatTime24(this)" ${disabledAttr} ${disabledStyle}></td>
        <td><input type="text" name="pm_out_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="17:00" maxlength="5" oninput="formatTime24(this)" ${disabledAttr} ${disabledStyle}></td>
        <td class="centered"><input type="checkbox" name="absent_${rowNum}" data-row="${rowNum}" class="dtr-absent" ${isAbsent ? 'checked' : ''}></td>
        <td><input type="text" name="ot_out_${rowNum}" data-row="${rowNum}" class="dtr-input time24" autocomplete="off" placeholder="18:00" maxlength="5" oninput="formatTime24(this)" ${disabledAttr} ${disabledStyle}></td>
        <td><input type="number" name="work_hours_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="${serverWorkHours.toFixed(2)}" step="1" title="Total work hours"></td>
        <td><input type="number" name="late_mins_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="${Math.round(serverLateMins)}" step="0.01" title="Late minutes"></td>
        <td><input type="number" name="undertime_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="${serverUndertime.toFixed(2)}" step="0.01" title="Undertime hours"></td>
        <td><input type="number" name="ot_hours_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="${serverOT.toFixed(2)}" step="0.01" title="OT hours"></td>
        <td><input type="number" name="absent_day_${rowNum}" data-row="${rowNum}" class="dtr-calc calc-highlight" readonly value="${isAbsent ? '1' : '0'}" step="0.5" title="Absent days"></td>
        <td><input type="number" name="absent_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Absent deduction"></td>
        <td><input type="number" name="late_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Late deduction"></td>
        <td><input type="number" name="undertime_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Undertime deduction"></td>
        <td><input type="number" name="halfday_deduct_${rowNum}" data-row="${rowNum}" class="dtr-deduct deduct-highlight" readonly value="0.00" step="0.01" title="Halfday deduction (auto-calculated when work hours < 4)"></td>
        <td><input type="number" name="ot_pay_${rowNum}" data-row="${rowNum}" class="dtr-pay pay-highlight" readonly value="0.00" step="0.01" title="OT payment"></td>
        <td><input type="number" name="net_deduct_${rowNum}" data-row="${rowNum}" class="dtr-calc net-deduct-highlight" readonly value="0.00" step="0.01" title="Total deductions minus OT pay"></td>
        <td><input type="number" name="late_min_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="Late in minutes calculation"></td>
        <td><input type="number" name="undertime_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="Undertime calculation"></td>
        <td><input type="number" name="ot_calc_${rowNum}" data-row="${rowNum}" class="dtr-auto-calc" readonly value="0.00" step="0.01" title="OT calculation"></td>
        <td><input type="number" name="govt_${rowNum}" data-row="${rowNum}" class="dtr-manual" value="${_govtVal}" step="0.01" title="Manual Gov't deduction"></td>
        <td><input type="number" name="auto_salary_${rowNum}" data-row="${rowNum}" class="dtr-auto-salary" readonly value="" step="0.01" title="Net Salary"></td>
        <td><input type="text" name="f1_${rowNum}" data-row="${rowNum}" class="dtr-f-input" maxlength="10" title="F1 marker"></td>
        <td><input type="text" name="f2_${rowNum}" data-row="${rowNum}" class="dtr-f-input" maxlength="10" title="F2 marker"></td>
        <td><input type="text" name="remarks_${rowNum}" data-row="${rowNum}" class="dtr-remarks-input" value="${_remarkVal}" placeholder="Remarks" title="Remarks"></td>
        <td class="actions-cell">
            <button type="button" class="btn-delete-row" onclick="deleteDTRRow(this)" title="Delete">
                <i class="fas fa-trash-alt"></i>
            </button>
        </td>
    `;
    
    return row;
}

// Load existing DTR records from database
function loadExistingDTR() {
    const employeeId = document.getElementById('employee_select').value;
    const payrollPeriodEl = document.getElementById('payroll_period');
    const payrollPeriodId = payrollPeriodEl ? payrollPeriodEl.value : '';
    
    if (!employeeId || !payrollPeriodId) {
        alert('Please select both employee and payroll period first.');
        return;
    }
    
    fetch(`get_dtr_records.php?employee_id=${employeeId}&payroll_period_id=${payrollPeriodId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.dtr_data.length > 0) {
                populateDTRFromData(data.dtr_data);
                showImportStatus('success', `Loaded ${data.dtr_data.length} existing DTR records.`);
            } else {
                alert('No existing DTR records found for this employee and period.');
            }
        })
        .catch(error => {
            console.error('Error loading DTR:', error);
            alert('Error loading DTR records. Please try again.');
        });
}

// Check for existing DTR when employee/period changes
function checkExistingDTR() {
    const employeeId = document.getElementById('employee_select').value;
    const payrollPeriodEl = document.getElementById('payroll_period');
    const payrollPeriodId = payrollPeriodEl ? payrollPeriodEl.value : '';
    const noticeEl = document.getElementById('existing_dtr_notice');
    
    if (!noticeEl) return;
    if (!employeeId || !payrollPeriodId) {
        noticeEl.style.display = 'none';
        return;
    }
    
    fetch(`get_dtr_records.php?employee_id=${employeeId}&payroll_period_id=${payrollPeriodId}&check_only=1`)
        .then(response => response.json())
        .then(data => {
            if (data.exists) {
                noticeEl.style.display = 'flex';
            } else {
                noticeEl.style.display = 'none';
            }
        })
        .catch(error => {
            console.error('Error checking DTR:', error);
        });
}

// Add check for existing DTR on selection change
document.getElementById('employee_select').addEventListener('change', checkExistingDTR);
// Note: checkExistingDTR updated to work with date picker
const payrollPeriodEl = document.getElementById('payroll_period');
if (payrollPeriodEl) {
    payrollPeriodEl.addEventListener('change', function() {
        // Date picker - check existing DTR if needed
        const employeeId = document.getElementById('employee_select').value;
        const selectedDate = this.value;
        if (employeeId && selectedDate) {
            const period = calculatePeriodFromDate(selectedDate);
            // Can add check for existing DTR here if needed
        }
    });
}

// Download DTR template
function downloadDTRTemplate() {
    window.location.href = 'download_dtr_template.php';
}

// Update TB5-style header with employee info and calculated rates
function updateTB5Header(employeeName, basicSalary, periodOption = null) {
    const salary = parseFloat(basicSalary) || 0;
    const workingDays = 15; // 15 days per cut-off computation
    const workingHoursPerDay = 8;
    const workingMinsPerDay = 480;
    
    // Calculate rates
    const perDay = salary / workingDays;
    const perHour = perDay / workingHoursPerDay;
    const perMin = perDay / workingMinsPerDay;
    
    // Update TB5 header elements
    const nameEl = document.getElementById('tb5_employee_name');
    const basicEl = document.getElementById('tb5_basic');
    const perDayEl = document.getElementById('tb5_per_day');
    const perMinEl = document.getElementById('tb5_per_min');
    const perHourEl = document.getElementById('tb5_per_hour');
    const periodEl = document.getElementById('tb5_period_display');
    
    if (nameEl) nameEl.textContent = employeeName || '-';
    if (basicEl) basicEl.textContent = salary > 0 ? '₱' + salary.toLocaleString('en-PH') : '₱0';
    if (perDayEl) perDayEl.textContent = perDay.toFixed(2);
    if (perMinEl) perMinEl.textContent = perMin.toFixed(4);
    if (perHourEl) perHourEl.textContent = perHour.toFixed(4);
    
    if (periodEl && periodOption) {
        const start = new Date(periodOption.dataset.start);
        const end = new Date(periodOption.dataset.end);
        const periodText = start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + 
                          '-' + end.toLocaleDateString('en-US', { day: 'numeric', year: 'numeric' });
        periodEl.textContent = periodText;
    }
    
    // Store rates globally for calculations (using working days excluding Sundays)
    const importedPeriodStart = document.getElementById('imported_period_start')?.value;
    const importedPeriodEnd = document.getElementById('imported_period_end')?.value;
    const workingDaysForCalc = countWorkingDays(importedPeriodStart, importedPeriodEnd) || 15;
    const recalcPerDay = salary / workingDaysForCalc;
    const recalcPerHour = recalcPerDay / 8;
    const recalcPerMin = recalcPerHour / 60;
    
    window.dtrRates = {
        basicSalary: salary,
        perDay: recalcPerDay,
        perHour: recalcPerHour,
        perMin: recalcPerMin
    };
}

// Update all rates when basic salary changes
function updateAllRates() {
    const basicSalaryInput = document.getElementById('basic_monthly_salary');
    const basicSalary = parseFloat(basicSalaryInput.value) || 13000;
    
    // Calculate working days excluding Sundays
    const periodStart = document.getElementById('calculated_start_date')?.value;
    const periodEnd = document.getElementById('calculated_end_date')?.value;
    const workingDays = countWorkingDays(periodStart, periodEnd) || 15; // Fallback to 15 if no dates
    
    const hoursPerDay = 8;
    const minsPerHour = 60;
    const otMultiplier = 1.25;
    
    // Calculate daily rate from basic salary
    const dailyRate = basicSalary / workingDays;
    const otRate = (dailyRate / hoursPerDay) * otMultiplier;
    
    // Update daily rate and OT rate inputs
    const dailyEl = document.getElementById('daily_rate');
    const otEl = document.getElementById('ot_rate');
    
    if (dailyEl) dailyEl.value = dailyRate.toFixed(2);
    if (otEl) otEl.value = otRate.toFixed(2);
    
    // Trigger calculation of hourly and minute rates from daily rate
    updateRatesFromDaily();
}

// Update rates when per/day changes (called when user inputs per/day)
function updateRatesFromDaily() {
    const dailyEl = document.getElementById('daily_rate');
    const dailyRate = parseFloat(dailyEl.value) || 500;
    
    const hoursPerDay = 8;
    const minsPerHour = 60;
    
    // Calculate hourly and minute rates from daily rate
    const hourlyRate = dailyRate / hoursPerDay;
    const minuteRate = hourlyRate / minsPerHour;
    const halfDayRate = dailyRate / 2;
    
    // Update hourly and minute rate displays (read-only computed values)
    const hourlyEl = document.getElementById('hourly_rate');
    const minuteEl = document.getElementById('minute_rate');
    
    if (hourlyEl) hourlyEl.textContent = hourlyRate.toFixed(2);
    if (minuteEl) minuteEl.textContent = minuteRate.toFixed(4);
    
    // Get OT rate from input (user can change this)
    const otEl = document.getElementById('ot_rate');
    const otRate = parseFloat(otEl.value) || 78.13;
    
    // Get basic salary
    const basicSalaryInput = document.getElementById('basic_monthly_salary');
    const basicSalary = parseFloat(basicSalaryInput.value) || 13000;
    
    // Update TB5 header rate displays
    const tb5DailyEl = document.getElementById('tb5_daily_rate');
    const tb5HourlyEl = document.getElementById('tb5_hourly_rate');
    if (tb5DailyEl) tb5DailyEl.textContent = dailyRate.toFixed(2);
    if (tb5HourlyEl) tb5HourlyEl.textContent = hourlyRate.toFixed(2);
    
    // Update summary rate displays
    const summaryDailyEl = document.getElementById('summary_daily_rate');
    const summaryHourlyEl = document.getElementById('summary_hourly_rate');
    const summaryMinEl = document.getElementById('summary_min_rate');
    const summaryOtEl = document.getElementById('summary_ot_rate');
    const summaryHalfdayEl = document.getElementById('summary_halfday_rate');
    
    if (summaryDailyEl) summaryDailyEl.textContent = dailyRate.toFixed(2);
    if (summaryHourlyEl) summaryHourlyEl.textContent = hourlyRate.toFixed(2);
    if (summaryMinEl) summaryMinEl.textContent = minuteRate.toFixed(4);
    if (summaryOtEl) summaryOtEl.textContent = otRate.toFixed(2);
    if (summaryHalfdayEl) summaryHalfdayEl.textContent = halfDayRate.toFixed(2);
    
    // Update hidden inputs
    const hourlyInputEl = document.getElementById('hourly_rate_value');
    const minuteInputEl = document.getElementById('minute_rate_value');
    if (hourlyInputEl) hourlyInputEl.value = hourlyRate.toFixed(2);
    if (minuteInputEl) minuteInputEl.value = minuteRate.toFixed(4);
    
    // Store rates globally
    window.dtrRates = {
        basicSalary: basicSalary,
        perDay: dailyRate,
        perHour: hourlyRate,
        perMin: minuteRate,
        otRate: otRate,
        halfDayRate: halfDayRate
    };
    
    // Recalculate all rows with new rates
    document.querySelectorAll('#dtr_rows tr').forEach(row => {
        const rowNum = row.getAttribute('data-row');
        if (rowNum) calculateRowDTR(rowNum);
    });
    
    // Recalculate totals
    calculateTotals();
}

// Update OT rate in global storage when changed
// Recalculate all DTR rows (used when late start or end time changes)
function recalculateAllRows() {
    document.querySelectorAll('#dtr_rows tr').forEach(row => {
        const rowNum = row.getAttribute('data-row');
        if (rowNum) calculateRowDTR(rowNum);
    });
    calculateTotals();
}

function updateOTRate() {
    const otEl = document.getElementById('ot_rate');
    const otRate = parseFloat(otEl.value) || 78.13;
    
    // Update summary display
    const summaryOtEl = document.getElementById('summary_ot_rate');
    if (summaryOtEl) summaryOtEl.textContent = otRate.toFixed(2);
    
    // Update global rates
    if (window.dtrRates) {
        window.dtrRates.otRate = otRate;
    }
    
    // Recalculate all main DTR rows with new OT rate
    document.querySelectorAll('#dtr_rows tr').forEach(row => {
        const rowNum = row.getAttribute('data-row');
        if (rowNum) calculateRowDTR(rowNum);
    });
    calculateTotals();

    // Recalculate all TB5 rows with new OT rate
    const tb5Body = document.getElementById('tb5_dtr_body');
    if (tb5Body && tb5Body.children.length > 0) {
        for (let day = 1; day <= 31; day++) {
            calculateTB5Row(day);
        }
        updateTB5Totals();
    }
}

// Update payroll summary section with all calculated values
// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Initializing...');
    
    // Initialize rates
    setTimeout(function() {
        updateAllRates();
        
        // Calculate totals if there are existing rows
        const existingRows = document.querySelectorAll('#dtr_rows tr[data-row]');
        if (existingRows.length > 0) {
            console.log('Found', existingRows.length, 'existing rows, calculating totals...');
            calculateTotals();
        }
        
        // Also recalculate after a longer delay to catch any async operations
        setTimeout(function() {
            const rowsCheck = document.querySelectorAll('#dtr_rows tr[data-row]');
            if (rowsCheck.length > 0) {
                console.log('Delayed recalculation with', rowsCheck.length, 'rows');
                calculateTotals();
            }
        }, 1000);
    }, 100);
    
    // Set current month in payroll period dropdown
    const currentDate = new Date();
    const currentMonth = currentDate.getMonth() + 1; // 1-indexed
    const monthSelect = document.getElementById('payroll_month');
    if (monthSelect) {
        monthSelect.value = currentMonth;
    }
    
    // Update cutoff options to show correct last day for current month
    updateCutoffOptions();
    
    // Load employee DTR cards
    loadEmployeeDTRCards();
});
</script>

<!-- Full View Modal for DTR -->
<div id="fullViewModal" class="modal-fullview" style="display: none;">
    <div class="modal-fullview-content">
        <div class="modal-fullview-header">
            <h2><i class="fas fa-calendar-alt"></i> DTR Full View - <span id="modal_employee_name">Employee</span></h2>
            <button type="button" class="modal-close-btn" onclick="closeFullViewModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-fullview-body">
            <div class="modal-employee-info">
                <div class="info-item"><strong>Employee Name:</strong> <span id="modal_employee_name">-</span></div>
                <div class="info-item"><strong>Basic Salary:</strong> ₱<span id="modal_salary">0.00</span></div>
                <div class="info-item"><strong>Period:</strong> <span id="modal_period">-</span></div>
            </div>
            <div id="modal_dtr_table_container" class="modal-table-container">
                <!-- DTR table will be cloned here -->
            </div>
        </div>
    </div>
</div>

<!-- Employee DTR View Modal -->
<div id="employeeDTRModal" class="modal-fullview" style="display: none;">
    <div class="modal-fullview-content">
        <div class="modal-fullview-header">
            <h2><i class="fas fa-user"></i> <span id="emp_modal_name">Employee DTR</span></h2>
            <button type="button" class="modal-close-btn" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="modal-fullview-body">
            <div class="month-selector">
                <label>Select Month:</label>
                <select id="dtr_month_select" onchange="loadEmployeeDTRByMonth()">
                    <option value="">-- Select Month --</option>
                </select>
            </div>
            <div id="employee_dtr_content" class="employee-dtr-content">
                <!-- DTR records will be loaded here -->
            </div>
        </div>
        <div class="modal-fullview-footer">
            <button type="button" class="btn-modern btn-secondary" onclick="closeEmployeeDTRModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<?php require_once 'include/footer.php'; ?>
