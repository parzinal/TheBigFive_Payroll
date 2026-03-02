# DTR-Sample-TB5.xlsm QUICK REFERENCE SUMMARY

## FILE STATS
- **File:** DTR-Sample-TB5.xlsm (89,982 bytes)
- **Sheet:** "Freedom"  
- **Columns:** 28 (A-AB)
- **Data Rows:** 7-37 (31 days)

---

## ROW STRUCTURE QUICK MAP

```
Row 1:  DTR CALCULATOR [Title]
Row 2:  Date Range (A2:G2 merged) | ↓↓↓↓ (N2) | INPUT SALARY HERE (O2)
Row 3:  EMPLOYEE NAME: FREEDOM (A3:I3 merged) | Times (J3,K3,L3) | BASIC ► ₱13,000 (M3,N3) | Rates (O3,P3,Q3)
Row 4:  THE BIG FIVE... (A4:G4 merged) | Reference times | Column labels
Row 5:  Main category headers (AM, PM, ABSENT, OT, HALFDAY, calculations...)
Row 6:  Detailed column headers (DATE, IN, OUT, IN, OUT, Column, OUT...)
Row 7+: DATA ROWS (all formulas!)
Row 38+: TOTALS & CALCULATIONS
```

---

## COLUMN QUICK MAP (A-AB = 28 columns)

### INPUT COLUMNS (User enters data):
- **A** - Date (day number)
- **B-E** - Time entries (AM In/Out, PM In/Out)
- **F** - Absent marker ("X" or "ABSENT")
- **H-I** - Halfday times (In/Out)
- **X** - Benefits/Cash Advance (manual)
- **AB** - Remarks (manual)

### FORMULA COLUMNS (Auto-calculated, BLACK background, WHITE text):
- **G** - OT Out detection: `=IF(E7>$J$4,E7,"")`
- **J** - Work hours: `=(MOD(E7-B7,1)*24)-1`
- **K** - Late (MINUTES!): `=(MOD(B7-$K$3,1)*1440)`
- **L** - Undertime (hours): `=MOD($L$3-E7,1)*24`
- **M** - OT hours (complex formula)
- **N** - Absent count: `=COUNTIF(B7:H7,"ABSENT")`
- **O** - Absent deduct: `=($N$3/30)*N7`
- **P** - Late deduct: `=$Q$3*U7`
- **Q** - Undertime deduct: `=$P$3*V7`
- **R** - Halfday deduct: `=($O$3*AA7)-(MOD(I7-H7,1)*24.29)*$P$3`
- **S** - OT payment: `=($P$3*W7)*125%`
- **T** - Total adjustment: `=S7-SUM(O7:R7)`

### HELPER COLUMNS (Support calculations):
- **U** - Late minutes (cleaned): `=IF(AND(K7>499,K7>=271),0,IF(K7<=270,K7,0))`
- **V** - Undertime (cleaned): `=IF(AND(L7>17,L7>=17),0,IF(L7<=15,L7,0))`
- **W** - OT hours (cleaned): `=IFERROR(M7*1,0)`
- **Z** - Flag 1: `=COUNTBLANK(I7)`
- **AA** - Flag 2: `=COUNTIF(Z7,"0")`

### NET SALARY COLUMN:
- **Y** - Net salary (calculated in summary rows)

---

## KEY REFERENCE CELLS (Used in formulas)

Located in **Row 3-4:**
- **N3** - Basic Monthly Salary (USER INPUT: ₱13,000)
- **O3** - Per-day rate: `=$N$3/30` = 433.33
- **P3** - Per-hour rate: `=($N$3/30)/8` = 54.17
- **Q3** - Per-minute rate: `=($N$3/30)/480` = 0.9028
- **K3** - Grace end time: 7:35 AM (used as $K$3)
- **L3** - Shift end time: 5:00 PM (used as $L$3)
- **J4** - OT threshold: 5:45 PM (used as $J$4)
- **S3** - Late threshold: 8:10 AM (used as $S$3)

---

## COLOR SCHEME

| Color | RGB Code | Usage |
|-------|----------|-------|
| **Black** | #000000 | Formula cells in data rows (MOST IMPORTANT!) |
| White | #FFFFFF | Text on black backgrounds |
| Gray | #D9D9D9 | Employee info row background |
| Gray | #A6A6A6 | Calculation column headers |
| Light Blue | #B6DEE8 | AM column headers |
| Orange | #FABF8F | PM column headers |
| Dark Gray | #808080 | Absent column |
| Peach | #FDEADA | Halfday columns |
| Red | #FF0000 | Important labels/input indicators |
| Light Gray | #EAEAEA | Total column (T) header |

---

## MERGED CELLS

- **A2:G2** - Date range
- **A3:I3** - Employee name
- **U3:W3** - "AUTOMATIC CALCULATIONS"
- **A4:G4** - Company name
- **Y38:AA38** - Net salary total
- **N41:O41, N43:O43, N44:O44** - Bottom labels

---

## CRITICAL DIFFERENCES FROM CURRENT TEMPLATE

