# Comprehensive End-to-End Reconciliation Workflow

A complete, reliable reconciliation workflow is essential for ensuring your app's financial records are accurate across all account layers. Below is a comprehensive, step-by-step reconciliation approach tailored to your app's current architecture.

---

## 1. Goals of a Proper Reconciliation Workflow

A solid reconciliation process should guarantee:

- **No Duplicates:** Every cent imported from external banks is represented once (no duplicates).
- **Correct Posting:** Funds are posted into the correct External Bank, then Master Bank, and finally into the relevant Member Bank/Fund.
- **Ledger Consistency:**
    - Sum of all External Banks ≈ Master Bank (subject to timing).
    - Master Fund ≈ Σ(Member Fund Balances) + Σ(Outstanding Loan Balances) + any configured reserves.
- **Accurate Member Balances:**
    - Member bank/fund balances match what you'd get by recomputing from transactions.
- **Loan Consistency:**
    - Outstanding loan balances match schedules and payment history.
- **Exception Handling:**
    - Any break in the above is recorded as an Exception, with severity and SLA, and must be cleared or justified.

---

## 2. High-Level Daily Workflow (Operator View)

### A. Import and Post External Bank Transactions

1. **Import Transactions** using the Import External Bank feature, inserting records into `ExternalBankImport` per bank.
2. **De-duplicate** imports (`external_ref_id` or hash), ensuring each external transaction exists only once.
3. **Post Imports to Master Bank:**
    - Use the External Bank’s Transactions tab or `import_to_master` actions to post non-duplicate imports into a Master Bank account as `external_import → master_to_user_bank`, or other internal transaction types.
4. **Balance Validation:**
    - Each `external_bank.current_balance` (per bank) should match the running sum of imports minus any bank fees/adjustments.

### B. Distribute Into Members and Funds

1. **Map Each Imported Amount:**
    - External Import → Master Bank (done above).
    - Master Bank → Member Bank via `master_to_user_bank`.
    - Member Bank → Member Fund via contribution or loan repayment.
2. **Use Standard Member Actions:**
    - Actions such as Contribute, Allocate to Dependant, and Loan Repayment should generate corresponding matched transactions:
        - Member Bank ↔ Member Fund.
        - Member Fund ↔ Master Fund (if your model applies).

### C. Run Daily Reconciliation

1. **Use `DailyReconciliation` Page/Actions:**
    - Capture ("snap") current totals for:
        - Master Bank
        - Master Fund
        - Σ(External Banks)
        - Σ(Member Bank balances)
        - Σ(Member Fund balances)
        - Σ(Outstanding Loan balances)
2. **Run Reconciliation Checks:**
    - As described in section 3, run concrete checks and store results in the `reconciliations` table.
    - **If all checks pass:** Mark reconciliation as `all_passed = true`, `status = complete`.
    - **If any fail:** Mark as `all_passed = false`, `status = failed/under_review` and create/update corresponding Exception records.

### D. Review & Clear Exceptions

1. **Visit the Exceptions Resource:**
    - Filter by Open/Overdue/Critical exceptions.
2. **Handle Each Exception:**
    - Fix data issues (missing imports, wrong transaction type, mis-posted allocation).
    - Or, document the reason and mark as resolved if the variance is understood/accepted.
3. **Follow SLA Logic:**
    - Critical/High: same-day resolution.
    - Medium/Low: batched remediation.

### E. Finalize the Day

- Once reconciliation passes (or known issues are captured as Exceptions) **and** Member/Master balances are updated (via “Recalculate Balances”), consider the day closed from a reconciliation point-of-view.

---

## 3. Recommended Concrete Checks (ReconciliationService Scope)

Below are must-have checks for robust reconciliation. Adjust as needed for your implementation.

### 3.1 External → Master Bank

- **Check E1: External Imports vs External Statement**
    - For each bank and given date range:
        - `Σ(ExternalBankImport.amount)` should equal the net change on the real bank statement.
        - **If not:** Raise `balance_mismatch` or `missing_transaction` Exception.
