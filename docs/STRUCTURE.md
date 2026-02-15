# Project Structure

## Directory Layout

```
cashflow-system/
├── app/
│   ├── Console/
│   │   └── Kernel.php (Schedule daily reconciliation)
│   │
│   ├── Filament/
│   │   ├── Pages/
│   │   │   ├── Dashboard.php
│   │   │   ├── DailyReconciliation.php
│   │   │   └── ImportExternalBank.php
│   │   │
│   │   ├── Resources/
│   │   │   ├── UserResource.php
│   │   │   ├── UserResource/
│   │   │   │   └── Pages/
│   │   │   │       ├── ListUsers.php
│   │   │   │       ├── CreateUser.php
│   │   │   │       ├── EditUser.php
│   │   │   │       └── ViewUser.php
│   │   │   │
│   │   │   ├── TransactionResource.php
│   │   │   ├── LoanResource.php
│   │   │   ├── ExternalBankAccountResource.php
│   │   │   ├── ReconciliationResource.php
│   │   │   └── ExceptionResource.php
│   │   │
│   │   └── Widgets/
│   │       ├── StatsOverview.php
│   │       ├── RecentTransactions.php
│   │       ├── ReconciliationStatus.php
│   │       └── OpenExceptions.php
│   │
│   ├── Http/
│   │   └── Controllers/
│   │       └── Api/
│   │           ├── ReconciliationController.php
│   │           └── UserBalanceController.php
│   │
│   ├── Models/
│   │   ├── User.php
│   │   ├── MasterAccount.php
│   │   ├── ExternalBankAccount.php
│   │   ├── ExternalBankImport.php
│   │   ├── Transaction.php
│   │   ├── Loan.php
│   │   ├── LoanPayment.php
│   │   ├── Reconciliation.php
│   │   └── Exception.php
│   │
│   ├── Policies/
│   │   ├── UserPolicy.php
│   │   ├── TransactionPolicy.php
│   │   ├── LoanPolicy.php
│   │   └── ReconciliationPolicy.php
│   │
│   └── Services/
│       ├── ReconciliationService.php
│       ├── TransactionService.php
│       └── LoanService.php
│
├── database/
│   ├── factories/
│   │   ├── UserFactory.php
│   │   ├── TransactionFactory.php
│   │   └── LoanFactory.php
│   │
│   ├── migrations/
│   │   ├── 2024_01_01_000001_create_users_table.php
│   │   ├── 2024_01_01_000002_create_external_bank_accounts_table.php
│   │   ├── 2024_01_01_000003_create_master_accounts_table.php
│   │   ├── 2024_01_01_000004_create_transactions_table.php
│   │   ├── 2024_01_01_000005_create_external_bank_imports_table.php
│   │   ├── 2024_01_01_000006_create_loans_table.php
│   │   ├── 2024_01_01_000007_create_loan_payments_table.php
│   │   ├── 2024_01_01_000008_create_reconciliations_table.php
│   │   └── 2024_01_01_000009_create_exceptions_table.php
│   │
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── UserSeeder.php
│       ├── MasterAccountSeeder.php
│       └── DemoDataSeeder.php
│
├── routes/
│   ├── web.php
│   └── api.php
│
├── tests/
│   ├── Feature/
│   │   ├── ReconciliationTest.php
│   │   ├── TransactionTest.php
│   │   ├── LoanTest.php
│   │   └── UserAccountTest.php
│   │
│   └── Unit/
│       ├── LoanCalculationTest.php
│       ├── ReconciliationServiceTest.php
│       └── TransactionProcessingTest.php
│
├── config/
│   ├── filament.php
│   └── cashflow.php (Custom config)
│
├── .env.example
├── composer.json
├── package.json
├── README.md
├── INSTALLATION.md
└── STRUCTURE.md (this file)
```

## Key Components

### 1. Models

#### User.php
- User account management
- Bank account balance tracking
- Fund account balance tracking
- Outstanding loans calculation
- Available to borrow calculation
- Business methods for transactions

