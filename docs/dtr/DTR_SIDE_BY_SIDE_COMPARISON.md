# DTR Template: Side-by-Side Comparison
## Current Template vs. Actual TB5 Format

---

## ROW-BY-ROW COMPARISON

### ROW 1: Title
```
CURRENT TEMPLATE:
┌─────────────────────────────────────────┬────────────┐
│ DTR CALCULATOR                          │ Oct. 13... │
│ [Merged A1:D1]                          │ [E1]       │
│ [BLUE BG #1F4E79, WHITE TEXT]           │ [Italic]   │
└─────────────────────────────────────────┴────────────┘

ACTUAL TB5:
┌───────────────────────────────┬────────────────────────────────┐
│ DTR CALCULATOR                │ (no merge)                     │
│ [A1 only]                     │                                │
│ [Gray text #404040, NO BG]    │                                │
└───────────────────────────────┴────────────────────────────────┘

❌ DIFFERENT: Merge pattern, colors, layout
```

### ROW 2: Date & Employee Info
```
CURRENT TEMPLATE:
┌────────────┬──────────────┬──────────────────────────────────────┐
│ EMP NAME:  │ FREEDOM      │                                      │
│ [A2 Bold]  │ [B2:C2]      │                                      │
│ [Yellow BG]│ [Yellow BG]  │                                      │
└────────────┴──────────────┴──────────────────────────────────────┘

ACTUAL TB5:
┌────────────────────────────────────┬──────┬─────────────────────────┐
│ =DATE!A2 → "Oct. 13-27, 2025"      │ ↓↓↓↓ │ ◄ INPUT SALARY HERE     │
│ [A2:G2 MERGED]                     │ [N2] │ [O2]                    │
│ [Brown text #953735, FORMULA!]     │ [RED]│ [Bold]                  │
└────────────────────────────────────┴──────┴─────────────────────────┘

❌ DIFFERENT: Formula vs static, different content, different position
```

### ROW 3: Company Info
```
CURRENT TEMPLATE:
┌──────────────────────────────────────────────────┐
│ THE BIG FIVE TRAINING AND ASSESSMENT...         │
│ [A3:E3 MERGED]                                   │
│ [Bold, size 9]                                   │
└──────────────────────────────────────────────────┘

ACTUAL TB5:
┌────────────────────────────────┬──────────┬───────────────────────────┐
│ EMPLOYEE NAME: FREEDOM          │ Times... │ BASIC ► ₱13,000 | Rates  │
│ [A3:I3 MERGED]                  │ [J3-S3]  │ [N3=13000, O3-Q3 formulas│
│ [Gray BG #D9D9D9, Bold]         │          │ [All RED text, Bold]     │
└────────────────────────────────┴──────────┴───────────────────────────┘

❌ DIFFERENT: Complete row mismatch - TB5 has employee in row 3, not company
```

### ROW 4: Headers Start
```
CURRENT TEMPLATE:
┌────┬────┬────┬────┬────┬────┬────┬────┬────┬───────┬─────┐
│MO/Y│ AM │    │ PM │    │ABS │ OT │HALF│    │TOT.WRK│LATE │...
│[A4]│[B4:C4]  │[D4:E4]  │[F4]│[G4]│[H4:I4]  │[J4]   │[K4] │
│    │[Orange] │[Orange] │[Red│[Blu│[Yellow] │[Green]│[Grn]│
└────┴────┴────┴────┴────┴────┴────┴────┴────┴───────┴─────┘

ACTUAL TB5:
┌──────────────────────────────────────┬────────────┬──────────────────┐
│ THE BIG FIVE TRAINING...             │ ALSO UT OR │ Times & Labels   │
│ [A4:G4 MERGED]                       │ [H4]       │ [J4-Y4]          │
│ [Bold]                               │ [Blue BG]  │ [Gray BG]        │
└──────────────────────────────────────┴────────────┴──────────────────┘

❌ DIFFERENT: TB5 has company name in row 4, not headers
```

### ROW 5-6: Column Headers
```
CURRENT TEMPLATE (Rows 4-5):
Row 4: Category headers (MO/YR, AM, PM, ABSENT, OT...)
Row 5: Detail headers (DATE, IN, OUT, IN, OUT, Column...)

ACTUAL TB5 (Rows 5-6):
Row 5: Category headers (MO/YR, AM, PM, ABSENT, OT, HALFDAY, calculations...)
Row 6: Detail headers (DATE, IN, OUT, Column, OUT, HOURS, LATE...)

❌ DIFFERENT: One row offset, different colors (TB5 uses more gray)
```

---

## COLUMN COUNT COMPARISON

