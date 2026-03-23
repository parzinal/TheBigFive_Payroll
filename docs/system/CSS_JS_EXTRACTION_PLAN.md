# CSS/JS Extraction Plan

> **Purpose:** This document is a systematic plan for extracting all remaining inline `<style>` and `<script>` blocks from PHP files into external `.css` and `.js` files under `assets/`.
>
> **Key Finding:** No file has PHP embedded in its `<style>` or `<script>` blocks — all extractions are straightforward copy-paste operations with no dynamic CSS/JS complications.

---

## Already Completed

| Source File(s) | External CSS | External JS | Status |
|----------------|-------------|------------|--------|
| admin/profile.php, staff/profile.php, user/profile.php | `assets/css/profile.css` | `assets/js/profile.js` | ✅ Done |
| admin/account_logs.php, staff/account_logs.php, user/account_logs.php | `assets/css/account_logs.css` | `assets/js/account_logs.js` | ✅ Done |

---

## Extraction Queue (Ordered by Priority & Size)

### Phase 1 — Largest Files (HIGH priority)

#### 1. admin/Generatepayroll.php (8,820 lines total)

- **CSS:** lines 613–4215 (~3,604 lines) | PHP embedded: **No**
- **JS:** lines 4217–8767 (~4,551 lines) | PHP embedded: **No**
- **Target files:** `assets/css/generatepayroll.css` (already created, 3607 lines) + `assets/js/generatepayroll.js` (NOT yet created)
- **Notes:**
  - `assets/css/generatepayroll.css` was previously extracted and is ready to use
  - Second `<style>` tag at line ~7208 is inside a JS template literal (print window) — it is NOT a real inline block, leave it inside the JS
  - After creating the JS file, replace the `<style>...</style>` block (lines 612–4216) with `<link rel="stylesheet" href="../assets/css/generatepayroll.css">` and the `<script>...</script>` block (lines 4217–8768) with `<script src="../assets/js/generatepayroll.js"></script>`
- **Steps:**
  1. ~~Create `assets/css/generatepayroll.css`~~ (already done — verify 3607 lines)
  2. Read lines 4218–8767 from `admin/Generatepayroll.php` and create `assets/js/generatepayroll.js`
  3. Replace inline `<style>` block with `<link>` tag
  4. Replace inline `<script>` block with `<script src>` tag
  5. Verify file loads correctly in browser

#### 2. staff/Generatepayroll.php (1,819 lines total)

- **CSS:** lines 565–1391 (~827 lines) | PHP embedded: **No**
- **JS:** lines 1394–1810 (~417 lines) | PHP embedded: **No**
- **Target files:** `assets/css/generatepayroll_staff.css` + `assets/js/generatepayroll_staff.js`
- **Notes:** Different feature set from admin version (read-only DTR viewer). NOT a candidate for sharing with admin/Generatepayroll.
- **Steps:**
  1. Read lines 566–1391 → create `assets/css/generatepayroll_staff.css`
  2. Read lines 1395–1810 → create `assets/js/generatepayroll_staff.js`
  3. Replace inline `<style>` with `<link>` tag
  4. Replace inline `<script>` with `<script src>` tag

#### 3. admin/settings.php (2,476 lines total)

- **CSS:** lines 598–1799 (~1,202 lines) | PHP embedded: **No**
- **JS:** lines 1801–2475 (~675 lines) | PHP embedded: **No**
- **Target files:** `assets/css/settings.css` + `assets/js/settings.js`
- **Notes:** Unique to admin (backup/restore settings UI). Standalone extraction.
- **Steps:**
  1. Read lines 599–1799 → create `assets/css/settings.css`
  2. Read lines 1802–2475 → create `assets/js/settings.js`
  3. Replace inline blocks with external file references

#### 4. admin/employee_positions.php (2,036 lines total)

- **CSS:** lines 342–1359 (~1,018 lines) | PHP embedded: **No**
- **JS:** lines 1361–2030 (~670 lines) | PHP embedded: **No**
- **Target files:** `assets/css/employee_positions.css` + `assets/js/employee_positions.js`
- **Notes:** Admin-only page. Standalone extraction.
- **Steps:**
  1. Read lines 343–1359 → create `assets/css/employee_positions.css`
  2. Read lines 1362–2030 → create `assets/js/employee_positions.js`
  3. Replace inline blocks with external file references

---

### Phase 2 — Shared-File Candidates (HIGH priority)

> These file pairs share very similar CSS/JS and should be diffed before extraction to determine if they can use a shared external file (like profile.css/account_logs.css approach) or need separate files.

#### 5 & 6. Payslip History (admin + staff)

| File | CSS | JS |
|------|-----|-----|
| staff/payslip_history.php (2,145 lines) | lines 275–1734 (~1,460 lines) | lines 1736–2140 (~405 lines) |
| admin/payslip_history.php (1,201 lines) | lines 186–902 (~717 lines) | lines 904–1196 (~293 lines) |

