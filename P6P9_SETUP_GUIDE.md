# P6/P9 Tax Code Monitoring - Complete Setup Guide

This guide will walk you through setting up automated P6/P9 tax code monitoring in your Laravel application.

## What You'll Achieve

After completing this setup, your application will:

- ✅ Automatically check for HMRC P6/P9 tax code changes every day
- ✅ Parse P6/P9 notices from HMRC emails via IMAP
- ✅ Detect changes by comparing with previous records
- ✅ Send formatted email alerts to your payroll team
- ✅ Export notices to CSV for record-keeping
- ✅ Provide manual checking via Artisan command

## Prerequisites

- PHP 8.0 or higher
- Laravel 8.x, 9.x, 10.x, or 11.x
- Composer
- Email account with IMAP access (Gmail, Outlook, etc.)
- HMRC Developer account (optional, for API method)

## Installation Steps

### Step 1: Install Package

```bash
composer require shynne109/hmrc
```

### Step 2: Publish Configuration

Create `config/hmrc-p6p9.php`:

```php
<?php

return require __DIR__.'/../vendor/shynne109/hmrc/config/hmrc-p6p9.php';
```

Or manually copy from `vendor/shynne109/hmrc/config/hmrc-p6p9.php`.

### Step 3: Configure Environment

Add these settings to your `.env` file:

```env
# ==============================================
# HMRC P6/P9 Tax Code Monitoring Configuration
# ==============================================

# Enable monitoring
HMRC_P6P9_ENABLED=true

# Check method (email recommended)
HMRC_P6P9_METHOD=email

# Email Configuration
HMRC_P6P9_EMAIL_ENABLED=true
HMRC_P6P9_EMAIL_HOST=imap.gmail.com
HMRC_P6P9_EMAIL_USERNAME=payroll@yourcompany.com
HMRC_P6P9_EMAIL_PASSWORD=your-app-password-here
HMRC_P6P9_EMAIL_FOLDER=INBOX
HMRC_P6P9_EMAIL_SSL=true
HMRC_P6P9_EMAIL_FETCH=unread

# Notifications
HMRC_P6P9_NOTIFY=true
HMRC_P6P9_NOTIFY_TO=payroll@yourcompany.com,admin@yourcompany.com

# Export Settings
HMRC_P6P9_EXPORT=true
HMRC_P6P9_EXPORT_PATH=storage/app/hmrc/p6p9

# Logging
HMRC_P6P9_LOG_CHANNEL=daily
```

### Step 4: Email Setup

#### For Gmail:

1. **Enable 2-Factor Authentication**
   - Go to your Google Account settings
   - Navigate to **Security**
   - Enable **2-Step Verification**

2. **Create App Password**
   - Go to **Security** → **App Passwords**
   - Select **Mail** as the app
   - Generate password
   - Copy the 16-character password

3. **Update .env**
   ```env
   HMRC_P6P9_EMAIL_HOST=imap.gmail.com
   HMRC_P6P9_EMAIL_USERNAME=your-email@gmail.com
   HMRC_P6P9_EMAIL_PASSWORD=xxxx-xxxx-xxxx-xxxx
   ```

#### For Outlook/Office 365:

```env
HMRC_P6P9_EMAIL_HOST=outlook.office365.com
HMRC_P6P9_EMAIL_USERNAME=your-email@outlook.com
HMRC_P6P9_EMAIL_PASSWORD=your-password
```

#### For Other Providers:

Check your email provider's IMAP settings and update accordingly.

### Step 5: Register Scheduled Job

Edit `app/Console/Kernel.php`:

```php
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use HMRC\PAYE\Laravel\Jobs\CheckP6P9TaxCodesJob;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule)
    {
        // Check P6/P9 daily at 6:00 AM
        $schedule->job(new CheckP6P9TaxCodesJob)
                 ->dailyAt('06:00')
                 ->name('check-hmrc-p6p9')
                 ->withoutOverlapping()
                 ->onFailure(function () {
                     \Log::error('P6/P9 check job failed');
                 })
                 ->onSuccess(function () {
                     \Log::info('P6/P9 check job completed successfully');
                 });
    }
}
```

### Step 6: Register Artisan Command (Optional)

Edit `app/Console/Kernel.php`:

```php
protected $commands = [
    \HMRC\PAYE\Laravel\Commands\CheckP6P9Command::class,
];
```