### CURRENT TEMPLATE: 19 Columns (A-S)
```
A   - DATE
B   - AM IN
C   - AM OUT
D   - PM IN
E   - PM OUT
F   - ABSENT
G   - OT OUT
H   - HALFDAY IN
I   - HALFDAY OUT
J   - TOT.WORK (static value)
K   - LATE (static value)
L   - UNDERTIME (static value)
M   - OT (static value)
N   - ABSENT DAY (static value)
O   - LATE DEDUCT (static value)
P   - UNDERTIME DEDUCT (static value)
Q   - HALFDAY DEDUCT (static value)
R   - OT PAY (static value)
S   - REMARKS
```

### ACTUAL TB5: 28 Columns (A-AB)
```
A   - DATE
B   - AM IN
C   - AM OUT
D   - PM IN
E   - PM OUT
F   - ABSENT
G   - OT OUT (FORMULA!)
H   - HALFDAY IN
I   - HALFDAY OUT
J   - TOT.WORK (FORMULA!)
K   - LATE (FORMULA!, in MINUTES)
L   - UNDERTIME (FORMULA!)
M   - OT (FORMULA!)
N   - ABSENT DAY (FORMULA!)
O   - ABSENT DEDUCT (FORMULA!)
P   - LATE DEDUCT (FORMULA!)
Q   - UNDERTIME DEDUCT (FORMULA!)
R   - HALFDAY DEDUCT (FORMULA!)
S   - OT PAYMENT (FORMULA!)
T   - TOTAL DAY ADJUST (FORMULA!)
U   - LATE/min helper (FORMULA!)     ← MISSING
V   - UNDERTIME helper (FORMULA!)    ← MISSING
W   - OT helper (FORMULA!)           ← MISSING
X   - BENEFITS (manual input)        ← MISSING
Y   - NET SALARY (FORMULA!)          ← MISSING
Z   - Flag 1 (FORMULA!)              ← MISSING
AA  - Flag 2 (FORMULA!)              ← MISSING
AB  - Remarks (manual input)         ← MISSING
```

**Missing:** 9 columns (U-AB)

---

## DATA ROW COMPARISON (Example Row 7)

### CURRENT TEMPLATE (Row 6 in generated file):
```php
// PHP generates static values:
$sheet->setCellValue("A6", "10/13");          // Static date
$sheet->setCellValue("B6", "8:05");           // Static time
$sheet->setCellValue("C6", "12:00");          // Static time
$sheet->setCellValue("D6", "1:00");           // Static time
$sheet->setCellValue("E6", "5:00");           // Static time
$sheet->setCellValue("F6", "");               // Empty
$sheet->setCellValue("G6", "");               // Static value
$sheet->setCellValue("H6", "");               // Empty
$sheet->setCellValue("I6", "");               // Empty
$sheet->setCellValue("J6", 480);              // Static number
$sheet->setCellValue("K6", 0.08);             // Static number
$sheet->setCellValue("L6", 0);                // Static number
$sheet->setCellValue("M6", 0);                // Static number
$sheet->setCellValue("N6", 0);                // Static number
$sheet->setCellValue("O6", 0.00);             // Static number
$sheet->setCellValue("P6", 0.00);             // Static number
$sheet->setCellValue("Q6", 0.00);             // Static number
$sheet->setCellValue("R6", 0.00);             // Static number
$sheet->setCellValue("S6", "");               // Empty
// NO COLUMNS U-AB!
```

### ACTUAL TB5 (Row 7):
```excel
// Excel formulas (not PHP values):
A7: 1                                        // Static day number
B7:                                          // User input (time)
C7:                                          // User input (time)
D7:                                          // User input (time)
E7:                                          // User input (time)
F7:                                          // User input (absent marker)
G7: =IF(E7>$J$4,E7,"")                      // FORMULA
H7:                                          // User input (halfday)
I7:                                          // User input (halfday)
J7: =(MOD(E7-B7,1)*24)-1                    // FORMULA
K7: =(MOD(B7-$K$3,1)*1440)                  // FORMULA (minutes!)
L7: =MOD($L$3-E7,1)*24                      // FORMULA
M7: =((MOD(G7-$L$3,1)*24)+...)              // FORMULA (complex)
N7: =COUNTIF(B7:H7,"ABSENT")                // FORMULA
O7: =($N$3/30)*N7                           // FORMULA
P7: =$Q$3*U7                                // FORMULA (uses helper U7!)
Q7: =$P$3*V7                                // FORMULA (uses helper V7!)
R7: =($O$3*AA7)-(MOD(I7-H7,1)*24.29)*$P$3  // FORMULA (uses helper AA7!)
S7: =($P$3*W7)*125%                         // FORMULA (uses helper W7!)
T7: =S7-SUM(O7:R7)                          // FORMULA
U7: =IF(AND(K7>499,K7>=271),0,...)          // FORMULA (helper)
V7: =IF(AND(L7>17,L7>=17),0,...)            // FORMULA (helper)
W7: =IFERROR(M7*1,0)                        // FORMULA (helper)
X7:                                          // User input (benefits)
Y7:                                          // FORMULA (in summary row)
Z7: =COUNTBLANK(I7)                         // FORMULA (flag)
AA7: =COUNTIF(Z7,"0")                       // FORMULA (flag)
AB7:                                         // User input (remarks)
```

