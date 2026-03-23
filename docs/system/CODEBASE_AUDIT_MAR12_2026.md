# TheBigFive Payroll System — Codebase Audit (March 12, 2026)

> **Scope:** Comprehensive audit of the entire codebase, verifying previously completed fixes and identifying **new issues** not covered in the original improvements plan.
>
> **Previous work:** 26 items from `SYSTEM_IMPROVEMENTS_PLAN.md` were confirmed as implemented and functional (March 2, 2026).

---

## Verification: Previously Completed Fixes (All Confirmed ✅)

All 26 items from the March 2 improvements plan were verified in the actual source code:

- **C1** Login rate limiting — DB-based, 5 attempts/15 min by IP ✅
- **C2** delete_user.php — POST + CSRF + JSON API ✅
- **C3** Migration endpoints — admin auth required ✅
- **H1** Admin API role checks — 9 endpoints secured ✅
- **H2** Diagnostic page auth guards — 4 files secured ✅
- **H3** CSRF on update_dtr_records.php ✅
- **H4** Backup path traversal protection ✅
- **H5** OTP rate limiting + resend cooldown ✅
- **H7** export_sample_dtr.php neutralized (410 Gone) ✅
- **M1** Notification IDOR fix ✅
- **M2** XSS attribute escaping ✅
- **M3** DTR import MIME validation ✅
- **S1–S7** All spammable buttons disabled during submit ✅
- **V1–V4** Input validation improvements ✅
- **O1** Duplicate header include removed ✅
- **O3** Error alerts no longer auto-dismiss ✅
- **O4** Modal loading overlays added ✅
- **O2** CSS/JS extraction partially done (6 files completed, 19 remaining) ✅

---

## NEW Issues Found — Not In Previous Documentation

### CRITICAL — Fix Immediately

| ID | File | Issue |
|----|------|-------|
| N1 | `admin/export_dtr_pdf_proxy.php` | **No authentication at all.** The proxy file includes `export_dtr_pdf.php` but has zero session/role checks. Anyone can generate DTR PDFs for any employee by visiting this URL with an employee/period ID. While the included file has its own auth check, the proxy pattern is fragile and bypasses it depending on execution context. |
| N2 | `admin/add_payroll_list_columns_migration.php` | **No authentication.** Only requires `database.php`. Any visitor can run ALTER TABLE migrations on the database. Unlike the other migration files (`run_migration.php`, `migrate_employee_classification.php`) which were secured in C3, this file was missed. |
| N3 | `analyze_dtr_detailed.php` + `analyze_dtr_sample.php` | **No authentication.** Root-level utility scripts accessible to any web visitor. Should be restricted to CLI execution (`php_sapi_name() === 'cli'`) or deleted entirely. |
| N4 | `config/smtp.php` — `generateOTP()` | Uses **`rand()`** which is NOT cryptographically secure. OTP codes are predictable. Must use `random_int()` instead. |
| N5 | `config/smtp.php` — `sendPasswordResetSuccessEmail()` | `$_SERVER['HTTP_HOST']` is user-controlled and used to build password-reset email links. This is a **host header injection** vulnerability that enables phishing attacks. Should use `APP_URL` from `.env` instead. |
| N13 | `config/` directory | **No `.htaccess` file.** If PHP fails or is misconfigured, `database.php`, `smtp.php`, and `auth.php` source code (containing credentials) would be served as plain text. An `.htaccess` with `Deny from all` should be added. |

### HIGH — Fix Soon

| ID | File | Issue |
|----|------|-------|
| N6 | `config/account_logs_helper.php` — `getClientIP()` | Trusts spoofable proxy headers (`HTTP_X_FORWARDED_FOR`, `HTTP_CLIENT_IP`) without `filter_var($ip, FILTER_VALIDATE_IP)` validation. IP values are stored in the database and displayed in the admin Account Logs page — this is a **stored XSS vector** if the IP string isn't escaped on output. |
| N7 | `admin/user_management.php` ~line 39 | **Role/status not whitelisted on user update.** The `$role` and `$status` from `$_POST` are accepted without validation against an allowed list. A crafted POST could set `role=superadmin` or any arbitrary value. Privilege escalation risk. |
| N8 | `admin/process_payroll.php` ~line 176 | Error response returns the raw **SQL query string (`$stmt->queryString`) + full parameter array** in the JSON response. Massive information disclosure — leaks table names, column names, and data values to the client. |
| N9 | `staff/save_payslip.php` | Same as N8 — error catch block returns raw SQL query + params in JSON response to the client. |
| N10 | `admin/save_cutoff_payslip.php` | Debug log (`@file_put_contents(__DIR__ . '/../debug_cutoff_payslip.log', ...)`) writes full SQL + params to a potentially **web-accessible log file**. Also has dead code (second `$checkStmt` overwrites first `$existing`) and double `ob_end_clean()`. |
| N11 | `config/csrf.php` ~line 108 | **Open redirect** — `$_SERVER['HTTP_REFERER']` used directly in `Location` header on CSRF validation failure. The referer header is user-controlled and could redirect to a malicious site. Should validate against a whitelist or use a hardcoded fallback. |
| N12 | `config/csrf.php` | `requireCSRFToken()` only validates POST requests. **PUT/DELETE/PATCH** requests from AJAX are not validated, meaning they bypass CSRF protection entirely. Should check `$_SERVER['REQUEST_METHOD'] !== 'GET'` instead. |