### Step 7: Setup Cron Job

Add to your server's crontab:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Or for Windows Task Scheduler:

```batch
C:\path\to\php.exe C:\path\to\artisan schedule:run
```

### Step 8: Create Email Template

Create `resources/views/emails/p6p9-changes.blade.php`:

Copy from `vendor/shynne109/hmrc/resources/views/emails/p6p9-changes.blade.php` or use your custom template.

### Step 9: Test Email Connection

```bash
php artisan tinker
```

```php
$monitor = new \HMRC\PAYE\P6P9Monitor(
    config('hmrc.oauth2.client_id', 'test'),
    config('hmrc.oauth2.client_secret', 'test'),
    config('hmrc.oauth2.redirect_uri', 'test')
);

$parser = new \HMRC\PAYE\P6P9EmailParser($monitor);

$connected = $parser->connect(
    config('hmrc-p6p9.email.host'),
    config('hmrc-p6p9.email.username'),
    config('hmrc-p6p9.email.password'),
    config('hmrc-p6p9.email.folder'),
    config('hmrc-p6p9.email.ssl')
);

if ($connected) {
    echo "✅ Successfully connected to email!\n";
    $info = $parser->getMailboxInfo();
    print_r($info);
    $parser->disconnect();
} else {
    echo "❌ Failed to connect to email\n";
}
```

### Step 10: Manual Test Run

```bash
# Test email parsing
php artisan hmrc:check-p6p9 --method=email

# Test with notifications
php artisan hmrc:check-p6p9 --method=email --notify

# Test with CSV export
php artisan hmrc:check-p6p9 --method=email --export --notify
```

## Configuration Options

### Email Fetch Methods

```env
# Fetch unread emails only (recommended)
HMRC_P6P9_EMAIL_FETCH=unread

# Fetch today's emails
HMRC_P6P9_EMAIL_FETCH=today

# Fetch emails since specific date
HMRC_P6P9_EMAIL_FETCH=since
HMRC_P6P9_EMAIL_SINCE=01-Jan-2025
```

### Scheduling Options

```php
// Daily at specific time
$schedule->job(new CheckP6P9TaxCodesJob)->dailyAt('06:00');

// Twice daily
$schedule->job(new CheckP6P9TaxCodesJob)->twiceDaily(6, 18);

// Every 4 hours
$schedule->job(new CheckP6P9TaxCodesJob)->everyFourHours();

// Custom cron expression
$schedule->job(new CheckP6P9TaxCodesJob)->cron('0 6 * * *');
```

### Notification Settings

```env
# Send notifications
HMRC_P6P9_NOTIFY=true

# Multiple recipients (comma-separated)
HMRC_P6P9_NOTIFY_TO=payroll@company.com,admin@company.com,manager@company.com

# Send even if no changes found
HMRC_P6P9_NOTIFY_ALWAYS=false

# Send on job failure
HMRC_P6P9_NOTIFY_FAILURE=true
```

### Export Settings

```env
# Enable CSV export
HMRC_P6P9_EXPORT=true

# Export path (relative to storage/app or absolute)
HMRC_P6P9_EXPORT_PATH=storage/app/hmrc/p6p9

# Keep exports for 90 days
HMRC_P6P9_EXPORT_RETENTION=90
```

## API Method (Alternative)

If you prefer checking via HMRC API instead of email parsing:

### Step 1: Register HMRC Application