**Key Difference:**
- ❌ Current template: Static calculated values
- ✅ Actual TB5: Live Excel formulas that auto-calculate

---

## COLOR SCHEME COMPARISON

### CURRENT TEMPLATE Colors:
```
Title (A1):        BLUE background #1F4E79, WHITE text
Employee (B2):     YELLOW background #FFFF00, RED text
AM columns:        ORANGE background #FFCC99
PM columns:        ORANGE background #FFCC99
ABSENT column:     RED background #FF9999
OT column:         BLUE background #99CCFF
HALFDAY columns:   YELLOW background #FFFF99
Calculation cols:  GREEN background #CCFFCC
Deduction cols:    PINK background #FFCCCC
OT Pay column:     GREEN background #99FF99
Data rows:         NO special colors (white/default)
```

### ACTUAL TB5 Colors:
```
Title (A1):        NO background, GRAY text #404040
Date (A2):         NO background, BROWN text #953735
Employee row (A3): GRAY background #D9D9D9
AM columns:        LIGHT BLUE background #B6DEE8
PM columns:        ORANGE background #FABF8F
ABSENT column:     DARK GRAY background #808080, WHITE text
OT column:         GRAY background #A6A6A6
HALFDAY columns:   PEACH background #FDEADA
Calculation cols:  GRAY background #A6A6A6, WHITE text
Total column (T):  LIGHT GRAY background #EAEAEA
Data rows:         BLACK background #000000, WHITE text ← CRITICAL!
Helper columns:    BLACK background #000000, WHITE text
```

**Major Difference:**
- ❌ Current template: Colorful headers, white data rows
- ✅ Actual TB5: **BLACK BACKGROUNDS on all formula cells in data rows**

---

## FORMULA vs. STATIC VALUE COMPARISON

### Example: Late Deduction Calculation

**CURRENT TEMPLATE (Static):**
```php
// PHP calculates and inserts value:
$lateMin = 5;  // 5 minutes late
$perMin = 1.0417;
$lateDeduct = round($lateMin * $perMin, 2);  // = 5.21
$sheet->setCellValue("O6", $lateDeduct);     // Cell shows: 5.21

// If user changes time entry B6:
// - Deduction O6 DOES NOT UPDATE (static value!)
```

**ACTUAL TB5 (Formula):**
```excel
// Excel formula in cell P7:
P7: =$Q$3*U7

// Where:
//   $Q$3 = 1.0417 (per-minute rate formula)
//   U7 = =IF(AND(K7>499,K7>=271),0,IF(K7<=270,K7,0))
//   K7 = =(MOD(B7-$K$3,1)*1440)

// If user changes time entry B7:
// - K7 recalculates (new late minutes)
// - U7 recalculates (cleaned late minutes)
// - P7 recalculates (new late deduction)
// EVERYTHING AUTO-UPDATES!
```

**Verdict:** ❌ Current template cannot auto-recalculate

---

## RATE CALCULATION COMPARISON

### CURRENT TEMPLATE:
```php
// Row 2 in generated file:
$sheet->setCellValue('K2', '500');      // Static value
$sheet->setCellValue('L2', '62.50');    // Static value
$sheet->setCellValue('M2', '1.0417');   // Static value

// If user changes salary in J2:
// - Rates K2, L2, M2 DO NOT UPDATE
```

### ACTUAL TB5:
```excel
// Row 3:
N3: 13000                              // User input
O3: =$N$3/30                          // Formula → 433.33
P3: =($N$3/30)/8                      // Formula → 54.17
Q3: =($N$3/30)/480                    // Formula → 0.9028

// If user changes N3 to 15000:
// - O3 updates to 500.00
// - P3 updates to 62.50
// - Q3 updates to 1.0417
// - ALL deductions in data rows AUTO-UPDATE
```

**Verdict:** ❌ Current template has no formula-based rates

---

## BOTTOM AREA COMPARISON

### CURRENT TEMPLATE:
```
Row after last data: TOTALS row
- A: "TOTALS:"
- J: =SUM(J6:J35)
- K: =SUM(K6:K35)
- L: =SUM(L6:L35)
... etc

That's it. No additional calculations.
```