- **PHP embedded:** No (both files)
- **Approach:** Diff the CSS/JS blocks side-by-side. If ≥80% overlap → create shared `assets/css/payslip_history.css` + `assets/js/payslip_history.js`. Otherwise → separate files (`payslip_history_admin.css`, `payslip_history_staff.css`).
- **Steps:**
  1. Read and compare both CSS blocks
  2. Read and compare both JS blocks
  3. Decide shared vs. separate
  4. Create external file(s)
  5. Update both PHP files

#### 7 & 8. Payroll List (admin + staff)

| File | CSS | JS |
|------|-----|-----|
| admin/payroll_list.php (1,985 lines) | lines 226–944 (~719 lines) | lines 946–1982 (~1,037 lines) |
| staff/payroll_list.php (1,431 lines) | lines 210–894 (~685 lines) | lines 896–1428 (~533 lines) |

- **PHP embedded:** No (both files)
- **Notes:** Very similar `.stats-row`, `.stat-card`, `.employee-cards-*` CSS. Second `<style>` tags at lines ~1905 (admin) and ~1324 (staff) are inside JS template literals (print windows) — NOT real inline blocks.
- **Approach:** Same as payslip_history — diff and decide shared vs. separate.
- **Steps:**
  1. Read and compare both CSS blocks
  2. Read and compare both JS blocks
  3. Decide shared vs. separate
  4. Create external file(s)
  5. Update both PHP files

#### 9 & 10. Employee List (admin + staff)

| File | CSS | JS |
|------|-----|-----|
| admin/employee_list.php (1,715 lines) | Block 1: lines 459–1170 (~712 lines), Block 2: lines 1570–1709 (~140 lines) | lines 1172–1568 (~397 lines) |
| staff/employee_list.php (586 lines) | lines 214–531 (~318 lines) | lines 533–580 (~48 lines) |

- **PHP embedded:** No (both files)
- **Notes:** Admin has TWO `<style>` blocks — merge them into one external CSS file. Admin has much more CSS/JS (modals, validation, CRUD). Staff is read-only with shared table/card layout.
- **Approach:** Create a shared base `assets/css/employee_list.css` and optionally a supplement for admin-specific modal/validation styles.
- **Steps:**
  1. Read and compare admin Block 1 + Block 2 with staff CSS
  2. Identify shared vs. admin-only styles
  3. Create external file(s) — consider `employee_list.css` (shared) + admin-specific styles inline or in a second file
  4. Update both PHP files

---

### Phase 3 — Medium Files (MEDIUM priority)

#### 11. admin/user_management.php (1,595 lines total)

- **CSS:** lines 436–1212 (~777 lines) | PHP embedded: **No**
- **JS:** lines 1214–1589 (~376 lines) | PHP embedded: **No**
- **Target files:** `assets/css/user_management.css` + `assets/js/user_management.js`
- **Notes:** Admin-only. Shares validation CSS patterns (`.input-error`, `.input-success`) with employee_list and Add_emplooyees / add_user — consider extracting validation styles into a shared `form-validation.css`.
- **Steps:**
  1. Read CSS/JS blocks
  2. Create external files
  3. Replace inline blocks

#### 12. admin/Add_emplooyees.php (837 lines total)

- **CSS Block 1:** lines 353–665 (~313 lines) | PHP embedded: **No**
- **CSS Block 2:** lines 667–696 (~30 lines) | PHP embedded: **No**
- **JS:** lines 698–832 (~135 lines) | PHP embedded: **No**
- **Target files:** `assets/css/add_employee.css` + `assets/js/add_employee.js`
- **Notes:** Two `<style>` blocks — merge into one external file. Second block is validation CSS identical to add_user.php and employee_list.php.
- **Steps:**
  1. Read and merge both CSS blocks → create external CSS file
  2. Read JS block → create external JS file
  3. Replace inline blocks

#### 13. admin/add_user.php (716 lines total)

- **CSS:** lines 273–554 (~282 lines) | PHP embedded: **No**
- **JS:** lines 556–710 (~155 lines) | PHP embedded: **No**
- **Target files:** `assets/css/add_user.css` + `assets/js/add_user.js`
- **Notes:** Shares validation CSS/JS patterns with Add_emplooyees.php and employee_list.php.
- **Steps:**
  1. Read CSS/JS blocks
  2. Create external files
  3. Replace inline blocks

#### 14. staff/employee_list.php (586 lines total)

- **CSS:** lines 214–531 (~318 lines) | PHP embedded: **No**
- **JS:** lines 533–580 (~48 lines) | PHP embedded: **No**
- **Notes:** See Phase 2 item #9/10 for shared approach with admin/employee_list.php.

---

### Phase 4 — Dashboards (MEDIUM priority, CSS-only)

> These files only have `<style>` blocks, no `<script>` blocks.

#### 15. admin/dashboard.php (826 lines total)

- **CSS:** lines 318–820 (~503 lines) | PHP embedded: **No**
- **JS:** None
- **Target file:** `assets/css/dashboard.css` (or `dashboard_admin.css`)
- **Notes:** Could share a base with staff/dashboard_staff.php but diverges significantly. Might be cleaner as separate files.

