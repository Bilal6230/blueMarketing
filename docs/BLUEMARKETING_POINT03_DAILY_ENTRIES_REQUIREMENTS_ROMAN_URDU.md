# POINT 03 – DAILY ENTRIES MODULE
## Developer ke liye Final Software Upgrade Requirements

**Project:** Blue Marketing Software

**Maqsad:** Maujooda Daily Entries Module ko ek complete, professional aur integrated accounting voucher system mein upgrade karna hai. Tamam vouchers, cheque transactions, online transactions, plot purchases, plot receipts aur un se mutaliq accounting records aapas mein sahi tarah linked, automated aur traceable hon.

**Zaroori baat:** Software ka tamam maujooda data, accounts, records, modules aur functionality mehfooz rehni chahiye. Koi existing record ya functionality lost, damaged ya baghair zaroorat change nahi honi chahiye.

---

# A. GENERAL VOUCHER DESIGN & LAYOUT

Tamam vouchers ka ek standard, professional accounting layout hona chahiye, jo maujooda Debit/Credit Voucher design par based ho.

### Common Voucher Fields

Jahan applicable ho, vouchers mein ye fields hon:

1. Auto Voucher Number
2. Voucher Date
3. Voucher Type
4. Account / Customer / Vendor
5. Description / Narration
6. Debit Amount
7. Credit Amount
8. Reference Number
9. Attachment / Supporting Documents
10. Prepared By
11. Approved By
12. Voucher Status

### General Functionality

- Naya Voucher add karne ki sahulat.
- Permissions aur approval rules ke mutabiq Voucher edit karna.
- Voucher view karna.
- Voucher print karna.
- Voucher search karna.
- Date-wise filter.
- Voucher Type filter.
- Account-wise filter.
- Automatic Voucher Numbering.
- Debit/Credit Balance Validation.
- Voucher Approval Status.
- Complete Transaction History.

Tamam vouchers ka View aur Print layout ek jaisa, consistent aur professional hona chahiye.

Maujooda Debit/Credit Voucher ki functionality poori tarah chalti rehni chahiye.

---

# B. VOUCHER APPROVAL SETTINGS

Tamam voucher types ke liye ek centralized approval system implement kiya jaye.

Ye configuration **Definitions & Settings / Administration** ke andar **Voucher Settings** naam ke alag option mein honi chahiye.

### 1. Voucher Approval Configuration

Tamam voucher types ki list show ki jaye, jin mein ye shamil hon:

- Cash Receipt Voucher
- Cash Payment Voucher
- Bank Receipt Voucher
- Bank Payment Voucher
- Bank Deposit Voucher
- Bank Withdrawal Voucher
- Debit/Credit Voucher
- Plot Receive Payment Voucher
- Purchase Voucher (Plot)
- Sale Voucher (Plot)

Har voucher type ke liye ye options hon:

- Approval Required: ON / OFF
- Approver User Selection
- Authorized Approver
- Save Settings

### 2. Approval Workflow

- Har voucher type ke liye alag approver select kiya ja sake.
- Admin, Super Admin, Director ya koi aur authorized user approver ban sakta ho.
- Voucher submit hote hi approval request automatically selected approver ko chali jaye.
- Pending requests Approval Dashboard mein nazar aayen.
- Approver ke paas View, Approve, Reject aur Remarks ke options hon.
- Reject karte waqt remarks dena lazmi ho.
- Approval milne tak voucher finally post na ho.
- Agar Approval OFF ho, to user ki existing permissions ke mutabiq voucher post ho sake.

### 3. Approval History

Har voucher ke liye ye information mehfooz rakhi jaye:

- Voucher kis ne create kiya (Created By).
- Kis ne submit kiya (Submitted By).
- Kis ne approve ya reject kiya (Approved / Rejected By).
- Date aur Time.
- Remarks.
- Current Status.
- Complete Approval History.

Sirf selected authorized approver hi relevant voucher approve kar sake. Approval ke baad koi bhi change permission system ke mutabiq ho aur uski complete audit trail mehfooz rahe.

---

# C. REQUIRED VOUCHERS