- **Check E2: Aggregated External Balance vs Master Bank**
    - `Σ(ExternalBankAccount.current_balance)` (all external banks) should equal Master Bank balance (or known offset for buffer).
    - **If not:** Raise `balance_mismatch` Exception.

### 3.2 Master Bank & Master Fund vs Derived Components

- **Check M1: Master Bank vs Posted Transactions**
    - Recaculate Master Bank balance:
        - Opening balance + all `external_import`, `master_to_user_bank`, `adjustment`, and other affecting transactions.
        - Compare to stored `master_bank.balance`.
        - **If variance above tolerance:** Raise `balance_mismatch`.
- **Check M2: Master Fund vs Members & Loans**
    - Concept:
        - `Master Fund ≈ Σ(Member Fund Balances) + any reserves − Σ(Outstanding Loan Balances)`
        - If your model is “Master Fund funds loans and holds deposits”:
            - `master_fund.balance + Σ(outstanding_loans) ≈ Σ(member_fund_balances) + reserves`
        - **If off:** Raise `fund_account_negative` or `balance_mismatch` Exception.

### 3.3 Members: Recompute vs Stored Balances

- **For Each Active Member:**
    - **Check MEM1:** Recompute bank account balance, compare with `member.bank_account_balance`
        - If different: auto-correct or log `balance_mismatch` Exception.
    - **Check MEM2:** Recompute member fund account balance.
        - If different: flag as above.
    - **Check MEM3:** Negative balances (if disallowed except for specific cases), raise `negative_balance` or `fund_account_negative` Exceptions as needed.

### 3.4 Loans: Amortization vs Movements

- **For Each Active or Recently Closed Loan:**
    - **Check L1:** Outstanding balance vs payment history.
        - Rebuild from original amount, repayments, disbursements, adjustments. Compare to `loan.outstanding_balance`.
        - **If mismatched:** Raise `loan_payment_mismatch`.
    - **Check L2 (Optional):** Loan schedules vs reality.
        - Use `generateAmortizationSchedule()`, compare scheduled vs actual repayments.
        - Flag missed/partial payments as delinquencies feeding Exception records.

---

## 4. Monthly / Period-End Workflow

In addition to daily checks:

- **Full Monthly Reconciliation:**
    - Same as daily, but for the full period/month.
    - Confirm:
        - Σ(monthly external statement changes) = Net change in Master Bank + known adjustments.
        - All loans/member accounts reconcile for the month/period.
- **Archive/Snapshot Balances:**
    - Store monthly snapshots of:
        - Master Bank & Master Fund
        - Σ(Member bank balances)
        - Σ(Member fund balances)
        - Σ(Outstanding loans)
    - Enables drift detection over time.
- **Review Aging/SLA Breaches:**
    - Use Exceptions to check deadlines and breaches.
    - All Critical/High Exceptions should be resolved or documented before closing the period.

---

## 5. UX Suggestions to Support This Workflow

- **On `DailyReconciliation` Page:**
    - Show each check result, itemized with:
        - Name, short description, pass/fail badge.
        - Link to related Exception or resource (e.g., “View External Banks”, “View Master Fund”).
        - “View breakdown” link to the related Reconciliation record.
- **On Reconciliation View:**
    - In check_results key/value display:
        - Use readable keys (e.g., `E1_external_vs_imports`, `M2_master_fund_vs_members`).
        - Where possible, use readable values (“OK”, “Variance 123.45”, “Missing 2 loans”, etc.).
- **On Exceptions List:**
    - Add filters for check type/source (if available), allowing quick review of:
        - Exceptions for external/master reconciliation
        - Member balance mismatches
        - Loan payment mismatches

---

### Next Steps

If desired, the process can include:

- Review of your current `ReconciliationService` and Exception creation logic.
- Mapping which of these checks are already covered.
- Proposal (and/or implementation) of any missing checks, using clear check_results keys and Exception mappings for seamless integration into your codebase.

