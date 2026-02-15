# CASHFLOW MANAGEMENT PROCESS FLOWCHART

## System Architecture Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                        EXTERNAL WORLD                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐              │
│  │ External     │  │ External     │  │ External     │              │
│  │ Bank Acct 1  │  │ Bank Acct 2  │  │ Bank Acct N  │              │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘              │
└─────────┼──────────────────┼──────────────────┼──────────────────────┘
          │                  │                  │
          │   Daily Import   │   Daily Import   │   Daily Import
          │   (Duplicate     │   (Duplicate     │   (Duplicate
          │    Detection)    │    Detection)    │    Detection)
          │                  │                  │
          └──────────────────┴──────────────────┘
                            │
                            ▼
          ┌─────────────────────────────────────┐
          │   MASTER BANK ACCOUNT (Shadow)      │
          │   - Aggregates all external banks   │
          │   - Single source of truth          │
          └──────────────┬──────────────────────┘
                         │
                         │   Regular Distribution
                         │   (Scheduled/Manual)
                         │
          ┌──────────────┴──────────────────────┐
          │                                     │
          ▼                                     ▼
┌─────────────────────┐            ┌─────────────────────┐
│ USER BANK ACCOUNT 1 │            │ USER BANK ACCOUNT N │
│  - Receives funds   │    ...     │  - Receives funds   │
│  - Makes payments   │            │  - Makes payments   │
└─────────┬───────────┘            └─────────┬───────────┘
          │                                  │
          │ Contributions/Repayments         │ Contributions/Repayments
          │                                  │
          ▼                                  ▼
┌─────────────────────┐            ┌─────────────────────┐
│ USER FUND ACCOUNT 1 │            │ USER FUND ACCOUNT N │
│  - Tracks lifetime  │    ...     │  - Tracks lifetime  │
│    contributions    │            │    contributions    │
│  - Tracks repayment │            │  - Tracks repayment │
└─────────┬───────────┘            └─────────┬───────────┘
          │                                  │
          └──────────────┬───────────────────┘
                         │
                         │ Aggregated
                         │
                         ▼
          ┌─────────────────────────────────────┐
          │   MASTER FUND ACCOUNT               │
          │   - Sum of all User Fund Accounts   │
          │   - Minus Outstanding Loans         │
          └──────────────┬──────────────────────┘
                         │
                         │ Loan Disbursements
                         │ ◄────────────────
                         │ Loan Repayments
                         │
          ┌──────────────┴──────────────────────┐
          │                                     │
          ▼                                     ▼
┌─────────────────────┐            ┌─────────────────────┐
│ USER LOAN ACCOUNT 1 │            │ USER LOAN ACCOUNT N │
│  - Tracks principal │    ...     │  - Tracks principal │
│  - Tracks interest  │            │  - Tracks interest  │
│  - Payment schedule │            │  - Payment schedule │
└─────────────────────┘            └─────────────────────┘
```

## Transaction Flow Processes

### Process 1: External Bank Import to Master Bank Account

```
START: New transactions in external bank
    │
    ├─► Step 1: Export transactions from external bank
    │           ├─ Download CSV/Excel from bank portal
    │           └─ Ensure includes: Date, Amount, Description, Reference ID
    │
    ├─► Step 2: Load into "External Bank Import" sheet
    │           ├─ Paste transactions
    │           └─ Mark Import Date
    │
    ├─► Step 3: Duplicate Detection
    │           ├─ Check External Ref ID against existing
    │           ├─ IF duplicate found:
    │           │   ├─ Flag in "Duplicate Flag" column
    │           │   ├─ Mark as "DO NOT IMPORT"
    │           │   └─ Document in Notes
    │           └─ IF unique: Proceed
    │
    ├─► Step 4: Import to Master Bank Account
    │           ├─ Create entry in Transaction Log
    │           ├─ Update Master Bank Account balance (Accounts sheet)
    │           ├─ Mark "Imported to Master" = YES
    │           └─ Cross-reference Transaction ID
    │
    └─► Step 5: Verification
                ├─ Run Daily Reconciliation Check #1
                ├─ Verify Master Bank = Sum of External Banks
                └─ IF mismatch: Trigger Exception Handling
    