## 1. Cash Receipt Voucher

Cash payment receive karne ke liye use hoga.

**Fields:**
- Cash Account Selection
- Customer / Account Selection
- Amount
- Date
- Description / Narration
- Reference Number
- Attachment (Optional)

**Accounting Entry:**

- Cash Account – Debit
- Customer / Other Account – Credit

View aur Print ke options available hon.

## 2. Cash Payment Voucher

Cash payment dene ke liye use hoga.

**Fields:**
- Cash Account Selection
- Receiving Account / Vendor / Customer
- Amount
- Date
- Description / Narration
- Reference Number
- Attachment (Optional)

**Accounting Entry:**

- Expense / Payable / Other Account – Debit
- Cash Account – Credit

View aur Print ke options available hon.

## 3. Bank Receipt Voucher

Bank mein receive hone wali payment record karne ke liye use hoga.

Ye voucher **Cheque & Online Transaction Management** se existing transaction select kar ke generate hona chahiye.

### Transaction Selection

Sirf ye transactions select ho sakti hain:

- Passed / Cleared Cheques
- Confirmed Online Receipts

Pending ya unconfirmed transactions ko Bank Receipt Voucher generate karne ke liye select nahi kiya ja sakna chahiye.

### Bank Side

- Relevant Bank Account automatically select ho.
- Amount selected transaction se automatically fetch ho.
- Bank amount fixed rahe.
- User bank amount ko edit, split ya dobara manually enter na kar sake.

### Opposite Side

Multiple credit allocation rows ki sahulat ho, misal ke taur par:

- Customer Accounts
- Plot Accounts
- Other Relevant Accounts

Tamam credit allocations ka total fixed bank transaction amount ke bilkul barabar hona chahiye.

**Accounting Entry:**

- Bank Account – Debit
- Customer / Plot / Other Accounts – Credit

### Important Rules

- Ek hi transaction ke against duplicate Bank Receipt Vouchers generate na hon.
- Original transaction reference hamesha linked rahe.
- Transaction ki tamam details voucher ki Description mein automatically nazar aayen.

## 4. Bank Payment Voucher

Bank ke zariye di gayi payments record karne ke liye use hoga.

Sirf confirmed bank/online payment transactions select ki ja saken.

### Bank Side

- Bank Account automatically select ho.
- Amount original transaction se automatically fetch ho.
- Amount fixed rahe; edit ya split na ho sake.

### Opposite Side

Multiple debit allocation rows ki sahulat ho, jaise:

- Vendor Accounts
- Customer Accounts
- Expense Accounts
- Other Relevant Accounts

Tamam debit allocations ka total fixed bank transaction amount ke bilkul barabar ho.

**Accounting Entry:**

- Expense / Payable / Other Accounts – Debit
- Bank Account – Credit

### Important Rules

- Ek transaction ke against duplicate Bank Payment Vouchers generate na hon.
- Original transaction reference mehfooz rahe.
- Transaction details voucher ki Description mein automatically aa jayein.

## 5. Bank Deposit Voucher

Cash ko bank account mein deposit karne ke liye use hoga.

**Fields:**
- Cash Account
- Bank Account
- Date
- Amount
- Reference Number
- Description

**Accounting Entry:**

- Bank Account – Debit
- Cash Account – Credit

Debit aur Credit amounts barabar honi chahiye.

## 6. Bank Withdrawal Voucher

Bank account se cash withdraw karne ke liye use hoga.

Agar withdrawal cheque se ki jaye, to relevant withdrawn cheque record **Cheque & Online Transaction Management** se select kiya jaye.

- Cheque Reference select karein.
- Amount original cheque record se automatically fetch ho.
- Amount fixed rahe.
- Alag se manual amount entry ki zaroorat na ho.
- Relevant Bank aur Cash Accounts select karein.

**Accounting Entry:**

- Cash Account – Debit
- Bank Account – Credit

Dono amounts barabar honi chahiye.

Original cheque reference voucher ke saath linked rahe.

## 7. Debit / Credit Voucher

Maujooda Debit/Credit Voucher ki tamam functionality preserve ki jaye.