### ACTUAL TB5:
```
Row 38: Partial totals
- T38: =SUM(T7:T37)           // Total adjustments
- X38: =SUM(X7:X37)           // Total benefits
- Y38: (FINAL NET SALARY - merged Y38:AA38)

Row 39: Deduction totals
- O39: =SUM(O7:O38)           // Total absent deductions
- P39: =SUM(P7:P38)           // Total late deductions
- Q39: =SUM(Q7:Q38)           // Total undertime deductions
- S39: =SUM(S7:S38)           // Total OT payments

Row 41-44: Additional calculations
- N41:O41: "Days Office" (merged)
- P42: =P41*O3                // Office days × per-day rate
- N43:O43: "No. of Trainings" (merged)
- P44: =P43*1000              // Training calculations

Final Salary Calculation:
Y38: =P42+S39-O39-P39-Q39-X38
     = (Days worked × rate)
       + OT payments
       - Absent deductions
       - Late deductions
       - Undertime deductions
       - Cash advances
```

**Verdict:** ❌ Current template missing entire bottom calculation area

---

## FILE OUTPUT COMPARISON

### CURRENT TEMPLATE Generates:
```
File: DTR_Template_2026-02-22.xlsx
Size: ~15-20 KB
Contains:
- 1 data sheet
- 1 instructions sheet
- 19 columns of data
- Static values in cells
- Colorful headers
- Basic totals row
```

### ACTUAL TB5 File:
```
File: DTR-Sample-TB5.xlsm
Size: 89,982 bytes (~90 KB)
Contains:
- 1 main data sheet (Freedom)
- 1 DATE sheet (for date formula reference)
- 28 columns of data
- 471+ formulas
- Black backgrounds on formula cells
- Complex bottom calculation area
- Macro-enabled (xlsm format)
```

**Verdict:** ❌ Current template is simplified version (1/4 the size, 1/2 the columns)

---

## IMPORT COMPATIBILITY

### What Current Template CAN do:
✅ Provides basic structure for data entry
✅ Has employee name label
✅ Has salary field
✅ Has time entry columns
✅ Can be imported (basic data extraction works)

### What Current Template CANNOT do:
❌ Auto-calculate when user changes time entries
❌ Update deductions when salary changes
❌ Provide helper column calculations
❌ Match exact TB5 appearance
❌ Support complex bottom area calculations
❌ Provide formula-based validation

### What Actual TB5 CAN do:
✅ Everything above, PLUS:
✅ Live recalculation when times change
✅ Auto-update all deductions when salary changes
✅ Complex OT detection and calculation
✅ Helper columns for data validation
✅ Multiple calculation layers
✅ Professional appearance (black/white theme)
✅ Training cost calculations
✅ Net salary computation

---

## SUMMARY TABLE

| Feature | Current Template | Actual TB5 | Match? |
|---------|-----------------|------------|--------|
| **Columns** | 19 (A-S) | 28 (A-AB) | ❌ |
| **Data Format** | Static values | Formulas | ❌ |
| **Row Structure** | Rows 1-5 headers, 6+ data | Rows 1-6 headers, 7+ data | ❌ |
| **Color Scheme** | Colorful (blue/yellow/green/pink) | Gray/Black theme | ❌ |
| **Formula Cells** | None | 471+ formulas | ❌ |
| **Helper Columns** | None | U, V, W, Z, AA | ❌ |
| **Auto-Calculate** | No | Yes | ❌ |
| **Rate Formulas** | Static values | Formulas linked to N3 | ❌ |
| **Bottom Area** | Simple totals | Complex calculations | ❌ |
| **File Size** | ~15-20 KB | ~90 KB | ❌ |
| **Import-able** | Yes | Yes | ✅ |
| **Employee Label** | "EMPLOYEE NAME:" | "EMPLOYEE NAME:" | ✅ |
| **Salary Label** | "BASIC" | "BASIC ►" | ✅ |

**Overall Match:** 2/12 features (16.7%) ❌

---

## RECOMMENDATION

**To match TB5 exactly, the template generator needs:**

1. ✅ Add 9 columns (U-AB)
2. ✅ Change all calculated cells to formulas
3. ✅ Update row structure (shift everything down 1 row)
4. ✅ Change color scheme to black/gray/white theme
5. ✅ Add formula-based rate calculations
6. ✅ Add helper column formulas
7. ✅ Add bottom calculation area (rows 38-44)
8. ✅ Add DATE reference sheet
9. ✅ Change file format to .xlsm (macro-enabled)
10. ✅ Update merged cell patterns

**Estimated work:** Complete rewrite of `download_dtr_template.php`

---

**END OF COMPARISON**