---

## 1. Goals of a “Proper” Reconciliation Workflow

For this app, a solid reconciliation process should guarantee:

- **Every cent imported from external banks is:**
    - Represented once (no duplicates).
    - Posted into the correct External Bank, then Master Bank, then Member Bank/Fund as appropriate.

- **System ledgers always tie together:**
    - Sum of all External Banks ≈ Master Bank (subject to timing).
    - Master Fund ≈ Σ(Member Fund Balances) + Σ(Outstanding Loan Balances) + Any Configured Reserves.

- **Member-level balances are true:**
    - Member bank/fund balances match what you'd get by recomputing from transactions.

- **Loans are internally consistent:**
    - Outstanding balances match schedules and payment history.

- **Exceptions are explainable and tracked:**
    - Any break in the above is recorded as an Exception, with severity and SLA, and must be cleared or justified.

Below is a suggested end-to-end daily reconciliation workflow that uses your existing concepts (External Bank, Master Bank, Master Fund, Member bank/fund, Transactions, Loans, Exceptions, Daily Reconciliation).

---

## 2. High-Level Daily Workflow *(Operator View)*

For each business day:

### a. Import and Post External Bank Transactions

- Use **Import External Bank** to:
    - Import transactions into `ExternalBankImport` (per external bank).
    - De-duplicate (`external_ref_id`/hash), so each external transaction exists once.
- Use the External Bank’s Transactions tab or `import_to_master` actions to:
    - Post non-duplicate imports into Master Bank (as `external_import → master_to_user_bank` or other internal transaction types).

**Result:** For each external bank,  
`external_bank.current_balance` (per bank) matches running sum of imports minus any bank fees/adjustments.

---

### b. Distribute Into Members and Funds

For each imported amount intended for a member:

- Ensure clear mapping:
    - **External import → Master Bank** (already done).
    - **Master Bank → Member Bank** via `master_to_user_bank`.
    - **Member Bank → Member Fund** via contribution or loan repayment.
- Existing member actions (Contribute, Allocate to Dependant, Loan Repayment) should generate matching transaction pairs involving:
    - Member Bank ↔ Member Fund
    - Member Fund ↔ Master Fund (if applicable)

---

### c. Run Daily Reconciliation

- Use the **DailyReconciliation** page/actions (calling `ReconciliationService`) to:
    - Snap current totals:
        - Master Bank
        - Master Fund
        - Σ(External banks)
        - Σ(Member bank balances)
        - Σ(Member fund balances)
        - Σ(Outstanding loan balances)
    - Run checks (see Section 3) and store results in `reconciliations` table.
- If all checks pass:
    - Mark reconciliation as `all_passed = true, status = complete`.
- If any checks fail:
    - Mark as `all_passed = false, status = failed` or `under_review`.
    - Create or update corresponding Exception records for each failed check.

---

### d. Review & Clear Exceptions

- Visit the **Exceptions** resource:
    - Filter by Open, Overdue, or Critical.
    - For each exception, either:
        - *Fix* the underlying data (e.g., missing import, wrong transaction type, mis-posted allocation)
        - *Or* document the reason and mark resolved once variance is understood and accepted.
- SLA logic (already present in your model) should drive urgency:
    - **Critical/High exceptions:** same-day attention
    - **Medium/Low:** batched remediations

---

### e. Finalize the Day

Once:

- Daily reconciliation passes *(or known issues are all logged as Exceptions)*, and
- Member/Master balances are updated *(via your “Recalculate Balances” actions as needed)*,

Treat the day as closed from a reconciliation standpoint.

---

## 3. Recommended Concrete Checks *(What ReconciliationService Should Do)*

You may already do some of these; the key is to cover all layers.

### 3.1. External → Master Bank

For each **External Bank Account**:

- **Check E1: External imports vs external statement**
    - For a given date range (today or since last reconciliation):
        - Σ(`ExternalBankImport.amount`) for that bank should **equal** the net change in the bank’s real-world statement for that date range.
    - _If not:_ Raise `balance_mismatch` or `missing_transaction` exception for that bank.