Is mein ye sahulaten hon:

- Multiple Debit Rows
- Multiple Credit Rows
- Account Selection
- Amount
- Description / Narration
- Reference
- Attachment
- Total Debit
- Total Credit

Total Debit aur Total Credit barabar hon.

Existing voucher ke design ya workflow ko baghair zaroorat change na kiya jaye.

## 8. Plot Receive Payment Voucher

Ek hi voucher mein teen payment methods support hon:

- Cash
- Cheque
- Online Transfer

### A. Cash Payment

Cash select karne par existing Cash Receipt jaisa process use kiya jaye.

**Fields:**
- Cash Account
- Customer
- Plot Selection
- Amount
- Date
- Description

**Accounting Entry:**

- Cash Account – Debit
- Customer / Plot Account – Credit

### B. Cheque / Online Payment

Cheque ya Online select karne par:

- Existing transaction **Cheque & Online Transaction Management** se select ki jaye.
- Is voucher se nayi cheque ya online transaction manually create na ki jaye.
- Sirf Passed / Cleared Cheques ya Confirmed Online Receipts select ho saken.
- Bank amount automatically fetch ho aur fixed rahe.
- Bank side par sirf ek amount entry generate ho.

### Multiple Plot Allocation

Ek cheque ya online transaction ki payment multiple plots ya customers mein allocate ki ja sake.

**Example:**

| Plot | Amount |
|---|---:|
| Plot A | Rs. 400,000 |
| Plot B | Rs. 350,000 |
| Plot C | Rs. 250,000 |
| **Total** | **Rs. 1,000,000** |

Is example mein:

- Bank side par sirf ek entry Rs. 1,000,000 ki bane.
- Opposite side par Plot / Customer ki teen alag allocations hon.
- Total allocations original bank amount ke barabar hon.
- Har plot ke liye alag printable payment receipt available ho.
- Tamam receipts usi original cheque/online transaction ke saath linked rahen.
- Duplicate bank entries generate na hon.

---

# D. PLOT PURCHASE MODULE & PURCHASE VOUCHER

Existing Plot Sale Module ko reference banate hue naya **Plot Purchase Module** banaya jaye.

### 1. Plot Purchase Module

Iska layout, design aur workflow existing Plot Sale Module jaisa ho, lekin purchases ke liye banaya jaye.

**Required Fields:**

- Seller / Customer Name
- Seller Account Selection
- Plot Details
- Plot Location / Identification
- Plot Size
- Unit
- Rate
- Total Purchase Amount
- Purchase Date
- Description / Remarks
- Relevant Supporting Documents

Users apni permissions ke mutabiq existing Seller Account select kar saken ya naya account bana saken.

### 2. Automatic Purchase Voucher Generation

Plot Purchase Record save, complete ya post hone par system automatically **Purchase Voucher (Plot)** generate kare.

- Dobara manually duplicate voucher banane ki zaroorat na ho.
- Purchase Record aur Voucher aapas mein linked hon.
- Voucher Number Purchase Record mein nazar aaye.
- Purchase Record se Voucher view aur print ho sake.
- Purchase aur accounting ki tamam details shamil hon.
- Duplicate voucher generation prevent ki jaye.

### 3. Accounting Entry

- Plot Purchase / Relevant Purchase Account – Debit
- Seller / Customer Account – Credit

### 4. Plot Inventory Integration

Purchase complete hone par purchased plot automatically Plot Inventory mein add ho.

- Purchase Record ko Plot Inventory ke saath link karein.
- Purchase Cost mehfooz rahe.
- Seller Details mehfooz rahen.
- Plot Identification aur Purchase History mehfooz rahen.
- Plot ka status sahi tarah update ho.
- Duplicate plot entries prevent hon.

### 5. Edit / Cancel / Reversal

Agar Purchase Record edit ya cancel ho:

- Linked voucher aur inventory record bhi sahi tarah update hon.
- Reversal / Cancellation sirf authorized user kar sake.
- Complete audit history maintain ho.
- Pehle se recorded financial history lost na ho.

### Existing Plot Sale Module