#### MasterAccount.php
- Master Bank Account
- Master Fund Account
- Balance tracking

#### ExternalBankAccount.php
- External bank registry
- Balance tracking
- Import relationship

#### ExternalBankImport.php
- Bank transaction imports
- Duplicate detection
- Master bank integration

#### Transaction.php
- Complete transaction log
- Type-based processing
- Account updates
- Reversal support

#### Loan.php
- Loan origination
- Amortization calculations
- Payment processing
- Balance tracking
- Delinquency detection

#### LoanPayment.php
- Payment history
- Principal/interest split
- Balance after payment

#### Reconciliation.php
- Daily reconciliation records
- Check results storage
- Pass/fail tracking

#### Exception.php
- Exception tracking
- SLA management
- Resolution workflow

### 2. Services

#### ReconciliationService.php
Handles all reconciliation logic:
- 7 daily checks
- Exception creation
- System totals calculation
- Duplicate detection
- External bank import processing

#### TransactionService.php (optional)
- Bulk transaction processing
- Transaction validation
- Batch operations

#### LoanService.php (optional)
- Loan approval workflow
- Payment scheduling
- Delinquency management

### 3. FilamentPHP Resources

#### UserResource
- CRUD operations for users
- Account balance display
- Available to borrow calculation
- Suspend/activate actions
- Transaction history

#### TransactionResource
- Transaction log display
- Filtering by type, date, user
- Search functionality
- Reversal action
- Export functionality

#### LoanResource
- Loan management
- Amortization schedule display
- Payment history
- Approval workflow
- Delinquency alerts

#### ExternalBankAccountResource
- Bank account management
- Import history
- Balance tracking

#### ReconciliationResource
- Reconciliation history
- Check results display
- Exception links
- Approval workflow

#### ExceptionResource
- Exception tracking
- Assignment workflow
- Resolution documentation
- SLA monitoring

### 4. FilamentPHP Pages

#### Dashboard
- System overview
- Key metrics
- Recent activity
- Alerts and warnings

#### DailyReconciliation
- Run reconciliation
- View results
- Check details
- Create manual reconciliation

#### ImportExternalBank
- Bulk import interface
- CSV upload
- Duplicate detection
- Import preview

### 5. FilamentPHP Widgets

#### StatsOverview
- Master Bank balance
- Master Fund balance
- Total users
- Active loans
- Open exceptions

#### RecentTransactions
- Latest transactions
- Quick view
- Links to details

#### ReconciliationStatus
- Latest reconciliation
- Pass rate
- Failed checks

#### OpenExceptions
- Exception count
- Overdue count
- Critical exceptions

## Database Relationships

```
User
├── hasMany → Transaction
├── hasMany → Loan
└── hasMany → LoanPayment (through Loan)

Transaction
├── belongsTo → User
├── belongsTo → User (creator)
├── belongsTo → User (approver)
└── hasOne → LoanPayment

Loan
├── belongsTo → User
├── belongsTo → User (approver)
└── hasMany → LoanPayment

LoanPayment
├── belongsTo → Loan
└── belongsTo → Transaction

ExternalBankAccount
└── hasMany → ExternalBankImport

ExternalBankImport
├── belongsTo → ExternalBankAccount
├── belongsTo → Transaction
└── belongsTo → User (importer)

Reconciliation
├── belongsTo → User (performer)
├── belongsTo → User (approver)
└── hasMany → Exception

Exception
├── belongsTo → Transaction
├── belongsTo → Reconciliation
├── belongsTo → User (assigned_to)
└── belongsTo → User (resolved_by)
```

## Authentication & Authorization

### Roles (Optional Enhancement)
- **Super Admin**: Full system access
- **Accounting Manager**: Reconciliation approval, exception resolution
- **Accounting Staff**: Transaction entry, imports, basic operations
- **Loan Officer**: Loan management, approval
- **Auditor**: Read-only access to all data

### Policies
- UserPolicy: User management permissions
- TransactionPolicy: Transaction creation, reversal
- LoanPolicy: Loan approval, management
- ReconciliationPolicy: Reconciliation execution, approval

