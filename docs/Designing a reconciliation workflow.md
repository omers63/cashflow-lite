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