Maujooda Plot Sale Module ki tamam functionality preserve ho.

Existing automatic **Sale Voucher (Plot)** generation chalti rahe. Sale aur Purchase dono modules Inventory aur Accounting ke saath properly integrated hon.

---

# E. CHEQUE & ONLINE TRANSACTION MANAGEMENT

**Ye Daily Entries ke andar ek section/submenu hona chahiye, alag Main Menu module nahi.**

Is section mein cheque receiving, bank clearing, direct withdrawal, cheque passing, cheque returns aur online transactions manage hon.

## 1. Add Cheque / Cheque Receiving

Naya cheque add karte waqt ye fields hon:

1. Cheque Number
2. Cheque Date
3. Receiving Date
4. Amount
5. Bank Name
6. Branch
7. Received From / Customer
8. Related Customer / Account
9. Purpose / Description
10. Remarks
11. Cheque Image / Attachment
12. Current Status

### Purpose / Description Field – Mandatory

Add Cheque form mein **Purpose / Description** ki field lazmi honi chahiye.

User bataye ke cheque kis wajah se diya ya receive kiya gaya.

**Examples:**

- Plot Payment
- Contractor Payment
- Material Purchase
- Customer Payment
- Security Deposit
- Advance Payment
- Other Purpose

Ye Description:

- Cheque Record mein save ho.
- Cheque Details mein nazar aaye.
- Cheque Receiving Slip par shamil ho.
- Related Bank Receipt / Bank Payment Voucher ki Description mein automatically transfer ho.
- Linked Voucher History mein bhi nazar aaye.

## 2. Cheque Receiving Slip – View / Print

Naya cheque add aur save hote hi system **Cheque Receiving Slip** generate kare.

Slip foran View aur Print ke liye available ho.

Ye slip sirf cheque receive hone ka proof hai, Final Accounting Voucher nahi.

**Slip ki Required Details:**

- Company Name / Logo / Contact
- Auto Slip Number
- Receiving Date
- Customer / Person Name
- Cheque Number
- Cheque Date
- Cheque Amount
- Bank Name / Branch
- Related Customer Account
- Purpose / Description
- Current Cheque Status
- Received By / Authorized Person
- Signature Space
- Remarks

### Receiving Slip Functionality

- Cheque save hote hi slip available ho.
- Cheque Record se dobara View / Print ki ja sake.
- Cheque ka latest actual status show ho.
- Slip original Cheque Record ke saath linked rahe.
- Slip par clearly **Cheque Receiving Slip** likha ho.
- Isay bank clearing ya funds successfully receive hone ke proof ke taur par show na kiya jaye.

## 3. Cheque Receive Hone ke Baad Do Options

Cheque save hone ke baad ye do options hon:

**Option A: Send for Bank Clearing**

**Option B: Direct Bank Withdrawal**

Dono options mein original cheque amount automatically fetch ho aur fixed rahe.

## 4. Direct Bank Withdrawal

Agar cheque directly bank se withdraw kiya jaye, to ye details hon:

- Withdrawal Date
- Person Who Withdrew / Collected
- CNIC (agar required ho)
- Bank Details
- Original Cheque Details
- Amount (Auto / Fixed)
- Remarks

Amount original cheque se automatically fetch ho; manually enter ya edit na ho sake.

Successful withdrawal ke baad status **Withdrawn** ho jaye.

Ye information maintain ki jaye:

- Cheque withdraw karne wale shakhs ki detail.
- Withdrawal date.
- Original cheque reference.
- Original amount.
- Complete transaction history.

Accounting confirmation ki zaroorat hone par ye record Bank Withdrawal Voucher ke saath link kiya ja sake.

## 5. Send for Bank Clearing

Agar cheque bank clearing ke liye deposit kiya jaye, to ye details hon:

- Bank Account
- Bank / Branch
- Clearing / Deposit Date
- Original Cheque Reference
- Amount (Auto / Fixed)
- Remarks

Amount original cheque se automatically fetch ho; alag editable amount field na ho.

Status **Pending Clearing** ho.

## 6. Cheque Clearing Confirmation

Cheque ke statuses ye hon:

