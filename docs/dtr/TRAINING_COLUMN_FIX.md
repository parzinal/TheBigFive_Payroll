# Training Column Fix - DTR System

## Issue
The training checkbox in the DTR calculator was not being saved or retrieved from the database. While salary data was correctly fetched, the checked status of training days was lost after saving.

## Root Cause
The `dtr_records` table was missing an `is_training` column to store training day data. The UI had training checkboxes but the backend wasn't persisting this information.

## Files Modified

### Database Schema
1. **config/sql/add_training_column.sql** (NEW)
   - Migration script to add `is_training` column to existing databases

2. **config/sql/create_database.sql**
   - Updated schema to include `is_training` column for new installations

3. **admin/DTR_IMPORT_README.md**
   - Updated documentation with new column

### Backend (PHP)
4. **admin/save_dtr.php**
   - Added `is_training` to INSERT statement
   - Added `is_training` to ON DUPLICATE KEY UPDATE
   - Extract `is_training` from POST data
   - Include `is_training` in execute parameters

5. **admin/get_employee_dtr_data.php**
   - Added `is_training` to SELECT query (both period and month-based queries)

6. **admin/update_dtr_records.php**
   - Added `is_training` to UPDATE statement
   - Include `is_training` in execute parameters

### Frontend (JavaScript)
7. **admin/payroll_list.php**
   - Display training checkbox with correct checked state: `${rec.is_training ? 'checked' : ''}`
   - Collect `is_training` value when saving changes

8. **admin/Generatepayroll.php**
   - Collect `is_training` from TB5 format records
   - Collect `is_training` from regular format records

## How to Apply

### Step 1: Run Database Migration
Execute the SQL migration to add the `is_training` column:

```bash
# Using phpMyAdmin or MySQL command line
mysql -u your_username -p thebigfive_payroll < config/sql/add_training_column.sql
```

Or manually run this SQL:
```sql
USE thebigfive_payroll;
ALTER TABLE dtr_records 
ADD COLUMN IF NOT EXISTS is_training BOOLEAN DEFAULT FALSE AFTER is_absent;
```

### Step 2: Verify
1. Open the DTR calculator (Generatepayroll.php)
2. Select an employee and period
3. Check some training checkboxes
4. Save the DTR
5. Reload the same employee and period
6. Verify training checkboxes are still checked

### Step 3: Test in Payroll List
1. Open Payroll List (payroll_list.php)
2. Click on an employee card
3. Select a period with saved DTR data
4. Verify training checkboxes show the correct state
5. Enable Edit Mode, modify training checkboxes
6. Save changes
7. Reload and verify changes persisted

## Technical Details

### Database Column
- **Column Name**: `is_training`
- **Type**: `BOOLEAN` (equivalent to `TINYINT(1)`)
- **Default**: `FALSE` (0)
- **Position**: After `is_absent` column
- **Nullable**: No (uses default)

### Data Flow
1. User checks training checkbox in UI
2. JavaScript collects checkbox state: `checkbox.checked ? 1 : 0`
3. PHP receives `is_training` in POST/JSON data
4. PHP saves to `dtr_records.is_training` column
5. When fetching, PHP SELECTs `is_training` from database
6. JavaScript renders checkbox: `<input type="checkbox" ${rec.is_training ? 'checked' : ''}>`

## Impact
- **Backwards Compatible**: Existing records will have `is_training = 0` (not training)
- **No Data Loss**: Previous DTR records remain intact
- **Performance**: Minimal - single boolean column
- **UI**: No visual changes, just functional fix
