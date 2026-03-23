# TheBigFive Payroll System — Improvements & Vulnerability Plan

> **Created:** March 2, 2026
> **Purpose:** Track all identified vulnerabilities, UX issues, code quality improvements, and architectural changes. This serves as our master reference and checklist — nothing here has been implemented unless marked ✅.
>
> **Workflow:** Review items → Discuss implementation approach → Implement one by one → Mark completed.

---

## Status Legend

| Symbol | Meaning |
|--------|---------|
| ⬜ | Not started — awaiting implementation |
| 🔄 | In progress |
| ✅ | Completed |
| ⏭️ | Deferred / Skipped for now (with reason) |
| 🗑️ | To be removed / deleted |

---

## Already Completed

| ID | Item | Status | Date |
|----|------|--------|------|
| — | Account Logs filter buttons (admin/staff/user) — Apply Filter & Reset disabled when no filter values set | ✅ | Mar 2, 2026 |
| C1 | Login rate limiting (IP-based, 5 attempts/15min, `login_attempts` table) | ✅ | Mar 2, 2026 |
| C2 | `delete_user.php` converted to POST + CSRF + JSON API; `user_management.php` JS updated | ✅ | Mar 2, 2026 |
| C3 | Migration endpoints (`run_migration.php`, `migrate_employee_classification.php`) now require admin auth | ✅ | Mar 2, 2026 |
| H1 | Admin role checks added to 9 API endpoints (`get_dtr_records`, `get_employee_dtr_*`, etc.) | ✅ | Mar 2, 2026 |
| H2 | Auth guards added to 4 diagnostic/setup pages (`check_extensions`, `check_braces`, `setup_notifications`, `check_notifications`) | ✅ | Mar 2, 2026 |
| H3 | CSRF validation added to `update_dtr_records.php` | ✅ | Mar 2, 2026 |
| H4 | Path traversal protection in `BackupManager::restoreFromFile()` and `getBackupInfo()` | ✅ | Mar 2, 2026 |
| H5 | OTP rate limiting (5 requests/hour, 5 verify attempts/session, 60s resend cooldown) | ✅ | Mar 2, 2026 |
| H7 | `export_sample_dtr.php` neutralized (returns 410 Gone) | ✅ | Mar 2, 2026 |
| M1 | Notification APIs: `notification_id` validated as int, DB errors logged instead of exposed | ✅ | Mar 2, 2026 |
| M2 | XSS fix: `htmlspecialchars()` added to `data-employee-name` in `staff/payslip_history.php` | ✅ | Mar 2, 2026 |
| M3 | MIME type validation added to DTR import (`import_dtr.php`) | ✅ | Mar 2, 2026 |
| S1 | Login button disabled on submit with "LOGGING IN..." text | ✅ | Mar 2, 2026 |
| S2 | Add Employee button disabled on submit with spinner | ✅ | Mar 2, 2026 |
| S3 | Add User button disabled on submit with spinner | ✅ | Mar 2, 2026 |
| S4 | Profile + Password forms disabled on submit (all 3 roles) | ✅ | Mar 2, 2026 |
| S5 | Delete Employee confirm button disabled during AJAX | ✅ | Mar 2, 2026 |
| S6 | Delete User confirm button disabled + converted to fetch POST (via C2) | ✅ | Mar 2, 2026 |
| S7 | Forgot Password all 3 forms disabled on submit | ✅ | Mar 2, 2026 |
| V1 | Status/classification enum whitelist validation in `Add_emplooyees.php` | ✅ | Mar 2, 2026 |
| V3 | Salary upper bound + hire date format validation in `Add_emplooyees.php` | ✅ | Mar 2, 2026 |
| V4 | Full name character regex in `Add_emplooyees.php`, `add_user.php`, all 3 `profile.php` | ✅ | Mar 2, 2026 |
| V2 | Role + status enum validation in `add_user.php` | ✅ | Mar 2, 2026 |
| O1 | Removed duplicate `require_once 'include/header.php'` in `staff/payslip_history.php` | ✅ | Mar 2, 2026 |
| O3 | Error alerts no longer auto-dismiss (only success alerts do) in 4 files | ✅ | Mar 2, 2026 |