- Received
- Pending Clearing
- Passed / Cleared
- Returned / Bounced
- Rejected / Memo
- Withdrawn

### Mandatory Verification Popup

Jab bhi user status **Pending Clearing** se **Passed / Cleared** kare, ek mandatory confirmation popup khule.

**Popup Message (Roman Urdu):**

"Kya aap confirm karte hain ke ye cheque successfully clear ho chuka hai aur payment relevant bank account mein credit ho chuki hai? Kya aap ne bank statement / account audit se credit entry verify kar li hai? Sirf us waqt confirm karein jab amount waqai receive aur verify ho chuka ho."

**Buttons:**

- OK – Confirm Cheque Cleared
- Cancel – Do Not Confirm

### Confirmation Rules

- Cancel select karne par status **Pending Clearing** hi rahe.
- OK select karne par status **Passed / Cleared** ho jaye.
- Confirm karne wale user ka naam, date aur time record ho.
- Original cheque amount fixed rahe.
- Sirf Passed / Cleared cheques Bank Receipt Voucher generation ke liye available hon.
- Duplicate voucher generation block ho.
- Complete audit trail maintain ho.

### Returned / Rejected Cheque

Agar cheque return ya reject ho, to ye details hon:

- Return / Rejection Date
- Reason
- Bank Memo / Attachment
- Remarks

Status **Returned / Bounced** ya **Rejected / Memo** set ho.

Aise cheque ke against Bank Receipt Voucher generate nahi hona chahiye.

---

# F. ONLINE TRANSACTION MANAGEMENT

Online transactions bhi **Cheque & Online Transaction Management** ke andar manage hon.

## 1. Online Transaction Fields

- Transaction ID / Reference Number
- Transaction Date
- Transaction Amount
- Sending Bank / Account
- Receiving Bank / Account
- Sender Name
- Receiver Name
- Related Customer / Account
- Transaction Type: Receipt / Payment
- Description / Purpose
- Supporting Proof / Attachment
- Confirmation Status
- Remarks

## 2. Online Transaction Confirmation

Jab online transaction ka status **Pending** se **Confirmed** kiya jaye, to mandatory confirmation popup khule.

**Popup Message (Roman Urdu):**

"Kya aap confirm karte hain ke ye online transaction successfully complete ho chuki hai aur payment relevant bank account mein credit ho chuki hai ya us account se transfer ho chuki hai? Kya aap ne bank statement / account audit se transaction verify ki hai? Sirf tab confirm karein jab transaction waqai complete aur verify ho chuki ho."

**Buttons:**

- OK – Confirm Transaction
- Cancel – Do Not Confirm

### Confirmation Rules

- Cancel karne par transaction **Pending** rahe.
- OK karne par status **Confirmed** ho jaye.
- Confirm karne wale user ka naam, date aur time record ho.
- Original transaction amount fixed rahe.
- Confirmed Online Receipts se Bank Receipt Voucher generate ho sake.
- Confirmed Online Payments se Bank Payment Voucher generate ho sake.
- Pending transactions voucher generation ke liye available na hon.
- Duplicate transaction references aur duplicate vouchers prevent hon.
- Complete audit history maintain ho.

---

# G. BANK VOUCHER DESCRIPTION & LINKED VOUCHER HISTORY

## 1. Bank Voucher ki Description mein Automatic Transaction Details

Jab Bank Receipt Voucher, Bank Payment Voucher ya koi relevant Bank Voucher linked Cheque/Online Transaction se generate ho, to relevant transaction ki tamam details voucher ki **Description / Narration** mein automatically nazar aayen.

Automatically ye details shamil hon:

- Transaction Type: Cheque / Online Transfer
- Cheque Number ya Online Transaction ID
- Cheque Date ya Transaction Date
- Transaction Amount
- Bank Name aur Branch
- Sender / Receiver ya relevant Customer
- Cheque Purpose / Description
- Original Transaction Reference

Ye details selected Cheque / Online Transaction se automatically fetch hon.

User ko wohi information dobara manually enter na karni pade. Zaroorat par additional remarks enter kiye ja saken.