END: Master Bank Account updated
```

### Process 2: Master Bank Account to User Bank Accounts

```
START: Scheduled distribution period (e.g., weekly, monthly)
    │
    ├─► Step 1: Determine distribution amounts
    │           ├─ Calculate per user based on:
    │           │   ├─ Fixed allocation
    │           │   ├─ Percentage-based
    │           │   └─ Activity-based
    │           └─ Document distribution basis
    │
    ├─► Step 2: Verify sufficient balance
    │           ├─ Check Master Bank Account balance
    │           ├─ IF insufficient:
    │           │   ├─ Halt distribution
    │           │   └─ Trigger Exception Handling
    │           └─ IF sufficient: Proceed
    │
    ├─► Step 3: Execute distributions
    │           ├─ FOR each user:
    │           │   ├─ Create Transaction Log entry
    │           │   │   ├─ Type: "Master Bank → User Bank"
    │           │   │   ├─ From: Master Bank Account
    │           │   │   ├─ To: User Bank Account
    │           │   │   └─ Amount: [Calculated amount]
    │           │   ├─ Debit Master Bank Account
    │           │   └─ Credit User Bank Account
    │           └─ Record approval and timestamp
    │
    ├─► Step 4: Post-distribution verification
    │           ├─ Run Daily Reconciliation Check #2
    │           ├─ Verify: Sum of User Banks ≤ Original Master Bank
    │           └─ Run Daily Reconciliation Check #7
    │                └─ Verify: Master Bank = User Banks + Master Fund
    │
    └─► Step 5: Notification
                ├─ Notify users of allocation
                └─ Document distribution report
    
END: Funds distributed to User Bank Accounts
```

### Process 3: User Bank to User Fund (Contributions & Repayments)

```
START: User makes contribution or loan repayment
    │
    ├─► Step 1: Identify transaction type
    │           ├─ Contribution: User adding to savings
    │           └─ Loan Repayment: Paying back borrowed amount
    │
    ├─► Step 2: Verify User Bank Account balance
    │           ├─ Check sufficient funds in User Bank Account
    │           ├─ IF insufficient:
    │           │   ├─ Reject transaction
    │           │   └─ Notify user
    │           └─ IF sufficient: Proceed
    │
    ├─► Step 3: Process transaction
    │           ├─ Create Transaction Log entry
    │           │   ├─ Type: "Contribution" or "Loan Repayment"
    │           │   ├─ From: User Bank Account
    │           │   ├─ To: User Fund Account
    │           │   ├─ Amount: [Payment amount]
    │           │   └─ IF Loan Repayment: Link to Loan ID
    │           ├─ Debit User Bank Account
    │           ├─ Credit User Fund Account
    │           └─ Credit Master Fund Account
    │
    ├─► Step 4: Update loan records (if repayment)
    │           ├─ IF Loan Repayment:
    │           │   ├─ Update Loan Tracking sheet
    │           │   ├─ Split payment into Principal & Interest
    │           │   ├─ Update Outstanding Balance
    │           │   ├─ Update Total Paid
    │           │   └─ Calculate Next Payment Date
    │           └─ Update User Outstanding Loans total
    │
    ├─► Step 5: Verification
    │           ├─ Run Daily Reconciliation Check #3
    │           ├─ Run Daily Reconciliation Check #4
    │           └─ Run Daily Reconciliation Check #6
    │
    └─► Step 6: Confirmation
                ├─ Generate receipt for user
                └─ Update user's Available to Borrow
    
