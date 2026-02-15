# Cashflow Management System

A comprehensive Laravel + FilamentPHP v5 application for managing internal cashflow, user accounts, loans, and automated reconciliation.

## 🎯 Features

### Core Functionality
- **Master Account Management**: Single Master Bank and Master Fund accounts
- **User Account Management**: Individual Bank and Fund accounts per user
- **External Bank Integration**: Import and track external bank transactions
- **Loan Management**: Complete loan origination, tracking, and repayment system
- **Automated Reconciliation**: 7 critical daily checks with exception handling
- **Transaction Logging**: Complete audit trail for all financial movements
- **Exception Handling**: Automated detection and tracking of discrepancies

### Key Modules

#### 1. Account Management
- Master Bank Account (aggregates all external banks)
- Master Fund Account (tracks all user funds minus loans)
- User Bank Accounts (individual user balances)
- User Fund Accounts (lifetime contributions and repayments)
- External Bank Accounts (real bank account connections)

#### 2. Transaction System
- External bank imports with duplicate detection
- Master to User Bank distributions
- User contributions
- Loan repayments
- Loan disbursements
- Manual adjustments with approval workflow

#### 3. Loan System
- Loan application and approval
- Amortization calculation
- Payment processing with principal/interest split
- Delinquency tracking
- Early payoff support
- Complete loan history

#### 4. Reconciliation Engine
**7 Critical Daily Checks:**
1. Master Bank = Sum of External Banks
2. User Banks Total validation
3. Master Fund = User Funds - Loans
4. User Fund Accounts Total
5. Outstanding Loans Total
6. Fund Balance Equation
7. Cash Flow Balance (Master Bank = User Banks + Master Fund)

#### 5. Exception Management
- Automated exception creation for failed checks
- Severity-based SLA tracking (Critical: 1hr, High: 2hr, Medium: 4hr, Low: 24hr)
- Assignment and resolution workflow
- Complete audit trail

## 🚀 Technology Stack

- **Laravel 11.x**: Backend framework
- **FilamentPHP 5.x**: Admin panel and UI
- **MySQL/PostgreSQL**: Database
- **PHP 8.2+**: Programming language

## 📋 Prerequisites

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0+ or PostgreSQL 13+
- Node.js 18+ and NPM

## 🔧 Installation

See [INSTALLATION.md](INSTALLATION.md) for detailed installation instructions.

### Quick Start

```bash
# Clone repository
git clone <repository-url>
cd cashflow-system

# Install dependencies
composer install
npm install

# Setup environment
cp .env.example .env
php artisan key:generate

# Configure database in .env
# DB_CONNECTION=mysql
# DB_DATABASE=cashflow_system
# DB_USERNAME=your_username
# DB_PASSWORD=your_password

# Run migrations
php artisan migrate

# Create admin user
php artisan make:filament-user

# Start development server
php artisan serve
```

Visit: http://localhost:8000/admin

## 📊 Database Schema

### Main Tables
- `users` - User accounts with bank/fund balances
- `master_accounts` - Master Bank and Master Fund
- `external_bank_accounts` - External bank account registry
- `transactions` - Complete transaction log
- `external_bank_imports` - Bank import tracking with duplicate detection
- `loans` - Loan registry and tracking
- `loan_payments` - Payment history with principal/interest split
- `reconciliations` - Daily reconciliation records
- `exceptions` - Exception tracking and resolution

## 🎨 FilamentPHP Resources

### Available Resources
1. **Users** - Complete user management with account balances
2. **Transactions** - Transaction log with filtering and search
3. **Loans** - Loan management with amortization schedules
4. **External Banks** - Bank account management
5. **Reconciliations** - Daily reconciliation results
6. **Exceptions** - Exception tracking and resolution

### Custom Pages
1. **Dashboard** - System overview with key metrics
2. **Daily Reconciliation** - Run and view reconciliation checks
3. **Import External Bank** - Bulk import bank transactions
4. **Reports** - Financial reports and analytics

## 💼 Business Logic

### Transaction Flow

```
External Banks → Master Bank → User Banks → User Funds → Master Fund
                                    ↓              ↓
                                  Loans ← ← ← ← ← ←
```

### Critical Equations

1. **Master Bank = External Banks**
2. **Master Bank = User Banks + Master Fund**
3. **Master Fund = User Funds - Outstanding Loans**
4. **User Fund = Contributions + Repayments**
5. **Loan Balance = Original - Principal Paid**

## 🔒 Security Features

- Role-based access control
- Transaction approval workflows
- Audit logging with Laravel Activity Log
- Soft deletes for data recovery
- Exception-based error handling
- SLA tracking for issue resolution

## 📈 Key Features

### 1. Automated Reconciliation
- Runs daily (schedulable)
- 7 comprehensive balance checks
- Auto-creates exceptions for failures
- Email notifications for critical issues

### 2. Duplicate Detection
- Automatic duplicate prevention on bank imports
- Uses external reference IDs
- Flags duplicates without processing

### 3. Loan Management
- Automatic amortization calculations
- Principal/interest split
- Delinquency tracking
- Payment reminders

### 4. Exception Handling
- Severity-based prioritization
- SLA deadline tracking
- Assignment workflow
- Resolution documentation

## 🛠 Configuration

### Daily Reconciliation Schedule

Add to `app/Console/Kernel.php`:

```php
$schedule->call(function () {
    app(ReconciliationService::class)->runDailyReconciliation();
})->dailyAt('23:30');
```

### Email Notifications

Configure in `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-username
MAIL_PASSWORD=your-password
```

### Materiality Thresholds

Adjust in `ReconciliationService`:

```php
protected float $tolerance = 0.01; // $0.01 tolerance
```

## 📱 API Endpoints (Optional)

```php
// Get system status
GET /api/status

// Run reconciliation
POST /api/reconciliation/run

// Get reconciliation results
GET /api/reconciliation/latest

// Get user balance
GET /api/users/{id}/balance
```

## 🧪 Testing

```bash
# Run tests
php artisan test

# Run specific test
php artisan test --filter ReconciliationTest
```

## 📚 Documentation

- [Installation Guide](INSTALLATION.md)
- [User Guide](USER_GUIDE.md) - Coming soon
- [API Documentation](API.md) - Coming soon
- [Troubleshooting](TROUBLESHOOTING.md) - Coming soon

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📝 License

[Your License Here]

## 🆘 Support

For issues and questions:
- Create an issue on GitHub
- Email: support@yourcompany.com
- Documentation: https://docs.yourcompany.com

## 🔄 Version History

### v1.0.0 (Current)
- Initial release
- Complete accounting structure implementation
- Automated reconciliation
- Loan management
- Exception handling

## 🎯 Roadmap

- [ ] Multi-currency support
- [ ] Advanced reporting and analytics
- [ ] Mobile app
- [ ] Bank API integration
- [ ] Automated payment processing
- [ ] Two-factor authentication
- [ ] Advanced user permissions
- [ ] Data export/import tools
- [ ] Webhook support
- [ ] GraphQL API

## 👥 Credits

Built with:
- Laravel - https://laravel.com
- FilamentPHP - https://filamentphp.com
- TailwindCSS - https://tailwindcss.com

---

**Note**: This system handles financial data. Ensure proper security measures, regular backups, and compliance with relevant regulations.