## 2. Cheque & Online Transaction Module mein Linked Voucher History

Har Cheque aur Online Transaction ke saath us ke against generate hone wale tamam vouchers ka complete record maintain ho.

Jab user transaction details open kare, to **Linked Vouchers / Voucher History** naam ka alag section show ho.

Is mein ye details hon:

- Tamam generated Voucher Numbers.
- Voucher Type.
- Voucher Date.
- Voucher Amount.
- Har voucher ki short Description / Narration.
- View aur Print options.
- Clickable Voucher Number, jis se relevant voucher khul jaye.

Agar ek transaction multiple vouchers ke saath linked ho, to tamam linked vouchers alag-alag show hon.

Is section ka maqsad ye hai ke user foran dekh sake ke kisi cheque ya online transaction ke against kaun se vouchers generate hue aur har voucher kis purpose ke liye tha.

### Important Rules

- Original transaction aur related vouchers ke darmiyan permanent linkage maintain ho.
- Vouchers ko view ya link karte waqt original transaction ka amount ya details change na hon.
- Duplicate voucher links prevent hon.
- Complete voucher aur transaction history preserve ho.

## 3. Cheque Receiving Slip vs. Final Accounting Voucher

System mein **Cheque Receiving Slip** aur **Final Accounting Voucher** ka farq clearly nazar aana chahiye.

**Cheque receive hote waqt:**

- Sirf Cheque Receiving Slip generate ho.
- Sirf cheque receive hone par final Bank Receipt Voucher generate na ho.

**Cheque clearance ke baad:**

- Jab cheque Passed / Cleared aur confirm ho jaye, tab Bank Receipt Voucher generation ke liye eligible ho.

**Online Transactions ke liye:**

- Pending transaction se accounting voucher generate na ho.
- Confirmed Online Receipt se Bank Receipt Voucher generate ho sake.
- Confirmed Online Payment se Bank Payment Voucher generate ho sake.

**Final Accounting Voucher:**

Final voucher ka apna View / Print option ho aur ye details hon:

- Voucher Number
- Voucher Date
- Original Transaction Reference
- Amount
- Accounts
- Description / Narration
- Relevant transaction details

Ye original Cheque / Online Transaction ke saath linked rahe aur future mein view ya reprint kiya ja sake.

**Cheque Receiving Slip** sirf cheque milne ka proof hai. **Final Accounting Voucher** relevant transaction confirm hone ke baad banne wala accounting record hai.

---

# H. CHEQUE & ONLINE TRANSACTION DASHBOARD / SUMMARY

**Cheque & Online Transaction Management** ke andar summary dashboard diya jaye.

### Cheque Summary

Ye figures show hon:

- Total Cheques Received
- Pending Clearing
- Passed / Cleared
- Returned / Bounced
- Direct Withdrawals
- Pending Cheque Amount
- Cleared Cheque Amount
- Cheques Awaiting Voucher
- Cheques Already Adjusted / Posted

### Online Transaction Summary

Ye figures show hon:

- Total Online Receipts
- Total Online Payments
- Pending Transactions
- Confirmed Transactions
- Pending Receipt Amount
- Pending Payment Amount
- Confirmed Transactions Awaiting Voucher
- Transactions Already Adjusted / Posted

### Filters

Ye filters available hon:

- Date Range
- Bank
- Bank Account
- Customer / Account
- Transaction Status
- Transaction Type
- Cheque / Online

Tamam summary figures actual transaction records par based hon aur automatically update hoti rahen.

---

# I. GENERAL INTEGRATION & CONTROL RULES