### ❌ MISSING in current template:
1. **9 columns** (U, V, W, X, Y, Z, AA + adjusted T)
2. **Formulas** (uses static values instead)
3. **Black backgrounds** on formula cells
4. **Helper columns** for calculations
5. **Bottom calculation area** (rows 38-44)
6. **Formula-based rates** in N3-Q3
7. **Correct row structure** (off by 1-2 rows)

### ✅ MATCHES in current template:
1. Basic column structure (A-S)
2. Employee name label
3. Basic salary label
4. Time entry columns
5. Header rows concept

---

## FORMULA BREAKDOWN EXAMPLE (Row 7)

If you enter:
- B7: 8:10 AM (5 min late)
- C7: 12:00 PM
- D7: 1:00 PM  
- E7: 5:00 PM

The formulas auto-calculate:
- K7: 5 minutes late
- U7: 5 (cleaned)
- P7: ₱5.21 late deduction (5 min × ₱1.0417/min)
- J7: 8 hours work
- Others: ₱0.00

---

## IMPORT CONSIDERATIONS

When importing DTR-Sample-TB5.xlsm:

### ✅ Import SHOULD read:
- Columns A-F (date and time entries)
- Columns H-I (halfday)
- Employee name from A3
- Salary from N3
- Date range from A2

### ⚠️ Import SHOULD IGNORE:
- Columns U, V, W, Z, AA (helpers - recalculate in PHP)
- Column G formula (recalculate OT in PHP)
- Columns J-T formulas (recalculate in PHP)

### ✅ Import CAN extract (for validation):
- Column N (absent count)
- Columns O-S (deduction amounts - to verify PHP calculations)

---

## FORMULA DEPENDENCIES CHART

```
User Input: B,C,D,E,F,H,I → 
  ↓
Formula Layer 1: G,J,K,L,M,N,Z →
  ↓
Formula Layer 2: U,V,W,AA →
  ↓
Formula Layer 3: O,P,Q,R,S →
  ↓
Formula Layer 4: T,Y (totals)
```

---

## RATE CALCULATION VERIFICATION

For ₱13,000 monthly salary:
- **Per Day:** 13000 ÷ 26 = **₱500.00** ✓
- **Per Hour:** 500 ÷ 8 = **₱62.50** ✓
- **Per Minute:** 13000 ÷ 26 ÷ 480 = **₱1.0417** ✓

(480 minutes = 8 hours × 60)

---

## LATE CALCULATION LOGIC

**Column K** (raw minutes):
```excel
=(MOD(B7-$K$3,1)*1440)
```
- If B7 = 8:10 AM, K3 = 8:05 AM → 5 minutes late
- If B7 is empty → 955 (invalid, gets filtered)

**Column U** (cleaned):
```excel
=IF(AND(K7>499,K7>=271),0,IF(K7<=270,K7,0))
```
- If K7 ≤ 270 minutes (4.5 hrs) → use K7 value
- If K7 > 270 AND ≥ 271 → set to 0
- If K7 > 499 → set to 0  

This filters out invalid late calculations (like 955 from empty cells)

---

## OT PAYMENT CALCULATION

**Formula:** `=($P$3*W7)*125%`

Where:
- $P$3 = Per-hour rate (₱62.50)
- W7 = OT hours (cleaned from M7)
- 125% = OT multiplier

Example: 2 hours OT = 62.50 × 2 × 1.25 = **₱156.25**

⚠️ **NOTE:** Formula uses per-HOUR rate, not per-minute!

---

## ABSENT DETECTION

**Formula:** `=COUNTIF(B7:H7,"ABSENT")`

Checks cells B7-H7 for the text "ABSENT"
- User can type "ABSENT" or "X" in column F
- Or leave time entries empty and mark F column

---

## BOTTOM AREA CALCULATIONS (Rows 38-44)

**Row 38 Totals:**
- T38: `=SUM(T7:T37)` - Sum of daily adjustments
- X38: `=SUM(X7:X37)` - Sum of benefits
- Y38: `=P42+S39-O39-P39-Q39-X38` - **FINAL NET SALARY**

**Row 39:**
- O39-S39: SUM formulas for each deduction column

**Row 42:**
- P42: `=P41*O3` - Days worked × per-day rate

**Row 44:**
- P44: `=P43*1000` - Training count × 1000

---

## TESTING CHECKLIST

To verify template matches TB5:

- [ ] 28 columns (A-AB)
- [ ] Row 3 has employee name (merged A3:I3)
- [ ] N3 has editable salary input
- [ ] O3-Q3 have rate formulas
- [ ] Row 5-6 are headers
- [ ] Row 7+ data rows have BLACK backgrounds
- [ ] Column G has OT formula
- [ ] Column J-M have work/late/undertime/OT formulas
- [ ] Column N has COUNTIF formula
- [ ] Column O-S have deduction formulas
- [ ] Column U-W have helper formulas
- [ ] Column Z-AA have flag formulas
- [ ] Row 38-44 have bottom calculations
- [ ] Merged cells match
- [ ] Colors match (especially BLACK for formulas)

---

**FOR FULL DETAILS:** See `DTR_STRUCTURE_COMPARISON_REPORT.md`