## API Endpoints (Optional)

```php
// Reconciliation
GET    /api/reconciliation/latest
POST   /api/reconciliation/run
GET    /api/reconciliation/summary

// User Balances
GET    /api/users/{id}/balance
GET    /api/users/{id}/available-to-borrow

// System Status
GET    /api/status
GET    /api/health

// Transactions (if needed)
GET    /api/transactions
POST   /api/transactions
GET    /api/transactions/{id}
```

## Scheduled Tasks

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    // Daily reconciliation at 11:30 PM
    $schedule->call(function () {
        app(ReconciliationService::class)->runDailyReconciliation();
    })->dailyAt('23:30');

    // Check for delinquent loans daily
    $schedule->command('loans:check-delinquency')->daily();

    // Send payment reminders
    $schedule->command('loans:payment-reminders')->dailyAt('09:00');

    // Check SLA deadlines for exceptions
    $schedule->command('exceptions:check-sla')->hourly();
}
```

## Event System (Optional)

### Events
- `TransactionCreated`
- `TransactionReversed`
- `LoanDisbursed`
- `LoanPaymentReceived`
- `ReconciliationCompleted`
- `ReconciliationFailed`
- `ExceptionCreated`
- `ExceptionResolved`
- `UserAccountSuspended`

### Listeners
- Send notifications
- Update balances
- Log activities
- Create audit records

## Queue Jobs (Optional)

- `ProcessExternalImport`
- `RunDailyReconciliation`
- `SendPaymentReminder`
- `GenerateLoanReport`
- `ProcessBulkTransactions`

## Testing Structure

### Feature Tests
- User account operations
- Transaction processing
- Loan lifecycle
- Reconciliation execution
- Exception handling

### Unit Tests
- Loan calculations
- Balance equations
- Duplicate detection
- Payment processing
- Amortization formulas

## Configuration Files

### config/cashflow.php
```php
return [
    'reconciliation' => [
        'tolerance' => env('RECONCILIATION_TOLERANCE', 0.01),
        'auto_create_exceptions' => true,
    ],
    'loans' => [
        'max_term_months' => 60,
        'min_interest_rate' => 0,
        'max_interest_rate' => 25,
    ],
    'exceptions' => [
        'sla_hours' => [
            'critical' => 1,
            'high' => 2,
            'medium' => 4,
            'low' => 24,
        ],
    ],
];
```

## File Naming Conventions

- Models: PascalCase (e.g., `User.php`, `MasterAccount.php`)
- Migrations: snake_case with timestamp (e.g., `2024_01_01_000001_create_users_table.php`)
- Resources: PascalCase with "Resource" suffix (e.g., `UserResource.php`)
- Services: PascalCase with "Service" suffix (e.g., `ReconciliationService.php`)
- Controllers: PascalCase with "Controller" suffix (e.g., `ReconciliationController.php`)
- Policies: PascalCase with "Policy" suffix (e.g., `UserPolicy.php`)

## Development Workflow

1. **Local Development**
   - Use `php artisan serve`
   - Enable debug mode
   - Use Telescope for debugging

2. **Staging**
   - Mirror production
   - Test migrations
   - Performance testing

3. **Production**
   - Disable debug mode
   - Enable caching
   - Setup monitoring
   - Regular backups

## Performance Considerations

### Database Indexes
All critical columns are indexed:
- User codes
- Transaction dates and types
- Loan statuses
- External reference IDs

### Query Optimization
- Use eager loading for relationships
- Select only needed columns
- Use database-level calculations where possible

### Caching
- Cache reconciliation results
- Cache system totals
- Cache user balances (with proper invalidation)

## Backup Strategy

### Database Backups
- Daily full backup
- Hourly incremental (production)
- 30-day retention

### File Backups
- Document uploads
- Log files
- Configuration files

---

This structure provides a scalable, maintainable foundation for the cashflow management system with clear separation of concerns and comprehensive functionality.