#### 16. staff/dashboard_staff.php (784 lines total)

- **CSS:** lines 290–778 (~489 lines) | PHP embedded: **No**
- **JS:** None
- **Target file:** `assets/css/dashboard_staff.css` (or shared `dashboard.css` with admin)

#### 17. user/dashboard_user.php (405 lines total)

- **CSS:** lines 290–399 (~110 lines) | PHP embedded: **No**
- **JS:** None
- **Target file:** `assets/css/dashboard_user.css`
- **Notes:** Small and unique. Standalone extraction.

---

### Phase 5 — Small Files (LOW priority)

#### 18. login.php (307 lines total)

- **CSS:** None (already uses external `assets/css/login.css`)
- **JS:** lines 248–304 (~57 lines) | PHP embedded: **No**
- **Target file:** `assets/js/login.js`
- **Notes:** Password toggle + form submission logic. Shares passwordtoggle pattern with forgotpass.php.

#### 19. forgotpass.php (526 lines total)

- **CSS:** lines 244–289 (~46 lines) | PHP embedded: **No**
- **JS:** lines 430–523 (~94 lines) | PHP embedded: **No**
- **Target files:** `assets/css/forgotpass.css` + `assets/js/forgotpass.js`
- **Notes:** Already uses external `assets/css/login.css`. Small supplemental CSS (OTP input, step indicators).

---

## Shared Utility Files to Consider

During extraction, watch for opportunities to create shared utility files:

### 1. `assets/css/form-validation.css` (~30 lines)
**Shared across:** admin/employee_list.php, admin/user_management.php, admin/Add_emplooyees.php, admin/add_user.php

Common styles:
```css
.form-input.input-error { border-color: #EF4444; ... }
.form-input.input-success { border-color: #10B981; ... }
.field-error-msg { color: #EF4444; font-size: 12px; ... }
```

### 2. `assets/js/form-validation.js` (~40 lines)
**Shared across:** Same 4 files above

Common functions:
```javascript
function showFieldError(inputId, message) { ... }
function clearFieldError(inputId) { ... }
function showEditFieldError(inputId, message) { ... }
function clearEditFieldError(inputId) { ... }
```

### 3. `assets/js/password-toggle.js` (~15 lines)
**Shared across:** login.php, forgotpass.php, profile pages (already in profile.js)

Common function:
```javascript
function togglePassword(inputId, icon) { ... }
```

---

## Summary Statistics

| Metric | Count |
|--------|-------|
| Total files to process | 19 |
| Total inline CSS lines | ~13,952 |
| Total inline JS lines | ~9,847 |
| Total lines to extract | **~23,799** |
| Files with PHP in CSS/JS | **0** (all clean) |
| Potential shared files | 5-6 pairs |
| External files to create | ~25-30 |

---

## Extraction Procedure (Per File)

For each file, follow these steps:

1. **Read** the inline CSS content (between `<style>` and `</style>`)
2. **Create** external `.css` file in `assets/css/` with extracted content
3. **Read** the inline JS content (between `<script>` and `</script>`)
4. **Create** external `.js` file in `assets/js/` with extracted content
5. **Replace** the `<style>...</style>` block in the PHP file with:
   ```html
   <link rel="stylesheet" href="../assets/css/FILENAME.css">
   ```
6. **Replace** the `<script>...</script>` block in the PHP file with:
   ```html
   <script src="../assets/js/FILENAME.js"></script>
   ```
7. **Test** in the browser to confirm no visual or functional regressions

> **Note for `<style>` blocks inside JS template literals** (found in admin/payroll_list.php, staff/payroll_list.php, admin/Generatepayroll.php): These are part of print-window functionality — they should stay inside the JS code, not be extracted separately.

---

## Order of Execution

| Step | Files | Est. External Files Created |
|------|-------|---------------------------|
| 1 | admin/Generatepayroll.php | +1 (`generatepayroll.js` — CSS already done) |
| 2 | staff/Generatepayroll.php | +2 (`generatepayroll_staff.css`, `generatepayroll_staff.js`) |
| 3 | admin/settings.php | +2 (`settings.css`, `settings.js`) |
| 4 | admin/employee_positions.php | +2 (`employee_positions.css`, `employee_positions.js`) |
| 5 | payslip_history (admin + staff) | +2 (shared or separate after diffing) |
| 6 | payroll_list (admin + staff) | +2 (shared or separate after diffing) |
| 7 | employee_list (admin + staff) | +2 (shared or separate after diffing) |
| 8 | admin/user_management.php | +2 |
| 9 | admin/Add_emplooyees.php + admin/add_user.php | +4 (+ optional shared validation files) |
| 10 | Dashboards (admin, staff, user) | +3 (CSS-only) |
| 11 | login.php + forgotpass.php | +3 (`login.js`, `forgotpass.css`, `forgotpass.js`) |