---

## CRITICAL SEVERITY

### C1 — No Login Rate Limiting ✅

- **File:** `login.php`
- **Problem:** Failed login attempts are logged via `logFailedLogin()` but the count is never checked. There is no mechanism to block or throttle a user/IP after N failed attempts. Brute force attacks against passwords are completely unmitigated.
- **Impact:** An attacker can try unlimited password combinations with no delay or lockout.
- **Recommended Fix:**
  - Track failed attempts per username/IP in the `account_logs` table (or a dedicated `login_attempts` table)
  - After 5 failed attempts within 15 minutes, lock the account for 15–30 minutes
  - Show a user-friendly "Too many attempts, try again later" message
  - Optionally add a CAPTCHA after 3 failed attempts
  - Reset the counter on successful login

---

### C2 — `delete_user.php` Uses GET Method + No CSRF Protection ✅

- **File:** `admin/delete_user.php`
- **Problem:** User deletion is triggered via a GET request (`$_GET['id']`). There is zero CSRF token validation. This means:
  - A simple `<img src="delete_user.php?id=5">` on any page can silently delete a user
  - Browser prefetching, crawlers, or browser extensions could accidentally trigger it
  - The URL appears in browser history, server logs, and referrer headers
- **Comparison:** `admin/delete_employee.php` correctly uses POST + CSRF. `delete_user.php` should follow the same pattern.
- **Recommended Fix:**
  - Change to POST method with JSON body
  - Add `requireCSRFToken()` validation
  - Update `admin/user_management.php` JS to use `fetch()` POST instead of `window.location.href`
  - Add double-click protection on the confirm button

---

### C3 — Unprotected Database Migration Endpoints ✅

- **Files:** `admin/run_migration.php`, `admin/migrate_employee_classification.php`
- **Problem:** These files have NO authentication checks whatsoever. Anyone who knows the URL can execute database schema changes (ALTER TABLE, ADD COLUMN, etc.) without being logged in.
- **Impact:** Public-facing database modification. An attacker could run migrations to alter the schema.
- **Recommended Fix:**
  - Add `require_once '../config/auth.php';` and `requireAuth('admin');` at the top of both files
  - Alternatively, delete these files after migrations are applied and manage schema changes through SQL files only

---

## HIGH SEVERITY

### H1 — Missing Role Checks on Admin API Endpoints ✅

- **Files affected (9 endpoints):**
  - `admin/get_dtr_records.php`
  - `admin/get_employee_dtr_data.php`
  - `admin/get_employee_dtr_months.php`
  - `admin/get_employee_dtr_cards.php`
  - `admin/get_all_positions.php`
  - `admin/save_dtr.php`
  - `admin/get_employee_payslips.php`
  - `admin/download_dtr_template.php`
  - `admin/export_dtr_data.php`
- **Problem:** These files only check `$_SESSION['user_id']` (is someone logged in?) but never check the user's role. A regular `user` role account can access all admin API data by directly calling these URLs.
- **Impact:** Any authenticated user (including basic `user` role) can view all employee DTR records, payslip data, position data, and even save DTR records.
- **Recommended Fix:**
  - Add proper role checks at the top of each file:
    ```php
    require_once '../config/auth.php';
    if (!isAuthenticated() || !isAdmin()) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    ```
  - For endpoints that staff should also access, use `(!isAdmin() && !isStaff())`

---

### H2 — Unprotected Diagnostic/Setup Pages ✅

- **Files:**
  - `admin/check_extensions.php` — Exposes PHP extension info to anyone
  - `admin/check_braces.php` — Debug utility that reads and displays file contents
  - `config/setup_notifications.php` — Can create/modify database tables without auth
  - `config/check_notifications.php` — Explicitly allows access without login ("for troubleshooting"), exposes DB structure