END: User Fund Account updated, Master Fund reconciled
```

### Process 4: Loan Disbursement

```
START: User requests loan
    │
    ├─► Step 1: Credit evaluation
    │           ├─ Check User Fund Account balance
    │           ├─ Review Outstanding Loans
    │           ├─ Calculate Available to Borrow
    │           │   = User Fund Account - Outstanding Loans
    │           └─ Apply credit policy rules
    │
    ├─► Step 2: Loan approval decision
    │           ├─ IF Denied:
    │           │   ├─ Notify user with reason
    │           │   └─ END
    │           └─ IF Approved: Proceed
    │
    ├─► Step 3: Create loan record
    │           ├─ Generate unique Loan ID
    │           ├─ Create entry in Loan Tracking sheet
    │           │   ├─ Loan ID
    │           │   ├─ User ID
    │           │   ├─ Origination Date
    │           │   ├─ Original Amount
    │           │   ├─ Interest Rate
    │           │   ├─ Term (months)
    │           │   └─ Calculate Monthly Payment
    │           └─ Status = "Active"
    │
    ├─► Step 4: Verify Master Fund balance
    │           ├─ Check Master Fund Account ≥ Loan Amount
    │           ├─ IF insufficient:
    │           │   ├─ Halt disbursement
    │           │   ├─ Mark loan "Pending Funds"
    │           │   └─ Trigger Exception Handling
    │           └─ IF sufficient: Proceed
    │
    ├─► Step 5: Disburse funds
    │           ├─ Create Transaction Log entry
    │           │   ├─ Type: "Loan Disbursement"
    │           │   ├─ From: Master Fund Account
    │           │   ├─ To: User Bank Account
    │           │   ├─ Amount: [Loan amount]
    │           │   └─ Reference: Loan ID
    │           ├─ Debit Master Fund Account
    │           ├─ Credit User Bank Account
    │           └─ Update User Outstanding Loans
    │
    ├─► Step 6: Post-disbursement verification
    │           ├─ Run Daily Reconciliation Check #5
    │           ├─ Run Daily Reconciliation Check #6
    │           └─ Verify loan appears in Outstanding Loans
    │
    └─► Step 7: Confirmation & Setup
                ├─ Generate loan agreement
                ├─ Set Next Payment Date
                ├─ Notify user of terms
                └─ Setup payment reminders
    
END: Loan disbursed and tracked
```

## Daily Reconciliation Workflow

```
START: Daily reconciliation (EOD or specified time)
    │
    ├─► Step 1: Data Collection
    │           ├─ Ensure all transactions for day are recorded
    │           ├─ Verify Transaction Log is complete
    │           └─ Update all account balances
    │
    ├─► Step 2: Run Automated Checks
    │           ├─ Open "Daily Reconciliation" sheet
    │           ├─ Enter today's date
    │           ├─ Input Actual Results for each check
    │           └─ Review Status column (PASS/FAIL)
    │
    ├─► Step 3: Check Results Analysis
    │           ├─ FOR each check (1-7):
    │           │   ├─ IF Status = "PASS":
    │           │   │   └─ Mark as verified ✓
    │           │   └─ IF Status = "FAIL":
    │           │       ├─ Note variance amount
    │           │       ├─ Identify affected accounts
    │           │       └─ Proceed to Exception Handling
    │           │
    │           ├─ Check #1: Master Bank = External Banks
    │           ├─ Check #2: User Banks Total validation
    │           ├─ Check #3: Master Fund validation
    │           ├─ Check #4: User Fund Accounts Total
    │           ├─ Check #5: Outstanding Loans Total
    │           ├─ Check #6: Fund Balance equation
    │           └─ Check #7: Cash Flow Balance
    │
    ├─► Step 4: Balance Equation Verification
    │           ├─ Review "Balance Equations" sheet
    │           ├─ Verify all 5 critical equations hold true
    │           └─ Document any discrepancies
    │
    ├─► Step 5: Exception Resolution (if needed)
    │           ├─ IF any failures:
    │           │   ├─ Create exception ticket
    │           │   ├─ Follow Exception Handling workflow
    │           │   ├─ Resolve within SLA
    │           │   └─ Re-run reconciliation
    │           └─ IF all pass: Proceed
    │
    ├─► Step 6: Documentation
    │           ├─ Save Daily Reconciliation results
    │           ├─ Generate reconciliation report
    │           ├─ Archive for audit trail
    │           └─ Note any exceptions resolved
    │
    └─► Step 7: Sign-off
                ├─ Accounting Manager review
                ├─ Approve reconciliation
                └─ Close daily process
    
