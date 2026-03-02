# DTR-Sample-TB5.xlsm STRUCTURE ANALYSIS & COMPARISON REPORT
**Date:** February 22, 2026  
**File:** DTR-Sample-TB5.xlsm (89,982 bytes)  
**Sheet Name:** Freedom  
**Total Rows:** 1000 (but active data in first ~44 rows)  
**Total Columns:** AC (29 columns)  

---

## EXECUTIVE SUMMARY

The actual TB5 Excel template has **SIGNIFICANTLY different** structure compared to the current `download_dtr_template.php`. The actual template has:
- **27+ columns** of data & calculations (vs. 19 in current template)
- **Complex Excel formulas** in data rows (not just raw values)
- **Separate helper columns** (U, V, W, Z, AA) for calculations
- **Different color scheme** (black backgrounds, white text for calculation cells)
- **Different row structure** (employee info in row 3, not row 2)
- **Additional calculation area** at bottom (rows 38-44)

---

## SECTION 1: EXACT ROW-BY-ROW STRUCTURE (Rows 1-6)

### **ROW 1: Main Title**
```
A1: "DTR CALCULATOR" [BOLD, Gray Font #404040]
```
- **Actual:** Simple text, no merge, no background
- **Current Template:** Merged A1:D1, white text on blue background (#1F4E79)
- **DIFFERENCE:** ❌ Layout and styling completely different

### **ROW 2: Date Range & Salary Input Instruction**
```
A2: =DATE!A2 → displays "Oct. 13-27, 2025" [FORMULA, Brown font #953735]
    (Merged A2:G2)
N2: "↓↓↓↓" [RED FONT #FF0000, BOLD]
O2: "  ◄   INPUT BASIC MONTHLY SALARY HERE" [BOLD]
```
- **Actual:** Date is a formula referencing another sheet called "DATE"
- **Current Template:** Date is hardcoded text in E1
- **DIFFERENCE:** ❌ Formula-based vs. static, different cell positions

### **ROW 3: Employee Info & Rate Configuration**
```
A3: "EMPLOYEE NAME: FREEDOM" [Merged A3:I3, Gray BG #D9D9D9, BOLD]
J3: 0.74027... → displays "5:46 PM" [BOLD]
K3: 0.33680... → displays "7:35 AM" [RED, BOLD] ← Grace end time reference
L3: 0.70833... → displays "5:00 PM" [RED, BOLD] ← Shift end time reference  
M3: "BASIC ►" [RED, BOLD]
N3: 13000 → displays "₱13,000" [RED, BOLD] ← Basic Monthly Salary INPUT
O3: =($N$3/30) → 433.33 [RED, BOLD] ← PER DAY rate
P3: =($N$3/30)/8 → 54.17 [RED, BOLD] ← PER HOUR rate
Q3: =($N$3/30)/480 → 0.9028 [RED, BOLD] ← PER MINUTE rate
R3: 0.5 [BOLD]
S3: 0.34027... → displays "8:10 AM" [BOLD]
T3: "neg-₱TOTAL" [Light Gray BG #EAEAEA, BOLD]
U3-W3: "AUTOMATIC CALCULATIONS" [Merged U3:W3, Gray BG #A6A6A6, RED text]
X3: "(MANUAL)" [Gray BG #A6A6A6, WHITE text]
Y3: "Automatic" [Gray BG #A6A6A6, WHITE text]
AB3: "REMARKS" [Gray BG #A6A6A6, RED text]
```
- **Actual:** Employee name in A3 (merged to I3), rates calculated via formulas
- **Current Template:** Employee name in A2:B2, rates in K2:M2 as static values
- **DIFFERENCE:** ❌ Completely different row structure and calculation approach

### **ROW 4: Company Name & Reference Times**
```
A4: "THE BIG FIVE TRAINING AND ASSESSMENT CENTER INC." [Merged A4:G4, BOLD]
H4: "ALSO UNDERTIME OR" [Light Blue BG #B8CCE4, BOLD]
J4: 0.73958... → "5:45 PM" [BOLD] ← Referenced as $J$4 in formulas
K4: 0.3375 → "8:06 AM" [BOLD]
L4: 0.34444... → "8:16 AM" [BOLD]
N4: "VARIABLE" [RED, BOLD]
O4: "PER/DAY"
P4: "PER/MIN"
Q4: "PER/HOUR" [BOLD]
R4: "AUTOMATIC" [BOLD]
S4: 0.34375 → "8:15 AM" [BOLD]
T4: "DEDUCTIONS" [Gray BG #A6A6A6, BOLD]
U4-Y4: "AUTO", "AUTO", "AUTO", "CA ADV.+", "TOTAL" [All Gray BG, WHITE text, BOLD]
```
- **Actual:** Company name in row 4, reference times for formulas
- **Current Template:** Company name in row 3
- **DIFFERENCE:** ❌ Different row position

### **ROW 5: Main Category Headers**
```
A5: "MO/YR" [Gray BG #BFBFBF]
B5-C5: "AM" [Light Blue BG #B6DEE8]
D5-E5: "PM" [Orange BG #FABF8F]
F5: "ABSENT" [Dark Gray #808080, WHITE text]
G5: "OT" [Gray BG #A6A6A6]
H5-I5: "HALFDAY" [Peach BG #FDEADA]
J5-S5: Various calculation headers [Gray BG #A6A6A6, WHITE text]
T5: "MINUS OT" [Gray BG #A6A6A6]
U5-W5: "COPY" [Gray BG #A6A6A6, WHITE text]
X5: "GOV'T." [Gray BG #A6A6A6, WHITE text]
Y5: "NET" [Gray BG #A6A6A6, WHITE text]
```
- **Actual:** Extensive gray header backgrounds, specific colors per section
- **Current Template:** Different color scheme (orange, red, blue, green, pink)
- **DIFFERENCE:** ❌ Completely different color coding

### **ROW 6: Detailed Column Headers**
```
A6: "DATE" [RED text, BOLD]
B6: "IN" [RED text, BOLD]
C6: "OUT" [RED text, BOLD]
D6: "IN" [RED text, BOLD]
E6: "OUT" [RED text, BOLD]
F6: "Column" [Gray BG #808080, WHITE text, BOLD]
G6: "OUT" [Gray BG #A6A6A6, RED text, BOLD]
H6-I6: "IN", "OUT" [RED text, BOLD]
J6-S6: "HOURS", "LATE", "UNDERTM", "OT", "ABSENT", "DEDUCT", "DEDUCT", "DEDUCT", "DEDUCT", "PAYMENT", "(OT PAY)" [Gray BG, WHITE text, BOLD]
T6: "(OT PAY)" [Gray BG #A6A6A6, BOLD]
U6-W6: "LATE/min", "UNDERTIME", "OT" [Gray BG, WHITE text]
X6: "BENEFITS" [Gray BG, WHITE text]
Y6: "SALARY" [Gray BG, WHITE text]
Z6: "F1*" [Gray BG, WHITE text]
AA6: "F2*" [Gray BG, RED text]
AB6: "Rremarks" [Gray BG]
```
- **Actual:** 28 columns (A-AB), includes helper columns U, V, W, Z, AA
- **Current Template:** Only 19 columns (A-S)
- **DIFFERENCE:** ❌ Missing 9 columns

---

## SECTION 2: DATA ROW STRUCTURE (Row 7+ Example)

### **ACTUAL TB5 FORMAT (Row 7 as example):**

| Column | Value | Type | Description |
|--------|-------|------|-------------|
| A | "1" | Static | Day number |
| B-E | (empty) | Input | Time entries (AM In, AM Out, PM In, PM Out) |
| F | (empty) | Input | Absent marker |
| G | =IF(E7>$J$4,E7,"") | **FORMULA** | OT Out time (auto-calculated) |
| H-I | (empty) | Input | Halfday times |
| J | =(MOD(E7-B7,1)*24)-1 | **FORMULA** | Total work hours |
| K | =(MOD(B7-$K$3,1)*1440) | **FORMULA** | Late in MINUTES (not hours!) |
| L | =MOD($L$3-E7,1)*24 | **FORMULA** | Undertime in hours |
| M | =((MOD(G7-$L$3,1)*24)+(IF(B7<$S$3,"+0.00","-0.5")))-(IF(G7<$J$3,"-0.5","0")) | **FORMULA** | OT hours (complex) |
| N | =COUNTIF(B7:H7,"ABSENT") | **FORMULA** | Absent count |
| O | =($N$3/30)*N7 | **FORMULA** | Absent deduction |
| P | =$Q$3*U7 | **FORMULA** | Late deduction (uses U7) |
| Q | =$P$3*V7 | **FORMULA** | Undertime deduction (uses V7) |
| R | =($O$3*AA7)-(MOD(I7-H7,1)*24.29)*$P$3 | **FORMULA** | Halfday deduction |
| S | =($P$3*W7)*125% | **FORMULA** | OT payment (uses W7) |
| T | =S7-SUM(O7:R7) | **FORMULA** | Total day adjustment |
| U | =IF(AND(K7>499,K7>=271),0,IF(K7<=270,K7,0)) | **FORMULA** | Late minutes (helper) |
| V | =IF(AND(L7>17,L7>=17),0,IF(L7<=15,L7,0)) | **FORMULA** | Undertime calc (helper) |
| W | =IFERROR(M7*1,0) | **FORMULA** | OT hours clean (helper) |
| X | (empty/manual) | Input | Cash advance/benefits |
| Y | (formula in summary row) | **FORMULA** | Net salary |
| Z | =COUNTBLANK(I7) | **FORMULA** | Flag 1 |
| AA | =COUNTIF(Z7,"0") | **FORMULA** | Flag 2 |
| AB | (empty) | Input | Remarks |

**KEY CHARACTERISTICS:**
- ✅ ALL calculation cells (G, J-W, Z-AA) have **FORMULAS**, not static values
- ✅ BLACK background (#000000) on formula cells with WHITE text (#FFFFFF)
- ✅ Helper columns (U, V, W) for intermediate calculations
- ✅ Flag columns (Z, AA) for tracking
- ✅ Late is calculated in **MINUTES** in column K, then converted via helper column U

### **CURRENT TEMPLATE FORMAT:**
- ❌ All values are **STATIC** (raw calculated numbers, not formulas)
- ❌ No helper columns (U, V, W, Z, AA)
- ❌ Different color scheme (no black backgrounds)
- ❌ Late calculated in hours (not minutes)
- ❌ Only 19 columns (missing U-AB)

---

## SECTION 3: FORMULA ANALYSIS

### **Key Formula References:**
- `$N$3` - Basic monthly salary (user input in N3)
- `$O$3` - Per-day rate (=$N$3/30)
- `$P$3` - Per-hour rate (=$N$3/30/8 = 54.17)
- `$Q$3` - Per-minute rate (=$N$3/30/480)
- `$K$3` - Grace end time (7:35 AM)
- `$L$3` - Shift end time (5:00 PM)
- `$J$4` - OT threshold time (5:45 PM)
- `$S$3` - Late threshold (8:10 AM)

### **Important Formula Patterns:**

**1. OT Detection (Column G):**
```excel
=IF(E7>$J$4,E7,"")
```
If PM Out > 5:45 PM, show the time (indicates OT)

**2. Total Work Hours (Column J):**
```excel
=(MOD(E7-B7,1)*24)-1
```
Calculates work hours from time entries

**3. Late Calculation (Column K):**
```excel
=(MOD(B7-$K$3,1)*1440)
```
Calculates minutes late (returns 955 when empty - needs negative handling!)

**4. Late Deduction (Column P):**
```excel
=$Q$3*U7
```
Per-minute rate × late minutes FROM HELPER COLUMN U

**5. Helper Column U (Late Minutes Clean):**
```excel
=IF(AND(K7>499,K7>=271),0,IF(K7<=270,K7,0))
```
Filters out invalid late values

**6. OT Payment (Column S):**
```excel
=($P$3*W7)*125%
```
⚠️ **Uses $P$3 (per-hour rate) but W7 is in hours, should be per-minute × minutes!**

---

## SECTION 4: COLOR CODING SCHEME

### **ACTUAL TB5 COLORS:**

| Color | RGB | Usage |
|-------|-----|-------|
| Gray | #D9D9D9 | Employee info row (A3) |
| Light Gray | #EAEAEA | Total column T header |
| Dark Gray | #A6A6A6 | Calculation column headers |
| Light Gray | #BFBFBFBF | Date header (A5) |
| Light Blue | #B6DEE8 | AM columns (B5, C5) |
| Orange | #FABF8F | PM columns (D5, E5) |
| Dark Gray | #808080 | Absent column (F5, F6) |
| Peach | #FDEADA | Halfday columns (H5, I5) |
| Light Blue | #B8CCE4 | "ALSO UNDERTIME OR" label (H4) |
| **BLACK** | #000000 | **All formula cells in data rows (G7+, J7-W7, Z7, AA7)** |
| Red | #FF0000 | Input indicators (N2, K3-M3, N3-Q3, etc.) |
| White | #FFFFFF | Text on dark backgrounds |
| Brown | #953735 | Date formula cell (A2) |
| Gray | #404040 | Title font (A1) |

### **CURRENT TEMPLATE COLORS:**
- Blue #1F4E79 - Title background
- Yellow #FFFF00 - Employee name background
- Orange #FFCC99 - AM/PM columns
- Red #FF9999 - Absent column
- Blue #99CCFF - OT column
- Yellow #FFFF99 - Halfday columns
- Green #CCFFCC - Calculation columns
- Pink #FFCCCC - Deduction columns
- **NO BLACK BACKGROUNDS**

**DIFFERENCE:** ❌ Completely different color scheme. TB5 uses black backgrounds for formula cells.

---

## SECTION 5: MERGED CELLS

**ACTUAL TB5 MERGED CELLS:**
1. `A2:G2` - Date range "Oct. 13-27, 2025"
2. `A3:I3` - Employee name "EMPLOYEE NAME: FREEDOM"
3. `U3:W3` - "AUTOMATIC CALCULATIONS"
4. `A4:G4` - Company name
5. `Y38:AA38` - Total salary display
6. `N41:O41` - "Days Office"
7. `N43:O43` - "No. of Trainings"
8. `N44:O44` - "Total cost Trainings"

**CURRENT TEMPLATE MERGED CELLS:**
1. `A1:D1` - Title
2. `B2:C2` - Employee name
3. `A3:E3` - Company name
4. `B4:C4` - AM header
5. `D4:E4` - PM header
6. `H4:I4` - Halfday header

**DIFFERENCE:** ❌ Different merge patterns, TB5 has more extensive merges

---

## SECTION 6: BOTTOM CALCULATION AREA (Rows 38-44)

The actual TB5 template has additional rows at the bottom:

**Row 38-39: Summary Totals**
```
T38: =SUM(T7:T37)  - Total daily adjustments
X38: =SUM(X7:X37)  - Total benefits/cash advances
Y38: =P42+S39-O39-P39-Q39-X38  - Final net salary (MERGED Y38:AA38)
O39-S39: Various SUM formulas for deduction totals
```

**Rows 41-44: Training & Office Days**
```
N41:O41: "Days Office" (merged)
P42: =P41*O3  - Office days × per-day rate = 7000
N43:O43: "No. of Trainings" (merged)
P44: =P43*1000  - Training cost calculation
```

**DIFFERENCE:** ❌ Current template has simple TOTALS row, TB5 has complex multi-row calculation area

---

## SECTION 7: KEY DIFFERENCES SUMMARY

### **STRUCTURAL DIFFERENCES:**

| Aspect | Actual TB5 | Current Template | Status |
|--------|------------|------------------|--------|
| **Total Columns** | 28 (A-AB) | 19 (A-S) | ❌ Missing 9 columns |
| **Data Cell Format** | Formulas | Static values | ❌ Wrong approach |
| **Helper Columns** | U, V, W, Z, AA | None | ❌ Missing |
| **Employee Row** | Row 3 (A3:I3) | Row 2 (A2:C2) | ❌ Different |
| **Rate Config** | Row 3 (N3:Q3) formulas | Row 2 static | ❌ Different |
| **Company Row** | Row 4 | Row 3 | ❌ Different |
| **Header Rows** | 5-6 | 4-5 | ❌ Different |
| **Data Start Row** | 7 | 6 | ❌ Different |
| **Background Colors** | Black for formulas | Various colors | ❌ Different |
| **Text Colors** | White on black | Various | ❌ Different |
| **Bottom Area** | Rows 38-44 calculations | Simple totals | ❌ Missing |
| **Date Cell** | Formula =DATE!A2 | Static text | ❌ Different |
| **Late Calculation** | Minutes (K column) | Hours | ❌ Different |
| **OT Detection** | Formula in G column | Static value | ❌ Different |

### **FORMULA DIFFERENCES:**

| Calculation | TB5 Formula | Current Template | Status |
|-------------|-------------|------------------|--------|
| Per-day rate | =$N$3/30 | Static 433.33 | ❌ |
| Per-hour rate | =$N$3/30/8 | Static 54.17 | ❌ |
| Per-minute rate | =$N$3/30/480 | N/A | ❌ |
| Late minutes | =(MOD(B7-$K$3,1)*1440) | Static | ❌ |
| Late deduction | =$Q$3*U7 (uses helper) | Static | ❌ |
| OT detection | =IF(E7>$J$4,E7,"") | Static | ❌ |
| OT payment | =($P$3*W7)*125% | Static | ❌ |
| Total work | =(MOD(E7-B7,1)*24)-1 | Static | ❌ |
| Absent count | =COUNTIF(B7:H7,"ABSENT") | Static | ❌ |

---

## SECTION 8: IMPORT COMPATIBILITY ISSUES

Based on `import_dtr.php` analysis, the current import expects:

### **What Import Looks For:**
1. "EMPLOYEE NAME:" label to extract name
2. "BASIC" or salary amount to extract monthly rate
3. Date range from header
4. Data rows with time entries

### **Issues with Current Template:**
1. ✅ Has "EMPLOYEE NAME:" label
2. ✅ Has "BASIC" label
3. ✅ Has date range
4. ❌ Generates static values instead of formulas
5. ❌ Missing helper columns that import might expect
6. ❌ Different row structure might confuse parser
7. ❌ Color scheme doesn't match original

### **Issues with Actual TB5:**
1. ✅ Has all formulas
2. ✅ Has all 28 columns
3. ✅ Has correct structure
4. ⚠️ Import would read FORMULA RESULT VALUES (not formulas themselves)
5. ⚠️ Import needs to handle helper columns (U, V, W, Z, AA)
6. ⚠️ Import needs to handle empty cells that have formulas

---

## SECTION 9: RECOMMENDATIONS

### **For Template Generator (download_dtr_template.php):**

1. **Add Missing Columns:**
   - Add columns U (Late/min helper), V (Undertime helper), W (OT helper)
   - Add columns Z (Flag 1), AA (Flag 2)
   - Add columns X (Benefits), Y (Net Salary)

2. **Change Row Structure:**
   - Move employee info to Row 3 (A3:I3 merged)
   - Move company to Row 4 (A4:G4 merged)
   - Start headers at Row 5-6
   - Start data at Row 7

3. **Add Formulas Instead of Static Values:**
   - Column G: `=IF(E{row}>$J$4,E{row},"")`  for OT detection
   - Column J: `=(MOD(E{row}-B{row},1)*24)-1` for work hours
   - Column K: `=(MOD(B{row}-$K$3,1)*1440)` for late minutes
   - Column L: `=MOD($L$3-E{row},1)*24` for undertime
   - Column M: Complex OT formula
   - Column N: `=COUNTIF(B{row}:H{row},"ABSENT")`
   - Column O: `=($N$3/30)*N{row}`
   - Column P: `=$Q$3*U{row}`
   - Column Q: `=$P$3*V{row}`
   - Column R: `=($O$3*AA{row})-(MOD(I{row}-H{row},1)*24.29)*$P$3`
   - Column S: `=($P$3*W{row})*125%`
   - Column T: `=S{row}-SUM(O{row}:R{row})`
   - Column U: `=IF(AND(K{row}>499,K{row}>=271),0,IF(K{row}<=270,K{row},0))`
   - Column V: `=IF(AND(L{row}>17,L{row}>=17),0,IF(L{row}<=15,L{row},0))`
   - Column W: `=IFERROR(M{row}*1,0)`
   - Column Z: `=COUNTBLANK(I{row})`
   - Column AA: `=COUNTIF(Z{row},"0")`

4. **Update Color Scheme:**
   - Use BLACK (#000000) background for formula cells (G7+, J7-W7, Z7, AA7)
   - Use WHITE (#FFFFFF) text on black backgrounds
   - Use exact color codes from TB5
   - Gray #A6A6A6 for calculation headers
   - Light blue #B6DEE8 for AM headers
   - Orange #FABF8F for PM headers

5. **Add Reference Cells:**
   - K3: Grace end time (7:35 AM)
   - L3: Shift end time (5:00 PM)
   - J4: OT threshold (5:45 PM)
   - S3: Late threshold (8:10 AM)
   - N3: Basic monthly salary (editable)
   - O3: Formula =($N$3/30)
   - P3: Formula =($N$3/30)/8
   - Q3: Formula =($N$3/30)/480

6. **Add Bottom Calculation Area:**
   - Rows 38-39 for totals
   - Rows 41-44 for office days and training calculations

### **For Import Parser (import_dtr.php):**

1. **Handle Formula Cells:**
   - Read calculated values (not formulas)
   - Helper columns U, V, W should be ignored (they're for calculations)
   - Flag columns Z, AA should be ignored

2. **Update Row Detection:**
   - Look for employee name in Row 3 (not Row 2)
   - Look for headers in Row 6 (not Row 5)
   - Data starts at Row 7 (not Row 6)

3. **Handle Additional Columns:**
   - Extract from columns A-S (ignore T-AB as they're calculations)
   - Or: Extract all and store calculated values

---

## SECTION 10: COLUMN MAPPING REFERENCE

**Complete TB5 Column Structure:**

```
A  - DATE (Day number: 1, 2, 3...)
B  - AM IN (Time input)
C  - AM OUT (Time input)
D  - PM IN (Time input)
E  - PM OUT (Time input)
F  - ABSENT (Mark "X" or "ABSENT")
G  - OT OUT [FORMULA] (Auto-calculated from E)
H  - HALFDAY IN (Time input)
I  - HALFDAY OUT (Time input)
J  - TOT.WORK HOURS [FORMULA] (in hours, minus 1)
K  - LATE [FORMULA] (in MINUTES - raw calculation)
L  - UNDERTIME [FORMULA] (in hours)
M  - OT [FORMULA] (OT hours - complex formula)
N  - ABSENT [FORMULA] (Count of absents = 0 or 1)
O  - ABSENT DEDUCT [FORMULA] (Per-day × absent days)
P  - LATE/MIN DEDUCT [FORMULA] (Per-minute × U7)
Q  - UNDERTIME DEDUCT [FORMULA] (Per-hour × V7)
R  - HALFDAY DEDUCT [FORMULA] (Complex halfday calculation)
S  - OT PAYMENT [FORMULA] (Per-hour × W7 × 125%)
T  - (OT PAY) TOTAL [FORMULA] (S - SUM(O:R))
U  - LATE/min [FORMULA HELPER] (Cleaned late minutes)
V  - UNDERTIME [FORMULA HELPER] (Cleaned undertime hours)
W  - OT [FORMULA HELPER] (Cleaned OT hours)
X  - GOV'T. BENEFITS (Manual input)
Y  - NET SALARY [FORMULA] (Final calculation)
Z  - F1* [FORMULA FLAG] (COUNTBLANK check)
AA - F2* [FORMULA FLAG] (COUNTIF check)
AB - Remarks (Manual input)
```

---

## SECTION 11: EXACT CELL VALUES FOR TESTING

**Test Case: Empty DTR Row (Row 7 with no time entries)**

Expected formula results when B7-E7 are empty:
- G7: "" (empty, because E7 not > J4)
- J7: "-" (displays as dash due to invalid calculation)
- K7: "955" (large number because B7 is empty)
- L7: "17.0" (17 hours - because E7 is empty)
- M7: "#VALUE!" (error because G7 is empty)
- N7: "0" or "" (no ABSENT marker found)
- O7: "₱0.00" (no absent deduction)
- P7: "₱0.00" (no late deduction)
- Q7: "₱0.00" (no undertime deduction)
- R7: "" or "₱0.00" (no halfday deduction)
- S7: "₱0.00" (no OT payment)
- T7: "" or "₱0.00" (total = 0)
- U7: "  - " (no valid late value)
- V7: "  -  " (undertime > 17, so 0)
- W7: "" or "0" (OT error handled)
- Z7: "1" (I7 is blank)
- AA7: "" or "0" (Z7 is not "0")

**Test Case: Normal Day with 8:05 AM in, 12:00 PM, 1:00 PM in, 5:00 PM out**
- Would calculate 0 late (exactly on time)
- Would calculate 480 minutes (8 hours) work
- Would show all deductions as ₱0.00

---

## FINAL VERDICT

The current `download_dtr_template.php` generates a **SIMPLIFIED VERSION** that:
- ✅ Has the basic structure for data input
- ✅ Has most of the required columns for import
- ❌ **MISSING** 9 columns (U, V, W, X, Y, Z, AA, plus adjusted T)
- ❌ **WRONG** cell format (static values vs. formulas)
- ❌ **WRONG** color scheme (TB5 uses black backgrounds)
- ❌ **WRONG** row structure (off by 1-2 rows)
- ❌ **MISSING** bottom calculation area
- ❌ **MISSING** formula-based rate calculations

**TO MATCH TB5 EXACTLY:**  
The template generator needs a **COMPLETE REWRITE** with:
1. Corrected row structure (rows 1-6)
2. All 28 columns (A-AB)
3. Excel formulas in data rows (not static values)
4. Exact color scheme (black backgrounds, white text)
5. Proper merged cells
6. Bottom calculation area (rows 38-44)
7. Reference cells with formulas (K3, L3, J4, S3, N3-Q3)

---

**END OF REPORT**