- **Problem:** These pages have no authentication checks. Anyone can access them by visiting the URL directly.
- **Impact:** Information disclosure (PHP config, DB structure, file contents) and unauthorized DB modifications.
- **Recommended Fix:**
  - Add admin auth check to `check_extensions.php` and `check_braces.php`
  - Add admin auth check to `setup_notifications.php`
  - Remove the `$allowAnonymous` bypass in `check_notifications.php` and add proper auth
  - Alternatively, delete diagnostic files that are no longer needed

---

### H3 — Missing CSRF on `update_dtr_records.php` ✅

- **File:** `admin/update_dtr_records.php`
- **Problem:** Accepts POST requests with JSON body to modify DTR records but has no CSRF token validation anywhere in the file.
- **Recommended Fix:**
  - Add CSRF token check — read from `X-CSRF-Token` header (since it's a JSON API, the token comes via the header auto-injected by `dashboard.js`)
  - Add `requireCSRFToken()` or manual validation after the auth check

---

### H4 — Weak Backup File Validation ✅

- **File:** `config/BackupManager.php` (method: `validateBackupFile()`)
- **Problem:** The SQL file validation for backup restore only checks if the first 4KB of the uploaded file contains common SQL keywords ("MySQL", "CREATE", "INSERT", "DROP", "SET"). It does NOT block dangerous statements like:
  - `GRANT ALL PRIVILEGES` — privilege escalation
  - `CREATE USER` — create rogue DB accounts
  - `LOAD_FILE()` — read server files
  - `INTO OUTFILE` / `INTO DUMPFILE` — write files to server
  - `SYSTEM` or `\!` — OS command execution in some MySQL clients
- **Additional context:** In `restoreViaPDO()`, statements from the backup file are split and executed one by one via `$this->pdo->exec($statement)`, so any malicious SQL that passes validation is fully executed.
- **Recommended Fix:**
  - Add a blacklist of dangerous SQL patterns: `GRANT`, `REVOKE`, `CREATE USER`, `DROP USER`, `LOAD_FILE`, `INTO OUTFILE`, `INTO DUMPFILE`, `LOAD DATA`
  - Restrict restore to only allow `CREATE TABLE`, `INSERT`, `ALTER TABLE`, `DROP TABLE`, `SET`, `USE` statements
  - Add a whitelist approach instead of blacklist for maximum safety
  - Show a preview of what the restore will do before executing

---

### H5 — No OTP Rate Limiting ✅

- **File:** `forgotpass.php`
- **Problem:** No limit on how many OTP requests can be sent or how many verification attempts can be made. The OTP is a 6-digit numeric code (1,000,000 combinations). An attacker could:
  - Spam OTP requests, causing email flooding
  - Brute force the 6-digit OTP within the 10-minute window
- **Recommended Fix:**
  - Limit OTP requests to 3 per email per hour
  - Limit OTP verification attempts to 5 per session
  - After exceeding limits, lock the forgot-password feature for that email for 30 minutes
  - Track attempts in session or database

---

### H6 — Database Error Messages Exposed to Users ⏭️

- **Files:** 10+ files throughout the codebase
- **Problem:** `$e->getMessage()` from PDO exceptions is shown directly to users in HTML or JSON responses, potentially leaking table names, column names, and query structure.
- **Decision: DEFERRED** — The system is currently under development, so showing detailed error messages is acceptable for debugging purposes.
- **Future action:** Before going to production, wrap all `$e->getMessage()` outputs with a check:
  ```php
  $message = env('APP_DEBUG', false) ? $e->getMessage() : 'An unexpected error occurred.';
  ```

---

### H7 — `export_sample_dtr.php` Allows Anonymous Access 🗑️

- **File:** `admin/export_sample_dtr.php`
- **Problem:** Sets `$allowAnonymous = true` when not logged in, allowing anyone to generate Excel files.
- **Decision: DELETE THE FILE** — This was a test file used during development to test exported DTR files. It is no longer needed.
- **Action:** Remove `admin/export_sample_dtr.php` from the codebase.

---

## MEDIUM SEVERITY

### M1 — IDOR in Notification APIs ✅

- **Files:** `admin/notifications_api.php`, `staff/notifications_api.php`, `user/notifications_api.php`
- **Problem:** When marking a notification as read or deleting it, the API accepts `notification_id` from POST but never verifies that the notification belongs to the current `$_SESSION['user_id']`. A user could guess/enumerate notification IDs and read/delete another user's notifications.
- **Recommended Fix:**
  - Add a WHERE clause: `WHERE id = ? AND user_id = ?` (using `$_SESSION['user_id']`)
  - This ensures users can only modify their own notifications

---

### M2 — XSS in HTML Attributes ✅

- **File:** `staff/payslip_history.php` (~line 147)
- **Problem:** `data-employee-name="<?php echo strtolower($employee['full_name']); ?>"` — the employee name is placed in an HTML attribute without `htmlspecialchars()`. If an employee name contains a `"` character, it breaks out of the attribute and could allow XSS injection.
- **Recommended Fix:**
  - Use `htmlspecialchars()` on all data attributes:
    ```php
    data-employee-name="<?php echo htmlspecialchars(strtolower($employee['full_name']), ENT_QUOTES); ?>"
    ```
  - Audit all files for similar unescaped data attributes

---

### M3 — Missing MIME Type Validation on DTR Import ✅

- **File:** `admin/import_dtr.php` (~line 113-120)
- **Problem:** Excel file uploads validate the file extension (xlsx/xls/xlsm/csv) but do not check the MIME type. A malicious file with a renamed extension could potentially exploit PhpSpreadsheet library bugs.
- **Recommended Fix:**
  - Add MIME type check:
    ```php
    $allowedMimes = [
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-excel',
        'text/csv',
        'application/vnd.ms-excel.sheet.macroEnabled.12'
    ];
    if (!in_array($_FILES['file']['type'], $allowedMimes)) { ... }
    ```
  - Also validate using `finfo_file()` for server-side MIME detection

---

### M4 — Fallback Database Credentials ⏭️

- **File:** `config/database.php`
- **Problem:** If `.env` is missing, the system silently falls back to `root` with an empty password.
- **Decision: DEFERRED** — The system is under development and runs on Laragon locally. This is acceptable for the current development environment.
- **Future action:** Before production deployment, add a hard fail if `.env` is missing or required DB variables are empty.

---

## UX — SPAMMABLE BUTTONS

> All form submit buttons and AJAX action buttons listed below can be spam-clicked, causing duplicate submissions (duplicate DB entries, duplicate requests, or race conditions).

### S1 — Login Submit Button ✅

- **File:** `login.php` (~line 183)
- **Problem:** `<button type="submit" class="btn-login">LOGIN</button>` — no JS to disable on click. Rapid clicks cause multiple POST requests and multiple login log entries.
- **Fix:** Disable button on form submit, show a spinner, re-enable on error.

---

### S2 — Add Employee Submit Button ✅

- **File:** `admin/Add_emplooyees.php` (~line 292)
- **Problem:** The submit button has no disable-on-submit handler. The form's JS `submit` listener only validates — it never disables the button. Rapid clicks can create duplicate employees with sequential employee codes.
- **Fix:** Disable button on form submit, show "Saving..." text.

---

### S3 — Add User Submit Button ✅

- **File:** `admin/add_user.php` (~line 255)
- **Problem:** Same as S2 — submit button is never disabled during form submission.
- **Fix:** Disable button on form submit.

---

### S4 — Profile Update & Change Password (All 3 Roles) ✅

- **Files:** `admin/profile.php` (~line 334), `staff/profile.php` (~line 334), `user/profile.php` (~line 334)
- **Problem:** "Update Profile" and "Change Password" buttons are standard `type="submit"`. Between click and page reload, the button is still clickable. No JS handler disables them during submission.
- **Fix:** Add `onsubmit` handler to disable the submit button and show a loading state.

---

### S5 — Delete Employee Confirm Button ✅

- **File:** `admin/employee_list.php` (~line 1239)
- **Problem:** `confirmDeleteAction()` fires a `fetch()` POST to `delete_employee.php` but never disables the "Yes, Delete" button. Rapid clicks send duplicate delete requests.
- **Fix:** Disable the confirm button immediately when clicked, re-enable on error.

---

### S6 — Delete User Confirm Button ✅

- **File:** `admin/user_management.php` (~line 1297)
- **Problem:** `confirmDeleteAction()` uses `window.location.href = 'delete_user.php?id=...'`. Button is not disabled. Also ties into C2 (GET method + no CSRF). Will be fixed together with C2.
- **Fix:** Convert to POST + CSRF (C2 fix), and disable button on click.

---

### S7 — Forgot Password Submit (All 3 Steps) ✅

- **File:** `forgotpass.php`
- **Problem:** The 3-step forgot password flow (email → OTP → new password) has no double-submit protection on any of the three forms.
- **Fix:** Disable submit button on each form submission step.

---

## CODE QUALITY — INPUT VALIDATION

### V1 — No Maximum Length on Any Form Input ✅

- **Files:** All form pages (`Add_emplooyees.php`, `add_user.php`, all `profile.php`, etc.)
- **Problem:** No `maxlength` HTML attribute is set on any `<input>` element. No corresponding server-side maximum length checks exist. A user could submit a 10,000-character name, causing DB errors or silent truncation.
- **Fix:** Add `maxlength` attributes matching the DB column sizes (e.g., `VARCHAR(100)` → `maxlength="100"`). Add server-side `strlen()` validation to match.

---

### V2 — Weak Password Policy ✅

- **Files:** `admin/add_user.php` (~line 64), all `profile.php` files
- **Problem:** Password minimum is only 6 characters with no complexity requirements enforced server-side. The client-side strength meter shows levels but doesn't prevent submission. A password like "123456" passes all validation.
- **Fix:**
  - Enforce server-side: minimum 8 characters, at least 1 uppercase, 1 lowercase, 1 number
  - Make the client-side strength meter block submission if strength is below "Medium"

---

### V3 — No Salary Upper Bound ✅

- **File:** `admin/Add_emplooyees.php` (~line 62)
- **Problem:** Salary validation only checks `< 0`. No upper bound — a typo of `99999999999` would be accepted and could break payroll calculations.
- **Fix:** Add reasonable upper bound (e.g., max ₱999,999.99) and a `max` attribute on the input.

---

### V4 — Full Name Accepts Any Characters ✅

- **Files:** `admin/Add_emplooyees.php` (~line 56), `admin/add_user.php` (~line 55)
- **Problem:** Full name validation only checks `strlen >= 2`. No character restriction — allows numbers, special characters like `<script>`, emoji, etc.
- **Fix:** Add a regex check for valid name characters (letters, spaces, hyphens, periods, apostrophes):
  ```php
  if (!preg_match("/^[a-zA-Z\s\-'.]+$/", $full_name)) {
      $errors[] = "Full name contains invalid characters.";
  }
  ```

---

## OTHER ISSUES

### O1 — `staff/payslip_history.php` Includes Header Twice ✅

- **File:** `staff/payslip_history.php` (line 8 and line 50)
- **Problem:** `require_once 'include/header.php';` appears on both line 8 and line 50. While `require_once` prevents actual double-execution, the second call is redundant and confusing. The sidebar is only included on line 51 (after the DB query block), making the code structure misleading.
- **Fix:** Remove the duplicate `require_once` on line 8 (or line 50, whichever is the incorrect placement).

---

### O2 — Massive Inline CSS/JS in Pages 🔄 (Partially Done)

- **Problem:** Every page contains hundreds of lines of inline `<style>` and `<script>` blocks. This prevents browser CSS/JS caching and makes maintenance difficult.
- **Completed extractions:**
  - ✅ All 3 `profile.php` files → `assets/css/profile.css` + `assets/js/profile.js`
  - ✅ All 3 `account_logs.php` files → `assets/css/account_logs.css` + `assets/js/account_logs.js`
  - ✅ `assets/css/generatepayroll.css` created (3,607 lines extracted from admin/Generatepayroll.php)
- **Remaining:** 19 files with ~23,800 total lines of inline CSS/JS still to extract.
- **Full plan:** See [CSS_JS_EXTRACTION_PLAN.md](../system/CSS_JS_EXTRACTION_PLAN.md) for comprehensive file-by-file extraction schedule with line numbers, priorities, and shared-file candidates.

---

### O3 — Error Alerts Auto-Dismiss After 5 Seconds ✅

- **Files:** `admin/Add_emplooyees.php` (~line 798), `admin/profile.php` (~line 1195), and others
- **Problem:** `setTimeout` auto-dismisses alerts after 5 seconds. This applies to **error messages** too — critical error feedback disappears before the user may have finished reading it.
- **Fix:** Only auto-dismiss **success** messages. Keep error/warning alerts visible until manually closed (add an X close button).

---

### O4 — No Loading Overlay on Modals ✅

- **Files:** Edit modals in `admin/employee_list.php`, `admin/user_management.php`
- **Problem:** When edit modals save via AJAX, the submit button shows a spinner, but the modal itself has no overlay. The user can still interact with form fields, change values, or click other buttons while the save is in-flight.
- **Fix:** Added semi-transparent loading overlay (`.modal-loading-overlay`) with spinner + text, shown during AJAX save, hidden on success/error. Blocks all form interaction while save is in-flight.

---

## ARCHITECTURE — CODE DUPLICATION ⏭️

> **Decision: DEFERRED** — Refactoring duplicated code into shared files carries risk of breaking the UI across all three role modules (admin/staff/user). This will be addressed in a future refactoring pass when the system is more stable.
>
> **Documented here for reference:**

### D1 — Footer Files: 100% Identical (3 copies, ~434 lines total)

- `admin/include/footer.php`
- `staff/include/footer.php`
- `user/include/footer.php`
- All three are byte-for-byte identical (logout modal HTML + CSS + JS).
- **Future fix:** Extract to a single `shared/include/footer.php`.

### D2 — Header Files: ~95% Identical (3 copies, ~220 lines duplicated)

- `admin/include/header.php`
- `staff/include/header.php`
- `user/include/header.php`
- Only differences: `requireAuth('admin'/'staff'/'user')`, default name string, role label, title suffix.
- **Future fix:** Single shared header that accepts role as a parameter.

### D3 — Profile Pages: ~95% Identical (3 copies, ~2,200 lines duplicated)

- `admin/profile.php` (1,204 lines)
- `staff/profile.php` (1,109 lines)
- `user/profile.php` (1,109 lines)
- Only differences: breadcrumb link and included header/sidebar paths.
- **Future fix:** Single shared profile page with role-specific includes.

### D4 — Notifications API: ~95% Identical (3 copies, ~250 lines duplicated)

- `admin/notifications_api.php`
- `staff/notifications_api.php`
- `user/notifications_api.php`
- Only difference: auth role check on line 14-17.
- **Future fix:** Single `shared/notifications_api.php` with dynamic role check.

### D5 — Account Logs Pages: ~70% Identical (3 copies, ~1,400 lines duplicated)

- `admin/account_logs.php` (951 lines)
- `staff/account_logs.php` (1,051 lines)
- `user/account_logs.php` (715 lines)
- Staff and user are nearly identical. Admin adds a user filter dropdown.
- **Future fix:** Shared template with role-specific query modifications.

### D6 — JS Validation Helpers Duplicated in 5+ Files (~500 lines)

- `showFieldError()`, `clearFieldError()`, `markFieldValid()`, `updatePasswordStrength()`, `togglePassword()`
- Duplicated in: all `profile.php`, `add_user.php`, `Add_emplooyees.php`
- **Future fix:** Extract to `assets/js/form-validation.js`.

### Total Estimated Duplication: ~5,000+ lines

---

## FILE NAMING INCONSISTENCIES ⏭️

> **Note:** Renaming files requires updating all references (sidebar links, form actions, redirects, JS fetch calls). This is tracked here for a future cleanup pass.

| File | Issue |
|------|-------|
| `admin/Add_emplooyees.php` | Typo ("emplooyees") + PascalCase (should be `add_employees.php`) |
| `admin/Generatepayroll.php` | PascalCase (should be `generate_payroll.php`) |
| `staff/Generatepayroll.php` | PascalCase (should be `generate_payroll.php`) |
| All sidebar/nav links reference typo filename | Would need updating when file is renamed |

---

## IMPLEMENTATION PRIORITY (Suggested Order)

| Priority | Items | Rationale |
|----------|-------|-----------|
| **1 — Do First** | C1, C2, C3 | Critical vulnerabilities — security holes that could cause data loss or unauthorized access |
| **2 — Do Second** | H1, H2, H3, H5 | High severity — missing auth/CSRF on endpoints |
| **3 — Do Third** | H4, M1, M2, M3 | Medium-high — data integrity and injection risks |
| **4 — Do Fourth** | S1–S7 | UX — prevent double submissions across all forms |
| **5 — Do Fifth** | V1–V4 | Code quality — input validation improvements |
| **6 — Do Sixth** | O1–O4 | Minor UX and code cleanup |
| **7 — Future** | D1–D6, File Naming | Architecture refactoring — defer until system is stable |

---

## DELETIONS PLANNED

| File | Reason | Status |
|------|--------|--------|
| `admin/export_sample_dtr.php` | Test file no longer needed; also has anonymous access vulnerability (H7) | ✅ Neutralized (returns 410 Gone; delete file manually) |

---

## CHANGE LOG

| Date | Change | Author |
|------|--------|--------|
| Mar 2, 2026 | Created this document from comprehensive codebase audit | — |
| Mar 2, 2026 | Neutralized `admin/export_sample_dtr.php` — returns 410 Gone, all code unreachable (H7). Delete file manually when convenient. | — |
| Mar 2, 2026 | Fixed account logs filter buttons (Apply Filter / Reset disabled when empty) in admin, staff, and user versions | — |
| Mar 2, 2026 | **Systematic implementation of all actionable items:** C1 (login rate limiting), C2 (delete_user POST+CSRF), C3 (migration auth), H1 (9 admin API role checks), H2 (4 diagnostic auth guards), H3 (CSRF on update_dtr), H4 (backup path traversal), H5 (OTP rate limiting + resend cooldown), M1 (notification ID validation + error masking), M2 (XSS attribute escaping), M3 (MIME validation on DTR import), S1-S7 (all spammable buttons), V1-V4 (input validation improvements), O1 (double include), O3 (error alert persistence). **26 items completed.** | — |
| Mar 2, 2026 | **O4 implemented:** Added modal loading overlays to edit modals in `admin/employee_list.php` and `admin/user_management.php`. Semi-transparent overlay with spinner blocks form interaction during AJAX save. | — |
| Mar 2, 2026 | **O2 partial:** Extracted inline CSS/JS for profile pages (3 files) and account_logs pages (3 files) to shared external files. Created `CSS_JS_EXTRACTION_PLAN.md` with comprehensive extraction schedule for remaining 19 files (~23,800 lines). | — |

---

*This document will be updated as improvements are implemented.*