- **Check E2: Aggregated external balance vs Master Bank**
    - Σ(`ExternalBankAccount.current_balance`) across all external banks **should equal** Master Bank balance (or a known offset if you intentionally keep a buffer).
    - _If not:_ Raise `balance_mismatch` on Master Bank vs External Banks.

---

### 3.2. Master Bank & Master Fund vs Derived Components

- **Check M1: Master Bank vs Posted Transactions**
    - Recompute Master Bank balance from:
        - Opening balance
        - All relevant transactions: `external_import`, `master_to_user_bank`, `adjustment`, and any other master-affecting transaction types
    - Compare with `master_bank.balance`
    - _Variance beyond a small tolerance:_ Raise `balance_mismatch`

- **Check M2: Master Fund vs members & loans**
    - **Conceptually:**  
      `Master Fund ≈ Σ(Member Fund Balances) + any reserves − Σ(Outstanding Loan Balances)`
    - If the model is “Master Fund funds loans and holds deposits”, then:  
      `master_fund.balance + Σ(outstanding_loans)` should approximate `Σ(member_fund_balances)` plus any configured adjustment/reserve.
    - _If off:_ Raise `fund_account_negative` or `balance_mismatch` on Master Fund.

---

### 3.3. Members: Recompute vs Stored Balances

For each **active Member**:

- **Check MEM1: Bank account balance recalc**
    - Run `recalculateBankAccountBalanceFromTransactions()` and compare the computed value with `member.bank_account_balance`.
    - _If different:_ Either auto-correct (your “Recalculate Balances” action), or log an Exception (`balance_mismatch` for that member).

- **Check MEM2: Fund account balance recalc**
    - Same as MEM1 but for `fund_account_balance` via `recalculateFundAccountBalanceFromTransactions()`.
    - Differences indicate missing or mis-typed transactions (e.g. fund contributions recorded as bank only).

- **Check MEM3: Negative balances**
    - If your rules disallow negative fund/bank balances except in specific loan-related cases:
        - Raise `negative_balance` or `fund_account_negative` exceptions accordingly.

---

### 3.4. Loans: Amortization vs Movements

For each **active or recently closed Loan**:

- **Check L1: Outstanding balance vs payment history**
    - Rebuild the loan’s outstanding balance from:
        - Original amount
        - All `loan_repayment` transactions for that loan (principal portion)
        - Any `loan_disbursement` or adjustments
    - Compare with `loan.outstanding_balance`
    - _If mismatched:_ Raise `loan_payment_mismatch`

- **Check L2: Loan schedules vs reality** (optional but powerful)
    - Use your `generateAmortizationSchedule()`
    - Compare scheduled payment dates/amounts with actual repayments
    - Flag missed or partial payments (delinquencies) more explicitly.
    - This can feed:
        - Exception records for loans that are >X days overdue or significantly off their expected balance.

---

## 4. Monthly / Period-End Workflow

On top of the daily checks:

- **Run a full reconciliation for the period**
    - Same checks as daily, but for the full month/period.
    - Confirm:
        - Σ(monthly external statement changes) = Net change in Master Bank + known adjustments
        - All loans and member accounts reconcile for the period

- **Archive / Snapshot Key Balances**
    - Store monthly snapshots of:
        - Master Bank & Master Fund
        - Σ(Member bank balances)
        - Σ(Member fund balances)
        - Σ(Outstanding loans)
    - This lets you detect drift over longer periods.

- **Review Aging and SLA Breaches**
    - Use Exceptions:
        - Check `sla_deadline` and `sla_breached` flags.
        - All Critical/High exceptions should be cleared or explicitly documented before closing the period.

---

## 5. UX Suggestions to Support this Workflow

To make this reconciliation flow usable in your app:

