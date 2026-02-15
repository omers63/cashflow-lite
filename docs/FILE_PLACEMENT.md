# FILE PLACEMENT GUIDE

This document tells you exactly where to place each file from this package into your Laravel project.

## Step-by-Step File Placement

### 1. Database Migrations
Copy ALL migration files to: `database/migrations/`

Files:
- 2024_01_01_000001_create_users_table.php
- 2024_01_01_000002_create_external_bank_accounts_table.php
- 2024_01_01_000003_create_master_accounts_table.php
- 2024_01_01_000004_create_transactions_table.php
- 2024_01_01_000005_create_external_bank_imports_table.php
- 2024_01_01_000006_create_loans_table.php
- 2024_01_01_000007_create_loan_payments_table.php
- 2024_01_01_000008_create_reconciliations_table.php
- 2024_01_01_000009_create_exceptions_table.php

### 2. Models
Copy to: `app/Models/`

**IMPORTANT**: The User.php file should REPLACE your existing User.php

Files:
- User.php (REPLACE existing)
- Transaction.php (NEW)
- Loan.php (NEW)
- MasterAccount.php (NEW)

**From AdditionalModels.php**, create these SEPARATE files in `app/Models/`:
- ExternalBankAccount.php
- ExternalBankImport.php
- LoanPayment.php
- Reconciliation.php
- Exception.php

Instructions for AdditionalModels.php:
1. Open AdditionalModels.php
2. Each class needs to be in its own file
3. Copy each class to a new file named after the class
4. Example: Copy the `ExternalBankAccount` class to `ExternalBankAccount.php`

### 3. Services
Copy to: `app/Services/`

Create the directory if it doesn't exist: `mkdir app/Services`

Files:
- ReconciliationService.php

### 4. Filament Resources
Copy to: `app/Filament/Resources/`

Files from FilamentResources/ folder:
- UserResource.php
- TransactionResource.php
- LoanResource.php
- ExternalBankAccountResource.php

### 5. Filament Resource Pages
For each Resource, create its Pages folder:

#### UserResource Pages
Create: `app/Filament/Resources/UserResource/Pages/`

Create these files (using Filament command or manually):
```bash
php artisan make:filament-page ListUsers --resource=UserResource --type=ListRecords
php artisan make:filament-page CreateUser --resource=UserResource --type=CreateRecord
php artisan make:filament-page ViewUser --resource=UserResource --type=ViewRecord
php artisan make:filament-page EditUser --resource=UserResource --type=EditRecord
```

Repeat for:
- TransactionResource/Pages/
- LoanResource/Pages/
- ExternalBankAccountResource/Pages/

### 6. Filament Pages
Copy to: `app/Filament/Pages/`

Files from FilamentPages/ folder:
- Dashboard.php
- DailyReconciliation.php

### 7. Filament Widgets
Copy to: `app/Filament/Widgets/`

Files from FilamentWidgets/ folder:
- StatsOverview.php

Create additional widgets using:
```bash
php artisan make:filament-widget ReconciliationStatus
php artisan make:filament-widget RecentTransactions
```

### 8. Configuration Files
Copy to root directory:

Files:
- composer.json (MERGE with existing, don't replace)
- .env.example (Reference only, update your .env)

### 9. Documentation
Copy to root directory:

Files:
- README.md
- INSTALLATION.md
- QUICKSTART.md
- STRUCTURE.md
- FILE_PLACEMENT.md (this file)

## Quick Copy Commands

If you have the package extracted, you can use these commands:

```bash
# From your Laravel project root

# Copy migrations
cp /path/to/package/2024_*.php database/migrations/

# Copy models (careful with User.php)
cp /path/to/package/Transaction.php app/Models/
cp /path/to/package/Loan.php app/Models/
cp /path/to/package/MasterAccount.php app/Models/
# Backup your User.php first!
cp app/Models/User.php app/Models/User.php.backup
cp /path/to/package/User.php app/Models/

# Copy services
mkdir -p app/Services
cp /path/to/package/ReconciliationService.php app/Services/

# Copy Filament resources
cp /path/to/package/FilamentResources/*.php app/Filament/Resources/

# Copy Filament pages
cp /path/to/package/FilamentPages/*.php app/Filament/Pages/

# Copy Filament widgets
cp /path/to/package/FilamentWidgets/*.php app/Filament/Widgets/

# Copy documentation
cp /path/to/package/*.md .
```

## After Copying Files

1. **Handle AdditionalModels.php**:
   ```bash
   # Edit AdditionalModels.php and split each class into its own file
   # Or use the pre-split files if provided
   ```

2. **Update Composer Dependencies**:
   ```bash
   composer require filament/filament:"^5.0" -W
   composer require spatie/laravel-activitylog
   composer require maatwebsite/excel
   composer require barryvdh/laravel-dompdf
   ```

3. **Clear Cache**:
   ```bash
   php artisan optimize:clear
   composer dump-autoload
   ```

4. **Run Migrations**:
   ```bash
   php artisan migrate
   ```

5. **Create Admin User**:
   ```bash
   php artisan make:filament-user
   ```

## Verification Checklist

After placing all files, verify:

- [ ] All 9 migration files in database/migrations/
- [ ] All 9 model files in app/Models/ (including updated User.php)
- [ ] ReconciliationService.php in app/Services/
- [ ] At least 4 Resource files in app/Filament/Resources/
- [ ] Resource page folders created for each Resource
- [ ] Custom pages in app/Filament/Pages/
- [ ] Widgets in app/Filament/Widgets/
- [ ] Documentation files in root

## Common Issues

### Issue: "Class not found" errors
**Solution**: Run `composer dump-autoload`

### Issue: FilamentPHP pages not showing
**Solution**: 
1. Clear cache: `php artisan filament:cache-components`
2. Check file namespaces match directory structure

### Issue: Migration errors
**Solution**: 
1. Check database connection in .env
2. Ensure migrations are in correct order (numbered correctly)
3. Run `php artisan migrate:fresh` (WARNING: loses data)

### Issue: Models not working
**Solution**:
1. Verify all model files from AdditionalModels.php are separated
2. Check each file has correct namespace: `namespace App\Models;`
3. Run `composer dump-autoload`

## Need Help?

1. Check QUICKSTART.md for quick setup
2. Check INSTALLATION.md for detailed instructions
3. Check STRUCTURE.md for architecture overview
4. Review Laravel docs: https://laravel.com/docs
5. Review FilamentPHP docs: https://filamentphp.com/docs

---

**Pro Tip**: Start with migrations and models first, verify they work, then add Filament resources one at a time. This makes debugging easier!
