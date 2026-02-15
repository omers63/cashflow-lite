# Quick Start Guide

## 🚀 Get Your Cashflow System Running in 15 Minutes

### Step 1: Create Laravel Project (2 minutes)

```bash
composer create-project laravel/laravel cashflow-system
cd cashflow-system
```

### Step 2: Install FilamentPHP v5 (2 minutes)

```bash
composer require filament/filament:"^5.0" -W
php artisan filament:install --panels
```

### Step 3: Setup Database (1 minute)

Edit `.env`:
```env
DB_CONNECTION=mysql
DB_DATABASE=cashflow_system
DB_USERNAME=root
DB_PASSWORD=your_password
```

### Step 4: Copy Project Files (3 minutes)

Copy from the provided `cashflow-system` folder:

**Migrations** → `database/migrations/`
- All migration files (9 files)

**Models** → `app/Models/`
- User.php (replace existing)
- Transaction.php
- Loan.php
- MasterAccount.php
- Plus all files from AdditionalModels.php (split them into individual files)

**Services** → `app/Services/`
- ReconciliationService.php

**Filament Resources** → `app/Filament/Resources/`
- UserResource.php
- (Other resources will be created similarly)

### Step 5: Run Migrations (1 minute)

```bash
php artisan migrate
```

### Step 6: Create Admin User (1 minute)

```bash
php artisan make:filament-user
```

Enter:
- Name: Admin
- Email: admin@example.com
- Password: password (change in production!)

### Step 7: Start the Server (1 minute)

```bash
php artisan serve
```

Visit: http://localhost:8000/admin

Login with your admin credentials.

## ✅ You're Done!

### What You Have Now

1. ✅ Complete accounting structure
2. ✅ User management system
3. ✅ Transaction logging
4. ✅ Loan management
5. ✅ Automated reconciliation
6. ✅ Exception tracking
7. ✅ FilamentPHP admin panel

### First Steps in the System

1. **Go to Users** → Create your first test user
2. **Go to External Banks** → Add an external bank account
3. **Go to Dashboard** → See system overview
4. **Try Reconciliation** → Run your first reconciliation check

### Customize Your System

Edit `.env` for:
- Database connection
- Mail settings (for notifications)
- Queue settings (for background jobs)

Edit `ReconciliationService.php` for:
- Tolerance levels ($0.01 default)
- Custom reconciliation logic

## 📚 Next Steps

1. Read the full [INSTALLATION.md](INSTALLATION.md) for production setup
2. Check [STRUCTURE.md](STRUCTURE.md) for architecture details
3. Review [README.md](README.md) for feature overview
4. Explore the FilamentPHP admin panel

## 🆘 Common Issues

### Issue: "Class not found" errors
**Solution:**
```bash
composer dump-autoload
php artisan optimize:clear
```

### Issue: Permission errors
**Solution:**
```bash
chmod -R 775 storage bootstrap/cache
```

### Issue: FilamentPHP assets not loading
**Solution:**
```bash
php artisan filament:assets
php artisan optimize:clear
```

## 🎯 What to Test

1. **Create a User**
   - Go to Users → Create
   - Enter user details
   - See auto-generated user code

2. **Make a Transaction**
   - Manually create a contribution or distribution
   - Check user balances update

3. **Run Reconciliation**
   - Dashboard → Run Reconciliation
   - All checks should PASS with fresh system

4. **Create a Loan** (after creating Transaction and Loan resources)
   - Set amount, rate, term
   - System calculates monthly payment
   - Disburse and watch balances update

## 💡 Pro Tips

- Use FilamentPHP's resource generator to create remaining resources faster
- Check the console for any errors during operations
- The system uses database transactions for safety
- All financial changes are logged for audit

## 📞 Need Help?

- Check the error log: `storage/logs/laravel.log`
- Enable debug mode in `.env`: `APP_DEBUG=true`
- Review FilamentPHP docs: https://filamentphp.com/docs
- Review Laravel docs: https://laravel.com/docs

---

**You now have a fully functional cashflow management system! 🎉**

Explore the admin panel, create some test data, and see how the reconciliation engine keeps everything balanced.