END: Daily reconciliation complete
```

## Exception Handling Workflow

```
START: Exception detected (any source)
    │
    ├─► Step 1: Exception Identification
    │           ├─ Determine Exception Type (see Exception Handling sheet)
    │           ├─ Record in exception log
    │           ├─ Assign Severity (Critical/High/Medium)
    │           └─ Notify Responsible Party
    │
    ├─► Step 2: Initial Assessment
    │           ├─ Review Detection Method details
    │           ├─ Gather relevant data:
    │           │   ├─ Transaction Log entries
    │           │   ├─ Account balances before/after
    │           │   ├─ Related transactions
    │           │   └─ Timeline of events
    │           └─ Estimate impact scope
    │
    ├─► Step 3: Immediate Actions (if Critical/High)
    │           ├─ IF severity = Critical:
    │           │   ├─ Freeze affected accounts
    │           │   ├─ Halt related transactions
    │           │   └─ Escalate to senior management
    │           ├─ IF severity = High:
    │           │   ├─ Flag affected accounts
    │           │   └─ Review pending transactions
    │           └─ Document actions taken
    │
    ├─► Step 4: Root Cause Analysis
    │           ├─ Trace transaction history
    │           ├─ Identify point of error:
    │           │   ├─ Data entry error?
    │           │   ├─ System calculation error?
    │           │   ├─ Process not followed?
    │           │   ├─ Timing issue?
    │           │   └─ External factor?
    │           └─ Document findings
    │
    ├─► Step 5: Resolution Execution
    │           ├─ Follow Resolution Steps from Exception Handling sheet
    │           ├─ Execute corrective transactions:
    │           │   ├─ Create adjustment entries
    │           │   ├─ Log in Transaction Log (Type: "Adjustment")
    │           │   ├─ Update affected accounts
    │           │   └─ Link to exception ticket
    │           ├─ Obtain required approvals
    │           └─ Verify correction applied correctly
    │
    ├─► Step 6: Verification
    │           ├─ Re-run failed reconciliation checks
    │           ├─ Verify all balances correct
    │           ├─ Confirm exception resolved
    │           └─ IF still failing: Return to Step 4
    │
    ├─► Step 7: Documentation & Prevention
    │           ├─ Complete exception report:
    │           │   ├─ Exception type and cause
    │           │   ├─ Actions taken
    │           │   ├─ Resolution time
    │           │   └─ Lessons learned
    │           ├─ Update procedures if needed
    │           ├─ Communicate to team
    │           └─ Add to training materials if systemic
    │
    └─► Step 8: Close Exception
                ├─ Update exception status to "Resolved"
                ├─ Archive documentation
                └─ Report to management (if required)
    
END: Exception resolved and documented
```

## Monthly/Periodic Reconciliation

```
START: Month-end close
    │
    ├─► Step 1: Complete all daily reconciliations
    │           └─ Verify no outstanding exceptions
    │
    ├─► Step 2: Full audit of all accounts
    │           ├─ Master Bank Account
    │           ├─ Master Fund Account
    │           ├─ All User Bank Accounts
    │           ├─ All User Fund Accounts
    │           └─ All User Loan Accounts
    │
    ├─► Step 3: External bank statement reconciliation
    │           ├─ Obtain official bank statements
    │           ├─ Compare to Master Bank Account
    │           ├─ Identify any unrecorded transactions
    │           └─ Resolve discrepancies
    │
    ├─► Step 4: Loan portfolio review
    │           ├─ Verify all loans in Loan Tracking
    │           ├─ Confirm Outstanding Balance calculations
    │           ├─ Check for delinquent payments
    │           └─ Update loan statuses
    │
    ├─► Step 5: Generate monthly reports
    │           ├─ Account balance summaries
    │           ├─ Transaction volume by type
    │           ├─ Exception summary
    │           ├─ Loan portfolio report
    │           └─ Cash flow statement
    │
    └─► Step 6: Management review & approval
                ├─ Present findings to management
                ├─ Address questions/concerns
                ├─ Obtain sign-off
                └─ Archive month-end package
    