- **On DailyReconciliation page:**
    - Show each check result itemized (e.g. 1–7 checks) with:
        - Name, short description
        - Pass/Fail badge
        - Link to related Exceptions or resources (e.g., “View External Banks”, “View Master Fund”)
    - Provide a “View breakdown” link to the corresponding Reconciliation record where `check_results` are stored.

- **On Reconciliation View:**
    - In the `check_results` key/value display, use readable keys (e.g., `E1_external_vs_imports`, `M2_master_fund_vs_members`, etc.)
    - Where possible, turn values into short, human‑readable text: “OK”, “Variance 123.45”, “Missing 2 loans”, etc.

- **On Exceptions List:**
    - Add filters for check type/source (if recorded), so you can quickly see:
        - All exceptions coming from external/master reconciliation
        - All member balance mismatches
        - All loan payment mismatches

---

---

## 6. Implementation Notes (Current Codebase)

The following has been implemented in `ReconciliationService` and related UI.

### 6.1 Check keys and logic

| Key | Name | Description | Exception type on fail |
|-----|------|-------------|------------------------|
| **E1** | External imports vs balance | Per external bank: stored `current_balance` = sum of imports posted to master | `balance_mismatch` |
| **E2** | External banks vs Master Bank | Σ(external bank balances) = Master Bank balance | `balance_mismatch` |
| **M1** | Master Bank vs recomputed | Stored Master Bank balance = recomputed from transactions (external_import − master_to_user_bank) | `balance_mismatch` |
| **M2** | Master Fund vs members & loans | Master Fund = Σ(member fund balances) − Σ(outstanding loan balances) | `balance_mismatch` |
| **MEM1** | Member bank vs recomputed | Each active member’s `bank_account_balance` = computed from transactions (no persist during check) | `balance_mismatch` |
| **MEM2** | Member fund vs recomputed | Each active member’s `fund_account_balance` = computed from transactions (no persist during check) | `balance_mismatch` |
| **MEM3** | Negative balance check | No active member may have negative bank or fund balance | `negative_balance` |
| **L1** | Loan outstanding vs payments | Each active loan’s `outstanding_balance` = original_amount − Σ(principal from LoanPayment) | `loan_payment_mismatch` |

- **E1** does not compare to an external statement file; it compares each external bank’s stored balance to the sum of its imports posted to master. Full statement comparison would require a separate feed.
- **L2** (schedule vs actual / delinquency) is implemented: active loans past `next_payment_date` are flagged; failed check creates `loan_delinquency` exceptions.
- Tolerance for numeric comparisons: `0.01` (configurable in `ReconciliationService::$tolerance`).

### 6.2 Exception creation

- Failed checks create an `Exception` with `type` as above, `related_reconciliation_id`, `affected_accounts['reconciliation_check'] = key` (e.g. `E1`, `M1`), and severity from variance thresholds.
- Exceptions list can be filtered by **Reconciliation** (relationship filter on `related_reconciliation_id`).

### 6.3 UX

- **Daily Reconciliation page:** Shows latest reconciliation summary, pass/fail, and a **Check results** table (key, name, status, variance). Links: “View reconciliation”, “View exceptions” (when any check failed).
- **Reconciliation view (resource):** Check results displayed as readable key ⇒ value (e.g. `E1` ⇒ “OK”, `M1` ⇒ “Variance 123.45”).
- **Monthly reconciliation:** `runMonthlyReconciliation()` runs the same checks (E1–L2) for the previous month; record stored with `type = monthly`. Triggered from Daily Reconciliation (“Run monthly”) or programmatically.
- **Balance snapshots:** `BalanceSnapshot` model and `createMonthlyBalanceSnapshot()` / `snapshots:monthly` command; scheduler runs on the 1st at 00:15 for previous month. “Create snapshot” on Daily Reconciliation; **Balance Snapshots** resource to view history.
- **SLA breaches:** `exceptions:check-sla` is scheduled **hourly** in `Kernel`; marks overdue exceptions as `sla_breached`.
- **Review & clear exceptions:** Exception resource has Open, Overdue, Severity, Status, Reconciliation filters; resolve/close with notes.