### MEDIUM — Should Fix

| ID | File | Issue |
|----|------|-------|
| N14 | `forgotpass.php` | OTP rate limit is **session-based** (`$_SESSION` counters). An attacker can bypass all rate limiting by simply not sending session cookies (new session = fresh counters). Should use DB storage keyed by email or IP address. |
| N15 | `config/auth.php` — `redirectToProperDashboard()` | Missing `exit()` after `header('Location: ...')`. If this function is ever called standalone (not via `requireAuth` which does call exit), code execution continues after the redirect header is sent. |
| N16 | `config/BackupManager.php` — `restoreViaPDO()` | Reads the entire SQL file into memory with `file_get_contents()`. Large backup files could exhaust PHP memory. Also, `validateBackupFile()` only inspects the first 4KB of the file — a malicious payload after that point would pass validation. |
| N17 | 10+ files across codebase | **DB error messages exposed** to users via `$e->getMessage()` in JSON responses and `die()` calls. Affected files: `add_user.php`, `delete_user.php`, `delete_employee.php`, `user_management.php`, `Add_emplooyees.php`, `process_payroll.php`, `save_payslip.php`, `payslip_history.php`, `get_dtr_records.php`, `get_payslip_by_employee_period.php`, `generate_payslip_pdf.php`, `export_dtr_table.php`. Should `error_log()` internally and return generic messages. |
| N18 | `admin/payroll_list.php` | **Performance problem.** Main SQL query uses 9+ correlated subqueries per employee row (each re-fetching from `payroll_computations` with identical filters). Will degrade significantly as employee count grows. Should use JOINs or CTEs. |
| N19 | `forgotpass.php`, `add_user.php`, all `profile.php` | **Weak password policy.** Minimum 6 characters with no complexity requirement. A password like "123456" passes all validation. Should enforce 8+ characters with at least 1 uppercase, 1 lowercase, 1 number. |
| N20 | `admin/export_dtr_data.php`, `admin/export_dtr_pdf.php` | **CSRF on GET export endpoints.** An attacker can craft `<img>` or link tags to trigger data/PDF downloads if the victim is logged in (session cookies sent automatically). Consider requiring a CSRF token parameter or switching to POST. |
| N21 | `config/sql/README.md` | Default credentials in plaintext committed in the repo: `admin/admin123`, `staff/staff123`, `user/user123`. These should be set during installation, not hardcoded in seed SQL. |
| N22 | `.gitignore` | `/admin/vendor/` directory not in `.gitignore`. The admin directory has its own Composer vendor folder that could be accidentally committed. |
| N23 | `login.php` ~line 37 | `CREATE TABLE IF NOT EXISTS login_attempts` runs on **every single login POST**. Should be a one-time migration script, not per-request DDL. |
| N24 | `config/bootstrap.php` | Sets `X-Frame-Options`, `X-Content-Type-Options`, `X-XSS-Protection`, `Referrer-Policy` — but is **missing `Content-Security-Policy` header**, which is the most important XSS mitigation header. |

### LOW — Nice to Fix

| ID | File | Issue |
|----|------|-------|
| N25 | `admin/get_employee_details.php` | Hardcoded UK placeholder PII (address: "123 Any Court Road, London W1T 1JY, UK", phone: "+44 00 0000 0000") used as defaults. Confusing for a Philippine payroll system — should use Philippine format or empty strings. |
| N26 | `staff/Generatepayroll.php` | `json_decode($_POST['dtr_data'], true)` result not null-checked before `foreach`. Malformed JSON input causes a PHP warning/error. |
| N27 | `staff/save_payslip.php` | No input validation on JSON body fields. Missing or null monetary fields are silently inserted as null into the database without type or existence checks. |
| N28 | `admin/migrate_training_column.php` | Hardcoded database name `thebigfive_payroll` in the INFORMATION_SCHEMA query. Will break if the database name ever changes. Should use `DATABASE()` function instead. |
| N29 | All 3 `notifications_api.php` files | `requireCSRFToken()` runs unconditionally — including on GET requests (e.g., `?action=fetch`). This is unusual and could break simple GET fetches from JavaScript that don't include a CSRF token. |

---

## Architecture Issues (Previously Documented, Still Pending)

These were already tracked in `SYSTEM_IMPROVEMENTS_PLAN.md` and are documented here for completeness:

- **~5,000+ lines of duplicated code** across admin/staff/user modules (D1–D6)
- **~23,800 lines of inline CSS/JS** remaining in 19 files (O2, see `CSS_JS_EXTRACTION_PLAN.md`)
- **File naming inconsistencies** — `Add_emplooyees.php` (typo), `Generatepayroll.php` (PascalCase)
- **Dual Composer setups** — root `composer.json` and `admin/composer.json` with overlapping `vlucas/phpdotenv` dependency

---

## Recommended Fix Priority

| Batch | Items | Rationale |
|-------|-------|-----------|
| **1 — Immediate** | N1, N2, N3, N4, N5, N13 | Zero-auth endpoints and cryptographic weakness — actively exploitable |
| **2 — This week** | N6, N7, N8, N9, N10, N11, N12 | Data leaks, privilege escalation, open redirect |
| **3 — Soon** | N14, N15, N17, N19, N20, N21, N23, N24 | Rate limit bypass, weak passwords, error disclosure |
| **4 — When convenient** | N16, N18, N22, N25–N29, CSS/JS extraction | Performance, code quality, cosmetic |