1. Cheque & Online Transaction Management ko Daily Entries Module ke andar rakha jaye.
2. Sirf eligible aur confirmed transactions se Bank Receipt ya Bank Payment Voucher generate ho.
3. Bank-side transaction amount hamesha original transaction se fetch ho aur fixed rahe.
4. Jahan specified ho, multiple opposite-side account allocations available hon aur unka total original transaction amount ke barabar ho.
5. Bank Deposit aur Bank Withdrawal Vouchers mein debit aur credit amounts barabar hon.
6. Cheque-based withdrawals mein original cheque record ki fixed amount use ho.
7. Cheque receive hone ke baad user ko Direct Bank Withdrawal aur Send for Bank Clearing ke options milen.
8. Plot Receive Payment Voucher mein Cash, Cheque aur Online methods support hon; ek cheque/online transaction multiple plots mein allocate ho sake.
9. Cheque entry ke foran baad Cheque Receiving Slip generate ho aur isay accounting voucher na samjha jaye.
10. Final accounting vouchers sirf required transaction confirmation ke baad generate hon.
11. Cheque clearing ya online transaction confirmation ke mandatory verification popups ko bypass na kiya ja sake.
12. Voucher Settings mein har voucher type ki alag approval configuration aur approver selection ho.
13. Tamam linked transactions aur vouchers ke complete references aur audit history maintain hon.
14. Authorized edit, cancellation ya reversal linked records ko sahi tarah update kare, magar historical information delete na ho.
15. Duplicate transactions, duplicate voucher generation aur duplicate accounting entries prevent ki jayein.

---

# J. OTHER REQUIRED ENTRIES

Bila zaroorat naye voucher types ya duplicate functionality add na ki jaye.

Agar developer ko proper accounting ke liye koi aur essential voucher ya entry type zaroori lage, to implementation se pehle usay alag se approval ke liye propose kiya jaye.

---

# K. FINAL DEVELOPER INSTRUCTIONS

Neeche di gayi tamam instructions lazmi hain:

1. Tamam vouchers ke standard layout ke liye existing Debit/Credit Voucher ko reference banaya jaye.
2. Existing Debit/Credit Voucher ki tamam functionality preserve ki jaye.
3. Existing Plot Sale Module aur uski automatic Sale Voucher generation preserve ki jaye.
4. Existing Plot Sale Module ko reference bana kar naya Plot Purchase Module create kiya jaye.
5. Purchase complete hone par Purchase Voucher (Plot) automatically generate ho.
6. Purchased plots automatically Plot Inventory mein add hon.
7. Cheque & Online Transaction Management Daily Entries ke andar rakha jaye.
8. Sirf eligible confirmed transactions se Bank Receipt / Payment Voucher generate ho.
9. Bank-side amount original cheque ya online transaction se fixed fetch ho.
10. Jahan required ho, multiple opposite-side account allocations hon aur total amount bilkul match kare.
11. Bank Deposit / Withdrawal Vouchers mein debit aur credit amounts barabar hon.
12. Cheque withdrawal ke liye amount directly original cheque record se fetch ho.
13. Cheque receiving ke baad Direct Bank Withdrawal aur Send for Bank Clearing ke options diye jayein.
14. Plot Receive Payment Voucher mein ek cheque ya online transaction ko multiple plots mein allocate kiya ja sake.
15. Har plot allocation ke liye alag printable payment receipt ho, magar original bank transaction ek hi aur linked rahe.
16. Cheque entry ke foran baad Cheque Receiving Slip generate ho.
17. Sirf cheque receive hone par final accounting voucher generate na ho.
18. Final Bank Receipt / Payment Vouchers sirf required confirmation ke baad generate hon.
19. Original Cheque / Online Transaction record mein tamam linked Voucher Numbers, Types, Dates, Amounts aur Descriptions show hon.
20. Cheque aur online transaction details relevant Bank Voucher ki Description / Narration mein automatically transfer hon.
21. Add Cheque form mein mandatory Purpose / Description field ho.
22. Cheque clearing aur online transaction verification popups mandatory hon aur bypass na kiye ja saken.
23. Har voucher type ke liye alag approver configuration ho.
24. Creation, submission, approval, rejection, editing, cancellation aur reversal ki complete audit history maintain ho.
25. Software ka tamam existing data, accounting records, customers, plots, transactions aur historical information preserve ho.

**Final Implementation Requirement:** Tamam naye features existing accounting system ke saath properly integrated hon. Final delivery se pehle developer voucher generation, debit/credit balancing, transaction linking, approval workflow, cheque clearing, online confirmation, plot inventory updates, duplicate prevention aur print layouts ki testing kare.