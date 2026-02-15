# Cashflow Management System - Installation Guide

## System Requirements

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0+ or PostgreSQL 13+
- Node.js 18+ and NPM
- Laravel 11.x
- FilamentPHP 5.x

## Quick Installation

### Step 1: Create Laravel Project

```bash
composer create-project laravel/laravel cashflow-system
cd cashflow-system
```

### Step 2: Install FilamentPHP v5

```bash
composer require filament/filament:"^5.0" -W
php artisan filament:install --panels
```

### Step 3: Configure Database

Edit `.env`:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cashflow_system
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Step 4: Copy Application Files

Copy all the provided files into your Laravel project:

- Models → `app/Models/`
- Migrations → `database/migrations/`
- Filament Resources → `app/Filament/Resources/`
- Filament Pages → `app/Filament/Pages/`
- Filament Widgets → `app/Filament/Widgets/`
- Policies → `app/Policies/`
- Services → `app/Services/`
- Jobs → `app/Jobs/`

### Step 5: Run Migrations

```bash
php artisan migrate
```

### Step 6: Create Admin User

```bash
php artisan make:filament-user
```

### Step 7: Install Additional Packages (Optional but Recommended)

```bash
# For Excel export/import
composer require maatwebsite/excel

# For PDF generation
composer require barryvdh/laravel-dompdf

# For audit logging
composer require owen-it/laravel-auditing

# For activity logging
composer require spatie/laravel-activitylog
```

### Step 8: Publish Assets

```bash
php artisan filament:assets
php artisan vendor:publish --tag=filament-config
```

### Step 9: Setup Scheduler (for automated tasks)

Add to your crontab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

### Step 10: Run the Application

```bash
php artisan serve
```

Visit: `http://localhost:8000/admin`

## Post-Installation Configuration

### 1. Configure Mail (for notifications)

Edit `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=your_username
MAIL_PASSWORD=your_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@cashflow.com
MAIL_FROM_NAME="${APP_NAME}"
```

### 2. Configure Queue (for background jobs)

```bash
# Database queue
php artisan queue:table
php artisan migrate

# Run queue worker
php artisan queue:work
```

### 3. Setup Storage

```bash
php artisan storage:link
```

### 4. Cache Configuration

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

## Initial System Setup

### 1. Create External Bank Accounts

1. Go to Settings → External Banks
2. Add each external bank account with:
   - Bank Name
   - Account Number
   - Initial Balance

### 2. Create Users

1. Go to Users
2. Add each user with:
   - User ID
   - Name
   - Email
   - Initial balances (if any)

### 3. Import Historical Data (if needed)

Use the bulk import feature:
1. Go to Transactions → Import
2. Upload CSV files
3. Map columns
4. Review and confirm

### 4. Run Initial Reconciliation

1. Go to Dashboard
2. Click "Run Daily Reconciliation"
3. Review results
4. Address any issues before going live

## Troubleshooting

### Issue: Filament assets not loading
```bash
php artisan filament:assets
php artisan optimize:clear
```

### Issue: Permission denied errors
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Issue: Database connection failed
- Check `.env` database credentials
- Ensure database exists
- Verify database user has proper permissions

### Issue: Composer memory limit
```bash
COMPOSER_MEMORY_LIMIT=-1 composer install
```

## Security Checklist

- [ ] Change default admin password
- [ ] Configure proper database user permissions
- [ ] Enable HTTPS in production
- [ ] Set APP_DEBUG=false in production
- [ ] Configure proper CORS settings
- [ ] Enable rate limiting
- [ ] Setup regular backups
- [ ] Configure proper file upload limits
- [ ] Enable audit logging
- [ ] Setup two-factor authentication (optional)

## Backup Strategy

### Database Backup
```bash
# Add to crontab
0 2 * * * cd /path-to-project && php artisan backup:run
```

### Configuration
Create `config/backup.php` with proper settings for your backup destination.

## Production Deployment

### Using Laravel Forge
1. Connect your repository
2. Configure environment variables
3. Setup deployment script
4. Enable quick deploy

### Using Envoyer
1. Import project
2. Configure deployment hooks
3. Setup zero-downtime deployment
4. Configure health checks

### Manual Deployment Checklist
- [ ] Pull latest code
- [ ] Run `composer install --optimize-autoloader --no-dev`
- [ ] Run `npm install && npm run build`
- [ ] Run `php artisan migrate --force`
- [ ] Run `php artisan config:cache`
- [ ] Run `php artisan route:cache`
- [ ] Run `php artisan view:cache`
- [ ] Restart queue workers
- [ ] Clear application cache
- [ ] Run smoke tests

## Performance Optimization

### Enable Caching
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Database Indexing
Indexes are included in migrations, but verify:
```sql
SHOW INDEX FROM transactions;
SHOW INDEX FROM accounts;
```

### Queue Configuration
Use Redis for better queue performance:
```env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

## Monitoring

### Setup Laravel Telescope (Development)
```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

### Setup Laravel Horizon (Production Queues)
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan migrate
```

### Health Checks
The system includes built-in health checks at:
- `/health` - Basic health check
- `/api/reconciliation/status` - Reconciliation status

## Support & Documentation

- Laravel Docs: https://laravel.com/docs
- FilamentPHP Docs: https://filamentphp.com/docs
- System Docs: See README.md in project root

## Next Steps

After installation:
1. Review the README.md for system overview
2. Check ARCHITECTURE.md for technical details
3. Read USER_GUIDE.md for user documentation
4. Setup automated daily reconciliation
5. Train your team on the system