1. Go to [HMRC Developer Hub](https://developer.service.hmrc.gov.uk/)
2. Create application
3. Get Client ID and Client Secret
4. Configure redirect URI

### Step 2: Configure OAuth2

```env
# HMRC OAuth2 Credentials
HMRC_OAUTH2_CLIENT_ID=your-client-id
HMRC_OAUTH2_CLIENT_SECRET=your-client-secret
HMRC_OAUTH2_REDIRECT_URI=https://yourapp.com/oauth/callback
```

### Step 3: Enable API Method

```env
HMRC_P6P9_METHOD=api
HMRC_P6P9_API_ENABLED=true

# List of employee NINOs (comma-separated)
HMRC_P6P9_EMPLOYEES=AB123456C,CD789012E,EF345678G
```

### Step 4: Database Integration (Optional)

If you have an Employee model:

```env
HMRC_P6P9_USE_DB=true
HMRC_P6P9_MODEL=App\Models\Employee
```

Your Employee model should have a `nino` column and `active` status:

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $fillable = ['name', 'nino', 'active'];
    
    protected $casts = [
        'active' => 'boolean',
    ];
}
```

## Troubleshooting

### "Failed to connect to email"

**Check:**
- IMAP host is correct
- Username and password are correct
- For Gmail: using App Password, not regular password
- IMAP is enabled in email account
- SSL setting matches provider requirements

**Test manually:**
```bash
telnet imap.gmail.com 993
```

### "No P6/P9 emails found"

**Check:**
- HMRC emails are not in spam/junk folder
- Correct email folder configured (INBOX vs other)
- Email account receives HMRC notifications
- Fetch method is appropriate (try `today` instead of `unread`)

### "Schedule not running"

**Check:**
- Cron job is configured: `* * * * * php artisan schedule:run`
- Laravel's schedule is working: `php artisan schedule:list`
- Job is registered in Kernel.php
- Check logs: `storage/logs/laravel.log`

### "Notifications not sending"

**Check:**
- Mail is configured in `config/mail.php`
- `.env` has valid mail settings (MAIL_*)
- Recipients are valid email addresses
- Email template exists: `resources/views/emails/p6p9-changes.blade.php`

## Monitoring & Maintenance

### View Scheduled Jobs

```bash
php artisan schedule:list
```

### Check Last Run

```bash
php artisan tinker
>>> \Illuminate\Support\Facades\Cache::get('hmrc_p6p9_last_check')
```

### View Logs

```bash
tail -f storage/logs/laravel.log | grep -i p6p9
```

### Manual Run

```bash
# Run scheduled job immediately
php artisan schedule:run

# Or run specific command
php artisan hmrc:check-p6p9
```

### Clear Cache

```bash
php artisan cache:forget hmrc_p6p9_last_check
php artisan cache:clear
```

### View Exports

```bash
ls -lh storage/app/hmrc/p6p9/
```

## Production Checklist

- [ ] `.env` file secured (not in version control)
- [ ] Email credentials use App Password
- [ ] Cron job configured and running
- [ ] Notification recipients verified
- [ ] Export directory has write permissions
- [ ] Logs are monitored regularly
- [ ] Test job runs successfully
- [ ] Backup strategy for CSV exports
- [ ] Recipients receive test notification
- [ ] Error notification works (test with invalid credentials)

## Best Practices

1. **Use Dedicated Email** - Create separate email account for HMRC notices
2. **Morning Checks** - Schedule before business hours (6 AM recommended)
3. **Multiple Recipients** - Add backup contacts for notifications
4. **Regular Audits** - Review CSV exports monthly
5. **Log Monitoring** - Check logs weekly for errors
6. **Secure Credentials** - Never commit `.env` to repository
7. **Test Regularly** - Run manual checks weekly to verify system health

## Support

For issues or questions:

1. Check logs: `storage/logs/laravel.log`
2. Review HMRC documentation: [PAYE RTI](https://www.gov.uk/government/collections/real-time-information-online-internet-submissions)
3. Test email connection manually
4. Verify configuration in `config/hmrc-p6p9.php`

## Example Full Setup Script

```bash
#!/bin/bash

# Install package
composer require shynne109/hmrc

# Create config file
echo "<?php return require __DIR__.'/../vendor/shynne109/hmrc/config/hmrc-p6p9.php';" > config/hmrc-p6p9.php

# Copy example env
cat vendor/shynne109/hmrc/.env.p6p9.example >> .env

# Create export directory
mkdir -p storage/app/hmrc/p6p9
chmod 755 storage/app/hmrc/p6p9

# Copy email template
mkdir -p resources/views/emails
cp vendor/shynne109/hmrc/resources/views/emails/p6p9-changes.blade.php resources/views/emails/

# Clear cache
php artisan config:clear
php artisan cache:clear

# Test
php artisan hmrc:check-p6p9 --method=email

echo "✅ Setup complete! Edit .env with your email credentials and run: php artisan hmrc:check-p6p9"
```

## Next Steps

1. Configure your email credentials in `.env`
2. Test email connection: `php artisan tinker` + connection test
3. Run manual check: `php artisan hmrc:check-p6p9`
4. Verify notification email received
5. Monitor logs for first scheduled run
6. Review CSV exports

Your P6/P9 monitoring system is now ready! 🎉