END: Month-end close complete
```

## Key Decision Points

### Should a transaction be allowed?

```
Decision Tree:
    │
    ├─ Is source account balance sufficient?
    │   ├─ NO → Reject transaction
    │   └─ YES → Continue
    │
    ├─ Is destination account active/valid?
    │   ├─ NO → Reject transaction
    │   └─ YES → Continue
    │
    ├─ Does transaction violate any rules?
    │   ├─ YES → Reject transaction
    │   └─ NO → Continue
    │
    ├─ Is required approval obtained?
    │   ├─ NO → Hold for approval
    │   └─ YES → Process transaction
    │
    └─ SUCCESS
```

### How to handle a reconciliation failure?

```
Decision Tree:
    │
    ├─ Is variance < $0.01 (rounding)?
    │   ├─ YES → Document as immaterial, approve
    │   └─ NO → Continue investigation
    │
    ├─ Is cause immediately identifiable?
    │   ├─ YES → Correct and re-reconcile
    │   └─ NO → Continue
    │
    ├─ Does it affect multiple accounts?
    │   ├─ YES → Escalate to manager, full audit
    │   └─ NO → Continue investigation
    │
    ├─ Is variance > materiality threshold?
    │   ├─ YES → Halt operations, full investigation
    │   └─ NO → Standard exception process
    │
    └─ Follow Exception Handling Workflow
```

## System Integration Points

```
┌─────────────────────────────────────────────────────────┐
│  EXTERNAL SYSTEMS                                       │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐    │
│  │   Banking   │  │   Payment   │  │   User      │    │
│  │   APIs      │  │   Gateway   │  │   Portal    │    │
│  └──────┬──────┘  └──────┬──────┘  └──────┬──────┘    │
└─────────┼─────────────────┼─────────────────┼──────────┘
          │                 │                 │
          │  Auto Import    │  Transaction    │  User Actions
          │                 │  Notifications  │
          ▼                 ▼                 ▼
┌──────────────────────────────────────────────────────────┐
│  INTERNAL SYSTEM (Your Excel/Database Tool)              │
│                                                           │
│  ┌────────────────────────────────────────────────┐     │
│  │  Transaction Processing Engine                  │     │
│  │  ├─ Duplicate Detection                        │     │
│  │  ├─ Balance Validation                         │     │
│  │  ├─ Transaction Logging                        │     │
│  │  └─ Account Updates                            │     │
│  └────────────────────────────────────────────────┘     │
│                                                           │
│  ┌────────────────────────────────────────────────┐     │
│  │  Reconciliation Engine                         │     │
│  │  ├─ Daily Automated Checks                     │     │
│  │  ├─ Balance Equation Validation                │     │
│  │  ├─ Exception Detection                        │     │
│  │  └─ Reporting                                  │     │
│  └────────────────────────────────────────────────┘     │
│                                                           │
│  ┌────────────────────────────────────────────────┐     │
│  │  Data Storage                                  │     │
│  │  ├─ Account Balances                          │     │
│  │  ├─ Transaction Log                           │     │
│  │  ├─ Loan Records                              │     │
│  │  └─ Audit Trail                               │     │
│  └────────────────────────────────────────────────┘     │
└───────────────────────────────────────────────────────────┘
          │                 │                 │
          │  Reports        │  Alerts         │  Audit Logs
          │                 │                 │
          ▼                 ▼                 ▼
┌──────────────────────────────────────────────────────────┐
│  STAKEHOLDERS                                            │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐     │
│  │  Accounting │  │  Management │  │   Users     │     │
│  │    Team     │  │             │  │             │     │
│  └─────────────┘  └─────────────┘  └─────────────┘     │
└──────────────────────────────────────────────────────────┘
```
